<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';

dbconn(false);
loggedinorreturn();

// --- CSRF init ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



parked();

stdhead('Загрузить Фильм на TorrentSide');
begin_frame('Загрузить Фильм на TorrentSide');

/* ---- доступ ---- */
if (get_user_class() < UC_USER) {
    stdmsg($tracker_lang['error'], $tracker_lang['upget']);
    stdfoot();
    exit;
}

/* ---- helpers ---- */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---- Memcached (lazy) ---- */
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached('tbdev-persistent');
    if (empty($memcached->getServerList())) {
        $memcached->addServer('127.0.0.1', 11211);
    }
    $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
}

/* ---- passkey (генерация при отсутствии/битом значении) ---- */
if (empty($CURUSER['passkey']) || strlen((string)$CURUSER['passkey']) !== 32) {
    $CURUSER['passkey'] = bin2hex(random_bytes(16)); // 32 hex-символа
    sql_query("UPDATE users SET passkey = " . sqlesc($CURUSER['passkey']) . " WHERE id = " . (int)$CURUSER['id'] . " LIMIT 1");
}

/* ---- быстрые ссылки-шаблоны ---- */
?>
<div align="center">
    <p><span style="color: green; font-weight: bold;">Вы можете выбрать один из шаблонов раздач.</span></p>
    <table width="500" cellspacing="0" align="center" class="menu">
        <tr>
            <td class="embedded"><form method="get" action="film.php"><input type="submit" value="Фильмы/Видео" style="height: 20px; width: 100px"></form></td>
            <td class="embedded"><form method="get" action="music.php"><input type="submit" value="Музыка/Аудио"  style="height: 20px; width: 100px"></form></td>
            <td class="embedded"><form method="get" action="game.php"><input type="submit" value="Игры" style="height: 20px; width: 100px"></form></td>
            <td class="embedded"><form method="get" action="soft.php"><input type="submit" value="Софт" style="height: 20px; width: 100px"></form></td>
        </tr>
    </table>
</div>
<?php

/* ---- входной параметр type ---- */
$allowedIds = [11, 13, 14, 15, 20, 23, 26, 27, 31]; // как у тебя
$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 0]]);

/* ---- если категория не выбрана: показать список разрешённых ---- */
if ($type === null) {
    $MC_KEY_CATS = 'cats:film_allow:v1';
    $cats = $memcached->get($MC_KEY_CATS);
    if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($cats)) {
        $idList = implode(',', array_map('intval', $allowedIds));
        $res = sql_query("SELECT id, name FROM categories WHERE id IN ($idList) ORDER BY id ASC");
        $cats = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cats[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
        $memcached->set($MC_KEY_CATS, $cats, 300);
    }

    echo '<br><table border="1" width="100%">';
    echo '<center><p><span style="color: green; font-weight: bold;">Или загрузите по общему шаблону, выбрав категорию:</span></p></center>';
    foreach ($cats as $c) {
        echo "<tr><td class='lol' align='center'><a href=\"film.php?type={$c['id']}\">" . h($c['name']) . "</a></td></tr>";
    }
    echo '</table>';

    stdfoot();
    exit;
}

/* ---- валидация выбранной категории ---- */
$descrtype = (int)$type;
if ($descrtype <= 0 || !in_array($descrtype, $allowedIds, true)) {
    stdmsg($tracker_lang['error'], 'Неверный ID категории');
    stdfoot();
    exit;
}

/* ---- форма ---- */
?>
<div align="center">
<p><span style="color: green; font-weight: bold;">После загрузки торрента, вам нужно будет скачать его и начать сидировать из папки с оригинальными файлами.</span></p>

<form id="upload" name="upload" enctype="multipart/form-data" action="takeuploadfilm.php" method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$max_torrent_size ?>" />
<table border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="2"><?= h($tracker_lang['upload_torrent']) ?></td></tr>
<?php

/* announce, файл, названия */
tr($tracker_lang['announce_url'], h($announce_urls[0] ?? ''), 1);
tr($tracker_lang['torrent_file'], '<input type="file" name="tfile" size="80" accept=".torrent">', 1);
tr('Русское название', '<input type="text" name="name" size="80" /><br>(например - <b>Матрица</b>)', 1);
tr('Оригинальное название', '<input type="text" name="origname" size="80" /><br>(например - <b>Matrix</b>)', 1);
tr($tracker_lang['images'], '<input type="text" name="image0" size="80"><br><b>Укажите URL-адрес картинки</b><br>Если не знаете, куда загрузить — используйте <a href="http://radikal.ru/">Radikal</a> или <a href="http://ipicture.ru">iPicture</a>', 1);
tr('Год выхода', '<input type="text" name="year" size="4" />', 1);
tr('Жанр', '<input type="text" name="janr" size="40" />', 1);
tr('Режиссёр', '<input type="text" name="director" size="40" />', 1);
tr('В ролях', '<input type="text" name="roles" size="100" />', 1);

