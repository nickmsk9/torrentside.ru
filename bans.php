<?php

require "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

// Проверка прав доступа
if (get_user_class() < UC_MODERATOR) {
    die;
}

// Удаление IP из бана
$remove = $_GET['remove'] ?? null;
if (is_valid_id($remove)) {
    $res = sql_query("SELECT first, last FROM bans WHERE id=" . (int)$remove) or sqlerr(__FILE__, __LINE__);
    $ip = mysqli_fetch_assoc($res);
    $first = long2ip($ip["first"]);
    $last = long2ip($ip["last"]);
    sql_query("DELETE FROM bans WHERE id=" . (int)$remove) or sqlerr(__FILE__, __LINE__);

    // Логируем удаление
    $ip_range = ($first == $last) ? $first : "адреса с $first по $last";
    write_log("Бан IP адреса номер $remove ($ip_range) был убран пользователем " . htmlspecialchars($CURUSER['username']) . ".");
}

// Добавление нового бана
if ($_SERVER["REQUEST_METHOD"] === "POST" && get_user_class() >= UC_ADMINISTRATOR) {
    $first = trim($_POST["first"] ?? '');
    $last = trim($_POST["last"] ?? '');
    $comment = trim($_POST["comment"] ?? '');

    if (!$first || !$last || !$comment) {
        stderr($tracker_lang['error'], $tracker_lang['missing_form_data']);
    }

    $first_ip = ip2long($first);
    $last_ip = ip2long($last);

    if ($first_ip === false || $last_ip === false || $first_ip === -1 || $last_ip === -1) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_ip']);
    }

    $comment_esc = sqlesc(htmlspecialchars($comment));
    $added = sqlesc(get_date_time());
    $addedby = (int)$CURUSER['id'];

    sql_query("INSERT INTO bans (added, addedby, first, last, comment) VALUES ($added, $addedby, $first_ip, $last_ip, $comment_esc)") or sqlerr(__FILE__, __LINE__);

    write_log("IP адреса от " . long2ip($first_ip) . " до " . long2ip($last_ip) . " были забанены пользователем " . htmlspecialchars($CURUSER['username']) . ".");

    header("Location: " . htmlspecialchars($DEFAULTBASEURL . $_SERVER['REQUEST_URI']));
    exit;
}

gzip();

// Получение списка забаненных IP
$res = sql_query("SELECT * FROM bans ORDER BY added DESC") or sqlerr(__FILE__, __LINE__);

stdhead($tracker_lang['bans']);
begin_frame($tracker_lang['bans']);
if (mysqli_num_rows($res) === 0) {
    echo "<p align=\"center\"><b>{$tracker_lang['nothing_found']}</b></p>\n";
} else {
    begin_table();
    echo "<tr><td class=\"colhead\" colspan=\"7\">Забаненные IP</td></tr>\n";
    echo "<tr>
            <td class=\"colhead\">Добавлен</td>
            <td class=\"colhead\" align=\"left\">Первый IP</td>
            <td class=\"colhead\" align=\"left\">Последний IP</td>
            <td class=\"colhead\" align=\"left\">Кем</td>
            <td class=\"colhead\" align=\"left\">Комментарий</td>
            <td class=\"colhead\">До</td>
            <td class=\"colhead\">Снять бан</td>
          </tr>\n";

    while ($arr = mysqli_fetch_assoc($res)) {
        $addedby = (int)$arr['addedby'];
        $r2 = sql_query("SELECT username FROM users WHERE id=$addedby") or sqlerr(__FILE__, __LINE__);
        $a2 = mysqli_fetch_assoc($r2);

        $first = long2ip($arr["first"]);
        $last = long2ip($arr["last"]);
        $until = ($arr['until'] === "0000-00-00 00:00:00") ? "&nbsp;" : $arr['until'];
        $comment = htmlspecialchars($arr["comment"]);

        echo "<tr>
                <td class=\"row1\">{$arr['added']}</td>
                <td class=\"row1\" align=\"left\">$first</td>
                <td class=\"row1\" align=\"left\">$last</td>
                <td class=\"row1\" align=\"left\"><a href=\"userdetails.php?id=$addedby\">" . htmlspecialchars($a2['username']) . "</a></td>
                <td class=\"row1\" align=\"left\">$comment</td>
                <td class=\"row1\">$until</td>
                <td class=\"row1\"><a href=\"bans.php?remove={$arr['id']}\">Снять бан</a></td>
              </tr>\n";
    }
    end_table();
}

// Форма добавления нового бана
if (get_user_class() >= UC_ADMINISTRATOR) {
    echo "<br />";
    echo "<form method=\"post\" action=\"bans.php\">\n";
    begin_table();
    echo "<tr><td class=\"colhead\" colspan=\"2\">Забанить IP адрес</td></tr>";
    echo "<tr><td class=\"rowhead\">Первый IP</td><td class=\"row1\"><input type=\"text\" name=\"first\" size=\"40\"/></td></tr>\n";
    echo "<tr><td class=\"rowhead\">Последний IP</td><td class=\"row1\"><input type=\"text\" name=\"last\" size=\"40\"/></td></tr>\n";
    echo "<tr><td class=\"rowhead\">Комментарий</td><td class=\"row1\"><input type=\"text\" name=\"comment\" size=\"40\"/></td></tr>\n";
    echo "<tr><td class=\"row1\" align=\"center\" colspan=\"2\"><input type=\"submit\" value=\"Забанить\" class=\"btn\"/></td></tr>\n";
    end_table();
    echo "</form>\n";
}
end_frame();
stdfoot();
