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

    $currentUserId = (int)($CURUSER['id'] ?? 0);
    $currentUserClass = (int)($CURUSER['class'] ?? 0);
    $currentBonus = (int)($CURUSER['bonus'] ?? 0);
    $currentInvites = (int)($CURUSER['invites'] ?? 0);
    $currentUploaded = (int)($CURUSER['uploaded'] ?? 0);
    $currentDownloaded = (int)($CURUSER['downloaded'] ?? 0);

    $ratioText = '---';
    $ratioValue = null;
    if ($currentDownloaded > 0) {
        $ratioValue = $currentUploaded / $currentDownloaded;
        $ratioText = $ratioValue > 100 ? '100+' : number_format($ratioValue, 2, '.', '');
    } elseif ($currentUploaded > 0) {
        $ratioText = 'Inf.';
    }

    $ratioColorValue = '#000000';
    if ($ratioValue !== null) {
        $ratioColorValue = (string)get_ratio_color($ratioValue);
    }
    $ratioColor = htmlspecialchars($ratioColorValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ratioHtml = '<font color="' . $ratioColor . '">' . htmlspecialchars($ratioText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</font>';

    $adminPanel = [
        'show' => false,
        'unchecked_torrents' => 0,
        'pending_users' => 0,
        'unread' => 0,
        'dashboard_url' => 'admincp.php',
        'queue_url' => 'modded.php',
        'pending_users_url' => 'usersearch.php?st=2',
        'inbox_url' => 'message.php',
        'staff_url' => 'staffmess.php',
    ];

    if ($currentUserId > 0 && $currentUserClass >= UC_MODERATOR) {
        $staffGlobal = tracker_cache_remember(
            tracker_cache_key('sidebar', 'staff-global'),
            60,
            static function (): array {
                $pendingUsers = 0;
                $uncheckedTorrents = 0;

                $pendingRes = sql_query("SELECT COUNT(*) AS total FROM users WHERE status = 'pending'");
                if ($pendingRes && ($row = mysqli_fetch_assoc($pendingRes))) {
                    $pendingUsers = max(0, (int)($row['total'] ?? 0));
                }

                $queueRes = sql_query("SELECT COUNT(*) AS total FROM torrents WHERE modded = 'no'");
                if ($queueRes && ($row = mysqli_fetch_assoc($queueRes))) {
                    $uncheckedTorrents = max(0, (int)($row['total'] ?? 0));
                }

                return [
                    'unchecked_torrents' => $uncheckedTorrents,
                    'pending_users' => $pendingUsers,
                ];
            }
        );
        if (!is_array($staffGlobal)) {
            $staffGlobal = [];
        }

        $adminPanel['show'] = true;
        $adminPanel['unchecked_torrents'] = max(0, (int)($staffGlobal['unchecked_torrents'] ?? 0));
        $adminPanel['pending_users'] = max(0, (int)($staffGlobal['pending_users'] ?? 0));
        $adminPanel['unread'] = (int)tracker_cache_remember(
            tracker_cache_ns_key(tracker_message_cache_namespace($currentUserId), 'unread-count'),
            45,
            static function () use ($currentUserId): int {
                $res = sql_query(
                    "SELECT COUNT(*) AS unread_cnt
                     FROM messages
                     WHERE receiver = " . sqlesc($currentUserId) . " AND unread = 'yes'"
                ) or sqlerr(__FILE__, __LINE__);

                $row = mysqli_fetch_assoc($res) ?: ['unread_cnt' => 0];
                return (int)$row['unread_cnt'];
            }
        );
    }

    $bestBlock = [
        'loggedin' => $currentUserId > 0,
        'title' => 'Быстрый старт',
        'ratio_html' => $ratioHtml,
        'bonus' => number_format($currentBonus, 0, '.', ' '),
        'invites' => $currentInvites,
        'bonus_url' => 'mybonus.php',
        'invites_url' => $currentUserId > 0 ? ('invite.php?id=' . $currentUserId) : 'signup.php',
        'primary_url' => 'browse.php',
        'primary_label' => 'Открыть новинки',
        'secondary_url' => 'rules.php',
        'secondary_label' => 'Правила',
        'hint' => 'Правила, FAQ и свежие раздачи под рукой.',
    ];

    if ($currentUserId > 0) {
        $bestBlock['title'] = 'Хороший темп';
        $bestBlock['secondary_url'] = 'userdetails.php?id=' . $currentUserId;
        $bestBlock['secondary_label'] = 'Мой профиль';
        $bestBlock['hint'] = 'Рейтинг, бонусы и инвайты под рукой.';

        if ($currentBonus >= 100) {
            $bestBlock['title'] = 'Бонусный ход';
            $bestBlock['primary_url'] = 'mybonus.php';
            $bestBlock['primary_label'] = 'Обменять бонусы';
            $bestBlock['hint'] = 'Баланс уже годится для обмена.';
        } elseif ($currentInvites > 0) {
            $bestBlock['title'] = 'Можно звать';
            $bestBlock['primary_url'] = 'invite.php?id=' . $currentUserId;
            $bestBlock['primary_label'] = 'Выдать инвайт';
            $bestBlock['hint'] = 'Есть инвайты для новых людей.';
        } elseif ($ratioValue !== null && $ratioValue < 1.0) {
            $bestBlock['title'] = 'Подтяни рейтинг';
            $bestBlock['primary_url'] = 'browse.php';
            $bestBlock['primary_label'] = 'Найти раздачи';
            $bestBlock['hint'] = 'Новинки и сидирование помогут рейтингу.';
        } elseif ($ratioText === '---') {
            $bestBlock['title'] = 'Пора начать';
            $bestBlock['primary_url'] = 'browse.php';
            $bestBlock['primary_label'] = 'Выбрать первую раздачу';
            $bestBlock['hint'] = 'После первых скачиваний здесь появится ритм.';
        }
    }

    $siteBirthdaysCount = (int)tracker_cache_remember(
        tracker_cache_ns_key('online', 'birthdays-count', date('m-d')),
        12 * 3600,
        static function (): int {
            $sql = "
                SELECT COUNT(*) AS total
                FROM users
                WHERE
                    DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                    OR birthday LIKE CONCAT('%', DATE_FORMAT(CURDATE(), '-%m-%d'))
            ";
            $res = sql_query($sql);
            $row = ($res && ($data = mysqli_fetch_assoc($res))) ? $data : [];
            return max(0, (int)($row['total'] ?? 0));
        }
    );

    $socialBlock = [
        'loggedin' => $currentUserId > 0,
        'url' => 'friends.php',
        'friends_count' => 0,
        'online_count' => 0,
        'pending_count' => 0,
        'birthdays_count' => $siteBirthdaysCount,
        'friends_preview' => [],
        'pending_preview' => [],
    ];

    if ($currentUserId > 0) {
        $socialData = tracker_cache_remember(
            tracker_cache_key('sidebar', 'social', 'u' . $currentUserId, date('Ymd')),
            60,
            static function () use ($currentUserId, $onlineCut): array {
                $uid = (int)$currentUserId;
                $cutoff = (int)$onlineCut;

                $summaryRes = sql_query(
                    "SELECT
                        (SELECT COUNT(*) FROM friends WHERE userid = {$uid} AND status = 'yes') AS friends_count,
                        (SELECT COUNT(*) FROM friends WHERE friendid = {$uid} AND status = 'pending') AS pending_count,
                        (SELECT COUNT(DISTINCT s.uid)
                         FROM friends f
                         INNER JOIN sessions s ON s.uid = f.friendid
                         WHERE f.userid = {$uid} AND f.status = 'yes' AND s.time > {$cutoff}) AS online_count"
                );
                $summary = ($summaryRes && ($row = mysqli_fetch_assoc($summaryRes))) ? $row : [];

                $friendsPreview = [];
                $friendsRes = sql_query(
                    "SELECT
                        u.id,
                        MAX(u.username) AS username,
                        MAX(u.class) AS class,
                        MAX(u.last_access) AS last_access,
                        MAX(CASE WHEN s.time > {$cutoff} THEN 1 ELSE 0 END) AS online_now
                    FROM friends f
                    INNER JOIN users u ON u.id = f.friendid
                    LEFT JOIN sessions s ON s.uid = u.id AND s.time > {$cutoff}
                    WHERE f.userid = {$uid} AND f.status = 'yes'
                    GROUP BY u.id
                    ORDER BY online_now DESC, last_access DESC
                    LIMIT 3"
                );
                while ($friendsRes && ($row = mysqli_fetch_assoc($friendsRes))) {
                    $friendId = (int)($row['id'] ?? 0);
                    $friendName = trim((string)($row['username'] ?? ''));
                    if ($friendId <= 0 || $friendName === '') {
                        continue;
                    }
                    $friendsPreview[] = [
                        'id' => $friendId,
                        'username' => $friendName,
                        'class' => (int)($row['class'] ?? 0),
                    ];
                }

                $pendingPreview = [];
                $pendingRes = sql_query(
                    "SELECT u.id, u.username, u.class
                    FROM friends f
                    INNER JOIN users u ON u.id = f.userid
                    WHERE f.friendid = {$uid} AND f.status = 'pending'
                    ORDER BY f.id DESC
                    LIMIT 2"
                );
                while ($pendingRes && ($row = mysqli_fetch_assoc($pendingRes))) {
                    $senderId = (int)($row['id'] ?? 0);
                    $senderName = trim((string)($row['username'] ?? ''));
                    if ($senderId <= 0 || $senderName === '') {
                        continue;
                    }
                    $pendingPreview[] = [
                        'id' => $senderId,
                        'username' => $senderName,
                        'class' => (int)($row['class'] ?? 0),
                    ];
                }

                return [
                    'friends_count' => max(0, (int)($summary['friends_count'] ?? 0)),
                    'online_count' => max(0, (int)($summary['online_count'] ?? 0)),
                    'pending_count' => max(0, (int)($summary['pending_count'] ?? 0)),
                    'friends_preview' => $friendsPreview,
                    'pending_preview' => $pendingPreview,
                ];
            }
        );

        if (!is_array($socialData)) {
            $socialData = [];
        }

        $socialBlock['friends_count'] = max(0, (int)($socialData['friends_count'] ?? 0));
        $socialBlock['online_count'] = max(0, (int)($socialData['online_count'] ?? 0));
        $socialBlock['pending_count'] = max(0, (int)($socialData['pending_count'] ?? 0));

        foreach (($socialData['friends_preview'] ?? []) as $row) {
            $friendId = (int)($row['id'] ?? 0);
            $friendName = (string)($row['username'] ?? '');
            $friendClass = (int)($row['class'] ?? 0);
            if ($friendId <= 0 || $friendName === '') {
                continue;
            }
            $socialBlock['friends_preview'][] = [
                'link_html' => "<a href='userdetails.php?id={$friendId}'>" . get_user_class_color($friendClass, $friendName) . "</a>",
            ];
        }

        foreach (($socialData['pending_preview'] ?? []) as $row) {
            $senderId = (int)($row['id'] ?? 0);
            $senderName = (string)($row['username'] ?? '');
            $senderClass = (int)($row['class'] ?? 0);
            if ($senderId <= 0 || $senderName === '') {
                continue;
            }
            $socialBlock['pending_preview'][] = [
                'link_html' => "<a href='userdetails.php?id={$senderId}'>" . get_user_class_color($senderClass, $senderName) . "</a>",
            ];
        }
    }

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

        $smarty->assign('adminPanel', $adminPanel);
        echo $smarty->fetch('partials/admin_block.tpl');

        $smarty->assign('bestBlock', $bestBlock);
        echo $smarty->fetch('partials/random_block.tpl');

        $smarty->assign('socialBlock', $socialBlock);
        echo $smarty->fetch('partials/friends_block.tpl');

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
