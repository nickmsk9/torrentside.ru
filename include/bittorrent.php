<?php
declare(strict_types=1);

# IMPORTANT: Do not edit below unless you know what you are doing!

/* === security protection by n-sw-bit ::: ANTIDDOS (refined) ===
 * Чтобы включить защиту от флуда — блок уже активен. При желании можно
 * быстро отключить, закомментировав секцию до маркера END-ANTIDDOS.
 */

// Базовый URL нужен здесь — без хардкода домена
if (!defined('DEFAULTBASEURL')) {
    // Определяем схему (учитываем reverse proxy)
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    $scheme = $https ? 'https' : 'http';

    // Санитизируем хост, поддерживаем нестандартные порты
    $rawHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', (string)$rawHost);
    if (strpos($host, ':') === false) {
        $port = (int)($_SERVER['SERVER_PORT'] ?? 0);
        if (($https && $port && $port !== 443) || (!$https && $port && $port !== 80)) {
            $host .= ':' . $port;
        }
    }

    define('DEFAULTBASEURL', $scheme . '://' . $host);
} else {
    // Если уже определён где-то выше
    $https = (stripos((string)DEFAULTBASEURL, 'https://') === 0);
}

### BEGIN-ANTIDDOS ###
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot =
    (stripos($ua, 'googlebot') !== false) ||
    (stripos($ua, 'yandexbot') !== false);

// Определяем, что мы НЕ в announce.php
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$isAnnounce = (substr($script, -12) === '/announce.php' || $script === '/announce.php');

// Имя куки завязываем на IP (как было)
$cookieName = md5('837sgsa' . ($_SERVER['REMOTE_ADDR'] ?? '0'));

// Если нет куки, не бот и не announce — включаем простую защиту с автопостом
if (empty($_COOKIE[$cookieName]) && !$isAnnounce && !$isBot) {

    // Если пришли со скрытым полем — ставим куку и редиректим обратно
    if (isset($_POST[$cookieName])) {
        // Кука на час (можно увеличить/уменьшить)
        $cookieOpts = [
            'expires'  => time() + 3600,
            'path'     => '/',            // на весь сайт
            'secure'   => $https,         // только по HTTPS, если доступно
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie($cookieName, 'yes', $cookieOpts);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . DEFAULTBASEURL . $uri);
        exit;
    }

    // Первая страница — отдаём автопост-форму без хардкодов домена
    $action = DEFAULTBASEURL . ((string)($_SERVER['REQUEST_URI'] ?? '/'));
    $hn = htmlspecialchars($cookieName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Checking…</title></head><body>',
         '<form id="f" action="', htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
         '" method="post"><input type="hidden" name="', $hn, '" value="a">',
         '<noscript><p>Нажмите «Продолжить», чтобы подтвердить запрос.</p><input type="submit" value="Continue"></noscript>',
         '</form><script>document.getElementById("f")&&document.getElementById("f").submit();</script>',
         '</body></html>';
    exit;
}
### END-ANTIDDOS ###


// Определяем, что это код трекера
if (!defined('IN_TRACKER')) {
    define('IN_TRACKER', true);
}

// Настройка окружения PHP
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '0');
ini_set('ignore_repeated_errors', '1');
ignore_user_abort(true);
set_time_limit(0);

// Старт сессии (если ещё не запущена)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Корневой путь
define('ROOT_PATH', dirname(__DIR__) . '/');

// Таймер
function timer(): float {
    $m = microtime(true);
    return is_float($m) ? $m : (float)microtime(true);
}

// (ЛЕГАСИ) простая сигнатура детекта SQL-инъекций — оставлена как есть,
// но лучше пользоваться подготовленными выражениями
function detect_sqlinjection(string $query): void
{
    // Если детектор явно выключен где-то выше — просто выходим
    if (defined('SQLI_DETECT') && SQLI_DETECT === false) {
        return;
    }

    // Нормализуем пробелы, НО не трогаем кавычки — иначе склеиваем токены
    $q = preg_replace('/\s+/', ' ', $query);

    // Быстрый выход: простые SELECT без опасных слов — пропускаем
    if (preg_match('/^\s*SELECT\b/i', $q) &&
        !preg_match('/\b(UNION|OUTFILE|DUMPFILE|LOAD_FILE|BENCHMARK)\b/i', $q)) {
        return;
    }

    // Чёткие границы слов и безопасные проверки комментариев
    $bad = [
        '/\bUNION\b\s+\bSELECT\b/i',
        '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i',
        '/\bLOAD_FILE\s*\(/i',
        '/\bBENCHMARK\s*\(/i',
        '/<\?php/i',
        '/\bUSER\s*\(/i',
        '/\bDATABASE\s*\(/i',
        // комментарии: "--" и "#" только как отдельные маркеры (начало строки или пробел перед)
        '/(^|[\s])--(?=[\s]|$)/m',
        '/(^|[\s])#(?=[\s]|$)/m',
        // блочные комментарии
        '/\/\*.*?\*\//s',
    ];

    foreach ($bad as $re) {
        if (preg_match($re, $q)) {
            exit('SQL Injection DETECTED! HA-HA!');
        }
    }
}

// Засекаем старт
$tstart = timer();

// Подключение ядра
$rootpath = $rootpath ?? ROOT_PATH;
require_once $rootpath . 'include/core.php';


// Прогресс-бар по проценту
function get_percent_completed_image(int $p): string {
    $p = max(0, min(100, $p));
    $maxpx = 100;
    $filled = (int)round($p * ($maxpx / 100));
    $rest   = $maxpx - $filled;

    $barGreen = '<img src="/pic/progbar-green.gif" height="9" width="' . $filled . '" alt="">';
    $barRest  = '<img src="/pic/progbar-rest.gif"  height="9" width="' . $rest   . '" alt="">';

    $progress = ($p <= 0)
        ? '<img src="/pic/progbar-rest.gif" height="9" width="' . $maxpx . '" alt="">'
        : (($p >= 100)
            ? '<img src="/pic/progbar-green.gif" height="9" width="' . $maxpx . '" alt="">'
            : $barGreen . $barRest);

    return '<img src="/pic/bar_left.gif" alt="">' . $progress . '<img src="/pic/bar_right.gif" alt="">';
}

// Индикатор доната
function get_percent_donated_image(int $d): string {
    $d = max(0, $d);
    $img = 'progress-'
         . ($d >= 100 ? '5'
         : ($d >= 81 ? '4'
         : ($d >= 61 ? '3'
         : ($d >= 41 ? '2'
         : ($d >= 11 ? '1' : '0')))));
    return '<img src="/pic/' . $img . '.gif" alt="">';
}
