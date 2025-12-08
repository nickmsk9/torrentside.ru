<?php
require_once "include/bittorrent.php";

dbconn();
loggedinorreturn();

$catoptions = '';
$category = [];

$res = sql_query("SELECT id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($res)) {
    $checked = (strpos($CURUSER['notifs'], "[cat{$cat['id']}]") !== false) ? " checked" : "";
    $catoptions .= "<input type=\"checkbox\" name=\"cat[]\" value=\"{$cat['id']}\"{$checked} /> {$cat['name']}<br />";
    $category[$cat['id']] = $cat['name'];
}

stdhead("RSS");

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $link = "$DEFAULTBASEURL/rss.php";
    $query = [];

    if ($_POST['feed'] === "dl") {
        $query[] = "feed=dl";
    }

    if (isset($_POST['cat']) && is_array($_POST['cat']) && count($_POST['cat']) > 0) {
        $query[] = "cat=" . implode(',', array_map('intval', $_POST['cat']));
    }

    if ($_POST['login'] === "passkey") {
        $query[] = "passkey=" . urlencode($CURUSER['passkey']);
    }

    $queries = implode("&", $query);
    if ($queries) {
        $link .= "?$queries";
    }

    stdmsg("Успешно", "Используйте этот адрес в вашей программе для чтения RSS: <br /><a href=\"$link\">$link</a>");
    stdfoot();
    exit;
}
?>


<? begin_frame ("Генерация RSS"); ?>

<form method="POST" action="getrss.php">
<table border="1" cellspacing="1" cellpadding="5">
    <tr>
        <td class="rowhead">Категории:</td>
        <td>
            <?= $catoptions ?>
            <span class="small">
                Если вы не выберете категории для просмотра,<br />
                вам будет выдана ссылка на все категории.
            </span>
        </td>
    </tr>
    <tr>
        <td class="rowhead">Тип ссылки в RSS:</td>
        <td>
            <input type="radio" name="feed" value="web" checked /> Ссылка на страницу<br>
            <input type="radio" name="feed" value="dl" /> Ссылка на скачивание
        </td>
    </tr>
    <tr>
        <td class="rowhead">Тип логина:</td>
        <td>
            <input type="radio" name="login" value="cookie" /> Стандарт (cookies)<br>
            <input type="radio" name="login" value="passkey" checked /> Альтернативный (passkey)
        </td>
    </tr>
    <tr>
        <td colspan="2" align="center">
            <button type="submit">Сгенерировать RSS ссылку</button>
        </td>
    </tr>
</table>
</form>

<?php
end_frame();
stdfoot();
?>
