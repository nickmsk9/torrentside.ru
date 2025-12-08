<?php
// Запрещаем прямой доступ к файлу
if (!defined('UC_SYSOP')) {
    die('Direct access denied.');
}

// Если боковые блоки разрешены
if ($blockhide !== 'right' && $blockhide !== 'all') {
    echo "<td valign='top' width='190' style='border:none'>";

    // Подключаем Smarty и необходимые глобальные переменные
    require_once dirname(__DIR__, 2) . '/include/smarty_init.php';
    global $smarty, $CURUSER, $memcached, $tracker_lang;

    // ====================== БЛОК «КТО ОНЛАЙН» ======================
    $ONLINE_CACHE_KEY = 'online:list:v2'; // ключ кэша «онлайн»
    $TODAY_CACHE_KEY  = 'today:list:v2';  // ключ кэша «сегодня»
    $ONLINE_TTL       = 30;               // время жизни кэша онлайн (сек)
    $TODAY_TTL        = 60;               // время жизни кэша «сегодня» (сек)

    $now       = time();
    $onlineCut = $now - 300; // считаем онлайн за последние 5 минут

    // Получаем список из кэша или из БД
    $result = $memcached->get($ONLINE_CACHE_KEY);
    if ($result === false) {
        $q = sql_query("
            SELECT s.uid, s.username, s.class, s.ip
            FROM sessions AS s
            WHERE s.time > " . (int)$onlineCut . "
            ORDER BY s.class DESC, s.username ASC
        ");
        $result = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $result[] = $row;
        }
        $memcached->set($ONLINE_CACHE_KEY, $result, $ONLINE_TTL);
    }

    // Разделяем пользователей и гостей
    $title_who   = [];
    $seenUserIds = [];
    $guestIps    = [];
    $staff = $users = $guests = 0;

    foreach ($result as $row) {
        $uid   = (int)($row['uid'] ?? 0);
        $uname = (string)($row['username'] ?? '');
        $class = (int)($row['class'] ?? 0);
        $ip    = (string)($row['ip'] ?? '');

        if ($uid > 0) {
            // Пользователь (дедупликация по id)
            if (!isset($seenUserIds[$uid]) && $uname !== '') {
                $safeName = get_user_class_color($class, $uname); // имя с цветом по классу
                $title_who[] = "<a href='userdetails.php?id={$uid}' class='online'><b>{$safeName}</b></a>";
                ($class >= UC_MODERATOR) ? $staff++ : $users++;
                $seenUserIds[$uid] = true;
            }
        } elseif ($ip !== '' && !isset($guestIps[$ip])) {
            // Гость (по уникальному IP)
            $guests++;
            $guestIps[$ip] = true;
        }
    }

    $total = $staff + $users + $guests; // всего онлайн

    // ====================== БЛОК «ПОСЕТИЛИ СЕГОДНЯ» ======================
    $res = $memcached->get($TODAY_CACHE_KEY);
    if ($res === false) {
        $sql = "
            SELECT id, username, class
            FROM users
            WHERE last_access >= CURDATE()
            ORDER BY username ASC
        ";
        $query = sql_query($sql);
        $res = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $res[] = $row;
        }
        $memcached->set($TODAY_CACHE_KEY, $res, $TODAY_TTL);
    }

    // Формируем ссылки для списка «сегодня»
    $links = [];
    foreach ($res as $arr) {
        $uid   = (int)$arr['id'];
        $uname = (string)$arr['username'];
        $class = (int)$arr['class'];
        $links[] = "<a href='userdetails.php?id={$uid}'>" . get_user_class_color($class, $uname) . "</a>";
    }

    $usersactivetoday = count($links);

    // ====================== ДНИ С МОМЕНТА ОТКРЫТИЯ САЙТА ======================
    $site_open_date = '2025-01-01';
    $daysAlive = (int) floor((strtotime('today') - strtotime($site_open_date)) / 86400);
    $daysAlive = max(0, $daysAlive);

    // ====================== РЕНДЕР БЛОКОВ ======================
    if (isset($smarty)) {
        // Онлайн
        $smarty->assign('online', [
            'total' => $total,
            'users' => $title_who,
        ]);
        $smarty->assign('today', [
            'count' => $usersactivetoday,
            'users' => $links,
        ]);
        $smarty->assign('daysAlive', $daysAlive);
        echo $smarty->fetch('partials/online_block.tpl');

        // Админка (только для UC_SYSOP)
        $is_admin = isset($CURUSER['class']) && (int)$CURUSER['class'] >= UC_SYSOP;
        $smarty->assign('CURUSER', $CURUSER);
        $smarty->assign('is_admin', $is_admin);
        echo $smarty->fetch('partials/admin_block.tpl');

        // Рандомное число (JS-генерация)
        echo $smarty->fetch('partials/random_block.tpl');
		
        // === БЛОК «Именинники» (PHP 8.1 + Memcached) ===
$BIRTH_TTL  = 6 * 3600;                  // 6 часов
$birthKey   = 'birthdays:block:v1:' . date('m-d');
$birthdays  = $memcached->get($birthKey);

if ($birthdays === false || !is_array($birthdays)) {
    // Универсальный запрос: подойдёт и для DATE/DATETIME, и для строкового поля вида 'YYYY-mm-dd'
    $sql = "
        SELECT id, username, class, gender
        FROM users
        WHERE
            DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
            OR birthday LIKE CONCAT('%', DATE_FORMAT(CURDATE(), '-%m-%d'))
        LIMIT 100
    ";
    $q = sql_query($sql);
    $birthdays = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $id       = (int)$row['id'];
        $username = (string)$row['username'];
        $class    = isset($row['class']) ? (int)$row['class'] : 0;
        $gender   = isset($row['gender']) ? (int)$row['gender'] : 0;

        // Цветное имя по классу (HTML):
        $nameHtml = get_user_class_color($class, $username);

        // Иконка пола (если есть):
        $genderIcon = null;
        if ($gender === 2) {
            $genderIcon = ['alt' => 'Девушка', 'src' => 'pic/ico_f.gif'];
        } elseif ($gender === 1) {
            $genderIcon = ['alt' => 'Парень',  'src' => 'pic/ico_m.gif'];
        }

        $birthdays[] = [
            'id'         => $id,
            'name_html'  => $nameHtml,   // уже раскрашено (HTML)
            'genderIcon' => $genderIcon, // либо null
        ];
    }
    $memcached->set($birthKey, $birthdays, $BIRTH_TTL);
}

