<?php
require "include/bittorrent.php";
require_once __DIR__ . '/include/multitracker.php';
gzip();
dbconn(true);
stdhead($tracker_lang['war']);

/** =========================
 *  Сервисные настройки
 *  ========================= */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Amsterdam');
}

$homeMemcached = tracker_cache_instance();

/** =========================
 *  Ключи кэша/локов
 *  ========================= */
const NS                 = 'home:';                         // namespace
const MC_KEY_CFG         = NS . 'cfg:active_super_loto';
const MC_TTL_CFG         = 300;
const MC_LOCK_KEY_PREFIX = NS . 'loto:lock:';               // + YYYY-MM-DD
const MC_LOCK_SHORT      = 300;                              // 5 мин
const MC_NEWS_TTL        = 600;
const MC_LOTO_STATE_TTL  = 60;
const MC_NEW_TORRENTS_TTL = 300;
const MC_HEROES_TTL      = 900;
const MC_STATS_TTL       = 600;

function home_truncate_text(string $text, int $limit = 400): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit)) . '...';
}

function home_cache_key(...$parts): string {
    return tracker_cache_ns_key('home', ...$parts);
}

/** =========================
 *  Время и границы
 *  ========================= */
$secTillMidnight = strtotime('tomorrow 00:00:00') - time();
$day   = (int)date('N');   // 1..7 (1-пн, 7-вс)
$hour  = (int)date('H');
$today = date('Y-m-d');

$lotoStateKey = home_cache_key('loto-state', $today);
$lotoState = tracker_cache_remember($lotoStateKey, MC_LOTO_STATE_TTL, static function () use ($today): array {
    $res = sql_query(
        "SELECT
            COALESCE((SELECT `value` FROM `config` WHERE `config`='active_super_loto' LIMIT 1), 0) AS active_loto,
            EXISTS(SELECT 1 FROM `super_loto_winners` WHERE `date` = " . sqlesc($today) . ") AS already,
            EXISTS(SELECT 1 FROM `super_loto_tickets` WHERE `active` = 0) AS has_tickets"
    ) or sqlerr(__FILE__, __LINE__);

    $row = mysqli_fetch_assoc($res) ?: [];

    return [
        'active_loto' => (int)($row['active_loto'] ?? 0),
        'already' => (int)($row['already'] ?? 0),
        'hasTickets' => (int)($row['has_tickets'] ?? 0),
    ];
});

$active_loto = (int)($lotoState['active_loto'] ?? 0);
$already = (int)($lotoState['already'] ?? 0);
$hasTickets = (int)($lotoState['hasTickets'] ?? 0);

if ($already === 1 && $active_loto !== 0) {
    sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
    tracker_cache_set(MC_KEY_CFG, 0, MC_TTL_CFG);
    tracker_cache_delete($lotoStateKey);
    $active_loto = 0;
}

/** =========================
 *  Логика запуска розыгрыша
 *  ========================= */
if ($day === 7 && $hour >= 18 && $active_loto === 0 && $already === 0 && $hasTickets === 1) {
    $lockKey = MC_LOCK_KEY_PREFIX . $today;

    $lockAcquired = $homeMemcached instanceof Memcached ? $homeMemcached->add($lockKey, 1, MC_LOCK_SHORT) : true;
    if ($lockAcquired) {
        // поднять флаг на время розыгрыша
        sql_query("UPDATE `config` SET `value`=1 WHERE `config`='active_super_loto'");
        tracker_cache_set(MC_KEY_CFG, 1, MC_TTL_CFG);
        tracker_cache_delete($lotoStateKey);

        try {
            // ВАЖНО: корректный файл с логикой розыгрыша
            include __DIR__ . '/get_loto_winners1.php';

            // суточный лок, чтобы повторно не запустили сегодня
            tracker_cache_set($lockKey, 1, $secTillMidnight);
            tracker_cache_delete($lotoStateKey);
        } finally {
            // в любом случае опускаем флаг
            sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
            tracker_cache_set(MC_KEY_CFG, 0, MC_TTL_CFG);
            tracker_cache_delete($lotoStateKey);
        }
    }
} elseif ($day !== 7 && $active_loto === 1) {
    // вне воскресенья — флаг должен быть опущен
    sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
    tracker_cache_set(MC_KEY_CFG, 0, MC_TTL_CFG);
    tracker_cache_delete($lotoStateKey);
}

