<?php
// poll.ajax.php — AJAX API для блока опроса
// Зависимости TBDev
require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/functions.php';

header('Content-Type: application/json; charset=UTF-8');

// Без кеширующих прокси
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

global $CURUSER, $mysqli;
if (!isset($CURUSER['id'])) {
    echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED']);
    exit;
}

// CSRF token (lazy)
if (empty($_SESSION['poll_csrf'])) {
    $_SESSION['poll_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['poll_csrf'];

// Memcached (локальный)
$mc = new Memcached();
$mc->addServer('localhost', 11211);

// -------- helpers --------
function fetch_active_poll(mysqli $db): ?array {
    // Берём самый свежий опрос
    $sql = "SELECT * FROM polls ORDER BY added DESC, id DESC LIMIT 1";
    $res = $db->query($sql);
    if (!$res) return null;
    $row = $res->fetch_assoc();
    return $row ?: null;
}

/** Возвращает массив вариантов вида:
 * [ ['i'=>0,'text'=>'вариант'], ... ] — до первой пустой optionN
 */
function poll_options(array $poll): array {
    $opts = [];
    for ($i = 0; $i <= 19; $i++) {
        $k = "option{$i}";
        if (!isset($poll[$k]) || $poll[$k] === '') break;
        $opts[] = ['i' => $i, 'text' => $poll[$k]];
    }
    return $opts;
}

function get_user_vote(mysqli $db, int $pollId, int $userId): ?int {
    $stmt = $db->prepare("SELECT selection FROM pollanswers WHERE pollid = ? AND userid = ?");
    $stmt->bind_param('ii', $pollId, $userId);
    $stmt->execute();
    $stmt->bind_result($sel);
    if ($stmt->fetch()) {
        $stmt->close();
        return (int)$sel;
    }
    $stmt->close();
    return null;
}

/** Считает итоги: ['total'=>N, 'by'=> [optIndex=>count]] */
function tally_results(mysqli $db, int $pollId, int $optCount, Memcached $mc): array {
    $ckey = "poll_tally_{$pollId}";
    $cached = $mc->get($ckey);
    if ($cached !== false) return $cached;

    $by = array_fill(0, $optCount, 0);
    $total = 0;
    $stmt = $db->prepare("SELECT selection, COUNT(*) c FROM pollanswers WHERE pollid = ? GROUP BY selection");
    $stmt->bind_param('i', $pollId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sel = (int)$r['selection'];
        $cnt = (int)$r['c'];
        if ($sel >= 0 && $sel < $optCount) {
            $by[$sel] = $cnt;
            $total += $cnt;
        }
    }
    $stmt->close();

    $data = ['total' => $total, 'by' => $by];
    $mc->set($ckey, $data, 60); // итоги кэшируем на 60с
    return $data;
}

/** Формирует JSON для фронта: состояние опроса */
function build_poll_payload(mysqli $db, array $poll, int $userId, Memcached $mc): array {
    $options = poll_options($poll);
    $optCount = count($options);
    $pollId = (int)$poll['id'];
    $userSel = get_user_vote($db, $pollId, $userId);

    $data = [
        'poll' => [
            'id' => $pollId,
            'question' => $poll['question'],
            'added' => $poll['added'],
            'sort' => $poll['sort'],
            'options' => $options,
        ],
        'user' => [
            'selection' => $userSel, // null если не голосовал
        ],
    ];

    if ($userSel !== null) {
        // Добавим итоги если пользователь уже голосовал
        $tally = tally_results($db, $pollId, $optCount, $mc);
        $data['results'] = $tally;
    }
    return $data;
}

// -------- router --------
$action = $_GET['action'] ?? 'load';

try {
    switch ($action) {
        // Загрузка текущего опроса (состояние + итоги если уже голосовал)
        case 'load': {
            $poll = fetch_active_poll($mysqli);
            if (!$poll) {
                echo json_encode(['ok' => false, 'error' => 'NO_POLL']);
                break;
            }
            $payload = build_poll_payload($mysqli, $poll, (int)$CURUSER['id'], $mc);
            $payload['csrf'] = $csrf;
            echo json_encode(['ok' => true, 'data' => $payload]);
            break;
        }

        // Голосование
        case 'vote': {
            // Безопасность
            if (($_POST['csrf'] ?? '') !== $csrf) {
                echo json_encode(['ok' => false, 'error' => 'BAD_CSRF']);
                break;
            }

            $pollId = (int)($_POST['pollid'] ?? 0);
            $selection = (int)($_POST['selection'] ?? -1);
            if ($pollId <= 0 || $selection < 0) {
                echo json_encode(['ok' => false, 'error' => 'BAD_INPUT']);
                break;
            }

            // Проверим валидность варианта
            $pstmt = $mysqli->prepare("SELECT * FROM polls WHERE id = ?");
            $pstmt->bind_param('i', $pollId);
            $pstmt->execute();
            $pollRes = $pstmt->get_result();
            $poll = $pollRes->fetch_assoc();
            $pstmt->close();

            if (!$poll) { echo json_encode(['ok'=>false,'error'=>'POLL_NOT_FOUND']); break; }

            $options = poll_options($poll);
            $optCount = count($options);
            if ($selection >= $optCount) {
                echo json_encode(['ok'=>false,'error'=>'SELECTION_OUT_OF_RANGE']);
                break;
            }

            // Пишем голос (с правом изменить свой выбор)
            $stmt = $mysqli->prepare("
                INSERT INTO pollanswers (pollid, userid, selection)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE selection = VALUES(selection)
            ");
            $uid = (int)$CURUSER['id'];
            $stmt->bind_param('iii', $pollId, $uid, $selection);
            $stmt->execute();
            $stmt->close();

            // Сбросим кэш итогов
            $mc->delete("poll_tally_{$pollId}");

            // Вернём обновлённые итоги
            $tally = tally_results($mysqli, $pollId, $optCount, $mc);
            echo json_encode(['ok' => true, 'results' => $tally]);
            break;
        }

        // Принудительный просмотр итогов (без голосования)
        case 'results': {
            $poll = fetch_active_poll($mysqli);
            if (!$poll) { echo json_encode(['ok'=>false, 'error'=>'NO_POLL']); break; }
            $options = poll_options($poll);
            $tally = tally_results($mysqli, (int)$poll['id'], count($options), $mc);
            echo json_encode(['ok'=>true, 'results'=>$tally, 'options'=>$options]);
            break;
        }

        default:
            echo json_encode(['ok' => false, 'error' => 'BAD_ACTION']);
    }
} catch (Throwable $e) {
    // Лог + безопасная ошибка
    error_log("[poll.ajax] ".$e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
