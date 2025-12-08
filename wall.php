<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
dbconn(false);
loggedinorreturn();

// Всегда JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$xh     = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$acc    = $_SERVER['HTTP_ACCEPT'] ?? '';

// Принимаем ЛЮБОЙ POST из того же сайта;
// не заваливаемся, если заголовок X-Requested-With отсутствует
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'err'=>'method']); exit;
}
// (опционально проверка происхождения — если у тебя есть csrf)


// (если используете CSRF)
// if (function_exists('check_token') && !check_token('post')) {
//     http_response_code(403);
//     echo json_encode(['ok'=>false,'err'=>'csrf']); exit;
// }

$CURUSER_ID = (int)($CURUSER['id'] ?? 0);
if ($CURUSER_ID <= 0) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'err'=>'auth']); exit;
}

/** @var mysqli|null $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'err'=>'db']); exit;
}

// Обёртки кэша (если нет Memcached — просто no-op)
if (!function_exists('mc_get'))    { function mc_get(string $k){ return false; } }
if (!function_exists('mc_set'))    { function mc_set(string $k,$v,int $ttl=60){ return false; } }

$act   = (string)($_POST['act']   ?? '');
$owner = (int)   ($_POST['owner'] ?? 0);
if ($owner <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'owner']); exit; }

if ($act === 'send') {
    $raw  = (string)($_POST['text'] ?? '');
    $text = base64_decode($raw, true);
    if ($text === false) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'b64']); exit; }

    $text = trim($text);
    $text = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', '', $text);
    if ($text === '') { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'empty']); exit; }
    if (strlen($text) > 16384) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'too_long']); exit; }

    $stmt = $mysqli->prepare("INSERT INTO wall (`user`, owner, `text`, added) VALUES (?, ?, ?, NOW())");
    if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'stmt']); exit; }
    $stmt->bind_param('iis', $CURUSER_ID, $owner, $text);
    $ok = $stmt->execute();
    $newId = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();

    if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'insert']); exit; }

    // обновим кэш-счётчик, если есть
    $k = "wall:count:$owner";
    $c = mc_get($k);
    if (is_int($c)) mc_set($k, $c + 1, 60);

    echo json_encode(['ok'=>true,'id'=>$newId]); exit;
}

if ($act === 'delete') {
    $postId = (int)($_POST['post'] ?? 0);
    if ($postId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'id']); exit; }

    $res = sql_query("SELECT id, owner, `user` FROM wall WHERE id = $postId LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) { http_response_code(404); echo json_encode(['ok'=>false,'err'=>'nf']); exit; }
    $row = mysqli_fetch_assoc($res);
    $ownerRow = (int)$row['owner'];
    $author   = (int)$row['user'];

    $isMod   = (int)get_user_class() >= UC_MODERATOR;
    $allowed = ($CURUSER_ID === $ownerRow) || ($CURUSER_ID === $author) || $isMod;
    if (!$allowed) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'perm']); exit; }

    $stmt = $mysqli->prepare("DELETE FROM wall WHERE id = ?");
    if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'stmt']); exit; }
    $stmt->bind_param('i', $postId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'delete']); exit; }

    $k = "wall:count:$ownerRow";
    $c = mc_get($k);
    if (is_int($c) && $c > 0) mc_set($k, $c - 1, 60);

    echo json_encode(['ok'=>true]); exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'err'=>'unknown']);
