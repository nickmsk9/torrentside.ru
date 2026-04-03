<?php
// takeupload.php — unified handler (PHP 8.1-ready). Адаптируйте имя/роут под свой файл.

declare(strict_types=1);

require_once __DIR__ . "/include/benc.php";
require_once __DIR__ . "/include/bittorrent.php";
require_once __DIR__ . "/include/multitracker.php";
require_once __DIR__ . "/include/upload_ai.php";

global $mysqli, $mysqli_charset, $announce_urls, $DEFAULTBASEURL, $SITENAME, $torrent_dir;

dbconn(false);
loggedinorreturn();
parked();


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (get_user_class() < UC_USER || !user_has_module('torrent_add')) {
    die; // как в исходнике — тихий отказ
}

// --- безопасные настройки (примечание: upload_max_filesize задаётся в php.ini, ini_set здесь не влияет на текущий запрос)
$maxTorrentSize = (int)($GLOBALS['max_torrent_size'] ?? 1048576);

// --- общая функция ошибки
function bark(string $msg): void {
    global $tracker_lang;
    genbark($msg, $tracker_lang['error'] ?? 'Ошибка');
}

// --- только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bark('Неверный метод запроса.');
}

// Ранняя проверка на "пустой POST" из-за переполнения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // Подсказка по лимитам
    $postMax = ini_get('post_max_size');
    $uplMax  = ini_get('upload_max_filesize');
    bark(sprintf(
        'Размер запроса превышает лимиты сервера (post_max_size=%s, upload_max_filesize=%s). Уменьши файл или увеличь лимиты.',
        $postMax ?: 'n/a',
        $uplMax  ?: 'n/a'
    ));
    exit;
}


// --- CSRF
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    bark('Проверка CSRF не пройдена.');
}
// (опционально) одноразовый токен
// unset($_SESSION['csrf_token']);

// --- обязательные поля
foreach (['type', 'name'] as $v) {
    if (!isset($_POST[$v]) || trim((string)$_POST[$v]) === '') {
        bark("Отсутствует поле формы: $v");
    }
}

// --- категория
$catid = (int)($_POST['type'] ?? 0);
if (!is_valid_id($catid)) {
    bark("Вы должны выбрать категорию, в которую поместить торрент!");
}

// --- скидка (0..100, шаг 10)
$free = isset($_POST['free']) ? (int)$_POST['free'] : 0;
if ($free < 0 || $free > 100) $free = 0;

// --- sticky (только админам)
$sticky = ((($_POST['sticky'] ?? '') === 'yes') && get_user_class() >= UC_ADMINISTRATOR) ? 'yes' : 'no';

// --- теги
$rawTags = (string)($_POST['tags'] ?? '');

// приводим разделители к запятой и нижний регистр
$replace = [", ", " , ", " ,", ";", "\n", "\r", "\t"];
$tagsCsv = trim(str_replace($replace, ",", mb_convert_case(unesc($rawTags), MB_CASE_LOWER, 'UTF-8')));

// разрезаем на массив, триммим, убираем дубли и пустые
$tagArr = array_map('trim', explode(",", $tagsCsv));
$tagArr = array_filter(array_unique($tagArr), fn($t) => $t !== '');

// ограничиваем длину каждого тега (≤32 символа) и общее количество (≤20)
$tagArr = array_map(
    fn($t) => mb_substr($t, 0, 32, 'UTF-8'),
    $tagArr
);
$tagArr = array_slice($tagArr, 0, 20);

// обратно в CSV для сохранения
$tagsCsv = implode(",", $tagArr);

// --- файл .torrent
if (empty($_FILES['tfile']) || !isset($_FILES['tfile']['tmp_name'])) {
    bark("Файл не выбран!");
}
$f = $_FILES['tfile'];
$fname = unesc($f['name'] ?? '');
if ($fname === '') {
    bark("Файл не загружен. Пустое имя файла!");
}
if (!validfilename($fname)) {
    bark("Неверное имя файла!");
}
if (!preg_match('/^(.+)\.torrent$/si', $fname, $m)) {
    bark("Неверное имя файла (не .torrent).");
}
$shortfname = $torrent = $m[1];

