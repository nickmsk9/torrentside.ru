<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/multitracker.php';

dbconn(false);
multitracker_ensure_schema();

$limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : multitracker_scrape_limit();
$processed = multitracker_refresh_due_stats($limit);

if (PHP_SAPI === 'cli') {
    echo "Processed: {$processed}\n";
    exit;
}

stdhead('Обновление внешних трекеров');
begin_frame('Обновление внешних трекеров');
echo '<div style="padding:10px">Обновлено трекеров: <b>' . (int)$processed . '</b></div>';
end_frame();
stdfoot();
