<?php
// takeupload.php — безопасный и быстрый обработчик (PHP 8.1-ready)
declare(strict_types=1);

require_once __DIR__ . "/include/benc.php";
require_once __DIR__ . "/include/bittorrent.php";

global $mysqli, $mysqli_charset, $announce_urls, $DEFAULTBASEURL, $SITENAME, $torrent_dir, $max_torrent_size, $tracker_lang, $CURUSER;

dbconn(false);
loggedinorreturn();
parked();

if (get_user_class() < UC_USER) {
    die;
}

// NB: ini_set('upload_max_filesize') не влияет на уже идущий аплоад — полагаемся на $max_torrent_size
$maxTorrentSize = (int)($max_torrent_size ?? 1048576);

// Универсальная ошибка
function bark(string $msg): void {
    global $tracker_lang;
    genbark($msg, $tracker_lang['error'] ?? 'Ошибка');
}

// --- Разрешаем только POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    bark('Неверный метод запроса.');
}

// --- CSRF (форма должна передавать hidden csrf_token)
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    bark('Проверка CSRF не пройдена.');
}
// При желании делаем токен одноразовым:
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
    bark("Вы должны выбрать категорию, в которую поместить торрент!");
}

// --- Скидка (вариант A: проценты 0..100). Если у вас логика yes/no — оставьте как было.
// $free = isset($_POST['free']) ? max(0, min(100, (int)$_POST['free'])) : 0;

// --- Скидка (вариант B: yes/no как в исходнике)
$free = (($_POST['free'] ?? 'no') === 'yes') ? 'yes' : 'no';

// --- Sticky (только админам)
$sticky = ((($_POST['sticky'] ?? '') === 'yes') && get_user_class() >= UC_ADMINISTRATOR) ? 'yes' : 'no';

// --- Теги: нормализация
$rawTags = (string)($_POST['tags'] ?? '');
$replace = [", ", " , ", " ,", ";", "\n", "\r", "\t"];
$tagsCsv = trim(str_replace($replace, ",", mb_convert_case(unesc($rawTags), MB_CASE_LOWER, $mysqli_charset ?: 'UTF-8')));
$tagArr  = array_filter(array_unique(array_map('trim', explode(",", $tagsCsv))));
$tagArr  = array_slice(array_map(fn($t) => mb_substr($t, 0, 32)), 0, 20); // ≤20 тегов, ≤32 символа
$tagsCsv = implode(",", $tagArr);

// --- Файл .torrent
if (empty($_FILES['tfile']) || !isset($_FILES['tfile']['tmp_name'])) {
    bark("Файл не выбран!");
}
$f = $_FILES['tfile'];
$fname = unesc($f['name'] ?? '');
if ($fname === '') bark("Файл не загружен. Пустое имя файла!");
if (!validfilename($fname)) bark("Неверное имя файла!");
if (!preg_match('/^(.+)\.torrent$/si', $fname, $m)) bark("Неверное имя файла (не .torrent).");

$shortfname = $torrentName = $m[1];
if (!empty($_POST['name'])) {
    $torrentName = unesc((string)$_POST['name']);
}

$tmpname = $f['tmp_name'];
if (!is_uploaded_file($tmpname)) bark("Ошибка загрузки файла.");
$filesize = (int)@filesize($tmpname);
if ($filesize <= 0) bark("Пустой файл!");
if ($filesize > $maxTorrentSize) bark("Файл торрента превышает допустимый размер.");

// --- Читаем bencode
$dict = bdec_file($tmpname, $maxTorrentSize);
if (!$dict) bark("Загруженный файл не является корректным .torrent!");

// --- Вспомогательные проверки .torrent
function dict_check($d, $s) {
    if ($d['type'] !== 'dictionary') bark('Ожидался словарь (dictionary)');
    $a = explode(':', $s);
    $dd = $d['value'];
    $ret = [];
    foreach ($a as $k) {
        unset($t);
        if (preg_match('/^(.*)\((.*)\)$/', $k, $m)) {
            $k = $m[1]; $t = $m[2];
        }
        if (!isset($dd[$k])) bark("В словаре отсутствует ключ $k");
        if (isset($t)) {
            if ($dd[$k]['type'] !== $t) bark("Неверный тип для ключа $k");
            $ret[] = $dd[$k]['value'];
        } else {
            $ret[] = $dd[$k];
        }
    }
    return $ret;
}
function dict_get($d, $k, $t) {
    if ($d['type'] !== 'dictionary') bark('Ожидался словарь (dictionary)');
    $dd = $d['value'];
    if (!isset($dd[$k])) return null;
    $v = $dd[$k];
    if ($v['type'] !== $t) bark("Неверный тип значения в словаре для ключа $k");
    return $v['value'];
}

// --- Поля info
[$info] = dict_check($dict, "info");
[$dname, $plen, $pieces] = dict_check($info, "name(string):piece length(integer):pieces(string)");
if (strlen($pieces) % 20 !== 0) bark("Неверная структура 'pieces'");

// --- Список файлов и общий размер
$filelist = [];
$totallen = dict_get($info, "length", "integer");
$type = "single";
if (isset($totallen)) {
    $filelist[] = [$dname, $totallen];
} else {
    $flist = dict_get($info, "files", "list");
    if (!$flist || !count($flist)) bark("Файлы не найдены в .torrent");
    $totallen = 0;
    foreach ($flist as $fn) {
        [$ll, $ff] = dict_check($fn, "length(integer):path(list)");
        $totallen += $ll;
        $parts = [];
        foreach ($ff as $ffe) {
            if ($ffe['type'] !== 'string') bark("Ошибка в названии файла");
            $parts[] = $ffe['value'];
        }
        if (!count($parts)) bark("Ошибка в названии файла");
        $path = implode("/", $parts);
        if ($path === 'Thumbs.db') {
            stderr("Ошибка", "В торрентах запрещено держать файлы Thumbs.db!");
            die;
        }
        $filelist[] = [$path, $ll];
    }
    $type = "multi";
}

