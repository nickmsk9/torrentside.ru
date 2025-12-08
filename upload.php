<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';

dbconn(false);
loggedinorreturn();
parked();

stdhead($tracker_lang['upload_torrent']);
begin_frame('Загрузить торрент на TorrentSide');

/* ---------- guard: доступ ---------- */
if (get_user_class() < UC_USER) {
    stdmsg($tracker_lang['error'], $tracker_lang['upget']);
    stdfoot();
    exit;
}

/* ---------- helpers ---------- */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------- Memcached (lazy) ---------- */
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached('tbdev-persistent');
    if (empty($memcached->getServerList())) {
        $memcached->addServer('127.0.0.1', 11211);
    }
    $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
}

/* ---------- passkey (генерация при отсутствии/битом значении) ---------- */
if (empty($CURUSER['passkey']) || strlen($CURUSER['passkey']) !== 32) {
    // 32 hex-символа, безопаснее чем md5 от данных пользователя
    $CURUSER['passkey'] = bin2hex(random_bytes(16));
    sql_query("UPDATE users SET passkey = " . sqlesc($CURUSER['passkey']) . " WHERE id = " . (int)$CURUSER['id'] . " LIMIT 1");
}

/* ---------- входной параметр type ---------- */
$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]) ?? 0;

/* ---------- быстрые ссылки-шаблоны ---------- */
echo <<<HTML
<div align="center">
    <p><span style="color: green; font-weight: bold;">Вы можете выбрать один из шаблонов раздач.</span></p>
    <table width="500" cellspacing="0" align="center" class="menu">
        <tr>
            <td class="embedded">
                <form method="get" action="film.php"><input type="submit" value="Фильмы/Видео" style="height:20px;width:100px"></form>
            </td>
            <td class="embedded">
                <form method="get" action="music.php"><input type="submit" value="Музыка/Аудио" style="height:20px;width:100px"></form>
            </td>
            <td class="embedded">
                <form method="get" action="game.php"><input type="submit" value="Игры" style="height:20px;width:100px"></form>
            </td>
            <td class="embedded">
                <form method="get" action="soft.php"><input type="submit" value="Софт" style="height:20px;width:100px"></form>
            </td>
        </tr>
    </table>
</div>
HTML;

/* ---------- если категория не выбрана: список категорий ---------- */
if ($type === 0) {
    $MC_KEY_CATS = 'cats:all:v1';
    $uploadtypes = $memcached->get($MC_KEY_CATS);
    if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($uploadtypes)) {
        $q = sql_query("SELECT id, name FROM categories ORDER BY id ASC");
        $uploadtypes = [];
        while ($r = mysqli_fetch_assoc($q)) {
            $uploadtypes[] = ['id' => (int)$r['id'], 'name' => $r['name']];
        }
        $memcached->set($MC_KEY_CATS, $uploadtypes, 300);
    }

    echo '<br>';
    echo '<p style="text-align:center"><span style="color: green; font-weight: bold;">Или загрузить раздачу по общему шаблону выбрав категорию.</span></p>';
    echo '<table border="1" width="100%">';
    foreach ($uploadtypes as $uploadtype) {
        $id = (int)$uploadtype['id'];
        $name = h($uploadtype['name']);
        echo "<tr><td class='lol' align='center'><a href=\"upload.php?type={$id}\">{$name}</a></td></tr>";
    }
    echo '</table>';

    stdfoot();
    exit;
}

/* ---------- форма загрузки ---------- */
echo <<<HTML
<div align="center">
<p><span style="color: green; font-weight: bold;">После загрузки торрента, вам нужно будет скачать торрент и поставить качаться в папку где лежат оригиналы файлов.</span></p>

<form name="upload" enctype="multipart/form-data" action="takeupload.php" id="upload" method="post" autocomplete="off">
    <input type="hidden" name="MAX_FILE_SIZE" value="{$max_torrent_size}" />
    <table border="1" cellspacing="0" cellpadding="5">
        <tr><td class="colhead" colspan="2">{$tracker_lang['upload_torrent']}</td></tr>
HTML;

