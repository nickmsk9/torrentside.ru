<?php

require_once "include/bittorrent.php";

dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка", "Доступ запрещён.");
}

// Обработка формы
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? '');
    $invites = trim($_POST["invites"] ?? '');

    if ($username === "" || $invites === "" || !is_numeric($invites)) {
        stderr("Ошибка", "Пожалуйста, заполните имя пользователя и количество приглашений (числом).");
    }

    $username_esc = sqlesc($username);
    $invites_esc = (int)$invites;

    // Обновляем количество приглашений
    $res = sql_query("UPDATE users SET invites = $invites_esc WHERE username = $username_esc") or sqlerr(__FILE__, __LINE__);

    // Проверяем, найден ли пользователь
    $res = sql_query("SELECT id FROM users WHERE username = $username_esc");
    $arr = mysqli_fetch_row($res);

    if (!$arr) {
        stderr("Ошибка", "Пользователь с таким именем не найден.");
    }

    // Редирект
    header("Location: $DEFAULTBASEURL/userdetails.php?id=" . (int)$arr[0]);
    exit;
}

stdhead("Изменение количества приглашений");
begin_frame("Изменение количества приглашений");

?>

<h1>Изменить количество приглашений пользователя</h1>

<form method="post" action="inviteadd.php">
    <table border="1" cellspacing="0" cellpadding="5">
        <tr>
            <td class="rowhead">Имя пользователя:</td>
            <td><input type="text" name="username" size="40" required></td>
        </tr>
        <tr>
            <td class="rowhead">Количество приглашений:</td>
            <td><input type="number" name="invites" size="5" min="0" required></td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                <button type="submit" class="btn">Сохранить</button>
            </td>
        </tr>
    </table>
</form>

<?php
end_frame();
stdfoot();
?>
