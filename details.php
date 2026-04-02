<?php 
require_once("include/bittorrent.php");
require_once("include/multitracker.php");
require_once 'classes/rating.class.php'; 

gzip();

function details_cache_get(string $key) {
    $value = tracker_cache_get($key, $hit);
    return $hit ? $value : false;
}

function details_cache_set(string $key, $value, int $ttl = 300): void {
    tracker_cache_set($key, $value, $ttl);
}

function details_render_descr_html(int $torrentId, string $descr): string {
    $hash = md5($descr);
    $key = tracker_cache_key('details', 'descr', 't' . $torrentId, 'h' . $hash);
    $cached = details_cache_get($key);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $html = format_comment($descr);
    details_cache_set($key, $html, 600);
    return $html;
}

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

dbconn(false);

$id = 0 + (int)$_GET["id"];

if (!isset($id) || !$id)
        die();

if (empty($_SESSION['mt_refresh_token'])) {
    $_SESSION['mt_refresh_token'] = bin2hex(random_bytes(32));
}

$multitrackerRefreshToken = (string)$_SESSION['mt_refresh_token'];
$multitrackerRefreshDone = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_multitracker'])) {
    $postedToken = (string)($_POST['mt_refresh_token'] ?? '');
    if (!hash_equals($multitrackerRefreshToken, $postedToken)) {
        stderr('Ошибка', 'Неверный токен обновления мультитрекера.');
    }

    multitracker_refresh_torrent_stats($id, 20, true);
    tracker_cache_delete(tracker_cache_key('details', 'torrent', 't' . $id));
    tracker_cache_delete(tracker_cache_key('details', 'trackers', 't' . $id));
    $multitrackerRefreshDone = true;
}

$row = tracker_cache_remember(
    tracker_cache_key('details', 'torrent', 't' . $id),
    60,
    static function () use ($id): array {
        $res = sql_query("
            SELECT
                torrents.id,
                torrents.name,
                torrents.owner,
                torrents.descr,
                torrents.filename,
                torrents.free,
                torrents.release_group_id,
                torrents.image1,
                torrents.times_completed,
                torrents.seeders,
                torrents.leechers,
                rg.name AS release_group_name,
                rg.image AS release_group_image,
                COALESCE(mts.external_seeders, 0) AS external_seeders,
                COALESCE(mts.external_leechers, 0) AS external_leechers,
                COALESCE(mts.external_completed, 0) AS external_completed,
                mts.external_fetched_at
            FROM torrents
            LEFT JOIN release_groups AS rg ON rg.id = torrents.release_group_id
            " . multitracker_stats_summary_sql('torrents') . "
            WHERE torrents.id = {$id}
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);

        return mysqli_fetch_array($res) ?: [];
    }
);

 
// Заголовок (имя — безопасно)
$titleName = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
stdhead($tracker_lang['torrent_details'] . ' "' . $titleName . '"');
?>
<script type="text/javascript" src="js/ajax.js"></script>
<link rel="stylesheet" href="/js/photoswipe/photoswipe.css" type="text/css" media="screen" />
<script type="module">
  import PhotoSwipeLightbox from '/js/photoswipe/photoswipe-lightbox.esm.min.js';

  let torrentLightbox = null;

  function initTorrentGallery() {
    if (torrentLightbox) {
      torrentLightbox.destroy();
    }

    torrentLightbox = new PhotoSwipeLightbox({
      gallery: 'body',
      children: 'a.js-torrent-gallery',
      pswpModule: () => import('/js/photoswipe/photoswipe.esm.min.js'),
      showHideAnimationType: 'zoom',
      bgOpacity: 0.92,
      wheelToZoom: true,
    });

    torrentLightbox.init();
  }

  window.tsRefreshTorrentGallery = initTorrentGallery;
  document.addEventListener('DOMContentLoaded', initTorrentGallery);
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
$descrHtml = details_render_descr_html($torrentId, (string)($row['descr'] ?? ''));
$bookmarkActive = $uid > 0 ? tracker_user_has_torrent_bookmark($uid, $torrentId) : false;

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
    $img1 = '<a href="' . $imgSrc . '" class="js-torrent-gallery" data-pswp-width="900" data-pswp-height="1350" data-title="' . $titleName . '">'
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
print($descrHtml);
print('</div>'); // #body
print('</div>'); // #tabs
print('</td></tr>');
print('</table>'); // ВАЖНО: без лишнего </p>

