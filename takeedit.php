<?php
declare(strict_types=1);

require_once __DIR__ . "/include/benc.php";
require_once __DIR__ . "/include/bittorrent.php";

function bark(string $msg): void {
    stderr("Ошибка", $msg);
    exit;
}
function dict_check(array $d, string $s): array {
    if (($d["type"] ?? null) !== "dictionary") bark("not a dictionary");
    $a  = explode(":", $s);
    $dd = $d["value"] ?? [];
    $ret = [];
    foreach ($a as $k) {
        $t = null;
        if (preg_match('/^(.*)\((.*)\)$/', $k, $m)) {
            $k = $m[1];
            $t = $m[2];
        }
        if (!isset($dd[$k])) bark("dictionary is missing key(s)");
        if ($t !== null) {
            if (($dd[$k]["type"] ?? null) !== $t) bark("invalid entry in dictionary");
            $ret[] = $dd[$k]["value"];
        } else {
            $ret[] = $dd[$k];
        }
    }
    return $ret;
}
function dict_get(array $d, string $k, string $t) {
    if (($d["type"] ?? null) !== "dictionary") bark("not a dictionary");
    $dd = $d["value"] ?? [];
    if (!isset($dd[$k])) return null;
    $v = $dd[$k];
    if (($v["type"] ?? null) !== $t) bark("invalid dictionary entry type");
    return $v["value"] ?? null;
}

/** Bootstrap */
dbconn();
loggedinorreturn();
global $CURUSER, $mysqli_charset, $torrent_dir, $max_torrent_size, $announce_urls, $DEFAULTBASEURL, $SITENAME;

/** CSRF */
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    bark("Неверный CSRF токен. Обновите страницу и попробуйте снова.");
}

/** ID */
$id = 0;
if (isset($_GET['id']))  $id = (int)$_GET['id'];
if (isset($_POST['id'])) $id = (int)$_POST['id'];
if ($id <= 0) die("Access denied: Wrong ID");

/** Permission + base row */
$res = sql_query("SELECT owner, filename, save_as, image1, image2, image3, image4, image5 FROM torrents WHERE id = " . sqlesc($id));
$row = mysqli_fetch_assoc($res);
if (!$row) die("Торрент не найден");
if ((int)$CURUSER["id"] !== (int)$row["owner"] && get_user_class() < UC_MODERATOR) {
    bark("Вы не являетесь владельцем торрента");
}

/** Inputs */
$name    = trim((string)($_POST['name']  ?? ''));
$descr   = (string)($_POST['descr'] ?? '');
$tags    = (string)($_POST['tags']  ?? '');
$oldtags = (string)($_POST['oldtags'] ?? '');
$typeIn  = (int)($_POST['type'] ?? 0);

if ($name === '' || $descr === '' || $typeIn <= 0) {
    bark("Недостаточно данных формы");
}

/** Returnto (internal only) */
$returnto = '';
if (!empty($_POST["returnto"])) {
    $rt = (string)$_POST["returnto"];
    if (str_starts_with($rt, '/')
        || (!preg_match('~^[a-z]+://~i', $rt) && !str_starts_with($rt, '//'))) {
        $returnto = $rt;
    }
}

/** Prepare update set */
$updateset = [];

/** File name helpers (from current row as baseline) */
$fname      = (string)$row["filename"];
$shortfname = '';
if (preg_match('/^(.+)\.torrent$/si', $fname, $m)) {
    $shortfname = $m[1] ?? '';
}
$dname = (string)$row["save_as"];

/** Check if we replace .torrent */
$update_torrent = isset($_FILES["tfile"]["name"]) && $_FILES["tfile"]["name"] !== '';

$filelist = [];
$totallen = 0;

