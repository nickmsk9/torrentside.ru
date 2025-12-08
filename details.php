<?
require_once("include/bittorrent.php");
require_once 'classes/rating.class.php'; 

gzip();

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

dbconn(false);

$id = 0 + (int)$_GET["id"];

if (!isset($id) || !$id)
        die();

$res = sql_query("SELECT torrents.seeders,torrents.modded, torrents.modby, torrents.modname, torrents.karma, torrents.leechers, torrents.info_hash, torrents.free, torrents.filename, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(torrents.last_action) AS lastseed,torrents.name, torrents.owner, torrents.save_as, torrents.descr, torrents.visible, torrents.size, torrents.added, torrents.views, torrents.hits, torrents.times_completed, torrents.id, torrents.type, torrents.tags, torrents.numfiles, torrents.image1,torrents.image2,torrents.image3,torrents.image4,torrents.image5, categories.name AS cat_name,categories.id AS cat_id, users.username " . ($CURUSER ? ", (SELECT COUNT(*) FROM karma WHERE type='torrent' AND value = torrents.id AND user = $CURUSER[id]) AS canrate" : "") . "   FROM torrents LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN users ON torrents.owner = users.id  WHERE torrents.id = $id")
        or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_array($res);


// безопасные дефолты
$uid     = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
$ownerId = isset($row['owner'])  ? (int)$row['owner']  : 0;

$owned = 0;
$moderator = 0;

if (get_user_class() >= UC_MODERATOR) {
    $owned = 1;
    $moderator = 1;
} elseif ($uid > 0 && $ownerId > 0 && $uid === $ownerId) {
    $owned = 1;
}

 
// Заголовок (имя — безопасно)
$titleName = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
stdhead($tracker_lang['torrent_details'] . ' "' . $titleName . '"');
?>
<script type="text/javascript" src="js/ajax.js"></script>

<!-- === GLightbox вместо Highslide === -->
<link rel="stylesheet" href="/glightbox/glightbox.min.css" type="text/css" media="screen" />
<script type="text/javascript" src="/glightbox/glightbox.min.js"></script>
<script>
  // Один init на страницу; селектор — для наших постеров
  document.addEventListener('DOMContentLoaded', function () {
    window.tsLightbox = GLightbox({
      selector: '.js-torrent-lightbox',
      touchNavigation: true,
      loop: true
    });
  });
</script>
<?php
// --- безопасные значения ---
$uid        = (int)($CURUSER['id'] ?? 0);
$ownerId    = (int)($row['owner'] ?? 0);
$id         = (int)($id ?? 0);
$torrentId  = $id;

// Если вообще нет $row — дальше выводить нечего
if (!$row) {
    httperr(); // или graceful return/redirect
}

$owned   = (get_user_class() >= UC_MODERATOR || ($uid > 0 && $uid === $ownerId)) ? 1 : 0;
$spacer  = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
$name    = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Кнопки "предыдущий/следующий" + название
$namer =
    '<a href="pass_on.php?to=pre&amp;from=' . $torrentId . '">'
  . '<img title="Предыдущий" width="13" height="13" src="pic/prev.gif" alt="Prev" border="0"></a>'
  . '&nbsp;&nbsp;' . $name . '&nbsp;&nbsp;'
  . '<a href="pass_on.php?to=next&amp;from=' . $torrentId . '">'
  . '<img title="Следующий" width="13" height="13" src="pic/next.gif" alt="Next" border="0"></a>';

$keepget = '';
$url     = 'edit.php?id=' . (int)$row['id'];
if (isset($_GET['returnto'])) {
    $addthis = '&amp;returnto=' . urlencode((string)$_GET['returnto']);
    $url    .= $addthis;
    $keepget = $addthis;
}
$editAnchor = '<a href="' . $url . '" class="sublink">[' . $tracker_lang['edit'] . ']</a>';
$s = $owned ? (' ' . $spacer . $editAnchor) : '';

// free % (строка для показа и число для бейджа)
$freePct  = (isset($row['free']) && is_numeric($row['free'])) ? max(0, min(100, (int)$row['free'])) : null;
$freebig  = $freePct !== null ? ('4. Скачивание не учитывается на <font color="red">' . $freePct . '</font> %') : '';

// ——— JS рейтинг ———
?>
<script type="text/javascript">
  function SE_TorrentRate(num, tid) {
    num = parseInt(num, 10);
    tid = parseInt(tid, 10);
    if (!Number.isFinite(num) || !Number.isFinite(tid)) return;
    $.post('/takerate.php', { 'do': 'rate', rating: num, tid: tid }, function (response) {
      $('#rating_div').html(response);
    }, 'html');
  }
  function SE_TorrentRatingDelete(tid) {
    tid = parseInt(tid, 10);
    if (!Number.isFinite(tid)) return;
    $.post('/takerate.php', { 'do': 'delete', tid: tid }, function (response) {
      $('#rating_div').html(response);
    }, 'html');
  }
