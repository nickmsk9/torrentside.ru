<?php
// takeupload.php — безопасный обработчик загрузки торрента (PHP 8.1-ready)
declare(strict_types=1);

require_once __DIR__ . "/include/benc.php";
require_once __DIR__ . "/include/bittorrent.php";
require_once __DIR__ . "/include/multitracker.php";

global $mysqli, $mysqli_charset, $announce_urls, $DEFAULTBASEURL, $SITENAME, $torrent_dir, $max_torrent_size, $tracker_lang, $CURUSER;

dbconn(false);
loggedinorreturn();
parked();

if (get_user_class() < UC_USER || !user_has_module('torrent_add')) {
    die;
}

// ВАЖНО: ini_set(upload_max_filesize) не влияет на текущую загрузку — ориентируемся на $max_torrent_size
$maxTorrentSize = (int)($max_torrent_size ?? 1048576);

// Универсальная ошибка
function bark(string $msg): void {
    global $tracker_lang;
    genbark($msg, $tracker_lang['error'] ?? 'Ошибка');
}

// --- ТОЛЬКО POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    bark('Неверный метод запроса.');
}

// --- CSRF (ожидается, что формы добавляют hidden csrf_token)
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    bark('Проверка CSRF не пройдена.');
}
// Можно одноразово инвалидировать токен:
// unset($_SESSION['csrf_token']);

// --- Обязательные поля
foreach (['descr','type','name'] as $v) {
    if (!isset($_POST[$v]) || trim((string)$_POST[$v]) === '') {
        bark("Отсутствует поле формы: $v");
    }
}

// --- Категория
$catid = (int)($_POST['type'] ?? 0);
if (!is_valid_id($catid)) {
    bark("Вы должны выбрать категорию!");
}

// --- Скидка (0..100, шаг не важен)
$free = isset($_POST['free']) ? (int)$_POST['free'] : 0;
if ($free < 0 || $free > 100) $free = 0;

// --- Sticky (только админам)
$sticky = ((($_POST['sticky'] ?? '') === 'yes') && get_user_class() >= UC_ADMINISTRATOR) ? 'yes' : 'no';

// --- Теги: нормализация
$rawTags = (string)($_POST['tags'] ?? '');
$replace = [", ", " , ", " ,", ";", "\n", "\r", "\t"];
$tagsCsv = trim(str_replace($replace, ",", mb_convert_case(unesc($rawTags), MB_CASE_LOWER, 'UTF-8')));
$tagArr  = array_filter(array_unique(array_map('trim', explode(",", $tagsCsv))));
$tagArr  = array_slice(array_map(fn($t) => mb_substr($t, 0, 32, 'UTF-8')), 0, 20);
$tagsCsv = implode(",", $tagArr);

// --- Файл .torrent
if (empty($_FILES['tfile']) || !isset($_FILES['tfile']['tmp_name'])) {
    bark("Файл не выбран!");
}
$f = $_FILES['tfile'];
$fname = unesc($f['name'] ?? '');
if ($fname === '') bark("Файл не загружен. Пустое имя файла!");
if (!validfilename($fname)) bark("Неверное имя файла!");
if (!preg_match('/^(.+)\.torrent$/si', $fname, $m)) bark("Файл должен иметь расширение .torrent");

$shortfname = $torrentName = $m[1];
if (!empty($_POST['name'])) {
    $torrentName = unesc((string)$_POST['name']);
}

$tmpname = $f['tmp_name'];
if (!is_uploaded_file($tmpname)) bark("Ошибка загрузки файла!");
$filesize = (int)@filesize($tmpname);
if ($filesize <= 0) bark("Пустой файл!");
if ($filesize > $maxTorrentSize) bark("Файл торрента превышает допустимый размер.");

// --- Читаем bencode
$dict = bdec_file($tmpname, $maxTorrentSize);
if (!$dict) bark("Файл повреждён или не является .torrent");
$preserveExternalTrackers = (string)($_POST['preserve_external_trackers'] ?? '1') === '1';

