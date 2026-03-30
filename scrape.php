<?php
declare(strict_types=1);

require_once __DIR__ . '/include/secrets.php';
require_once __DIR__ . '/include/cache.php';
require_once __DIR__ . '/include/benc.php';

const SCRAPE_MAX_HASHES = 60;
const SCRAPE_TTL = 60;

function scrape_fail(string $reason): void
{
    scrape_send([
        'failure reason' => [
            'type' => 'string',
            'value' => $reason,
        ],
    ]);
}

function scrape_send(array $payload): void
{
    $encoded = benc([
        'type' => 'dictionary',
        'value' => $payload,
    ]);

    header('Content-Type: text/plain');
    header('Pragma: no-cache');
    echo $encoded ?? '';
    exit;
}

function scrape_requested_hashes(): array
{
    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString === '') {
        return [];
    }

    $hashes = [];
    foreach (explode('&', $queryString) as $pair) {
        if ($pair === '') {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        if ($key !== 'info_hash') {
            continue;
        }

        $binary = rawurldecode($value);
        if (strlen($binary) !== 20) {
            scrape_fail('Invalid info_hash');
        }

        $hashes[bin2hex($binary)] = true;
        if (count($hashes) > SCRAPE_MAX_HASHES) {
            scrape_fail('Too many info_hash values');
        }
    }

    return array_keys($hashes);
}

function scrape_fetch_stats(mysqli $mysqli, array $hashesHex): array
{
    $stats = [];
    $missing = [];

    foreach ($hashesHex as $hashHex) {
        $cacheKey = tracker_cache_key('scrape', 'torrent', $hashHex);
        $cached = tracker_cache_get($cacheKey, $hit);
        if ($hit) {
            if (is_array($cached) && $cached !== []) {
                $stats[$hashHex] = $cached;
            }
            continue;
        }

        $missing[] = $hashHex;
    }

    if ($missing) {
        $escaped = array_map(
            static fn(string $hashHex): string => "UNHEX('" . $mysqli->real_escape_string($hashHex) . "')",
            $missing
        );

        $sql = "
            SELECT
                LOWER(HEX(info_hash)) AS info_hash_hex,
                seeders,
                leechers,
                times_completed
            FROM torrents
            WHERE info_hash IN (" . implode(', ', $escaped) . ")
        ";

        $res = $mysqli->query($sql);
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $hashHex = (string)($row['info_hash_hex'] ?? '');
                if ($hashHex === '') {
                    continue;
                }

                $stats[$hashHex] = [
                    'seeders' => (int)($row['seeders'] ?? 0),
                    'leechers' => (int)($row['leechers'] ?? 0),
                    'times_completed' => (int)($row['times_completed'] ?? 0),
                ];
            }
            $res->free();
        }

        foreach ($missing as $hashHex) {
            $cacheKey = tracker_cache_key('scrape', 'torrent', $hashHex);
            if (isset($stats[$hashHex])) {
                tracker_cache_set($cacheKey, $stats[$hashHex], SCRAPE_TTL);
            } else {
                tracker_cache_set($cacheKey, [], 30);
            }
        }
    }

    return $stats;
}

$hashesHex = scrape_requested_hashes();
if (!$hashesHex) {
    scrape_fail('Full scrape is disabled. Use multiscrape with info_hash.');
}

if (!($mysqli instanceof mysqli)) {
    scrape_fail('Tracker database is unavailable');
}

$stats = scrape_fetch_stats($mysqli, $hashesHex);
$files = [];

foreach ($hashesHex as $hashHex) {
    if (!isset($stats[$hashHex])) {
        continue;
    }

    $row = $stats[$hashHex];
    $files[pack('H*', $hashHex)] = [
        'type' => 'dictionary',
        'value' => [
            'complete' => [
                'type' => 'integer',
                'value' => (int)$row['seeders'],
            ],
            'downloaded' => [
                'type' => 'integer',
                'value' => (int)$row['times_completed'],
            ],
            'incomplete' => [
                'type' => 'integer',
                'value' => (int)$row['leechers'],
            ],
        ],
    ];
}

scrape_send([
    'files' => [
        'type' => 'dictionary',
        'value' => $files,
    ],
]);
