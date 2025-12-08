<?php

require "include/bittorrent.php";

gzip();
dbconn();
loggedinorreturn();

global $CURUSER, $mysqli;

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$type = isset($_GET["type"]) ? unesc($_GET["type"]) : '';
$invite = isset($_GET["invite"]) ? $_GET["invite"] : '';

stdhead("Приглашения");
begin_frame("Приглашения");

function bark($msg) {
    stdmsg("Ошибка", $msg);
    stdfoot();
    exit;
}

if ($id === 0) {
    $id = (int)$CURUSER["id"];
}

$res = sql_query("SELECT invites FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);
$inv = mysqli_fetch_assoc($res);

$_s = ($inv["invites"] != 1) ? "ний" : "ие";

if ($type === 'new') {
    print("<form method='get' action='takeinvite.php'>");
    print("<input type='hidden' name='id' value='$id' />");
    print("<table border='1' width='100%' cellspacing='0' cellpadding='5'>");
    print("<tr class='tabletitle'><td colspan='2'><b>Создать пригласительный код (осталось {$inv['invites']} приглаше$_s)</b></td></tr>");
    print("<tr class='tableb'><td align='center' colspan='2'><input type='submit' value='Создать'></td></tr>");
    print("</table></form>");
} elseif ($type === 'del') {
    $ret = sql_query("SELECT * FROM invites WHERE invite = " . sqlesc($invite)) or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_assoc($ret);
    if ($num && $num['inviter'] == $id) {
        sql_query("DELETE FROM invites WHERE invite = " . sqlesc($invite)) or sqlerr(__FILE__, __LINE__);
        sql_query("UPDATE users SET invites = invites + 1 WHERE id = " . (int)$CURUSER['id']) or sqlerr(__FILE__, __LINE__);
        stdmsg("Успешно", "Приглашение удалено. Сейчас мы вас переадресуем на страницу приглашений...");
    } else {
        stdmsg("Ошибка", "Вам не разрешено удалять приглашения.");
    }
    header("Refresh: 3; url=invite.php?id=$id");
} else {
    if (get_user_class() <= UC_UPLOADER && $id !== $CURUSER["id"]) {
        bark("У вас нет права видеть приглашения этого пользователя.");
    }

    $rel = sql_query("SELECT COUNT(*) FROM users WHERE invitedby = $id") or sqlerr(__FILE__, __LINE__);
    [$number] = mysqli_fetch_row($rel);

    $ret = sql_query("SELECT id, username, class, email, uploaded, downloaded, status, warned, enabled, donor FROM users WHERE invitedby = $id") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_num_rows($ret);

    print("<form method='post' action='takeconfirm.php?id=$id'><table border='1' width='100%' cellspacing='0' cellpadding='5'>");
    print("<tr class='tabletitle'><td colspan='7'><b>Статус приглашенных вами</b> ($number)</td></tr>");

    if ($num === 0) {
        print("<tr class='tableb'><td colspan='7'>Еще никто вами не приглашен.</td></tr>");
    } else {
        print("<tr class='tableb'><td><b>Пользователь</b></td><td><b>Email</b></td><td><b>Раздал</b></td><td><b>Скачал</b></td><td><b>Рейтинг</b></td><td><b>Статус</b></td>");
        if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP)
            print("<td align='center'><b>Подтвердить</b></td>");
        print("</tr>");
        while ($arr = mysqli_fetch_assoc($ret)) {
            if ($arr["status"] === 'pending') {
                $user = "<td align='left'>" . htmlspecialchars($arr["username"]) . "</td>";
            } else {
                $userlink = "<a href='userdetails.php?id={$arr['id']}'>" . get_user_class_color($arr["class"], htmlspecialchars($arr["username"])) . "</a>";
                $user = "<td align='left'>$userlink" .
                    ($arr["warned"] === "yes" ? "&nbsp;<img src='pic/warned.gif' border='0' alt='Warned'>" : "") .
                    ($arr["enabled"] === "no" ? "&nbsp;<img src='pic/disabled.gif' border='0' alt='Disabled'>" : "") .
                    ($arr["donor"] === "yes" ? "&nbsp;<img src='pic/star.gif' border='0' alt='Donor'>" : "") . "</td>";
            }

            if ($arr["downloaded"] > 0) {
                $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
                $ratio = "<font color='" . get_ratio_color($ratio) . "'>$ratio</font>";
            } else {
                $ratio = $arr["uploaded"] > 0 ? "Inf." : "---";
            }

            $status = ($arr["status"] === 'confirmed')
                ? "<a href='userdetails.php?id={$arr['id']}'><font color='green'>Подтвержден</font></a>"
                : "<font color='red'>Не подтвержден</font>";

            print("<tr class='tableb'>$user<td>{$arr['email']}</td><td>" . mksize($arr["uploaded"]) . "</td><td>" . mksize($arr["downloaded"]) . "</td><td>$ratio</td><td>$status</td>");

            if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP) {
                print("<td align='center'>");
                if ($arr["status"] === 'pending') {
                    print("<input type='checkbox' name='conusr[]' value='{$arr['id']}' />");
                }
                print("</td>");
            }
            print("</tr>");
        }
    }

    if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP) {
        print("<input type='hidden' name='email' value='" . htmlspecialchars($arr["email"] ?? '') . "'>");
        print("<tr class='tableb'><td colspan='7' align='right'><input type='submit' value='Подтвердить пользователей'></form></td></tr>");
    }
    print("</table><br>");

    $rul = sql_query("SELECT COUNT(*) FROM invites WHERE inviter = $id") or sqlerr(__FILE__, __LINE__);
    [$number1] = mysqli_fetch_row($rul);

    $rer = sql_query("SELECT inviteid, invite, time_invited FROM invites WHERE inviter = $id AND confirmed = 'no'") or sqlerr(__FILE__, __LINE__);
    $num1 = mysqli_num_rows($rer);

    print("<table border='1' width='100%' cellspacing='0' cellpadding='5'>");
    print("<tr class='tabletitle'><td colspan='6'><b>Статус созданных приглашений</b> ($number1)</td></tr>");

    if ($num1 === 0) {
        print("<tr class='tableb'><td colspan='6'>На данный момент вами не создано ни одного приглашения.</td></tr>");
    } else {
        print("<tr class='tableb'><td><b>Код приглашения</b></td><td><b>Дата создания</b></td><td></td></tr>");
        while ($arr1 = mysqli_fetch_assoc($rer)) {
            print("<tr class='tableb'><td>{$arr1['invite']}</td><td>{$arr1['time_invited']}</td>");
            print("<td><a href='invite.php?invite={$arr1['invite']}&type=del'>Удалить приглашение</a></td></tr>");
        }
    }

    print("<tr class='tableb'><td colspan='7' align='center'>");
    print("<form method='get' action='invite.php'>");
    print("<input type='hidden' name='id' value='$id' />");
    print("<input type='hidden' name='type' value='new' />");
    print("<input type='submit' value='Создать приглашение'>");
    print("</form></td></tr>");
    print("</table>");
}
end_frame();
stdfoot();
?>