// --- Переписываем announce/private/source
$dict['value']['announce'] = bdec(benc_str($announce_urls[0]));
$dict['value']['info']['value']['private'] = bdec('i1e');
$dict['value']['info']['value']['source']  = bdec(benc_str("[$DEFAULTBASEURL] $SITENAME"));
unset(
    $dict['value']['announce-list'],
    $dict['value']['nodes'],
    $dict['value']['azureus_properties'],
    $dict['value']['info']['value']['crc32'],
    $dict['value']['info']['value']['ed2k'],
    $dict['value']['info']['value']['md5sum'],
    $dict['value']['info']['value']['sha1'],
    $dict['value']['info']['value']['tiger']
);

// --- Нормализуем и проставим сервисные поля
$dict = bdec(benc($dict));
$dict['value']['comment']             = bdec(benc_str("Торрент создан для '$SITENAME'"));
$dict['value']['created by']          = bdec(benc_str($CURUSER['username']));
$dict['value']['publisher']           = bdec(benc_str($CURUSER['username']));
$dict['value']['publisher.utf-8']     = bdec(benc_str($CURUSER['username']));
$dict['value']['publisher-url']       = bdec(benc_str("$DEFAULTBASEURL/userdetails.php?id={$CURUSER['id']}"));
$dict['value']['publisher-url.utf-8'] = bdec(benc_str("$DEFAULTBASEURL/userdetails.php?id={$CURUSER['id']}"));

// --- Итоговый infohash (после нормализации!)
[$info] = dict_check($dict, "info");
$infohash = sha1($info['string']);

// --- Ранний чек дублей
$dup = sql_query("SELECT id FROM torrents WHERE info_hash = " . sqlesc($infohash) . " LIMIT 1");
if ($dup && mysqli_fetch_assoc($dup)) {
    bark("Такой торрент уже был загружен!");
}

// --- Описание (берём готовое из формы, но чуть-чуть страхуем)
$descr = trim((string)unesc($_POST['descr'] ?? ''));
if ($descr === '') bark("Вы должны ввести описание!");

// --- Картинки
$image1 = trim((string)($_POST['image0'] ?? ''));
$image2 = trim((string)($_POST['image1'] ?? ''));
$image3 = trim((string)($_POST['image2'] ?? ''));
$image4 = trim((string)($_POST['image3'] ?? ''));
$image5 = trim((string)($_POST['image4'] ?? ''));

// --- Готовим INSERT в torrents
$now        = get_date_time();
$torrentDisp= htmlspecialchars(str_replace("_", " ", $torrentName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$searchText = searchfield("$shortfname $dname $torrentDisp");

$ret = sql_query("INSERT INTO torrents (
    search_text, filename, owner, visible, sticky, info_hash, name, size, numfiles, type, tags,
    descr, ori_descr, free, image1, image2, image3, image4, image5,
    category, save_as, added, last_action, poster, modname, modtime
) VALUES (" .
    implode(",", array_map('sqlesc', [
        $searchText,
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
        $free,
        $image1, $image2, $image3, $image4, $image5,
        (string)$catid,
        $dname
    ])) .
    ", '$now', '$now', " . sqlesc($CURUSER['id']) . ", " . sqlesc($CURUSER['username']) . ", '$now')"
);

if (!$ret) {
    bark("Ошибка при добавлении торрента!");
}

$id = (int)mysqli_insert_id($mysqli);

// --- Привязки/файлы
sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($id, {$CURUSER['id']}, 1)") or sqlerr(__FILE__, __LINE__);
sql_query("DELETE FROM files WHERE torrent = $id");
foreach ($filelist as $file) {
    sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, " . sqlesc($file[0]) . ", " . (int)$file[1] . ")");
}

// --- Сохраняем нормализованный .torrent
$target = rtrim($torrent_dir, "/") . "/$id.torrent";
if (@file_put_contents($target, benc($dict)) === false) {
    // Фоллбэк (но лучше всегда писать нормализованный)
    @move_uploaded_file($tmpname, $target);
}

// --- Теги: апдейт/вставка
$existing = [];
$res = sql_query("SELECT name FROM tags WHERE category = " . sqlesc($catid));
while ($row = mysqli_fetch_assoc($res)) $existing[] = $row['name'];

$common   = array_intersect($existing, $tagArr);
$toInsert = array_diff($tagArr, $existing);

if ($common) {
    $in = implode(',', array_map('sqlesc', $common));
    sql_query("UPDATE tags SET howmuch = howmuch + 1 WHERE category = " . sqlesc($catid) . " AND name IN ($in)") or sqlerr(__FILE__, __LINE__);
}
foreach ($toInsert as $tag) {
    sql_query("INSERT INTO tags (category, name, howmuch) VALUES (" . sqlesc($catid) . ", " . sqlesc($tag) . ", 1)") or sqlerr(__FILE__, __LINE__);
}

// --- Лог
write_log("Торрент номер $id ($torrentDisp) был залит пользователем {$CURUSER['username']}", "5DDB6E", "torrent");

// --- Ответ пользователю
stdhead("Файл загружен");

$downlink = "<a title=\"Скачать\" href=\"download.php?id=$id&amp;name=" . htmlspecialchars($fname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"><span style=\"color:red;cursor:help;\" title=\"Скачать торрент-файл.\">СКАЧАТЬ ФАЙЛ</span></a>";

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

stdfoot();