// отображаемое имя
if (!empty($_POST['name'])) {
    $torrent = unesc((string)$_POST['name']);
}

$tmpname = $f['tmp_name'];
if (!is_uploaded_file($tmpname)) {
    bark("Ошибка загрузки файла!");
}
$filesize = (int)@filesize($tmpname);
if ($filesize <= 0) {
    bark("Пустой файл!");
}
if ($filesize > $maxTorrentSize) {
    bark("Файл торрента превышает допустимый размер.");
}

// --- парсинг bencode
$dict = bdec_file($tmpname, $maxTorrentSize);
if (!$dict) {
    bark("Загружен не .torrent файл!");
}
$preserveExternalTrackers = (string)($_POST['preserve_external_trackers'] ?? '1') === '1';

// --- проверки структуры
function dict_check($d, $s) {
    if ($d['type'] !== 'dictionary') bark('Ожидался словарь');
    $a = explode(':', $s);
    $dd = $d['value'];
    $ret = [];
    foreach ($a as $k) {
        unset($t);
        if (preg_match('/^(.*)\((.*)\)$/', $k, $m)) {
            $k = $m[1]; $t = $m[2];
        }
        if (!isset($dd[$k])) bark("Словарь не содержит ключ '$k'");
        if (isset($t)) {
            if ($dd[$k]['type'] !== $t) bark("Неверный тип для ключа '$k'");
            $ret[] = $dd[$k]['value'];
        } else {
            $ret[] = $dd[$k];
        }
    }
    return $ret;
}
function dict_get($d, $k, $t) {
    if ($d['type'] !== 'dictionary') bark('Ожидался словарь');
    $dd = $d['value'];
    if (!isset($dd[$k])) return null;
    $v = $dd[$k];
    if ($v['type'] !== $t) bark("Неверный тип ключа '$k'");
    return $v['value'];
}

[$info] = dict_check($dict, "info");
[$dname, $plen, $pieces] = dict_check($info, "name(string):piece length(integer):pieces(string)");

if (strlen($pieces) % 20 !== 0) {
    bark("Недопустимая длина поля pieces");
}

// --- список файлов
$filelist = [];
$totallen = dict_get($info, "length", "integer");
$type = "single";

if (!isset($totallen)) {
    $flist = dict_get($info, "files", "list");
    if (!$flist || !count($flist)) bark("Не удалось получить список файлов");
    $totallen = 0;
    foreach ($flist as $fn) {
        [$ll, $ff] = dict_check($fn, "length(integer):path(list)");
        $totallen += $ll;
        $ffa = [];
        foreach ($ff as $ffe) {
            if ($ffe['type'] !== 'string') bark("Ошибка в имени файла");
            $ffa[] = $ffe['value'];
        }
        if (!count($ffa)) bark("Ошибка имени файла");
        $ffe = implode("/", $ffa);
        if ($ffe === 'Thumbs.db') {
            stderr("Ошибка", "В торрентах запрещено держать файлы Thumbs.db!");
            die;
        }
        $filelist[] = [$ffe, $ll];
    }
    $type = "multi";
}

$preparedTorrent = multitracker_prepare_uploaded_torrent($dict, $preserveExternalTrackers);
$dict = $preparedTorrent['dict'];
$infohash = $preparedTorrent['infohash'];

// --- ранняя проверка на дубль по info_hash
$dup = sql_query("SELECT id FROM torrents WHERE info_hash = " . sqlesc($infohash) . " LIMIT 1");
if ($dup && mysqli_fetch_assoc($dup)) {
    bark("Такой торрент уже существует!");
}

