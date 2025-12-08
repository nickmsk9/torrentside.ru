<?php
require "include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);

$action = $_GET["action"] ?? '';
$pollid = (int)($_GET["pollid"] ?? 0);

$poll = array_fill_keys(["question"], "");
for ($i = 0; $i < 20; $i++) $poll["option$i"] = "";
$poll["sort"] = "yes";
$poll["id"] = $pollid;

if ($action == "edit" && is_valid_id($pollid)) {
    $res = sql_query("SELECT * FROM polls WHERE id = $pollid") or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($res) === 0)
        stderr($tracker_lang['error'], "Опрос с таким ID не найден.");
    $poll = mysqli_fetch_assoc($res);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($action === 'edit' && !is_valid_id($pollid))
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);

    $question = htmlspecialchars($_POST["question"] ?? '');
    $options = [];
    for ($i = 0; $i < 20; $i++) {
        $options[$i] = htmlspecialchars($_POST["option$i"] ?? '');
    }
    $sort = ($_POST["sort"] ?? 'yes') === 'no' ? 'no' : 'yes';
    $returnto = htmlspecialchars($_POST["returnto"] ?? '');

    if (empty($question) || empty($options[0]) || empty($options[1]))
        stderr($tracker_lang['error'], "Заполните как минимум вопрос и первые два варианта ответа!");

    if ($pollid > 0) {
        $update = "UPDATE polls SET question = " . sqlesc($question);
        for ($i = 0; $i < 20; $i++) {
            $update .= ", option$i = " . sqlesc($options[$i]);
        }
        $update .= ", sort = " . sqlesc($sort) . " WHERE id = $pollid";
        sql_query($update) or sqlerr(__FILE__, __LINE__);
    } else {
        $values = "0, '" . get_date_time() . "', " . sqlesc($question);
        for ($i = 0; $i < 20; $i++) {
            $values .= ", " . sqlesc($options[$i]);
        }
        $values .= ", " . sqlesc($sort);
        sql_query("INSERT INTO polls VALUES($values)") or sqlerr(__FILE__, __LINE__);
    }

    $location = $returnto === "main" ? $DEFAULTBASEURL :
        ($pollid ? "$DEFAULTBASEURL/polls.php#$pollid" : $DEFAULTBASEURL);
    header("Location: $location");
    die;
}

stdhead($pollid ? "Редактировать опрос" : "Создать опрос");
begin_frame($pollid ? "Редактировать опрос" : "Создать опрос");

if (!$pollid) {
    $res = sql_query("SELECT question, added FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);
    if ($arr) {
        $hours = floor((gmtime() - sql_timestamp_to_unix_timestamp($arr["added"])) / 3600);
        $days = floor($hours / 24);
        if ($days < 3) {
            $hours -= $days * 24;
            $t = $days ? "$days день" . ($days > 1 ? "ей" : "") : "$hours час" . ($hours > 1 ? "ов" : "");
            echo "<p><font color='red'><b>Внимание: текущему опросу (<i>" . $arr["question"] . "</i>) всего $t.</b></font></p>";
        }
    }
}
?>

<h1><?=$pollid ? "Редактировать опрос" : "Создать опрос"?></h1>
<form method="post" action="makepoll.php">
  <table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">Вопрос <font color="red">*</font></td>
        <td><input name="question" size="80" maxlength="255" value="<?=htmlspecialchars($poll["question"])?>"></td></tr>
<?php
for ($i = 0; $i < 20; $i++) {
    $required = ($i < 2) ? ' <font color="red">*</font>' : '';
    echo "<tr><td class='rowhead'>Вопрос " . ($i + 1) . "$required</td><td><input name='option$i' size='80' maxlength='40' value=\"" . htmlspecialchars($poll["option$i"]) . "\"></td></tr>";
}
?>
    <tr><td class="rowhead">Сортировать</td>
        <td>
          <label><input type="radio" name="sort" value="yes" <?=$poll["sort"] !== "no" ? "checked" : ""?>> Да</label>
          <label><input type="radio" name="sort" value="no" <?=$poll["sort"] === "no" ? "checked" : ""?>> Нет</label>
        </td></tr>
    <tr><td colspan="2" align="center">
        <input type="submit" value="<?=$pollid ? 'Редактировать' : 'Создать'?>" style="height: 20pt;">
    </td></tr>
  </table>
  <p><font color="red">*</font> обязательно</p>
  <input type="hidden" name="pollid" value="<?=$poll["id"]?>">
  <input type="hidden" name="action" value="<?=$pollid ? 'edit' : 'create'?>">
  <input type="hidden" name="returnto" value="<?=htmlspecialchars($_GET["returnto"] ?? '')?>">
</form>
<?php end_frame(); ?>

<?php stdfoot(); ?>