// Проброс в Smarty и рендер:
$smarty->assign('birthdays_title', 'Именинники');
$smarty->assign('birthdays', $birthdays);
echo $smarty->fetch('partials/birthdays_block.tpl');

    }

    echo "</td>";
}
?>
</td></tr></table>

<?php
// ====================== ФУТЕР САЙТА ======================
require_once dirname(__DIR__, 2) . '/include/smarty_init.php';
global $smarty, $tracker_lang;

// Метрики генерации страницы
$seconds     = timer() - $tstart;
$phptime     = $seconds - $querytime;
$query_time  = $querytime;
$percentphp  = number_format(($phptime / $seconds) * 100, 2);
$percentsql  = number_format(($query_time / $seconds) * 100, 2);
$seconds     = substr($seconds, 0, 8);
$phpversion  = phpversion();

// Готовим текст «страница сгенерирована...»
$page_generated = sprintf(
    $tracker_lang["page_generated"],
    $seconds,
    $queries,
    $percentphp,
    $percentsql
);

// Рендер футера через Smarty
if (isset($smarty)) {
    $smarty->assign('page_generated', $page_generated);
    $smarty->assign('phpversion', $phpversion);
    echo $smarty->fetch('partials/footer_block.tpl');
}

// Закрытие HTML-документа
echo "</body></html>\n";