// --- AI-анализ: заполняем пробелы по названию, .torrent и MediaInfo/NFO
$fileEntries = array_map(
    static fn(array $file): array => ['path' => (string)$file[0], 'size' => (int)$file[1]],
    $filelist
);
$allowRemoteAutofill =
    trim((string)($_POST['descr'] ?? '')) === '' ||
    trim((string)($_POST['origname'] ?? '')) === '' ||
    trim((string)($_POST['year'] ?? '')) === '' ||
    trim((string)($_POST['image0'] ?? '')) === '' ||
    trim((string)($_POST['janr'] ?? '')) === '' ||
    trim((string)($_POST['kachestvo'] ?? '')) === '' ||
    trim((string)($_POST['resolution'] ?? '')) === '';

$aiSuggestion = tracker_upload_ai_generate_suggestions([
    'context' => 'film',
    'title' => (string)$torrent,
    'alt_title' => trim((string)($_POST['origname'] ?? '')),
    'torrent_name' => (string)$dname,
    'existing_descr' => trim((string)unesc($_POST['descr'] ?? '')),
    'mediainfo' => trim((string)unesc($_POST['ai_mediainfo'] ?? '')),
    'nfo' => trim((string)unesc($_POST['ai_nfo'] ?? '')),
    'file_entries' => $fileEntries,
    'total_size' => (int)$totallen,
    'allow_remote' => $allowRemoteAutofill,
]);

if (!empty($aiSuggestion['release']['display_title'])) {
    $torrent = (string)$aiSuggestion['release']['display_title'];
}

if (!empty($aiSuggestion['tags'])) {
    $tagArr = array_values(array_unique(array_merge(
        $tagArr,
        array_map(
            static fn(string $tag): string => mb_substr(trim(mb_strtolower($tag, 'UTF-8')), 0, 32, 'UTF-8'),
            (array)$aiSuggestion['tags']
        )
    )));
    $tagArr = array_slice(array_filter($tagArr, static fn(string $tag): bool => $tag !== ''), 0, 20);
    $tagsCsv = implode(",", $tagArr);
}

$originalTitle = trim((string)($_POST['origname'] ?? ''));
if ($originalTitle === '') {
    $originalTitle = trim((string)($aiSuggestion['release']['original_title'] ?? ''));
}

$year = trim((string)($_POST['year'] ?? ''));
if ($year === '') {
    $year = trim((string)($aiSuggestion['release']['year'] ?? ''));
}

$genre = trim((string)($_POST['janr'] ?? ''));
if ($genre === '' && !empty($aiSuggestion['genres'])) {
    $genre = implode(', ', (array)$aiSuggestion['genres']);
}

$director = trim((string)($_POST['director'] ?? ''));
$roles = trim((string)($_POST['roles'] ?? ''));
$translation = trim((string)($_POST['perevod'] ?? ''));
if ($translation === '') {
    $translation = tracker_upload_ai_translation_label(trim((string)($aiSuggestion['release']['translation'] ?? '')), 'film');
}

$story = trim((string)unesc($_POST['descr'] ?? ''));
if ($story === '') {
    $story = trim((string)($aiSuggestion['plain_summary'] ?? ''));
}

$runtime = trim((string)($_POST['time'] ?? ''));
if ($runtime === '') {
    $runtime = trim((string)($aiSuggestion['release']['runtime'] ?? ''));
}

$publisher = trim((string)($_POST['publisher'] ?? ''));
$quality = trim((string)($_POST['kachestvo'] ?? ''));
if ($quality === '') {
    $quality = trim((string)($aiSuggestion['release']['quality'] ?? ''));
}

$format = trim((string)($_POST['format'] ?? ''));
if ($format === '') {
    $format = trim((string)($aiSuggestion['release']['format'] ?? ''));
}

$resolution = trim((string)($_POST['resolution'] ?? ''));
if ($resolution === '') {
    $resolution = trim((string)($aiSuggestion['release']['resolution'] ?? ''));
}

$videoCodec = trim((string)($_POST['videocodec'] ?? ''));
if ($videoCodec === '') {
    $videoCodec = trim((string)($aiSuggestion['release']['video_codec'] ?? ''));
}

$videoBitrate = trim((string)($_POST['videobitrate'] ?? ''));
$audioCodec = trim((string)($_POST['audiocodec'] ?? ''));
if ($audioCodec === '') {
    $audioCodec = trim((string)($aiSuggestion['release']['audio_codec'] ?? ''));
}

