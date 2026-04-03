#!/usr/bin/env php
<?php
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '80';

chdir(dirname(__DIR__));

require_once __DIR__ . '/../include/bittorrent.php';

dbconn(false);

$batchSize = isset($argv[1]) ? max(1, min(1000, (int)$argv[1])) : 200;
$startedAt = microtime(true);

echo "Rebuilding torrent search index with batch size {$batchSize}\n";

$rebuilt = tracker_rebuild_torrent_search_indexes($batchSize, static function (int $torrentId, int $count): void {
    if ($count === 1 || $count % 50 === 0) {
        echo "  rebuilt {$count}, last torrent #{$torrentId}\n";
    }
});

tracker_invalidate_torrent_cache(0, true);

$elapsed = microtime(true) - $startedAt;
echo "Done. Rebuilt {$rebuilt} torrents in " . number_format($elapsed, 2, '.', '') . "s\n";
