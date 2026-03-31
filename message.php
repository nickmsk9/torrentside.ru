<?php
declare(strict_types=1);

require_once "include/bittorrent.php";

gzip();
dbconn();
loggedinorreturn();
parked();

define('PM_DELETED', 0);
define('PM_INBOX', 1);
define('PM_SENTBOX', -1);
define('PM_PER_PAGE', 25);

function message_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function message_box_normalize(int $mailbox): int
{
    return $mailbox === PM_SENTBOX ? PM_SENTBOX : PM_INBOX;
}

function message_box_label(int $mailbox): string
{
    return $mailbox === PM_SENTBOX ? 'Отправленные' : 'Входящие';
}

function message_box_url(int $mailbox, array $extra = []): string
{
    $params = array_merge(
        [
            'action' => 'viewmailbox',
            'box' => message_box_normalize($mailbox),
        ],
        $extra
    );

    return 'message.php?' . http_build_query($params);
}

function message_safe_returnto(string $raw, int $fallbackBox = PM_INBOX): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return message_box_url($fallbackBox);
    }

    if (preg_match('~^https?://[^/]+/(.*)$~i', $raw, $m)) {
        $raw = $m[1];
    }

    $raw = ltrim($raw, '/');
    if ($raw === '' || !str_starts_with($raw, 'message.php')) {
        return message_box_url($fallbackBox);
    }

    return $raw;
}

