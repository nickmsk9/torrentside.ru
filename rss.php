<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

gzip();
dbconn(false);

function rss_exit_forbidden(): void
{
    http_response_code(403);
    exit;
}

function rss_trim_text(string $text, int $limit = 1200): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit)) . '...';
}

$passkey = trim((string)($_GET['passkey'] ?? ''));
$feed = ((string)($_GET['feed'] ?? 'web')) === 'dl' ? 'dl' : 'web';

if ($passkey !== '') {
    if (!preg_match('/^[a-f0-9]{32,64}$/i', $passkey)) {
        rss_exit_forbidden();
    }

    $rssUser = tracker_cache_remember(
        tracker_cache_key('rss', 'passkey', $passkey),
        300,
        static function () use ($passkey): ?array {
            global $mysqli;

            if (!($mysqli instanceof mysqli)) {
                return null;
            }

            $stmt = $mysqli->prepare("
                SELECT id, passkey
                FROM users
                WHERE passkey = ?
                  AND enabled = 'yes'
                  AND status = 'confirmed'
                LIMIT 1
            ");
            if (!$stmt) {
                return null;
            }

            $stmt->bind_param('s', $passkey);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
            $stmt->close();

            return is_array($row) ? $row : null;
        }
    );

    if (!is_array($rssUser) || empty($rssUser['id'])) {
        rss_exit_forbidden();
    }
} else {
    loggedinorreturn();
}

$cats = [];
if (!empty($_GET['cat'])) {
    foreach (explode(',', (string)$_GET['cat']) as $catId) {
        $catId = (int)$catId;
        if ($catId > 0) {
            $cats[$catId] = $catId;
        }
    }
}
$catIds = array_values($cats);

$authKey = $passkey !== '' ? 'pk:' . md5($passkey) : 'cookie';
$rssCacheKey = tracker_cache_key('rss', 'feed', $feed, $authKey, $catIds ?: 'all');

$xml = tracker_cache_render($rssCacheKey, 120, static function () use ($catIds, $feed, $passkey): string {
    global $mysqli, $SITENAME, $DEFAULTBASEURL, $SITEEMAIL;

    $where = ["t.visible = 'yes'"];
    if (!empty($catIds)) {
        $where[] = 't.category IN (' . implode(', ', array_map('intval', $catIds)) . ')';
    }

    $sql = "
        SELECT
            t.id,
            t.name,
            t.descr,
            t.filename,
            t.size,
            t.category,
            t.seeders,
            t.leechers,
            t.added,
            t.times_completed,
            c.name AS category_name
        FROM torrents AS t
        LEFT JOIN categories AS c ON c.id = t.category
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.id DESC
        LIMIT 15
    ";

    $rows = [];
    $torrentIds = [];
    $res = sql_query($sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['size'] = (int)($row['size'] ?? 0);
        $row['seeders'] = (int)($row['seeders'] ?? 0);
        $row['leechers'] = (int)($row['leechers'] ?? 0);
        $row['times_completed'] = (int)($row['times_completed'] ?? 0);
        $rows[] = $row;
        if ($row['id'] > 0) {
            $torrentIds[] = $row['id'];
        }
    }

    $peerDownloaded = [];
    if ($torrentIds) {
        $peerSql = "
            SELECT torrent, SUM(downloaded) AS downloading
            FROM peers
            WHERE seeder = 'no'
              AND torrent IN (" . implode(', ', array_map('intval', $torrentIds)) . ")
            GROUP BY torrent
        ";
        $peerRes = sql_query($peerSql);
        while ($peerRes && ($peerRow = mysqli_fetch_assoc($peerRes))) {
            $peerDownloaded[(int)$peerRow['torrent']] = (float)($peerRow['downloading'] ?? 0);
        }
    }

    $siteNameEsc = htmlspecialchars((string)$SITENAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $siteEmailEsc = htmlspecialchars((string)$SITEEMAIL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $baseUrlEsc = htmlspecialchars((string)$DEFAULTBASEURL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $out .= "<rss version=\"2.0\">\n<channel>\n";
    $out .= "<title>{$siteNameEsc}</title>\n";
    $out .= "<link>{$baseUrlEsc}</link>\n";
    $out .= "<description>RSS Feeds</description>\n";
    $out .= "<language>ru-ru</language>\n";
    $out .= "<copyright>Copyright " . date('Y') . " {$siteNameEsc}</copyright>\n";
    $out .= "<webMaster>{$siteEmailEsc}</webMaster>\n";

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $filename = rawurlencode((string)($row['filename'] ?? 'torrent'));
        $link = $feed === 'dl'
            ? $DEFAULTBASEURL . '/download.php/' . $id . '/' . ($passkey !== '' ? $passkey . '/' : '') . $filename
            : $DEFAULTBASEURL . '/details.php?id=' . $id . '&hit=1';

        $seeders = (int)$row['seeders'];
        $leechers = (int)$row['leechers'];
        $completed = (int)$row['times_completed'];
        $ageSeconds = max(1, time() - strtotime((string)$row['added']));
        $trafficBytes = ((float)$row['size'] * $completed) + ($peerDownloaded[$id] ?? 0.0);
        $totalSpeed = $trafficBytes > 0 ? mksize((int)max(1, $trafficBytes / $ageSeconds)) . '/s' : 'нет траффика';

        if ($seeders > 0) {
            $seedersLabel = $seeders . ' раздающих';
        } else {
            $seedersLabel = 'нет раздающих';
        }

        if ($leechers > 0) {
            $leechersLabel = $leechers . ' качающих';
        } else {
            $leechersLabel = 'нет качающих';
        }

        $descrHtml = format_comment(rss_trim_text((string)($row['descr'] ?? '')));
        $description = "Категория: " . ((string)($row['category_name'] ?? 'Без категории')) .
            "\nРазмер: " . mksize((int)$row['size']) .
            "\nСтатус: {$seedersLabel} и {$leechersLabel}" .
            "\nСкорость: {$totalSpeed}" .
            "\nДобавлен: " . (string)$row['added'] .
            "\nОписание:\n{$descrHtml}";

        $title = (string)($row['name'] ?? 'Без названия');
        $out .= "<item>\n";
        $out .= "<title><![CDATA[{$title}]]></title>\n";
        $out .= "<link>" . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</link>\n";
        $out .= "<guid isPermaLink=\"true\">" . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</guid>\n";
        $out .= "<pubDate>" . gmdate(DATE_RSS, strtotime((string)$row['added'])) . "</pubDate>\n";
        $out .= "<description><![CDATA[{$description}]]></description>\n";
        $out .= "</item>\n";
    }

    $out .= "</channel>\n</rss>\n";
    return $out;
});

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: private, max-age=120');
echo $xml;
