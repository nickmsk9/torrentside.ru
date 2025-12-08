<?php
declare(strict_types=1);

// === Режим анонса ===
define('IN_ANNOUNCE', true);

// === Зависимости ===
require_once __DIR__ . '/include/secrets.php';      // инициализирует $mysqli
require_once __DIR__ . '/include/core_announce.php';
require_once __DIR__ . '/include/functions_announce.php';
require_once __DIR__ . '/include/benc.php';
require_once __DIR__ . '/include/config.php';       // $announce_interval

// === Проверяем $mysqli ===
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    benc_resp_raw(['failure reason' => 'DB unavailable']);
    exit;
}

// === GZIP: проверка по подстроке, а не строгому равенству ===
$ae = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
if (stripos($ae, 'gzip') !== false) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// === Простой лог (по желанию подключайте) ===
function announce_log(string $msg): void {
    @error_log(date('[Y-m-d H:i:s] ') . $msg . "\n", 3, __DIR__ . '/logs/announce.log');
}

// === Безопасный IP-детектор с учётом списков в XFF ===
function getip(): string {
    $candidates = [];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Берём первый «реальный» IP слева
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $ip = trim($part);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $candidates[] = $ip;
                break;
            }
        }
    }
    if (empty($candidates) && !empty($_SERVER['HTTP_CLIENT_IP']) &&
        filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
    }
    $ra = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ra && filter_var($ra, FILTER_VALIDATE_IP)) $candidates[] = $ra;
    return $candidates[0] ?? '0.0.0.0';
}

// === Обязательные GET-параметры ===
$required = ['passkey', 'info_hash', 'peer_id', 'port', 'uploaded', 'downloaded', 'left'];
foreach ($required as $key) {
    if (!array_key_exists($key, $_GET)) {
        benc_resp_raw(['failure reason' => 'Missing key: ' . $key]);
        exit;
    }
}

// === НОРМАЛИЗАЦИЯ ВАЖНОЕ ===
// info_hash/peer_id приходят как percent-encoded бинарные 20 байт — используем rawurldecode
$passkey    = trim((string)$_GET['passkey']);
$info_hash  = rawurldecode((string)$_GET['info_hash']);
$peer_id    = rawurldecode((string)$_GET['peer_id']);

$port       = (int)$_GET['port'];
$uploaded   = (int)$_GET['uploaded'];
$downloaded = (int)$_GET['downloaded'];
$left       = (int)$_GET['left'];

$event      = (string)($_GET['event'] ?? '');              // '', 'started', 'stopped', 'completed'
$compact    = (int)($_GET['compact'] ?? 0);
$no_peer_id = (int)($_GET['no_peer_id'] ?? 0);
$numwant    = max(0, min(100, (int)($_GET['numwant'] ?? 50))); // верхний предел 100 — нормально
$ip         = getip();
$agent      = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 200); // капнем длину

// === Валидация бинарных полей и прочего ===
if (strlen($info_hash) !== 20 || strlen($peer_id) !== 20 || strlen($passkey) !== 32) {
    benc_resp_raw(['failure reason' => 'Invalid info_hash, peer_id, or passkey']);
    exit;
}
if ($port < 1 || $port > 65535) {
    benc_resp_raw(['failure reason' => 'Invalid port']);
    exit;
}

// === Бан клиентов (учитываем бинарность peer_id) ===
$banned_clients = [
    'FUTB', '-BB', '-SZ', '-AG', 'turbo', 'T03A', 'T03B', 'R34', 'FRS', '-FG', '-XX0025-',
];
$prefix4 = substr($peer_id, 0, 4);
$prefix8 = substr($peer_id, 0, 8);
foreach ($banned_clients as $pref) {
    if ($pref === $prefix4 || $pref === $prefix8 || strncmp($peer_id, $pref, strlen($pref)) === 0) {
        benc_resp_raw(['failure reason' => 'Banned client']);
        exit;
    }
}

// === Пользователь по passkey ===
$stmt = $mysqli->prepare('SELECT id, passkey_ip, class, parked, hiderating FROM users WHERE passkey = ? LIMIT 1');
$stmt->bind_param('s', $passkey);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    benc_resp_raw(['failure reason' => 'Invalid passkey']);
    exit;
}

$userid = (int)$user['id'];

// === Проверка закрепления IP для passkey (если включено) ===
$passkey_ip = (string)($user['passkey_ip'] ?? '');
if ($passkey_ip !== '' && $passkey_ip !== $ip) {
    benc_resp_raw(['failure reason' => 'IP mismatch for this passkey']);
    exit;
}

// === Parked-аккаунты не пускаем ===
if (($user['parked'] ?? 'no') === 'yes') {
    benc_resp_raw(['failure reason' => 'Account is parked']);
    exit;
}

// === Поиск торрента по info_hash (бинарный) ===
$info_hash_hex = bin2hex($info_hash);
$stmt = $mysqli->prepare('SELECT id, banned, free, seeders, leechers, times_completed FROM torrents WHERE info_hash = UNHEX(?) LIMIT 1');
$stmt->bind_param('s', $info_hash_hex);
$stmt->execute();
$torrent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$torrent) {
    benc_resp_raw(['failure reason' => 'Torrent not found']);
    exit;
}

