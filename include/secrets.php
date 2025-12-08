<?php
// Настройки подключения к базе данных
$mysqli_host = "localhost";
$mysqli_user = "root";
$mysqli_pass = "";
$mysqli_db   = "torrent2";
$mysqli_charset = "utf8";

// Подключение к MySQL
$mysqli = new mysqli($mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db);

if ($mysqli->connect_error) {
    die("Ошибка подключения: " . $mysqli->connect_error);
}

if (!$mysqli->set_charset($mysqli_charset)) {
    die("Ошибка установки кодировки: " . $mysqli->error);
}

// === Memcached ===
$memcache_host = "127.0.0.1";
$memcache_port = 11211;

$memcached = new Memcached();
$memcached->addServer($memcache_host, $memcache_port);

?>
