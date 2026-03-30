<?php
declare(strict_types=1);

// === Режим анонса ===
define('IN_ANNOUNCE', true);

// === Зависимости ===
require_once __DIR__ . '/include/secrets.php';      // инициализирует $mysqli
require_once __DIR__ . '/include/cache.php';
require_once __DIR__ . '/include/benc.php';
require_once __DIR__ . '/include/config.php';       // $announce_interval

function announce_benc_resp_raw(string $payload): void
{
    header('Content-Type: text/plain');
    header('Pragma: no-cache');
    echo $payload;
}

// === Проверяем $mysqli ===
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    announce_benc_resp_raw(benc([
        'type' => 'dictionary',
        'value' => [
            'failure reason' => ['type' => 'string', 'value' => 'DB unavailable'],
        ],
    ]));
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

function announce_fail(string $reason): void {
    announce_benc_resp_raw(benc([
        'type' => 'dictionary',
        'value' => [
            'failure reason' => ['type' => 'string', 'value' => $reason],
        ],
    ]));
    exit;
}

function announce_send(array $payload): void
{
    announce_benc_resp_raw(benc([
        'type' => 'dictionary',
        'value' => $payload,
    ]));
    exit;
}

function announce_cached_user(mysqli $mysqli, string $passkey): ?array
{
    $key = tracker_cache_key('announce', 'user', $passkey);

    $row = tracker_cache_remember($key, 60, static function () use ($mysqli, $passkey): ?array {
        $stmt = $mysqli->prepare('SELECT id, passkey_ip, class, parked, hiderating FROM users WHERE passkey = ? LIMIT 1');
        $stmt->bind_param('s', $passkey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return $row;
    });

    return is_array($row) ? $row : null;
}

function announce_cached_torrent(mysqli $mysqli, string $infoHashHex): ?array
{
    $key = tracker_cache_key('announce', 'torrent', $infoHashHex);

    $row = tracker_cache_remember($key, 60, static function () use ($mysqli, $infoHashHex): ?array {
        $stmt = $mysqli->prepare('SELECT id, banned, free, seeders, leechers, times_completed FROM torrents WHERE info_hash = UNHEX(?) LIMIT 1');
        $stmt->bind_param('s', $infoHashHex);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return $row;
    });

    return is_array($row) ? $row : null;
}

function announce_cached_peer_pool(mysqli $mysqli, int $torrentId, int $limit): array
{
    $limit = max(50, min(200, $limit));
    $key = tracker_cache_key('announce', 'peerpool', 't' . $torrentId, 'l' . $limit);

    $rows = tracker_cache_remember($key, 5, static function () use ($mysqli, $torrentId, $limit): array {
        $stmt = $mysqli->prepare('
            SELECT peer_id, ip, port, seeder
            FROM peers
            WHERE torrent = ?
            ORDER BY last_action DESC
            LIMIT ?
        ');
        $stmt->bind_param('ii', $torrentId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    });

    return is_array($rows) ? $rows : [];
}

function announce_normalize_ip(string $ip): string
{
    $ip = trim($ip);
    if ($ip === '') {
        return '';
    }

    $packed = @inet_pton($ip);
    if ($packed === false) {
        return '';
    }

    $normalized = @inet_ntop($packed);
    return is_string($normalized) && $normalized !== '' ? $normalized : $ip;
}

function announce_ip_equals(string $left, string $right): bool
{
    $leftRaw = trim($left);
    $rightRaw = trim($right);
    $left = announce_normalize_ip($left);
    $right = announce_normalize_ip($right);
    if ($left === '' || $right === '') {
        return $leftRaw === $rightRaw;
    }

    return $left === $right;
}

// === Безопасный IP-детектор с учётом списков в XFF ===
function announce_get_ip(): string {
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
        announce_fail('Missing key: ' . $key);
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
$ip         = announce_get_ip();
$requestIsIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
$agent      = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 200); // капнем длину

// === Валидация бинарных полей и прочего ===
if (strlen($info_hash) !== 20 || strlen($peer_id) !== 20 || strlen($passkey) !== 32) {
    announce_fail('Invalid info_hash, peer_id, or passkey');
}
if ($port < 1 || $port > 65535) {
    announce_fail('Invalid port');
}

// === Бан клиентов (учитываем бинарность peer_id) ===
$banned_clients = [
    'FUTB', '-BB', '-SZ', '-AG', 'turbo', 'T03A', 'T03B', 'R34', 'FRS', '-FG', '-XX0025-',
];
$prefix4 = substr($peer_id, 0, 4);
$prefix8 = substr($peer_id, 0, 8);
foreach ($banned_clients as $pref) {
    if ($pref === $prefix4 || $pref === $prefix8 || strncmp($peer_id, $pref, strlen($pref)) === 0) {
        announce_fail('Banned client');
    }
}

// === Пользователь по passkey ===
$user = announce_cached_user($mysqli, $passkey);

if (!$user) {
    announce_fail('Invalid passkey');
}

$userid = (int)$user['id'];

// === Проверка закрепления IP для passkey (если включено) ===
$passkey_ip = (string)($user['passkey_ip'] ?? '');
if ($passkey_ip !== '' && !announce_ip_equals($passkey_ip, $ip)) {
    announce_fail('IP mismatch for this passkey');
}

// === Parked-аккаунты не пускаем ===
if (($user['parked'] ?? 'no') === 'yes') {
    announce_fail('Account is parked');
}

// === Поиск торрента по info_hash (бинарный) ===
$info_hash_hex = bin2hex($info_hash);
$torrent = announce_cached_torrent($mysqli, $info_hash_hex);

if (!$torrent) {
    announce_fail('Torrent not found');
}

if (($torrent['banned'] ?? 'no') === 'yes') {
    announce_fail('Torrent is banned');
}

$torrentid = (int)$torrent['id'];
$seeder    = ($left === 0) ? 'yes' : 'no';
$now       = date('Y-m-d H:i:s');

// === Выдаем список пиров (numwant) ===
$peers_compact_v4 = '';
$peers_compact_v6 = '';
$peers_list = [];

if ($numwant > 0) {
    $peerPool = announce_cached_peer_pool($mysqli, $torrentid, max($numwant + 20, 80));
    $ipv4Peers = [];
    $ipv6Peers = [];

    foreach ($peerPool as $row) {
        if (($row['peer_id'] ?? '') === $peer_id) {
            continue;
        }

        $peerIp = announce_normalize_ip((string)($row['ip'] ?? ''));
        $peerPort = (int)($row['port'] ?? 0);
        if ($peerIp === '' || $peerPort < 1 || $peerPort > 65535) {
            continue;
        }

        $peer = [
            'ip'   => $peerIp,
            'port' => $peerPort,
        ];
        if (!$no_peer_id && isset($row['peer_id'])) {
            $peer['peer id'] = (string)$row['peer_id'];
        }

        if (filter_var($peerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $ipv6Peers[] = $peer;
        } else {
            $ipv4Peers[] = $peer;
        }
    }

    $selectedPeers = $requestIsIpv6
        ? array_merge($ipv6Peers, $ipv4Peers)
        : array_merge($ipv4Peers, $ipv6Peers);
    $selectedPeers = array_slice($selectedPeers, 0, $numwant);

    if ($compact) {
        foreach ($selectedPeers as $peer) {
            if (filter_var((string)$peer['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ipParts = array_map('intval', explode('.', (string)$peer['ip']));
                if (count($ipParts) === 4) {
                    $peers_compact_v4 .= pack('C4n', $ipParts[0], $ipParts[1], $ipParts[2], $ipParts[3], (int)$peer['port']);
                }
                continue;
            }

            $packedIpv6 = @inet_pton((string)$peer['ip']);
            if ($packedIpv6 !== false && strlen($packedIpv6) === 16) {
                $peers_compact_v6 .= $packedIpv6 . pack('n', (int)$peer['port']);
            }
        }
    } else {
        $peers_list = $selectedPeers;
    }
}

// === Проверяем, есть ли уже запись этого пира ===
$stmt = $mysqli->prepare('SELECT id, uploaded, downloaded, seeder FROM peers WHERE torrent = ? AND peer_id = ? LIMIT 1');
$stmt->bind_param('is', $torrentid, $peer_id);
$stmt->execute();
$self = $stmt->get_result()->fetch_assoc();
$stmt->close();
$previousSeeder = $self ? (((string)($self['seeder'] ?? 'no') === 'yes') ? 'yes' : 'no') : null;
$deltaSeeders = 0;
$deltaLeechers = 0;

// === Обработка события stopped: корректно удаляем (без чейнинга методов) ===
if ($event === 'stopped') {
    $stmt = $mysqli->prepare('DELETE FROM peers WHERE torrent = ? AND peer_id = ?');
    $stmt->bind_param('is', $torrentid, $peer_id);
    $stmt->execute();
    $stmt->close();

    if ($previousSeeder === 'yes') {
        $deltaSeeders = -1;
    } elseif ($previousSeeder === 'no') {
        $deltaLeechers = -1;
    }

} else {
    $uploaded   = $self ? max($uploaded, (int)$self['uploaded']) : $uploaded;
    $downloaded = $self ? max($downloaded, (int)$self['downloaded']) : $downloaded;

    $connectable = 'yes';
    $stmt = $mysqli->prepare('
        INSERT INTO peers
            (torrent, peer_id, ip, port, uploaded, downloaded, to_go, seeder, userid, agent, started, last_action, prev_action, passkey, connectable)
        VALUES
            (?,       ?,       ?,  ?,    ?,        ?,          ?,     ?,      ?,     ?,     ?,       ?,           ?,          ?,       ?)
        ON DUPLICATE KEY UPDATE
            ip           = VALUES(ip),
            port         = VALUES(port),
            uploaded     = GREATEST(uploaded, VALUES(uploaded)),
            downloaded   = GREATEST(downloaded, VALUES(downloaded)),
            to_go        = VALUES(to_go),
            seeder       = VALUES(seeder),
            userid       = VALUES(userid),
            agent        = VALUES(agent),
            prev_action  = last_action,
            last_action  = VALUES(last_action),
            passkey      = VALUES(passkey),
            connectable  = VALUES(connectable)
    ');
    $stmt->bind_param(
        'issiiiisissssss',
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
        $now,
        $now,
        $passkey,
        $connectable
    );
    $stmt->execute();
    $stmt->close();

    if ($self) {
        if ($previousSeeder !== $seeder) {
            if ($seeder === 'yes') {
                $deltaSeeders = 1;
                $deltaLeechers = -1;
            } else {
                $deltaSeeders = -1;
                $deltaLeechers = 1;
            }
        }
    } else {
        if ($seeder === 'yes') {
            $deltaSeeders = 1;
        } else {
            $deltaLeechers = 1;
        }
    }

    // если клиент сообщил 'completed' — увеличим счётчик завершений
    $completedDelta = 0;
    if ($event === 'completed' && $seeder === 'yes' && $previousSeeder !== 'yes') {
        $stmt = $mysqli->prepare('UPDATE torrents SET times_completed = times_completed + 1 WHERE id = ?');
        $stmt->bind_param('i', $torrentid);
        $stmt->execute();
        $stmt->close();
        $completedDelta = 1;
    }
}

if ($deltaSeeders !== 0 || $deltaLeechers !== 0) {
    $stmt = $mysqli->prepare('
        UPDATE torrents
           SET seeders = GREATEST(seeders + ?, 0),
               leechers = GREATEST(leechers + ?, 0),
               last_action = NOW()
         WHERE id = ?
    ');
    $stmt->bind_param('iii', $deltaSeeders, $deltaLeechers, $torrentid);
    $stmt->execute();
    $stmt->close();
}

// === Формируем ответ клиенту ===
$complete = max(0, (int)($torrent['seeders'] ?? 0) + $deltaSeeders);
$incomplete = max(0, (int)($torrent['leechers'] ?? 0) + $deltaLeechers);
$downloadedCount = max(0, (int)($torrent['times_completed'] ?? 0) + ($completedDelta ?? 0));

tracker_cache_set(
    tracker_cache_key('announce', 'torrent', $info_hash_hex),
    [
        'id' => $torrentid,
        'banned' => (string)($torrent['banned'] ?? 'no'),
        'free' => (int)($torrent['free'] ?? 0),
        'seeders' => $complete,
        'leechers' => $incomplete,
        'times_completed' => $downloadedCount,
    ],
    60
);
tracker_cache_delete(tracker_cache_key('announce', 'peerpool', 't' . $torrentid, 'l' . max($numwant + 20, 80)));

$response = [
    'interval' => ['type' => 'integer', 'value' => (int)($announce_interval ?? 1800)],
    'min interval' => ['type' => 'integer', 'value' => (int)($announce_interval ?? 1800)],
    'complete' => ['type' => 'integer', 'value' => $complete],
    'incomplete' => ['type' => 'integer', 'value' => $incomplete],
];

if ($compact) {
    $response['peers'] = ['type' => 'string', 'value' => $peers_compact_v4];
    if ($peers_compact_v6 !== '') {
        $response['peers6'] = ['type' => 'string', 'value' => $peers_compact_v6];
    }
} else {
    $peerNodes = [];
    foreach ($peers_list as $peer) {
        $value = [
            'ip'   => ['type' => 'string', 'value' => (string)$peer['ip']],
            'port' => ['type' => 'integer', 'value' => (int)$peer['port']],
        ];
        if (!$no_peer_id && isset($peer['peer id'])) {
            $value['peer id'] = ['type' => 'string', 'value' => (string)$peer['peer id']];
        }
        $peerNodes[] = ['type' => 'dictionary', 'value' => $value];
    }
    $response['peers'] = ['type' => 'list', 'value' => $peerNodes];
}

announce_send($response);
