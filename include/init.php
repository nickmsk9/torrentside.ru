<?php
# IMPORTANT: Do not edit below unless you know what you are doing!
if (!defined('IN_TRACKER') && !defined('IN_ANNOUNCE')) {
    exit('Hacking attempt!');
}

/**
 * htmlspecialchars_uni — оставляем сигнатуру и эффект, но делаем чуть аккуратнее:
 * - экранируем амперсанды, кроме уже готовых сущностей (&...;)
 * - экранируем < > " ' (кавычки добавлены; раньше могли «протечь» одинарные)
 * - двойные пробелы -> &nbsp;&nbsp; (как в оригинале)
 */
if (!function_exists('htmlspecialchars_uni')) {
    function htmlspecialchars_uni(string $message): string
    {
        // & -> &amp;, но не трогаем уже оформленные сущности &...;
        $message = preg_replace('/&(?!(?:#\d+|#x[a-fA-F0-9]+|\w+);)/', '&amp;', $message);
        // базовые символы HTML
        $message = str_replace(
            ['<',   '>',   '"',    "'"],
            ['&lt;','&gt;','&quot;','&#039;'],
            $message
        );
        // двойные пробелы сохраняем визуально
        $message = str_replace('  ', '&nbsp;&nbsp;', $message);

        return $message;
    }
}

/* ========= DEFINE IMPORTANT CONSTANTS ========= */
if (!defined('TIMENOW')) {
    define('TIMENOW', time());
}

/**
 * Базовый URL: учитываем reverse proxy (X-Forwarded-Proto), нестандартные порты и отсутствие HTTP_HOST.
 */
$https =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

$scheme = $https ? 'https' : 'http';

// Надёжно берём хост (HTTP_HOST предпочтительнее), с фолбэком и санитизацией.
$rawHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', (string)$rawHost); // допускаем IPv6 в []

// Если порта нет в HOST и он нестандартный — добавим
if (strpos($host, ':') === false && isset($_SERVER['SERVER_PORT'])) {
    $port = (int) $_SERVER['SERVER_PORT'];
    if (($https && $port !== 443) || (!$https && $port !== 80)) {
        $host .= ':' . $port;
    }
}

$DEFAULTBASEURL = $scheme . '://' . $host;
$BASEURL = $DEFAULTBASEURL;

// Вспомогательный массив анонсеров (как было)
$announce_urls = [];
$announce_urls[] = $DEFAULTBASEURL . '/announce.php';

/* ========= DEFINE TRACKER GROUPS (idempotent) ========= */
defined('UC_USER')           || define('UC_USER', 0);
defined('UC_POWER_USER')     || define('UC_POWER_USER', 1);
defined('UC_VIP')            || define('UC_VIP', 2);
defined('UC_UPLOADER')       || define('UC_UPLOADER', 3);
defined('UC_MODERATOR')      || define('UC_MODERATOR', 4);
defined('UC_ADMINISTRATOR')  || define('UC_ADMINISTRATOR', 5);
defined('UC_SYSOP')          || define('UC_SYSOP', 6);
