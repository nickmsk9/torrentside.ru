<?php

require "include/bittorrent.php";

dbconn();

// Проверка прав
if (get_user_class() < UC_ADMINISTRATOR) {
    stderr($tracker_lang['error'], "Нет доступа.");
}

// Обработка формы
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? '');

    if (empty($username)) {
        stderr($tracker_lang['error'], "Пожалуйста, заполните форму корректно.");
    }

    // Получаем пользователя с более низким классом
    $res = sql_query("SELECT * FROM users WHERE username = " . sqlesc($username) . 
        " AND class < " . (int)$CURUSER['class']) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) !== 1) {
        stderr($tracker_lang['error'], "Неверное имя пользователя или недостаточно прав.");
    }

    $arr = mysqli_fetch_assoc($res);
    $id = (int)$arr['id'];

    // Удаляем из users
    sql_query("DELETE FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);

    if (mysqli_affected_rows($GLOBALS["mysqli"]) !== 1) {
        stderr($tracker_lang['error'], "Невозможно удалить аккаунт.");
    }

    // Удаление из сопутствующих таблиц
    sql_query("DELETE FROM messages WHERE receiver = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM friends WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM friends WHERE friendid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM blocks WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM blocks WHERE blockid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM invites WHERE inviter = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM peers WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM simpaty WHERE fromuserid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM addedrequests WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM checkcomm WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM offervotes WHERE userid = $id") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM sessions WHERE uid = $id") or sqlerr(__FILE__, __LINE__);

    // Удаление из phpBB, если используется
    delete_phpBB2user($username, "nopasswordcheck", false);

    stderr($tracker_lang['success'], "Аккаунт <b>" . htmlspecialchars($username) . "</b> удалён.");
}

stdhead("Удалить аккаунт");

?>

<h1>Удалить аккаунт</h1>
<form method="post" action="delacctadmin.php">
    <table border="1" cellspacing="0" cellpadding="5">
        <tr><td class="rowhead">Пользователь</td><td><input size="40" name="username" required></td></tr>
        <tr><td colspan="2" align="center"><input type="submit" class="btn" value="Удалить"></td></tr>
    </table>
</form>

<?php stdfoot(); ?>