// --- Вспомогательные проверки .torrent
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
        if (!isset($dd[$k])) bark('dictionary is missing key(s)');
        if (isset($t)) {
            if ($dd[$k]['type'] !== $t) bark('invalid entry in dictionary');
            $ret[] = $dd[$k]['value'];
        } else {
            $ret[] = $dd[$k];
        }
    }
    return $ret;
}
function dict_get($d, $k, $t) {
    if ($d['type'] !== 'dictionary') bark('not a dictionary');
    $dd = $d['value'];
    if (!isset($dd[$k])) return null;
    $v = $dd[$k];
    if ($v['type'] !== $t) bark('invalid dictionary entry type');
    return $v['value'];
}

// --- Базовые поля info
[$info] = dict_check($dict, "info");
[$dname, $plen, $pieces] = dict_check($info, "name(string):piece length(integer):pieces(string)");
if (strlen($pieces) % 20 !== 0) bark("invalid pieces");

// --- Список файлов и размер
$filelist = [];
$totallen = dict_get($info, "length", "integer");
$type = "single";
if (isset($totallen)) {
    $filelist[] = [$dname, $totallen];
} else {
    $flist = dict_get($info, "files", "list");
    if (!$flist || !count($flist)) bark("no files");
    $totallen = 0;
    foreach ($flist as $fn) {
        [$ll, $ff] = dict_check($fn, "length(integer):path(list)");
        $totallen += $ll;
        $parts = [];
        foreach ($ff as $ffe) {
            if ($ffe['type'] !== 'string') bark("filename error");
            $parts[] = $ffe['value'];
        }
        if (!count($parts)) bark("filename error");
        $path = implode("/", $parts);
        if ($path === 'Thumbs.db') {
            stderr("Ошибка", "В торрентах запрещено держать файлы Thumbs.db!");
            die;
        }
        $filelist[] = [$path, $ll];
    }
    $type = "multi";
}

$preparedTorrent = multitracker_prepare_uploaded_torrent($dict, $preserveExternalTrackers);
$dict = $preparedTorrent['dict'];
$infohash = $preparedTorrent['infohash'];

// --- Ранний чек дублей
$dup = sql_query("SELECT id FROM torrents WHERE info_hash = " . sqlesc($infohash) . " LIMIT 1");
if ($dup && mysqli_fetch_assoc($dup)) {
    bark("Такой торрент уже существует!");
}

// --- Картинки
$image1 = trim((string)($_POST["image0"] ?? ''));
$image2 = trim((string)($_POST["image1"] ?? ''));
$image3 = trim((string)($_POST["image2"] ?? ''));
$image4 = trim((string)($_POST["image3"] ?? ''));
$image5 = trim((string)($_POST["image4"] ?? ''));

