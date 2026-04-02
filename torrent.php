<?php

require_once("include/bittorrent.php");
require_once("include/multitracker.php");
dbconn();
header ("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);
header ("Cache-control: no-store");
header ("Pragma: no-cache");

$isAjaxRequest = strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
if($isAjaxRequest && ($_SERVER["REQUEST_METHOD"] ?? '') == 'POST')
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
                  $s .= "<td class=\"lol\"><a href=\"userdetails.php?id={$e["userid"]}\"><b>".get_user_class_color($e["class"], $e["username"])."</b></a>".($mod ? "&nbsp;[<span title=\"{$e["ip"]}\" style=\"cursor: pointer\">IP</span>]" : "")."</td>\n";
                else
                  $s .= "<td>" . ($mod ? $e["ip"] : preg_replace('/\.\d+$/', ".xxx", $e["ip"])) . "</td>\n";
                $secs = max(10, ($e["la"]) - $e["pa"]);
        		$s .= "<td class=\"lol\" align=\"center\">" . ($e["connectable"] == "yes" ? "<span style=\"color: green; cursor: help;\" title=\"Порт открыт. Этот пир может подключатся к любому пиру.\">".$tracker_lang['yes']."</span>" : "<span style=\"color: red; cursor: help;\" title=\"Порт закрыт. Рекомендовано проверить настройки Firwewall'а.\">".$tracker_lang['no']."</span>") . "</td>\n";
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
function ajax_torrent_row(int $torrentId): array
{
    global $CURUSER;

    $cacheKey = tracker_cache_key('torrent-tab', 'row', 't' . $torrentId);
    $row = tracker_cache_remember($cacheKey, 60, static function () use ($torrentId): array {
        $res = sql_query(
            "SELECT
                torrents.seeders,
                torrents.modded,
                torrents.modby,
                torrents.modname,
                torrents.karma,
                torrents.leechers,
                torrents.info_hash,
                torrents.free,
                torrents.filename,
                UNIX_TIMESTAMP() - UNIX_TIMESTAMP(torrents.last_action) AS lastseed,
                torrents.name,
                torrents.owner,
                torrents.release_group_id,
                torrents.save_as,
                torrents.descr,
                torrents.visible,
                torrents.size,
                torrents.added,
                torrents.views,
                torrents.hits,
                torrents.times_completed,
                torrents.id,
                torrents.type,
                torrents.tags,
                torrents.numfiles,
                torrents.image1,
                torrents.image2,
                torrents.image3,
                torrents.image4,
                torrents.image5,
                COALESCE(mts.external_completed, 0) AS external_completed,
                rg.name AS release_group_name,
                rg.image AS release_group_image,
                categories.name AS cat_name,
                categories.id AS cat_id,
                users.username
            FROM torrents
            LEFT JOIN categories ON torrents.category = categories.id
            LEFT JOIN users ON torrents.owner = users.id
            LEFT JOIN release_groups AS rg ON rg.id = torrents.release_group_id
            " . multitracker_stats_summary_sql('torrents') . "
            WHERE torrents.id = {$torrentId}
            LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        return mysqli_fetch_assoc($res) ?: [];
    });

    if (!is_array($row) || !$row) {
        return [];
    }

    $row['canrate'] = 0;
    if (!empty($CURUSER['id'])) {
        $res = sql_query("SELECT COUNT(*) AS cnt FROM karma WHERE type='torrent' AND value = " . (int)$torrentId . " AND user = " . (int)$CURUSER['id']);
        $canrateRow = $res ? mysqli_fetch_assoc($res) : null;
        $row['canrate'] = (int)($canrateRow['cnt'] ?? 0);
    }

    return $row;
}

$row = ajax_torrent_row($id);
if (!$row) {
    die('Ошибка');
}

if ($act == "opisanie")
{
$op = format_comment((string)$row["descr"]);
                                print("$op"); 


}


		
if ($act == "info")
{
    $size = mksize((float)$row["size"]) . " (" . number_format((float)$row["size"]) . " " . $tracker_lang['bytes'] . ")";
    $uprow = isset($row["username"])
        ? ("<a href=\"userdetails.php?id=" . (int)$row["owner"] . "\">" . htmlspecialchars((string)$row["username"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>")
        : "<i>Аноним</i>";
    $lastseed = mkprettytime((int)$row["lastseed"]);
    $catname = isset($row["cat_name"]) ? htmlspecialchars((string)$row["cat_name"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $tracker_lang['no_choose'];
    $hash = htmlspecialchars((string)$row["info_hash"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $meta = tracker_torrent_extract_meta((string)($row['descr'] ?? ''));
    $downloadsCount = (int)($row['times_completed'] ?? 0) + (int)($row['external_completed'] ?? 0);

    $genreHtml = $meta['genre'] !== '' ? tracker_render_browse_search_links($meta['genre']) : 'Не указано';
    $yearHtml = $meta['year'] !== '' ? tracker_render_browse_search_links($meta['year'], 1) : 'Не указано';
    $releasedHtml = $meta['released'] !== '' ? tracker_render_browse_search_links($meta['released']) : 'Не указано';
    $directorHtml = $meta['director'] !== '' ? tracker_render_browse_search_links($meta['director']) : 'Не указан';
    $rolesHtml = $meta['roles'] !== '' ? tracker_render_browse_search_links($meta['roles'], 30) : 'Не указаны';

    $tags = [];
    if (!empty($row["tags"])) {
        foreach (explode(",", (string)$row["tags"]) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }

            $tagEsc = htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $tags[] = "<a style=\"font-weight:normal;\" href=\"browse.php?tag=" . urlencode($tag) . "&amp;incldead=1\">" . $tagEsc . "</a>";
        }
    }
    $tagsHtml = $tags ? implode(', ', $tags) : 'Нет тегов';
    $releaseGroupHtml = ((int)($row['release_group_id'] ?? 0) > 0)
        ? tracker_release_group_badge_html([
            'id' => (int)$row['release_group_id'],
            'name' => (string)($row['release_group_name'] ?? ''),
            'image' => (string)($row['release_group_image'] ?? ''),
        ])
        : 'Не указана';

    $canVoteTorrent = $CURUSER && (int)($row["canrate"] ?? 0) <= 0 && (int)($CURUSER['id'] ?? 0) !== (int)$row['owner'];
    $karmaValueHtml = karma((int)($row["karma"] ?? 0));
    $karmaHtml = '<span id="torrent-karma-' . (int)$id . '" style="display:inline-flex;align-items:center;gap:8px;">';
    if ($canVoteTorrent) {
        $karmaHtml .= '<button type="button" class="torrent-karma-btn" data-id="' . (int)$id . '" data-type="torrent" data-act="minus" style="border:1px solid #d5dce5;border-radius:999px;background:#fff;padding:2px 8px;cursor:pointer;">−</button>'
            . $karmaValueHtml
            . '<button type="button" class="torrent-karma-btn" data-id="' . (int)$id . '" data-type="torrent" data-act="plus" style="border:1px solid #d5dce5;border-radius:999px;background:#fff;padding:2px 8px;cursor:pointer;">+</button>';
    } else {
        $karmaHtml .= '<button type="button" disabled style="border:1px solid #e3e7ee;border-radius:999px;background:#f8fafc;padding:2px 8px;color:#9aa6b2;">−</button>'
            . $karmaValueHtml
            . '<button type="button" disabled style="border:1px solid #e3e7ee;border-radius:999px;background:#f8fafc;padding:2px 8px;color:#9aa6b2;">+</button>';
    }
    $karmaHtml .= '</span>';

    $moderatedHtml = '';
    if (get_user_class() >= UC_MODERATOR) {
        $moderatedHtml = '<div style="margin-bottom:12px;padding:10px 12px;border:1px solid #d7dee6;border-radius:12px;background:#fbfdff;">'
            . '<b>Проверен:</b> '
            . ($row["modded"] == "no"
                ? '<a href="#" onclick="javascript: check(' . (int)$row["id"] . '); return false;">Нет</a>'
                : '<a href="userdetails.php?id=' . (int)$row["modby"] . '">' . htmlspecialchars((string)$row["modname"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>')
            . '</div>';
    }

    echo '<div class="torrent-info-tab" style="padding:4px 2px 8px;">';
    echo $moderatedHtml;
    echo '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:stretch;">';
    echo '<div style="flex:1 1 240px;min-width:240px;padding:12px 14px;border:1px solid #d7dee6;border-radius:14px;background:#fbfdff;">';
    echo '<div style="font-size:12px;color:#6b7d92;margin-bottom:6px;">Карма раздачи</div>';
    echo '<div style="font-size:14px;line-height:1.5;">' . $karmaHtml . '</div>';
    echo '<div style="margin-top:8px;color:#6b7d92;font-size:12px;">Скачали ' . $downloadsCount . ' раз</div>';
    echo '</div>';

    echo '<div style="flex:3 1 520px;min-width:280px;">';
    echo '<table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:separate;border-spacing:0;">';

    $rows = [
        ['Размер', $size],
        ['Раздал', $uprow],
        ['Категория', $catname],
        ['Релиз-группа', $releaseGroupHtml],
        ['Активность', 'Последний раз ' . $lastseed . ' назад'],
        ['Скачали', (string)$downloadsCount],
        ['Хэш раздачи', '<span style="font-family:monospace;font-size:12px;word-break:break-all;">' . $hash . '</span>'],
        ['Жанр', $genreHtml],
        ['Выпущено', $releasedHtml],
        ['Режиссер', $directorHtml],
        ['В ролях', $rolesHtml],
        ['Год', $yearHtml],
        ['Тэги', $tagsHtml],
    ];

    foreach ($rows as [$label, $value]) {
        echo '<tr>'
            . '<td class="lol" style="width:180px;font-weight:700;vertical-align:top;">' . $label . ':</td>'
            . '<td class="lol" style="vertical-align:top;">' . $value . '</td>'
            . '</tr>';
    }

    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
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
    $gTitle = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $thumbs = [];
    for ($i = 2; $i <= 5; $i++) {
        $key = "image{$i}";
        if (!empty($row[$key])) {
            $src = htmlspecialchars((string)$row[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thumbs[] =
                '<a href="' . $src . '" class="js-torrent-gallery" data-pswp-width="1280" data-pswp-height="720" data-title="' . $gTitle . '" style="display:block;border:1px solid #d8e0e8;border-radius:12px;overflow:hidden;background:#fff;text-decoration:none;">'
                   . '<img border="0" width="100%" height="160" src="' . $src . '" alt="Скрин ' . $i . '" loading="lazy" decoding="async" style="display:block;width:100%;height:160px;object-fit:cover;">'
                 . '</a>';
        }
    }

    if ($thumbs) {
        echo '<div id="screenshots" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:start;">' . implode('', $thumbs) . '</div>';
    } else {
        echo '<div style="padding:18px 12px;border:1px dashed #d6dee7;border-radius:12px;background:#fbfdff;color:#66788f;">Скриншоты не добавлены.</div>';
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
         <?php  
         while ($data = mysqli_fetch_array($similar)) 
         { 
            list($sim_id, $sim_name, $cat_id, $cat_name, $cat_image) = $data; 
            print("<tr><td class=\"lol\" style=\"padding:0px;width:45px;\"><a href=\"browse.php?cat=$cat_id\"><img width=\"55\" height=\"55\" src=\"pic/cats/$cat_image\" title=\"$cat_name\" border=\"0\"/></a></td><td class=\"lol\"><a href=\"details.php?id=$sim_id\">$sim_name</a></td></tr>"); 
         } 
         ?> 
         </table> 
         <?php  
       } 

}


if ($act == "peers")
{
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