$torrentid = (int)$torrent['id'];
$seeder    = ($left === 0) ? 'yes' : 'no';
$now       = date('Y-m-d H:i:s');

// === Выдаем список пиров (numwant) ===
// Не возвращаем самого себя (по peer_id)
$stmt = $mysqli->prepare('
    SELECT peer_id, ip, port, seeder
    FROM peers
    WHERE torrent = ? AND peer_id <> ?
    ORDER BY last_action DESC
    LIMIT ?
');
$stmt->bind_param('isi', $torrentid, $peer_id, $numwant);
$stmt->execute();
$res = $stmt->get_result();

$peers_compact = '';
$peers_list    = [];

while ($row = $res->fetch_assoc()) {
    // В compact-режиме — только IPv4; пропускаем иное
    if ($compact) {
        if (filter_var($row['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipParts = array_map('intval', explode('.', $row['ip']));
            if (count($ipParts) === 4) {
                $peers_compact .= pack('C4n', $ipParts[0], $ipParts[1], $ipParts[2], $ipParts[3], (int)$row['port']);
            }
        }
    } else {
        $peer = [
            'ip'   => $row['ip'],
            'port' => (int)$row['port'],
        ];
        if (!$no_peer_id) {
            // В неcompact-режиме BitTorrent-спеки ждут ключ 'peer id'
            $peer['peer id'] = $row['peer_id'];
        }
        $peers_list[] = $peer;
    }
}
$stmt->close();

// === Проверяем, есть ли уже запись этого пира ===
$stmt = $mysqli->prepare('SELECT id, uploaded, downloaded, seeder FROM peers WHERE torrent = ? AND peer_id = ? LIMIT 1');
$stmt->bind_param('is', $torrentid, $peer_id);
$stmt->execute();
$self = $stmt->get_result()->fetch_assoc();
$stmt->close();

// === Обработка события stopped: корректно удаляем (без чейнинга методов) ===
if ($event === 'stopped') {
    $stmt = $mysqli->prepare('DELETE FROM peers WHERE torrent = ? AND peer_id = ?');
    $stmt->bind_param('is', $torrentid, $peer_id);
    $stmt->execute();
    $stmt->close();

    // (опционально) быстрый подсчет сид/лич (можно держать триггерами/cron)
    // $mysqli->query("UPDATE torrents SET seeders = (SELECT COUNT(*) FROM peers WHERE torrent=$torrentid AND seeder='yes'),
    //                 leechers = (SELECT COUNT(*) FROM peers WHERE torrent=$torrentid AND seeder='no') WHERE id=$torrentid");

} else {
    if ($self) {
        // апдейт существующего пира
        $uploaded   = max($uploaded,   (int)$self['uploaded']);
        $downloaded = max($downloaded, (int)$self['downloaded']);

        $stmt = $mysqli->prepare('
            UPDATE peers
               SET uploaded = ?, downloaded = ?, to_go = ?, seeder = ?, last_action = ?, ip = ?, port = ?, agent = ?, connectable = ?
             WHERE torrent = ? AND peer_id = ?
        ');
        $connectable = 'yes'; // упрощённо; при желании добавьте fsockopen-тест
        $stmt->bind_param(
            'iiisssissis',
            $uploaded,
            $downloaded,
            $left,
            $seeder,
            $now,
            $ip,
            $port,
            $agent,
            $connectable,
            $torrentid,
            $peer_id
        );
        $stmt->execute();
        $stmt->close();

    } else {
        // вставка нового пира
        $connectable = 'yes';
        $stmt = $mysqli->prepare('
            INSERT INTO peers
                (torrent, peer_id, ip, port, uploaded, downloaded, to_go, seeder, userid, agent, last_action, passkey, connectable)
            VALUES
                (?,       ?,       ?,  ?,    ?,        ?,          ?,     ?,      ?,     ?,     ?,           ?,       ?)
        ');
        $stmt->bind_param(
            'issiiississss',
            $torrentid,
            $peer_id,
            $ip,
            $port,
            $uploaded,
            $downloaded,
            $left,
            $seeder,
            $userid,
            $agent,
            $now,
            $passkey,
            $connectable
        );
        $stmt->execute();
        $stmt->close();
    }

    // если клиент сообщил 'completed' — увеличим счётчик завершений
    if ($event === 'completed') {
        $stmt = $mysqli->prepare('UPDATE torrents SET times_completed = times_completed + 1 WHERE id = ?');
        $stmt->bind_param('i', $torrentid);
        $stmt->execute();
        $stmt->close();
    }
}

// === Формируем ответ клиенту ===
$response = [
    'interval' => (int)($announce_interval ?? 1800),
];

if ($compact) {
    // compact должен быть бинарной строкой (не массив!)
    $response['peers'] = $peers_compact;
} else {
    $response['peers'] = $peers_list;
}

benc_resp_raw($response);