if ($update_torrent) {
    $f = $_FILES["tfile"];

    $fname = unesc($f["name"]);
    if (!validfilename($fname)) bark("Неверное имя файла!");
    if (!preg_match('/^(.+)\.torrent$/si', $fname, $mm)) bark("Файл не имеет расширения .torrent");

    $shortfname = $mm[1] ?? '';
    $tmpname = $f["tmp_name"];
    if (!is_uploaded_file($tmpname) || !filesize($tmpname)) bark("Неверный файл!");

    $dict = bdec_file($tmpname, $max_torrent_size);
    if (!$dict) bark("Файл не является .torrent");

    /** parse info */
    [$info] = dict_check($dict, "info");
    [$dname, $plen, $pieces] = dict_check($info, "name(string):piece length(integer):pieces(string)");
    if (strlen($pieces) % 20 !== 0) bark("invalid pieces");

    /** build file list & total len */
    $totallen = (int)(dict_get($info, "length", "integer") ?? 0);
    if ($totallen > 0) {
        $filelist[] = [$dname, $totallen];
    } else {
        $flist = dict_get($info, "files", "list");
        if (!$flist || !count($flist)) bark("no files");
        $totallen = 0;
        foreach ($flist as $fn) {
            [$ll, $ff] = dict_check($fn, "length(integer):path(list)");
            $totallen += (int)$ll;
            $ffa = [];
            foreach ($ff as $ffe) {
                if (($ffe["type"] ?? null) !== "string") bark("filename error");
                $ffa[] = $ffe["value"];
            }
            $ffe = implode("/", $ffa);
            if (strcasecmp($ffe, 'Thumbs.db') === 0) bark("В торрентах запрещён файл Thumbs.db!");
            $filelist[] = [$ffe, (int)$ll];
        }
    }

    /** mutate torrent (announce, private, source, meta) */
    // announce
    $dict['value']['announce'] = bdec(benc_str($announce_urls[0]));
    // info flags
    $dict['value']['info']['value']['private'] = bdec('i1e');
    $dict['value']['info']['value']['source']  = bdec(benc_str("[$DEFAULTBASEURL] $SITENAME"));
    // cleanup public fields
    unset(
        $dict['value']['announce-list'],
        $dict['value']['nodes'],
        $dict['value']['azureus_properties']
    );
    unset(
        $dict['value']['info']['value']['crc32'],
        $dict['value']['info']['value']['ed2k'],
        $dict['value']['info']['value']['md5sum'],
        $dict['value']['info']['value']['sha1'],
        $dict['value']['info']['value']['tiger']
    );
    // meta
    $dict['value']['comment']       = bdec(benc_str("Торрент создан для '$SITENAME'"));
    $dict['value']['created by']    = bdec(benc_str($CURUSER["username"]));
    $dict['value']['publisher']     = bdec(benc_str($CURUSER["username"]));
    $dict['value']['publisher-url'] = bdec(benc_str("$DEFAULTBASEURL/userdetails.php?id={$CURUSER['id']}"));

    /** recompute info hash against *mutated* info dict */
    $info_bencoded = benc($dict['value']['info']);
    $infohash      = sha1($info_bencoded);

    /** safe write: tmp → atomic rename */
    $target = rtrim($torrent_dir, '/\\') . '/' . $id . '.torrent';
    $tmpOut = $target . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($tmpOut, benc($dict)) === false) {
        bark("Не удалось записать файл торрента");
    }
    // заменить оригинал атомарно
    if (!@rename($tmpOut, $target)) {
        @unlink($tmpOut);
        bark("Не удалось заменить файл торрента");
    }

    /** stage updates */
    $updateset[] = "info_hash = " . sqlesc($infohash);
    $updateset[] = "size      = " . sqlesc($totallen);
    $updateset[] = "numfiles  = " . sqlesc(count($filelist));
    $updateset[] = "filename  = " . sqlesc($fname);
    $updateset[] = "save_as   = " . sqlesc($dname);
}

/** Tags normalize */
$replace = [", ", " , ", " ,"];
$tagsNorm = mb_convert_case(unesc($tags), MB_CASE_LOWER, $mysqli_charset);
$tagsNorm = trim(str_replace($replace, ",", $tagsNorm), " ,");
$oldtagsNorm = trim(unesc($oldtags), " ,");

$tagsArr    = $tagsNorm === '' ? [] : array_filter(array_map('trim', explode(',', $tagsNorm)));
$oldTagsArr = $oldtagsNorm === '' ? [] : array_filter(array_map('trim', explode(',', $oldtagsNorm)));

$toAdd   = array_values(array_diff($tagsArr, $oldTagsArr));
$toMinus = array_values(array_diff($oldTagsArr, $tagsArr));

/** Build search_text */
$search_text = trim($shortfname . ' ' . $dname . ' ' . $name);

/** Images */
$image1 = (string)($_POST["image2"] ?? '');
$image2 = (string)($_POST["image3"] ?? '');
$image3 = (string)($_POST["image4"] ?? '');
$image4 = (string)($_POST["image5"] ?? '');
$image5 = (string)($_POST["image6"] ?? '');