/* announce, файл, имя, обложка */
tr($tracker_lang['announce_url'], h($announce_urls[0] ?? ''), 1);
tr($tracker_lang['torrent_file'], "<input type='file' name='tfile' size='80' accept='.torrent' required>", 1);
tr($tracker_lang['torrent_name'], "<input type='text' name='name' size='80' maxlength='255' required /><br />(" . h($tracker_lang['taken_from_torrent']) . ")", 1);
tr($tracker_lang['images'], "<input type='url' name='image0' size='80' placeholder='https://...' pattern='https?://.+'><br/><b>Укажите URL-адрес картинки</b><br/>Если вы не знаете, куда загрузить картинку, воспользуйтесь бесплатными хостингами: <a href='http://radikal.ru/'>Radikal</a>, <a href='http://ipicture.ru'>iPicture</a>", 1);
print("</td></tr>\n");

/* описание */
print("<tr><td class='rowhead' style='padding: 10px'>" . h($tracker_lang['description']) . "</td><td class='lol'>");
textbbcode("upload", "descr");
print("</td></tr>\n");

/* скриншоты */
tr(
    "Скриншоты",
    "<input type='url' name='image1' size='80' placeholder='https://...'><br/>" .
    "<input type='url' name='image2' size='80' placeholder='https://...'><br/>" .
    "<input type='url' name='image3' size='80' placeholder='https://...'><br/>" .
    "<input type='url' name='image4' size='80' placeholder='https://...'>",
    1
);

/* категории (селект) — кэшируем отдельно, сортировка по name */
$MC_KEY_CATS_NAME = 'cats:byname:v1';
$catsByName = $memcached->get($MC_KEY_CATS_NAME);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($catsByName)) {
    $res = sql_query("SELECT id, name FROM categories ORDER BY name ASC");
    $catsByName = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $catsByName[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $memcached->set($MC_KEY_CATS_NAME, $catsByName, 300);
}
$s = "<select name=\"type\" required>\n<option value=\"0\">(" . h($tracker_lang['choose']) . ")</option>\n";
foreach ($catsByName as $row) {
    $id = (int)$row['id'];
    $nm = h($row['name']);
    $sel = ($id === $type) ? ' selected' : '';
    $s .= "<option value=\"{$id}\"{$sel}>{$nm}</option>\n";
}
$s .= "</select>\n";
tr($tracker_lang['type'], $s, 1);

/* теги — кэш на основании выбранного type */
echo <<<HTML
<script type="text/javascript" src="js/tagto.js"></script>
<script type="text/javascript">
(function($){
    $(document).ready(function(){
        $("#from").tagTo("#tags");
    });
})(jQuery);
</script>
HTML;

$MC_KEY_TAGS = 'tags:cat:' . $type . ':v1';
$tags = $memcached->get($MC_KEY_TAGS);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($tags)) {
    $tags = taggenrelist($type) ?: [];
    // Нормализуем к простому массиву строк
    $tags = array_values(array_map(static fn($r) => (string)($r['name'] ?? ''), $tags));
    $memcached->set($MC_KEY_TAGS, $tags, 300);
}
$tagsHtml = '<input type="text" id="tags" name="tags">';
$tagsHtml .= '<div id="from">';
if (empty($tags)) {
    $tagsHtml .= "Нет тегов для данной категории. Вы можете добавить собственные.";
} else {
    foreach ($tags as $t) {
        $tagsHtml .= "<a href='#'>" . h($t) . "</a> ";
    }
}
$tagsHtml .= '</div>';
tr("Тэги", $tagsHtml, 1);

/* скидка (free) */
$prc = "<select name=\"free\">";
for ($i = 0; $i <= 10; $i++) {
    $val = $i * 10;
    $prc .= "<option value=\"{$val}\">{$val}</option>";
}
$prc .= "</select> процентов";
tr("Скидка", $prc, 1);

/* submit */
echo "<tr><td class=\"lol\" align=\"center\" colspan=\"2\"><input type=\"submit\" class=\"btn\" value=\"" . h($tracker_lang['upload']) . "\" /></td></tr>";
echo "</table></form></div>";

end_frame();
stdfoot();
