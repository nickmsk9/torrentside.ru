<?php

require_once("include/bittorrent.php");
dbconn();
header ("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);
header ("Cache-control: no-store");
header ("Pragma: no-cache");

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && $_SERVER["REQUEST_METHOD"] == 'POST')
{
    $id = (int)$_POST["torrent"];
    $act = (string)$_POST["act"];
	
	    if (!is_valid_id($id) || empty($act))
    	die("Ошибка");
		
// Определение BitTorrent-клиента по HTTP_USER_AGENT и peer_id
function getagent(string $httpagent, string $peer_id = ""): string {
    // Проверка по HTTP_USER_AGENT
    $patterns = [
        // Популярные клиенты
        ['/qBittorrent\/([\d.]+)/i', 'qBittorrent/%s'],
        ['/uTorrent\/([\d.]+)/i', 'µTorrent/%s'],
        ['/BitTorrent\/([\d.]+)/i', 'BitTorrent/%s'],
        ['/Transmission\/([\d.]+)/i', 'Transmission/%s'],
        ['/Deluge\/([\d.]+)/i', 'Deluge/%s'],
        ['/libtorrent\/([\d.]+)/i', 'libtorrent/%s'],
        ['/rTorrent\/([\d.]+)/i', 'rTorrent/%s'],
        ['/BiglyBT\/([\d.]+)/i', 'BiglyBT/%s'],
        ['/Tixati\/([\d.]+)/i', 'Tixati/%s'],
        ['/WebTorrent\/([\d.]+)/i', 'WebTorrent/%s'],
        ['/Flood\/([\d.]+)/i', 'Flood/%s'],

        // Старые, но возможные клиенты
        ['/Azureus ([\d._A-Z]+)/i', 'Azureus/%s'],
        ['/BitComet\/([\d.]+)/i', 'BitComet/%s'],
        ['/BitLord\/([\d.]+)/i', 'BitLord/%s'],
        ['/Shareaza\/([\d.]+)/i', 'Shareaza/%s'],
        ['/ABC\/([\d.]+)/i', 'ABC/%s'],
        ['/MLDonkey\/([\d.]+)/i', 'MLDonkey/%s'],
        ['/Tribler\/([\d.]+)/i', 'Tribler/%s'],
        ['/BitTorrent Plus!\/([\d.]+)/i', 'BitTorrent Plus!/%s'],
        ['/XBT Client\/([\d.]+)/i', 'XBT Client/%s'],
        ['/eXeem[ \/]*([\d.]+)/i', 'eXeem/%s'],
    ];

    foreach ($patterns as [$pattern, $format]) {
        if (preg_match($pattern, $httpagent, $matches)) {
            return sprintf($format, $matches[1]);
        }
    }

    // Проверка по peer_id
    $peer_patterns = [
        ['/^-qB(\d{2})(\d{2})-/', 'qBittorrent/%d.%d'],
        ['/^-UT(\d{1,2})(\d{2})-/', 'µTorrent/%d.%02d'],
        ['/^-TR(\d{1,2})(\d{2})-/', 'Transmission/%d.%02d'],
        ['/^-AZ(\d{1,2})(\d{2})-/', 'Azureus/%d.%02d'],
        ['/^-LT(\d{1,2})(\d{2})-/', 'libtorrent/%d.%02d'],
        ['/^-DE(\d{1,2})(\d{2})-/', 'Deluge/%d.%02d'],
        ['/^-BT(\d{2})(\d{2})-/', 'BitTorrent/%d.%02d'],
        ['/^-BC0(\d{2})-/', 'BitComet/0.%d'],
        ['/^-BL(\d{2})(\d{2})-/', 'BitLord/%d.%02d'],
    ];

    foreach ($peer_patterns as [$pattern, $format]) {
        if (preg_match($pattern, $peer_id, $m)) {
            return sprintf($format, $m[1], $m[2]);
        }
    }

    // Проверка по известным строкам
    if (str_starts_with($peer_id, 'exbc')) {
        return 'BitComet';
    }

    if (str_starts_with($peer_id, 'M')) {
        return 'Mainline';
    }

    if (str_starts_with($peer_id, '-BW')) {
        return 'BitWombat';
    }

    if (str_starts_with($peer_id, '-FG')) {
        return 'FlashGet';
    }

    // Неизвестный клиент
    return 'Unknown';
}

function dltable($name, $arr, $torrent)
{

        global $CURUSER, $tracker_lang;
        $s = "<center><h1>" . count($arr) . " $name</h1></center>\n";
        if (!count($arr))
                return $s;
        $s .= "\n";
        $s .= "<table width=100% class=main border=1 cellspacing=0 cellpadding=5>\n";
        $s .= "<tr><td class=colhead>".$tracker_lang['user']."</td>" .
          "<td class=colhead align=center>".$tracker_lang['port_open']."</td>".
          "<td class=colhead align=right>".$tracker_lang['uploaded']."</td>".
          "<td class=colhead align=right>".$tracker_lang['ul_speed']."</td>".
          "<td class=colhead align=right>".$tracker_lang['downloaded']."</td>" .
          "<td class=colhead align=right>".$tracker_lang['dl_speed']."</td>" .
          "<td class=colhead align=right>".$tracker_lang['ratio']."</td>" .
          "<td class=colhead align=right>".$tracker_lang['completed']."</td>" .
          "<td class=colhead align=right>".$tracker_lang['connected']."</td>" .
          "<td class=colhead align=right>".$tracker_lang['idle']."</td>" .
          "<td class=colhead align=left>".$tracker_lang['client']."</td></tr>\n";
        $now = time();
        $moderator = (isset($CURUSER) && get_user_class() >= UC_MODERATOR);
		$mod = get_user_class() >= UC_MODERATOR;
        foreach ($arr as $e) {
                // user/ip/port
                // check if anyone has this ip
                $s .= "<tr>\n";
                if ($e["username"])
                  $s .= "<td class=\"lol\"><a href=\"userdetails.php?id=$e[userid]\"><b>".get_user_class_color($e["class"], $e["username"])."</b></a>".($mod ? "&nbsp;[<span title=\"{$e["ip"]}\" style=\"cursor: pointer\">IP</span>]" : "")."</td>\n";
                else
                  $s .= "<td>" . ($mod ? $e["ip"] : preg_replace('/\.\d+$/', ".xxx", $e["ip"])) . "</td>\n";
                $secs = max(10, ($e["la"]) - $e["pa"]);
                $revived = $e["revived"] == "yes";
        		$s .= "<td class=\"lol\" align=\"center\">" . ($e[connectable] == "yes" ? "<span style=\"color: green; cursor: help;\" title=\"Порт открыт. Этот пир может подключатся к любому пиру.\">".$tracker_lang['yes']."</span>" : "<span style=\"color: red; cursor: help;\" title=\"Порт закрыт. Рекомендовано проверить настройки Firwewall'а.\">".$tracker_lang['no']."</span>") . "</td>\n";
                $s .= "<td class=\"lol\" align=\"right\"><nobr>" . mksize($e["uploaded"]) . "</nobr></td>\n";
                $s .= "<td class=\"lol\" align=\"right\"><nobr>" . mksize($e["uploadoffset"] / $secs) . "/s</nobr></td>\n";
                $s .= "<td class=\"lol\" align=\"right\"><nobr>" . mksize($e["downloaded"]) . "</nobr></td>\n";
                //if ($e["seeder"] == "no")
                        $s .= "<td class=\"lol\" align=\"right\"><nobr>" . mksize($e["downloadoffset"] / $secs) . "/s</nobr></td>\n";
                /*else
                        $s .= "<td class=\"lol\" align=\"right\"><nobr>" . mksize($e["downloadoffset"] / max(1, $e["finishedat"] - $e["st"])) . "/s</nobr></td>\n";*/
                if ($e["downloaded"]) {
                  $ratio = floor(($e["uploaded"] / $e["downloaded"]) * 1000) / 1000;
                    $s .= "<td class=\"lol\" align=\"right\"><font color=" . get_ratio_color($ratio) . ">" . number_format($ratio, 3) . "</font></td>\n";
                } else
					if ($e["uploaded"])
	                  	$s .= "<td class=\"lol\" align=\"right\">Inf.</td>\n";
					else
	                  	$s .= "<td class=\"lol\" align=\"right\">---</td>\n";
                $s .= "<td class=\"lol\" align=\"right\">" . sprintf("%.2f%%", 100 * (1 - ($e["to_go"] / $torrent["size"]))) . "</td>\n";
                $s .= "<td class=\"lol\" align=\"right\">" . mkprettytime($now - $e["st"]) . "</td>\n";
                $s .= "<td class=\"lol\" align=\"right\">" . mkprettytime($now - $e["la"]) . "</td>\n";
                $s .= "<td class=\"lol\" align=\"left\">" . htmlspecialchars(getagent($e["agent"], $e["peer_id"])) . "</td>\n";
                $s .= "</tr>\n";
        }
        $s .= "</table>\n";
        return $s;
}





$res = sql_query("SELECT torrents.seeders,torrents.modded, torrents.modby, torrents.modname, torrents.karma, torrents.leechers, torrents.info_hash, torrents.free, torrents.filename, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(torrents.last_action) AS lastseed,torrents.name, torrents.owner, torrents.save_as, torrents.descr, torrents.visible, torrents.size, torrents.added, torrents.views, torrents.hits, torrents.times_completed, torrents.id, torrents.type, torrents.tags, torrents.numfiles, torrents.image1,torrents.image2,torrents.image3,torrents.image4,torrents.image5, categories.name AS cat_name,categories.id AS cat_id, users.username " . ($CURUSER ? ", (SELECT COUNT(*) FROM karma WHERE type='torrent' AND value = torrents.id AND user = $CURUSER[id]) AS canrate" : "") . "   FROM torrents LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN users ON torrents.owner = users.id  WHERE torrents.id = $id")
        or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_array($res);

if ($act == "opisanie")
{
$op = format_comment("$row[descr]");
                                print("$op"); 


}


		
if ($act == "info")
{

                ?>
                    <script language="JavaScript" type="text/javascript">
                    /*<![CDATA[*/
                        function karma(id, type, act) {
                        jQuery.post("karma.php",{"id":id,"act":act,"type":type},function (response) {
                            jQuery("#karma" + id).empty();
                            jQuery("#karma" + id).append(response);
                        });
                        }
                    /*]]>*/
                    </script>
<script type="text/javascript" src="js/ajax.js"></script>
                <?
$size = mksize($row["size"]) . " (" . number_format($row["size"]) . " ".$tracker_lang['bytes'].") ";
$uprow = (isset($row["username"]) ? ("<a href=userdetails.php?id=" . $row["owner"] . ">" . htmlspecialchars($row["username"]) . "</a>") : "<i>Аноним</i>");
$lastseed = mkprettytime($row["lastseed"]);
if (isset($row["cat_name"])){$catname = $row["cat_name"];}else{$catname = $tracker_lang['no_choose'];}
$hash = $row["info_hash"];
?>
<center>
<table width="100%" cellspacing="0" cellpadding="5">
<tr>
<?                if (!$CURUSER || $row["canrate"] > 0 || $CURUSER['id'] == $row['owner'])
                    print("<td></td><td colspan=\"1\" class=\"lol\"  align=\"center\"><img src=\"pic/minus-dis.png\" title=\"Вы не можете голосовать\" alt=\"\" /> " . karma($row["karma"]) . " <img src=\"pic/plus-dis.png\" title=\"Вы не можете голосовать\" alt=\"\" /></td>");
                else
                    print("<td></td><td colspan=\"1\" class=\"lol\" align=\"center\" id=\"karma$id\"><img src=\"pic/minus.png\" style=\"cursor:pointer;\" title=\"Уменьшить карму\" alt=\"\" onclick=\"javascript: karma('$id', 'torrent', 'minus');\" /> " . karma($row["karma"]) . " <img src=\"pic/plus.png\" style=\"cursor:pointer;\" onclick=\"javascript: karma('$id', 'torrent', 'plus');\" title=\"Увеличить карму\" alt=\"\" /></td>\n");
?>
</tr>
<tr><td class="lol" width="33%">
<?
if (get_user_class() >= UC_MODERATOR)
    print("<div id=\"moderated\"><b>Проверен:</b> ".($row["modded"] == "no" ? "<a href=\"#\" onclick=\"javascript: check(".$row["id"].")\">Нет</a>" : "<a href=\"userdetails.php?id=".$row["modby"]."\"> ".$row["modname"]." </a>")."</div>");

?><div id="loading-layer" style="display:none;font-family: Verdana;font-size: 11px;width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000">
     <div style="font-weight:bold" id="loading-layer-text">Загрузка. Пожалуйста, подождите...</div><br />
     <img src="pic/loading.gif" border="0" />
</div>
</td><td class="lol" width="33%"><b>Размер: </b> <?print("$size");?></td><td class="lol" width="33%"><b>Раздал: </b><?print("$uprow");?></td></tr>
<tr><td class="lol" width="33%"><b>Активность: </b>Последний раз <?print("$lastseed");?> назад</td><td class="lol" width="33%"><b>Категория: </b> <?print("$catname");?></td><td class="lol" width="33%"><b>Хэш раздачи: </b> <?print("$hash");?></td></tr>
<tr>
<?php
$tags = '';

if (!empty($row["tags"])) {
    foreach (explode(",", $row["tags"]) as $tag) {
        $tag = htmlspecialchars(trim($tag));
        if ($tag !== '') {
            $tags .= "<a style=\"font-weight:normal;\" href=\"browse.php?tag=" . urlencode($tag) . "\">" . $tag . "</a>, ";
        }
    }
    if ($tags !== '') {
        $tags = rtrim($tags, ', ');
    }
    echo "<tr><td colspan=\"3\" class=\"lol\" align=\"left\"><b>Тэги:</b> $tags</td></tr>\n";
} else {
    echo "<tr><td colspan=\"3\" class=\"lol\" align=\"left\"><b>Тэги:</b> Нет тэгов</td></tr>\n";
}
?>



                        


</table><br><br><br>
<?
}



if ($act == "files")
{
                                print("<table align=center class=main border=\"1\" cellspacing=0 cellpadding=\"5\">"); 

                                $subres = sql_query("SELECT * FROM files WHERE torrent = $id ORDER BY id"); 
                                print("<tr><td class=colhead>".$tracker_lang['path']."</td><td class=colhead align=right>".$tracker_lang['size']."</td></tr>\n"); 
                                while ($subrow = mysqli_fetch_array($subres)) { 
                                        print("<tr><td class=\"lol\">" . $subrow["filename"] . 
                                        "</td><td class=\"lol\" align=\"right\">" . mksize($subrow["size"]) . "</td></tr>\n");
                                } 

                                print("</table>\n"); 
}


if ($act == "downed") {
    $id = (int)$id;
	$limit = "LIMIT 10";
    $res = sql_query("
        SELECT 
            users.id, users.username, users.title, users.uploaded, users.downloaded,
            users.donor, users.enabled, users.warned, users.last_access, users.class,
            snatched.startdat, snatched.last_action, snatched.completedat, snatched.seeder,
            snatched.userid, snatched.uploaded AS sn_up, snatched.downloaded AS sn_dn
        FROM snatched
        INNER JOIN users ON snatched.userid = users.id
        WHERE snatched.finished = 'yes' AND snatched.torrent = " . sqlesc($id) . "
        ORDER BY users.class DESC $limit
    ") or sqlerr(__FILE__, __LINE__);

    echo "<table align='center' width='100%' class='main' border='1' cellspacing='0' cellpadding='5'>
    <tr>
        <td class='colhead'>Юзер</td>
        <td class='colhead'>Раздал</td>
        <td class='colhead'>Скачал</td>
        <td class='colhead'>Рейтинг</td>
        <td class='colhead' align='center'>Начал / Закончил</td>
        <td class='colhead' align='center'>Раздавал</td>
        <td class='colhead' align='center'>Сидирует</td>
        <td class='colhead' align='center'>ЛС</td>
    </tr>";

    while ($arr = mysqli_fetch_assoc($res)) {
        $userid = (int)$arr['userid'];

        // Общий рейтинг
        if ($arr["downloaded"] > 0) {
            $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 2);
        } elseif ($arr["uploaded"] > 0) {
            $ratio = "Inf.";
        } else {
            $ratio = "---";
        }

        // Рейтинг по торренту
        if ($arr["sn_dn"] > 0) {
            $ratio2 = number_format($arr["sn_up"] / $arr["sn_dn"], 2);
            $ratio2 = "<font color=" . get_ratio_color($ratio2) . ">$ratio2</font>";
        } elseif ($arr["sn_up"] > 0) {
            $ratio2 = "Inf.";
        } else {
            $ratio2 = "---";
        }

        $uploaded     = mksize($arr["uploaded"]);
        $downloaded   = mksize($arr["downloaded"]);
        $uploaded2    = mksize($arr["sn_up"]);
        $downloaded2  = mksize($arr["sn_dn"]);

        $seeder_status = ($arr["seeder"] === "yes")
            ? "<b><font color='green'>Да</font></b>"
            : "<b><font color='red'>Нет</font></b>";

        echo "<tr>
            <td class='lol'><a href='userdetails.php?id=$userid'>" . get_user_class_color($arr["class"], $arr["username"]) . "</a>" . get_user_icons($arr) . "</td>
            <td class='lol'><nobr>$uploaded&nbsp;Общего<br>$uploaded2&nbsp;Торрент</nobr></td>
            <td class='lol'><nobr>$downloaded&nbsp;Общего<br>$downloaded2&nbsp;Торрент</nobr></td>
            <td class='lol'><nobr>$ratio&nbsp;Общего<br>$ratio2&nbsp;Торрент</nobr></td>
            <td class='lol' align='center'><nobr>" . htmlspecialchars($arr["startdat"]) . "<br>" . htmlspecialchars($arr["completedat"]) . "</nobr></td>
            <td class='lol' align='center'><nobr>" . htmlspecialchars($arr["last_action"]) . "</nobr></td>
            <td class='lol' align='center'>$seeder_status</td>
            <td class='lol' align='center'>
                <a href='message.php?action=sendmessage&amp;receiver=$userid'>
                    <img src='{$pic_base_url}button_pm.gif' border='0' alt='ЛС'>
                </a>
            </td>
        </tr>";
    }

    echo "</table>";
}




if ($act === "screen") {
    // ВНИМАНИЕ: подключение glightbox.css/js делаем на основной странице, не здесь.

    $galleryId = 'torrent-' . (int)($row['id'] ?? 0);
    $gTitle    = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $thumbs = [];
    for ($i = 2; $i <= 5; $i++) {
        $key = "image{$i}";
        if (!empty($row[$key])) {
            $src = htmlspecialchars((string)$row[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thumbs[] =
                '<a href="' . $src . '" class="js-torrent-lightbox" data-gallery="' . $galleryId . '" data-title="' . $gTitle . '">
                   <img border="0" width="172" height="69" src="' . $src . '" alt="Скрин ' . $i . '" loading="lazy" decoding="async">
                 </a>';
        }
    }

    if ($thumbs) {
        echo '<div align="center" id="screenshots" style="gap:8px">' . implode('', $thumbs) . '</div>';

        // ⬇️ ДОБАВКА: переинициализируем GLightbox сразу после вставки фрагмента
        echo '<script>
          (function () {
            // если есть наш хук — используем его
            if (window.tsRefreshLightbox) { window.tsRefreshLightbox(); return; }
            // иначе — безопасно переинициализируем тут
            if (window.GLightbox) {
              if (window.tsLightbox && window.tsLightbox.destroy) {
                try { window.tsLightbox.destroy(); } catch(e) {}
              }
              window.tsLightbox = GLightbox({
                selector: ".js-torrent-lightbox",
                touchNavigation: true,
                loop: true
              });
            }
          })();
        </script>';
    }
}
if ($act == "skill")
{
       $stuff = array('DVDRip', 'DVD9', 'DVD5', 'CAMRip', 'BDRip', 'DVD'); ## Вырезаем то, что не должно учавствовать в поиске 

       preg_match_all('/([а-яА-Я]+)/si', $row['name'], $rus); 
       preg_match_all('/([a-zA-Z]+)/si', $row['name'], $eng); 

       $rus = sqlwildcardesc(trim(implode(" ", $rus[0]))); 
       $eng = sqlwildcardesc(trim(implode(" ", $eng[0]))); 
       $eng = str_ireplace($stuff, '', $eng); 

       if (!empty($rus) && !empty($eng)) {
  $query = "(CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(" . sqlesc("%$rus%") . " USING utf8mb4) COLLATE utf8mb4_unicode_ci
         OR CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(" . sqlesc("%$eng%") . " USING utf8mb4) COLLATE utf8mb4_unicode_ci)";
} elseif (!empty($rus)) {
  $query = "CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(" . sqlesc("%$rus%") . " USING utf8mb4) COLLATE utf8mb4_unicode_ci";
} else {
  $query = "CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(" . sqlesc("%$eng%") . " USING utf8mb4) COLLATE utf8mb4_unicode_ci";
}



       $similar = sql_query("SELECT t.id, t.name, t.category, c.name, c.image FROM torrents t LEFT JOIN categories c ON (c.id = t.category) WHERE $query AND t.id <> $id") or sqlerr(__FILE__, __LINE__); 
       if (mysqli_num_rows($similar) > 0) 
       { 
         ?> 
         <table width="100%" border="1" cellspacing="0" cellpadding="5"> 
         <tr><td class="colhead">Жанр</td><td class="colhead">Название</td></tr> 
         <? 
         while ($data = mysqli_fetch_array($similar)) 
         { 
            list($sim_id, $sim_name, $cat_id, $cat_name, $cat_image) = $data; 
            print("<tr><td class=\"lol\" style=\"padding:0px;width:45px;\"><a href=\"browse.php?cat=$cat_id\"><img width=\"55\" height=\"55\" src=\"pic/cats/$cat_image\" title=\"$cat_name\" border=\"0\"/></a></td><td class=\"lol\"><a href=\"details.php?id=$sim_id\">$sim_name</a></td></tr>"); 
         } 
         ?> 
         </table> 
         <? 
       } 

}


if ($act == "peers")
{
$res = sql_query("SELECT torrents.seeders,torrents.modded, torrents.modby, torrents.modname, torrents.karma, torrents.leechers, torrents.info_hash, torrents.free, torrents.filename, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(torrents.last_action) AS lastseed,torrents.name, torrents.owner, torrents.save_as, torrents.descr, torrents.visible, torrents.size, torrents.added, torrents.views, torrents.hits, torrents.times_completed, torrents.id, torrents.type, torrents.tags, torrents.numfiles, torrents.image1,torrents.image2,torrents.image3,torrents.image4,torrents.image5, categories.name AS cat_name,categories.id AS cat_id, users.username " . ($CURUSER ? ", (SELECT COUNT(*) FROM karma WHERE type='torrent' AND value = torrents.id AND user = $CURUSER[id]) AS canrate" : "") . "   FROM torrents LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN users ON torrents.owner = users.id  WHERE torrents.id = $id")
        or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_array($res);
if(empty($row)) die('Ошибка');
					    $downloaders = array();
                        $seeders = array();
                        $subres = sql_query("SELECT seeder, finishedat, downloadoffset, uploadoffset, peers.ip, port, peers.uploaded, peers.downloaded, to_go, UNIX_TIMESTAMP(started) AS st, connectable, agent, peer_id, UNIX_TIMESTAMP(last_action) AS la, UNIX_TIMESTAMP(prev_action) AS pa, userid, users.username, users.class FROM peers INNER JOIN users ON peers.userid = users.id WHERE torrent = $id") or sqlerr(__FILE__, __LINE__);
                        while ($subrow = mysqli_fetch_array($subres)) {
                                if ($subrow["seeder"] == "yes")
                                        $seeders[] = $subrow;
                                else
                                        $downloaders[] = $subrow;
                        }

                        function leech_sort($a,$b) {
                                if ( isset( $_GET["usort"] ) ) return seed_sort($a,$b);
                                $x = $a["to_go"];
                                $y = $b["to_go"];
                                if ($x == $y)
                                        return 0;
                                if ($x < $y)
                                        return -1;
                                return 1;
                        }
                        function seed_sort($a,$b) {
                                $x = $a["uploaded"];
                                $y = $b["uploaded"];
                                if ($x == $y)
                                        return 0;
                                if ($x < $y)
                                        return 1;
                                return -1;
                        }

                        usort($seeders, "seed_sort");
                        usort($downloaders, "leech_sort");

                        print(dltable("Раздающие", $seeders, $row));
                        if($row["leechers"]) {
                        print(dltable("Качающие", $downloaders, $row));
                        }



}
}
?>