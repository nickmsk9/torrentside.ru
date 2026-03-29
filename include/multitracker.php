<?php

if (!function_exists('bdec')) {
    require_once __DIR__ . '/benc.php';
}

if (!function_exists('multitracker_ensure_schema')) {
    function multitracker_ensure_schema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        sql_query("
            CREATE TABLE IF NOT EXISTS torrent_external_trackers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                torrent_id INT UNSIGNED NOT NULL,
                tracker_url VARCHAR(1024) NOT NULL,
                tracker_hash CHAR(40) NOT NULL,
                source_name VARCHAR(255) NOT NULL DEFAULT '',
                is_local TINYINT(1) NOT NULL DEFAULT 0,
                position INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_torrent_tracker (torrent_id, tracker_hash),
                KEY idx_torrent (torrent_id),
                KEY idx_local (is_local)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        sql_query("
            CREATE TABLE IF NOT EXISTS torrent_external_tracker_stats (
                tracker_id INT UNSIGNED NOT NULL,
                torrent_id INT UNSIGNED NOT NULL,
                seeders INT UNSIGNED NOT NULL DEFAULT 0,
                leechers INT UNSIGNED NOT NULL DEFAULT 0,
                completed INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                last_error VARCHAR(255) NOT NULL DEFAULT '',
                fetched_at DATETIME NOT NULL,
                PRIMARY KEY (tracker_id),
                KEY idx_torrent_fetched (torrent_id, fetched_at),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $ready = true;
    }
}

if (!function_exists('multitracker_primary_announce')) {
    function multitracker_primary_announce(): string
    {
        global $announce_urls;

        if (!empty($announce_urls[0]) && is_string($announce_urls[0])) {
            return trim($announce_urls[0]);
        }

        return rtrim((string)DEFAULTBASEURL, '/') . '/announce.php';
    }
}

if (!function_exists('multitracker_normalize_url')) {
    function multitracker_normalize_url(string $url): string
    {
        return trim(str_replace(["\r", "\n", "\t"], '', $url));
    }
}

if (!function_exists('multitracker_is_private_tracker_host')) {
    function multitracker_is_private_tracker_host(string $url): bool
    {
        $parsed = parse_url(multitracker_normalize_url($url));
        $host = strtolower((string)($parsed['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', 'retracker.local'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_match('#^(10\\.|127\\.|192\\.168\\.|172\\.(1[6-9]|2[0-9]|3[0-1])\\.)#', $host) === 1;
        }

        return false;
    }
}

if (!function_exists('multitracker_extract_urls_from_dict')) {
    function multitracker_extract_urls_from_dict(array $dict): array
    {
        $out = [];

        if (isset($dict['value']['announce']['type'], $dict['value']['announce']['value']) && $dict['value']['announce']['type'] === 'string') {
            $out[] = multitracker_normalize_url((string)$dict['value']['announce']['value']);
        }

        if (isset($dict['value']['announce-list']['type'], $dict['value']['announce-list']['value']) && $dict['value']['announce-list']['type'] === 'list') {
            foreach ($dict['value']['announce-list']['value'] as $tier) {
                if (($tier['type'] ?? '') !== 'list' || empty($tier['value']) || !is_array($tier['value'])) {
                    continue;
                }
                foreach ($tier['value'] as $item) {
                    if (($item['type'] ?? '') === 'string' && !empty($item['value'])) {
                        $out[] = multitracker_normalize_url((string)$item['value']);
                    }
                }
            }
        }

        $out = array_values(array_filter(array_unique($out), static function ($url): bool {
            return is_string($url) && $url !== '';
        }));

        return $out;
    }
}

if (!function_exists('multitracker_is_local_tracker')) {
    function multitracker_is_local_tracker(string $url): bool
    {
        global $announce_urls;

        $url = multitracker_normalize_url($url);
        if ($url === '') {
            return false;
        }

        $localPool = [multitracker_primary_announce()];
        if (!empty($announce_urls) && is_array($announce_urls)) {
            $localPool = array_merge($localPool, $announce_urls);
        }

        foreach ($localPool as $localUrl) {
            if (!is_string($localUrl) || $localUrl === '') {
                continue;
            }
            if (strcasecmp(multitracker_normalize_url($localUrl), $url) === 0) {
                return true;
            }
        }

        $base = parse_url((string)DEFAULTBASEURL);
        $parsed = parse_url($url);
        if (!$base || !$parsed) {
            return false;
        }

        $baseHost = strtolower((string)($base['host'] ?? ''));
        $host = strtolower((string)($parsed['host'] ?? ''));
        $path = (string)($parsed['path'] ?? '');

        return $baseHost !== '' && $host === $baseHost && str_ends_with($path, '/announce.php');
    }
}

if (!function_exists('multitracker_build_announce_list_node')) {
    function multitracker_build_announce_list_node(array $urls): array
    {
        $node = ['type' => 'list', 'value' => []];
        foreach ($urls as $url) {
            $url = multitracker_normalize_url((string)$url);
            if ($url === '') {
                continue;
            }
            $node['value'][] = [
                'type' => 'list',
                'value' => [[
                    'type' => 'string',
                    'value' => $url,
                    'strlen' => strlen($url),
                    'string' => strlen($url) . ':' . $url,
                ]],
            ];
        }

        return $node;
    }
}

if (!function_exists('multitracker_prepare_uploaded_torrent')) {
    function multitracker_prepare_uploaded_torrent(array $dict, bool $preserveInfo): array
    {
        global $CURUSER, $DEFAULTBASEURL, $SITENAME;

        $primaryAnnounce = multitracker_primary_announce();
        $originalTrackers = multitracker_extract_urls_from_dict($dict);
        $externalTrackers = array_values(array_filter($originalTrackers, static function (string $url): bool {
            return !multitracker_is_local_tracker($url);
        }));

        $allTrackers = array_values(array_unique(array_filter(array_merge([$primaryAnnounce], $externalTrackers))));

        $dict['value']['announce'] = bdec(benc_str($primaryAnnounce));
        if ($allTrackers) {
            $dict['value']['announce-list'] = multitracker_build_announce_list_node($allTrackers);
        }

        unset($dict['value']['nodes'], $dict['value']['azureus_properties']);

        if (!$preserveInfo) {
            $dict['value']['info']['value']['private'] = bdec('i1e');
            $dict['value']['info']['value']['source']  = bdec(benc_str("[$DEFAULTBASEURL] $SITENAME"));
            unset(
                $dict['value']['info']['value']['crc32'],
                $dict['value']['info']['value']['ed2k'],
                $dict['value']['info']['value']['md5sum'],
                $dict['value']['info']['value']['sha1'],
                $dict['value']['info']['value']['tiger']
            );
        }

        $dict = bdec(benc($dict));
        $dict['value']['comment']             = bdec(benc_str("Торрент создан для '$SITENAME'"));
        $dict['value']['created by']          = bdec(benc_str((string)$CURUSER['username']));
        $dict['value']['publisher']           = bdec(benc_str((string)$CURUSER['username']));
        $dict['value']['publisher.utf-8']     = bdec(benc_str((string)$CURUSER['username']));
        $dict['value']['publisher-url']       = bdec(benc_str("$DEFAULTBASEURL/userdetails.php?id={$CURUSER['id']}"));
        $dict['value']['publisher-url.utf-8'] = bdec(benc_str("$DEFAULTBASEURL/userdetails.php?id={$CURUSER['id']}"));

        $infoNode = $dict['value']['info'] ?? null;
        if (!is_array($infoNode)) {
            stderr('Ошибка', 'Не удалось прочитать секцию info в torrent-файле.');
            exit;
        }

        $infoRaw = (string)($infoNode['string'] ?? benc($infoNode));
        $infoHash = sha1($infoRaw);

        return [
            'dict' => $dict,
            'infohash' => $infoHash,
            'external_trackers' => $externalTrackers,
            'all_trackers' => $allTrackers,
            'preserve_info' => $preserveInfo,
        ];
    }
}

if (!function_exists('multitracker_save_trackers')) {
    function multitracker_save_trackers(int $torrentId, array $urls): void
    {
        global $mysqli;

        multitracker_ensure_schema();
        $torrentId = (int)$torrentId;
        if ($torrentId <= 0) {
            return;
        }

        $mysqli->query("DELETE FROM torrent_external_tracker_stats WHERE torrent_id = {$torrentId}");
        $mysqli->query("DELETE FROM torrent_external_trackers WHERE torrent_id = {$torrentId}");

        $urls = array_values(array_unique(array_filter(array_map('multitracker_normalize_url', $urls))));
        if (!$urls) {
            return;
        }

        $now = get_date_time();
        $stmt = $mysqli->prepare("
            INSERT INTO torrent_external_trackers
                (torrent_id, tracker_url, tracker_hash, source_name, is_local, position, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($urls as $idx => $url) {
            $parsed = parse_url($url);
            $sourceName = (string)($parsed['host'] ?? 'tracker');
            $isLocal = multitracker_is_local_tracker($url) ? 1 : 0;
            if (!$isLocal && multitracker_is_private_tracker_host($url)) {
                continue;
            }
            $hash = sha1($url);
            $pos = $idx + 1;
            $stmt->bind_param('isssiiss', $torrentId, $url, $hash, $sourceName, $isLocal, $pos, $now, $now);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('multitracker_get_stored_trackers')) {
    function multitracker_get_stored_trackers(int $torrentId): array
    {
        multitracker_ensure_schema();

        $torrentId = (int)$torrentId;
        if ($torrentId <= 0) {
            return [];
        }

        $res = sql_query("
            SELECT tracker_url
            FROM torrent_external_trackers
            WHERE torrent_id = {$torrentId}
            ORDER BY is_local DESC, position ASC, id ASC
        ");

        $out = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $url = multitracker_normalize_url((string)($row['tracker_url'] ?? ''));
            if ($url !== '') {
                $out[] = $url;
            }
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('multitracker_tracker_urls_for_download')) {
    function multitracker_tracker_urls_for_download(array $urls, string $passkey): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = multitracker_normalize_url((string)$url);
            if ($url === '') {
                continue;
            }
            if (multitracker_is_local_tracker($url) && preg_match('#^https?://#i', $url)) {
                $sep = str_contains($url, '?') ? '&' : '?';
                $url .= $sep . 'passkey=' . $passkey;
            }
            $out[] = $url;
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('multitracker_stats_summary_sql')) {
    function multitracker_stats_summary_sql(string $torrentAlias = 'torrents'): string
    {
        multitracker_ensure_schema();

        return "LEFT JOIN (
            SELECT
                torrent_id,
                MAX(CASE WHEN status = 'ok' THEN seeders ELSE 0 END) AS external_seeders,
                MAX(CASE WHEN status = 'ok' THEN leechers ELSE 0 END) AS external_leechers,
                MAX(CASE WHEN status = 'ok' THEN completed ELSE 0 END) AS external_completed,
                MAX(fetched_at) AS external_fetched_at
            FROM torrent_external_tracker_stats
            GROUP BY torrent_id
        ) mts ON mts.torrent_id = {$torrentAlias}.id";
    }
}

if (!function_exists('multitracker_get_tracker_details')) {
    function multitracker_get_tracker_details(int $torrentId): array
    {
        multitracker_ensure_schema();

        $torrentId = (int)$torrentId;
        if ($torrentId <= 0) {
            return [];
        }

        $res = sql_query("
            SELECT
                tet.tracker_url,
                tet.source_name,
                tet.is_local,
                COALESCE(stats.seeders, 0) AS seeders,
                COALESCE(stats.leechers, 0) AS leechers,
                COALESCE(stats.completed, 0) AS completed,
                COALESCE(stats.status, 'pending') AS status,
                COALESCE(stats.last_error, '') AS last_error,
                stats.fetched_at
            FROM torrent_external_trackers tet
            LEFT JOIN torrent_external_tracker_stats stats ON stats.tracker_id = tet.id
            WHERE tet.torrent_id = {$torrentId}
            ORDER BY tet.is_local DESC, tet.position ASC, tet.id ASC
        ");

        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('multitracker_translate_status')) {
    function multitracker_translate_status(string $status, bool $isLocal = false, string $lastError = ''): string
    {
        if ($isLocal) {
            return 'локальный';
        }

        $status = strtolower(trim($status));
        $lastError = trim($lastError);

        return match ($status) {
            'ok' => 'получено',
            'pending' => 'ожидает',
            'unsupported' => 'не поддерживается',
            'skipped' => 'пропущен',
            'error' => ($lastError !== '' ? 'ошибка' : 'недоступен'),
            default => ($status !== '' ? $status : 'неизвестно'),
        };
    }
}

if (!function_exists('multitracker_stats_ttl')) {
    function multitracker_stats_ttl(): int
    {
        global $external_tracker_stats_ttl;
        return max(300, (int)($external_tracker_stats_ttl ?? 1800));
    }
}

if (!function_exists('multitracker_http_timeout')) {
    function multitracker_http_timeout(): int
    {
        global $external_tracker_http_timeout;
        return max(2, min(20, (int)($external_tracker_http_timeout ?? 4)));
    }
}

if (!function_exists('multitracker_scrape_limit')) {
    function multitracker_scrape_limit(): int
    {
        global $external_tracker_scrape_limit;
        return max(1, min(50, (int)($external_tracker_scrape_limit ?? 5)));
    }
}

if (!function_exists('multitracker_hex_to_binary')) {
    function multitracker_hex_to_binary(string $hex): string
    {
        $hex = strtolower(trim($hex));
        if (strlen($hex) !== 40 || !ctype_xdigit($hex)) {
            return '';
        }
        $bin = @hex2bin($hex);
        return $bin === false ? '' : $bin;
    }
}

if (!function_exists('multitracker_scrape_candidates')) {
    function multitracker_scrape_candidates(string $announceUrl, string $infoHashHex): array
    {
        $announceUrl = multitracker_normalize_url($announceUrl);
        $rawHash = multitracker_hex_to_binary($infoHashHex);
        if ($announceUrl === '' || $rawHash === '' || !preg_match('#^https?://#i', $announceUrl)) {
            return [];
        }

        $encodedHash = rawurlencode($rawHash);
        $candidates = [];
        $parsed = parse_url($announceUrl);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return [];
        }

        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        $query['info_hash'] = $rawHash;

        $path = (string)($parsed['path'] ?? '');
        $candidatePaths = [];
        if ($path !== '') {
            $candidatePaths[] = preg_replace('#announce\.php$#i', 'scrape.php', $path);
            $candidatePaths[] = preg_replace('#/announce$#i', '/scrape', $path);
            $candidatePaths[] = preg_replace('#/ann$#i', '/scrape', $path);
            $candidatePaths[] = preg_replace('#/announce[^/]*$#i', '/scrape', $path);
            $candidatePaths[] = $path;
        }

        $candidatePaths = array_values(array_unique(array_filter($candidatePaths)));

        foreach ($candidatePaths as $candidatePath) {
            $base = $parsed['scheme'] . '://' . $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '') . $candidatePath;
            $pairs = [];
            foreach ($query as $key => $value) {
                if ($key === 'info_hash') {
                    $pairs[] = 'info_hash=' . $encodedHash;
                } else {
                    $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
                }
            }
            $candidates[] = $base . (str_contains($base, '?') ? '&' : '?') . implode('&', $pairs);
        }

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('multitracker_announce_probe_url')) {
    function multitracker_announce_probe_url(string $announceUrl, string $infoHashHex): string
    {
        $announceUrl = multitracker_normalize_url($announceUrl);
        $rawHash = multitracker_hex_to_binary($infoHashHex);
        if ($announceUrl === '' || $rawHash === '' || !preg_match('#^https?://#i', $announceUrl)) {
            return '';
        }

        $parsed = parse_url($announceUrl);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return '';
        }

        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $peerSeed = substr(sha1($infoHashHex . '|probe'), 0, 12);
        $peerId = '-TSMT01-' . str_pad($peerSeed, 12, '0');

        $query['info_hash'] = $rawHash;
        $query['peer_id'] = $peerId;
        $query['port'] = 6881;
        $query['uploaded'] = 0;
        $query['downloaded'] = 0;
        $query['left'] = 1;
        $query['compact'] = 1;
        $query['no_peer_id'] = 1;
        $query['numwant'] = 0;
        $query['event'] = 'started';

        $pairs = [];
        foreach ($query as $key => $value) {
            if ($key === 'info_hash') {
                $pairs[] = 'info_hash=' . rawurlencode($rawHash);
            } else {
                $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
            }
        }

        return $announceUrl . (str_contains($announceUrl, '?') ? '&' : '?') . implode('&', $pairs);
    }
}

if (!function_exists('multitracker_parse_failure_reason')) {
    function multitracker_parse_failure_reason(string $body): string
    {
        $decoded = bdec($body);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'dictionary') {
            return '';
        }

        $failure = trim((string)(($decoded['value']['failure reason']['value'] ?? '')));
        if ($failure !== '') {
            return $failure;
        }

        $warning = trim((string)(($decoded['value']['warning message']['value'] ?? '')));
        return $warning;
    }
}

if (!function_exists('multitracker_http_get')) {
    function multitracker_http_get(string $url, array $headers = [], string $cookie = ''): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL недоступен'];
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => multitracker_http_timeout(),
            CURLOPT_TIMEOUT => multitracker_http_timeout(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
        ];

        global $kinozal_user_agent;
        $ua = trim((string)($kinozal_user_agent ?? 'TorrentSide External Tracker Bot/1.0'));
        $opts[CURLOPT_USERAGENT] = $ua;

        if ($headers) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        if ($cookie !== '') {
            $opts[CURLOPT_COOKIE] = $cookie;
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'ok' => ($body !== false && $status >= 200 && $status < 400),
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => $error,
        ];
    }
}

if (!function_exists('multitracker_parse_scrape_stats')) {
    function multitracker_parse_scrape_stats(string $body, string $infoHashHex): ?array
    {
        $decoded = bdec($body);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'dictionary') {
            return null;
        }

        $filesNode = $decoded['value']['files'] ?? null;
        if (!is_array($filesNode) || ($filesNode['type'] ?? '') !== 'dictionary') {
            return null;
        }

        $rawHash = multitracker_hex_to_binary($infoHashHex);
        if ($rawHash === '') {
            return null;
        }

        $statsNode = $filesNode['value'][$rawHash] ?? null;
        if (!is_array($statsNode)) {
            foreach (($filesNode['value'] ?? []) as $key => $node) {
                if (bin2hex((string)$key) === strtolower($infoHashHex)) {
                    $statsNode = $node;
                    break;
                }
            }
        }

        if (!is_array($statsNode) || ($statsNode['type'] ?? '') !== 'dictionary') {
            return null;
        }

        $val = $statsNode['value'];
        $seeders = (int)(($val['complete']['value'] ?? $val['seeders']['value']) ?? 0);
        $leechers = (int)(($val['incomplete']['value'] ?? $val['leechers']['value']) ?? 0);
        $completed = (int)(($val['downloaded']['value'] ?? $val['completed']['value']) ?? 0);

        return [
            'seeders' => max(0, $seeders),
            'leechers' => max(0, $leechers),
            'completed' => max(0, $completed),
        ];
    }
}

if (!function_exists('multitracker_parse_announce_stats')) {
    function multitracker_parse_announce_stats(string $body): ?array
    {
        $decoded = bdec($body);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'dictionary') {
            return null;
        }

        $val = $decoded['value'] ?? [];
        $completeNode = $val['complete'] ?? ($val['seeders'] ?? null);
        $incompleteNode = $val['incomplete'] ?? ($val['leechers'] ?? null);
        $downloadedNode = $val['downloaded'] ?? ($val['completed'] ?? null);

        $seeders = (int)(is_array($completeNode) ? ($completeNode['value'] ?? 0) : 0);
        $leechers = (int)(is_array($incompleteNode) ? ($incompleteNode['value'] ?? 0) : 0);
        $completed = (int)(is_array($downloadedNode) ? ($downloadedNode['value'] ?? 0) : 0);

        if ($seeders <= 0 && $leechers <= 0 && $completed <= 0) {
            return null;
        }

        return [
            'seeders' => max(0, $seeders),
            'leechers' => max(0, $leechers),
            'completed' => max(0, $completed),
        ];
    }
}

if (!function_exists('multitracker_fetch_tracker_stats')) {
    function multitracker_fetch_tracker_stats(string $trackerUrl, string $infoHashHex): array
    {
        $trackerUrl = multitracker_normalize_url($trackerUrl);
        if ($trackerUrl === '') {
            return ['ok' => false, 'status' => 'unsupported', 'error' => 'Пустой URL трекера'];
        }

        if (multitracker_is_local_tracker($trackerUrl)) {
            return ['ok' => false, 'status' => 'skipped', 'error' => 'Локальный трекер считается отдельно'];
        }

        if (multitracker_is_private_tracker_host($trackerUrl)) {
            return ['ok' => false, 'status' => 'unsupported', 'error' => 'Локальный адрес внешнего трекера недоступен'];
        }

        if (!preg_match('#^https?://#i', $trackerUrl)) {
            return ['ok' => false, 'status' => 'unsupported', 'error' => 'Поддерживается только HTTP/HTTPS scrape'];
        }

        $candidates = multitracker_scrape_candidates($trackerUrl, $infoHashHex);
        if (!$candidates) {
            return ['ok' => false, 'status' => 'unsupported', 'error' => 'Не удалось построить scrape URL'];
        }

        $lastError = 'Нет ответа от внешнего трекера';
        foreach ($candidates as $candidate) {
            $resp = multitracker_http_get($candidate);
            if (!$resp['ok']) {
                $lastError = $resp['error'] !== '' ? $resp['error'] : ('HTTP ' . $resp['status']);
                continue;
            }

            $stats = multitracker_parse_scrape_stats((string)$resp['body'], $infoHashHex);
            if ($stats !== null) {
                $stats['ok'] = true;
                $stats['status'] = 'ok';
                $stats['error'] = '';
                return $stats;
            }

            $announceStats = multitracker_parse_announce_stats((string)$resp['body']);
            if ($announceStats !== null) {
                $announceStats['ok'] = true;
                $announceStats['status'] = 'ok';
                $announceStats['error'] = '';
                return $announceStats;
            }

            $failureReason = multitracker_parse_failure_reason((string)$resp['body']);
            if ($failureReason !== '') {
                $lastError = $failureReason;
                continue;
            }

            $lastError = 'Трекер не поддерживает scrape или вернул нестандартный ответ';
        }

        $unsupportedMarkers = [
            'does not support scrape',
            'unsupported',
            'not support',
            'scrape',
            'нестандартный ответ',
        ];

        $normalizedError = mb_strtolower($lastError, 'UTF-8');
        if (mb_strpos($normalizedError, 'unregistered torrent', 0, 'UTF-8') !== false) {
            return ['ok' => false, 'status' => 'error', 'error' => 'Внешний трекер не знает этот info_hash (unregistered torrent)'];
        }
        foreach ($unsupportedMarkers as $marker) {
            if (mb_strpos($normalizedError, mb_strtolower($marker, 'UTF-8')) !== false) {
                $announceUrl = multitracker_announce_probe_url($trackerUrl, $infoHashHex);
                if ($announceUrl !== '') {
                    $resp = multitracker_http_get($announceUrl);
                    if ($resp['ok']) {
                        $announceStats = multitracker_parse_announce_stats((string)$resp['body']);
                        if ($announceStats !== null) {
                            $announceStats['ok'] = true;
                            $announceStats['status'] = 'ok';
                            $announceStats['error'] = '';
                            return $announceStats;
                        }
                    }
                }

                return ['ok' => false, 'status' => 'unsupported', 'error' => $lastError];
            }
        }

        $announceUrl = multitracker_announce_probe_url($trackerUrl, $infoHashHex);
        if ($announceUrl !== '') {
            $resp = multitracker_http_get($announceUrl);
            if ($resp['ok']) {
                $announceStats = multitracker_parse_announce_stats((string)$resp['body']);
                if ($announceStats !== null) {
                    $announceStats['ok'] = true;
                    $announceStats['status'] = 'ok';
                    $announceStats['error'] = '';
                    return $announceStats;
                }

                $failureReason = multitracker_parse_failure_reason((string)$resp['body']);
                if ($failureReason !== '') {
                    return ['ok' => false, 'status' => 'error', 'error' => $failureReason];
                }
            }
        }

        return ['ok' => false, 'status' => 'error', 'error' => $lastError];
    }
}

if (!function_exists('multitracker_store_tracker_stats')) {
    function multitracker_store_tracker_stats(int $trackerId, int $torrentId, array $stats): void
    {
        global $mysqli;

        multitracker_ensure_schema();

        $trackerId = (int)$trackerId;
        $torrentId = (int)$torrentId;
        $seeders = (int)($stats['seeders'] ?? 0);
        $leechers = (int)($stats['leechers'] ?? 0);
        $completed = (int)($stats['completed'] ?? 0);
        $status = (string)($stats['status'] ?? 'pending');
        $error = mb_substr((string)($stats['error'] ?? ''), 0, 255);
        $now = get_date_time();

        $stmt = $mysqli->prepare("
            INSERT INTO torrent_external_tracker_stats
                (tracker_id, torrent_id, seeders, leechers, completed, status, last_error, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                torrent_id = VALUES(torrent_id),
                seeders = VALUES(seeders),
                leechers = VALUES(leechers),
                completed = VALUES(completed),
                status = VALUES(status),
                last_error = VALUES(last_error),
                fetched_at = VALUES(fetched_at)
        ");
        $stmt->bind_param('iiiiisss', $trackerId, $torrentId, $seeders, $leechers, $completed, $status, $error, $now);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('multitracker_refresh_due_stats')) {
    function multitracker_refresh_due_stats(?int $limit = null): int
    {
        multitracker_ensure_schema();

        $ttl = multitracker_stats_ttl();
        $limit = $limit ?? multitracker_scrape_limit();
        $limit = max(1, min(50, (int)$limit));

        $res = sql_query("
            SELECT
                tet.id,
                tet.torrent_id,
                tet.tracker_url,
                t.info_hash,
                stats.fetched_at
            FROM torrent_external_trackers tet
            INNER JOIN torrents t ON t.id = tet.torrent_id
            LEFT JOIN torrent_external_tracker_stats stats ON stats.tracker_id = tet.id
            WHERE tet.is_local = 0
              AND (
                    stats.fetched_at IS NULL
                 OR stats.fetched_at < DATE_SUB(NOW(), INTERVAL {$ttl} SECOND)
              )
            ORDER BY COALESCE(stats.fetched_at, '1970-01-01 00:00:00') ASC, tet.id ASC
            LIMIT {$limit}
        ");

        $processed = 0;
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $infoHashHex = (string)($row['info_hash'] ?? '');
            $fetch = multitracker_fetch_tracker_stats((string)$row['tracker_url'], $infoHashHex);
            multitracker_store_tracker_stats((int)$row['id'], (int)$row['torrent_id'], $fetch);
            $processed++;
        }

        return $processed;
    }
}

if (!function_exists('multitracker_refresh_torrent_stats')) {
    function multitracker_refresh_torrent_stats(int $torrentId, int $limit = 3, bool $force = false): int
    {
        multitracker_ensure_schema();

        $torrentId = (int)$torrentId;
        if ($torrentId <= 0) {
            return 0;
        }

        $ttl = multitracker_stats_ttl();
        $limit = max(1, min(10, (int)$limit));

        $ttlCondition = $force
            ? ''
            : "
              AND (
                    stats.fetched_at IS NULL
                 OR stats.fetched_at < DATE_SUB(NOW(), INTERVAL {$ttl} SECOND)
              )";

        $res = sql_query("
            SELECT
                tet.id,
                tet.torrent_id,
                tet.tracker_url,
                t.info_hash,
                stats.fetched_at
            FROM torrent_external_trackers tet
            INNER JOIN torrents t ON t.id = tet.torrent_id
            LEFT JOIN torrent_external_tracker_stats stats ON stats.tracker_id = tet.id
            WHERE tet.is_local = 0
              AND tet.torrent_id = {$torrentId}
              {$ttlCondition}
            ORDER BY COALESCE(stats.fetched_at, '1970-01-01 00:00:00') ASC, tet.id ASC
            LIMIT {$limit}
        ");

        $processed = 0;
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $fetch = multitracker_fetch_tracker_stats((string)$row['tracker_url'], (string)$row['info_hash']);
            multitracker_store_tracker_stats((int)$row['id'], (int)$row['torrent_id'], $fetch);
            $processed++;
        }

        return $processed;
    }
}
