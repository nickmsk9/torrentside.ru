<?php
if (!defined('ADMIN_FILE')) die('Illegal File Access');

function ts_ai_helper_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ts_ai_helper_styles_once(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    echo <<<CSS
<style>
.ai-admin-wrap{display:flex;flex-direction:column;gap:14px}
.ai-admin-grid{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:14px}
@media (max-width:1100px){.ai-admin-grid{grid-template-columns:1fr}}
.ai-admin-card{
  border:1px solid rgba(125,141,160,.22);
  border-radius:14px;
  background:rgba(255,255,255,.12);
  padding:14px;
}
.ai-admin-title{font-size:14px;font-weight:700;margin:0 0 10px}
.ai-admin-muted{color:#6b7d92;font-size:12px;line-height:1.45}
.ai-admin-stack{display:flex;flex-direction:column;gap:10px}
.ai-admin-actions{display:flex;flex-wrap:wrap;gap:8px}
.ai-admin-btn{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:36px;padding:8px 12px;border-radius:12px;
  border:1px solid rgba(125,141,160,.22);
  background:rgba(255,255,255,.16);color:inherit;text-decoration:none;
  font-weight:700;cursor:pointer;
}
.ai-admin-btn.primary{border-color:rgba(67,113,208,.55);background:rgba(67,113,208,.10)}
.ai-admin-field,.ai-admin-textarea,.ai-admin-select{
  width:100%;box-sizing:border-box;border-radius:12px;
  border:1px solid rgba(125,141,160,.24);
  background:rgba(255,255,255,.88);color:#111827;
  padding:10px 12px;
}
.ai-admin-textarea{min-height:140px;resize:vertical}
.ai-admin-kv{display:grid;grid-template-columns:170px minmax(0,1fr);gap:8px 12px}
@media (max-width:760px){.ai-admin-kv{grid-template-columns:1fr}}
.ai-admin-list{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px}
.ai-admin-pre{
  margin:0;padding:12px;border-radius:12px;border:1px solid rgba(125,141,160,.22);
  background:rgba(16,24,40,.04);white-space:pre-wrap;word-break:break-word;
  font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;
}
.ai-admin-table{width:100%;border-collapse:collapse}
.ai-admin-table th,.ai-admin-table td{padding:8px 10px;border-top:1px solid rgba(125,141,160,.18);text-align:left;vertical-align:top}
.ai-admin-table th{font-size:12px;color:#6b7d92;font-weight:700}
.ai-admin-pill{
  display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;
  border:1px solid rgba(125,141,160,.22);background:rgba(255,255,255,.2);font-size:12px;font-weight:700;
}
</style>
CSS;
}

function ts_ai_helper_collect_log_files(int $maxFiles = 5): array
{
    $files = [];
    foreach (glob(__DIR__ . '/../../logs/*.log') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $files[] = [
            'path' => $path,
            'name' => basename($path),
            'mtime' => (int)@filemtime($path),
            'size' => (int)@filesize($path),
        ];
    }

    usort($files, static fn(array $a, array $b): int => ($b['mtime'] <=> $a['mtime']));
    return array_slice($files, 0, $maxFiles);
}

function ts_ai_helper_tail_file(string $path, int $maxLines = 120, int $maxBytes = 65536): string
{
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }

    $size = (int)filesize($path);
    $offset = max(0, $size - $maxBytes);
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return '';
    }
    if ($offset > 0) {
        fseek($fh, $offset);
    }
    $data = stream_get_contents($fh);
    fclose($fh);

    if (!is_string($data) || $data === '') {
        return '';
    }

    $lines = preg_split("/\r\n|\n|\r/", $data) ?: [];
    $lines = array_slice($lines, -$maxLines);
    return trim(implode("\n", $lines));
}

