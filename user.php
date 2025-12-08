<?php

require_once("include/bittorrent.php");
dbconn();
header ("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && $_SERVER["REQUEST_METHOD"] == 'POST')
{
    $id = (int)$_POST["user"];
    $act = (string)$_POST["act"];

    if (!is_valid_id($id) || empty($act))
    	die("Ошибка");


   function maketable(mysqli_result $res)
{
    // локальные переменные для уменьшения обращений к глобалям
    $out = [];
    $out[] = '<table class="tt">';
    $out[] = '<tr>'
            . '<td class="tt" style="padding:0;margin:0;width:45px;" align="center"><img src="pic/genre.gif" title="Категория" alt="" /></td>'
            . '<td class="tt"><img src="pic/release.gif" title="Название" alt="" /></td>'
            . '<td class="tt" align="center"><img src="pic/mb.gif" title="Размер" alt="" /></td>'
            . '<td class="tt" width="30" align="center"><img src="pic/seeders.gif" title="Раздают" alt="" /></td>'
            . '<td class="tt" width="30" align="center"><img src="pic/leechers.gif" title="Качают" alt="" /></td>'
            . '<td class="tt" align="center"><img src="pic/uploaded.gif" title="Раздал" alt="" /></td>'
            . '<td class="tt" align="center"><img src="pic/downloaded.gif" title="Скачал" alt="" /></td>'
            . '<td class="tt" align="center"><img src="pic/ratio.gif" title="Рейтинг" alt="" /></td>'
            . '</tr>';

    while ($arr = mysqli_fetch_assoc($res))
    {
        // приведение типов и локальные переменные
        $downloaded = (float)$arr['downloaded'];
        $uploaded = (float)$arr['uploaded'];
        if ($downloaded > 0.0) {
            $ratio_val = $uploaded / $downloaded;
            $ratio_str = number_format($ratio_val, 3);
            $ratio_col = get_ratio_color($ratio_val); // предполагается, что функция возвращает корректный цвет
            $ratio = "<font color=\"{$ratio_col}\">{$ratio_str}</font>";
        } else {
            $ratio = ($uploaded > 0) ? 'Inf.' : '---';
        }

        // минимизируем вызовы htmlspecialchars/mksize
        $catid   = (int)$arr['catid'];
        $catimage = htmlspecialchars($arr['image'], ENT_QUOTES, 'UTF-8');
        $catname  = htmlspecialchars($arr['catname'], ENT_QUOTES, 'UTF-8');
        $size     = str_replace(' ', '&nbsp;', mksize((float)$arr['size']));
        $uploaded_s   = str_replace(' ', '&nbsp;', mksize($uploaded));
        $downloaded_s = str_replace(' ', '&nbsp;', mksize($downloaded));
        $seeders = number_format((int)$arr['seeders']);
        $leechers = number_format((int)$arr['leechers']);
        $torrentname = htmlspecialchars($arr['torrentname'], ENT_QUOTES, 'UTF-8');
        $added = htmlspecialchars($arr['added'], ENT_QUOTES, 'UTF-8');
        $torrentId = (int)$arr['torrent'];

        $out[] = '<tr>'
               . "<td class=\"lol\" rowspan=\"2\" style=\"padding:0;margin:0;\"><a href=\"browse.php?cat={$catid}\"><img src=\"pic/cats/{$catimage}\" width=\"55\" height=\"55\" title=\"{$catname}\" alt=\"\" border=\"0\"/></a></td>"
               . "<td class=\"lol\" colspan=\"7\"><a href=\"details.php?id={$torrentId}&amp;hit=1\"><b>{$torrentname}</b></a></td>"
               . '</tr>';

        $out[] = '<tr>'
               . "<td class=\"lol\" align=\"left\"><font color=\"#808080\" size=\"1\">{$added}</font></td>"
               . "<td class=\"lol\" align=\"center\">{$size}</td>"
               . "<td class=\"lol\" align=\"center\">{$seeders}</td>"
               . "<td class=\"lol\" align=\"center\">{$leechers}</td>"
               . "<td class=\"lol\" align=\"center\">{$uploaded_s}</td>"
               . "<td class=\"lol\" align=\"center\">{$downloaded_s}</td>"
               . "<td class=\"lol\" align=\"center\">{$ratio}</td>"
               . '</tr>';
    }

    $out[] = '</table>';
    return implode("\n", $out);
}

    $res = @sql_query("SELECT * FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    $user = mysqli_fetch_array($res) or die("Неверный идентификатор");

    print("<style>\n");
    print("table.main td {border:1px solid #cecece;margin:0;}\n");
    print("table.main a {color:#266C8A;font-family:tahoma;}\n");
    print("</style>\n");

    if ($act == "info")
    {
        if (empty($user['info']))
            print("<div class=\"tab_error\">Пользователь не сообщил эту информацию.</div>\n");
        else
            print(format_comment($user['info']));
        die();
    }
    elseif ($act == "friends")
{
    // кешируем список друзей 60 секунд (настройте TTL по потребности)
    $cache_key = "user_friends_{$id}";
    $friends_list = false;
    if (class_exists('Memcached')) {
        $m = new Memcached();
        $m->addServer('127.0.0.1', 11211);
        $friends_list = $m->get($cache_key);
    }

    if ($friends_list === false) {
        $sql = "SELECT f.friendid AS id, u.username AS name, u.class, u.avatar, u.gender, u.title, u.donor, u.warned, u.enabled, u.last_access 
                FROM friends AS f 
                LEFT JOIN users AS u ON f.friendid = u.id 
                WHERE f.userid = ? 
                ORDER BY name";
        $stmt = mysqli_prepare($GLOBALS['mysqli'], $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $friends = [];
        while ($row = mysqli_fetch_assoc($res)) $friends[] = $row;
        if (isset($m) && is_object($m)) $m->set($cache_key, $friends, 60); // кеш на 60 секунд
        $friends_list = $friends;
        mysqli_stmt_close($stmt);
    }

    if (!empty($friends_list))
    {
        $out = [];
        $out[] = '<div id="friends">';
        foreach ($friends_list as $row)
        {
            $avatar = empty($row['avatar']) ? "pic/default_avatar.gif" : htmlspecialchars($row['avatar'], ENT_QUOTES, 'UTF-8');

            // сравниваем timestamp один раз
            $online_since = get_date_time(gmtime() - 300);
            $status = ($row['last_access'] > $online_since) ? '<font color="#008000">Онлайн</font>' : '<font color="#FF0000">Оффлайн</font>';
            $genderIcon = ($row['gender'] == "1") ? '<img src="pic/male.gif" alt="Парень" title="Парень" />' : '<img src="pic/female.gif" alt="Девушка" title="Девушка" />';

            $username_link = get_user_class_color((int)$row['class'], $row['name']); // эта функция уже форматирует имя
            $uid = (int)$row['id'];

            $out[] = '<div class="friend">';
            $out[] = "<div class=\"avatar\"><a href=\"userdetails.php?id={$uid}\"><img src=\"{$avatar}\" width=\"100\" height=\"100\" alt=\"\" /></a></div>";
            $out[] = '<div class="finfo">';
            $out[] = "<p><b>Имя:</b>&nbsp;<a href=\"userdetails.php?id={$uid}\">{$username_link}</a></p>";
            $out[] = "<p><b>Пол:</b>&nbsp;{$genderIcon}</p>";
            $out[] = "<p><b>Класс:</b>&nbsp;" . get_user_class_name((int)$row['class']) . "</p>";
            $out[] = "<p><b>Статус:</b>&nbsp;{$status}</p>";
            $out[] = '</div>';
            $out[] = '<div class="actions">';
            $out[] = "<p><a href=\"message.php?action=sendmessage&receiver={$uid}\">Отправить сообщение</a></p>";
            $out[] = "<p><a href=\"friends.php?id={$uid}\">Друзья " . get_user_class_color((int)$row['class'], $row['name']) . "</a></p>";
            if ($CURUSER['id'] == $id) $out[] = "<p><a href=\"friends.php?action=delete&type=friend&targetid={$uid}\">Убрать из друзей</a></p>";
            $out[] = '</div>';
            $out[] = '<div style="clear:both;"></div>';
            $out[] = '</div>';
        }
        $out[] = '</div>';
        print implode("\n", $out);
    }
    else
        print("<div class=\"tab_error\">У пользователя нет друзей.</div>");
    die();
}

   elseif ($act === "downloaded") {
    // только нужные колонки, корректные алиасы
    $sql = "
        SELECT
            sn.torrent       AS id,
            sn.uploaded,
            sn.seeder,
            sn.downloaded,
            sn.startdat,
            sn.completedat,
            sn.last_action,
            c.name           AS catname,
            c.image          AS catimage,
            c.id             AS catid,
            t.name           AS torrentname,
            t.seeders,
            t.leechers
        FROM snatched AS sn
        JOIN torrents   AS t ON t.id       = sn.torrent
        JOIN categories AS c ON c.id       = t.category
        WHERE sn.finished = 'yes'
          AND sn.userid  = ?
        ORDER BY sn.torrent
    ";

    /** @var mysqli $mysqli */
    $mysqli = $GLOBALS['mysqli'];
    $stmt = mysqli_prepare($mysqli, $sql);
    if (!$stmt) {
        // отладочное сообщение, чтобы сразу видеть SQL/коннект
        throw new RuntimeException('prepare failed: '.mysqli_error($mysqli));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && mysqli_num_rows($res) > 0) {
        $out = [];
        $out[] = '<table class="tt" width="100%">';
        $out[] = '<tr>'
               . '<td class="tt" style="padding:0;margin:0;width:45px;" align="center"><img src="pic/genre.gif" title="Категория" alt=""></td>'
               . '<td class="tt"><img src="pic/release.gif"  title="Название"  alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/seeders.gif"   title="Раздают" alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/leechers.gif"  title="Качают"  alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/uploaded.gif"  title="Раздал"   alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/downloaded.gif"title="Скачал"   alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/ratio.gif"     title="Ратио"    alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/start.gif"     title="Начал"    alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/end.gif"       title="Закончил" alt=""></td>'
               . '<td class="tt" width="30" align="center"><img src="pic/seeded.gif"    title="Сид?"     alt=""></td>'
               . '</tr>';

        while ($row = mysqli_fetch_assoc($res)) {
            $downloaded = (float)($row['downloaded'] ?? 0);
            $uploaded   = (float)($row['uploaded']   ?? 0);

            if ($downloaded > 0) {
                $ratio_val = $uploaded / $downloaded;
                $ratio = '<span style="color:' . get_ratio_color($ratio_val) . ';">' . number_format($ratio_val, 3) . '</span>';
            } else {
                $ratio = ($uploaded > 0) ? 'Inf.' : '---';
            }

            $uploaded_s   = '<nobr>' . mksize($uploaded)   . '</nobr>';
            $downloaded_s = '<nobr>' . mksize($downloaded) . '</nobr>';

            $seeder = (isset($row['seeder']) && $row['seeder'] === 'yes')
                ? '<span style="color:green">Да</span>'
                : '<span style="color:red">Нет</span>';

            $catImg   = htmlspecialchars((string)($row['catimage']    ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $catName  = htmlspecialchars((string)($row['catname']     ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $torName  = htmlspecialchars((string)($row['torrentname'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $catId    = (int)($row['catid'] ?? 0);
            $torId    = (int)($row['id']    ?? 0);

            $startdat    = htmlspecialchars((string)($row['startdat']    ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $completedat = htmlspecialchars((string)($row['completedat'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $cat = '<a href="browse.php?cat=' . $catId . '">'
                 . '<img width="55" height="55" src="pic/cats/' . $catImg . '" alt="' . $catName . '" border="0"></a>';

            $out[] = '<tr>'
                   .   '<td class="lol" style="padding:0;margin:0;" rowspan="2">'.$cat.'</td>'
                   .   '<td class="lol" colspan="9"><a href="details.php?id='.$torId.'&amp;hit=1"><b>'.$torName.'</b></a></td>'
                   . '</tr>';

            $out[] = '<tr>'
                   // пустая ячейка-наполнитель: убрал фикс width=500, чтобы не ломать адаптив
                   .   '<td class="lol" align="left"></td>'
                   .   '<td class="lol" align="center">'.number_format((int)($row['seeders']  ?? 0)).'</td>'
                   .   '<td class="lol" align="center">'.number_format((int)($row['leechers'] ?? 0)).'</td>'
                   .   '<td class="lol" align="center">'.$uploaded_s.'</td>'
                   .   '<td class="lol" align="center">'.$downloaded_s.'</td>'
                   .   '<td class="lol" align="center">'.$ratio.'</td>'
                   .   '<td class="lol" align="center"><nobr style="font-size:10px;">'.$startdat.'</nobr></td>'
                   .   '<td class="lol" align="center"><nobr style="font-size:10px;">'.$completedat.'</nobr></td>'
                   .   '<td class="lol" align="center">'.$seeder.'</td>'
                   . '</tr>';
        }

        $out[] = '</table>';
        echo implode("\n", $out);
    } else {
        echo '<div class="tab_error">Пользователь не скачивал торрентов.</div>';
    }

    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    die();
}


    elseif ($act == "uploaded")
    {
        $res = sql_query("SELECT t.id, t.name, t.seeders, t.added, t.leechers, t.category, c.name AS catname, c.image AS catimage, c.id AS catid FROM torrents AS t LEFT JOIN categories AS c ON t.category = c.id WHERE t.owner = $id ORDER BY t.name") or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($res) > 0)
        {
            print("<table class=\"tt\">\n" .
            "<tr><td class=\"tt\" style=\"padding:0;margin:0;width:45px;\" align=\"center\"><img src=\"pic/genre.gif\" title=\"Категория\" alt=\"\" /></td><td class=\"tt\"><img src=\"pic/release.gif\" title=\"Название\" alt=\"\" /></td><td class=\"tt\" width=\"30\" align=\"center\"><img src=\"pic/seeders.gif\" title=\"Раздают\" alt=\"\" /></td><td class=\"tt\" width=\"30\" align=\"center\"><img src=\"pic/leechers.gif\" title=\"Качают\" alt=\"\" /></td></tr>\n");
            while ($row = mysqli_fetch_assoc($res))
            {
		        $cat = "<a href=\"browse.php?cat=$row[catid]\"><img width=\"55\" height=\"55\" src=\"pic/cats/$row[catimage]\" alt=\"$row[catname]\" border=\"0\" /></a>";
                print("<tr><td class=\"lol\" rowspan=\"2\" style=\"padding:0;margin:0;\">$cat</td><td class=\"lol\" colspan=\"3\"><a href=\"details.php?id=" . $row["id"] . "&hit=1\"><b>" . $row["name"] . "</b></a></td></tr>\n");
                print("<tr><td class=\"lol\"><font color=\"#808080\" size=\"1\">" . $row["added"] . "</font></td><td class=\"lol\" align=\"center\">$row[seeders]</td><td class=\"lol\" align=\"center\">$row[leechers]</td></tr>\n");
            }
            print("</table>");
        }
        else
            print("<div class=\"tab_error\">Пользователь не загружал торрентов.</div>");
        die();
    }
    elseif ($act == "downloading")
    {
        $res = sql_query("SELECT torrent, added, uploaded, downloaded, torrents.name AS torrentname, categories.name AS catname, categories.id AS catid, size, image, category, seeders, leechers FROM peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id WHERE userid = $id AND seeder='no'") or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($res) > 0)
            print(maketable($res));
        else
            print("<div class=\"tab_error\">Пользователь ничего не качает сейчас.</div>");
        die();
    }
    elseif ($act == "uploading")
    {
        $res = sql_query("SELECT torrent, added, uploaded, downloaded, torrents.name AS torrentname, categories.name AS catname, categories.id AS catid, size, image, category, seeders, leechers FROM peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id WHERE userid = $id AND seeder='yes'") or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($res) > 0)
            print(maketable($res));
        else
            print("<div class=\"tab_error\">Пользователь ничего не раздает сейчас.</div>");
        die();
    }
   elseif ($act == "moderate")
    {
        if (get_user_class() >= UC_MODERATOR)
        {
            print("<h2>Модерирование</h2>\n");

// --- Формируем список рангов для select ---
$rangclass1 = "<option value='0'>---Выбрать ранг---</option>\n";
$res28 = mysqli_query($mysqli, "SELECT * FROM rangclass ORDER BY name") or sqlerr(__FILE__, __LINE__);
while ($rank = mysqli_fetch_assoc($res28)) {
    $selected = ($user['rangclass'] == $rank['id']) ? " selected" : "";
    $rangclass1 .= "<option value='" . (int)$rank['id'] . "'$selected>" . htmlspecialchars($rank['name']) . "</option>\n";
}

// --- Получаем данные о текущем ранге пользователя ---
$user_rangclass_id = (int)$user['rangclass'];
$res22 = mysqli_query($mysqli, "SELECT name, rangpic FROM rangclass WHERE id = $user_rangclass_id LIMIT 1") or sqlerr(__FILE__, __LINE__);
if (mysqli_num_rows($res22) === 1) {
    $arr22 = mysqli_fetch_assoc($res22);
    $rang_name = htmlspecialchars($arr22['name']);
    $rang_pic = htmlspecialchars($arr22['rangpic']);
    $rangclass = "<img src='/pic/$rang_pic' alt=\"$rang_name\" title=\"$rang_name\" style='margin-left: 5pt' align='top'></td>";
}

  print("<form method=\"post\" action=\"modtask.php\">\n");
  print("<input type=\"hidden\" name=\"action\" value=\"edituser\">\n");
  print("<input type=\"hidden\" name=\"userid\" value=\"$id\">\n");
  print("<input type=\"hidden\" name=\"returnto\" value=\"userdetails.php?id=$id\">\n");
  print("<table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
print("<tr><td class=\"rowhead\">Ник</td><td colspan=\"2\" align=\"left\"><input type=\"text\" size=\"60\" name=\"username\" value=\"" . htmlspecialchars($user["username"]) . "\"></tr>\n");  
	$avatar = htmlspecialchars($user["avatar"]);
  print("<tr><td class=\"rowhead\">Аватар</td><td colspan=\"2\" align=\"left\"><input type=\"text\" size=\"60\" name=\"avatar\" value=\"$avatar\"></tr>\n");
	// we do not want mods to be able to change user classes or amount donated...
	if ($CURUSER["class"] < UC_SYSOP)
	  print("<input type=\"hidden\" name=\"donor\" value=\"$user[donor]\">\n");
	else {
	  print("<tr><td class=\"rowhead\">Донор</td><td colspan=\"2\" align=\"left\"><input type=\"radio\" name=\"donor\" value=\"yes\"" .($user["donor"] == "yes" ? " checked" : "").">Да <input type=\"radio\" name=\"donor\" value=\"no\"" .($user["donor"] == "no" ? " checked" : "").">Нет</td></tr>\n");
	}
	if (get_user_class() == UC_MODERATOR && $user["class"] > UC_VIP)
	  printf("<input type=\"hidden\" name=\"class\" value=\"$user[class]\"\n");
	else
	{
	  print("<tr><td class=\"rowhead\">Класс</td><td colspan=\"2\" align=\"left\"><select name=\"class\">\n");
	  if (get_user_class() == UC_SYSOP)
	  	$maxclass = UC_SYSOP;
	  elseif (get_user_class() == UC_MODERATOR)
	    $maxclass = UC_VIP;
	  else
	    $maxclass = get_user_class() - 1;
	  for ($i = 0; $i <= $maxclass; ++$i)
	    print("<option value=\"$i\"" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i) . "\n");
	  print("</select></td></tr>\n");
	}
	print("<tr><td class=\"rowhead\">Сбросить день рождения</td><td colspan=\"2\" align=\"left\"><input type=\"radio\" name=\"resetb\" value=\"yes\">Да<input type=\"radio\" name=\"resetb\" value=\"no\" checked>Нет</td></tr>\n");
	$modcomment  = htmlspecialchars($user['modcomment']  ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$supportfor  = htmlspecialchars($user['supportfor']  ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

print("<tr><td class=rowhead>Убрать рейтинг</td><td class=tablea colspan=2 align=left><input type=radio name=hiderating value=yes" .($user["hiderating"]=="yes" ? " checked" : "") . ">Да <input type=radio name=hiderating value=no" .($user["hiderating"]=="no" ? " checked" : "") . ">Нет</td></tr>\n");  
	print("<tr><td class=rowhead>Поддержка</td><td colspan=2 align=left><input type=radio name=support value=yes" .($user["support"] == "yes" ? " checked" : "").">Да <input type=radio name=support value=no" .($user["support"] == "no" ? " checked" : "").">Нет</td></tr>\n");
	print("<tr><td class=rowhead>Поддержка для:</td><td colspan=2 align=left><textarea cols=60 rows=6 name=supportfor>$supportfor</textarea></td></tr>\n");
	print("<tr><td class=rowhead>История пользователя</td><td colspan=2 align=left><textarea cols=60 rows=6".(get_user_class() < UC_SYSOP ? " readonly" : " name=modcomment").">$modcomment</textarea></td></tr>\n");
	print("<tr><td class=rowhead>Добавить заметку</td><td colspan=2 align=left><textarea cols=60 rows=3 name=modcomm></textarea></td></tr>\n");
	$warned = $user["warned"] == "yes";

 	print("<tr><td class=\"rowhead\"" . (!$warned ? " rowspan=\"2\"": "") . ">Предупреждение</td>
 	<td align=\"left\" width=\"20%\">" .
  ( $warned
  ? "<input name=\"warned\" value=\"yes\" type=\"radio\" checked>Да<input name=\"warned\" value=\"no\" type=\"radio\">Нет"
 	: "Нет" ) ."</td>");

	if ($warned) {
		$warneduntil = $user['warneduntil'];
		if ($warneduntil == '0000-00-00 00:00:00')
    		print("<td align=\"center\">На неограниченый срок</td></tr>\n");
		else {
    		print("<td align=\"center\">До $warneduntil");
	    	print(" (" . mkprettytime(strtotime($warneduntil) - gmtime()) . " осталось)</td></tr>\n");
 	    }
  } else {
    print("<td>Предупредить на <select name=\"warnlength\">\n");
    print("<option value=\"0\">------</option>\n");
    print("<option value=\"1\">1 неделю</option>\n");
    print("<option value=\"2\">2 недели</option>\n");
    print("<option value=\"4\">4 недели</option>\n");
    print("<option value=\"8\">8 недель</option>\n");
    print("<option value=\"255\">Неограничено</option>\n");
    print("</select>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Комментарий в ЛС:</td></tr>\n");
    print("<tr><td colspan=\"2\" align=\"left\"><input type=\"text\" size=\"60\" name=\"warnpm\"></td></tr>");
  }


if ($CURUSER["class"]  < UC_ADMINISTRATOR)
print("<input type=hidden name=rangclass value=$user[rangclass]>\n");
else
{
print("<tr><td class=rowhead>Ранг</td><td colspan=2 align=left><select name=rangclass>\n" .$rangclass1. "\n</select></tr>\n");
}
// приведение к булевому виду
$enabled = ($user['enabled'] ?? '') === 'yes';

print(
    '<tr><td class="rowhead" rowspan="2">Включен</td>
     <td colspan="2" align="left">
       <input name="enabled" value="yes" type="radio"' . ($enabled ? ' checked' : '') . '>Да
       <input name="enabled" value="no" type="radio"' . (!$enabled ? ' checked' : '') . '>Нет
     </td></tr>'
);

if ($enabled) {
    print('<tr><td colspan="2" align="left">Причина отключения:&nbsp;
           <input type="text" name="disreason" size="60" /></td></tr>');
} else {
    print('<tr><td colspan="2" align="left">Причина включения:&nbsp;
           <input type="text" name="enareason" size="60" /></td></tr>');
}
?>
<script type="text/javascript">

function togglepic(bu, picid, formid)
{
    var pic = document.getElementById(picid);
    var form = document.getElementById(formid);
    
    if(pic.src == bu + "/pic/plus.gif")
    {
        pic.src = bu + "/pic/minus.gif";
        form.value = "minus";
    }else{
        pic.src = bu + "/pic/plus.gif";
        form.value = "plus";
    }
}


</script>
<?
  print("<tr><td class=\"rowhead\">Изменить раздачу</td><td align=\"left\"><img src=\"pic/plus.gif\" id=\"uppic\" onClick=\"togglepic('$DEFAULTBASEURL','uppic','upchange')\" style=\"cursor: pointer;\">&nbsp;<input type=\"text\" name=\"amountup\" size=\"10\" /><td>\n<select name=\"formatup\">\n<option value=\"mb\">MB</option>\n<option value=\"gb\">GB</option></select></td></tr>");
  print("<tr><td class=\"rowhead\">Изменить скачку</td><td align=\"left\"><img src=\"pic/plus.gif\" id=\"downpic\" onClick=\"togglepic('$DEFAULTBASEURL','downpic','downchange')\" style=\"cursor: pointer;\">&nbsp;<input type=\"text\" name=\"amountdown\" size=\"10\" /><td>\n<select name=\"formatdown\">\n<option value=\"mb\">MB</option>\n<option value=\"gb\">GB</option></select></td></tr>");
print("<tr><td class=rowhead>Чат Бан</td><td colspan=2 align=left><input type=radio name=schoutboxpos value=yes" .($user["schoutboxpos"]=="yes" ? " checked" : "") . ">Нет <input type=radio name=schoutboxpos value=no" .($user["schoutboxpos"]=="no" ? " checked" : "") . ">Да</td></tr>\n");  
print("<tr><td class=rowhead>В группе?</td><td colspan=2 align=left><input type=radio name=groups value=yes" .($user["groups"]=="yes" ? " checked" : "") . ">Нет <input type=radio name=groups value=no" .($user["groups"]=="no" ? " checked" : "") . ">Да</td></tr>\n");  
  print("<tr><td class=\"rowhead\">Сбросить passkey</td><td colspan=\"2\" align=\"left\"><input name=\"resetkey\" value=\"1\" type=\"checkbox\"></td></tr>\n");
  if ($CURUSER["class"] < UC_ADMINISTRATOR)
  	print("<input type=\"hidden\" name=\"deluser\">");
  else
  	print("<tr><td class=\"rowhead\">Удалить</td><td colspan=\"2\" align=\"left\"><input type=\"checkbox\" name=\"deluser\"></td></tr>");
  print("</td></tr>");
  print("<tr><td colspan=\"3\" align=\"center\"><input type=\"submit\" class=\"btn\" value=\"ОК\"></td></tr>\n");
  print("</table>\n");
  print("<input type=\"hidden\" id=\"upchange\" name=\"upchange\" value=\"plus\"><input type=\"hidden\" id=\"downchange\" name=\"downchange\" value=\"plus\">\n");
  print("</form>\n");
            die();
        }
        else
            die("У вас нет прав");
    }
    elseif ($act == "pm")
    {
        ?>
        <script language="JavaScript" type="text/javascript">
 function send_message(to, msg, subject)
{
    // Если SCEditor подключён — обновляем textarea перед чтением
    if ($("#msg").sceditor) {
        $("#msg").sceditor("instance").updateOriginal();
        msg = $("#msg").val(); // актуальное значение из редактора
    }

    var text = enBASE64(msg);
    var subj = enBASE64(subject);

    if (text === '' || subj === '') {
        alert('Вы не указали сообщение или тему.');
        return;
    }

    jQuery.post("user.php", {
        "user": to,
        "msg": text,
        "subject": subj,
        "act": "sendmessage"
    }, function (response) {
        jQuery("#operation").empty().append(response);
    });

    document.pm.msg.value = '';
    document.pm.subject.value = '';
}

        </script>
        <?
        print("<form name=\"pm\">\n");
        print("<h2>Личное сообщение</h2>\n");
        print("<table width=\"100%\" cellpadding=\"5\">\n");
        print("<tr><td>\n");
        print("<div id=\"operation\"></div>\n");
        print("<p>Тема: <input name=\"subject\" type=\"text\" style=\"width:370px;\" /></p>");
    $text = $_POST['text'] ?? '';
textbbcode("pm", "msg", $text);

       print("<p><input type=\"button\" value=\"Отправить\" onclick=\"javascript:send_message('$id', document.pm.msg.value, document.pm.subject.value);\"/>&nbsp;&nbsp;<input type=\"reset\" value=\"Отменить\" /></p>\n");
        print("</td></tr>\n");
        print("</table>\n");
        print("</form>\n");
        die();
    }

    elseif ($act == "sendmessage")
    {
        if (!empty($_POST['subject']) && !empty($_POST['msg']))
        {
            $dt = get_date_time();
            $text = sqlesc(base64_decode($_POST['msg']));
            $subject = sqlesc(base64_decode($_POST['subject']));
            sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES (" . sqlesc($CURUSER['id']) . ", $id, $subject, $text, " . sqlesc($dt) . ")") or sqlerr(__FILE__,__LINE__);
            die("<div class=\"success\">Ваше сообщение отправлено.</div>");
        }
        else
            die("Вы не ввели тему или сообщение");
    }



// ------------------- Добавление/удаление из друзей -------------------
$targetId = isset($id) ? (int)$id : (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$act = $_POST['act'] ?? $_GET['act'] ?? '';

if ($act === "addtofriends") {
    $type = isset($_POST['type']) ? trim((string)$_POST['type']) : '';
    $me   = (int)($CURUSER['id'] ?? 0);

    if ($type === '' || !$me) die("Прямой доступ закрыт");
    if (!is_valid_id($targetId)) die("<div class=\"error\">Неверный пользователь.</div>");
    if ($me === $targetId) die("<div class=\"error\">Нельзя добавлять себя в друзья.</div>");

    $link = $GLOBALS['___mysqli_ston'] ?? null;

    if ($type === "add") {
        // 1) если уже есть моя запись — проверим статус
        $res = sql_query("
            SELECT id, status
            FROM friends
            WHERE userid = ".sqlesc($me)." AND friendid = ".(int)$targetId."
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);
        $row = $res ? mysqli_fetch_assoc($res) : null;

        if ($row) {
            if ($row['status'] === 'yes')     die("<div class=\"error\">Пользователь уже ваш друг.</div>");
            if ($row['status'] === 'pending') die("<div class=\"error\">Запрос уже отправлен. Дождитесь ответа.</div>");
            if ($row['status'] === 'no')      die("<div class=\"error\">Пользователь ранее отказал.</div>");
        }

        // 2) если у него уже есть заявка ко мне — принимаем сразу (зеркальная ситуация)
        $rev = sql_query("
            SELECT id, status
            FROM friends
            WHERE userid = ".(int)$targetId." AND friendid = ".sqlesc($me)."
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);
        $revrow = $rev ? mysqli_fetch_assoc($rev) : null;

        if ($revrow && $revrow['status'] === 'pending') {
            // он уже просил дружбу — примем обе стороны атомарно
            sql_query("
                UPDATE friends
                SET status = 'yes'
                WHERE id = ".(int)$revrow['id']." AND friendid = ".sqlesc($me)." AND status = 'pending'
            ") or sqlerr(__FILE__, __LINE__);
            if (mysqli_affected_rows($link) > 0) {
                // создаём/фиксируем мою сторону как yes
                sql_query("
                    INSERT INTO friends (userid, friendid, status)
                    VALUES (".sqlesc($me).", ".(int)$targetId.", 'yes')
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ") or sqlerr(__FILE__, __LINE__);
                die("<div class=\"success\">Взаимная дружба подтверждена.</div>");
            }
        }

        // 3) обычный случай — отправляем pending
        sql_query("
            INSERT INTO friends (userid, friendid, status)
            VALUES (".sqlesc($me).", ".(int)$targetId.", 'pending')
        ") or sqlerr(__FILE__, __LINE__);

        $newid = ($link instanceof mysqli) ? mysqli_insert_id($link) : 0;

        // системное сообщение адресату
        $dt   = sqlesc(get_date_time());
        $msg  = sqlesc(
            "Пользователь [url=userdetails.php?id={$me}]{$CURUSER['username']}[/url] хочет добавить вас в друзья. ".
            "[[url=friends.php?id={$newid}&act=accept&user={$me}]Принять[/url]] ".
            "[[url=friends.php?id={$newid}&act=surrender&user={$me}]Отказать[/url]]"
        );
        $subj = sqlesc("Предложение дружбы.");
        sql_query("
            INSERT INTO messages (sender, receiver, added, msg, subject)
            VALUES (0, ".(int)$targetId.", $dt, $msg, $subj)
        ") or sqlerr(__FILE__, __LINE__);

        die("<div class=\"success\">Запрос отправлен. Дождитесь ответа пользователя.</div>");
    }
    elseif ($type === "delete") {
        // Удаляем обе стороны (если были)
        sql_query("
            DELETE FROM friends
            WHERE (userid = ".sqlesc($me)." AND friendid = ".(int)$targetId.")
               OR (userid = ".(int)$targetId." AND friendid = ".sqlesc($me).")
        ") or sqlerr(__FILE__, __LINE__);

        $dt   = sqlesc(get_date_time());
        $msg  = sqlesc("Пользователь [url=userdetails.php?id={$me}]{$CURUSER['username']}[/url] удалил вас из друзей.");
        $subj = sqlesc("Отмена дружбы.");
        sql_query("
            INSERT INTO messages (sender, receiver, added, msg, subject)
            VALUES (0, ".(int)$targetId.", $dt, $msg, $subj)
        ") or sqlerr(__FILE__, __LINE__);

        die("<div class=\"success\">Пользователь удалён из друзей.</div>");
    }

    die();
}
else
    die("Прямой доступ запрещен");
}
?>