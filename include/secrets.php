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

// ============================================================================
// SOCIAL AUTH
// ============================================================================
$social_auth_auto_signup = true;

$social_telegram_enabled = filter_var(getenv('SOCIAL_TELEGRAM_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN);
$social_telegram_bot_username = getenv('SOCIAL_TELEGRAM_BOT_USERNAME') ?: '';
$social_telegram_bot_token = getenv('SOCIAL_TELEGRAM_BOT_TOKEN') ?: '';
$social_telegram_widget_size = getenv('SOCIAL_TELEGRAM_WIDGET_SIZE') ?: 'large';
$social_telegram_request_write_access = filter_var(getenv('SOCIAL_TELEGRAM_REQUEST_WRITE_ACCESS') ?: '0', FILTER_VALIDATE_BOOLEAN);
$social_telegram_auth_ttl = (int)(getenv('SOCIAL_TELEGRAM_AUTH_TTL') ?: 900);

$social_apple_enabled = filter_var(getenv('SOCIAL_APPLE_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN);
$social_apple_client_id = getenv('SOCIAL_APPLE_CLIENT_ID') ?: '';
$social_apple_team_id = getenv('SOCIAL_APPLE_TEAM_ID') ?: '';
$social_apple_key_id = getenv('SOCIAL_APPLE_KEY_ID') ?: '';
$social_apple_private_key_path = getenv('SOCIAL_APPLE_PRIVATE_KEY_PATH') ?: '';
$social_apple_private_key = getenv('SOCIAL_APPLE_PRIVATE_KEY') ?: '';
$social_apple_scope = getenv('SOCIAL_APPLE_SCOPE') ?: 'name email';