function ts_ai_helper_extract_suspicious_lines(string $blob, int $limit = 25): array
{
    $out = [];
    $patterns = [
        'fatal', 'error', 'warning', 'exception', 'trace', 'denied',
        'forbidden', 'sql', 'unknown passkey', 'hack', 'attack',
        'timeout', 'deadlock', 'deprecated', 'undefined', 'notice'
    ];
    foreach (preg_split("/\r\n|\n|\r/", $blob) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        foreach ($patterns as $needle) {
            if (stripos($line, $needle) !== false) {
                $out[] = $line;
                break;
            }
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function ts_ai_helper_recent_error_context(int $maxFiles = 4, int $linesPerFile = 120): array
{
    $chunks = [];
    $suspicious = [];
    foreach (ts_ai_helper_collect_log_files($maxFiles) as $file) {
        $tail = ts_ai_helper_tail_file($file['path'], $linesPerFile);
        if ($tail === '') {
            continue;
        }
        $chunks[] = "### {$file['name']}\n{$tail}";
        foreach (ts_ai_helper_extract_suspicious_lines($tail, 12) as $line) {
            $suspicious[] = '[' . $file['name'] . '] ' . $line;
        }
    }

    $hackers = [];
    $res = sql_query("SELECT added, ip, system, event FROM hackers ORDER BY id DESC LIMIT 15");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $hackers[] = sprintf(
            '%s | %s | %s | %s',
            (string)$row['added'],
            (string)$row['ip'],
            (string)$row['system'],
            trim((string)$row['event'])
        );
    }

    if ($hackers) {
        $chunks[] = "### hackers\n" . implode("\n", $hackers);
    }

    return [
        'raw' => trim(implode("\n\n", $chunks)),
        'suspicious' => array_slice(array_values(array_unique($suspicious)), 0, 20),
        'hackers' => $hackers,
    ];
}

function ts_ai_helper_chat_health(): array
{
    $stats = [
        'messages_1h' => 0,
        'messages_24h' => 0,
        'unique_users_24h' => 0,
        'last_message_at' => null,
        'last_message_user' => null,
        'last_message_text' => null,
        'suspicions' => [],
    ];

    $res = sql_query("
        SELECT
            SUM(date_dt >= (NOW() - INTERVAL 1 HOUR)) AS messages_1h,
            SUM(date_dt >= (NOW() - INTERVAL 24 HOUR)) AS messages_24h,
            COUNT(DISTINCT CASE WHEN date_dt >= (NOW() - INTERVAL 24 HOUR) THEN userid END) AS unique_users_24h
        FROM shoutbox
    ");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $stats['messages_1h'] = (int)($row['messages_1h'] ?? 0);
        $stats['messages_24h'] = (int)($row['messages_24h'] ?? 0);
        $stats['unique_users_24h'] = (int)($row['unique_users_24h'] ?? 0);
    }

    $lastRes = sql_query("SELECT username, orig_text, date_dt FROM shoutbox ORDER BY id DESC LIMIT 1");
    if ($lastRes && ($row = mysqli_fetch_assoc($lastRes))) {
        $stats['last_message_at'] = (string)$row['date_dt'];
        $stats['last_message_user'] = (string)$row['username'];
        $stats['last_message_text'] = mb_substr(trim((string)$row['orig_text']), 0, 180);
    }

    $logCtx = ts_ai_helper_recent_error_context(4, 80);
    foreach ($logCtx['suspicious'] as $line) {
        if (stripos($line, 'chat') !== false || stripos($line, 'shout') !== false) {
            $stats['suspicions'][] = $line;
        }
    }
    if ($stats['messages_24h'] === 0) {
        $stats['suspicions'][] = 'За последние 24 часа в shoutbox нет сообщений.';
    }
    if ($stats['messages_1h'] === 0 && $stats['messages_24h'] > 0) {
        $stats['suspicions'][] = 'В последний час чат неактивен, но за сутки сообщения были.';
    }

    return $stats;
}

function ts_ai_helper_anomalous_users(): array
{
    $items = [];

    $q1 = sql_query("
        SELECT s.uid, s.username, COUNT(*) AS session_count, COUNT(DISTINCT s.ip) AS ip_count,
               MIN(s.time_dt) AS first_seen, MAX(s.time_dt) AS last_seen
        FROM sessions AS s
        WHERE s.uid > 0
        GROUP BY s.uid, s.username
        HAVING COUNT(*) >= 3 OR COUNT(DISTINCT s.ip) >= 2
        ORDER BY ip_count DESC, session_count DESC, last_seen DESC
        LIMIT 20
    ");
    while ($q1 && ($row = mysqli_fetch_assoc($q1))) {
        $items[] = [
            'kind' => 'sessions',
            'uid' => (int)$row['uid'],
            'username' => (string)$row['username'],
            'score' => (int)$row['ip_count'] * 10 + (int)$row['session_count'],
            'summary' => 'Сессии: ' . (int)$row['session_count'] . ', IP: ' . (int)$row['ip_count'],
            'details' => 'Активность с ' . (string)$row['first_seen'] . ' до ' . (string)$row['last_seen'],
        ];
    }

    $q2 = sql_query("
        SELECT uid, uname, COUNT(*) AS hits, COUNT(DISTINCT url_hash) AS unique_urls,
               MIN(time) AS first_seen, MAX(time) AS last_seen
        FROM visitor_history
        WHERE uid > 0 AND time >= (NOW() - INTERVAL 24 HOUR)
        GROUP BY uid, uname
        HAVING COUNT(*) >= 25 OR COUNT(DISTINCT url_hash) >= 15
        ORDER BY hits DESC, unique_urls DESC
        LIMIT 20
    ");
    while ($q2 && ($row = mysqli_fetch_assoc($q2))) {
        $items[] = [
            'kind' => 'history',
            'uid' => (int)$row['uid'],
            'username' => (string)$row['uname'],
            'score' => (int)$row['hits'] + (int)$row['unique_urls'],
            'summary' => 'Переходы за 24ч: ' . (int)$row['hits'] . ', уникальных URL: ' . (int)$row['unique_urls'],
            'details' => 'С ' . (string)$row['first_seen'] . ' по ' . (string)$row['last_seen'],
        ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']));
    return array_slice($items, 0, 20);
}

function ts_ai_helper_local_model_available(): bool
{
    return function_exists('curl_init');
}

function ts_ai_helper_call_local_model(string $prompt, string $context): array
{
    $host = trim((string)(getenv('TS_ADMIN_AI_URL') ?: 'http://127.0.0.1:11434/api/generate'));
    $model = trim((string)(getenv('TS_ADMIN_AI_MODEL') ?: 'qwen2.5:7b-instruct'));

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'text' => 'cURL недоступен на сервере.'];
    }

    $system = "Ты локальный помощник администратора торрент-трекера. Отвечай по-русски кратко и по делу. Не выдумывай факты. Если данных мало, прямо так и скажи. Формат ответа: 1) краткий вывод 2) возможные причины 3) что проверить дальше.";
    $body = json_encode([
        'model' => $model,
        'stream' => false,
        'prompt' => $system . "\n\nВопрос администратора:\n" . $prompt . "\n\nКонтекст:\n" . $context,
        'options' => [
            'temperature' => 0.2,
            'num_predict' => 500,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($host);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($resp) || $resp === '') {
        return ['ok' => false, 'text' => $err !== '' ? $err : 'Пустой ответ от локальной модели.'];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['ok' => false, 'text' => 'Локальная модель вернула некорректный JSON.'];
    }

    if ($code >= 400) {
        return ['ok' => false, 'text' => (string)($json['error'] ?? ('HTTP ' . $code))];
    }

    $text = trim((string)($json['response'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'text' => 'Локальная модель не вернула текст ответа.'];
    }

    return ['ok' => true, 'text' => $text, 'model' => $model];
}

function ts_ai_helper_render_result(string $title, string $summary, array $details = [], ?string $aiText = null, ?string $rawContext = null): void
{
    echo '<div class="ai-admin-card">';
    echo '<h3 class="ai-admin-title">' . ts_ai_helper_h($title) . '</h3>';
    echo '<div class="ai-admin-muted" style="margin-bottom:10px;">' . $summary . '</div>';

    if ($details) {
        echo '<table class="ai-admin-table">';
        foreach ($details as $label => $value) {
            echo '<tr><th>' . ts_ai_helper_h((string)$label) . '</th><td>' . $value . '</td></tr>';
        }
        echo '</table>';
    }

    if ($aiText !== null) {
        echo '<div style="margin-top:12px">';
        echo '<div class="ai-admin-title" style="font-size:13px;margin-bottom:6px;">Краткое AI-объяснение</div>';
        echo '<div class="ai-admin-pre">' . ts_ai_helper_h($aiText) . '</div>';
        echo '</div>';
    }

    if ($rawContext !== null && $rawContext !== '') {
        echo '<div style="margin-top:12px">';
        echo '<div class="ai-admin-title" style="font-size:13px;margin-bottom:6px;">Контекст</div>';
        echo '<div class="ai-admin-pre">' . ts_ai_helper_h($rawContext) . '</div>';
        echo '</div>';
    }

    echo '</div>';
}

function AiHelper(): void
{
    $action = trim((string)($_POST['ai_action'] ?? $_GET['ai_action'] ?? ''));
    $question = trim((string)($_POST['question'] ?? ''));
    $manualLogs = trim((string)($_POST['manual_logs'] ?? ''));
    $useModel = isset($_POST['use_model']) && $_POST['use_model'] === 'yes';

    ts_ai_helper_styles_once();

    echo '<div class="ai-admin-wrap">';
    echo '<div class="ai-admin-grid">';

    echo '<div class="ai-admin-card">';
    echo '<h2 class="ai-admin-title">AI-помощник администратора</h2>';
    echo '<div class="ai-admin-muted" style="margin-bottom:12px;">Быстрый разбор логов, чата и подозрительной активности. Если локальная модель доступна, инструмент добавит короткое объяснение поверх сырых данных.</div>';
    echo '<form method="post" action="admincp.php?op=ai_helper" class="ai-admin-stack">';
    echo '<input type="hidden" name="op" value="ai_helper">';
    echo '<label class="ai-admin-muted">Готовый сценарий</label>';
    echo '<select name="ai_action" class="ai-admin-select">';
    $options = [
        '' => 'Выберите сценарий',
        'chat' => 'Почему не работает чат',
        'logs' => 'Найди подозрительные ошибки в логах',
        'anomaly' => 'Покажи пользователей с аномальной активностью',
        'ask' => 'Задать свой вопрос по логам',
    ];
    foreach ($options as $value => $label) {
        $sel = $action === $value ? ' selected' : '';
        echo '<option value="' . ts_ai_helper_h($value) . '"' . $sel . '>' . ts_ai_helper_h($label) . '</option>';
    }
    echo '</select>';
    echo '<label class="ai-admin-muted">Вопрос администратора</label>';
    echo '<input class="ai-admin-field" type="text" name="question" value="' . ts_ai_helper_h($question) . '" placeholder="Например: почему не работает чат после обновления?">';
    echo '<label class="ai-admin-muted">Дополнительные логи или контекст</label>';
    echo '<textarea class="ai-admin-textarea" name="manual_logs" placeholder="Можно вставить сюда кусок лога, stack trace или описание проблемы.">' . ts_ai_helper_h($manualLogs) . '</textarea>';
    echo '<label class="ai-admin-muted"><input type="checkbox" name="use_model" value="yes"' . ($useModel ? ' checked' : '') . '> Использовать локальную модель для краткого объяснения</label>';
    echo '<div class="ai-admin-actions">';
    echo '<button class="ai-admin-btn primary" type="submit">Запустить анализ</button>';
    echo '<a class="ai-admin-btn" href="admincp.php?op=ai_helper&ai_action=chat">Проверить чат</a>';
    echo '<a class="ai-admin-btn" href="admincp.php?op=ai_helper&ai_action=logs">Проверить логи</a>';
    echo '<a class="ai-admin-btn" href="admincp.php?op=ai_helper&ai_action=anomaly">Аномальная активность</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';

    echo '<div class="ai-admin-card">';
    echo '<h3 class="ai-admin-title">Что умеет</h3>';
    echo '<ul class="ai-admin-list">';
    echo '<li>собирает последние ошибки из файлов в <code>logs/</code> и таблицы <code>hackers</code>;</li>';
    echo '<li>показывает состояние чата по активности и последним сообщениям;</li>';
    echo '<li>ищет пользователей с нетипичной активностью по <code>sessions</code> и <code>visitor_history</code>;</li>';
    echo '<li>может отдать контекст в локальную модель через совместимый HTTP API.</li>';
    echo '</ul>';
    echo '<div style="margin-top:12px" class="ai-admin-stack">';
    echo '<div><span class="ai-admin-pill">Локальная модель: ' . (ts_ai_helper_local_model_available() ? 'доступен HTTP-клиент' : 'cURL недоступен') . '</span></div>';
    echo '<div class="ai-admin-muted">По умолчанию ожидается совместимый endpoint вроде <code>http://127.0.0.1:11434/api/generate</code> и модель из <code>TS_ADMIN_AI_MODEL</code>.</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    if ($action === '') {
        echo '</div>';
        return;
    }

    $context = '';
    $aiAnswer = null;

    if ($action === 'chat') {
        $stats = ts_ai_helper_chat_health();
        $context = "Диагностика чата\n"
            . "Сообщений за 1ч: {$stats['messages_1h']}\n"
            . "Сообщений за 24ч: {$stats['messages_24h']}\n"
            . "Уникальных пользователей за 24ч: {$stats['unique_users_24h']}\n"
            . "Последнее сообщение: " . ($stats['last_message_at'] ?: 'нет') . "\n"
            . "Автор: " . ($stats['last_message_user'] ?: 'нет') . "\n"
            . "Текст: " . ($stats['last_message_text'] ?: 'нет') . "\n"
            . ($stats['suspicions'] ? "Подозрения:\n- " . implode("\n- ", $stats['suspicions']) : '');
        if ($manualLogs !== '') {
            $context .= "\n\nДоп. контекст:\n" . $manualLogs;
        }
        if ($useModel) {
            $ai = ts_ai_helper_call_local_model($question !== '' ? $question : 'Почему может не работать чат?', $context);
            $aiAnswer = $ai['ok'] ? $ai['text'] : 'Локальная модель недоступна: ' . $ai['text'];
        }
        ts_ai_helper_render_result(
            'Диагностика чата',
            'Быстрый срез по активности чата и возможным сигналам проблем.',
            [
                'Сообщений за 1 час' => (string)$stats['messages_1h'],
                'Сообщений за 24 часа' => (string)$stats['messages_24h'],
                'Уникальных пользователей за 24 часа' => (string)$stats['unique_users_24h'],
                'Последнее сообщение' => ts_ai_helper_h(($stats['last_message_at'] ?: 'нет') . ($stats['last_message_user'] ? ' • ' . $stats['last_message_user'] : '')),
                'Подозрительные сигналы' => $stats['suspicions']
                    ? '<ul class="ai-admin-list"><li>' . implode('</li><li>', array_map('ts_ai_helper_h', $stats['suspicions'])) . '</li></ul>'
                    : 'Не найдено явных проблем по доступным данным.',
            ],
            $aiAnswer,
            $manualLogs !== '' ? $manualLogs : null
        );
    } elseif ($action === 'logs') {
        $ctx = ts_ai_helper_recent_error_context();
        $context = $ctx['raw'];
        if ($manualLogs !== '') {
            $context .= "\n\n### manual\n" . $manualLogs;
        }
        if ($useModel) {
            $ai = ts_ai_helper_call_local_model($question !== '' ? $question : 'Найди подозрительные ошибки в логах и кратко объясни.', $context);
            $aiAnswer = $ai['ok'] ? $ai['text'] : 'Локальная модель недоступна: ' . $ai['text'];
        }
        ts_ai_helper_render_result(
            'Подозрительные ошибки в логах',
            'Выборка по последним логам и таблице hackers.',
            [
                'Подозрительные строки' => $ctx['suspicious']
                    ? '<ul class="ai-admin-list"><li>' . implode('</li><li>', array_map('ts_ai_helper_h', $ctx['suspicious'])) . '</li></ul>'
                    : 'Подозрительные строки не найдены.',
                'События hackers' => $ctx['hackers']
                    ? '<ul class="ai-admin-list"><li>' . implode('</li><li>', array_map('ts_ai_helper_h', array_slice($ctx['hackers'], 0, 12))) . '</li></ul>'
                    : 'Записей нет.',
            ],
            $aiAnswer,
            $context
        );
    } elseif ($action === 'anomaly') {
        $items = ts_ai_helper_anomalous_users();
        $contextLines = [];
        foreach ($items as $item) {
            $contextLines[] = $item['username'] . ' #' . $item['uid'] . ' | ' . $item['summary'] . ' | ' . $item['details'];
        }
        $context = implode("\n", $contextLines);
        if ($manualLogs !== '') {
            $context .= "\n\nДоп. контекст:\n" . $manualLogs;
        }
        if ($useModel) {
            $ai = ts_ai_helper_call_local_model($question !== '' ? $question : 'Покажи пользователей с аномальной активностью и коротко объясни, что настораживает.', $context);
            $aiAnswer = $ai['ok'] ? $ai['text'] : 'Локальная модель недоступна: ' . $ai['text'];
        }

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td><a href="userdetails.php?id=' . (int)$item['uid'] . '">' . ts_ai_helper_h($item['username']) . '</a></td>'
                . '<td>' . ts_ai_helper_h($item['kind']) . '</td>'
                . '<td>' . ts_ai_helper_h($item['summary']) . '</td>'
                . '<td>' . ts_ai_helper_h($item['details']) . '</td>'
                . '</tr>';
        }
        ts_ai_helper_render_result(
            'Пользователи с аномальной активностью',
            'Сводка по сессиям и истории посещений за последние периоды.',
            [
                'Найдено записей' => (string)count($items),
                'Список' => $rows !== ''
                    ? '<table class="ai-admin-table"><tr><th>Пользователь</th><th>Источник</th><th>Что насторожило</th><th>Детали</th></tr>' . $rows . '</table>'
                    : 'Подозрительные пользователи не найдены.',
            ],
            $aiAnswer,
            $context !== '' ? $context : null
        );
    } elseif ($action === 'ask') {
        $ctx = ts_ai_helper_recent_error_context();
        $context = trim($ctx['raw'] . ($manualLogs !== '' ? "\n\n### manual\n" . $manualLogs : ''));
        if ($question === '') {
            ts_ai_helper_render_result(
                'Свой вопрос',
                'Для произвольного анализа нужно ввести вопрос администратора.',
                [],
                null,
                $context
            );
        } else {
            if ($useModel) {
                $ai = ts_ai_helper_call_local_model($question, $context);
                $aiAnswer = $ai['ok'] ? $ai['text'] : 'Локальная модель недоступна: ' . $ai['text'];
            }
            ts_ai_helper_render_result(
                'Ответ на вопрос администратора',
                'Результат по введённому вопросу и доступному контексту.',
                ['Вопрос' => ts_ai_helper_h($question)],
                $aiAnswer,
                $context
            );
        }
    }

    echo '</div>';
}

switch ($op) {
    case 'ai_helper':
    case 'AiHelper':
        AiHelper();
        break;
}
?>