$audioBitrate = trim((string)($_POST['audiobitrate'] ?? ''));
if ($audioBitrate === '') {
    $audioBitrate = trim((string)($aiSuggestion['release']['audio_bitrate'] ?? ''));
}

// --- описание (BB-код)
$torrentDisp = htmlspecialchars(str_replace("_", " ", $torrent), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$descr  = "[b]Название:[/b] $torrentDisp\n";
$fields = [
    ['Оригинальное название', $originalTitle],
    ['Год выхода', $year],
    ['Жанр', $genre],
    ['Режиссер', $director],
    ['В ролях', $roles],
    ['Перевод', $translation],
];
foreach ($fields as [$label, $value]) {
    if ($value !== '') {
        $descr .= "[b]$label:[/b] " . trim((string)$value) . "\n";
    }
}
if ($story !== '') {
    $descr .= "[b]О фильме:[/b]\n" . $story . "\n\n";
}
if ($runtime !== '') {
    $descr .= "[b]Продолжительность:[/b] " . $runtime . "\n";
}
if ($publisher !== '') {
    $descr .= "[b]Издатель:[/b] " . $publisher . "\n\n";
}
if ($quality !== '') {
    $descr .= "[b]Качество:[/b] " . $quality . "\n";
}
if ($format !== '') {
    $descr .= "[b]Формат:[/b] " . $format . "\n";
}

$videoParts = array_values(array_filter([$resolution, $videoCodec, $videoBitrate], static fn(string $value): bool => trim($value) !== ''));
if ($videoParts) {
    $descr .= "[b]Видео:[/b] " . implode(", ", $videoParts) . "\n";
}

$audioParts = array_values(array_filter([$audioCodec, $audioBitrate], static fn(string $value): bool => trim($value) !== ''));
if ($audioParts) {
    $descr .= "[b]Аудио:[/b] " . implode(", ", $audioParts) . "\n\n";
}

$audit = tracker_upload_ai_audit_description($descr, [
    'category_id' => $catid,
    'family_key' => (string)($aiSuggestion['family_probabilities'][0]['key'] ?? 'movie'),
    'quality' => $quality,
    'translation' => $translation,
]);
$criticalMessages = tracker_upload_ai_critical_messages($audit);
if ($criticalMessages) {
    bark(
        'AI-модератор обнаружил проблемы в описании:<br>' .
        implode('<br>', array_map(static fn(string $message): string => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $criticalMessages))
    );
}
$descr = (string)($audit['normalized_text'] ?? $descr);

// --- изображения
$image1 = trim((string)($_POST['image0'] ?? ''));
if ($image1 === '' && !empty($aiSuggestion['poster_url'])) {
    $image1 = (string)$aiSuggestion['poster_url'];
}
$image2 = trim((string)($_POST['image1'] ?? ''));
$image3 = trim((string)($_POST['image2'] ?? ''));
$image4 = trim((string)($_POST['image3'] ?? ''));
$image5 = trim((string)($_POST['image4'] ?? ''));
$releaseGroupId = get_user_class() >= UC_UPLOADER
    ? tracker_release_group_for_user((int)$CURUSER['id'], (int)($_POST['release_group_id'] ?? 0))
    : 0;

// --- вставка торрента
$now = get_date_time();
$insert = sql_query(
    "INSERT INTO torrents (
        search_text, filename, owner, visible, sticky, info_hash, name, size, numfiles, type, tags,
        descr, ori_descr, free, image1, image2, image3, image4, image5, category, save_as, release_group_id, added, last_action, poster, modname
    ) VALUES (" .
    implode(",", array_map('sqlesc', [
        searchfield("$shortfname $dname $torrent"),
        $fname,
        (string)$CURUSER['id'],
        "no",
        $sticky,
        $infohash,
        $torrent,
        (string)$totallen,
        (string)count($filelist),
        $type,
        $tagsCsv,
        $descr,
        $descr,
        (string)$free,
        $image1, $image2, $image3, $image4, $image5,
        (string)$catid,
        $dname,
        (string)$releaseGroupId
    ])) .
    ", '$now', '$now', " . sqlesc($CURUSER['id']) . ", " . sqlesc($CURUSER['username']) . ")"
);

if (!$insert) {
    if (mysqli_errno($mysqli) === 1062) {
        bark("Такой торрент уже существует!");
    }
    bark("Ошибка MySQL: " . mysqli_error($mysqli));
}

$id = (int)mysqli_insert_id($mysqli);

// --- привязки к модерации/файлам
sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($id, {$CURUSER['id']}, 1)") or sqlerr(__FILE__, __LINE__);
sql_query("DELETE FROM files WHERE torrent = $id");

foreach ($filelist as $file) {
    $fnameRow = sqlesc($file[0]);
    $fsizeRow = (int)$file[1];
    sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, $fnameRow, $fsizeRow)");
}

