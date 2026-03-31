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
    $ONLINE_CACHE_KEY      = tracker_cache_ns_key('online', 'list');
    $TODAY_CACHE_KEY       = tracker_cache_ns_key('online', 'today');
    $ONLINE_TTL            = 60;
    $TODAY_TTL             = 300;
    $ONLINE_WINDOW_MINUTES = 5;
    $ONLINE_PREVIEW_LIMIT  = 8;
    $TODAY_PREVIEW_LIMIT   = 8;

    $now       = time();
    $onlineCut = $now - ($ONLINE_WINDOW_MINUTES * 60);

    $onlineData = tracker_cache_remember($ONLINE_CACHE_KEY, $ONLINE_TTL, static function () use ($onlineCut, $ONLINE_PREVIEW_LIMIT): array {
        $cutoff = (int)$onlineCut;
        $previewLimit = (int)$ONLINE_PREVIEW_LIMIT;
        $moderatorClass = (int)UC_MODERATOR;

        $statsQuery = sql_query(
            "SELECT
                COUNT(DISTINCT CASE WHEN uid > 0 THEN uid END) AS members_count,
                COUNT(DISTINCT CASE WHEN uid > 0 AND class >= {$moderatorClass} THEN uid END) AS staff_count,
                COUNT(DISTINCT CASE WHEN uid = 0 AND ip <> '' THEN ip END) AS guests_count
            FROM sessions
            WHERE time > {$cutoff}"
        );
        $statsRow = ($statsQuery && ($row = mysqli_fetch_assoc($statsQuery))) ? $row : [];

        $previewQuery = sql_query(
            "SELECT
                uid,
                MAX(username) AS username,
                MAX(class) AS class
            FROM sessions
            WHERE time > {$cutoff} AND uid > 0
            GROUP BY uid
            ORDER BY MAX(class) DESC, MAX(username) ASC
            LIMIT {$previewLimit}"
        );

        $preview = [];
        while ($previewQuery && ($row = mysqli_fetch_assoc($previewQuery))) {
            $uid = (int)($row['uid'] ?? 0);
            $username = trim((string)($row['username'] ?? ''));
            if ($uid <= 0 || $username === '') {
                continue;
            }

            $preview[] = [
                'id' => $uid,
                'username' => $username,
                'class' => (int)($row['class'] ?? 0),
            ];
        }

        return [
            'members_count' => max(0, (int)($statsRow['members_count'] ?? 0)),
            'staff_count' => max(0, (int)($statsRow['staff_count'] ?? 0)),
            'guests_count' => max(0, (int)($statsRow['guests_count'] ?? 0)),
            'preview' => $preview,
            'updated_at' => time(),
        ];
    });
    if (!is_array($onlineData)) {
        $onlineData = [];
    }

    $onlineMembers = max(0, (int)($onlineData['members_count'] ?? 0));
    $onlineStaff = min($onlineMembers, max(0, (int)($onlineData['staff_count'] ?? 0)));
    $onlineUsers = max(0, $onlineMembers - $onlineStaff);
    $onlineGuests = max(0, (int)($onlineData['guests_count'] ?? 0));
    $onlinePreview = [];

    foreach (($onlineData['preview'] ?? []) as $row) {
        $uid = (int)($row['id'] ?? 0);
        $username = (string)($row['username'] ?? '');
        $class = (int)($row['class'] ?? 0);
        if ($uid <= 0 || $username === '') {
            continue;
        }

        $onlinePreview[] = [
            'link_html' => "<a href='userdetails.php?id={$uid}' class='online'>" . get_user_class_color($class, $username) . "</a>",
        ];
    }

    $online = [
        'total' => $onlineMembers + $onlineGuests,
        'members_count' => $onlineMembers,
        'staff_count' => $onlineStaff,
        'users_count' => $onlineUsers,
        'guests_count' => $onlineGuests,
        'preview' => $onlinePreview,
        'more_count' => max(0, $onlineMembers - count($onlinePreview)),
        'window_minutes' => $ONLINE_WINDOW_MINUTES,
        'updated_label' => date('H:i', (int)($onlineData['updated_at'] ?? $now)),
    ];

    // ====================== БЛОК «ПОСЕТИЛИ СЕГОДНЯ» ======================
    $todayData = tracker_cache_remember($TODAY_CACHE_KEY, $TODAY_TTL, static function () use ($TODAY_PREVIEW_LIMIT): array {
        $previewLimit = (int)$TODAY_PREVIEW_LIMIT;

        $countQuery = sql_query("SELECT COUNT(*) AS total FROM users WHERE last_access >= CURDATE()");
        $countRow = ($countQuery && ($row = mysqli_fetch_assoc($countQuery))) ? $row : [];

        $previewQuery = sql_query(
            "SELECT id, username, class, last_access
            FROM users
            WHERE last_access >= CURDATE()
            ORDER BY last_access DESC
            LIMIT {$previewLimit}"
        );

        $preview = [];
        while ($previewQuery && ($row = mysqli_fetch_assoc($previewQuery))) {
            $uid = (int)($row['id'] ?? 0);
            $username = trim((string)($row['username'] ?? ''));
            if ($uid <= 0 || $username === '') {
                continue;
            }

            $preview[] = [
                'id' => $uid,
                'username' => $username,
                'class' => (int)($row['class'] ?? 0),
                'last_access' => (string)($row['last_access'] ?? ''),
            ];
        }

        return [
            'count' => max(0, (int)($countRow['total'] ?? 0)),
            'preview' => $preview,
            'updated_at' => time(),
        ];
    });
    if (!is_array($todayData)) {
        $todayData = [];
    }

    $todayPreview = [];
    foreach (($todayData['preview'] ?? []) as $row) {
        $uid = (int)($row['id'] ?? 0);
        $username = (string)($row['username'] ?? '');
        $class = (int)($row['class'] ?? 0);
        $lastAccess = strtotime((string)($row['last_access'] ?? '')) ?: null;
        if ($uid <= 0 || $username === '') {
            continue;
        }

        $todayPreview[] = [
            'link_html' => "<a href='userdetails.php?id={$uid}'>" . get_user_class_color($class, $username) . "</a>",
            'time_label' => $lastAccess ? date('H:i', $lastAccess) : 'сегодня',
        ];
    }

    $today = [
        'count' => max(0, (int)($todayData['count'] ?? 0)),
        'preview' => $todayPreview,
        'more_count' => max(0, max(0, (int)($todayData['count'] ?? 0)) - count($todayPreview)),
        'updated_label' => date('H:i', (int)($todayData['updated_at'] ?? $now)),
    ];

    // ====================== ДНИ С МОМЕНТА ОТКРЫТИЯ САЙТА ======================
    $siteOpenedAt = new DateTimeImmutable('2025-01-01');
    $todayAt = new DateTimeImmutable('today');
    $siteAge = [
        'days' => max(0, (int)$siteOpenedAt->diff($todayAt)->format('%a')),
        'since' => $siteOpenedAt->format('d.m.Y'),
    ];

    // ====================== РЕНДЕР БЛОКОВ ======================
    if (isset($smarty)) {
        // Онлайн
        $smarty->assign('online', $online);
        $smarty->assign('today', $today);
        $smarty->assign('siteAge', $siteAge);
        $onlineBlockKey = tracker_cache_key(
            'tpl',
            'online-block',
            md5(json_encode([$online, $today, $siteAge], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string)$online['total'])
        );
        echo tracker_cache_render($onlineBlockKey, 60, static function () use ($smarty): string {
            return (string)$smarty->fetch('partials/online_block.tpl');
        });

        // Админка (только для UC_SYSOP)
        $is_admin = isset($CURUSER['class']) && (int)$CURUSER['class'] >= UC_SYSOP;
        $smarty->assign('CURUSER', $CURUSER);
        $smarty->assign('is_admin', $is_admin);
        echo $smarty->fetch('partials/admin_block.tpl');

        // Рандомное число (JS-генерация)
        echo $smarty->fetch('partials/random_block.tpl');
		
        // === БЛОК «Именинники» (PHP 8.1 + Memcached) ===
        $BIRTH_TTL = 12 * 3600;
        $birthKey = tracker_cache_ns_key('online', 'birthdays', date('m-d'));
        $birthdays = tracker_cache_remember($birthKey, $BIRTH_TTL, static function (): array {
            $sql = "
                SELECT id, username, class, gender
                FROM users
                WHERE
                    DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                    OR birthday LIKE CONCAT('%', DATE_FORMAT(CURDATE(), '-%m-%d'))
                LIMIT 100
            ";
            $q = sql_query($sql);
            $rows = [];
            while ($q && ($row = mysqli_fetch_assoc($q))) {
                $id = (int)$row['id'];
                $username = (string)$row['username'];
                $class = isset($row['class']) ? (int)$row['class'] : 0;
                $gender = isset($row['gender']) ? (int)$row['gender'] : 0;

                $genderIcon = null;
                if ($gender === 2) {
                    $genderIcon = ['alt' => 'Девушка', 'src' => 'pic/ico_f.gif'];
                } elseif ($gender === 1) {
                    $genderIcon = ['alt' => 'Парень', 'src' => 'pic/ico_m.gif'];
                }

                $rows[] = [
                    'id' => $id,
                    'name_html' => get_user_class_color($class, $username),
                    'genderIcon' => $genderIcon,
                ];
            }
            return $rows;
        });
        if (!is_array($birthdays)) {
            $birthdays = [];
        }

        // Проброс в Smarty и рендер:
        $smarty->assign('birthdays_title', 'Именинники');
        $smarty->assign('birthdays', $birthdays);
        echo tracker_cache_render(
            tracker_cache_key(
                'tpl',
                'birthdays-block',
                md5(json_encode($birthdays, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: date('m-d'))
            ),
            $BIRTH_TTL,
            static function () use ($smarty): string {
                return (string)$smarty->fetch('partials/birthdays_block.tpl');
            }
        );

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
