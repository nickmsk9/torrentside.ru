<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/upload_ai.php';

dbconn(false);
loggedinorreturn();
parked();

header('Content-Type: application/json; charset=UTF-8');

function upload_assistant_respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    upload_assistant_respond(405, [
        'ok' => false,
        'error' => 'Разрешён только POST-запрос.',
    ]);
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    upload_assistant_respond(403, [
        'ok' => false,
        'error' => 'Проверка CSRF не пройдена.',
    ]);
}

$context = (string)($_POST['context'] ?? 'generic');
$title = trim((string)unesc($_POST['title'] ?? ''));
$altTitle = trim((string)unesc($_POST['alt_title'] ?? ''));
$descr = trim((string)unesc($_POST['descr'] ?? ''));
$mediainfo = trim((string)unesc($_POST['ai_mediainfo'] ?? ''));
$nfo = trim((string)unesc($_POST['ai_nfo'] ?? ''));
$useWikipedia = (string)($_POST['use_wikipedia'] ?? '1') === '1';

$torrentName = '';
$fileEntries = [];
$totalSize = 0;

if (!empty($_FILES['tfile']['tmp_name']) && is_uploaded_file((string)$_FILES['tfile']['tmp_name'])) {
    try {
        $torrentMeta = tracker_upload_ai_parse_torrent_file((string)$_FILES['tfile']['tmp_name'], (int)($GLOBALS['max_torrent_size'] ?? 1048576));
        $torrentName = (string)($torrentMeta['name'] ?? '');
        $fileEntries = (array)($torrentMeta['files'] ?? []);
        $totalSize = (int)($torrentMeta['total_size'] ?? 0);
    } catch (Throwable $e) {
        upload_assistant_respond(422, [
            'ok' => false,
            'error' => 'Не удалось разобрать .torrent: ' . $e->getMessage(),
        ]);
    }
}

if ($title === '' && $torrentName === '') {
    upload_assistant_respond(422, [
        'ok' => false,
        'error' => 'Введите название релиза или выберите .torrent для анализа.',
    ]);
}

try {
    $suggestion = tracker_upload_ai_generate_suggestions([
        'context' => $context,
        'title' => $title,
        'alt_title' => $altTitle,
        'torrent_name' => $torrentName,
        'existing_descr' => $descr,
        'mediainfo' => $mediainfo,
        'nfo' => $nfo,
        'file_entries' => $fileEntries,
        'total_size' => $totalSize,
        'allow_remote' => $useWikipedia,
    ]);

    upload_assistant_respond(200, [
        'ok' => true,
        'context' => $context,
        'release' => $suggestion['release'] ?? [],
        'genres' => $suggestion['genres'] ?? [],
        'tags' => $suggestion['tags'] ?? [],
        'poster_url' => $suggestion['poster_url'] ?? '',
        'summary' => $suggestion['summary'] ?? '',
        'description_template' => $suggestion['description_template'] ?? '',
        'plain_summary' => $suggestion['plain_summary'] ?? '',
        'family_probabilities' => $suggestion['family_probabilities'] ?? [],
        'category_candidates' => $suggestion['category_candidates'] ?? [],
        'field_values' => $suggestion['field_values'] ?? [],
        'audit' => $suggestion['audit'] ?? ['issues' => []],
        'wikipedia' => $suggestion['wikipedia'] ?? [],
    ]);
} catch (Throwable $e) {
    upload_assistant_respond(500, [
        'ok' => false,
        'error' => 'AI-помощник не смог обработать запрос: ' . $e->getMessage(),
    ]);
}
