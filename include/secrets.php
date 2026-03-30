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

$memcached = null;
$mc = null;
$mc1 = null;

if (class_exists('Memcached')) {
    $memcached = new Memcached('torrentside_pool');

    if (empty($memcached->getServerList())) {
        $memcached->addServer($memcache_host, $memcache_port);
    }

    if (defined('Memcached::OPT_BINARY_PROTOCOL')) {
        $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
    }
    if (defined('Memcached::OPT_TCP_NODELAY')) {
        $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);
    }
    if (defined('Memcached::OPT_CONNECT_TIMEOUT')) {
        $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 80);
    }
    if (defined('Memcached::OPT_RETRY_TIMEOUT')) {
        $memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
    }
    if (defined('Memcached::OPT_POLL_TIMEOUT')) {
        $memcached->setOption(Memcached::OPT_POLL_TIMEOUT, 80);
    }

    $mc = $memcached;
    $mc1 = $memcached;
}