// --- сохраняем .torrent (перезаписывая нормализованным содержимым)
$target = rtrim($torrent_dir, "/") . "/$id.torrent";
if (@file_put_contents($target, benc($dict)) === false) {
    // как fallback попробуем move_uploaded_file, но это не идеал (без наших правок)
    @move_uploaded_file($tmpname, $target);
}

multitracker_save_trackers($id, $preparedTorrent['all_trackers']);

// --- теги: апсерт+счётчики
$existingTags = [];
$res = sql_query("SELECT name FROM tags WHERE category = " . sqlesc($catid));
while ($row = mysqli_fetch_assoc($res)) {
    $existingTags[] = $row['name'];
}
$common  = array_intersect($existingTags, $tagArr);
$toInsert = array_diff($tagArr, $existingTags);

if ($common) {
    $in = implode(',', array_map('sqlesc', $common));
    sql_query("UPDATE tags SET howmuch = howmuch + 1 WHERE category = " . sqlesc($catid) . " AND name IN ($in)") or sqlerr(__FILE__, __LINE__);
}
foreach ($toInsert as $tag) {
    sql_query("INSERT INTO tags (category, name, howmuch) VALUES (" . sqlesc($catid) . ", " . sqlesc($tag) . ", 1)") or sqlerr(__FILE__, __LINE__);
}

tracker_recount_tags_for_categories((int)$catid);
tracker_refresh_torrent_search_index($id);
tracker_invalidate_torrent_cache($id, true);
if ($releaseGroupId > 0) {
    tracker_invalidate_release_group_cache($releaseGroupId);
}

// --- лог
write_log("Торрент №$id ($torrent) залит пользователем {$CURUSER['username']}", "5DDB6E", "torrent");

// --- ответ
stdhead("Файл загружен");
$downlink = "<a href=\"download.php?id=$id&amp;name=" . htmlspecialchars($fname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"><span style=\"color:red\">СКАЧАТЬ ФАЙЛ</span></a>";

begin_main_frame();
begin_frame();

echo <<<HTML
<div style='width:100%;border:1px dashed #008000;padding:10px;background-color:#D6F3CC'>
<b><span style="font-size:13px">Спасибо, Ваша раздача почти готова. Теперь нужно $downlink и начать раздачу в клиенте.</span></b></div><br>
<div style='width:100%;border:1px dashed #990000;padding:10px;background-color:#FFF0F0'>
<b><span style="color:#990000;font-size:13px">Напоминаем, что Вы должны сидировать свой релиз, чтобы он стал видим!</span></b></div><br>
<center>
<table class="my_table" width="100%">
<tr>
    <td class="bottom"><form method="post" action="edit.php?id=$id"><input type="submit" value="Редактировать торрент" style="height:20px;width:160px;"></form></td>
    <td class="bottom"><form method="post" action="details.php?id=$id"><input type="submit" value="Перейти к деталям" style="height:20px;width:160px;"></form></td>
    <td class="bottom"><form method="post" action="torrent_info.php?id=$id"><input type="submit" value="Данные торрента" style="height:20px;width:160px;"></form></td>
</tr>
</table>
</center>
HTML;

end_frame();
end_main_frame();

stdfoot();