// ——— Блок «скачать .torrent / magnet» ———
?>
<table align="center" width="70%" border="1" cellspacing="0" cellpadding="5" style="margin-top:14px;">
<?php
$torr   = htmlspecialchars($row['name'] ?? $row['filename'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fname  = (string)($row['filename'] ?? $row['name'] ?? ('torrent-' . $torrentId . '.torrent'));
$dUrl   = htmlspecialchars('download.php?id=' . $torrentId . '&name=' . rawurlencode($fname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$mUrl   = htmlspecialchars('download.php?id=' . $torrentId . '&name=' . rawurlencode($fname) . '&magnet=1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$seedersCount = (int)($row['seeders'] ?? 0);
$leechersCount = (int)($row['leechers'] ?? 0);
$externalSeedersCount = (int)($row['external_seeders'] ?? 0);
$externalLeechersCount = (int)($row['external_leechers'] ?? 0);
$externalCompletedCount = (int)($row['external_completed'] ?? 0);
$localCompletedCount = (int)($row['times_completed'] ?? 0);
$totalCompletedCount = $localCompletedCount + $externalCompletedCount;
$externalFetchedAt = trim((string)($row['external_fetched_at'] ?? ''));
$totalSeedersCount = $seedersCount + $externalSeedersCount;
$totalLeechersCount = $leechersCount + $externalLeechersCount;
$hasActivePeers = ($totalSeedersCount + $totalLeechersCount) > 0;
$releaseGroupBadge = '';
if ((int)($row['release_group_id'] ?? 0) > 0) {
    $releaseGroupBadge = tracker_release_group_badge_html([
        'id' => (int)$row['release_group_id'],
        'name' => (string)($row['release_group_name'] ?? ''),
        'image' => (string)($row['release_group_image'] ?? ''),
    ]);
}
$trackerRows = tracker_cache_remember(
    tracker_cache_key('details', 'trackers', 't' . $torrentId),
    60,
    static function () use ($torrentId): array {
        return multitracker_get_tracker_details($torrentId);
    }
);
if (!is_array($trackerRows)) {
    $trackerRows = [];
}

// если нужно бейдж «Скидка N%»
$discountPct = $freePct; // используем то же число

$btnCommon = 'display:block;padding:9px 12px;margin:0 0 8px 0;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;font-weight:600;';
$btnTorrent = $btnCommon . 'background:#eef5ff;';
$btnMagnet  = $btnCommon . 'background:#f6f8fa;';
$badgeSale  = 'display:inline-block;padding:6px 10px;margin:6px 0 10px 0;border:1px solid #991b1b;border-radius:999px;background:#fee2e2;color:#7f1d1d;font-weight:700;';

$left = ''
  . '<div>'
  .   '<a href="' . $dUrl . '" style="' . $btnTorrent . '">Скачать .torrent</a>'
  .   ($hasActivePeers
        ? '<a href="' . $mUrl . '" style="' . $btnMagnet  . '">Открыть magnet</a>'
        : '<div style="' . $btnMagnet . 'opacity:.65;cursor:not-allowed;">Magnet недоступен</div>'
    )
  .   ($discountPct !== null ? '<span style="' . $badgeSale . '">Скидка ' . (int)$discountPct . '%</span>' : '')
  .   (!$hasActivePeers ? '<div style="margin-top:8px;color:#8a1f1f;">Magnet работает только при наличии активных пиров.</div>' : '')
  . '</div>';

$right = ''
  . '<div style="font-weight:700;margin-bottom:6px;">' . $torr . '</div>'
  . ($releaseGroupBadge !== '' ? '<div style="margin:0 0 10px 0;">' . $releaseGroupBadge . '</div>' : '')
  . '<div style="margin:0 0 8px 0;padding:8px 10px;border:1px solid #d7dee6;border-radius:10px;background:#f8fbff;">'
  .   '<div><b>Локально:</b> сиды ' . $seedersCount . ' / личи ' . $leechersCount . '</div>'
  .   '<div><b>Внешне:</b> сиды ' . $externalSeedersCount . ' / личи ' . $externalLeechersCount . '</div>'
  .   '<div><b>Итого:</b> сиды ' . $totalSeedersCount . ' / личи ' . $totalLeechersCount . '</div>'
  .   '<div><b>Скачали:</b> ' . $totalCompletedCount . ' <span style="color:#7a8ca2;">(локально ' . $localCompletedCount . ' / мультитрекер ' . $externalCompletedCount . ')</span></div>'
  .   ($externalFetchedAt !== '' ? '<div style="margin-top:4px;color:#64748b;">Кэш внешней статистики: ' . htmlspecialchars($externalFetchedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>' : '')
  .   ($multitrackerRefreshDone ? '<div style="margin-top:4px;color:#166534;">Данные мультитрекера обновлены принудительно.</div>' : '')
  .   ($uid > 0
        ? '<form method="post" action="bookmark.php" style="margin-top:10px;">'
            . '<input type="hidden" name="type" value="torrent">'
            . '<input type="hidden" name="entity_id" value="' . $torrentId . '">'
            . '<input type="hidden" name="returnto" value="' . htmlspecialchars('details.php?id=' . $torrentId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<button type="submit" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;background:' . ($bookmarkActive ? '#fef3c7' : '#eef5ff') . ';color:#1d4f91;font-weight:600;cursor:pointer;">'
            . ($bookmarkActive ? 'Убрать из закладок' : 'Добавить в закладки')
            . '</button>'
          . '</form>'
        : '')
  .   '<form method="post" action="details.php?id=' . $torrentId . '" style="margin-top:8px;">'
  .     '<input type="hidden" name="mt_refresh_token" value="' . htmlspecialchars($multitrackerRefreshToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
  .     '<input type="hidden" name="refresh_multitracker" value="1">'
  .     '<input type="submit" value="Обновить мультитрекерные данные" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#eef5ff;color:#1d4f91;font-weight:600;cursor:pointer;">'
  .   '</form>'
  . '</div>'
  . '<ol style="margin:6px 0 0 18px;padding:0;">'
  .   '<li>Установите клиент: <a href="https://www.qbittorrent.org/" target="_blank" rel="noopener"><b>qBittorrent</b></a> (или любой другой).</li>'
  .   '<li>Скачайте <b>.torrent</b> выше или используйте <b>magnet</b>-ссылку.</li>'
  .   '<li>Откройте в клиенте, выберите папку и подтвердите.</li>'
  . '</ol>'
  . ($freebig !== '' ? '<div style="margin-top:8px;">' . $freebig . '</div>' : '');

tr($left, $right, 1, 1, '22%');
?>
</table>
<?php if (!empty($trackerRows)) { ?>
<table align="center" width="70%" border="1" cellspacing="0" cellpadding="5" style="margin-top:10px;">
<?php
$trackerHtml = '<table width="100%" cellspacing="0" cellpadding="4">'
    . '<tr>'
    . '<td class="colhead" align="left"><b>Трекер</b></td>'
    . '<td class="colhead" align="center"><b>Тип</b></td>'
    . '<td class="colhead" align="center"><b>Сиды</b></td>'
    . '<td class="colhead" align="center"><b>Личи</b></td>'
    . '<td class="colhead" align="center"><b>Статус</b></td>'
    . '</tr>';

foreach ($trackerRows as $trackerRow) {
    $trackerUrl = htmlspecialchars((string)($trackerRow['tracker_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sourceName = htmlspecialchars((string)($trackerRow['source_name'] ?? 'tracker'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $isLocal = !empty($trackerRow['is_local']);
    $rawStatus = (string)($trackerRow['status'] ?? 'pending');
    $lastError = trim((string)($trackerRow['last_error'] ?? ''));
    $status = htmlspecialchars(multitracker_translate_status($rawStatus, $isLocal, $lastError), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $statusHint = $lastError !== '' ? '<div style="margin-top:2px;color:#7c8796;font-size:11px;">' . htmlspecialchars($lastError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>' : '';
    $trackerHtml .= '<tr>'
        . '<td class="lol"><a href="' . $trackerUrl . '" target="_blank" rel="noopener">' . $sourceName . '</a></td>'
        . '<td class="lol" align="center">' . ($isLocal ? 'локальный' : 'внешний') . '</td>'
        . '<td class="lol" align="center">' . (int)($trackerRow['seeders'] ?? 0) . '</td>'
        . '<td class="lol" align="center">' . (int)($trackerRow['leechers'] ?? 0) . '</td>'
        . '<td class="lol" align="center">' . $status . $statusHint . '</td>'
        . '</tr>';
}
$trackerHtml .= '</table>';
tr('<b>Мультитрекер</b>', $trackerHtml, 1, 1, '22%');
?>
</table>
<?php } ?>
<?php
end_frame();
echo '</center>';


echo '<script type="text/javascript" src="/js/comments.js"></script>' . "\n";
echo '<a id="startcomments"></a>' . "\n"; // без лишнего <p>

if (!function_exists('details_comments_supports_threads')) {
    function details_comments_supports_threads(): bool {
        return tracker_comments_supports_threads();
    }
}

if (!function_exists('details_comments_pager_wrap')) {
    function details_comments_pager_wrap(string $pagerHtml, int $count, int $rpp, bool $lastPageDefault = true): string {
        $pagerHtml = trim($pagerHtml);
        if ($pagerHtml === '' || $count <= 0) {
            return '';
        }

        $pages = max(1, (int)ceil($count / max(1, $rpp)));
        $pageDefault = $lastPageDefault ? max((int)floor(($count - 1) / max(1, $rpp)), 0) : 0;
        $page = isset($_GET['page']) ? max(0, (int)$_GET['page']) : $pageDefault;
        $page = min($page, max(0, $pages - 1));
        $currentPage = $page + 1;

        if (preg_match('~(<table class="pager-bubble".*?</table>)~si', $pagerHtml, $m)) {
            $pagerNav = $m[1];
        } else {
            $pagerNav = $pagerHtml;
        }

        $summary = 'Комментарии: ' . $count . ' <span style="opacity:.45;">•</span> Страница ' . $currentPage . ' из ' . $pages;
        $meta = '<span style="display:inline-flex;align-items:center;padding:7px 12px;border:1px solid rgba(125,141,160,.26);border-radius:999px;background:linear-gradient(180deg, rgba(255,255,255,.9), rgba(243,246,250,.92));box-shadow:0 1px 0 rgba(255,255,255,.7) inset;color:#6b7d92;font-size:12px;line-height:1.2;font-weight:700;max-width:100%;box-sizing:border-box;">' . $summary . '</span>';

        return '<div class="details-comments-pager" style="width:calc(100% - 24px);max-width:calc(100% - 24px);box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:0 auto 12px;padding:0 8px;">'
            . '<div class="details-comments-pager__meta" style="max-width:100%;">' . $meta . '</div>'
            . '<div class="details-comments-pager__nav">' . $pagerNav . '</div>'
            . '</div>';
    }
}

$id = (int)$id;
$limited = 10;
$commentsHaveThreads = details_comments_supports_threads();

// Авторизация (мягко)
$isLogged = isset($CURUSER) && is_array($CURUSER) && !empty($CURUSER['id']);
$uid      = $isLogged ? (int)$CURUSER['id'] : 0;

$commentsCount = details_cache_get("comments:count:t{$id}");
if (!is_int($commentsCount)) {
    $subres = $mysqli->query("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = {$id}");
    $subrow = $subres ? $subres->fetch_assoc() : ['cnt' => 0];
    $commentsCount = (int)$subrow['cnt'];
    details_cache_set("comments:count:t{$id}", $commentsCount, 60);
}

$commentsPages = max(1, (int)ceil($commentsCount / max(1, $limited)));
$commentsDefaultPage = $commentsCount > 0 ? max((int)floor(($commentsCount - 1) / max(1, $limited)), 0) : 0;
$commentsPage = isset($_GET['page']) ? max(0, (int)$_GET['page']) : $commentsDefaultPage;
$commentsPage = min($commentsPage, max(0, $commentsPages - 1));

$commentsCacheKey = tracker_cache_ns_key(
    tracker_comment_cache_namespace($id),
    'details-block',
    'page' . $commentsPage,
    'user' . ($isLogged ? $uid : 0),
    'class' . (int)get_user_class()
);

echo tracker_cache_render($commentsCacheKey, 60, static function () use (
    $commentsCount,
    $id,
    $isLogged,
    $uid,
    $limited,
    $commentsHaveThreads,
    $mysqli
): string {
    ob_start();

    echo '<div id="comments_list" class="comments-list">' . "\n";

    if ($commentsCount === 0) {
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

        echo "</div>\n";
        return (string)ob_get_clean();
    }

    [$pagertop, $pagerbottom, $limit] = pager($limited, $commentsCount, "details.php?id={$id}&", ['lastpagedefault' => 1]);
    $pagertop = details_comments_pager_wrap($pagertop, $commentsCount, $limited, true);
    $pagerbottom = details_comments_pager_wrap($pagerbottom, $commentsCount, $limited, true);

    $canrateCol = $isLogged
        ? ", (SELECT COUNT(*) FROM karma WHERE type = 'comment' AND value = c.id AND user = {$uid}) AS canrate"
        : "";
    $parentCol = $commentsHaveThreads ? "c.parent_id AS parent_id" : "0 AS parent_id";

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
            {$parentCol},
            u.avatar,
            u.warned,
            u.username,
            u.title,
            u.class,
            u.donor,
            u.downloaded,
            u.uploaded,
            u.karma         AS user_karma,
            u.profile_rating_bonus,
            u.gender,
            u.last_access,
            COALESCE(uts.torrents_count, 0)  AS torrents_count,
            COALESCE(uts.completed_count, 0) AS completed_count,
            e.username      AS editedbyname
            {$canrateCol}
        FROM comments AS c
        LEFT JOIN users AS u ON c.`user` = u.id
        LEFT JOIN (
            SELECT owner, COUNT(*) AS torrents_count, COALESCE(SUM(times_completed), 0) AS completed_count
            FROM torrents
            GROUP BY owner
        ) AS uts ON uts.owner = u.id
        LEFT JOIN users AS e ON c.editedby = e.id
        WHERE c.torrent = {$id}
        ORDER BY " . ($commentsHaveThreads ? "CASE WHEN c.parent_id = 0 THEN c.id ELSE c.parent_id END, c.parent_id, c.id" : "c.id") . " {$limit}";
    $subres = $mysqli->query($sql) or sqlerr(__FILE__, __LINE__);

    $allrows = [];
    while ($r = $subres->fetch_assoc()) {
        $allrows[] = $r;
    }

    echo '<table class="main" cellspacing="0" cellpadding="5" width="100%">';
    echo '<tr><td>';
    if (!empty($pagertop)) {
        echo '<div class="pager-wrap pager-wrap--top">', $pagertop, '</div>';
    }
    commenttable($allrows);
    echo '</td></tr><tr><td>';
    if (!empty($pagerbottom)) {
        echo '<div class="pager-wrap pager-wrap--bottom">', $pagerbottom, '</div>';
    }
    echo '</td></tr></table>';

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

    echo "</div>\n";
    return (string)ob_get_clean();
});

stdfoot();