// --- Формируем описание (под софт/игры, но универсально)
$torrentDisp = htmlspecialchars(str_replace("_", " ", $torrentName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$totaldesc = "[b]Название:[/b] " . $torrentDisp . "\n";

$fields = [
    ['Год выхода', 'year'],
    ['Разработчик', 'director'],
    ['Платформа', 'plata'],
    ['Таблетка', 'tablet'],
    ['Язык', 'translation'],
];
foreach ($fields as [$label, $key]) {
    if (!empty($_POST[$key])) {
        $totaldesc .= "[b]{$label}:[/b] " . trim((string)$_POST[$key]) . "\n";
    }
}
if (!empty($_POST['descr']))     $totaldesc .= "\n[b]Описание:[/b] " . trim((string)$_POST['descr']) . "\n";
if (!empty($_POST['opisanie']))  $totaldesc .= "\n[b]Системные требования:[/b] " . trim((string)$_POST['opisanie']) . "\n";

$descr = $totaldesc;
$releaseGroupId = get_user_class() >= UC_UPLOADER
    ? tracker_release_group_for_user((int)$CURUSER['id'], (int)($_POST['release_group_id'] ?? 0))
    : 0;

// --- INSERT в torrents
$now = get_date_time();
$ret = sql_query("INSERT INTO torrents (
    search_text, filename, owner, visible, sticky, info_hash, name, size, numfiles, type, tags,
    descr, ori_descr, free, image1, image2, image3, image4, image5,
    category, save_as, release_group_id, added, last_action, poster, modname, modtime
) VALUES (" .
    implode(",", array_map('sqlesc', [
        searchfield("$shortfname $dname $torrentDisp"),
        $fname,
        (string)$CURUSER['id'],
        "no",
        $sticky,
        $infohash,
        $torrentDisp,
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
    ", '$now', '$now', " . sqlesc($CURUSER['id']) . ", " . sqlesc($CURUSER['username']) . ", '$now')"
);

if (!$ret) {
    bark("Ошибка при добавлении торрента!");
}

$id = (int)mysqli_insert_id($mysqli);

// --- Привязки/файлы
sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($id, {$CURUSER['id']}, 1)");
sql_query("DELETE FROM files WHERE torrent = $id");
foreach ($filelist as $file) {
    sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, " . sqlesc($file[0]) . ", " . (int)$file[1] . ")");
}

// --- Сохраняем нормализованный .torrent
$target = rtrim($torrent_dir, "/") . "/$id.torrent";
if (@file_put_contents($target, benc($dict)) === false) {
    // как фоллбэк — хотя лучше всегда писать нормализованный
    @move_uploaded_file($tmpname, $target);
}

multitracker_save_trackers($id, $preparedTorrent['all_trackers']);

// --- Теги
$existing = [];
$res = sql_query("SELECT name FROM tags WHERE category = " . sqlesc($catid));
while ($row = mysqli_fetch_assoc($res)) $existing[] = $row['name'];

$common   = array_intersect($existing, $tagArr);
$toInsert = array_diff($tagArr, $existing);

if ($common) {
    $in = implode(",", array_map('sqlesc', $common));
    sql_query("UPDATE tags SET howmuch = howmuch + 1 WHERE category = " . sqlesc($catid) . " AND name IN ($in)");
}
foreach ($toInsert as $tag) {
    sql_query("INSERT INTO tags (category, name, howmuch) VALUES (" . sqlesc($catid) . ", " . sqlesc($tag) . ", 1)");
}

tracker_recount_tags_for_categories((int)$catid);
tracker_invalidate_torrent_cache($id, true);
if ($releaseGroupId > 0) {
    tracker_invalidate_release_group_cache($releaseGroupId);
}

// --- Лог
write_log("Торрент номер $id ($torrentDisp) был залит пользователем {$CURUSER['username']}", "5DDB6E", "torrent");

// --- Ответ пользователю
stdhead("Файл загружен");

$downlink = "<a title=\"Скачать\" href=\"download.php?id=$id&amp;name=" . htmlspecialchars($fname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"><span style=\"color:red;cursor:help;\">СКАЧАТЬ ФАЙЛ</span></a>";

begin_frame();
echo "<div style='width:100%;border:1px dashed #008000;padding:10px;background-color:#D6F3CC'>
<b><span style='font-size:13px'>Спасибо, Ваша раздача почти готова. Торрент-файл размещён на сервере.<hr>
Теперь нужно $downlink и начать раздачу в клиенте.</span></b></div><br>";

echo "<div style='width:100%;border:1px dashed #990000;padding:10px;background-color:#FFF0F0'>
<b><span style='color:#990000;font-size:13px'>Напоминаем: Вы должны сидировать релиз, чтобы он был виден!</span></b></div><br>";

echo "<center><table class='my_table' width='100%' border='0'>
<tr>
  <td class='bottom'><form method='post' action='edit.php?id=$id'><input type='submit' value='Редактировать торрент' style='height:20px;width:160px;'></form></td>
  <td class='bottom'><form method='post' action='details.php?id=$id'><input type='submit' value='Перейти к деталям' style='height:20px;width:160px;'></form></td>
  <td class='bottom'><form method='post' action='torrent_info.php?id=$id'><input type='submit' value='Данные торрента' style='height:20px;width:160px;'></form></td>
</tr></table></center>";
end_frame();

stdfoot();
