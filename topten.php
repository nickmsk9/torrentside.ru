<?php
declare(strict_types=1);

require __DIR__ . "/include/bittorrent.php";

gzip();
dbconn(false);
loggedinorreturn();

// ------- Memcached: persistent pool + проверка сервера -------
global $memcached;
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached('ts_memc_pool');
    if (empty($memcached->getServerList())) {
        $memcached->addServer('127.0.0.1', 11211);
    }
}

// ------- константа и хелперы -------
const DEFAULT_AVATAR = '/pic/default_avatar.gif';

/** Возвращает кортеж [srcEsc, fallbackEsc] — оба абсолютные и экранированные */
function avatar_src_and_fallback(?string $raw): array {
    global $DEFAULTBASEURL;
    $base = rtrim((string)($DEFAULTBASEURL ?? ''), '/');

    $raw = trim((string)$raw);
    if ($raw === '') $raw = DEFAULT_AVATAR;

    // относительное -> абсолютное
    if (!preg_match('~^(?:https?://|//|data:image/)~i', $raw)) {
        if ($raw[0] !== '/') $raw = '/' . $raw;
        $raw = $base . $raw;
    }

    $src      = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fallback = htmlspecialchars($base . DEFAULT_AVATAR, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return [$src, $fallback];
}

/** Быстрый форматтер размеров с крошечной мемоизацией на время рендера. */
function mksize_cached(int|float $bytes): string {
    static $cache = [];
    $k = (string)$bytes;
    if (isset($cache[$k])) return $cache[$k];
    $cache[$k] = mksize((float)$bytes);
    return $cache[$k];
}

/** Безопасный урл аватара с дефолтом */
function avatar_url(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return DEFAULT_AVATAR;
    // локальный путь или абсолютный URL — оба варианта ок
    // экранируем для вывода в HTML
    return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function usertable(array $rows, string $caption, string $mode): void {
    global $CURUSER;

    $metricLabel = [
        'ul'  => 'Залил',
        'dl'  => 'Скачал',
        'uls' => 'Ср. скорость раздачи',
        'dls' => 'Ср. скорость скачивания',
        'bsh' => 'Лучший по рейтингу',
        'wsh' => 'Худший по рейтингу',
    ][$mode] ?? 'Показатель';

    // Небольшой CSS (один раз на страницу — можно оставить тут)
    static $cssPrinted = false;
    if (!$cssPrinted) {
        $cssPrinted = true;
        echo <<<CSS
<style>
.topcards{margin:8px 0}
.topcard{margin:6px 0;background:#f9fafb;border:1px solid #e6e8eb;border-radius:12px;
         box-shadow:0 1px 3px rgba(0,0,0,.06);padding:10px 12px;transition:transform .08s ease, box-shadow .12s ease}
.topcard:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,.08)}
.topline{display:flex;align-items:center;gap:12px}
.rankpill{width:28px;text-align:center;font-weight:700;font-size:13px;padding:4px 0;border-radius:10px;background:#eef1f5}
.ava{width:56px;height:56px;flex:0 0 56px;border-radius:50%;object-fit:cover;box-shadow:0 0 4px rgba(0,0,0,.2)}
.name{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.meta{font-size:12px;color:#667085;margin-top:2px}
.metric{flex:0 0 auto;text-align:right}
.metric .k{font-size:12px;color:#667085}
.metric .v{font-size:16px;font-weight:800}
a.name-link{color:#1f2328;text-decoration:none}
a.name-link:hover{text-decoration:underline}
</style>
CSS;
    }

    ob_start();
    echo "<div class='topcards'>";

    $rank = 0;
    foreach ($rows as $a) {
        $rank++;

        $is_me = ((int)$CURUSER['id'] === (int)$a['userid']);
        $bg    = $is_me ? "background:#f3efe8;" : "";

        // Аватар (src + fallback)
        [$avaSrc, $avaFallback] = avatar_src_and_fallback($a['avatar'] ?? '');

        // Юзернейм (экранируем текст, затем раскрашиваем по классу)
        $usernameSafe  = htmlspecialchars((string)$a['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $usernameColor = get_user_class_color((int)($a['class'] ?? 0), $usernameSafe); // возвращает готовый HTML

        $uploaded   = (float)$a['uploaded'];
        $downloaded = (float)$a['downloaded'];
        $upspeed    = max(0.0, (float)$a['upspeed']);
        $downspeed  = max(0.0, (float)$a['downspeed']);

        // Ratio
        if ($downloaded > 0.0) {
            $ratio_val = $uploaded / $downloaded;
            $ratio_txt = number_format($ratio_val, 2, '.', '');
            $color     = get_ratio_color($ratio_val);
            if ($color) $ratio_txt = "<span style='color:{$color}'>{$ratio_txt}</span>";
        } else {
            $ratio_txt = "Inf.";
        }

        // Дата регистрации (кратко)
        $added_dt  = (string)($a['added'] ?? '');
        $t = strtotime($added_dt);
        $added_fmt = $t ? date("Y-m-d", $t) : '';

        // Главный показатель
        switch ($mode) {
            case 'ul':  $metricVal = mksize_cached($uploaded);   break;
            case 'dl':  $metricVal = mksize_cached($downloaded); break;
            case 'uls': $metricVal = mksize_cached($upspeed) . "/s";   break;
            case 'dls': $metricVal = mksize_cached($downspeed) . "/s"; break;
            case 'bsh':
            case 'wsh':
                $metricVal = ($downloaded > 0.0)
                    ? number_format($uploaded / $downloaded, 2, '.', '')
                    : "Inf.";
                break;
            default:    $metricVal = "-";
        }

        echo "
        <div class='topcard' style='{$bg}'>
          <div class='topline'>
            <div class='rankpill'>{$rank}</div>
            <div><img class='ava' src='{$avaSrc}' alt='avatar' loading='lazy' decoding='async'
                 onerror=\"this.onerror=null;this.src='{$avaFallback}';\"></div>
            <div style='flex:1 1 auto;min-width:0'>
              <div class='name'>
                <a class='name-link' href='userdetails.php?id={$a["userid"]}'>{$usernameColor}</a>
              </div>
              <div class='meta'>Рейтинг: {$ratio_txt}" . ($added_fmt ? " · с нами с {$added_fmt}" : "") . "</div>
            </div>
            <div class='metric'>
              <div class='k'>{$metricLabel}</div>
              <div class='v'>{$metricVal}</div>
            </div>
          </div>
        </div>";
    }

    echo "</div>";
    $list_html = ob_get_clean();

    begin_frame($caption);
    echo $list_html;
    end_frame();
}


// ------- вывод страницы -------
stdhead("Top 10");
begin_main_frame();

// ------- ввод: безопасный парсинг GET -------
$type    = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT, ['options'=>['default'=>1]]);
$limit   = filter_input(INPUT_GET, 'lim',  FILTER_VALIDATE_INT, ['options'=>['default'=>10]]);
$subtype = filter_input(INPUT_GET, 'subtype', FILTER_UNSAFE_RAW) ?? '';
$subtype = is_string($subtype) ? strtolower($subtype) : '';

$pu = (get_user_class() >= UC_POWER_USER);
if ($limit < 10 || $limit > 250) $limit = 10;

// ------- верхнее меню (как было) -------
echo "<p style='text-align:center'>";
if (get_user_class() >= UC_ADMINISTRATOR) {
    echo ($type === 1 ? "<b>Пользователи</b>" : "<a href='topten.php?type=1'>Пользователи</a>") . " | ";
}
echo ($type === 2 ? "<b>Торренты</b>" : "<a href='topten.php?type=2'>Торренты</a>") . " | ";
echo ($type === 3 ? "<b>Страны</b>"  : "<a href='topten.php?type=3'>Страны</a>");
echo "</p>";

// ------- карты сортировок и заголовков (для пользователей) -------
$available = [
    "ul"  => "uploaded DESC",
    "dl"  => "downloaded DESC",
    "uls" => "upspeed DESC",
    "dls" => "downspeed DESC",
    "bsh" => "uploaded / downloaded DESC",
    "wsh" => "uploaded / downloaded ASC, downloaded DESC",
];
$titleMap = [
    "ul"  => "Top %d заливающих",
    "dl"  => "Top %d качающих",
    "uls" => "Top %d быстрейших заливающих <span class='small'>(среднее, включая период неактивности)</span>",
    "dls" => "Top %d быстрейших качающих <span class='small'>(среднее, включая период неактивности)</span>",
    "bsh" => "Top %d лучших раздающих <span class='small'>(минимум 1 GB скачано)</span>",
    "wsh" => "Top %d худших раздающих <span class='small'>(минимум 1 GB скачано)</span>",
];

// ------- показываем топ пользователей только админам (как у вас) -------
if ($type === 1 && get_user_class() >= UC_ADMINISTRATOR) {
    $showAll = ($limit === 10);
    $nowSql  = "UNIX_TIMESTAMP(NOW())";

    foreach ($available as $key => $order) {
        if ($showAll || $subtype === $key) {

            $extra = ($key === "bsh" || $key === "wsh") ? "AND downloaded > 1073741824" : "";

            $cacheKey = "topten:users:{$key}:{$limit}";
            $rows = $memcached->get($cacheKey);

            if (!is_array($rows)) {
                $query = "
                    SELECT
                        id  AS userid,
                        username,
                        avatar,
                        added,
                        uploaded,
                        downloaded,
						class,
                        uploaded   / ( {$nowSql} - UNIX_TIMESTAMP(added) ) AS upspeed,
                        downloaded / ( {$nowSql} - UNIX_TIMESTAMP(added) ) AS downspeed
                    FROM users
                    WHERE enabled = 'yes' {$extra}
                    ORDER BY {$order}
                    LIMIT {$limit}";
                $res = sql_query($query);

                $rows = [];
                if ($res) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $rows[] = [
                            'userid'     => (int)$row['userid'],
                            'username'   => (string)$row['username'],
                            'avatar'     => (string)($row['avatar'] ?? ''),
                            'added'      => (string)$row['added'],
                            'uploaded'   => (float)$row['uploaded'],
                            'downloaded' => (float)$row['downloaded'],
							'class'      => (int)($row['class'] ?? 0),
                            'upspeed'    => is_null($row['upspeed']) ? 0.0 : (float)$row['upspeed'],
                            'downspeed'  => is_null($row['downspeed']) ? 0.0 : (float)$row['downspeed'],
                        ];
                    }
                }
                $ttl = ($rows ? 300 : 60);
                $memcached->set($cacheKey, $rows, $ttl);
            }

            $caption = sprintf($titleMap[$key], $limit);
            if ($showAll && $pu) {
                $caption .= " <span class='small'> - [<a href='?type=1&amp;lim=100&amp;subtype={$key}'>Top 100</a>] - [<a href='?type=1&amp;lim=250&amp;subtype={$key}'>Top 250</a>]</span>";
            }

            usertable($rows, $caption, $key);
        }
    }
}

// другие типы (2 — торренты, 3 — страны) — без изменений

end_main_frame();
stdhead();
stdfoot();