/** =========================
 *  UI: рамка для постера (оставлено)
 *  ========================= */
?>
<style>
  .glass-frame {
    display:block; text-align:left; width:fit-content;
    padding:6px; border-radius:14px;
    background:rgba(255,255,255,0.1);
    border:1px solid rgba(255,255,255,0.3);
    box-shadow:0 4px 12px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.2);
    backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); margin:0;
  }
  .glass-frame img { display:block; border-radius:10px; width:200px; height:300px; object-fit:cover; }
</style>
<script>
(function(){
  const onMove=e=>{
    const el=e.currentTarget, r=el.getBoundingClientRect();
    const x=((e.clientX??(e.touches?.[0]?.clientX||0))-r.left)/r.width*100;
    const y=((e.clientY??(e.touches?.[0]?.clientY||0))-r.top)/r.height*100;
    const tiltX=(50-y)/30, tiltY=(x-50)/30;
    el.style.transform=`perspective(700px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
  };
  const reset=e=>{ e.currentTarget.style.transform='none' };
  for (const el of document.querySelectorAll('.glass-wrap')){
    el.addEventListener('pointermove', onMove, {passive:true});
    el.addEventListener('pointerleave', reset);
    el.addEventListener('touchmove', onMove, {passive:true});
    el.addEventListener('touchend', reset);
  }
})();
function SmileIT(smile, form, text) {
  document.forms[form].elements[text].value += " " + smile + " ";
  document.forms[form].elements[text].focus();
}
function mySubmit(){ setTimeout(()=>document.shbox.reset(),10); }
</script>
<?php

/** =========================
 *  Блок новостей (оптим.)
 *  =========================
 *  Раньше тянули 10 штук и брали первую; теперь — сразу LIMIT 1.
 *  Кэшируем ровно то, что выводим.
 */
$news = tracker_cache_remember(home_cache_key('news', 'latest'), MC_NEWS_TTL, static function () {
    $q = sql_query("SELECT id, body FROM news ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $news = $q && mysqli_num_rows($q) ? mysqli_fetch_assoc($q) : null;
    if (!empty($news)) {
        $news['body_html'] = format_comment((string)$news['body']);
    }
    return $news;
});

$content = '';
if (!empty($news)) {
    $adminTools = '';
    if (get_user_class() >= UC_ADMINISTRATOR) {
        $adminTools = ' - <font class="small">[<a class="altlink" href="news.php?action=edit&newsid='
            . (int)$news['id'] . '&returnto=' . urlencode($_SERVER['PHP_SELF'])
            . '"><b>Редактировать</b></a>]</font>';
    }
    $content .= '<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">';
    $content .= '<div>' . ($news['body_html'] ?? format_comment((string)$news['body'])) . '</div>';
    $content .= '</td></tr></table>';
}

/** =========================
 *  Чат (как было)
 *  ========================= */
$content .= <<<HTML
<br>
<iframe src="shoutbox.php" width="95%" height="200" align="center" frameborder="0" name="sbox" marginwidth="0" marginheight="0" style="border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></iframe>
<br>
HTML;

if (!empty($CURUSER)) {
    $content .= <<<HTML
<form action="shoutbox.php" method="get" target="sbox" name="shbox" onSubmit="mySubmit()" style="text-align: center; margin-top: 10px;">
    <input type="text" name="shbox_text" size="100" placeholder="Введите сообщение..." style="
        padding: 6px; border: 1px solid #ccc; border-radius: 6px;
        font-family: Verdana, sans-serif; font-size: 12px;">
    <input type="hidden" name="sent" value="yes">
    <input type="submit" value="Сказать" style="
        padding: 6px 12px; margin-left: 6px; border: none;
        background: linear-gradient(to right, #5b5bff, #9e72ff);
        color: #fff; border-radius: 6px; font-weight: bold; cursor: pointer;">
    &nbsp;
    <a href="shoutbox.php" target="sbox" style="
        padding: 6px 10px; background: #eee; border-radius: 6px;
        text-decoration: none; font-weight: bold; font-size: 12px; color: #333; margin-left: 5px;">Обновить</a>
    <br><br>
    <fieldset style="
        display: inline-block; border: 1px solid #ccc; padding: 10px; margin-top: 10px;
        border-radius: 10px; background: #f5f5ff; max-width: 90%; text-align: left;">
        <legend style="font-weight: bold;">Смайлы</legend>
HTML;

    $smiles = [
        'smile2','laugh','wink','noexpression','wavecry','whistle','yes','no','love','blink','rolleyes','devil',
        'baby','ras','evilmad','hmm','kiss','unsure','shifty','nugget','snap','shutup','evo','shit','spidey','ike'
    ];
    $codes = [
        ':smile:',':lol:',';-)',':-|',':wavecry:',':whistle:',':yes:',':no:',':love:',':blink:',':rolleyes:',':devil:',
        ':baby:',':ras:',':evilmad:',':hmm:',':kiss:',':unsure:',':shifty:',':nugget:',':snap:',':shutup:',':evo:',':shit:',':spidey:',':ike:'
    ];
    foreach ($smiles as $i => $img) {
        $code = htmlspecialchars($codes[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content .= "<a href=\"javascript: SmileIT('$code','shbox','shbox_text')\" style=\"display:inline-flex;margin:2px;text-decoration:none\">"
            . tracker_smiley_html($codes[$i], $img . '.gif', ['class' => 'smiley-emoji--picker', 'title' => $codes[$i]])
            . "</a>";
    }
    $content .= "</fieldset></form>";
}

/** =========================
 *  Заголовок и вывод блока «Новости и чат»
 *  ========================= */
$cls = (int)($CURUSER['class'] ?? 0);
$newschat = "Новости и чат";
if ($cls >= UC_MODERATOR) {
    $newschat .= ' - <font class="small">[<a class="altlink" href="news.php"><b>Создать</b></a>]</font>';
}

begin_frame($newschat, "100", false, 10, true);
echo $content;
end_frame();


////////////////////////////////////////////////
// ОПРОС — только для залогиненных с классом >= UC_USER
$cls  = get_user_class();                     // int|null
$uid  = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
$can_show_poll = ($uid > 0 && $cls !== null && $cls >= UC_USER);

if ($can_show_poll) {
    $is_mod = ($cls >= UC_MODERATOR);

    $votetitle = "Опрос";
    if ($is_mod) {
        $votetitle .= ' - <font class="small">[<a href="makepoll.php?returnto=/index.php"><b>Создать</b></a>]</font>';
    }

    $pollBlockKey = home_cache_key('poll-block', 'mod' . (int)$is_mod);
    $poll_block = tracker_cache_render($pollBlockKey, 300, static function () use ($votetitle): string {
        ob_start();

        begin_frame($votetitle);
        ?>
        <?php if (!defined('POLL_JS_LOADED')): ?>
            <?php define('POLL_JS_LOADED', true); ?>
            <script src="js/poll.core.js" defer></script>
            <style>
              /* центрирование без таблицы */
              #poll_container{ text-align:left; max-width:720px; margin:0 auto; }
              #loading_poll{ text-align:center; padding:6px 0; }
            </style>
        <?php endif; ?>

        <script>
          // безопасный старт: ждём jQuery-алиас $jq и DOM
          (function waitPollInit(){
            if (typeof window.$jq === "function" && document.readyState !== "loading") {
              $jq(function(){ if (typeof loadpoll === "function") loadpoll(); });
            } else {
              setTimeout(waitPollInit, 50);
            }
          })();
        </script>

        <div id="poll_container">
          <div id="loading_poll" style="display:none"></div>
          <noscript><b>Для отображения опроса включите JavaScript.</b></noscript>
        </div>
        <?php
        end_frame();

        return (string)ob_get_clean();
    });

    echo $poll_block;
}
// ВАЖНО: никаких return/exit — ниже могут быть другие блоки
////////////////////////////////////////////////

////////////////////////////////////////////////


//////////////////////////////////////////////////////////////
// NEW: «Новые поступления» — оптимизированный блок

// Полезные индексы для этого блока:
// 1) основной под сортировку и фильтры:
//   CREATE INDEX idx_torrents_vis_mod_id ON torrents (visible, moderated, id);
// 2) дополнительный пригодится, если здесь будет равенство/IN по category:
//   CREATE INDEX idx_torrents_vis_mod_cat_id ON torrents (visible, moderated, category, id);

$isModeratorHome = get_user_class() >= UC_MODERATOR;
$newTorrentsBlock = tracker_cache_render(
    home_cache_key('new-torrents', 'mod' . (int)$isModeratorHome),
    MC_NEW_TORRENTS_TTL,
    static function (): string {
        $sql = "
        SELECT
            id,
            name,
            size,
            image1,
            leechers,
            seeders,
            times_completed,
            added,
            free,
            SUBSTRING(descr, 1, 900) AS descr
        FROM torrents
        WHERE category <> 31
          AND visible = 'yes'
          AND moderated = 'yes'
        ORDER BY id DESC
        LIMIT 5
    ";
        $result = sql_query($sql) or sqlerr(__FILE__, __LINE__);

        ob_start();
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id']              = (int)$row['id'];
            $row['size']            = (int)$row['size'];
            $row['leechers']        = (int)$row['leechers'];
            $row['seeders']         = (int)$row['seeders'];
            $row['times_completed'] = (int)$row['times_completed'];
            $row['free']            = (int)$row['free'];

            if (isset($row['leechers_net']))        $row['leechers']        += (int)$row['leechers_net'];
            if (isset($row['seeders_net']))         $row['seeders']         += (int)$row['seeders_net'];
            if (isset($row['times_completed_net'])) $row['times_completed'] += (int)$row['times_completed_net'];

            $size = mksize($row['size']);
            $nameSafe = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $freeLabel = $row['free'] > 0 ? ($row['free'] . '%') : '0%';

            $img = '';
            if (!empty($row['image1'])) {
                $imgUrl = htmlspecialchars((string)$row['image1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $img = "<div class='glass-frame'><img loading='lazy' src='{$imgUrl}' alt='Постер'></div><br>";
            }

            $descFormatted = format_comment(home_truncate_text((string)$row['descr'], 420));

            echo "
<div class=\"c\">
<div class=\"c1\">
    <div class=\"c2\">
        <div class=\"c3\">
            <div class=\"c4\">
                <div class=\"c5\">
                    <div class=\"c6\">
                        <div class=\"c7\">
                            <div class=\"c8\">
                                <div class=\"ci\" align=\"left\">
                                    <div class=\"c_tit\">{$nameSafe}</div>
                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"3\">
                                        <tr valign=\"top\">
                                            <td width=\"100%\" class=\"text\">
                                                <div align=\"center\"><a href=\"details.php?id={$row['id']}\">{$img}</a></div>
                                                <div>{$descFormatted}</div>
                                            </td>
                                        </tr>
                                    </table><br>
                                    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr>
                                        <td>
                                            <div class=\"sbg\"><div class=\"ss1\"><div class=\"rat st\">
                                                {$row['added']}
                                                <div class=\"cl\"></div>
                                            </div></div></div>
                                        </td>
                                        <td><div class=\"ss2\"></div></td>
                                    </tr></table>
                                    <div class=\"s\"><div class=\"s1\"><div class=\"s2\">
                                        <div class=\"st\">
                                            <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                                                <tr>
                                                    <td>
                                                        <b>
                                                            <font color=\"green\"><img src=\"pic/up.gif\" title=\"Раздают\" /> {$row['seeders']}</font> |
                                                            <font color=\"red\"><img src=\"pic/ardown.gif\" title=\"Качают\" /> {$row['leechers']}</font> |
                                                            <font color=\"orange\">Скачавших: {$row['times_completed']}</font> |
                                                            <font color=\"#999\">Размер: {$size}</font> |
                                                            <font color=\"#999\">Free: {$freeLabel}</font>
                                                        </b>
                                                    </td>
                                                    <td align=\"right\" class=\"r\">
                                                        <a href=\"details.php?id={$row['id']}\"><font color=\"#ADFF2F\">Подробнее/Скачать</font></a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
";
        }

        return (string)ob_get_clean();
    }
);

$blocktitle = "Новые поступления" . ($isModeratorHome
    ? "<font class=\"small\"> - [<a class=\"altlink\" href=\"upload.php\"><b>Загрузить</b></a>]</font>"
    : "<font class=\"small\"> - (торренты без сидов - не отображаются!)</font>");
echo '<div style="margin:0 0 8px 0;padding:0 2px;font-weight:bold;">' . $blocktitle . '</div>';
echo $newTorrentsBlock;
//////////////////////////////////////////////////////////////



////////////////////////////////////////////////
// «Наши герои» — оптимизированный блок

$heroestitle = "Наши герои";
begin_frame($heroestitle);

$data = tracker_cache_remember(home_cache_key('heroes-data'), MC_HEROES_TTL, static function (): array {
    $data = [
        'bonus'    => [],
        'karma'    => [],
        'comments' => [],
    ];

    // BONUS TOP 10 (users.bonus — DECIMAL(10,2))
    $qBonus = "
        SELECT id, class, username, bonus
        FROM users
        WHERE bonus > 1
        ORDER BY bonus DESC
        LIMIT 10
    ";
    $rs = sql_query($qBonus) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']    = (int)$row['id'];
        $row['class'] = (int)$row['class'];
        $data['bonus'][] = $row;
    }

    // KARMA TOP 10 (users.karma — INT)
    $qKarma = "
        SELECT id, class, username, karma
        FROM users
        WHERE karma >= 1
        ORDER BY karma DESC
        LIMIT 10
    ";
    $rs = sql_query($qKarma) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']    = (int)$row['id'];
        $row['class'] = (int)$row['class'];
        $row['karma'] = (int)$row['karma'];
        $data['karma'][] = $row;
    }

    // COMMENTS TOP 10:
    // сначала дешёвая агрегация по comments.user, затем JOIN к users
    // (достаточно одного индекса comments(user), без дублей)
    $qComments = "
        SELECT u.id, u.class, u.username, t.num_comm
        FROM (
            SELECT `user` AS uid, COUNT(*) AS num_comm
            FROM comments
            GROUP BY `user`
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ) AS t
        JOIN users u ON u.id = t.uid
        ORDER BY t.num_comm DESC
    ";
    $rs = sql_query($qComments) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']       = (int)$row['id'];
        $row['class']    = (int)$row['class'];
        $row['num_comm'] = (int)$row['num_comm'];
        $data['comments'][] = $row;
    }

    return $data;
});

// безопасная обёртка имени
$h = static function (?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '
<table width="100%" border="0" cellspacing="0" cellpadding="2">
<tr><td align="center" class="embedded">
<table width="100%" class="main" border="1" cellspacing="0" cellpadding="10">
<tr>
';

/* ========================== Блок Бонусов ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Бонусы</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['bonus'] as $b) {
    $uid  = (int)$b['id'];
    $cls  = (int)$b['class'];
    $name = $h($b['username']);
    $bonusVal = is_numeric($b['bonus']) ? number_format((float)$b['bonus'], 2, '.', '') : $h((string)$b['bonus']);
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $bonusVal . '</td>
    </tr>';
}

echo '</table></td>';

/* ========================== Блок Кармы ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Карма</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['karma'] as $k) {
    $uid  = (int)$k['id'];
    $cls  = (int)$k['class'];
    $name = $h($k['username']);
    $karmaVal = (int)$k['karma'];
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $karmaVal . '</td>
    </tr>';
}

echo '</table></td>';

/* ========================== Блок Комментариев ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Комментарии</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['comments'] as $c) {
    $uid  = (int)$c['id'];
    $cls  = (int)$c['class'];
    $name = $h($c['username']);
    $num  = (int)$c['num_comm'];
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $num . '</td>
    </tr>';
}

echo '</table></td></tr></table></td></tr></table>';

end_frame();

////////////////////////////////////////////////


//////////////////////////////////////////////////////
begin_frame("Статистика");

global $tracker_lang, $ss_uri, $maxusers, $CURUSER, $use_sessions, $mysqli;

$stats = tracker_cache_remember(home_cache_key('stats', 'v2'), MC_STATS_TTL, static function () use ($mysqli): array {
    $query = "
        SELECT
            (SELECT COUNT(*) FROM users) AS registered,
            (SELECT COUNT(*) FROM torrents) AS torrents,
            (SELECT COUNT(*) FROM peers WHERE seeder = 'yes') AS seeders,
            (SELECT COUNT(*) FROM peers WHERE seeder = 'no') AS leechers,
            (SELECT COUNT(*) FROM users WHERE gender = '1') AS male,
            (SELECT COUNT(*) FROM users WHERE gender = '2') AS female,
            (SELECT SUM(size) FROM torrents) AS total_size,
            (SELECT SUM(downloaded) FROM users) AS totaldl,
            (SELECT SUM(uploaded) FROM users) AS totalul
    ";

    $res = mysqli_query($mysqli, $query) or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_assoc($res);

    $externalSeeders = 0;
    $externalLeechers = 0;
    $externalCompleted = 0;
    if (function_exists('multitracker_schema_ready') && multitracker_schema_ready()) {
        $extRes = mysqli_query(
            $mysqli,
            "SELECT
                COALESCE(SUM(ext.external_seeders), 0) AS external_seeders,
                COALESCE(SUM(ext.external_leechers), 0) AS external_leechers,
                COALESCE(SUM(ext.external_completed), 0) AS external_completed
             FROM (
                SELECT
                    torrent_id,
                    MAX(CASE WHEN status = 'ok' THEN seeders ELSE 0 END) AS external_seeders,
                    MAX(CASE WHEN status = 'ok' THEN leechers ELSE 0 END) AS external_leechers,
                    MAX(CASE WHEN status = 'ok' THEN completed ELSE 0 END) AS external_completed
                FROM torrent_external_tracker_stats
                GROUP BY torrent_id
             ) AS ext"
        );

        if ($extRes instanceof mysqli_result) {
            $extRow = mysqli_fetch_assoc($extRes) ?: [];
            $externalSeeders = (int)($extRow['external_seeders'] ?? 0);
            $externalLeechers = (int)($extRow['external_leechers'] ?? 0);
            $externalCompleted = (int)($extRow['external_completed'] ?? 0);
        }
    }

    $seeders = (int)$row["seeders"];
    $leechers = (int)$row["leechers"];
    $totalDownloaded = (int)($row["totaldl"] ?? 0);
    $totalUploaded = (int)($row["totalul"] ?? 0);

    return [
        "registered" => number_format((int)$row["registered"]),
        "torrents" => number_format((int)$row["torrents"]),
        "seeders" => number_format($seeders),
        "leechers" => number_format($leechers),
        "external_seeders" => number_format($externalSeeders),
        "external_leechers" => number_format($externalLeechers),
        "external_completed" => number_format($externalCompleted),
        "male" => number_format((int)$row["male"]),
        "female" => number_format((int)$row["female"]),
        "total_size" => (int)($row["total_size"] ?? 0),
        "test" => mksize($totalUploaded + $totalDownloaded),
    ];
});

$registered = $stats["registered"];
$torrents = $stats["torrents"];
$seeders_fmt = $stats["seeders"];
$leechers_fmt = $stats["leechers"];
$external_seeders_fmt = $stats["external_seeders"];
$external_leechers_fmt = $stats["external_leechers"];
$external_completed_fmt = $stats["external_completed"];
$male = $stats["male"];
$female = $stats["female"];
$total_size = $stats["total_size"];
$test = $stats["test"];

$seeders = (int)str_replace(",", "", $seeders_fmt);
$leechers = (int)str_replace(",", "", $leechers_fmt);
$externalSeeders = (int)str_replace(",", "", $external_seeders_fmt);
$externalLeechers = (int)str_replace(",", "", $external_leechers_fmt);
$externalCompleted = (int)str_replace(",", "", $external_completed_fmt);

// Суммарное количество пиров
$peers = $seeders + $leechers + $externalSeeders + $externalLeechers;
$seedersTotal = $seeders + $externalSeeders;
$leechersTotal = $leechers + $externalLeechers;
$peers_fmt = number_format($peers);
$seeders_total_fmt = number_format($seedersTotal);
$leechers_total_fmt = number_format($leechersTotal);
$local_peer_split_fmt = number_format($seeders) . " / " . number_format($leechers);
$external_peer_split_fmt = $external_seeders_fmt . " / " . $external_leechers_fmt;

// Вывод таблицы
print("<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">
<td align=\"center\" style=\"border: none;\">
<table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">
<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"10\">
  <tr>
    <td width=\"50%\" align=\"center\" style=\"border: none;\">
<table class=\"main\" border=\"0\" width=\"100%\">
<tr><td class=\"lol\" align=left><b>Мест на трекере</b></td><td align=right class=\"lol\">$maxusers</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['users_registered']."</b></td><td class=\"lol\" align=right class=\"b\">$registered</td></tr>
<tr><td class=\"lol\" align=left><b>Парней</b></td><td align=right class=\"lol\">$male</td></tr>
<tr><td class=\"lol\" align=left><b>Девушек</b></td><td align=right class=\"lol\">$female</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_torrents']."</b></td><td class=\"lol\" align=right class=\"a\">$torrents</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_peers']."</b></td><td class=\"lol\" align=right class=\"a\">$peers_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_seeders']."</b></td><td class=\"lol\" align=right class=\"b\">$seeders_total_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_leechers']."</b></td><td class=\"lol\" align=right class=\"a\">$leechers_total_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>Локальные сидеры / личеры</b></td><td class=\"lol\" align=right class=\"a\">$local_peer_split_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>Мультитрекер сидеры / личеры</b></td><td class=\"lol\" align=right class=\"a\">$external_peer_split_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>Мультитрекер скачали</b></td><td class=\"lol\" align=right class=\"a\">$external_completed_fmt</td></tr>
<tr><td class=\"lol\" align=left><b>Всего траффика</b></td><td class=\"lol\" align=right class=\"a\">$test</td></tr>
<tr><td class=\"lol\" align=left><b>Общий размер раздач</b></td><td class=\"lol\" align=right class=\"a\">".mksize($total_size)."</td></tr>
");

print("</table></td>");
print("</table></td></tr></table>");

end_frame();


stdfoot();







?>
