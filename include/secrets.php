<?php
// ============================================================================
// КОНФИГУРАЦИЯ ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ MySQL
// ============================================================================
$mysqli_host = "db";
$mysqli_user = "root";
$mysqli_pass = "";
$mysqli_db   = "torrent2";
$mysqli_charset = "utf8mb4";

$mysqli = new mysqli($mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db);

if ($mysqli->connect_error) {
    die("Ошибка подключения к базе данных: " . $mysqli->connect_error);
}

if (!$mysqli->set_charset($mysqli_charset)) {
    die("Ошибка установки кодировки подключения: " . $mysqli->error);
}

// ============================================================================
// КОНФИГУРАЦИЯ ПОДКЛЮЧЕНИЯ К MEMCACHED
// ============================================================================
$memcache_host = "memcached";
$memcache_port = 11211;

$memcached = new Memcached();
$memcached->addServer($memcache_host, $memcache_port);
