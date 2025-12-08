<?php
require_once 'include/bittorrent.php';
dbconn(false);

// Безопасные входы
$mode = $_GET['browsemode'] ?? '';
$mode = ($mode === 'thumbs') ? 'thumbs' : 'list'; // по умолчанию список
$ret  = $_GET['ret'] ?? 'browse.php';

// Нормализуем ret: разрешаем только локальный путь
if (!is_string($ret) || stripos($ret, '://') !== false) {
    $ret = 'browse.php';
}

$uid = (int)($CURUSER['id'] ?? 0);

// Persist: memcached per user
if (isset($memcached) && $memcached instanceof Memcached) {
    $memcached->set("ui:browse_mode:{$uid}", $mode, 31536000); // 1 год
}

// Cookie per user (доп. страховка)
$cookie_name  = "browsemode_u{$uid}";
$cookie_value = $mode;
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie($cookie_name, $cookie_value, [
    'expires'  => time() + 31536000,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);

// Редирект обратно
header('Location: ' . $ret);
exit;
