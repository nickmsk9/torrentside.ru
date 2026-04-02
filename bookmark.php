<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
loggedinorreturn();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    stderr('Ошибка', 'Неверный метод запроса.');
}

$userId = (int)($CURUSER['id'] ?? 0);
if ($userId <= 0) {
    stderr('Ошибка', 'Требуется авторизация.');
}

$type = (string)($_POST['type'] ?? '');
$entityId = (int)($_POST['entity_id'] ?? 0);
$returnto = trim((string)($_POST['returnto'] ?? ''));
if ($returnto === '' || preg_match('~^[a-z]+://~i', $returnto) || str_starts_with($returnto, '//')) {
    $returnto = 'index.php';
}

if ($entityId <= 0 || !in_array($type, ['torrent', 'release_group'], true)) {
    stderr('Ошибка', 'Некорректные параметры закладки.');
}

$exists = false;
if ($type === 'torrent') {
    $res = sql_query("SELECT 1 FROM torrents WHERE id = " . $entityId . " LIMIT 1");
    $exists = (bool)($res && mysqli_fetch_row($res));
} else {
    $exists = tracker_get_release_group($entityId) !== null;
}

if (!$exists) {
    stderr('Ошибка', 'Объект для закладки не найден.');
}

if ($type === 'torrent') {
    $already = tracker_user_has_torrent_bookmark($userId, $entityId);
    if ($already) {
        sql_query("DELETE FROM torrent_bookmarks WHERE user_id = " . $userId . " AND torrent_id = " . $entityId . " LIMIT 1");
    } else {
        sql_query("INSERT IGNORE INTO torrent_bookmarks (user_id, torrent_id, added) VALUES (" . $userId . ", " . $entityId . ", NOW())");
    }

    tracker_invalidate_torrent_bookmark_cache($userId, $entityId);
} else {
    $already = tracker_user_has_release_group_bookmark($userId, $entityId);
    if ($already) {
        sql_query("DELETE FROM release_group_bookmarks WHERE user_id = " . $userId . " AND group_id = " . $entityId . " LIMIT 1");
    } else {
        sql_query("INSERT IGNORE INTO release_group_bookmarks (user_id, group_id, added) VALUES (" . $userId . ", " . $entityId . ", NOW())");
    }

    tracker_invalidate_release_group_bookmark_cache($userId, $entityId);
}

header('Location: ' . $returnto, true, 302);
exit;