/* Перевод */
$perevod = [
    'Любительский (Одноголосный)', 'Любительский (Многоголосный)', 'Любительский (Гоблин)',
    'Профессиональный (Одноголосный)', 'Профессиональный (Многоголосный)', 'Профессиональный (Дублированный)',
    'Отсутствует', 'Не требуется'
];
$pr = '<select name="perevod"><option value="0">(Выбрать)</option>';
foreach ($perevod as $val) {
    $pr .= '<option value="' . h($val) . '">' . h($val) . '</option>';
}
$pr .= '</select>';
tr('Перевод', $pr, 1);

/* Описание */
echo "</td></tr><tr><td class='rowhead' style='padding: 10px'>Сюжет фильма:</td><td class='lol'>";
textbbcode('upload', 'descr');
echo '</td></tr>';

/* Скриншоты */
tr('Скриншоты',
   '<input type="text" name="image1" size="80"><br><input type="text" name="image2" size="80"><br><input type="text" name="image3" size="80"><br><input type="text" name="image4" size="80">', 1);

/* Кем выпущено / длительность */
tr('Кем выпущено', '<input type="text" name="publisher" size="40" />', 1);
tr('Продолжительность', '<input type="text" name="time" size="40" />', 1);

/* Качество */
$kach = ['DVDRip','DVD5','DVD9','HDTV','TVRip','SATRip','TeleCine','TeleSync','CAMRip','VHSRip','DVDScreener','BDRip'];
$k = '<select name="kachestvo"><option value="0">(Выбрать)</option>';
foreach ($kach as $val) {
    $k .= '<option value="' . h($val) . '">' . h($val) . '</option>';
}
$k .= '</select>';
tr('Качество', $k, 1);

/* Формат */
$format = ['AVI','DVD Video','OGM','MKV','WMV','MPEG'];
$fr = '<select name="format"><option value="0">(Выбрать)</option>';
foreach ($format as $val) {
    $fr .= '<option value="' . h($val) . '">' . h($val) . '</option>';
}
$fr .= '</select>';
tr('Формат', $fr, 1);

/* Видео / Аудио */
tr('Видео', 'Разрешение: <input type="text" name="resolution" size="9" /> Кодек: <input type="text" name="videocodec" size="6" /> Битрейт: <input type="text" name="videobitrate" size="6" />', 1);
tr('Аудио', 'Кодек: <input type="text" name="audiocodec" size="6" /> Битрейт: <input type="text" name="audiobitrate" size="6" />', 1);

/* Категория (точечный запрос + LIMIT 1) */
$MC_KEY_CAT = 'cat:' . $descrtype . ':v1';
$catName = $memcached->get($MC_KEY_CAT);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_string($catName)) {
    $res = sql_query("SELECT name FROM categories WHERE id = " . (int)$descrtype . " LIMIT 1");
    $row = mysqli_fetch_row($res);
    $catName = (string)($row[0] ?? '');
    $memcached->set($MC_KEY_CAT, $catName, 300);
}
$s = '<select name="type"><option value="' . (int)$descrtype . '" selected>' . h($catName) . '</option></select>';
tr($tracker_lang['type'], $s, 1);
?>

<style type="text/css" media="screen">
    code {font:99.9%/1.2 consolas,'courier new',monospace;}
    #from a {margin:2px;font-weight:normal;}
    #tags {width:36em;}
    a.selected {background:#c00; color:#fff;}
    .addition {margin-top:2em; text-align:right;}
</style>

<script src="js/tagto.js"></script>
<script>
(function($){
    $(function(){
        $("#from").tagTo("#tags");
    });
})(jQuery);
</script>

<?php
/* Теги (кэш по категории) */
$MC_KEY_TAGS = 'tags:cat:' . $descrtype . ':v1';
$tags = $memcached->get($MC_KEY_TAGS);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($tags)) {
    $raw = taggenrelist($descrtype) ?: [];
    $tags = array_values(array_map(static fn($r) => (string)($r['name'] ?? ''), $raw));
    $memcached->set($MC_KEY_TAGS, $tags, 300);
}
$tagsHtml = '<input type="text" id="tags" name="tags">';
$tagsHtml .= '<div id="from">';
if (empty($tags)) {
    $tagsHtml .= 'Нет тегов для данной категории. Вы можете добавить собственные.';
} else {
    foreach ($tags as $t) {
        $tagsHtml .= '<a href="#">' . h($t) . '</a> ';
    }
}
$tagsHtml .= '</div>';
tr('Тэги', $tagsHtml, 1);

/* Скидка */
if (get_user_class() >= UC_USER) {
    $prc = '<select name="free">';
    for ($i = 0; $i <= 10; ++$i) {
        $val = $i * 10;
        $prc .= '<option value="' . $val . '">' . $val . '</option>';
    }
    $prc .= '</select> процентов';
    tr('Скидка', $prc, 1);
}
?>
<tr><td class="lol" align="center" colspan="2">
    <input type="submit" class="btn" value="<?= h($tracker_lang['upload']) ?>" />
</td></tr>
</table>
</form>

<?php
end_frame();
stdfoot();
