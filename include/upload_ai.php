<?php
declare(strict_types=1);

if (!function_exists('tracker_upload_ai_flatten_text')) {
    function tracker_upload_ai_flatten_text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('~\[(?:/?[a-z*]+(?:=[^\]]*)?)\]~iu', ' ', $text) ?? $text;
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('~\s+~u', ' ', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('tracker_upload_ai_human_size')) {
    function tracker_upload_ai_human_size(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $size = (float)$bytes;
        $unit = 0;
        while ($size >= 1024.0 && $unit < count($units) - 1) {
            $size /= 1024.0;
            $unit++;
        }

        $precision = $unit >= 2 ? 2 : 0;
        return number_format($size, $precision, '.', ' ') . ' ' . $units[$unit];
    }
}

if (!function_exists('tracker_upload_ai_titleize')) {
    function tracker_upload_ai_titleize(string $text): string
    {
        $text = tracker_upload_ai_flatten_text($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('~(\s+|[:\-|/()]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $result = [];
        foreach ($parts as $part) {
            if ($part === '' || preg_match('~^\s+$~u', $part)) {
                $result[] = $part;
                continue;
            }
            if (preg_match('~^[:\-|/()]+$~u', $part)) {
                $result[] = $part;
                continue;
            }
            if (preg_match('~^(?:WEB|HDR|UHD|HD|BD|DVD|TV|HEVC|AVC|AAC|AC3|DTS|TRUEHD|ATMOS|DDP|HDR10|DV|REMUX|BDRIP|WEBRIP|WEBDL|XVID|DIVX|MKV|AVI|MP4|ISO|PC|PS|PSP|XBOX)$~iu', $part)) {
                $result[] = mb_strtoupper($part, 'UTF-8');
                continue;
            }
            if (preg_match('~^(?:[IVXLCM]+|\d{1,4}[pk]?)$~iu', $part)) {
                $result[] = mb_strtoupper($part, 'UTF-8');
                continue;
            }

            $lower = mb_strtolower($part, 'UTF-8');
            $first = mb_substr($lower, 0, 1, 'UTF-8');
            $rest = mb_substr($lower, 1, null, 'UTF-8');
            $result[] = mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return trim(preg_replace('~\s+~u', ' ', implode('', $result)) ?? implode('', $result));
    }
}

if (!function_exists('tracker_upload_ai_detect_year')) {
    function tracker_upload_ai_detect_year(string $text): string
    {
        if (preg_match('~\b((?:19|20)\d{2})\b~u', $text, $m)) {
            return (string)$m[1];
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_quality')) {
    function tracker_upload_ai_detect_quality(string $text): string
    {
        $map = [
            'WEB-DL' => ['web dl', 'web-dl', 'webdl', 'amzn', 'nf', 'itunes', 'hmax'],
            'WEBRip' => ['webrip', 'web rip'],
            'BDRip' => ['bdrip', 'blu-ray', 'bluray', 'brrip', 'bd rip'],
            'BluRay' => ['blu ray', 'bluray', 'blu-ray'],
            'Remux' => ['remux', 'bdremux', 'blu ray remux'],
            'HDTV' => ['hdtv', 'hdtvrip'],
            'DVDRip' => ['dvdrip', 'dvd rip'],
            'DVD5' => ['dvd5'],
            'DVD9' => ['dvd9'],
            'TVRip' => ['tvrip', 'tv rip'],
            'SATRip' => ['satrip', 'sat rip'],
            'TeleSync' => ['telesync', 'tele sync', 'ts'],
            'TeleCine' => ['telecine', 'tele cine'],
            'CAMRip' => ['camrip', 'cam rip', 'cam'],
            'DVDScreener' => ['dvdscreener', 'dvd screener'],
            'VHSRip' => ['vhsrip', 'vhs rip'],
        ];

        $flat = mb_strtolower(str_replace(['.', '_'], ' ', tracker_upload_ai_flatten_text($text)), 'UTF-8');
        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_format')) {
    function tracker_upload_ai_detect_format(string $text): string
    {
        $flat = mb_strtolower(str_replace(['.', '_'], ' ', tracker_upload_ai_flatten_text($text)), 'UTF-8');
        $map = [
            'MKV' => ['mkv', 'matroska'],
            'AVI' => ['avi'],
            'MP4' => ['mp4'],
            'DVD Video' => ['dvd video', 'dvdvideo', 'vob', 'ifo', 'bup'],
            'MPEG' => ['mpeg', 'mpg'],
            'OGM' => ['ogm', 'ogg media'],
            'WMV' => ['wmv'],
            'M2TS' => ['m2ts', 'bdmv'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_resolution')) {
    function tracker_upload_ai_detect_resolution(string $text): string
    {
        if (preg_match('~\b(2160p|1080p|720p|480p)\b~iu', $text, $m)) {
            return mb_strtoupper((string)$m[1], 'UTF-8');
        }
        if (preg_match('~\b(3840x2160|1920x1080|1280x720|720x400|720x304)\b~iu', $text, $m)) {
            return (string)$m[1];
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_translation')) {
    function tracker_upload_ai_detect_translation(string $text): string
    {
        $flat = mb_strtolower(str_replace(['.', '_'], ' ', tracker_upload_ai_flatten_text($text)), 'UTF-8');
        $map = [
            'Дубляж' => ['дубляж', 'дублирован', 'dub'],
            'Профессиональный (Многоголосный)' => ['многоголос', 'mvo', 'мvo'],
            'Профессиональный (Одноголосный)' => ['одноголос', 'avo'],
            'Любительский (Многоголосный)' => ['любительск', 'lvo'],
            'Любительский (Одноголосный)' => ['авторский', 'любительск одноголос'],
            'Субтитры' => ['sub', 'subtitles', 'субтитр'],
            'Оригинал' => ['original audio', 'оригинал', 'без перевода'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_video_codec')) {
    function tracker_upload_ai_detect_video_codec(string $text): string
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text($text), 'UTF-8');
        $map = [
            'HEVC / H.265' => ['hevc', 'h.265', 'x265'],
            'AVC / H.264' => ['avc', 'h.264', 'x264'],
            'MPEG-4' => ['mpeg-4', 'mpeg4', 'divx', 'xvid'],
            'MPEG-2' => ['mpeg-2', 'mpeg2'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_audio_codec')) {
    function tracker_upload_ai_detect_audio_codec(string $text): string
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text($text), 'UTF-8');
        $map = [
            'Dolby TrueHD / Atmos' => ['truehd', 'atmos'],
            'DTS' => ['dts'],
            'AC3' => ['ac3', 'dolby digital'],
            'AAC' => ['aac'],
            'MP3' => ['mp3'],
            'FLAC' => ['flac'],
            'PCM' => ['pcm', 'lpcm'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_audio_bitrate')) {
    function tracker_upload_ai_detect_audio_bitrate(string $text): string
    {
        if (preg_match('~\b(\d{2,4})\s*(?:kb/s|kbps|кбит/с)\b~iu', $text, $m)) {
            return (string)$m[1] . ' Кбит/с';
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_runtime')) {
    function tracker_upload_ai_detect_runtime(string $text): string
    {
        if (preg_match('~\b(\d{1,2}:\d{2}:\d{2})\b~u', $text, $m)) {
            return (string)$m[1];
        }
        if (preg_match('~\b(\d{1,2})\s*h(?:ours?)?\s*(\d{1,2})?\s*m?~iu', $text, $m)) {
            $hours = (int)($m[1] ?? 0);
            $minutes = (int)($m[2] ?? 0);
            return sprintf('%02d:%02d:00', $hours, $minutes);
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_detect_platform')) {
    function tracker_upload_ai_detect_platform(string $text): string
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text($text), 'UTF-8');
        $map = [
            'PSP' => ['psp'],
            'PlayStation' => ['ps5', 'ps4', 'ps3', 'playstation'],
            'X-Box' => ['xbox', 'x-box'],
            'PC' => ['pc', 'windows', 'win64', 'win32'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (preg_match('~(?:^|[\s\-_])' . preg_quote($needle, '~') . '(?:$|[\s\-_])~iu', $flat)) {
                    return $label;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_extract_title_core')) {
    function tracker_upload_ai_extract_title_core(string $text): string
    {
        $text = tracker_upload_ai_flatten_text($text);
        if ($text === '') {
            return '';
        }

        $text = str_replace(['.', '_'], ' ', $text);
        $patterns = [
            '~\b(?:19|20)\d{2}\b~u',
            '~\b(?:2160p|1080p|720p|480p)\b~iu',
            '~\b(?:web[-\s]?dl|web[-\s]?rip|bdrip|blu[-\s]?ray|bluray|remux|hdtv|dvd(?:rip|5|9)?|camrip|cam|ts|telesync|telecine|dvdscreener|tvrip|satrip|vhsrip)\b~iu',
            '~\b(?:x264|x265|h\.?264|h\.?265|hevc|avc|hdr10?|dv|dolby(?:\s+digital)?|atmos|truehd|dts|aac|ac3|mp3|flac)\b~iu',
            '~\b(?:rus|eng|multi|sub|dub|mvo|avo|lvo)\b~iu',
            '~\b(?:дубляж|дублированный|многоголосый|одноголосый|профессиональный|любительский|субтитры|оригинал)\b~iu',
            '~\b(?:mkv|avi|mp4|iso|bdmv)\b~iu',
            '~\b(?:repack|unrated|proper|extended|director(?:s)? cut|complete|limited)\b~iu',
        ];
        $core = $text;
        foreach ($patterns as $pattern) {
            $core = preg_replace($pattern, ' ', $core) ?? $core;
        }

        $core = trim(preg_replace('~\s+~u', ' ', $core) ?? $core);
        return $core !== '' ? $core : $text;
    }
}

if (!function_exists('tracker_upload_ai_parse_release_name')) {
    function tracker_upload_ai_parse_release_name(string $title, string $context = 'generic'): array
    {
        $title = tracker_upload_ai_flatten_text($title);
        $year = tracker_upload_ai_detect_year($title);
        $quality = tracker_upload_ai_detect_quality($title);
        $format = tracker_upload_ai_detect_format($title);
        $resolution = tracker_upload_ai_detect_resolution($title);
        $translation = tracker_upload_ai_detect_translation($title);
        $videoCodec = tracker_upload_ai_detect_video_codec($title);
        $audioCodec = tracker_upload_ai_detect_audio_codec($title);
        $audioBitrate = tracker_upload_ai_detect_audio_bitrate($title);
        $runtime = tracker_upload_ai_detect_runtime($title);
        $platform = tracker_upload_ai_detect_platform($title);

        $core = tracker_upload_ai_extract_title_core($title);
        $displayTitle = tracker_upload_ai_titleize($core);
        if ($displayTitle === '') {
            $displayTitle = tracker_upload_ai_titleize($title);
        }

        $originalTitle = '';
        if (preg_match('~[A-Za-z]~', $core) && !preg_match('~[А-Яа-яЁё]~u', $core)) {
            $originalTitle = tracker_upload_ai_titleize($core);
        }

        $release = [
            'source_title' => $title,
            'clean_title' => $core,
            'display_title' => $displayTitle,
            'original_title' => $originalTitle,
            'year' => $year,
            'quality' => $quality,
            'format' => $format,
            'resolution' => $resolution,
            'translation' => $translation,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
            'audio_bitrate' => $audioBitrate,
            'runtime' => $runtime,
            'platform' => $platform,
        ];

        $release['release_name'] = $context === 'generic'
            ? tracker_upload_ai_compose_release_name($release)
            : trim((string)$release['display_title']);

        return $release;
    }
}

if (!function_exists('tracker_upload_ai_translation_label')) {
    function tracker_upload_ai_translation_label(string $translation, string $context = 'generic'): string
    {
        $translation = trim($translation);
        if ($translation === '') {
            return '';
        }

        if ($context === 'film') {
            $map = [
                'Дубляж' => 'Профессиональный (Дублированный)',
                'Оригинал' => 'Отсутствует',
            ];
            return $map[$translation] ?? $translation;
        }

        return $translation;
    }
}

if (!function_exists('tracker_upload_ai_compose_release_name')) {
    function tracker_upload_ai_compose_release_name(array $release): string
    {
        $displayTitle = trim((string)($release['display_title'] ?? ''));
        $originalTitle = trim((string)($release['original_title'] ?? ''));
        $year = trim((string)($release['year'] ?? ''));
        $quality = trim((string)($release['quality'] ?? ''));
        $resolution = trim((string)($release['resolution'] ?? ''));
        $translation = trim((string)($release['translation'] ?? ''));

        $title = $displayTitle !== '' ? $displayTitle : $originalTitle;
        if (
            $displayTitle !== '' &&
            $originalTitle !== '' &&
            mb_strtolower($displayTitle, 'UTF-8') !== mb_strtolower($originalTitle, 'UTF-8') &&
            !str_contains(mb_strtolower($displayTitle, 'UTF-8'), mb_strtolower($originalTitle, 'UTF-8'))
        ) {
            $title .= ' / ' . $originalTitle;
        }

        if ($title === '') {
            $title = trim((string)($release['source_title'] ?? ''));
        }

        $suffix = [];
        if ($year !== '') {
            $suffix[] = '(' . $year . ')';
        }
        if ($quality !== '') {
            $suffix[] = $quality;
        }
        if ($resolution !== '') {
            $suffix[] = $resolution;
        }

        if ($suffix) {
            $title .= ' ' . implode(' ', $suffix);
        }
        if ($translation !== '') {
            $title .= ' | ' . $translation;
        }

        return trim($title);
    }
}

if (!function_exists('tracker_upload_ai_similarity_score')) {
    function tracker_upload_ai_similarity_score(string $query, string $candidateTitle): float
    {
        $queryNorm = tracker_search_normalize_text($query);
        $titleNorm = tracker_search_normalize_text($candidateTitle);
        if ($queryNorm === '' || $titleNorm === '') {
            return 0.0;
        }

        $queryLat = tracker_search_transliterate_ru_to_lat($queryNorm);
        $titleLat = tracker_search_transliterate_ru_to_lat($titleNorm);
        if ($queryLat === '') {
            $queryLat = $queryNorm;
        }
        if ($titleLat === '') {
            $titleLat = $titleNorm;
        }

        similar_text($queryLat, $titleLat, $percent);
        $score = (float)$percent;

        $queryTokens = tracker_search_tokenize($queryNorm);
        $titleTokens = tracker_search_tokenize($titleNorm);
        $titleTokensLat = tracker_search_tokenize($titleLat);
        foreach ($queryTokens as $token) {
            $tokenLat = tracker_search_transliterate_ru_to_lat($token);
            if (
                in_array($token, $titleTokens, true) ||
                ($tokenLat !== '' && in_array($tokenLat, $titleTokensLat, true)) ||
                str_contains($titleLat, $tokenLat !== '' ? $tokenLat : $token)
            ) {
                $score += 14.0;
            }
        }

        if (preg_match_all('~\b\d+\b~u', $queryNorm, $queryNumbers) && preg_match_all('~\b\d+\b~u', $titleNorm, $titleNumbers)) {
            foreach ($queryNumbers[0] as $number) {
                if (in_array($number, $titleNumbers[0], true)) {
                    $score += 4.0;
                }
            }
        }

        return $score;
    }
}

if (!function_exists('tracker_upload_ai_extract_small_numbers')) {
    function tracker_upload_ai_extract_small_numbers(string $text): array
    {
        preg_match_all('~\b(\d{1,2})\b~u', $text, $matches);
        $numbers = [];
        foreach ($matches[1] ?? [] as $number) {
            $value = (int)$number;
            if ($value > 0) {
                $numbers[] = (string)$value;
            }
        }

        return array_values(array_unique($numbers));
    }
}

if (!function_exists('tracker_upload_ai_meaningful_tokens')) {
    function tracker_upload_ai_meaningful_tokens(string $text): array
    {
        $normalized = tracker_search_normalize_text(tracker_upload_ai_extract_title_core($text));
        if ($normalized === '') {
            $normalized = tracker_search_normalize_text($text);
        }

        $tokens = tracker_search_tokenize($normalized);
        $stopWords = [
            'and', 'the', 'for', 'with', 'about', 'from', 'into', 'movie', 'film', 'series', 'torrent', 'release',
            'про', 'или', 'для', 'это', 'как', 'фильм', 'сериал', 'релиз', 'раздача', 'торрент',
            'web', 'dl', 'rip', 'bd', 'hdr', 'uhd', 'dub', 'mvo', 'avo', 'lvo', 'cam', 'remux',
        ];

        $meaningful = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') < 3) {
                continue;
            }
            if (preg_match('~^(?:19|20)\d{2}$~', $token)) {
                continue;
            }
            if (preg_match('~^\d+p$~', $token)) {
                continue;
            }
            if (in_array($token, $stopWords, true)) {
                continue;
            }
            $meaningful[] = $token;
        }

        return array_values(array_unique($meaningful));
    }
}

if (!function_exists('tracker_upload_ai_clean_search_snippet')) {
    function tracker_upload_ai_clean_search_snippet(string $snippet): string
    {
        $snippet = html_entity_decode($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $snippet = strip_tags($snippet);
        return tracker_upload_ai_flatten_text($snippet);
    }
}

if (!function_exists('tracker_upload_ai_fetch_wikipedia_page_details')) {
    function tracker_upload_ai_fetch_wikipedia_page_details(int $pageId, string $lang = 'ru'): ?array
    {
        if ($pageId <= 0) {
            return null;
        }

        $detailsUrl = sprintf(
            'https://%s.wikipedia.org/w/api.php?action=query&prop=extracts|pageimages|info&exintro=1&explaintext=1&redirects=1&inprop=url&piprop=thumbnail|original&pithumbsize=800&pageids=%d&format=json',
            rawurlencode($lang),
            $pageId
        );
        $payload = tracker_upload_ai_fetch_json($detailsUrl, 5);
        $page = $payload['query']['pages'][(string)$pageId] ?? null;
        if (!is_array($page) || !empty($page['missing'])) {
            return null;
        }

        return [
            'title' => trim((string)($page['title'] ?? '')),
            'extract' => tracker_upload_ai_flatten_text((string)($page['extract'] ?? '')),
            'url' => trim((string)($page['fullurl'] ?? '')),
            'poster_url' => trim((string)($page['original']['source'] ?? ($page['thumbnail']['source'] ?? ''))),
        ];
    }
}

if (!function_exists('tracker_upload_ai_wikipedia_candidate_score')) {
    function tracker_upload_ai_wikipedia_candidate_score(string $query, string $title, string $snippet, string $extract, int $position = 0): float
    {
        $cleanQuery = tracker_upload_ai_extract_title_core($query);
        if ($cleanQuery === '') {
            $cleanQuery = tracker_upload_ai_flatten_text($query);
        }

        $candidateCorpus = trim($title . ' ' . $snippet . ' ' . $extract);
        $candidateNorm = tracker_search_normalize_text($candidateCorpus);
        $candidateNormLat = tracker_search_transliterate_ru_to_lat($candidateNorm);
        $titleNorm = tracker_search_normalize_text($title);

        $score = tracker_upload_ai_similarity_score($cleanQuery, $title);
        $score += max(0.0, 18.0 - ((float)$position * 3.0));

        $meaningfulTokens = tracker_upload_ai_meaningful_tokens($cleanQuery);
        if ($meaningfulTokens) {
            $hits = 0;
            foreach ($meaningfulTokens as $token) {
                $tokenLat = tracker_search_transliterate_ru_to_lat($token);
                if (
                    str_contains($candidateNorm, $token) ||
                    ($tokenLat !== '' && str_contains($candidateNormLat, $tokenLat))
                ) {
                    $hits++;
                }
            }

            if ($hits === 0) {
                return -1000.0;
            }

            $score += $hits * 10.0;
            $score -= max(0, count($meaningfulTokens) - $hits) * 4.0;
        }

        $queryYear = tracker_upload_ai_detect_year($query);
        $titleYear = tracker_upload_ai_detect_year($title);
        if ($queryYear !== '') {
            if ($titleYear === $queryYear) {
                $score += 24.0;
            } elseif ($titleYear !== '' && $titleYear !== $queryYear) {
                $score -= 34.0;
            } elseif (preg_match('~\b(?:фильм|movie|film|series|serial|сериал|аниме|anime)\b.{0,32}\b' . preg_quote($queryYear, '~') . '\b|\b' . preg_quote($queryYear, '~') . '\b.{0,32}\b(?:фильм|movie|film|series|serial|сериал|аниме|anime)\b~iu', $extract)) {
                $score += 10.0;
            }
        }

        $queryNumbers = tracker_upload_ai_extract_small_numbers($cleanQuery);
        $candidateNumbers = tracker_upload_ai_extract_small_numbers($title);
        if ($queryNumbers && $candidateNumbers) {
            $overlap = array_intersect($queryNumbers, $candidateNumbers);
            if ($overlap) {
                $score += count($overlap) * 16.0;
            } else {
                $score -= count($candidateNumbers) * 42.0;
            }
        }

        $queryHasMediaCue = (bool)preg_match('~\b(?:web[-\s]?dl|web[-\s]?rip|bdrip|bluray|blu[-\s]?ray|remux|hdtv|dvdrip|rip|2160p|1080p|720p|фильм|movie|film|сериал|series|аниме|anime)\b~iu', $query);
        $candidateHasMediaCue = (bool)preg_match('~\b(?:фильм|movie|film|series|serial|сериал|аниме|anime|album|игра|game)\b~iu', $candidateCorpus);
        if ($queryHasMediaCue && $candidateHasMediaCue) {
            $score += 12.0;
        }

        if ($titleNorm !== '' && $candidateNorm !== '' && str_starts_with($titleNorm, $cleanQuery !== '' ? tracker_search_normalize_text($cleanQuery) : $titleNorm)) {
            $score += 8.0;
        }

        return $score;
    }
}

if (!function_exists('tracker_upload_ai_should_query_wikipedia')) {
    function tracker_upload_ai_should_query_wikipedia(string $query): bool
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text($query), 'UTF-8');
        if ($flat === '' || !preg_match('~[A-Za-zА-Яа-яЁё]~u', $flat)) {
            return false;
        }

        $meaningfulTokens = tracker_upload_ai_meaningful_tokens($flat);
        if (!$meaningfulTokens) {
            return false;
        }

        $looksLikeNaturalLanguage = preg_match('~^(?:фильм|movie|film|сериал|series)\b~iu', $flat)
            || preg_match('~\b(?:про|about|where|which|котор|история)\b~iu', $flat);
        $hasReleaseCue = preg_match('~\b(?:19|20)\d{2}\b|\b(?:web[-\s]?dl|web[-\s]?rip|bdrip|bluray|blu[-\s]?ray|remux|hdtv|dvdrip|2160p|1080p|720p)\b~iu', $flat);

        return !($looksLikeNaturalLanguage && !$hasReleaseCue);
    }
}

if (!function_exists('tracker_upload_ai_detect_relevant_year')) {
    function tracker_upload_ai_detect_relevant_year(string $text): string
    {
        $text = tracker_upload_ai_flatten_text($text);
        if ($text === '') {
            return '';
        }

        $patterns = [
            '~\b((?:19|20)\d{2})\b.{0,36}\b(?:фильм|movie|film|series|serial|сериал|аниме|anime)\b~iu',
            '~\b(?:фильм|movie|film|series|serial|сериал|аниме|anime)\b.{0,36}\b((?:19|20)\d{2})\b~iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return (string)$m[1];
            }
        }

        if (preg_match_all('~\b((?:19|20)\d{2})\b~u', $text, $m)) {
            $currentYear = (int)date('Y') + 3;
            $years = array_map('intval', $m[1]);
            rsort($years);
            foreach ($years as $year) {
                if ($year >= 1950 && $year <= $currentYear) {
                    return (string)$year;
                }
            }
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_extract_original_title_from_text')) {
    function tracker_upload_ai_extract_original_title_from_text(string $text): string
    {
        $text = tracker_upload_ai_flatten_text($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('~\((?:англ\.|english)\s*([^)]+)\)~iu', $text, $m)) {
            return trim((string)$m[1], " \t\n\r\0\x0B,;");
        }

        return '';
    }
}

if (!function_exists('tracker_upload_ai_prettify_wikipedia_title')) {
    function tracker_upload_ai_prettify_wikipedia_title(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $patterns = [
            '~\s*\((?:фильм|сериал|телесериал|аниме|альбом|игра)(?:,\s*(?:19|20)\d{2})?\)\s*$~iu',
            '~\s*\((?:19|20)\d{2}\s+(?:film|tv series|television series|album|video game)\)\s*$~iu',
        ];
        foreach ($patterns as $pattern) {
            $title = preg_replace($pattern, '', $title) ?? $title;
        }

        return trim($title);
    }
}

if (!function_exists('tracker_upload_ai_torrent_dict_check')) {
    function tracker_upload_ai_torrent_dict_check(array $dictionary, string $spec): array
    {
        if (($dictionary['type'] ?? '') !== 'dictionary') {
            throw new RuntimeException('Torrent dictionary expected');
        }

        $ret = [];
        $value = $dictionary['value'] ?? [];
        foreach (explode(':', $spec) as $piece) {
            $type = null;
            if (preg_match('/^(.*)\((.*)\)$/', $piece, $m)) {
                $piece = $m[1];
                $type = $m[2];
            }
            if (!isset($value[$piece])) {
                throw new RuntimeException("Torrent key {$piece} is missing");
            }
            if ($type !== null) {
                if (($value[$piece]['type'] ?? '') !== $type) {
                    throw new RuntimeException("Torrent key {$piece} has invalid type");
                }
                $ret[] = $value[$piece]['value'];
            } else {
                $ret[] = $value[$piece];
            }
        }

        return $ret;
    }
}

if (!function_exists('tracker_upload_ai_torrent_dict_get')) {
    function tracker_upload_ai_torrent_dict_get(array $dictionary, string $key, string $type): mixed
    {
        if (($dictionary['type'] ?? '') !== 'dictionary') {
            throw new RuntimeException('Torrent dictionary expected');
        }
        $value = $dictionary['value'] ?? [];
        if (!isset($value[$key])) {
            return null;
        }
        if (($value[$key]['type'] ?? '') !== $type) {
            throw new RuntimeException("Torrent key {$key} has invalid type");
        }

        return $value[$key]['value'];
    }
}

if (!function_exists('tracker_upload_ai_parse_torrent_file')) {
    function tracker_upload_ai_parse_torrent_file(string $path, int $maxSize = 0): array
    {
        require_once __DIR__ . '/benc.php';

        $maxSize = $maxSize > 0 ? $maxSize : (int)($GLOBALS['max_torrent_size'] ?? 1048576);
        $dict = bdec_file($path, $maxSize);
        if (!$dict) {
            throw new RuntimeException('Invalid torrent file');
        }

        [$info] = tracker_upload_ai_torrent_dict_check($dict, 'info');
        [$dname] = tracker_upload_ai_torrent_dict_check($info, 'name(string)');
        $totalSize = tracker_upload_ai_torrent_dict_get($info, 'length', 'integer');
        $files = [];
        $type = 'single';

        if ($totalSize === null) {
            $fileList = tracker_upload_ai_torrent_dict_get($info, 'files', 'list');
            $totalSize = 0;
            $type = 'multi';
            foreach ((array)$fileList as $entry) {
                [$length, $pathPieces] = tracker_upload_ai_torrent_dict_check($entry, 'length(integer):path(list)');
                $parts = [];
                foreach ((array)$pathPieces as $piece) {
                    if (($piece['type'] ?? '') !== 'string') {
                        continue;
                    }
                    $parts[] = (string)$piece['value'];
                }

                $filename = implode('/', $parts);
                if ($filename !== '') {
                    $files[] = ['path' => $filename, 'size' => (int)$length];
                    $totalSize += (int)$length;
                }
            }
        } else {
            $files[] = ['path' => (string)$dname, 'size' => (int)$totalSize];
        }

        return [
            'name' => (string)$dname,
            'type' => $type,
            'total_size' => (int)$totalSize,
            'files' => $files,
        ];
    }
}

if (!function_exists('tracker_upload_ai_analyze_filelist')) {
    function tracker_upload_ai_analyze_filelist(array $files, int $totalSize = 0): array
    {
        $videoExt = ['mkv', 'mp4', 'avi', 'ts', 'm2ts', 'mov', 'wmv', 'iso', 'vob', 'bdmv'];
        $audioExt = ['mp3', 'flac', 'aac', 'm4a', 'ogg', 'ape', 'wav', 'alac', 'cue'];
        $bookExt = ['pdf', 'epub', 'fb2', 'djvu', 'mobi', 'doc', 'docx'];
        $subtitleExt = ['srt', 'ass', 'ssa', 'sub'];
        $imageExt = ['jpg', 'jpeg', 'png', 'webp'];
        $archiveExt = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];
        $exeExt = ['exe', 'msi', 'apk', 'pkg', 'appimage', 'dmg'];

        $stats = [
            'total_files' => count($files),
            'total_size' => $totalSize,
            'video_count' => 0,
            'audio_count' => 0,
            'book_count' => 0,
            'subtitle_count' => 0,
            'image_count' => 0,
            'archive_count' => 0,
            'exe_count' => 0,
            'iso_count' => 0,
            'episode_hits' => 0,
            'dominant_extension' => '',
            'extensions' => [],
            'paths_text' => '',
        ];

        $paths = [];
        foreach ($files as $entry) {
            $path = (string)($entry['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $paths[] = $path;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== '') {
                $stats['extensions'][$ext] = ($stats['extensions'][$ext] ?? 0) + 1;
            }

            if (preg_match('~(?:S\d{1,2}E\d{1,2}|Season\s*\d+|Сезон\s*\d+|Серия\s*\d+|Episode\s*\d+)~iu', $path)) {
                $stats['episode_hits']++;
            }

            if (in_array($ext, $videoExt, true)) {
                $stats['video_count']++;
            } elseif (in_array($ext, $audioExt, true)) {
                $stats['audio_count']++;
            } elseif (in_array($ext, $bookExt, true)) {
                $stats['book_count']++;
            } elseif (in_array($ext, $subtitleExt, true)) {
                $stats['subtitle_count']++;
            } elseif (in_array($ext, $imageExt, true)) {
                $stats['image_count']++;
            } elseif (in_array($ext, $archiveExt, true)) {
                $stats['archive_count']++;
            } elseif (in_array($ext, $exeExt, true)) {
                $stats['exe_count']++;
            }

            if ($ext === 'iso') {
                $stats['iso_count']++;
            }
        }

        if ($stats['extensions']) {
            arsort($stats['extensions']);
            $stats['dominant_extension'] = (string)array_key_first($stats['extensions']);
        }
        $stats['paths_text'] = tracker_upload_ai_flatten_text(implode(' ', $paths));

        return $stats;
    }
}

if (!function_exists('tracker_upload_ai_probability_rows')) {
    function tracker_upload_ai_probability_rows(array $scores, array $labels): array
    {
        $scores = array_filter($scores, static fn($value): bool => (float)$value > 0);
        if (!$scores) {
            return [];
        }

        arsort($scores);
        $total = array_sum($scores);
        if ($total <= 0) {
            return [];
        }

        $rows = [];
        $allocated = 0;
        $lastKey = array_key_last($scores);
        foreach ($scores as $key => $score) {
            $value = $key === $lastKey ? max(0, 100 - $allocated) : (int)round(((float)$score / $total) * 100);
            $allocated += $value;
            $rows[] = [
                'key' => (string)$key,
                'label' => $labels[$key] ?? (string)$key,
                'probability' => $value,
            ];
        }

        return $rows;
    }
}

if (!function_exists('tracker_upload_ai_guess_family_probabilities')) {
    function tracker_upload_ai_guess_family_probabilities(string $title, array $fileStats): array
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text($title . ' ' . ($fileStats['paths_text'] ?? '')), 'UTF-8');
        $hasVideoCue = (bool)preg_match('~\b(?:web[-\s]?dl|web[-\s]?rip|bdrip|bluray|blu[-\s]?ray|remux|hdtv|dvdrip|dvd5|dvd9|2160p|1080p|720p|camrip|telesync|telecine)\b~iu', $flat);
        $hasEpisodeCue = (bool)preg_match('~\b(?:season|episode|сезон|серия|серии|s\d{1,2}e\d{1,2})\b~iu', $flat);

        if (preg_match('~\b(?:xxx|porn|adult|18\+|эротика|hentai)\b~iu', $flat)) {
            return tracker_upload_ai_probability_rows(
                ['adult' => 92, 'game' => !empty($fileStats['exe_count']) ? 6 : 2, 'other' => !empty($fileStats['exe_count']) ? 2 : 6],
                [
                    'adult' => '18+ / XXX',
                    'game' => 'Игры',
                    'other' => 'Другое',
                ]
            );
        }

        if (($fileStats['video_count'] ?? 0) > 0) {
            if (($fileStats['episode_hits'] ?? 0) >= 2 || $hasEpisodeCue) {
                return tracker_upload_ai_probability_rows(
                    ['series' => 90, 'movie' => 8, 'other' => 2],
                    [
                        'series' => 'Сериалы',
                        'movie' => 'Фильмы',
                        'other' => 'Другое',
                    ]
                );
            }

            return tracker_upload_ai_probability_rows(
                ['movie' => 92, 'series' => 6, 'other' => 2],
                [
                    'movie' => 'Фильмы',
                    'series' => 'Сериалы',
                    'other' => 'Другое',
                ]
            );
        }

        if ($hasVideoCue) {
            if ($hasEpisodeCue) {
                return tracker_upload_ai_probability_rows(
                    ['series' => 88, 'movie' => 10, 'other' => 2],
                    [
                        'series' => 'Сериалы',
                        'movie' => 'Фильмы',
                        'other' => 'Другое',
                    ]
                );
            }

            return tracker_upload_ai_probability_rows(
                ['movie' => 90, 'series' => 8, 'other' => 2],
                [
                    'movie' => 'Фильмы',
                    'series' => 'Сериалы',
                    'other' => 'Другое',
                ]
            );
        }

        if (($fileStats['audio_count'] ?? 0) > 0 && ($fileStats['video_count'] ?? 0) === 0) {
            return tracker_upload_ai_probability_rows(
                ['music' => 93, 'other' => 4, 'movie' => 3],
                [
                    'music' => 'Музыка',
                    'other' => 'Другое',
                    'movie' => 'Видео',
                ]
            );
        }

        if (($fileStats['book_count'] ?? 0) > 0) {
            return tracker_upload_ai_probability_rows(
                ['book' => 94, 'other' => 6],
                [
                    'book' => 'Книги',
                    'other' => 'Другое',
                ]
            );
        }

        if (($fileStats['exe_count'] ?? 0) > 0 && preg_match('~\b(?:repack|gog|steam|игра|game)\b~iu', $flat)) {
            return tracker_upload_ai_probability_rows(
                ['game' => 92, 'software' => 6, 'other' => 2],
                [
                    'game' => 'Игры',
                    'software' => 'Программы',
                    'other' => 'Другое',
                ]
            );
        }

        if (($fileStats['exe_count'] ?? 0) > 0 || ($fileStats['archive_count'] ?? 0) > 0) {
            return tracker_upload_ai_probability_rows(
                ['software' => 92, 'game' => 6, 'other' => 2],
                [
                    'software' => 'Программы',
                    'game' => 'Игры',
                    'other' => 'Другое',
                ]
            );
        }

        return tracker_upload_ai_probability_rows(
            ['other' => 64, 'movie' => 18, 'software' => 18],
            [
                'other' => 'Другое',
                'movie' => 'Видео',
                'software' => 'Программы',
            ]
        );
    }
}

if (!function_exists('tracker_upload_ai_categories')) {
    function tracker_upload_ai_categories(): array
    {
        $cacheKey = 'upload_ai:categories:v1';
        $cached = function_exists('tracker_cache_get') ? tracker_cache_get($cacheKey, $hit) : null;
        if (!empty($hit) && is_array($cached)) {
            return $cached;
        }

        $rows = [];
        $res = sql_query("SELECT id, name FROM categories ORDER BY id ASC");
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[(int)$row['id']] = (string)$row['name'];
        }

        if (function_exists('tracker_cache_set')) {
            tracker_cache_set($cacheKey, $rows, 300);
        }

        return $rows;
    }
}

if (!function_exists('tracker_upload_ai_guess_category_candidates')) {
    function tracker_upload_ai_guess_category_candidates(array $families, array $release, array $fileStats): array
    {
        $familyKey = (string)($families[0]['key'] ?? 'other');
        $quality = $release['quality'] ?? '';
        $platform = $release['platform'] ?? '';
        $categories = tracker_upload_ai_categories();
        $candidates = [];

        switch ($familyKey) {
            case 'movie':
                if (in_array($quality, ['DVD5', 'DVD9', 'DVDRip', 'DVDScreener'], true) || ($fileStats['iso_count'] ?? 0) > 0) {
                    $candidates = [15 => 88, 14 => 8, 13 => 4];
                } elseif (in_array($quality, ['WEB-DL', 'BDRip', 'BluRay', 'Remux', 'HDTV'], true) || ($release['resolution'] ?? '') !== '') {
                    $candidates = [14 => 90, 13 => 6, 15 => 4];
                } else {
                    $candidates = [13 => 78, 14 => 18, 20 => 4];
                }
                break;

            case 'series':
                if (in_array($quality, ['DVD5', 'DVD9', 'DVDRip', 'DVDScreener'], true) || ($fileStats['iso_count'] ?? 0) > 0) {
                    $candidates = [27 => 88, 23 => 8, 11 => 4];
                } elseif (in_array($quality, ['WEB-DL', 'BDRip', 'BluRay', 'Remux', 'HDTV'], true) || ($release['resolution'] ?? '') !== '') {
                    $candidates = [23 => 90, 11 => 8, 27 => 2];
                } else {
                    $candidates = [11 => 78, 23 => 18, 20 => 4];
                }
                break;

            case 'music':
                $candidates = [24 => (($fileStats['video_count'] ?? 0) > 0 ? 74 : 8), 10 => (($fileStats['video_count'] ?? 0) > 0 ? 24 : 90), 20 => 2];
                break;

            case 'game':
                if ($platform === 'PlayStation') {
                    $candidates = [6 => 92, 5 => 6, 20 => 2];
                } elseif ($platform === 'X-Box') {
                    $candidates = [7 => 92, 5 => 6, 20 => 2];
                } elseif ($platform === 'PSP') {
                    $candidates = [8 => 92, 5 => 6, 20 => 2];
                } else {
                    $candidates = [5 => 92, 6 => 6, 20 => 2];
                }
                break;

            case 'software':
                $candidates = [28 => 92, 20 => 8];
                break;

            case 'book':
                $candidates = [16 => 94, 20 => 6];
                break;

            case 'adult':
                $candidates = [($fileStats['exe_count'] ?? 0) > 0 ? 32 : 31 => 92, 20 => 8];
                break;

            default:
                if (preg_match('~\b(?:anime|аниме)\b~iu', (string)($release['source_title'] ?? ''))) {
                    $candidates = [26 => 88, 20 => 12];
                } else {
                    $candidates = [20 => 100];
                }
                break;
        }

        $rows = [];
        $allocated = 0;
        $lastId = array_key_last($candidates);
        foreach ($candidates as $id => $probability) {
            $value = ((int)$id === (int)$lastId) ? max(0, 100 - $allocated) : (int)$probability;
            $allocated += $value;
            $rows[] = [
                'id' => (int)$id,
                'name' => $categories[(int)$id] ?? ('Категория #' . (int)$id),
                'probability' => $value,
            ];
        }

        return $rows;
    }
}

if (!function_exists('tracker_upload_ai_fetch_json')) {
    function tracker_upload_ai_fetch_json(string $url, int $timeout = 5): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => max(3, $timeout),
            CURLOPT_USERAGENT => 'TorrentSide Upload Assistant/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json; charset=UTF-8'],
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code < 200 || $code >= 300) {
            return null;
        }

        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }
}

if (!function_exists('tracker_upload_ai_wikipedia_summary')) {
    function tracker_upload_ai_wikipedia_summary(string $query, string $lang = 'ru'): ?array
    {
        $query = tracker_upload_ai_flatten_text($query);
        if ($query === '') {
            return null;
        }

        $searchUrl = sprintf(
            'https://%s.wikipedia.org/w/api.php?action=query&list=search&srsearch=%s&utf8=1&format=json&srlimit=8',
            rawurlencode($lang),
            rawurlencode($query)
        );
        $search = tracker_upload_ai_fetch_json($searchUrl, 5);
        $searchRows = $search['query']['search'] ?? [];
        if (!is_array($searchRows) || !$searchRows) {
            return null;
        }

        $best = null;
        foreach ($searchRows as $position => $row) {
            $pageId = (int)($row['pageid'] ?? 0);
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $details = tracker_upload_ai_fetch_wikipedia_page_details($pageId, $lang);
            if (!is_array($details)) {
                continue;
            }

            $snippet = tracker_upload_ai_clean_search_snippet((string)($row['snippet'] ?? ''));
            $extract = trim((string)($details['extract'] ?? ''));
            $description = $snippet !== '' ? $snippet : $extract;
            if ($description === '' && $extract === '') {
                continue;
            }

            $score = tracker_upload_ai_wikipedia_candidate_score($query, $title, $snippet, $extract, (int)$position);
            if ($score < 58.0) {
                continue;
            }

            if ($best !== null && $score <= $best['score']) {
                continue;
            }

            $best = [
                'score' => $score,
                'lang' => $lang,
                'title' => trim((string)($details['title'] ?? $title)),
                'description' => $description,
                'extract' => $extract !== '' ? $extract : $description,
                'url' => trim((string)($details['url'] ?? '')),
                'poster_url' => trim((string)($details['poster_url'] ?? '')),
            ];
        }

        if ($best === null) {
            return null;
        }

        unset($best['score']);
        return $best;
    }
}

if (!function_exists('tracker_upload_ai_fetch_wikipedia_bundle')) {
    function tracker_upload_ai_fetch_wikipedia_bundle(string $query, string $year = ''): array
    {
        $cacheKey = 'upload_ai:wiki:v4:' . md5($query . '|' . $year);
        $cached = function_exists('tracker_cache_get') ? tracker_cache_get($cacheKey, $hit) : null;
        if (!empty($hit) && is_array($cached)) {
            return $cached;
        }

        $searchQuery = trim($query . ($year !== '' ? ' ' . $year : ''));
        $bundle = [
            'ru' => tracker_upload_ai_wikipedia_summary($searchQuery, 'ru'),
            'en' => tracker_upload_ai_wikipedia_summary($searchQuery, 'en'),
        ];

        if (function_exists('tracker_cache_set')) {
            tracker_cache_set($cacheKey, $bundle, 1800);
        }

        return $bundle;
    }
}

if (!function_exists('tracker_upload_ai_collect_genres')) {
    function tracker_upload_ai_collect_genres(string ...$texts): array
    {
        $flat = mb_strtolower(tracker_upload_ai_flatten_text(implode(' ', $texts)), 'UTF-8');
        if ($flat === '') {
            return [];
        }

        $map = [
            'Фантастика' => ['фантаст', 'science fiction', 'sci-fi', 'sci fi'],
            'Приключения' => ['приключен', 'adventure'],
            'Боевик' => ['боевик', 'action'],
            'Комедия' => ['комед', 'comedy'],
            'Драма' => ['драм', 'drama'],
            'Триллер' => ['триллер', 'thriller'],
            'Ужасы' => ['ужас', 'хоррор', 'horror'],
            'Фэнтези' => ['фэнт', 'fantasy'],
            'Мелодрама' => ['мелодрам', 'romance', 'romantic'],
            'Детектив' => ['детектив', 'mystery'],
            'Криминал' => ['криминал', 'crime'],
            'Семейный' => ['семейн', 'family'],
            'Документальный' => ['документальн', 'documentary'],
            'Анимация' => ['анимац', 'мульт', 'animation', 'animated'],
            'Биография' => ['биограф', 'biographical', 'biopic'],
            'Исторический' => ['историчес', 'historical'],
        ];

        $genres = [];
        foreach ($map as $genre => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    $genres[$genre] = $genre;
                    break;
                }
            }
        }

        return array_slice(array_values($genres), 0, 4);
    }
}

if (!function_exists('tracker_upload_ai_build_tags')) {
    function tracker_upload_ai_build_tags(array $genres, array $release, array $families, array $fileStats): array
    {
        $tags = [];
        foreach ($genres as $genre) {
            $tag = mb_strtolower((string)$genre, 'UTF-8');
            $tags[$tag] = $tag;
        }

        foreach (['quality', 'resolution'] as $field) {
            $value = trim((string)($release[$field] ?? ''));
            if ($value !== '') {
                $tag = mb_strtolower($value, 'UTF-8');
                $tags[$tag] = $tag;
            }
        }

        if (!empty($release['translation'])) {
            $tag = mb_strtolower((string)$release['translation'], 'UTF-8');
            $tags[$tag] = $tag;
        }

        $familyKey = (string)($families[0]['key'] ?? '');
        $familyTags = [
            'movie' => ['фильм'],
            'series' => ['сериал'],
            'music' => ['музыка'],
            'game' => ['игра'],
            'software' => ['софт'],
            'book' => ['книга'],
            'adult' => ['18+'],
        ];
        foreach ($familyTags[$familyKey] ?? [] as $tag) {
            $tags[$tag] = $tag;
        }

        if (($fileStats['subtitle_count'] ?? 0) > 0) {
            $tags['субтитры'] = 'субтитры';
        }
        if (!empty($release['platform'])) {
            $tag = mb_strtolower((string)$release['platform'], 'UTF-8');
            $tags[$tag] = $tag;
        }

        return array_slice(array_values($tags), 0, 12);
    }
}

if (!function_exists('tracker_upload_ai_fallback_summary')) {
    function tracker_upload_ai_fallback_summary(array $release, array $families, array $fileStats): string
    {
        $title = (string)($release['display_title'] ?? $release['release_name'] ?? 'Эта раздача');
        $familyKey = (string)($families[0]['key'] ?? 'other');
        $labelMap = [
            'movie' => 'фильм',
            'series' => 'сериал',
            'music' => 'музыкальный релиз',
            'game' => 'игровой релиз',
            'software' => 'программный релиз',
            'book' => 'книжный релиз',
            'adult' => 'релиз 18+',
            'other' => 'релиз',
        ];

        $bits = [];
        if (!empty($release['quality'])) {
            $bits[] = 'качество ' . $release['quality'];
        }
        if (!empty($release['resolution'])) {
            $bits[] = 'разрешение ' . $release['resolution'];
        }
        if (!empty($release['audio_codec'])) {
            $bits[] = 'аудио ' . $release['audio_codec'];
        }
        if (($fileStats['total_size'] ?? 0) > 0) {
            $bits[] = 'размер ' . tracker_upload_ai_human_size((int)$fileStats['total_size']);
        }

        $suffix = $bits ? ' Техническая сводка: ' . implode(', ', $bits) . '.' : '';
        return sprintf(
            'Автоматически собран черновик описания для раздачи «%s». Проверьте сюжет, название и технические параметры перед публикацией. Это %s, распознанный по имени релиза и структуре файлов.%s',
            $title,
            $labelMap[$familyKey] ?? 'релиз',
            $suffix
        );
    }
}

if (!function_exists('tracker_upload_ai_build_description_template')) {
    function tracker_upload_ai_build_description_template(string $context, array $suggestion): string
    {
        $release = $suggestion['release'] ?? [];
        $summary = trim((string)($suggestion['summary'] ?? ''));
        if ($summary === '') {
            $summary = tracker_upload_ai_fallback_summary($release, $suggestion['family_probabilities'] ?? [], $suggestion['file_stats'] ?? []);
        }

        $genres = implode(', ', (array)($suggestion['genres'] ?? []));
        $size = !empty($suggestion['file_stats']['total_size']) ? tracker_upload_ai_human_size((int)$suggestion['file_stats']['total_size']) : '';

        if ($context !== 'generic') {
            return $summary;
        }

        $lines = [];
        if (!empty($release['display_title'])) {
            $lines[] = '[b]Название:[/b] ' . $release['display_title'];
        }
        if (!empty($release['original_title']) && $release['original_title'] !== $release['display_title']) {
            $lines[] = '[b]Оригинальное название:[/b] ' . $release['original_title'];
        }
        if (!empty($release['year'])) {
            $lines[] = '[b]Год выхода:[/b] ' . $release['year'];
        }
        if ($genres !== '') {
            $lines[] = '[b]Жанр:[/b] ' . $genres;
        }

        $lines[] = '';
        $lines[] = '[b]Описание:[/b]';
        $lines[] = $summary;
        $lines[] = '';

        if (!empty($release['quality'])) {
            $lines[] = '[b]Качество:[/b] ' . $release['quality'];
        }
        if (!empty($release['resolution']) || !empty($release['video_codec'])) {
            $videoParts = array_values(array_filter([
                $release['resolution'] ?? '',
                $release['video_codec'] ?? '',
            ]));
            $lines[] = '[b]Видео:[/b] ' . implode(', ', $videoParts);
        }
        if (!empty($release['audio_codec']) || !empty($release['audio_bitrate'])) {
            $audioParts = array_values(array_filter([
                $release['audio_codec'] ?? '',
                $release['audio_bitrate'] ?? '',
            ]));
            $lines[] = '[b]Аудио:[/b] ' . implode(', ', $audioParts);
        }
        if (!empty($release['translation'])) {
            $lines[] = '[b]Перевод:[/b] ' . $release['translation'];
        }
        if (!empty($release['runtime'])) {
            $lines[] = '[b]Продолжительность:[/b] ' . $release['runtime'];
        }
        if ($size !== '') {
            $lines[] = '[b]Размер:[/b] ' . $size;
        }

        $wiki = $suggestion['wikipedia']['ru'] ?? $suggestion['wikipedia']['en'] ?? null;
        if (is_array($wiki) && !empty($wiki['url'])) {
            $lines[] = '';
            $lines[] = '[b]Источник справки:[/b] ' . (string)$wiki['url'];
        }

        return trim(implode("\n", $lines));
    }
}

if (!function_exists('tracker_upload_ai_build_field_values')) {
    function tracker_upload_ai_build_field_values(string $context, array $suggestion): array
    {
        $release = $suggestion['release'] ?? [];
        $tags = implode(',', (array)($suggestion['tags'] ?? []));
        $poster = (string)($suggestion['poster_url'] ?? '');
        $bestCategory = (int)($suggestion['category_candidates'][0]['id'] ?? 0);
        $fields = [];

        switch ($context) {
            case 'film':
                $fields = [
                    'name' => (string)($release['display_title'] ?? ''),
                    'origname' => (string)($release['original_title'] ?? ''),
                    'year' => (string)($release['year'] ?? ''),
                    'janr' => implode(', ', (array)($suggestion['genres'] ?? [])),
                    'perevod' => tracker_upload_ai_translation_label((string)($release['translation'] ?? ''), 'film'),
                    'time' => (string)($release['runtime'] ?? ''),
                    'kachestvo' => (string)($release['quality'] ?? ''),
                    'format' => (string)($release['format'] ?? ''),
                    'resolution' => (string)($release['resolution'] ?? ''),
                    'videocodec' => (string)($release['video_codec'] ?? ''),
                    'audiocodec' => (string)($release['audio_codec'] ?? ''),
                    'audiobitrate' => (string)($release['audio_bitrate'] ?? ''),
                    'descr' => (string)($suggestion['plain_summary'] ?? ''),
                    'tags' => $tags,
                    'image0' => $poster,
                ];
                break;

            case 'music':
                $fields = [
                    'name' => (string)($release['display_title'] ?? ''),
                    'origname' => (string)($release['original_title'] ?? ''),
                    'year' => (string)($release['year'] ?? ''),
                    'janr' => implode(', ', (array)($suggestion['genres'] ?? [])),
                    'audiocodec' => (string)($release['audio_codec'] ?? ''),
                    'audiobitrate' => (string)($release['audio_bitrate'] ?? ''),
                    'time' => (string)($release['runtime'] ?? ''),
                    'descr' => (string)($suggestion['plain_summary'] ?? ''),
                    'tags' => $tags,
                    'image0' => $poster,
                ];
                break;

            case 'game':
                $fields = [
                    'name' => (string)($release['display_title'] ?? ''),
                    'origname' => (string)($release['original_title'] ?? ''),
                    'year' => (string)($release['year'] ?? ''),
                    'janr' => implode(', ', (array)($suggestion['genres'] ?? [])),
                    'translation' => (string)($release['translation'] ?? ''),
                    'descr' => (string)($suggestion['plain_summary'] ?? ''),
                    'tags' => $tags,
                    'image0' => $poster,
                ];
                break;

            case 'soft':
                $fields = [
                    'name' => (string)($release['display_title'] ?? ''),
                    'year' => (string)($release['year'] ?? ''),
                    'plata' => (string)($release['platform'] ?? ''),
                    'translation' => (string)($release['translation'] ?? ''),
                    'descr' => (string)($suggestion['plain_summary'] ?? ''),
                    'tags' => $tags,
                    'image0' => $poster,
                ];
                break;

            default:
                $fields = [
                    'name' => (string)($release['release_name'] ?? $release['display_title'] ?? ''),
                    'descr' => (string)($suggestion['description_template'] ?? ''),
                    'tags' => $tags,
                    'image0' => $poster,
                ];
                if ($bestCategory > 0) {
                    $fields['type'] = (string)$bestCategory;
                }
                break;
        }

        if (empty($fields['name'])) {
            $fields['name'] = (string)($release['display_title'] ?? '');
        }

        return array_filter($fields, static fn($value): bool => is_string($value) && trim($value) !== '');
    }
}

if (!function_exists('tracker_upload_ai_plain_length')) {
    function tracker_upload_ai_plain_length(string $text): int
    {
        $text = tracker_upload_ai_flatten_text($text);
        return mb_strlen($text, 'UTF-8');
    }
}

if (!function_exists('tracker_upload_ai_find_bbcode_issues')) {
    function tracker_upload_ai_find_bbcode_issues(string $text): array
    {
        preg_match_all('~\[(\/?)([a-z*]+)(?:=[^\]]*)?\]~iu', $text, $matches, PREG_SET_ORDER);
        $stack = [];
        $issues = [];
        $selfClosing = ['*'];

        foreach ($matches as $match) {
            $isClose = ($match[1] ?? '') === '/';
            $tag = mb_strtolower((string)($match[2] ?? ''), 'UTF-8');
            if ($tag === '' || in_array($tag, $selfClosing, true)) {
                continue;
            }

            if (!$isClose) {
                $stack[] = $tag;
                continue;
            }

            $last = end($stack);
            if ($last !== $tag) {
                $issues[] = "Нарушен порядок BBCode-тегов возле [/$tag].";
                continue;
            }

            array_pop($stack);
        }

        foreach ($stack as $tag) {
            $issues[] = "Не закрыт BBCode-тег [$tag].";
        }

        return $issues;
    }
}

if (!function_exists('tracker_upload_ai_audit_description')) {
    function tracker_upload_ai_audit_description(string $text, array $context = []): array
    {
        $normalized = trim((string)(preg_replace('~[ \t]+\n~u', "\n", str_replace(["\r\n", "\r"], "\n", $text)) ?? $text));
        $normalized = preg_replace("~\n{3,}~u", "\n\n", $normalized) ?? $normalized;
        $plain = tracker_upload_ai_flatten_text($normalized);
        $issues = [];
        $isVideo = in_array((int)($context['category_id'] ?? 0), [11, 13, 14, 15, 23, 27, 31], true)
            || in_array((string)($context['family_key'] ?? ''), ['movie', 'series', 'adult'], true);

        if ($plain === '') {
            $issues[] = ['severity' => 'error', 'message' => 'Описание отсутствует. Добавьте хотя бы короткую справку или сгенерируйте её через AI-помощник.'];
        } else {
            $minLength = $isVideo ? 120 : 80;
            if (tracker_upload_ai_plain_length($plain) < $minLength) {
                $issues[] = ['severity' => $isVideo ? 'error' : 'warning', 'message' => 'Описание слишком короткое для публикации.'];
            }
        }

        foreach (tracker_upload_ai_find_bbcode_issues($normalized) as $message) {
            $issues[] = ['severity' => 'error', 'message' => $message];
        }

        if ($isVideo) {
            $videoChecks = [
                'качество' => ['качество', 'quality'],
                'перевод' => ['перевод', 'dub', 'mvo', 'avo', 'дубляж'],
                'аудио' => ['аудио', 'audio', 'ac3', 'aac', 'dts'],
            ];
            $missingVideoBlocks = [];
            foreach ($videoChecks as $label => $needles) {
                $found = false;
                foreach ($needles as $needle) {
                    if (str_contains(mb_strtolower($normalized, 'UTF-8'), mb_strtolower($needle, 'UTF-8'))) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missingVideoBlocks[] = $label;
                }
            }
            if ($missingVideoBlocks) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'В описании отсутствует информация о ' . implode(', ', $missingVideoBlocks) . '.',
                ];
            }
        }

        preg_match_all('~https?://[^\s\]]+~iu', $normalized, $urlMatches);
        $allowedHosts = ['imdb.com', 'www.imdb.com', 'kinopoisk.ru', 'www.kinopoisk.ru', 'wikipedia.org', 'ru.wikipedia.org', 'en.wikipedia.org', 'youtube.com', 'www.youtube.com', 'youtu.be', 'upload.wikimedia.org'];
        foreach ($urlMatches[0] ?? [] as $url) {
            $host = mb_strtolower((string)parse_url($url, PHP_URL_HOST), 'UTF-8');
            if ($host !== '' && !in_array($host, $allowedHosts, true)) {
                $issues[] = ['severity' => 'warning', 'message' => 'Обнаружена внешняя ссылка на ' . $host . '. Проверьте, что она действительно нужна в описании.'];
            }
        }

        if (preg_match('~([!?])\1{3,}~u', $normalized)) {
            $issues[] = ['severity' => 'warning', 'message' => 'В описании слишком много эмоциональных повторов вроде !!! или ???.'];
        }

        $lettersOnly = preg_replace('~[^A-Za-zА-Яа-яЁё]+~u', '', $plain) ?? '';
        if ($lettersOnly !== '') {
            $upperCount = preg_match_all('~[A-ZА-ЯЁ]~u', $lettersOnly);
            $ratio = $upperCount > 0 ? $upperCount / max(1, mb_strlen($lettersOnly, 'UTF-8')) : 0;
            if ($ratio >= 0.55 && mb_strlen($lettersOnly, 'UTF-8') >= 30) {
                $issues[] = ['severity' => 'warning', 'message' => 'Описание выглядит как CAPS LOCK. Лучше привести текст к нормальному регистру.'];
            }
        }

        if (preg_match('~(?:csam|child porn|детск(?:ая|ое)\s+порн|несовершеннолетн(?:ие|ий|яя).{0,12}эрот)~iu', $plain)) {
            $issues[] = ['severity' => 'error', 'message' => 'Описание содержит запрещённые формулировки. Проверьте текст перед публикацией.'];
        }

        return [
            'normalized_text' => $normalized,
            'issues' => $issues,
        ];
    }
}

if (!function_exists('tracker_upload_ai_critical_messages')) {
    function tracker_upload_ai_critical_messages(array $audit): array
    {
        $messages = [];
        foreach ((array)($audit['issues'] ?? []) as $issue) {
            if (($issue['severity'] ?? '') !== 'error') {
                continue;
            }
            $message = trim((string)($issue['message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return array_values(array_unique($messages));
    }
}

if (!function_exists('tracker_upload_ai_render_panel')) {
    function tracker_upload_ai_render_panel(string $context = 'generic'): string
    {
        $context = in_array($context, ['generic', 'film', 'music', 'game', 'soft'], true) ? $context : 'generic';
        $baseUrl = defined('DEFAULTBASEURL') ? DEFAULTBASEURL : '';
        $assetPath = dirname(__DIR__) . '/js/upload-assistant.js';
        $assetVersion = is_file($assetPath) ? ('?v=' . (string)filemtime($assetPath)) : '';
        $scriptUrl = htmlspecialchars($baseUrl . '/js/upload-assistant.js' . $assetVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $contextHint = [
            'generic' => 'По одному названию и .torrent подберёт красивое имя релиза, черновик описания, теги и рекомендуемую категорию.',
            'film' => 'По названию, .torrent и MediaInfo подтянет сюжет, год, жанр, качество, перевод и черновик описания фильма.',
            'music' => 'Подскажет жанр, год, аудиокодек и соберёт черновик музыкального описания.',
            'game' => 'Определит платформу, жанр, год и подготовит черновик описания игры.',
            'soft' => 'Попробует определить платформу, тип релиза и базовое описание программы.',
        ];
        $contextExamples = [
            'generic' => ['avatar 2 2022 web dl 1080p дубляж', 'торнадо 2025', 'terminator extended bdrip'],
            'film' => ['Аватар 2', 'The Matrix', 'Торнадо 2025 WEB-DL'],
            'music' => ['Linkin Park Meteora 2003 FLAC', 'Rammstein live 2024'],
            'game' => ['Alan Wake 2 Deluxe Edition', 'God of War Ragnarok PS5'],
            'soft' => ['Photoshop 2025 RePack', 'Windows 11 24H2'],
        ];

        static $assetsPrinted = false;
        $html = '';
        if (!$assetsPrinted) {
            $assetsPrinted = true;
            $html .= <<<HTML
<style>
  .upload-ai-card{position:relative;border:1px solid #dfe1e5;border-radius:30px;background:radial-gradient(circle at top,#ffffff 0%,#f8fafc 52%,#eef4ff 100%);padding:22px;box-shadow:0 16px 40px rgba(60,64,67,.08)}
  .upload-ai-kicker{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#5f6368}
  .upload-ai-title{margin-top:8px;font-size:26px;line-height:1.15;font-weight:700;color:#202124}
  .upload-ai-sub{margin-top:8px;color:#5f6368;line-height:1.55;max-width:860px}
  .upload-ai-intake{margin-top:18px}
  .upload-ai-search-shell{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:14px;min-height:76px;padding:0 12px 0 18px;border:1px solid #dfe1e5;border-radius:999px;background:#fff;box-shadow:0 1px 6px rgba(32,33,36,.12)}
  .upload-ai-search-shell:focus-within{border-color:#8ab4f8;box-shadow:0 2px 12px rgba(66,133,244,.24)}
  .upload-ai-search-icon{font-size:24px;line-height:1;color:#5f6368}
  .upload-ai-run{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:0;border-radius:999px;background:#1a73e8;color:#fff;font-weight:700;cursor:pointer;box-shadow:0 8px 18px rgba(26,115,232,.22)}
  .upload-ai-run[disabled]{opacity:.55;cursor:wait}
  .upload-ai-slot{min-width:0}
  .upload-ai-slot:empty::before{content:attr(data-slot-placeholder);display:block;color:#9aa0a6;font-size:14px}
  .upload-ai-slot-field{display:block}
  .upload-ai-slot-label{margin-bottom:6px;font-size:12px;font-weight:700;color:#5f6368}
  .upload-ai-slot-hint{margin-top:6px;font-size:12px;color:#6b7280;line-height:1.4}
  .upload-ai-slot-main .upload-ai-slot-label,.upload-ai-slot-main .upload-ai-slot-hint{display:none}
  .upload-ai-inline-input{width:100%;padding:0;border:0;background:transparent;font-size:19px;line-height:1.4;color:#202124;outline:0;box-shadow:none}
  .upload-ai-inline-input::placeholder{color:#9aa0a6}
  .upload-ai-secondary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
  .upload-ai-secondary-grid .upload-ai-slot{padding:14px 16px;border:1px solid #e5e7eb;border-radius:22px;background:rgba(255,255,255,.86)}
  .upload-ai-file-input{width:100%;font-size:14px;color:#374151}
  .upload-ai-file-input::file-selector-button{margin-right:12px;padding:10px 14px;border:0;border-radius:999px;background:#f1f3f4;color:#202124;font-weight:700;cursor:pointer}
  .upload-ai-chip-row,.upload-ai-pill-row,.upload-ai-family-row,.upload-ai-cat-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
  .upload-ai-chip,.upload-ai-pill,.upload-ai-family,.upload-ai-cat{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:#f1f3f4;border:1px solid rgba(60,64,67,.08);color:#3c4043;font-size:12px}
  .upload-ai-pill strong,.upload-ai-family strong,.upload-ai-cat strong{color:#174ea6}
  .upload-ai-tools{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:16px}
  .upload-ai-tools label{font-size:12px;color:#57534e}
  .upload-ai-extra{margin-top:12px}
  .upload-ai-extra summary{cursor:pointer;color:#1a73e8;font-weight:700}
  .upload-ai-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
  .upload-ai-grid textarea{width:100%;min-height:118px;padding:12px;border:1px solid #dfe1e5;border-radius:16px;background:#fff;box-sizing:border-box}
  .upload-ai-status{margin-top:14px;padding:12px 14px;border-radius:16px;background:#fff;border:1px solid #ebe5d6;color:#525252;display:none}
  .upload-ai-status.is-visible{display:block}
  .upload-ai-status.is-error{border-color:#f2b8b5;background:#fff4f2;color:#8a2c24}
  .upload-ai-status.is-success{border-color:#c9e5c1;background:#f3fff0;color:#2f6a22}
  .upload-ai-results{margin-top:14px;display:none}
  .upload-ai-results.is-visible{display:grid;gap:10px}
  .upload-ai-box{padding:14px;border:1px solid #e8eaed;border-radius:20px;background:rgba(255,255,255,.92)}
  .upload-ai-box + .upload-ai-box{margin-top:10px}
  .upload-ai-box-title{font-weight:700;color:#202124;margin-bottom:8px}
  .upload-ai-list{margin:0;padding-left:18px}
  .upload-ai-list li{margin:6px 0}
  .upload-ai-list li.error{color:#a32020}
  .upload-ai-list li.warning{color:#9b5c00}
  @media (max-width:920px){
    .upload-ai-search-shell{grid-template-columns:minmax(0,1fr);padding:16px;border-radius:28px}
    .upload-ai-search-icon{display:none}
    .upload-ai-run{width:100%}
    .upload-ai-secondary-grid,.upload-ai-grid{grid-template-columns:1fr}
    .upload-ai-title{font-size:22px}
  }
</style>
<script defer src="{$scriptUrl}"></script>
HTML;
        }

        $hint = htmlspecialchars($contextHint[$context] ?? $contextHint['generic'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $examples = array_map(
            static fn(string $example): string => '<span class="upload-ai-chip">' . htmlspecialchars($example, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>',
            $contextExamples[$context] ?? $contextExamples['generic']
        );
        $originalSlot = $context === 'film'
            ? '<div class="upload-ai-slot" data-upload-slot="origname" data-slot-placeholder="Оригинальное название подхватится сюда"></div>'
            : '';
        $contextEsc = htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= <<<HTML
<div class="upload-ai-card" data-upload-assistant data-context="{$contextEsc}">
  <div class="upload-ai-kicker">Умный помощник</div>
  <div class="upload-ai-title">Одного названия достаточно, чтобы собрать черновик релиза</div>
  <div class="upload-ai-sub">{$hint}</div>
  <div class="upload-ai-intake">
    <div class="upload-ai-search-shell">
      <div class="upload-ai-search-icon">+</div>
      <div class="upload-ai-slot upload-ai-slot-main" data-upload-slot="name" data-slot-placeholder="Поле названия подключится сюда автоматически"></div>
      <button type="button" class="upload-ai-run">Собрать карточку</button>
    </div>
    <div class="upload-ai-chip-row">{$examples}</div>
    <div class="upload-ai-secondary-grid">
      {$originalSlot}
      <div class="upload-ai-slot" data-upload-slot="tfile" data-slot-placeholder=".torrent-файл подключится сюда автоматически"></div>
    </div>
  </div>
  <div class="upload-ai-tools">
    <label><input type="checkbox" class="upload-ai-use-wiki" checked> Разрешить справку из Wikipedia, если локального анализа недостаточно</label>
    <span style="font-size:12px;color:#57534e">Можно вставить только название. MediaInfo и NFO нужны лишь для более точных техданных.</span>
  </div>
  <details class="upload-ai-extra">
    <summary>Усилить анализ через MediaInfo / NFO</summary>
    <div class="upload-ai-grid">
      <div>
        <div class="upload-ai-slot-label">MediaInfo</div>
        <textarea name="ai_mediainfo" class="upload-ai-mediainfo" placeholder="Вставьте MediaInfo, если хотите точнее определить качество, видео и аудио"></textarea>
      </div>
      <div>
        <div class="upload-ai-slot-label">NFO / Release Notes</div>
        <textarea name="ai_nfo" class="upload-ai-nfo" placeholder="Вставьте NFO, релиз-заметки или любую служебную справку"></textarea>
      </div>
    </div>
  </details>
  <input type="hidden" name="upload_assistant_snapshot" class="upload-ai-snapshot" value="">
  <div class="upload-ai-status"></div>
  <div class="upload-ai-results">
    <div class="upload-ai-box">
      <div class="upload-ai-box-title">Что распозналось</div>
      <div class="upload-ai-pill-row"></div>
    </div>
    <div class="upload-ai-box">
      <div class="upload-ai-box-title">Вероятность типа и категория</div>
      <div class="upload-ai-family-row"></div>
      <div class="upload-ai-cat-row"></div>
    </div>
    <div class="upload-ai-box">
      <div class="upload-ai-box-title">Подсказки и источники</div>
      <ul class="upload-ai-list upload-ai-summary-list"></ul>
    </div>
    <div class="upload-ai-box">
      <div class="upload-ai-box-title">Проверка описания</div>
      <ul class="upload-ai-list upload-ai-issue-list"></ul>
    </div>
  </div>
</div>
HTML;

        return $html;
    }
}

if (!function_exists('tracker_upload_ai_generate_suggestions')) {
    function tracker_upload_ai_generate_suggestions(array $input): array
    {
        $context = in_array((string)($input['context'] ?? 'generic'), ['generic', 'film', 'music', 'game', 'soft'], true)
            ? (string)$input['context']
            : 'generic';
        $title = trim((string)($input['title'] ?? ''));
        $altTitle = trim((string)($input['alt_title'] ?? ''));
        $torrentName = trim((string)($input['torrent_name'] ?? ''));
        $mediainfo = trim((string)($input['mediainfo'] ?? ''));
        $nfo = trim((string)($input['nfo'] ?? ''));
        $existingDescr = trim((string)($input['existing_descr'] ?? ''));
        $allowRemote = !empty($input['allow_remote']);
        $fileEntries = is_array($input['file_entries'] ?? null) ? (array)$input['file_entries'] : [];
        $totalSize = (int)($input['total_size'] ?? 0);

        $baseTitle = $title !== '' ? $title : ($altTitle !== '' ? $altTitle : $torrentName);
        $parseSource = trim(implode(' ', array_filter([$baseTitle, $altTitle, $torrentName, $mediainfo, $nfo], static fn(string $value): bool => $value !== '')));
        $release = tracker_upload_ai_parse_release_name($parseSource, $context);

        $fileStats = tracker_upload_ai_analyze_filelist($fileEntries, $totalSize);
        if ($totalSize <= 0) {
            $totalSize = (int)($fileStats['total_size'] ?? 0);
        }
        $fileStats['total_size'] = $totalSize;

        if ($release['format'] ?? '' === '') {
            $ext = strtoupper((string)($fileStats['dominant_extension'] ?? ''));
            if ($ext !== '') {
                $release['format'] = $ext === 'M2TS' ? 'M2TS' : $ext;
            } else {
                $release['format'] = '';
            }
        }

        $families = tracker_upload_ai_guess_family_probabilities($parseSource, $fileStats);
        $familyKey = (string)($families[0]['key'] ?? 'other');
        $categoryCandidates = tracker_upload_ai_guess_category_candidates($families, $release, $fileStats);

        $wiki = ['ru' => null, 'en' => null];
        if ($allowRemote && $release['clean_title'] !== '' && tracker_upload_ai_should_query_wikipedia($release['clean_title'])) {
            $wiki = tracker_upload_ai_fetch_wikipedia_bundle($release['clean_title'], (string)($release['year'] ?? ''));
        }

        $ruWiki = is_array($wiki['ru'] ?? null) ? $wiki['ru'] : null;
        $enWiki = is_array($wiki['en'] ?? null) ? $wiki['en'] : null;
        if ($ruWiki !== null && $ruWiki['title'] !== '') {
            $release['display_title'] = tracker_upload_ai_prettify_wikipedia_title((string)$ruWiki['title']);
        }
        $ruOriginalTitle = $ruWiki !== null
            ? tracker_upload_ai_extract_original_title_from_text((string)($ruWiki['extract'] ?? ($ruWiki['description'] ?? '')))
            : '';
        if ($ruOriginalTitle !== '') {
            $release['original_title'] = $ruOriginalTitle;
        } elseif ($enWiki !== null && $enWiki['title'] !== '') {
            $release['original_title'] = tracker_upload_ai_prettify_wikipedia_title((string)$enWiki['title']);
        }

        $wikiTexts = array_filter([
            (string)($ruWiki['description'] ?? ''),
            (string)($ruWiki['extract'] ?? ''),
            (string)($enWiki['description'] ?? ''),
            (string)($enWiki['extract'] ?? ''),
        ], static fn(string $value): bool => $value !== '');

        if (($release['year'] ?? '') === '') {
            foreach ($wikiTexts as $wikiText) {
                $year = tracker_upload_ai_detect_relevant_year($wikiText);
                if ($year !== '') {
                    $release['year'] = $year;
                    break;
                }
            }
        }

        $genres = tracker_upload_ai_collect_genres($parseSource, ...$wikiTexts);
        $tags = tracker_upload_ai_build_tags($genres, $release, $families, $fileStats);

        if ($release['display_title'] === '' && $altTitle !== '') {
            $release['display_title'] = tracker_upload_ai_titleize($altTitle);
        }
        $release['release_name'] = tracker_upload_ai_compose_release_name($release);

        $summary = '';
        if ($ruWiki !== null && !empty($ruWiki['extract'])) {
            $summary = (string)$ruWiki['extract'];
        } elseif ($enWiki !== null && !empty($enWiki['extract'])) {
            $summary = (string)$enWiki['extract'];
        }
        if ($summary === '') {
            $summary = tracker_upload_ai_fallback_summary($release, $families, $fileStats);
        }

        $summary = trim((string)(preg_replace('~\s+~u', ' ', $summary) ?? $summary));
        if (mb_strlen($summary, 'UTF-8') > 900) {
            $summary = trim(mb_substr($summary, 0, 900, 'UTF-8')) . '...';
        }

        $release['display_title'] = trim((string)($release['display_title'] ?? ''));
        if ($release['display_title'] === '') {
            $release['display_title'] = trim((string)($release['release_name'] ?? $release['source_title'] ?? ''));
        }

        $posterUrl = trim((string)($ruWiki['poster_url'] ?? ($enWiki['poster_url'] ?? '')));
        $descriptionTemplate = tracker_upload_ai_build_description_template($context, [
            'release' => $release,
            'summary' => $summary,
            'genres' => $genres,
            'file_stats' => $fileStats,
            'wikipedia' => $wiki,
            'family_probabilities' => $families,
        ]);

        $audit = tracker_upload_ai_audit_description(
            $existingDescr !== '' ? $existingDescr : ($context === 'generic' ? $descriptionTemplate : $summary),
            [
                'category_id' => (int)($categoryCandidates[0]['id'] ?? 0),
                'family_key' => $familyKey,
                'quality' => (string)($release['quality'] ?? ''),
                'translation' => (string)($release['translation'] ?? ''),
            ]
        );

        $suggestion = [
            'context' => $context,
            'release' => $release,
            'genres' => $genres,
            'tags' => $tags,
            'poster_url' => $posterUrl,
            'summary' => $summary,
            'plain_summary' => $summary,
            'description_template' => $descriptionTemplate,
            'file_stats' => $fileStats,
            'family_probabilities' => $families,
            'category_candidates' => $categoryCandidates,
            'wikipedia' => $wiki,
            'audit' => $audit,
        ];

        $suggestion['field_values'] = tracker_upload_ai_build_field_values($context, $suggestion);
        return $suggestion;
    }
}