/** Privileged flags */
if (get_user_class() >= UC_ADMINISTRATOR) {
    $banned = isset($_POST["banned"]) ? "yes" : "no";
    $updateset[] = "banned  = " . sqlesc($banned);

    $sticky = (isset($_POST["sticky"]) && $_POST["sticky"] === "yes") ? "yes" : "no";
    $updateset[] = "sticky  = " . sqlesc($sticky);
}
if (get_user_class() >= UC_UPLOADER) {
    $free = (int)($_POST["free"] ?? 0);
    if ($free < 0)   $free = 0;
    if ($free > 100) $free = 100;
    $updateset[] = "free    = " . sqlesc($free);
}

/** Visible + moderation marks */
$visible = isset($_POST["visible"]) ? "yes" : "no";
$updateset[] = "visible     = " . sqlesc($visible);
$updateset[] = "moderated   = 'yes'";
$updateset[] = "moderatedby = " . sqlesc((int)$CURUSER["id"]);
// Замок видимости: защищает от скрытия в docleanup()
$visible_lock = isset($_POST["visible_lock"]) ? 1 : 0;
$updateset[]  = "visible_lock = " . (int)$visible_lock;
if ($visible === 'yes' && $visible_lock === 0) {
    $updateset[] = "last_action = NOW()";
}


/** Core fields (name/descr/tags/search/images/category) */
$updateset[] = "name        = " . sqlesc($name);
$updateset[] = "descr       = " . sqlesc(unesc($descr));
$updateset[] = "ori_descr   = " . sqlesc(unesc($descr));
$updateset[] = "tags        = " . sqlesc(implode(',', $tagsArr));
$updateset[] = "search_text = " . sqlesc($search_text);
$updateset[] = "image1      = " . sqlesc($image1);
$updateset[] = "image2      = " . sqlesc($image2);
$updateset[] = "image3      = " . sqlesc($image3);
$updateset[] = "image4      = " . sqlesc($image4);
$updateset[] = "image5      = " . sqlesc($image5);
$updateset[] = "category    = " . sqlesc($typeIn);

/** DB ops: transaction */
sql_query("START TRANSACTION");

/** Update tags stats (only within chosen category) */
$ret = [];
$res = sql_query("SELECT name FROM tags WHERE category = " . sqlesc($typeIn));
while ($r = mysqli_fetch_assoc($res)) {
    $ret[] = $r["name"];
}
$knownSet = array_flip($ret);
$toPlusKnown   = array_values(array_intersect($tagsArr, array_keys($knownSet)));     // будут в tags (обновим)
$toInsertNew   = array_values(array_diff($tagsArr, array_keys($knownSet)));          // новых нет в tags
$toDecrement   = $toMinus; // всё, что убрали, уменьшаем

foreach ($toPlusKnown as $tag) {
    sql_query("UPDATE tags SET howmuch = howmuch + 1 WHERE category = " . sqlesc($typeIn) . " AND name = " . sqlesc($tag));
}
foreach ($toDecrement as $tag) {
    sql_query("UPDATE tags SET howmuch = GREATEST(howmuch - 1, 0) WHERE category = " . sqlesc($typeIn) . " AND name = " . sqlesc($tag));
}
foreach ($toInsertNew as $tag) {
    sql_query("INSERT INTO tags (category, name, howmuch) VALUES (" . sqlesc($typeIn) . ", " . sqlesc($tag) . ", 1)");
}

/** If torrent file was replaced: rebuild files table */
if ($update_torrent) {
    sql_query("DELETE FROM files WHERE torrent = " . sqlesc($id));
    foreach ($filelist as [$fn, $sz]) {
        $sz = (int)$sz;
        sql_query("INSERT INTO files (torrent, filename, size) VALUES (" . sqlesc($id) . ", " . sqlesc($fn) . ", {$sz})");
    }
}

/** Apply main update */
$updateset = array_values(array_filter($updateset, fn($v) => $v !== null && $v !== ''));
sql_query("UPDATE torrents SET " . implode(", ", $updateset) . " WHERE id = " . sqlesc($id));

sql_query("COMMIT");

/** Log */
write_log("Торрент '" . $name . "' был отредактирован пользователем {$CURUSER['username']}", "F25B61", "torrent");

/** Redirect */
$returl = "details.php?id=" . (int)$id;
if ($returnto !== '') {
    $returl .= "&returnto=" . urlencode($returnto);
}
header("Location: $returl");
exit;
