<?php
require_once "include/bittorrent.php";
require_once "include/benc.php";

dbconn();
loggedinorreturn();
parked();

/* ===================== helpers ===================== */

/**
 * Приводит info_hash (VARBINARY(40)) к 40-символьному HEX (верхний регистр).
 * Поддерживает как 20 байт, так и уже сохранённый HEX.
 */
function ih_to_hex(string $raw): string {
    $len = strlen($raw);
    if ($len === 20) {
        return strtoupper(bin2hex($raw));
    }
    if ($len === 40 && ctype_xdigit($raw)) {
        return strtoupper($raw);
    }
    if ($len > 0 && ctype_print($raw) && ctype_xdigit($raw)) {
        return strtoupper($raw);
    }
    stderr('Ошибка', 'Некорректный info_hash в базе.'); exit;
}

/**
 * RFC 4648 base32 без паддинга из HEX.
 */
function base32_from_hex(string $hex): string {
    $bin = @hex2bin(strtolower($hex));
    if ($bin === false) return '';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $out  = '';

    $bl = strlen($bin);
    for ($i = 0; $i < $bl; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $L = strlen($bits);
    for ($i = 0; $i < $L; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

/**
 * Добавляет passkey ко всем URL анонсеров и вычищает дубли.
 */
function tracker_urls_with_passkey(array $urls, string $passkey): array {
    $out = [];
    foreach ($urls as $u) {
        if (!is_string($u) || $u === '') continue;
        $sep = (str_contains($u, '?') ? '&' : '?');
        $out[] = $u . $sep . 'passkey=' . $passkey;
    }
    return array_values(array_unique($out));
}

/* ===================== gzip guard ===================== */

if (@ini_get('output_handler') === 'ob_gzhandler' && @ob_get_length() !== false) {
    @ob_end_clean();
    header('Content-Encoding:');
}

/* ===================== input ===================== */

$id         = (int)($_GET['id'] ?? 0);
$nameParam  = trim((string)($_GET['name'] ?? ''));
$wantMagnet = isset($_GET['magnet']) && (int)$_GET['magnet'] === 1;

if ($id <= 0 || $nameParam === '') {
    stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
}

/* ===================== fetch torrent row ===================== */

$q = sql_query("SELECT name, filename, size, info_hash FROM torrents WHERE id = " . sqlesc($id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
$t = mysqli_fetch_assoc($q);
if (!$t) {
    stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
}

/* ===================== .torrent exists ===================== */

$fn = "$torrent_dir/$id.torrent";
if (!is_file($fn) || !is_readable($fn)) {
    stderr($tracker_lang['error'], $tracker_lang['unable_to_read_torrent']);
}

/* ===================== hits ===================== */

sql_query("UPDATE torrents SET hits = hits + 1 WHERE id = " . sqlesc($id));

/* ===================== ensure passkey ===================== */

if (empty($CURUSER['passkey']) || strlen($CURUSER['passkey']) !== 32) {
    $passkey = md5($CURUSER['username'] . get_date_time() . $CURUSER['passhash']);
    $CURUSER['passkey'] = $passkey;
    sql_query("UPDATE users SET passkey = " . sqlesc($passkey) . " WHERE id = " . sqlesc($CURUSER['id']));
}

/* ===================== prepare tracker list ===================== */

$trackerPool = [];

// 1) из конфига
if (!empty($announce_urls) && is_array($announce_urls)) {
    $trackerPool = array_merge($trackerPool, $announce_urls);
}

// 2) из .torrent
$dict = bdec_file($fn, $max_torrent_size);
if (isset($dict['value']['announce']['type']) && $dict['value']['announce']['type'] === 'string') {
    $trackerPool[] = $dict['value']['announce']['value'];
}
if (isset($dict['value']['announce-list']['type']) && $dict['value']['announce-list']['type'] === 'list') {
    foreach ($dict['value']['announce-list']['value'] as $tier) {
        if (($tier['type'] ?? null) !== 'list') continue;
        foreach ($tier['value'] as $trk) {
            if (($trk['type'] ?? null) === 'string') {
                $trackerPool[] = $trk['value'];
            }
        }
    }
}

// 3) дефолт, если вообще пусто
if (empty($trackerPool) && defined('BASEURL')) {
    $trackerPool[] = rtrim(BASEURL, '/') . '/announce.php';
}

$trackerWithKey = tracker_urls_with_passkey($trackerPool, $CURUSER['passkey']);

/* ===================== MAGNET branch ===================== */

if ($wantMagnet) {
    $hex = ih_to_hex($t['info_hash']);
    $b32 = base32_from_hex($hex);

    // Два xt для максимальной совместимости
    $parts = [
        'magnet:?xt=urn:btih:' . $hex,
        'xt=urn:btih:' . $b32,
        'dn=' . rawurlencode($nameParam),
    ];

    // Трекеры (до 20)
    foreach (array_slice($trackerWithKey, 0, 20) as $tr) {
        $parts[] = 'tr=' . rawurlencode($tr);
    }

    // Размер (не обязателен)
    $size = (int)($t['size'] ?? 0);
    if ($size > 0) {
        $parts[] = 'xl=' . $size;
    }

    header('Location: ' . implode('&', $parts), true, 302);
    exit;
}

/* ===================== TORRENT branch ===================== */

$primaryAnnounce = $trackerWithKey[0] ?? ($announce_urls[0] . '?passkey=' . $CURUSER['passkey']);

// Подменяем announce
$dict['value']['announce'] = [
    'type'   => 'string',
    'value'  => $primaryAnnounce,
    'strlen' => strlen($primaryAnnounce),
    'string' => strlen($primaryAnnounce) . ':' . $primaryAnnounce
];

// Перезаписываем announce-list нашими URL с passkey
if (!empty($trackerWithKey)) {
    $alist = ['type' => 'list', 'value' => []];
    foreach (array_slice($trackerWithKey, 0, 20) as $trk) {
        $alist['value'][] = [
            'type'  => 'list',
            'value' => [[
                'type'   => 'string',
                'value'  => $trk,
                'strlen' => strlen($trk),
                'string' => strlen($trk) . ':' . $trk
            ]]
        ];
    }
    $dict['value']['announce-list'] = $alist;
}

/* ===================== headers & output ===================== */

header("Expires: Tue, 01 Jan 1980 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Accept-Ranges: bytes");
header("Connection: close");
header("Content-Transfer-Encoding: binary");
header("Content-Disposition: attachment; filename=\"" . basename($nameParam) . "\"");
header("Content-Type: application/x-bittorrent");

ob_implicit_flush(true);
echo benc($dict);
exit;
