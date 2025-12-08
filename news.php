<?php
require "include/bittorrent.php";

dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
    stderr($tracker_lang['error'], "Нет доступа.");
}

$action = $_GET["action"] ?? '';
$warning = '';

/* --- Удаление новости --- */
if ($action === 'delete') {
    $newsid = (int) ($_GET["newsid"] ?? 0);
    if ($newsid <= 0) {
        stderr($tracker_lang['error'], "Неверный ID новости.");
    }

    $returnto = htmlspecialchars($_GET["returnto"] ?? '');
    $sure = $_GET["sure"] ?? '';

    if (!$sure) {
        stderr("Удалить новость", "Вы действительно хотите удалить новость?<br><a href=\"?action=delete&newsid=$newsid&returnto=$returnto&sure=1\">Нажмите сюда</a>, если уверены.");
    }

    sql_query("DELETE FROM news WHERE id = $newsid") or sqlerr(__FILE__, __LINE__);

    if ($returnto !== '') {
        header("Location: $returnto");
        die();
    }

    $warning = "Новость <b>успешно</b> удалена";
}

/* --- Добавление новости --- */


if ($action === 'add') {
    $subject = trim($_POST["subject"] ?? '');
    $body = trim($_POST["body"] ?? '');

    if ($subject === '') {
        stderr($tracker_lang['error'], "Тема не может быть пустой.");
    }
    if ($body === '') {
        stderr($tracker_lang['error'], "Текст не может быть пустым.");
    }

    $added = sqlesc(get_date_time());

    sql_query("INSERT INTO news (userid, added, body, subject) VALUES (" . (int)$CURUSER['id'] . ", $added, " . sqlesc($body) . ", " . sqlesc($subject) . ")") or sqlerr(__FILE__, __LINE__);

    $warning = "Новость <b>успешно</b> добавлена";
}

/* --- Редактирование новости --- */
if ($action === 'edit') {
    $newsid = (int) ($_GET["newsid"] ?? 0);
    if ($newsid <= 0) {
        stderr($tracker_lang['error'], "Неверный ID новости.");
    }

    $res = sql_query("SELECT * FROM news WHERE id = $newsid") or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($res) !== 1) {
        stderr($tracker_lang['error'], "Новость не найдена.");
    }

    $arr = mysqli_fetch_assoc($res);
    $returnto = htmlspecialchars($_GET['returnto'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST["subject"] ?? '');
        $body = trim($_POST["body"] ?? '');

        if ($subject === '') {
            stderr($tracker_lang['error'], "Тема не может быть пустой.");
        }
        if ($body === '') {
            stderr($tracker_lang['error'], "Текст не может быть пустым.");
        }

        sql_query("UPDATE news SET subject = " . sqlesc($subject) . ", body = " . sqlesc($body) . " WHERE id = $newsid") or sqlerr(__FILE__, __LINE__);

        if (!empty($_POST['returnto'])) {
            header("Location: " . htmlspecialchars($_POST['returnto']));
            die();
        }

        $warning = "Новость <b>успешно</b> отредактирована";
    } else {
        stdhead("Редактирование новости");
		  begin_frame("Редактирование новости");
        print("<form method='post' action='?action=edit&newsid=$newsid'>");
        print("<input type='hidden' name='returnto' value='$returnto'>");
        print("<table class='main' border='1' cellspacing='0' cellpadding='5'>");
        print("<tr><td class='colhead'>Редактирование новости</td></tr>");
        print("<tr><td>Тема: <input type='text' name='subject' size='50' maxlength='70' value=\"" . htmlspecialchars($arr["subject"]) . "\"></td></tr>");
        print("<tr><td style='padding:0;'>");
        textbbcode("news", "body", htmlspecialchars($arr["body"]), "0");
        print("</td></tr>");
        print("<tr><td align='center'><input type='submit' value='Отредактировать'></td></tr>");
        print("</table>");
        print("</form>");
		end_frame();
        stdfoot();
        die();
    }
}

/* --- Главная страница управления --- */
stdhead("Новости");

begin_frame("Новости");

if ($warning) {
    print("<p><font size='-3'>($warning)</font></p>");
}

print("<form method='post' action='?action=add'>");
print("<table class='main' border='1' cellspacing='0' cellpadding='5'>");
print("<tr><td class='colhead'>Добавить новость</td></tr>");
print("<tr><td>Тема: <input type='text' name='subject' size='50' maxlength='40'></td></tr>");
print("<tr><td style='padding:0;'>");
textbbcode("news", "body", "", "0");
print("</td></tr>");
print("<tr><td align='center'><input type='submit' class='btn' value='Добавить'></td></tr>");
print("</table>");
print("</form><br><br>");

$res = sql_query("SELECT * FROM news ORDER BY added DESC") or sqlerr(__FILE__, __LINE__);
if (mysqli_num_rows($res) > 0) {
	 end_frame();
    begin_main_frame();
    begin_frame();

    while ($row = mysqli_fetch_assoc($res)) {
        $newsid = $row["id"];
        $subject = $row["subject"];
        $body = $row["body"];
        $userid = (int) $row["userid"];
        $added = $row["added"] . " GMT (" . get_elapsed_time(sql_timestamp_to_unix_timestamp($row["added"])) . " назад)";

        $res2 = sql_query("SELECT username, donor FROM users WHERE id = $userid") or sqlerr(__FILE__, __LINE__);
        $user = mysqli_fetch_assoc($res2);
	begin_main_frame();
	
begin_frame();
        $postername = $user["username"] ?? '';
        $by = $postername
            ? "<a href='userdetails.php?id=$userid'><b>$postername</b></a>" . ($user["donor"] === "yes" ? " <img src='pic/star.gif' alt='Donor'>" : '')
            : "Неизвестно [$userid]";
        
        print("<p class='sub'><table class='embedded'><tr><td>");
        print("Добавлена $added — $by");
        print(" — [<a href='?action=edit&newsid=$newsid'><b>Редактировать</b></a>]");
        print(" — [<a href='?action=delete&newsid=$newsid'><b>Удалить</b></a>]");
        print("</td></tr></table></p>");
		end_main_frame();
end_frame();
        begin_frame(true);
        print("<tr><td><b>" . htmlspecialchars($subject) . "</b></td></tr>");
        print("<tr><td class='comment'>" . format_comment($body) . "</td></tr>");
       
    }

    end_frame();
    end_main_frame();
} else {
    stdmsg("Извините", "Новостей пока нет.");
}

stdfoot();
