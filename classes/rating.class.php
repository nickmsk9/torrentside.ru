<?php
declare(strict_types=1);

class rating
{
    /** Версия для отладки */
    public string $version = '0.14'; // было 0.13
    /** Текущий торрент */
    public int $torrentid;

    /** Локальный кэш, чтобы не дёргать БД по нескольку раз за запрос */
    private ?array $meta = null; // ['ratingsum'=>int,'numratings'=>int,'owner'=>int,'user_vote'=>?int]

    public function __construct(int $torrentid)
    {
        $this->torrentid = (int)$torrentid;
    }

    /** ===================== ПУБЛИЧНЫЕ МЕТОДЫ (сигнатуры/HTML не менял) ===================== */

    public function getRatingBar(): string
    {
        global $CURUSER;

        [$ratingAvg, $votes] = $this->getTorrentRating();
        $votes   = (int)$votes;
        $num     = number_format($ratingAvg, 1);
        $widthPx = (float)$ratingAvg * 25;

        $user_vote = $this->getUserVote();
        $voted     = ($user_vote !== 0);

        // Большая цифра справа — оставил как есть
        $rate_str = "<span style=\"color:#FF6600;font-size:22px;font-weight:bold;\" title=\"Рейтинг: {$num} / 5 ({$votes})\">{$num}</span>";

        if ($this->AllowedToRate() && $CURUSER) {
            $rating_bar = "<table width=\"100%\" cellpadding=\"3\" cellspacing=\"0\">
        <tr><td width=\"125\" class=\"clear\">
        <div class=\"rating\"><ul class=\"star-rating\">
        <li class=\"current-rating\" style=\"width:{$widthPx}px;\"></li>";
            for ($i = 1; $i <= 5; $i++) {
                $rating_bar .= "<li><a href=\"javascript:;\" class=\"r{$i}-star\" onclick=\"SE_TorrentRate('{$i}', '{$this->torrentid}');\" title=\"Оценить на {$i}\"></a></li>";
            }
            $rating_bar .= "</ul></div></td><td class=\"clear\" align=\"center\">{$rate_str}</td></tr></table>";
        } else {
            $rating_bar = "<table width=\"100%\" cellpadding=\"3\" cellspacing=\"0\">
        <tr><td width=\"125\" class=\"clear\">
        <div class=\"rating\">
        <ul class=\"star-rating\">
        <li class=\"current-rating\" style=\"width:{$widthPx}px;\"></li>
        </ul></div></td><td align=\"center\">{$rate_str}</td></tr></table>";

            if ($voted) {
                $rating_bar .= "<div><a href=\"javascript:;\" style=\"color:#999;font-size:9px;\" onclick=\"SE_TorrentRatingDelete('{$this->torrentid}');\">Удалить оценку</a></div>";
            }
        }

        return $rating_bar;
    }

    public function getUserVote(): int
    {
        $meta = $this->loadMeta();
        return (int)($meta['user_vote'] ?? 0);
    }

    public function getTorrentRating(): array
    {
        $meta = $this->loadMeta();
        $numratings = (int)($meta['numratings'] ?? 0);
        if ($numratings > 0) {
            $avg = round(((int)$meta['ratingsum']) / $numratings, 2);
            return [$avg, $numratings];
        }
        return [0.0, 0];
    }

    public function getUserRating($vote_num)
    {
        if ($vote_num >= 1 && $vote_num <= 5) {
            $rating = (int)$vote_num * 25;
            return "<div class=\"rating\"><ul class=\"star-rating\"><li class=\"current-rating\" style=\"width:{$rating}px;\">{$rating}</li></ul></div>";
        }
        return "<span style=\"color:red;\">N/A</span>";
    }

    public function AllowedToRate(): bool
    {
        global $CURUSER;
        if (!$this->torrentid || !$CURUSER) return false;

        $meta   = $this->loadMeta();
        $owner  = (int)($meta['owner'] ?? 0);
        $uv     = $meta['user_vote'] ?? null;

        // Разрешено, если пользователь не владелец и ещё не голосовал
        return ($CURUSER['id'] != $owner) && ($uv === null || (int)$uv === 0);
    }

    /** ===================== ВНУТРЕННЕЕ: ЕДИНЫЙ ЗАПРОС + МЯГКИЙ КЕШ ===================== */

    /**
     * Грузит агрегаты торрента + голос текущего пользователя
     * Делает РОВНО ОДИН запрос к БД, далее хранит в $this->meta.
     * Плюс мягкий Memcached: отдельный ключ на агрегаты и на голос пользователя.
     */
    private function loadMeta(): array
    {
        if ($this->meta !== null) {
            return $this->meta;
        }

        global $mysqli, $CURUSER, $memcached;

        $tid = $this->torrentid;
        if ($tid <= 0) {
            $this->meta = ['ratingsum'=>0,'numratings'=>0,'owner'=>0,'user_vote'=>0];
            return $this->meta;
        }

        $uid = (int)($CURUSER['id'] ?? 0);

        // ---- Попытка достать из Memcached (если подключен)
        $aggKey  = "rtg:agg:t:$tid"; // ratingsum,numratings,owner
        $voteKey = $uid ? "rtg:vote:t:$tid:u:$uid" : null;

        $agg = null;
        $uv  = null;

        if (isset($memcached) && $memcached instanceof Memcached) {
            $agg = $memcached->get($aggKey);
            if ($voteKey) {
                $uv = $memcached->get($voteKey);
            }
        }

        // ---- Если нет в кэше — тянем одним запросом
        if (!is_array($agg) || ($uid && $uv === false)) {
            // Один SELECT по torrents + скалярная подзапрос на user_vote (если пользователь есть)
            if ($uid) {
                $sql = "
                    SELECT 
                        t.ratingsum, t.numratings, t.owner,
                        (SELECT r.rating FROM ratings r WHERE r.user = ? AND r.torrent = t.id LIMIT 1) AS user_vote
                    FROM torrents t
                    WHERE t.id = ?
                    LIMIT 1
                ";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ii', $uid, $tid);
            } else {
                $sql = "
                    SELECT t.ratingsum, t.numratings, t.owner, NULL AS user_vote
                    FROM torrents t
                    WHERE t.id = ?
                    LIMIT 1
                ";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('i', $tid);
            }

            $stmt->execute();
            $stmt->bind_result($ratingsum, $numratings, $owner, $user_vote);
            if ($stmt->fetch()) {
                $agg = [
                    'ratingsum'  => (int)$ratingsum,
                    'numratings' => (int)$numratings,
                    'owner'      => (int)$owner,
                ];
                $uv = $user_vote !== null ? (int)$user_vote : null;
            } else {
                // Торрент не найден — безопасные значения
                $agg = ['ratingsum'=>0,'numratings'=>0,'owner'=>0];
                $uv  = 0;
            }
            $stmt->close();

            // Прокладываем в кэш (короткий TTL — чтобы не нарушать консистентность при голосовании)
            if (isset($memcached) && $memcached instanceof Memcached) {
                $memcached->set($aggKey, $agg, 90);      // агрегаты живут чуть дольше
                if ($voteKey) $memcached->set($voteKey, $uv ?? 0, 60);
            }
        }

        $this->meta = [
            'ratingsum'  => (int)($agg['ratingsum']  ?? 0),
            'numratings' => (int)($agg['numratings'] ?? 0),
            'owner'      => (int)($agg['owner']      ?? 0),
            'user_vote'  => ($uv === null ? 0 : (int)$uv), // наружу возвращаем int (как было)
        ];

        return $this->meta;
    }
}