</script>
<?php

// Инициализация рейтинга
$oRating = new rating((int)$row['id']);

// Обложка (безопасно) — Highslide → GLightbox
$img1 = '';
if (!empty($row['image1'])) {
    $imgSrc = htmlspecialchars((string)$row['image1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Добавил data-gallery, чтобы потом легко группировать несколько картинок
    // data-title — заголовок лайтбокса; loading/decoding — микрооптимизация
    $img1 = '<a href="' . $imgSrc . '" class="js-torrent-lightbox" data-gallery="torrent-' . (int)$row['id'] . '" data-title="' . $titleName . '">'
          .     '<img border="0" width="200" height="300" src="' . $imgSrc . '" alt="Обложка" loading="lazy" decoding="async" />'
          .   '</a><br>';
}

// ——— КАРКАС ВЁРСТКИ ———
echo '<center>';
begin_frame($namer);

// верхняя таблица блока деталей
print('<table width="100%" border="1" cellspacing="0" cellpadding="5">');
print('<tr><td colspan="2"></td></tr>');

// левая колонка: обложка + рейтинг + edit
print('<tr>');
print('<td width="200" class="rowhead" valign="top" align="center">');
echo $img1;
echo '<div id="rating_div">' . $oRating->getRatingBar() . '</div>';
if (!empty($s)) {
    echo '<br><center>' . $s . '</center>';
}
print('</td>');

// правая колонка: табы + описание
print('<td class="lol" valign="top" align="left">');
print('<script src="js/details.js" type="text/javascript"></script>');
print('<div id="tabs">');
print('<span class="tab active" id="opisanie">Описание</span>');
print('<span class="tab" id="info">Информация</span>');
print('<span class="tab" id="peers">Пиры</span>');
print('<span class="tab" id="downed">Скачавшие</span>');
print('<span class="tab" id="files">Файлы</span>');
print('<span class="tab" id="screen">Скриншоты</span>');
print('<span class="tab" id="skill">Похожие раздачи</span>');
print('<span id="loading"></span>');
print('<div id="body" torrent="' . $torrentId . '">');
$op = format_comment((string)($row['descr'] ?? ''));
print($op);
print('</div>'); // #body
print('</div>'); // #tabs
print('</td></tr>');
print('</table>'); // ВАЖНО: без лишнего </p>

// ——— Блок «скачать .torrent / magnet» ———
?>
<table align="center" width="70%" border="1" cellspacing="0" cellpadding="5">
<?php
$torr   = htmlspecialchars($row['name'] ?? $row['filename'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fname  = (string)($row['filename'] ?? $row['name'] ?? ('torrent-' . $torrentId . '.torrent'));
$dUrl   = 'download.php?id=' . $torrentId . '&amp;name=' . rawurlencode($fname);
$mUrl   = $dUrl . '&amp;magnet=1';

// если нужно бейдж «Скидка N%»
$discountPct = $freePct; // используем то же число

$btnCommon = 'display:block;padding:9px 12px;margin:0 0 8px 0;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;font-weight:600;';
$btnTorrent = $btnCommon . 'background:#eef5ff;';
$btnMagnet  = $btnCommon . 'background:#f6f8fa;';
$badgeSale  = 'display:inline-block;padding:6px 10px;margin:6px 0 10px 0;border:1px solid #991b1b;border-radius:999px;background:#fee2e2;color:#7f1d1d;font-weight:700;';

$left = ''
  . '<div>'
  .   '<a href="' . $dUrl . '" style="' . $btnTorrent . '">Скачать .torrent</a>'
  .   '<a href="' . $mUrl . '" style="' . $btnMagnet  . '">Открыть magnet</a>'
  .   ($discountPct !== null ? '<span style="' . $badgeSale . '">Скидка ' . (int)$discountPct . '%</span>' : '')
  . '</div>';

$right = ''
  . '<div style="font-weight:700;margin-bottom:6px;">' . $torr . '</div>'
  . '<ol style="margin:6px 0 0 18px;padding:0;">'
  .   '<li>Установите клиент: <a href="https://www.qbittorrent.org/" target="_blank" rel="noopener"><b>qBittorrent</b></a> (или любой другой).</li>'
  .   '<li>Скачайте <b>.torrent</b> выше или используйте <b>magnet</b>-ссылку.</li>'
  .   '<li>Откройте в клиенте, выберите папку и подтвердите.</li>'
  . '</ol>'
  . ($freebig !== '' ? '<div style="margin-top:8px;">' . $freebig . '</div>' : '');

tr($left, $right, 1, 1, '22%');
?>
</table>
<?php
end_frame();
echo '</center>';


echo '<script type="text/javascript" src="/js/comments.js"></script>' . "\n";
echo '<a id="startcomments"></a>' . "\n"; // без лишнего <p>

$id = (int)$id;
$limited = 10;

// Авторизация (мягко)
$isLogged = isset($CURUSER) && is_array($CURUSER) && !empty($CURUSER['id']);
$uid      = $isLogged ? (int)$CURUSER['id'] : 0;

// --- Счётчик комментариев (с мягким кешем, если есть) ---
$commentsCount = null;
if (function_exists('mc_get')) {
    $commentsCount = mc_get("comments:count:t{$id}");
}
if (!is_int($commentsCount)) {
    $subres = $mysqli->query("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = {$id}");
    $subrow = $subres ? $subres->fetch_assoc() : ['cnt' => 0];
    $commentsCount = (int)$subrow['cnt'];
    if (function_exists('mc_set')) mc_set("comments:count:t{$id}", $commentsCount, 60);
}

// Блок для AJAX
echo '<div id="comments_list" class="comments-list">' . "\n";

if ($commentsCount === 0) {
    if ($isLogged) {
        // Пусто → сразу форма
        begin_frame("Комментарии");
        echo '<form name="comment" id="comment" method="post" action="#">';
        echo '<table style="margin-top:2px;" cellpadding="5" width="100%">';
        echo '<tr><td align="center">';
        $text = $_POST['text'] ?? '';
        // ВАЖНО: имя формы = "comment", иначе редактор может не повеситься
        textbbcode("comment", "text", $text);
        echo '</td></tr><tr><td align="center">';
        echo '<input type="button" class="btn" value="Разместить комментарий" onclick="SE_SendComment(' . $id . ')" id="send_comment" />';
        echo '</td></tr></table>';
        echo '</form>';
        end_frame();
    }
} else {
    // Пагинация
    [$pagertop, $pagerbottom, $limit] = pager($limited, $commentsCount, "details.php?id={$id}&", ['lastpagedefault' => 1]);

    // Можно ли голосовать за карму комментария? (0/1 строка — считаем как количество оценок текущего юзера)
    $canrateCol = $isLogged
        ? ", (SELECT COUNT(*) FROM karma WHERE type = 'comment' AND value = c.id AND user = {$uid}) AS canrate"
        : "";

    // Получаем страницу комментариев
    $sql = "
        SELECT
            c.id,
            c.torrent       AS torrentid,
            c.ip,
            c.text,
            c.`user`,
            c.added,
            c.editedby,
            c.editedat,
            c.karma,
            u.avatar,
            u.warned,
            u.username,
            u.title,
            u.class,
            u.donor,
            u.downloaded,
            u.uploaded,
            u.gender,
            u.last_access,
            e.username      AS editedbyname
            {$canrateCol}
        FROM comments AS c
        LEFT JOIN users AS u ON c.`user` = u.id
        LEFT JOIN users AS e ON c.editedby = e.id
        WHERE c.torrent = {$id}
        ORDER BY c.id {$limit}";
    $subres = $mysqli->query($sql) or sqlerr(__FILE__, __LINE__);

    $allrows = [];
    while ($r = $subres->fetch_assoc()) {
        $allrows[] = $r;
    }

    // Рисуем список
    echo '<table class="main" cellspacing="0" cellpadding="5" width="100%">';
    echo '<tr><td>';

    // верхний пейджер
    if (!empty($pagertop)) {
        echo '<div class="pager-wrap pager-wrap--top">', $pagertop, '</div>';
    }

    // список комментариев
    commenttable($allrows);

    echo '</td></tr><tr><td>';

    // нижний пейджер
    if (!empty($pagerbottom)) {
        echo '<div class="pager-wrap pager-wrap--bottom">', $pagerbottom, '</div>';
    }

    echo '</td></tr></table>';

    // форма добавления — только для залогиненных
    if ($isLogged) {
        begin_frame("Комментарии");
        echo '<form name="comment" id="comment" method="post" action="#">';
        echo '<table style="margin-top:2px;" cellpadding="5" width="100%">';
        echo '<tr><td align="center">';
        $text = $_POST['text'] ?? '';
        textbbcode("comment", "text", $text);
        echo '</td></tr><tr><td align="center">';
        echo '<input type="button" class="btn" value="Разместить комментарий" onclick="SE_SendComment(' . $id . ')" id="send_comment" />';
        echo '</td></tr></table>';
        echo '</form>';
        end_frame();
    }
}

echo "</div>\n"; // #comments_list

stdfoot();