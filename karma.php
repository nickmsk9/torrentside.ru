<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/init.php';

dbconn();

global $tracker_lang, $CURUSER, $mysqli;
/** @var mysqli $mysqli */
if (!$mysqli instanceof mysqli) {
    // На всякий случай — без корректного $mysqli работать нельзя
    http_response_code(500);
    die('DB handle ($mysqli) is not available');
}

header('Content-Type: text/html; charset=' . ($tracker_lang['language_charset'] ?? 'UTF-8'));

/* ===================== ЛОГИРОВАНИЕ ===================== */
const KARMA_LOG_DIR  = __DIR__ . '/logs';
const KARMA_LOG_FILE = KARMA_LOG_DIR . '/ajax_karma.log';

if (!is_dir(KARMA_LOG_DIR)) {
    @mkdir(KARMA_LOG_DIR, 0775, true);
}
function karma_log(string $level, string $message, array $ctx = []) : void {
    $row = [
        'ts'  => date('c'),
        'lvl' => $level,
        'msg' => $message,
        'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'uid' => $GLOBALS['CURUSER']['id'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'ctx' => $ctx,
    ];
    @file_put_contents(KARMA_LOG_FILE, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/* ===================== ХЕЛПЕРЫ ===================== */
function is_ajax(): bool {
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}
function bad_access(string $why = 'Прямой доступ закрыт'): never {
    karma_log('warn', 'Access blocked', ['why' => $why]);
    http_response_code(400);
    die($why);
}

/* ===================== ПРОВЕРКА ДОСТУПА ===================== */
if (!is_ajax() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bad_access();
}

/* ===================== ВХОДНЫЕ ДАННЫЕ ===================== */
$id   = (int)($_POST['id']   ?? 0);
$user = (int)($CURUSER['id'] ?? 0);
$act  = trim((string)($_POST['act']  ?? ''));
$type = trim((string)($_POST['type'] ?? ''));

if ($id <= 0 || $user <= 0 || $act === '' || $type === '') {
    bad_access('Прямой доступ закрыт');
}
if (!in_array($type, ['torrent','comment','user'], true)) {
    bad_access('Прямой доступ закрыт');
}
if (!in_array($act, ['plus','minus'], true)) {
    bad_access('Прямой доступ закрыт');
}

/* ===================== ТАБЛИЦА ПО ТИПУ ===================== */
$map = [
    'torrent' => 'torrents',
    'comment' => 'comments',
    'user'    => 'users',
];
$table = $map[$type];

/* ===================== ПРОВЕРКА ДУБЛЯ ГОЛОСА ===================== */
$canrate = get_row_count(
    'karma',
    'WHERE type = ' . sqlesc($type) . " AND value = $id AND user = $user"
);
if ($canrate > 0) {
    karma_log('info', 'Duplicate vote rejected', compact('type','id','user'));
    die('Вы уже голосовали');
}

/* ===================== ТРАНЗАКЦИЯ ===================== */
$delta = ($act === 'plus') ? 1 : -1;
$ok = true;

// Явные транзакции через mysqli
$mysqli->begin_transaction();

$upd_sql = "UPDATE $table SET karma = karma + ($delta) WHERE id = $id";
$upd_ok  = $mysqli->query($upd_sql);
if (!$upd_ok || $mysqli->affected_rows < 1) {
    $ok = false;
    karma_log('error', 'Update karma failed', ['sql' => $upd_sql, 'table' => $table, 'id' => $id, 'delta' => $delta, 'errno' => $mysqli->errno, 'error' => $mysqli->error]);
}

// voted_on обязателен (DATE NOT NULL), пишем CURDATE()
$ins_sql = "
    INSERT INTO karma (type, value, user, added, voted_on)
    VALUES (" . sqlesc($type) . ", $id, $user, " . time() . ", CURDATE())
";
$ins_ok = $mysqli->query($ins_sql);
if (!$ins_ok) {
    $ok = false;
    karma_log('error', 'Insert vote failed', ['sql' => $ins_sql, 'type' => $type, 'id' => $id, 'user' => $user, 'errno' => $mysqli->errno, 'error' => $mysqli->error]);
}

if ($ok) {
    $mysqli->commit();
    karma_log('info', 'Vote committed', compact('type','id','user','delta'));
} else {
    $mysqli->rollback();
    http_response_code(500);
    die('Ошибка запроса');
}

/* ===================== ВОЗВРАТ ОБНОВЛЁННОГО ЗНАЧЕНИЯ ===================== */
$sel_sql = "SELECT karma FROM $table WHERE id = $id LIMIT 1";
$sel_res = $mysqli->query($sel_sql);
$row     = $sel_res ? $sel_res->fetch_assoc() : null;

if ($row && isset($row['karma'])) {
    echo '<img src="pic/minus-dis.png" title="Вы не можете голосовать" alt="" />&nbsp;'
       . karma((int)$row['karma'])
       . '&nbsp;<img src="pic/plus-dis.png" title="Вы не можете голосовать" alt="" />';
    exit;
}

karma_log('error', 'Select karma failed after commit', ['sql' => $sel_sql, 'table' => $table, 'id' => $id, 'errno' => $mysqli->errno, 'error' => $mysqli->error]);
http_response_code(500);
die('Ошибка запроса');
