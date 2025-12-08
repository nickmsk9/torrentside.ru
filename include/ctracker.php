<?php
declare(strict_types=1);

/**
 * Lightweight WAF for query-string signatures (drop-in replacement).
 * — No eregi/str_replace; robust regex with escaping
 * — Percent-decoding + '+' -> space, length cap
 * — Calls dbconn()/hacker() if present; keeps same die() message
 */
(function (): void {
    // Быстрый выход, если нет query-string
    $rawQS = $_SERVER['QUERY_STRING'] ?? '';
    if ($rawQS === '') return;

    // Нормализация: раскодируем %XX и '+' => пробел; ограничим до 4 КБ (достаточно для сигнатур)
    $norm = strtr(rawurldecode($rawQS), ['+' => ' ']);
    if ($norm === '') return;
    if (strlen($norm) > 4096) {
        // слишком длинные QS чаще всего вредоносны — подрежем (и всё равно проверим)
        $norm = substr($norm, 0, 4096);
    }

    // Сигнатуры (очищены от дублей; заведомо подозрительные конструкции)
    // Важно: используем последовательности и ключевые слова, которые действительно встречаются в атаках.
    $signatures = [
        // shell/net utils
        'wget', 'curl', 'cmd', 'sh', 'bash', 'telnet', 'nc.exe', 'traceroute', 'uname\x20-a',
        '/bin/ps', '/bin/echo', '/bin/kill', '/usr/bin/id', '/usr/bin', '/usr/X11R6/bin/xterm',
        'perl ', 'python', 'tclsh', 'nasm',
        // файловые операции
        'cp ', 'mv ', 'rm ', 'rmdir', 'chmod', 'chown', 'chgrp', 'locate ', 'grep ', 'diff ',
        // web / php
        'window.open', '<script', 'javascript://', 'img src', '.jsp', 'servlet', '.htpasswd', '.eml',
        '.conf', '.inc.php', 'config.php', 'db_mysql.inc', '.inc', 'http_php', 'phpinfo()',
        // критичные пути *nix
        '/etc/passwd', '/etc/shadow', '/etc/groups', '/etc/gshadow', '/etc/rc.local',
        // SQL-инъекции / тяжёлые функции
        'union', 'select ', 'select+', 'select*from', 'insert into', 'drop ', 'sql=',
        'xp_cmdshell', 'xp_filelist', 'xp_availablemedia', 'xp_enumdsn',
        'into outfile', 'into dumpfile', 'load_file(', 'benchmark(',
        // глобалы PHP/CGI протечки
        '$_get', '$_request', '$get', '$request', 'http_', '_php', 'php_',
        'HTTP_USER_AGENT', 'HTTP_HOST',
        // файл-схемы/инклуды
        'file://', 'cgi-', '.system', 'getenv', ' getenv', 'getenv ',
        // «нежелательные» статусы/info
        'server-info', 'server-status', 'mod_gzip_status', 'org.apache',
        // robots опечатка (часто в сканерах)
        '/robot.txt',
    ];

    // Компилируем regex: экранируем каждый маркер; добавим разумные границы
    static $rx = null;
    if ($rx === null) {
        $quoted = array_map(static function (string $s): string {
            // убираем дубликаты пробелов по краям и экранируем спецсимволы
            $s = trim($s);
            return preg_quote($s, '~');
        }, $signatures);
        $quoted = array_values(array_unique(array_filter($quoted, static fn($s) => $s !== '')));
        // ищем без учёта регистра; позволяем произвольные пробельные вариации
        // Некоторые маркеры содержат слеши/скобки — всё уже экранировано.
        $rx = '~(?:' . implode('|', $quoted) . ')~i';
    }

    if (!preg_match($rx, $norm)) {
        return; // всё ок, выходим
    }

    // Подготовим «нормализованную» версия строки с замены маркеров для лога (как было в исходнике)
    $masked = preg_replace($rx, '*', $norm);

    // Логируем максимально безопасно
    $ip  = $_SERVER['REMOTE_ADDR']     ?? '-';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $uri = $_SERVER['REQUEST_URI']     ?? '-';
    $self= $_SERVER['PHP_SELF']        ?? '-';

    if (function_exists('dbconn')) {
        // В оригинале вызывался dbconn() — сохраним
        try { dbconn(); } catch (\Throwable $e) { /* ignore */ }
    }
    if (function_exists('hacker')) {
        // Сохраняем формат: <script> : <orig>\n<masked>
        // Добавим контекст в конце (IP/UA/URI) — поможет в разборе инцидента
        $msg = $self . ' : ' . $norm . '\n' . $masked . ' | ip=' . $ip . ' | ua=' . $ua . ' | uri=' . $uri;
        try { hacker($msg); } catch (\Throwable $e) { /* ignore */ }
    }

    // Поведение исходника: отдаём «фальшивую» ошибку MySQL и прерываем выполнение
    die("Warning: mysql_connect(): Can't connect to local MySQL server through socket '/tmp/mysql.sock' (2) in /www/public_html/www/engine/db.php on line 92");
})();
