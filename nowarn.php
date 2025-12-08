<?php

require_once("include/bittorrent.php");

// Функция для отображения сообщения об ошибке
function bark($msg) {
    global $tracker_lang;
    stdhead();
    stdmsg($tracker_lang['error'], $msg);
    stdfoot();
    exit;
}

dbconn();
loggedinorreturn();

if (isset($_POST["nowarned"]) && $_POST["nowarned"] === "nowarned") {
    // Проверка прав доступа
    if (get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'], "Отказано в доступе.");
    }

    // Проверка, выбраны ли какие-либо действия
    if (empty($_POST["usernw"]) && empty($_POST["desact"]) && empty($_POST["delete"])) {
        bark("Вы должны выбрать пользователя для редактирования.");
    }

    // Снятие предупреждения
    if (!empty($_POST["usernw"]) && is_array($_POST["usernw"])) {
        $userIds = array_map('intval', $_POST['usernw']);
        $idList = implode(", ", $userIds);
        $msgText = "Ваше предупреждение снял " . $CURUSER['username'] . ".";
        $added = sqlesc(get_date_time());

        // Получаем текущий modcomment
        $r = sql_query("SELECT id, modcomment FROM users WHERE id IN ($idList)") or sqlerr(__FILE__, __LINE__);
        while ($user = mysqli_fetch_assoc($r)) {
            $newModComment = date("Y-m-d") . " - Предупреждение снял " . $CURUSER['username'] . ".\n" . $user['modcomment'];
            sql_query("UPDATE users SET modcomment = " . sqlesc($newModComment) . " WHERE id = " . (int)$user['id']) or sqlerr(__FILE__, __LINE__);

            // Можно отправить ЛС пользователю (если нужно раскомментировать):
            // sql_query("INSERT INTO messages (sender, receiver, msg, added) VALUES (0, " . (int)$user['id'] . ", " . sqlesc($msgText) . ", $added)") or sqlerr(__FILE__, __LINE__);
        }

        // Сброс предупреждений
        sql_query("UPDATE users SET warned = 'no', warneduntil = '0000-00-00 00:00:00' WHERE id IN ($idList)") or sqlerr(__FILE__, __LINE__);
    }

    // Деактивация пользователей
    if (!empty($_POST["desact"]) && is_array($_POST["desact"])) {
        $userIds = array_map('intval', $_POST["desact"]);
        $idList = implode(", ", $userIds);
        sql_query("UPDATE users SET enabled = 'no' WHERE id IN ($idList)") or sqlerr(__FILE__, __LINE__);
    }

    // Удаление (если будет реализовано)
    // if (!empty($_POST["delete"]) && is_array($_POST["delete"])) {
    //     ...
    // }
}

// Редирект назад
header("Location: warned.php");
exit;