function message_mailbox_summary(int $userId): array
{
    $res = sql_query("
        SELECT
            (SELECT COUNT(*) FROM messages WHERE receiver = " . sqlesc($userId) . " AND location = " . sqlesc(PM_INBOX) . ") AS inbox_total,
            (SELECT COUNT(*) FROM messages WHERE receiver = " . sqlesc($userId) . " AND location = " . sqlesc(PM_INBOX) . " AND unread = 'yes') AS inbox_unread,
            (SELECT COUNT(*) FROM messages WHERE sender = " . sqlesc($userId) . " AND saved = 'yes') AS sent_total
    ") or sqlerr(__FILE__, __LINE__);

    $summary = mysqli_fetch_assoc($res) ?: [
        'inbox_total' => 0,
        'inbox_unread' => 0,
        'sent_total' => 0,
    ];

    return [
        'inbox_total' => (int)($summary['inbox_total'] ?? 0),
        'inbox_unread' => (int)($summary['inbox_unread'] ?? 0),
        'sent_total' => (int)($summary['sent_total'] ?? 0),
    ];
}

function message_mailbox_rows(int $userId, int $mailbox, int $page, int $perPage = PM_PER_PAGE): array
{
    $mailbox = message_box_normalize($mailbox);
    $page = max(0, $page);
    $perPage = max(1, $perPage);
    $offset = $page * $perPage;

    if ($mailbox === PM_SENTBOX) {
        $where = "m.sender = " . sqlesc($userId) . " AND m.saved = 'yes'";
    } else {
        $where = "m.receiver = " . sqlesc($userId) . " AND m.location = " . sqlesc(PM_INBOX);
    }

    $res = sql_query("
        SELECT
            m.id,
            m.sender,
            m.receiver,
            m.added,
            m.subject,
            m.msg,
            m.unread,
            m.saved,
            m.location,
            su.username AS sender_username,
            su.class AS sender_class,
            ru.username AS receiver_username,
            ru.class AS receiver_class
        FROM messages m
        LEFT JOIN users su ON su.id = m.sender
        LEFT JOIN users ru ON ru.id = m.receiver
        WHERE {$where}
        ORDER BY m.id DESC
        LIMIT {$offset}, {$perPage}
    ") or sqlerr(__FILE__, __LINE__);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

    return $rows;
}

function message_cached_pm_row(int $pmId, int $viewerId, bool $adminview = false): ?array
{
    $cacheKey = $adminview
        ? tracker_cache_key('message', 'pm', 'admin', 'id' . $pmId, 'viewer' . $viewerId)
        : tracker_cache_ns_key(tracker_message_cache_namespace($viewerId), 'pm', 'id' . $pmId);

    $row = tracker_cache_remember($cacheKey, 45, static function () use ($pmId, $viewerId, $adminview): ?array {
        if ($adminview) {
            $where = "m.id = " . sqlesc($pmId);
        } else {
            $where = "m.id = " . sqlesc($pmId) . "
                AND (
                    m.receiver = " . sqlesc($viewerId) . "
                    OR (m.sender = " . sqlesc($viewerId) . " AND m.saved = 'yes')
                )";
        }

        $res = sql_query("
            SELECT
                m.*,
                su.username AS sender_username,
                su.class AS sender_class,
                ru.username AS receiver_username,
                ru.class AS receiver_class
            FROM messages m
            LEFT JOIN users su ON su.id = m.sender
            LEFT JOIN users ru ON ru.id = m.receiver
            WHERE {$where}
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        return ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
    });

    return is_array($row) ? $row : null;
}

function message_preview(string $body, int $limit = 110): string
{
    $body = preg_replace('/\[(?:\/)?[^\]]+\]/u', ' ', $body) ?? $body;
    $body = preg_replace('/\s+/u', ' ', trim($body)) ?? trim($body);

    if ($body === '') {
        return 'Без текста';
    }

    if (mb_strlen($body, 'UTF-8') > $limit) {
        return mb_substr($body, 0, $limit - 3, 'UTF-8') . '...';
    }

    return $body;
}

function message_user_link(int $userId, ?string $username, int $class = 0): string
{
    if ($userId <= 0) {
        return 'Система';
    }

    $username = trim((string)$username);
    if ($username === '') {
        $username = 'Пользователь #' . $userId;
    }

    return '<a href="userdetails.php?id=' . $userId . '">'
        . get_user_class_color($class, message_h($username))
        . '</a>';
}

function message_format_datetime(?string $value, int $tzoffset): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '---';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return message_h($value);
    }

    return message_h(display_date_time($timestamp, $tzoffset));
}

function message_resolve_user(?string $receiverRaw, ?string $targetRaw): ?array
{
    $receiverRaw = trim((string)$receiverRaw);
    $targetRaw = trim((string)$targetRaw);

    if ($receiverRaw !== '' && ctype_digit($receiverRaw) && (int)$receiverRaw > 0) {
        $where = 'id = ' . sqlesc((int)$receiverRaw);
    } elseif ($targetRaw !== '') {
        if (ctype_digit($targetRaw) && (int)$targetRaw > 0) {
            $where = 'id = ' . sqlesc((int)$targetRaw);
        } else {
            $where = 'LOWER(username) = LOWER(' . sqlesc($targetRaw) . ')';
        }
    } else {
        return null;
    }

    $res = sql_query("
        SELECT id, username, class, acceptpms, parked
        FROM users
        WHERE {$where}
        LIMIT 1
    ") or sqlerr(__FILE__, __LINE__);

    if (!$res || mysqli_num_rows($res) === 0) {
        return null;
    }

    return mysqli_fetch_assoc($res) ?: null;
}

function message_assert_can_send_to(array $recipient, int $senderId): void
{
    $recipientId = (int)($recipient['id'] ?? 0);

    if ($recipientId <= 0) {
        stderr('Ошибка', 'Не найден получатель сообщения.');
    }

    if (($recipient['parked'] ?? 'no') === 'yes') {
        stderr('Ошибка', 'Этот аккаунт припаркован.');
    }

    if (get_user_class() >= UC_MODERATOR || $recipientId === $senderId) {
        return;
    }

    $accept = (string)($recipient['acceptpms'] ?? 'yes');
    if ($accept === 'no') {
        stderr('Отклонено', 'Этот пользователь не принимает сообщения.');
    }

    if ($accept === 'friends') {
        $res = sql_query("
            SELECT 1
            FROM friends
            WHERE userid = " . sqlesc($recipientId) . "
              AND friendid = " . sqlesc($senderId) . "
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        if (mysqli_num_rows($res) !== 1) {
            stderr('Отклонено', 'Этот пользователь принимает сообщения только от друзей.');
        }
    }

    if (class_permissions_table_exists('blocks')) {
        $res = sql_query("
            SELECT 1
            FROM blocks
            WHERE userid = " . sqlesc($recipientId) . "
              AND blockid = " . sqlesc($senderId) . "
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        if (mysqli_num_rows($res) === 1) {
            stderr('Отклонено', 'Этот пользователь добавил вас в чёрный список.');
        }
    }
}

function message_insert_message(int $posterId, int $senderId, int $receiverId, string $subject, string $body, string $save = 'no'): int
{
    global $mysqli;

    sql_query("
        INSERT INTO messages (poster, sender, receiver, added, msg, subject, saved, location, unread)
        VALUES (
            " . sqlesc($posterId) . ",
            " . sqlesc($senderId) . ",
            " . sqlesc($receiverId) . ",
            NOW(),
            " . sqlesc($body) . ",
            " . sqlesc($subject) . ",
            " . sqlesc($save) . ",
            " . sqlesc(PM_INBOX) . ",
            'yes'
        )
    ") or sqlerr(__FILE__, __LINE__);

    return (int)$mysqli->insert_id;
}

function message_collect_ids(int $singleId, mixed $manyIds): array
{
    $ids = [];

    if ($singleId > 0) {
        $ids[] = $singleId;
    }

    if (is_array($manyIds)) {
        foreach ($manyIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }

    return array_values(array_unique($ids));
}

function message_fetch_owned_rows(array $ids, int $userId): array
{
    if ($ids === []) {
        return [];
    }

    $res = sql_query("
        SELECT id, sender, receiver, saved, location
        FROM messages
        WHERE id IN (" . implode(', ', array_map('intval', $ids)) . ")
          AND (sender = " . sqlesc($userId) . " OR receiver = " . sqlesc($userId) . ")
    ") or sqlerr(__FILE__, __LINE__);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[(int)$row['id']] = $row;
    }

    return $rows;
}

function message_delete_selected(array $ids, int $userId): int
{
    $rows = message_fetch_owned_rows($ids, $userId);
    if ($rows === []) {
        return 0;
    }

    $hardDelete = [];
    $receiverSoft = [];
    $senderSoft = [];

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $sender = (int)$row['sender'];
        $receiver = (int)$row['receiver'];
        $saved = (string)$row['saved'];
        $location = (int)$row['location'];

        if ($receiver === $userId && $saved === 'no') {
            $hardDelete[] = $id;
            continue;
        }

        if ($sender === $userId && $location === PM_DELETED) {
            $hardDelete[] = $id;
            continue;
        }

        if ($receiver === $userId && $saved === 'yes') {
            $receiverSoft[] = $id;
            continue;
        }

        if ($sender === $userId && $saved !== 'no') {
            $senderSoft[] = $id;
        }
    }

    if ($hardDelete !== []) {
        sql_query("DELETE FROM messages WHERE id IN (" . implode(', ', $hardDelete) . ")") or sqlerr(__FILE__, __LINE__);
    }

    if ($receiverSoft !== []) {
        sql_query("
            UPDATE messages
            SET location = " . sqlesc(PM_DELETED) . "
            WHERE id IN (" . implode(', ', $receiverSoft) . ")
        ") or sqlerr(__FILE__, __LINE__);
    }

    if ($senderSoft !== []) {
        sql_query("
            UPDATE messages
            SET saved = 'no'
            WHERE id IN (" . implode(', ', $senderSoft) . ")
        ") or sqlerr(__FILE__, __LINE__);
    }

    tracker_invalidate_message_cache($userId);

    return count($hardDelete) + count($receiverSoft) + count($senderSoft);
}

function message_mark_read_selected(array $ids, int $userId): int
{
    global $mysqli;

    if ($ids === []) {
        return 0;
    }

    sql_query("
        UPDATE messages
        SET unread = 'no'
        WHERE receiver = " . sqlesc($userId) . "
          AND location = " . sqlesc(PM_INBOX) . "
          AND unread = 'yes'
          AND id IN (" . implode(', ', array_map('intval', $ids)) . ")
    ") or sqlerr(__FILE__, __LINE__);

    tracker_invalidate_message_cache($userId);

    return (int)$mysqli->affected_rows;
}

function message_delete_original_after_reply(int $origId, int $userId): void
{
    if ($origId <= 0) {
        return;
    }

    $res = sql_query("
        SELECT id, receiver, saved
        FROM messages
        WHERE id = " . sqlesc($origId) . "
        LIMIT 1
    ") or sqlerr(__FILE__, __LINE__);

    $row = mysqli_fetch_assoc($res);
    if (!$row || (int)$row['receiver'] !== $userId) {
        return;
    }

    if (($row['saved'] ?? 'no') === 'yes') {
        sql_query("UPDATE messages SET location = " . sqlesc(PM_DELETED) . " WHERE id = " . sqlesc($origId) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
    } else {
        sql_query("DELETE FROM messages WHERE id = " . sqlesc($origId) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
    }

    tracker_invalidate_message_cache($userId);
}

function message_mass_recipient_ids(string $input): array
{
    $ids = array_map('intval', preg_split('/[\s,]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $ids = array_filter($ids, static fn(int $id): bool => $id > 0);
    return array_values(array_unique($ids));
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'viewmailbox');
if (in_array($action, ['sendmessage', 'takemessage', 'mass_pm', 'takemass_pm', 'forward'], true) && !user_has_module('message_write')) {
    stderr('Ошибка', 'У вас нет доступа к отправке личных сообщений.');
}

$currentUserId = (int)($CURUSER['id'] ?? 0);
$tzoffset = (int)($CURUSER['tzoffset'] ?? 0);

switch ($action) {
    case 'viewmailbox':
        $mailbox = message_box_normalize((int)($_GET['box'] ?? PM_INBOX));
        $summary = message_mailbox_summary($currentUserId);
        $total = $mailbox === PM_SENTBOX ? $summary['sent_total'] : $summary['inbox_total'];
        $pages = max(1, (int)ceil($total / PM_PER_PAGE));
        $page = isset($_GET['page']) ? max(0, min((int)$_GET['page'], $pages - 1)) : 0;
        [$pagertop, $pagerbottom] = pager(PM_PER_PAGE, $total, 'message.php?action=viewmailbox&box=' . $mailbox . '&');
        $rows = $total > 0 ? message_mailbox_rows($currentUserId, $mailbox, $page, PM_PER_PAGE) : [];

        stdhead(message_box_label($mailbox));

        $done = (string)($_GET['done'] ?? '');
        if ($done === 'delete') {
            stdmsg('Готово', 'Выбранные сообщения обработаны.');
        } elseif ($done === 'read') {
            stdmsg('Готово', 'Отмеченные письма помечены как прочитанные.');
        } elseif ($done === 'move') {
            stdmsg('Готово', 'Сообщения перемещены.');
        }

        begin_frame('Почтовый ящик');
        echo '<table border="0" cellspacing="0" cellpadding="5" width="100%">';
        echo '<tr><td class="rowhead" width="20%">Раздел</td><td class="lol">'
            . '<a href="' . message_h(message_box_url(PM_INBOX)) . '"><b>Входящие</b></a> (' . $summary['inbox_total'] . ')'
            . ' | <a href="' . message_h(message_box_url(PM_SENTBOX)) . '"><b>Отправленные</b></a> (' . $summary['sent_total'] . ')</td></tr>';
        echo '<tr><td class="rowhead">Сводка</td><td class="lol">Непрочитанных: '
            . ($summary['inbox_unread'] > 0 ? '<font color="#CC0000"><b>' . $summary['inbox_unread'] . '</b></font>' : '0')
            . ' | На странице: ' . count($rows) . ' из ' . $total . '</td></tr>';
        echo '<tr><td class="rowhead">Быстрое сообщение</td><td class="lol">';
        echo '<form action="message.php" method="get" style="margin:0">';
        echo '<input type="hidden" name="action" value="sendmessage">';
        echo '<input type="hidden" name="returnto" value="' . message_h(message_box_url($mailbox)) . '">';
        echo 'Кому: <input type="text" name="to" size="32" placeholder="Имя пользователя или ID"> ';
        echo '<input type="submit" class="btn" value="Написать">';
        echo '</form>';
        echo '</td></tr>';
        echo '</table><br>';

        if ($total > PM_PER_PAGE) {
            echo $pagertop;
        }

        echo '<script>
            function messageToggleAll(source) {
                var boxes = document.querySelectorAll(\'input[name="messages[]"]\');
                for (var i = 0; i < boxes.length; i++) {
                    boxes[i].checked = source.checked;
                }
            }
        </script>';

        echo '<form action="message.php" method="post">';
        echo '<input type="hidden" name="action" value="moveordel">';
        echo '<input type="hidden" name="box" value="' . $mailbox . '">';
        echo '<table border="0" cellpadding="4" cellspacing="0" width="100%">';
        echo '<tr>'
            . '<td class="colhead" width="5%">Статус</td>'
            . '<td class="colhead" width="47%">Письмо</td>'
            . '<td class="colhead" width="22%">' . ($mailbox === PM_SENTBOX ? 'Получатель' : 'Отправитель') . '</td>'
            . '<td class="colhead" width="18%">Дата</td>'
            . '<td class="colhead" width="8%" align="center"><input type="checkbox" onclick="messageToggleAll(this)" title="Выделить всё"></td>'
            . '</tr>';

        if ($rows === []) {
            echo '<tr><td class="lol" colspan="5" align="center">Писем нет. Можно открыть <a href="friends.php">список друзей</a> или быстро написать по имени выше.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $pmId = (int)$row['id'];
                $subject = trim((string)$row['subject']);
                $subject = $subject !== '' ? $subject : 'Без темы';
                $preview = message_preview((string)($row['msg'] ?? ''));
                $isUnread = ($mailbox === PM_INBOX && ($row['unread'] ?? 'no') === 'yes');
                $statusHtml = $mailbox === PM_SENTBOX
                    ? (($row['unread'] ?? 'no') === 'yes' ? '<font color="#CC0000"><b>Не прочитано</b></font>' : 'Прочитано')
                    : ($isUnread ? '<font color="#CC0000"><b>Новое</b></font>' : 'Прочитано');
                $peerLink = $mailbox === PM_SENTBOX
                    ? message_user_link((int)$row['receiver'], (string)($row['receiver_username'] ?? ''), (int)($row['receiver_class'] ?? 0))
                    : message_user_link((int)$row['sender'], (string)($row['sender_username'] ?? ''), (int)($row['sender_class'] ?? 0));
                $viewUrl = 'message.php?action=viewmessage&id=' . $pmId . '&box=' . $mailbox;

                echo '<tr>';
                echo '<td class="lol" align="center">' . $statusHtml . '</td>';
                echo '<td class="lol">'
                    . '<a href="' . message_h($viewUrl) . '"><b>' . message_h($subject) . '</b></a>'
                    . '<div style="font-size:11px;padding-top:3px;color:#666;">' . message_h($preview) . '</div>'
                    . '</td>';
                echo '<td class="lol">' . $peerLink . '</td>';
                echo '<td class="lol" nowrap>' . message_format_datetime((string)$row['added'], $tzoffset) . '</td>';
                echo '<td class="lol" align="center"><input type="checkbox" name="messages[]" value="' . $pmId . '"></td>';
                echo '</tr>';
            }
        }

        echo '<tr class="colhead"><td colspan="5" align="right">';
        echo '<input type="submit" name="delete" value="Удалить" class="btn" onclick="return confirm(\'Удалить выбранные сообщения?\')"> ';
        if ($mailbox === PM_INBOX) {
            echo '<input type="submit" name="markread" value="Отметить прочитанными" class="btn">';
        }
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';

        if ($total > PM_PER_PAGE) {
            echo $pagerbottom;
        }

        end_frame();
        stdfoot();
        exit;

    case 'viewmessage':
        $pmId = (int)($_GET['id'] ?? 0);
        if ($pmId <= 0) {
            stderr('Ошибка', 'Некорректный ID сообщения.');
        }

        $mailbox = isset($_GET['box']) ? message_box_normalize((int)$_GET['box']) : PM_INBOX;
        $adminview = get_user_class() === UC_SYSOP;
        $message = message_cached_pm_row($pmId, $currentUserId, $adminview);
        if (!$message) {
            stderr('Ошибка', 'Сообщение не найдено.');
        }

        $isSender = (int)$message['sender'] === $currentUserId;
        $isReceiver = (int)$message['receiver'] === $currentUserId;
        $ownsMessage = $isSender || $isReceiver;
        $isInspector = $adminview && !$ownsMessage;

        if ($isReceiver && ($message['unread'] ?? 'no') === 'yes') {
            sql_query("
                UPDATE messages
                SET unread = 'no'
                WHERE id = " . sqlesc($pmId) . "
                  AND receiver = " . sqlesc($currentUserId) . "
                LIMIT 1
            ") or sqlerr(__FILE__, __LINE__);
            tracker_invalidate_message_cache($currentUserId);
            $message['unread'] = 'no';
        }

        $subject = trim((string)($message['subject'] ?? ''));
        $subject = $subject !== '' ? $subject : 'Без темы';

        $body = tracker_cache_remember(
            tracker_cache_key('message', 'body', 'pm' . $pmId, 'h' . md5((string)($message['msg'] ?? ''))),
            600,
            static function () use ($message): string {
                return format_comment((string)($message['msg'] ?? ''));
            }
        );

        $partnerLabel = $isSender && !$isInspector ? 'Кому' : 'От кого';
        $partnerLink = $isSender && !$isInspector
            ? message_user_link((int)$message['receiver'], (string)($message['receiver_username'] ?? ''), (int)($message['receiver_class'] ?? 0))
            : message_user_link((int)$message['sender'], (string)($message['sender_username'] ?? ''), (int)($message['sender_class'] ?? 0));

        $actions = [];
        $actions[] = '<a href="' . message_h(message_box_url($mailbox)) . '">К ящику</a>';
        if ($ownsMessage && !$isSender && (int)$message['sender'] > 0) {
            $actions[] = '<a href="' . message_h('message.php?action=sendmessage&receiver=' . (int)$message['sender'] . '&replyto=' . $pmId . '&returnto=' . rawurlencode(message_box_url($mailbox))) . '">Ответить</a>';
        }
        if ($ownsMessage) {
            $actions[] = '<a href="' . message_h('message.php?action=forward&id=' . $pmId . '&box=' . $mailbox) . '">Переслать</a>';
            $actions[] = '<a href="' . message_h('message.php?action=deletemessage&id=' . $pmId . '&box=' . $mailbox) . '">Удалить</a>';
        }

        stdhead('Личное сообщение');
        begin_frame('Письмо: ' . message_h($subject));
        echo '<table border="0" cellpadding="5" cellspacing="0" width="100%">';
        echo '<tr><td class="rowhead" width="18%">' . $partnerLabel . '</td><td class="lol" width="32%">' . $partnerLink . '</td><td class="rowhead" width="18%">Дата</td><td class="lol">' . message_format_datetime((string)$message['added'], $tzoffset) . '</td></tr>';
        echo '<tr><td class="rowhead">Тема</td><td class="lol" colspan="3"><b>' . message_h($subject) . '</b></td></tr>';
        echo '<tr><td class="rowhead" valign="top">Сообщение</td><td class="lol" colspan="3">' . $body . '</td></tr>';
        echo '<tr><td class="rowhead">Действия</td><td class="lol" colspan="3">' . implode(' | ', $actions) . '</td></tr>';
        echo '</table>';
        end_frame();
        stdfoot();
        exit;

    case 'sendmessage':
        $recipient = message_resolve_user($_GET['receiver'] ?? null, $_GET['to'] ?? ($_GET['user_query'] ?? null));
        if (!$recipient) {
            stderr('Ошибка', 'Не удалось определить получателя.');
        }

        $replyto = (int)($_GET['replyto'] ?? 0);
        $returnto = message_safe_returnto((string)($_GET['returnto'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), PM_INBOX);
        $subject = '';
        $body = '';

        $auto = (string)($_GET['auto'] ?? '');
        $std = (string)($_GET['std'] ?? '');
        if ($auto !== '' && isset($pm_std_reply[$auto])) {
            $body = (string)$pm_std_reply[$auto];
        }
        if ($std !== '' && isset($pm_template[$std][1])) {
            $body = (string)$pm_template[$std][1];
        }

        if ($replyto > 0) {
            $original = message_cached_pm_row($replyto, $currentUserId, false);
            if (!$original || (int)$original['receiver'] !== $currentUserId) {
                stderr('Ошибка', 'Нельзя ответить на это сообщение.');
            }

            $senderName = (string)($original['sender_username'] ?? 'Система');
            $quotedSubject = trim((string)($original['subject'] ?? ''));
            $quotedSubject = $quotedSubject !== '' ? $quotedSubject : 'Без темы';
            $body .= ($body !== '' ? "\n\n" : '') . "-------- {$senderName} писал(а): --------\n" . (string)($original['msg'] ?? '') . "\n";
            $subject = 'Re: ' . $quotedSubject;
        }

        stdhead('Новое сообщение');
        begin_frame('Сообщение для ' . message_user_link((int)$recipient['id'], (string)$recipient['username'], (int)$recipient['class']));
        echo '<form method="post" action="message.php">';
        echo '<input type="hidden" name="action" value="takemessage">';
        echo '<input type="hidden" name="receiver" value="' . (int)$recipient['id'] . '">';
        echo '<input type="hidden" name="returnto" value="' . message_h($returnto) . '">';
        if ($replyto > 0) {
            echo '<input type="hidden" name="origmsg" value="' . $replyto . '">';
        }
        echo '<table border="0" cellpadding="5" cellspacing="0" width="100%">';
        echo '<tr><td class="rowhead" width="18%">Получатель</td><td class="lol">' . message_user_link((int)$recipient['id'], (string)$recipient['username'], (int)$recipient['class']) . '</td></tr>';
        echo '<tr><td class="rowhead">Тема</td><td class="lol"><input type="text" name="subject" size="70" maxlength="255" value="' . message_h($subject) . '"></td></tr>';
        echo '<tr><td class="rowhead" valign="top">Текст</td><td class="lol">';
        textbbcode('message', 'msg', $body);
        echo '</td></tr>';
        echo '<tr><td class="rowhead">Опции</td><td class="lol">';
        if ($replyto > 0) {
            echo '<label><input type="checkbox" name="delete" value="yes"' . (($CURUSER['deletepms'] ?? 'no') === 'yes' ? ' checked' : '') . '> Удалить исходное письмо после ответа</label><br>';
        }
        echo '<label><input type="checkbox" name="save" value="yes"' . (($CURUSER['savepms'] ?? 'no') === 'yes' ? ' checked' : '') . '> Сохранить копию в отправленных</label>';
        echo '</td></tr>';
        echo '<tr><td class="rowhead">Навигация</td><td class="lol"><a href="' . message_h($returnto) . '">Назад</a></td></tr>';
        echo '<tr><td class="rowhead"></td><td class="lol"><input type="submit" class="btn" value="Отправить"></td></tr>';
        echo '</table>';
        echo '</form>';
        end_frame();
        stdfoot();
        exit;

    case 'takemessage':
        $receiverId = (int)($_POST['receiver'] ?? 0);
        $origmsg = (int)($_POST['origmsg'] ?? 0);
        $recipient = message_resolve_user((string)$receiverId, null);
        if (!$recipient) {
            stderr('Ошибка', 'Получатель не найден.');
        }

        message_assert_can_send_to($recipient, $currentUserId);

        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['msg'] ?? ''));
        $save = (($_POST['save'] ?? '') === 'yes') ? 'yes' : 'no';
        $returnto = message_safe_returnto((string)($_POST['returnto'] ?? ''), $save === 'yes' ? PM_SENTBOX : PM_INBOX);

        if ($subject === '') {
            stderr('Ошибка', 'Введите тему сообщения.');
        }
        if ($body === '') {
            stderr('Ошибка', 'Введите текст сообщения.');
        }

        message_insert_message($currentUserId, $currentUserId, (int)$recipient['id'], $subject, $body, $save);
        tracker_invalidate_message_cache((int)$recipient['id'], $currentUserId);

        if ($origmsg > 0 && (($_POST['delete'] ?? '') === 'yes')) {
            message_delete_original_after_reply($origmsg, $currentUserId);
        }

        header('Location: ' . $returnto);
        exit;

    case 'mass_pm':
        if (get_user_class() < UC_MODERATOR) {
            stderr('Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещён.');
        }

        $recipientIds = message_mass_recipient_ids((string)($_POST['pmees'] ?? $_GET['pmees'] ?? ''));
        if ($recipientIds === []) {
            stderr('Ошибка', 'Не выбраны получатели для массовой рассылки.');
        }

        $auto = (string)($_POST['auto'] ?? $_GET['auto'] ?? '');
        $body = ($auto !== '' && isset($mm_template[$auto][1])) ? (string)$mm_template[$auto][1] : '';

        $res = sql_query("
            SELECT id, username
            FROM users
            WHERE id IN (" . implode(', ', $recipientIds) . ")
            ORDER BY username
        ") or sqlerr(__FILE__, __LINE__);

        $names = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $names[] = message_h($row['username']) . ' (#' . (int)$row['id'] . ')';
        }

        stdhead('Массовая рассылка');
        begin_frame('Массовое сообщение [' . count($recipientIds) . ']');
        echo '<form method="post" action="message.php">';
        echo '<input type="hidden" name="action" value="takemass_pm">';
        echo '<input type="hidden" name="pmees" value="' . message_h(implode(' ', $recipientIds)) . '">';
        echo '<table border="0" cellpadding="5" cellspacing="0" width="100%">';
        echo '<tr><td class="rowhead" width="18%">Получатели</td><td class="lol">' . implode(', ', array_slice($names, 0, 15));
        if (count($names) > 15) {
            echo ' ... и ещё ' . (count($names) - 15);
        }
        echo '</td></tr>';
        echo '<tr><td class="rowhead">Тема</td><td class="lol"><input type="text" name="subject" size="70" maxlength="255"></td></tr>';
        echo '<tr><td class="rowhead" valign="top">Текст</td><td class="lol">';
        textbbcode('massmessage', 'msg', $body);
        echo '</td></tr>';
        echo '<tr><td class="rowhead">Комментарий в профиль</td><td class="lol"><input type="text" name="comment" size="80"></td></tr>';
        echo '<tr><td class="rowhead">Отправитель</td><td class="lol"><label><input type="radio" name="sender" value="self" checked> ' . message_h((string)$CURUSER['username']) . '</label> <label><input type="radio" name="sender" value="system"> Системное</label></td></tr>';
        echo '<tr><td class="rowhead">Снимок статистики</td><td class="lol"><label><input type="checkbox" name="snap" value="1"> Добавить UL/DL/ratio в комментарий профиля</label></td></tr>';
        echo '<tr><td class="rowhead"></td><td class="lol"><input type="submit" class="btn" value="Отправить"></td></tr>';
        echo '</table>';
        echo '</form>';
        end_frame();
        stdfoot();
        exit;

    case 'takemass_pm':
        if (get_user_class() < UC_MODERATOR) {
            stderr('Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещён.');
        }

        $recipientIds = message_mass_recipient_ids((string)($_POST['pmees'] ?? ''));
        if ($recipientIds === []) {
            stderr('Ошибка', 'Не выбраны получатели для массовой рассылки.');
        }

        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['msg'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));
        $snapshot = (($_POST['snap'] ?? '') === '1');
        $senderId = (($_POST['sender'] ?? 'self') === 'system') ? 0 : $currentUserId;

        if ($body === '') {
            stderr('Ошибка', 'Введите текст массового сообщения.');
        }

        $res = sql_query("
            SELECT id, username, uploaded, downloaded, modcomment
            FROM users
            WHERE id IN (" . implode(', ', $recipientIds) . ")
        ") or sqlerr(__FILE__, __LINE__);

        $validUsers = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $validUsers[] = $row;
        }

        if ($validUsers === []) {
            stderr('Ошибка', 'Не найдены пользователи для рассылки.');
        }

        $values = [];
        foreach ($validUsers as $user) {
            $values[] = '('
                . sqlesc($senderId) . ', '
                . sqlesc((int)$user['id']) . ', '
                . 'NOW(), '
                . sqlesc($subject) . ', '
                . sqlesc($body) . ", 'yes', "
                . sqlesc($senderId) . ', '
                . sqlesc(PM_INBOX) . ", 'no')";
        }

        sql_query("
            INSERT INTO messages (sender, receiver, added, subject, msg, unread, poster, location, saved)
            VALUES " . implode(",\n", $values)
        ) or sqlerr(__FILE__, __LINE__);

        $updatedComments = 0;
        if ($comment !== '' || $snapshot) {
            foreach ($validUsers as $user) {
                $lines = [];
                if ($comment !== '') {
                    $lines[] = $comment;
                }
                if ($snapshot) {
                    $ratio = ((int)$user['downloaded'] > 0)
                        ? number_format((int)$user['uploaded'] / (int)$user['downloaded'], 2)
                        : (((int)$user['uploaded'] > 0) ? 'Inf.' : '---');
                    $lines[] = 'MMed, ' . date('Y-m-d') . ', UL: ' . mksize((int)$user['uploaded']) . ', DL: ' . mksize((int)$user['downloaded']) . ', r: ' . $ratio . ' - ' . ($senderId === 0 ? 'System' : (string)$CURUSER['username']);
                }

                $newComment = implode("\n", $lines);
                if ($newComment === '') {
                    continue;
                }

                $oldComment = (string)($user['modcomment'] ?? '');
                if ($oldComment !== '') {
                    $newComment .= "\n" . $oldComment;
                }

                sql_query("UPDATE users SET modcomment = " . sqlesc($newComment) . " WHERE id = " . sqlesc((int)$user['id']) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $updatedComments++;
            }
        }

        tracker_invalidate_message_cache(...array_map(static fn(array $user): int => (int)$user['id'], $validUsers));

        $message = (count($validUsers) > 1 ? 'Сообщения успешно отправлены.' : 'Сообщение успешно отправлено.');
        if ($updatedComments > 0) {
            $message .= ' Комментарии в профиле обновлены: ' . $updatedComments . '.';
        }

        stderr('Успешно', $message);
        exit;

    case 'moveordel':
        $mailbox = message_box_normalize((int)($_POST['box'] ?? PM_INBOX));
        $ids = message_collect_ids((int)($_POST['id'] ?? 0), $_POST['messages'] ?? []);
        if ($ids === []) {
            stderr('Ошибка', 'Не выбраны сообщения.');
        }

        if (isset($_POST['delete'])) {
            $changed = message_delete_selected($ids, $currentUserId);
            if ($changed === 0) {
                stderr('Ошибка', 'Не удалось удалить выбранные сообщения.');
            }
            header('Location: ' . message_box_url($mailbox, ['done' => 'delete']));
            exit;
        }

        if (isset($_POST['markread'])) {
            message_mark_read_selected($ids, $currentUserId);
            header('Location: ' . message_box_url($mailbox, ['done' => 'read']));
            exit;
        }

        if (isset($_POST['move'])) {
            sql_query("
                UPDATE messages
                SET location = " . sqlesc(PM_INBOX) . ", saved = 'yes'
                WHERE receiver = " . sqlesc($currentUserId) . "
                  AND id IN (" . implode(', ', array_map('intval', $ids)) . ")
            ") or sqlerr(__FILE__, __LINE__);
            tracker_invalidate_message_cache($currentUserId);
            header('Location: ' . message_box_url($mailbox, ['done' => 'move']));
            exit;
        }

        stderr('Ошибка', 'Не выбрано действие.');
        exit;

    case 'forward':
        $pmId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($pmId <= 0) {
            stderr('Ошибка', 'Некорректный ID сообщения.');
        }

        $mailbox = isset($_GET['box']) ? message_box_normalize((int)$_GET['box']) : message_box_normalize((int)($_POST['box'] ?? PM_INBOX));
        $original = message_cached_pm_row($pmId, $currentUserId, false);
        if (!$original) {
            stderr('Ошибка', 'У вас нет доступа к этому сообщению.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $recipient = message_resolve_user($_POST['receiver'] ?? null, $_POST['to'] ?? null);
            if (!$recipient) {
                stderr('Ошибка', 'Не удалось определить получателя.');
            }

            message_assert_can_send_to($recipient, $currentUserId);

            $subject = trim((string)($_POST['subject'] ?? ''));
            $body = trim((string)($_POST['msg'] ?? ''));
            $save = (($_POST['save'] ?? '') === 'yes') ? 'yes' : 'no';
            $returnto = message_safe_returnto((string)($_POST['returnto'] ?? ''), $save === 'yes' ? PM_SENTBOX : $mailbox);

            if ($subject === '') {
                stderr('Ошибка', 'Введите тему пересылаемого сообщения.');
            }
            if ($body === '') {
                stderr('Ошибка', 'Введите текст пересылки.');
            }

            $origSenderName = (int)$original['sender'] > 0
                ? (string)($original['sender_username'] ?? ('#' . (int)$original['sender']))
                : 'Система';

            $fullBody = $body
                . "\n\n-------- Оригинальное сообщение от {$origSenderName}: --------\n"
                . (string)($original['msg'] ?? '');

            message_insert_message($currentUserId, $currentUserId, (int)$recipient['id'], $subject, $fullBody, $save);
            tracker_invalidate_message_cache((int)$recipient['id'], $currentUserId);

            header('Location: ' . $returnto);
            exit;
        }

        $forwardSubject = 'Fwd: ' . (trim((string)($original['subject'] ?? '')) ?: 'Без темы');
        $prefillBody = "Пересылаю сообщение.\n";

        stdhead('Переслать сообщение');
        begin_frame('Пересылка письма');
        echo '<form method="post" action="message.php">';
        echo '<input type="hidden" name="action" value="forward">';
        echo '<input type="hidden" name="id" value="' . $pmId . '">';
        echo '<input type="hidden" name="box" value="' . $mailbox . '">';
        echo '<input type="hidden" name="returnto" value="' . message_h(message_box_url($mailbox)) . '">';
        echo '<table border="0" cellpadding="5" cellspacing="0" width="100%">';
        echo '<tr><td class="rowhead" width="18%">Кому</td><td class="lol"><input type="text" name="to" size="40" placeholder="Имя пользователя или ID"></td></tr>';
        echo '<tr><td class="rowhead">Оригинал</td><td class="lol">' . message_user_link((int)$original['sender'], (string)($original['sender_username'] ?? ''), (int)($original['sender_class'] ?? 0)) . ' -> ' . message_user_link((int)$original['receiver'], (string)($original['receiver_username'] ?? ''), (int)($original['receiver_class'] ?? 0)) . '</td></tr>';
        echo '<tr><td class="rowhead">Тема</td><td class="lol"><input type="text" name="subject" size="70" maxlength="255" value="' . message_h($forwardSubject) . '"></td></tr>';
        echo '<tr><td class="rowhead" valign="top">Ваш текст</td><td class="lol">';
        textbbcode('forwardmessage', 'msg', $prefillBody);
        echo '</td></tr>';
        echo '<tr><td class="rowhead">Опции</td><td class="lol"><label><input type="checkbox" name="save" value="yes"' . (($CURUSER['savepms'] ?? 'no') === 'yes' ? ' checked' : '') . '> Сохранить копию в отправленных</label></td></tr>';
        echo '<tr><td class="rowhead"></td><td class="lol"><input type="submit" class="btn" value="Переслать"></td></tr>';
        echo '</table>';
        echo '</form>';
        end_frame();
        stdfoot();
        exit;

    case 'deletemessage':
        $pmId = (int)($_GET['id'] ?? 0);
        if ($pmId <= 0) {
            stderr('Ошибка', 'Некорректный ID сообщения.');
        }

        $mailbox = isset($_GET['box']) ? message_box_normalize((int)$_GET['box']) : PM_INBOX;
        $changed = message_delete_selected([$pmId], $currentUserId);
        if ($changed === 0) {
            stderr('Ошибка', 'Невозможно удалить это сообщение.');
        }

        header('Location: ' . message_box_url($mailbox, ['done' => 'delete']));
        exit;

    default:
        stderr('Ошибка', 'Неизвестное действие.');
        exit;
}
