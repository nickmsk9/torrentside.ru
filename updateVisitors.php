<?php
declare(strict_types=1);

/**
 * AJAX endpoint: возвращает HTML-фрагмент списка посетителей для конкретного URL.
 * Ожидает POST: url, timeout|timeout_min (минуты), csrf_token
 */

require_once __DIR__ . '/include/bittorrent.php';
dbconn(false);
loggedinorreturn();

// ---------- Заголовки ----------
$charset = $tracker_lang['language_charset'] ?? 'UTF-8';
header('Content-Type: application/json; charset=' . $charset);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Сносим мусор из буферов, чтобы JSON не поломать
if (function_exists('ob_get_level') && ob_get_level() > 0) {
    @ob_clean();
}

// Унифицированная функция ответа
$send = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

// ---------- Метод ----------
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST') {
    $send(405, ['ok' => false, 'error' => 'Method Not Allowed']);
}

// ---------- CSRF ----------
$csrfForm = (string)($_POST['csrf_token'] ?? '');
$csrfHead = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfSess = (string)($_SESSION['csrf_token'] ?? '');

if ($csrfSess === '' || ($csrfForm === '' && $csrfHead === '')) {
    $send(403, ['ok' => false, 'error' => 'CSRF_missing']);
}

$passed = hash_equals($csrfSess, $csrfForm !== '' ? $csrfForm : $csrfHead);
if (!$passed) {
    header('X-Debug-Csrf: mismatch', true);
    $send(403, ['ok' => false, 'error' => 'CSRF']);
}

// ---------- Входные данные ----------
$urlRaw   = (string)($_POST['url'] ?? '');
$timeoutM = (int)($_POST['timeout'] ?? ($_POST['timeout_min'] ?? 15));

// Снимаем нул-байты и ограничиваем длину
$urlRaw = substr(str_replace("\0", '', $urlRaw), 0, 512);

// Нормализовать URL: если абсолютный, берём только path[?query]
$norm = $urlRaw;
if ($urlRaw !== '' && preg_match('~^[a-z][a-z0-9+\-.]*://~i', $urlRaw)) {
    $p = @parse_url($urlRaw);
    if (is_array($p)) {
        $path  = $p['path']  ?? '/';
        $query = isset($p['query']) ? ('?' . $p['query']) : '';
        $norm  = $path . $query;
    }
}

// Удаляем управляющие символы
$norm = preg_replace('~[\x00-\x1F]~', '', (string)$norm);
if ($norm === '') {
    $send(400, ['ok' => false, 'error' => 'bad_input']);
}

// Таймаут: 1..60 мин
$timeoutM = max(1, min(60, $timeoutM));

// ---------- Основная логика ----------
try {
    // notAdd = true — не записываем сам факт запроса в историю
    $ok = visitorsHistorie('', $timeoutM, true, $norm);
} catch (\Throwable $e) {
    $send(500, ['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}

if (!$ok) {
    $send(500, ['ok' => false, 'error' => 'server_error']);
}

// Ожидаем, что visitorsHistorie заполняет $GLOBALS['VISITORS']
$VISITORS = $GLOBALS['VISITORS'] ?? [];
if (!is_array($VISITORS) || !$VISITORS) {
    $VISITORS = ['<span class="small" style="opacity:.7">никого</span>'];
}

// Генерируем фрагмент (без внешних таблиц/рамок)
$html = (string)visitorsList('[VISITORS]', $VISITORS);

// Успех
$send(200, [
    'ok'    => true,
    'html'  => $html,
    'count' => is_countable($VISITORS) ? count($VISITORS) : 0,
]);
