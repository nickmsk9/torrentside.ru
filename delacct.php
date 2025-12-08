<?php

require "include/bittorrent.php";

dbconn();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = trim($_POST["password"] ?? '');

    if (empty($username) || empty($password)) {
        stderr($tracker_lang['error'], "Заполните форму корректно.");
    }

    // Поиск пользователя по имени и паролю
    $res = sql_query("SELECT id FROM users WHERE username = " . sqlesc($username) . " 
        AND passhash = md5(CONCAT(secret, CONCAT(" . sqlesc($password) . ", secret)))") or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) !== 1) {
        stderr($tracker_lang['error'], "Неверное имя пользователя или пароль. Проверьте введённую информацию.");
    }

    $arr = mysqli_fetch_assoc($res);
    $id = (int)$arr['id'];

    // Удаление пользователя и связанных записей
    $tables = [
        "users",
        "messages" => "receiver",
        "friends" => "userid",
        "friends_2" => ["friends", "friendid"],
        "blocks" => "userid",
        "blocks_2" => ["blocks", "blockid"],
        "invites" => "inviter",
        "peers" => "userid",
        "simpaty" => "fromuserid",
        "addedrequests" => "userid",
        "checkcomm" => "userid",
        "offervotes" => "userid",
        "sessions" => "uid",
    ];

    sql_query("DELETE FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);

    // Дополнительные очистки
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

    // Проверка, что удалён
    if (mysqli_affected_rows($GLOBALS["mysqli"]) !== 1) {
        stderr($tracker_lang['error'], "Невозможно удалить аккаунт.");
    }

    stderr($tracker_lang['success'], "Аккаунт удалён.");
}

stdhead("Удалить аккаунт");

?>

<h1>Удаление аккаунта</h1>
<form method="post" action="delacct.php">
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="colhead" colspan="2">Удалить аккаунт</td></tr>
    <tr><td class="rowhead">Пользователь</td><td><input size="40" name="username" required></td></tr>
    <tr><td class="rowhead">Пароль</td><td><input type="password" size="40" name="password" required></td></tr>
    <tr><td colspan="2" align="center"><input type="submit" class="btn" value="Удалить"></td></tr>
</table>
</form>

<?php stdfoot(); ?>
