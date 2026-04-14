<?php
require_once("include/bittorrent.php");
require_once("include/multitracker.php");
gzip();

dbconn(false);

/* ===== per-user browse mode (list/thumbs) ===== */
function get_browse_mode(): string {
    global $CURUSER, $memcached;
    $uid = (int)($CURUSER['id'] ?? 0);
    $def = 'list'; // дефолт ВСЕГДА список

    // 1) Memcached
    if (isset($memcached) && $memcached instanceof Memcached) {
        $k = "ui:browse_mode:{$uid}";
        $m = $memcached->get($k);
        if ($m === 'list' || $m === 'thumbs') return $m;
    }

    // 2) per-user cookie fallback
    $ck = "browsemode_u{$uid}";
    if (!empty($_COOKIE[$ck])) {
        $m = $_COOKIE[$ck];
        if ($m === 'list' || $m === 'thumbs') return $m;
    }

    // 3) дефолт
    return $def;
}

function browse_search_terms(string $search): array {
    $search = mb_strtolower(trim($search), 'UTF-8');
    if ($search === '') {
        return [];
    }

    $parts = preg_split('~[^\p{L}\p{N}]+~u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopwords = [
        'фильм', 'фильмы', 'кино', 'сериал', 'сериалы', 'мультфильм', 'мультфильмы',
        'мульт', 'аниме', 'дорама', 'сезон', 'сезоны', 'часть', 'серия', 'серии',
        'смотреть', 'онлайн', 'скачать', 'torrent', 'торрент', 'the', 'a', 'an',
        'про', 'это', 'этот', 'эта', 'эти', 'там', 'тут', 'где', 'когда', 'который',
        'которая', 'которые', 'или', 'для', 'без', 'после', 'перед', 'над', 'под',
        'история', 'киношка', 'movie', 'movies', 'about', 'with', 'this', 'that',
        'ищу', 'найти', 'нужен', 'нужна', 'нужно', 'хочу', 'какой', 'какая',
        'какое', 'какие', 'лучший', 'лучшая', 'новый', 'новая', 'старый', 'старая',
        'плохой', 'плохая', 'хороший', 'хорошая',
    ];
    $stop = array_fill_keys($stopwords, true);
    $terms = [];

    foreach ($parts as $part) {
        if (mb_strlen($part, 'UTF-8') <= 2) {
            continue;
        }
        if (isset($stop[$part])) {
            continue;
        }
        $terms[$part] = $part;
    }

    return array_values($terms);
}

function browse_synonym_groups(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $groups = [
        ['непогода', 'ненастье', 'стихия', 'бедствие', 'катастрофа', 'ураган', 'ураганы', 'шторм', 'штормы', 'торнадо', 'смерч', 'циклон', 'тайфун', 'буря', 'гроза', 'ливень', 'метель', 'weather', 'disaster', 'tornado', 'twister', 'storm', 'hurricane', 'cyclone', 'typhoon', 'blizzard'],
        ['ужасы', 'хоррор', 'horror'],
        ['боевик', 'action'],
        ['комедия', 'comedy'],
        ['драма', 'drama'],
        ['триллер', 'thriller'],
        ['мультфильм', 'мульт', 'animation', 'animated'],
        ['сериал', 'series', 'tv'],
    ];

    $map = [];
    foreach ($groups as $group) {
        $variants = [];
        foreach ($group as $term) {
            $term = tracker_search_normalize_text($term);
            if ($term === '') {
                continue;
            }
            $variants[$term] = $term;
            $latin = tracker_search_transliterate_ru_to_lat($term);
            if ($latin !== '' && $latin !== $term) {
                $variants[$latin] = $latin;
            }
        }

        $variants = array_values($variants);
        foreach ($variants as $variant) {
            $map[$variant] = $variants;
        }
    }

    return $map;
}

function browse_term_stem(string $term): string {
    return tracker_search_stem_token($term);
}

function browse_terms_are_close(string $left, string $right): bool {
    $leftNorm = browse_normalize_fuzzy($left);
    $rightNorm = browse_normalize_fuzzy($right);
    if ($leftNorm === '' || $rightNorm === '') {
        return false;
    }

    if ($leftNorm === $rightNorm) {
        return true;
    }

    $leftStem = browse_term_stem($left);
    $rightStem = browse_term_stem($right);
    if ($leftStem !== '' && $leftStem === $rightStem) {
        return true;
    }

    if (str_contains($leftNorm, $rightNorm) || str_contains($rightNorm, $leftNorm)) {
        return true;
    }

    $distance = levenshtein($leftNorm, $rightNorm);
    $maxLen = max(strlen($leftNorm), strlen($rightNorm));
    if ($maxLen <= 4) {
        return $distance <= 1;
    }
    if ($maxLen <= 7) {
        return $distance <= 2;
    }

    similar_text($leftNorm, $rightNorm, $percent);
    return $distance <= 2 || $percent >= 72.0;
}

function browse_terms_match_semantically(string $term, string $variant): bool {
    $term = tracker_search_normalize_text($term);
    $variant = tracker_search_normalize_text($variant);
    if ($term === '' || $variant === '') {
        return false;
    }

    if ($term === $variant || str_contains($term, $variant) || str_contains($variant, $term)) {
        return true;
    }

    if (browse_terms_are_close($term, $variant)) {
        return true;
    }

    $termStem = browse_term_stem($term);
    $variantStem = browse_term_stem($variant);
    return $termStem !== '' && $termStem === $variantStem;
}

function browse_collect_concept_variants(string $term): array {
    $term = tracker_search_normalize_text($term);
    if ($term === '') {
        return [];
    }

    $variants = [];
    foreach (tracker_search_concept_catalog() as $concept) {
        $aliases = array_merge($concept['labels'] ?? [], $concept['aliases'] ?? []);
        $matched = false;

        foreach ($aliases as $alias) {
            $alias = tracker_search_normalize_text((string)$alias);
            if ($alias === '' || !browse_terms_match_semantically($term, $alias)) {
                continue;
            }

            $matched = true;
            break;
        }

        if (!$matched) {
            continue;
        }

        foreach ($aliases as $alias) {
            $alias = tracker_search_normalize_text((string)$alias);
            if ($alias === '') {
                continue;
            }

            $variants[$alias] = $alias;
            $latin = tracker_search_transliterate_ru_to_lat($alias);
            if ($latin !== '' && $latin !== $alias) {
                $variants[$latin] = $latin;
            }
        }
    }

    return array_values($variants);
}

function browse_expand_term_variants(string $term): array {
    $term = tracker_search_normalize_text($term);
    if ($term === '') {
        return [];
    }

    $variants = [$term => $term];
    $latin = tracker_search_transliterate_ru_to_lat($term);
    if ($latin !== '' && $latin !== $term) {
        $variants[$latin] = $latin;
    }

    $groups = browse_synonym_groups();
    if (isset($groups[$term])) {
        foreach ($groups[$term] as $variant) {
            $variants[$variant] = $variant;
        }
    } else {
        foreach ($groups as $key => $group) {
            if (!browse_terms_are_close($term, $key)) {
                continue;
            }

            foreach ($group as $variant) {
                $variants[$variant] = $variant;
            }
        }
    }

    foreach (browse_collect_concept_variants($term) as $variant) {
        $variants[$variant] = $variant;
    }

    return array_values($variants);
}

function browse_query_term_groups(string $search): array {
    $groups = [];
    foreach (array_slice(browse_search_terms($search), 0, 6) as $term) {
        $variants = browse_expand_term_variants($term);
        if (!$variants) {
            continue;
        }

        sort($variants);
        $groups[implode('|', $variants)] = $variants;
    }

    return array_values($groups);
}

function browse_build_group_match_expression(array $variants, array $fields): ?string {
    $variants = array_values(array_unique(array_filter(array_map('tracker_search_normalize_text', $variants))));
    if (!$variants || !$fields) {
        return null;
    }

    $parts = [];
    foreach ($fields as $field) {
        foreach ($variants as $variant) {
            $parts[] = "LOWER({$field}) LIKE '%" . sqlwildcardesc($variant) . "%'";
        }
    }

    return $parts ? '(' . implode(' OR ', $parts) . ')' : null;
}

function browse_build_match_count_expression(string $search, string $scope = 'ai'): string {
    $groups = browse_query_term_groups($search);
    if (!$groups) {
        return '0';
    }

    $fields = browse_search_fields($scope);
    $parts = [];
    foreach ($groups as $group) {
        $groupExpr = browse_build_group_match_expression($group, $fields);
        if ($groupExpr === null) {
            continue;
        }

        $parts[] = "(CASE WHEN {$groupExpr} THEN 1 ELSE 0 END)";
    }

    return $parts ? '(' . implode(' + ', $parts) . ')' : '0';
}

function browse_required_match_count(string $search): int {
    $groupCount = count(browse_query_term_groups($search));
    if ($groupCount <= 0) {
        return 0;
    }
    if ($groupCount <= 2) {
        return $groupCount;
    }

    return max(2, (int)ceil($groupCount * 0.66));
}

function browse_search_candidates(string $search): array {
    $search = trim($search);
    if ($search === '') {
        return [];
    }

    $candidates = [];
    $variants = [$search, tracker_search_normalize_text($search)];

    foreach ($variants as $variant) {
        $variant = tracker_search_normalize_text($variant);
        if ($variant === '') {
            continue;
        }

        $candidates[$variant] = $variant;

        $latinVariant = tracker_search_transliterate_ru_to_lat($variant);
        if ($latinVariant !== '' && $latinVariant !== $variant) {
            $candidates[$latinVariant] = $latinVariant;
        }

        $terms = browse_search_terms($variant);
        if ($terms) {
            $joined = tracker_search_normalize_text(implode(' ', $terms));
            if ($joined !== '') {
                $candidates[$joined] = $joined;
            }
            foreach ($terms as $term) {
                $term = tracker_search_normalize_text($term);
                if ($term === '') {
                    continue;
                }

                $candidates[$term] = $term;

                $latinTerm = tracker_search_transliterate_ru_to_lat($term);
                if ($latinTerm !== '' && $latinTerm !== $term) {
                    $candidates[$latinTerm] = $latinTerm;
                }
            }
        }
    }

    return array_slice(array_values(array_filter($candidates)), 0, 24);
}

function browse_swap_keyboard_layout(string $search, bool $enToRu): string {
    $search = trim($search);
    if ($search === '') {
        return '';
    }

    $en = "qwertyuiop[]asdfghjkl;'zxcvbnm,.`QWERTYUIOP{}ASDFGHJKL:\"ZXCVBNM<>~";
    $ru = "йцукенгшщзхъфывапролджэячсмитьбюёйЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮЁ";

    return trim($enToRu ? strtr($search, $en, $ru) : strtr($search, $ru, $en));
}

function browse_fix_keyboard_layout(string $search): string {
    $search = trim($search);
    if ($search === '') {
        return '';
    }

    preg_match_all('~[A-Za-z]~u', $search, $latinMatches);
    preg_match_all('~[А-Яа-яЁё]~u', $search, $cyrMatches);
    $latinCount = count($latinMatches[0] ?? []);
    $cyrCount = count($cyrMatches[0] ?? []);

    if ($latinCount > $cyrCount) {
        return browse_swap_keyboard_layout($search, true);
    }
    if ($cyrCount > 0) {
        return browse_swap_keyboard_layout($search, false);
    }

    return browse_swap_keyboard_layout($search, true);
}

function browse_search_fields(string $scope): array {
    return match ($scope) {
        'descr' => ['torrents.descr', 'torrents.ori_descr'],
        'tags' => ['torrents.tags'],
        'all', 'ai' => ['torrents.name', 'torrents.search_text', 'torrents.descr', 'torrents.ori_descr', 'torrents.tags'],
        default => ['torrents.name', 'torrents.search_text'],
    };
}

function browse_search_uses_relevance(string $scope): bool {
    return in_array($scope, ['ai', 'all'], true);
}

function browse_compile_where(array $clauses, string $wherecatin = ''): string {
    $clauses = array_values(array_filter($clauses, static fn($clause): bool => is_string($clause) && trim($clause) !== ''));
    $where = implode(' AND ', $clauses);
    if ($wherecatin !== '') {
        $where .= ($where !== '' ? ' AND ' : '') . "category IN ({$wherecatin})";
    }
    return $where !== '' ? "WHERE {$where}" : '';
}

function browse_fulltext_index_mode(): string {
    global $mysqli;

    static $mode = null;
    if ($mode !== null) {
        return $mode;
    }

    $indexes = [];
    $res = mysqli_query($mysqli, "SHOW INDEX FROM torrents WHERE Index_type = 'FULLTEXT'");
    if (!$res) {
        $mode = '';
        return $mode;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $key = (string)($row['Key_name'] ?? '');
        $seq = (int)($row['Seq_in_index'] ?? 0);
        $column = (string)($row['Column_name'] ?? '');
        if ($key === '' || $column === '' || $seq <= 0) {
            continue;
        }
        $indexes[$key][$seq] = $column;
    }

    foreach ($indexes as &$columns) {
        ksort($columns);
        $columns = array_values($columns);
    }
    unset($columns);

    foreach ($indexes as $columns) {
        if (count($columns) === 4 && !array_diff(['name', 'search_text', 'descr', 'tags'], $columns)) {
            $mode = 'ai';
            return $mode;
        }
    }

    foreach ($indexes as $columns) {
        if (count($columns) === 3 && !array_diff(['name', 'descr', 'tags'], $columns)) {
            $mode = 'basic';
            return $mode;
        }
    }

    $mode = '';
    return $mode;
}

function browse_build_fulltext_condition(string $search, string $scope): ?string {
    $search = tracker_search_normalize_text($search);
    if ($search === '' || mb_strlen($search, 'UTF-8') < 3 || !browse_search_uses_relevance($scope)) {
        return null;
    }

    return match (browse_fulltext_index_mode()) {
        'ai' => "MATCH(torrents.name, torrents.search_text, torrents.descr, torrents.tags) AGAINST (" . sqlesc($search) . " IN NATURAL LANGUAGE MODE)",
        'basic' => "MATCH(torrents.name, torrents.descr, torrents.tags) AGAINST (" . sqlesc($search) . " IN NATURAL LANGUAGE MODE)",
        default => null,
    };
}

function browse_normalize_fuzzy(string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    if ($value === '') {
        return '';
    }

    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $value = strtr($value, $map);
    $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?? '';
    $value = trim(preg_replace('~\s+~', ' ', $value) ?? $value);
    return $value;
}

function browse_find_fuzzy_suggestion(mysqli $mysqli, string $search, array $wherebase, string $wherecatin = ''): string {
    $cacheKey = tracker_cache_ns_key('browse', 'fuzzy', md5(json_encode([
        'search' => $search,
        'wherebase' => array_values($wherebase),
        'wherecatin' => $wherecatin,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $search . '|' . $wherecatin));

    $cached = tracker_cache_get($cacheKey, $hit);
    if ($hit) {
        return is_string($cached) ? $cached : '';
    }

    $terms = browse_search_terms($search);
    $focus = '';
    foreach ($terms as $term) {
        if (mb_strlen($term, 'UTF-8') > mb_strlen($focus, 'UTF-8')) {
            $focus = $term;
        }
    }
    if ($focus === '') {
        $focus = trim($search);
    }
    if ($focus === '' || mb_strlen($focus, 'UTF-8') < 3) {
        return '';
    }

    $prefix = mb_substr($focus, 0, min(3, mb_strlen($focus, 'UTF-8')), 'UTF-8');
    $likePrefix = sqlwildcardesc($prefix);
    $where = $wherebase;
    $where[] = "torrents.name LIKE '%{$likePrefix}%'";
    if ($wherecatin !== '') {
        $where[] = "category IN ({$wherecatin})";
    }

    $sqlWhere = implode(' AND ', $where);
    if ($sqlWhere !== '') {
        $sqlWhere = 'WHERE ' . $sqlWhere;
    }

    $res = sql_query("
        SELECT torrents.name
        FROM torrents
        {$sqlWhere}
        ORDER BY torrents.sticky ASC, torrents.id DESC
        LIMIT 250
    ");

    $searchNorm = browse_normalize_fuzzy($focus);
    if ($searchNorm === '') {
        return '';
    }

    $bestName = '';
    $bestScore = 0.0;
    while ($row = mysqli_fetch_assoc($res)) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $nameNorm = browse_normalize_fuzzy($name);
        if ($nameNorm === '') {
            continue;
        }

        similar_text($searchNorm, $nameNorm, $percent);
        if ($percent < 55.0) {
            continue;
        }

        if ($percent > $bestScore) {
            $bestScore = $percent;
            $bestName = $name;
        }
    }

    $result = $bestScore >= 62.0 ? $bestName : '';
    tracker_cache_set($cacheKey, $result, 180);

    return $result;
}

function browse_build_search_condition(string $search, string $scope = 'title'): ?string {
    $search = trim($search);
    if ($search === '') {
        return null;
    }

    $searchParts = [];
    $fulltextCondition = browse_build_fulltext_condition($search, $scope);
    if ($fulltextCondition !== null) {
        $searchParts[] = '(' . $fulltextCondition . ' > 0)';
    }

    $fields = browse_search_fields($scope);
    $candidatePool = browse_search_candidates($search);
    foreach (browse_query_term_groups($search) as $group) {
        foreach ($group as $variant) {
            $candidatePool[] = $variant;
        }
    }

    foreach (array_values(array_unique(array_filter($candidatePool))) as $candidate) {
        $fieldParts = [];
        foreach ($fields as $field) {
            $fieldParts[] = "LOWER({$field}) LIKE '%" . sqlwildcardesc($candidate) . "%'";
        }
        if ($fieldParts) {
            $searchParts[] = '(' . implode(' OR ', $fieldParts) . ')';
        }
    }

    $searchParts = array_values(array_unique($searchParts));
    if (!$searchParts) {
        return null;
    }

    return '(' . implode(' OR ', $searchParts) . ')';
}

function browse_build_search_rank_expression(string $search, string $scope = 'ai'): string {
    $search = trim($search);
    if ($search === '') {
        return '0';
    }

    $parts = ['0'];
    $matchCountExpr = browse_build_match_count_expression($search, $scope);
    $requiredMatches = browse_required_match_count($search);
    if ($requiredMatches > 0) {
        $parts[] = "(({$matchCountExpr}) * 85)";
    }

    foreach (browse_query_term_groups($search) as $group) {
        $titleGroupExpr = browse_build_group_match_expression($group, ['torrents.name']);
        if ($titleGroupExpr !== null) {
            $parts[] = "(CASE WHEN {$titleGroupExpr} THEN 52 ELSE 0 END)";
        }

        $indexGroupExpr = browse_build_group_match_expression($group, ['torrents.search_text']);
        if ($indexGroupExpr !== null) {
            $parts[] = "(CASE WHEN {$indexGroupExpr} THEN 24 ELSE 0 END)";
        }
    }

    $normalized = tracker_search_normalize_text($search);
    if ($normalized !== '') {
        $like = sqlwildcardesc($normalized);
        $parts[] = "(CASE WHEN LOWER(torrents.name) = '{$like}' THEN 240 ELSE 0 END)";
        $parts[] = "(CASE WHEN LOWER(torrents.name) LIKE '{$like}%' THEN 140 ELSE 0 END)";
        $parts[] = "(CASE WHEN LOWER(torrents.name) LIKE '%{$like}%' THEN 80 ELSE 0 END)";
        $parts[] = "(CASE WHEN LOWER(torrents.search_text) LIKE '%{$like}%' THEN 55 ELSE 0 END)";

        if (browse_search_uses_relevance($scope)) {
            $parts[] = "(CASE WHEN LOWER(torrents.tags) LIKE '%{$like}%' THEN 45 ELSE 0 END)";
            $parts[] = "(CASE WHEN LOWER(torrents.descr) LIKE '%{$like}%' OR LOWER(torrents.ori_descr) LIKE '%{$like}%' THEN 28 ELSE 0 END)";
        }

        $fulltext = browse_build_fulltext_condition($normalized, $scope);
        if ($fulltext !== null) {
            $parts[] = "(GREATEST({$fulltext}, 0) * 30)";
        }
    }

    $terms = array_slice(browse_search_terms($search), 0, 6);
    $nameAll = [];
    $indexAll = [];
    foreach ($terms as $term) {
        $term = tracker_search_normalize_text($term);
        if ($term === '') {
            continue;
        }

        $like = sqlwildcardesc($term);
        $parts[] = "(CASE WHEN LOWER(torrents.name) LIKE '%{$like}%' THEN 20 ELSE 0 END)";
        $parts[] = "(CASE WHEN LOWER(torrents.search_text) LIKE '%{$like}%' THEN 12 ELSE 0 END)";
        if (browse_search_uses_relevance($scope)) {
            $parts[] = "(CASE WHEN LOWER(torrents.tags) LIKE '%{$like}%' THEN 14 ELSE 0 END)";
        }

        $nameAll[] = "LOWER(torrents.name) LIKE '%{$like}%'";
        $indexAll[] = "LOWER(torrents.search_text) LIKE '%{$like}%'";
    }

    if (count($nameAll) > 1) {
        $parts[] = "(CASE WHEN (" . implode(' AND ', $nameAll) . ") THEN 90 ELSE 0 END)";
    }
    if (count($indexAll) > 1) {
        $parts[] = "(CASE WHEN (" . implode(' AND ', $indexAll) . ") THEN 60 ELSE 0 END)";
    }

    $candidates = array_slice(browse_search_candidates($search), 0, 8);
    foreach ($candidates as $candidate) {
        if ($candidate === '' || $candidate === $normalized) {
            continue;
        }

        $like = sqlwildcardesc($candidate);
        $parts[] = "(CASE WHEN LOWER(torrents.name) LIKE '%{$like}%' THEN 26 ELSE 0 END)";
        $parts[] = "(CASE WHEN LOWER(torrents.search_text) LIKE '%{$like}%' THEN 18 ELSE 0 END)";
    }

    $parts[] = "(CASE WHEN torrents.sticky = 'yes' THEN 10 ELSE 0 END)";
    $parts[] = "(LEAST(torrents.seeders, 50) * 0.35)";
    $parts[] = "(LEAST(torrents.times_completed, 250) * 0.08)";

    return '(' . implode(' + ', $parts) . ')';
}

function browse_build_format_condition(string $format): ?string {
    $format = mb_strtolower(trim($format), 'UTF-8');
    if ($format === '') {
        return null;
    }

    return "(LOWER(torrents.search_text) LIKE '%" . sqlwildcardesc($format) . "%' OR LOWER(torrents.descr) LIKE '%" . sqlwildcardesc($format) . "%' OR LOWER(torrents.name) LIKE '%" . sqlwildcardesc($format) . "%')";
}

function browse_build_year_condition(int $year): ?string {
    if ($year < 1900 || $year > 2100) {
        return null;
    }

    $yearStr = (string)$year;
    return "(torrents.name LIKE '%{$yearStr}%' OR torrents.search_text LIKE '%{$yearStr}%' OR torrents.descr LIKE '%{$yearStr}%' OR YEAR(torrents.added) = {$year})";
}

function browse_format_options(): array {
    return [
        '' => 'Все форматы',
        'avi' => 'AVI',
        'mkv' => 'MKV',
        'mp4' => 'MP4',
        'dvd5' => 'DVD5',
        'dvd9' => 'DVD9',
        'web-dl' => 'WEB-DL',
        'webrip' => 'WEBRip',
        'bdrip' => 'BDRip',
        'blu-ray' => 'Blu-ray',
        'hdtv' => 'HDTV',
        'dvdrip' => 'DVDRip',
        'camrip' => 'CAMRip',
        'ts' => 'TS',
    ];
}

function browse_cached_count(string $where): int {
    $sql = "SELECT COUNT(*) AS cnt FROM torrents {$where}";
    $cacheKey = tracker_cache_ns_key('browse', 'count', md5($sql));

    $count = tracker_cache_remember($cacheKey, 45, static function () use ($sql): int {
        $res = sql_query($sql);
        $row = mysqli_fetch_assoc($res);
        return (int)($row['cnt'] ?? 0);
    });

    return (int)$count;
}

function browse_cached_rows(string $query, int $ttl = 45): array {
    $cacheKey = tracker_cache_ns_key('browse', 'rows', md5($query));

    $rows = tracker_cache_remember($cacheKey, $ttl, static function () use ($query): array {
        $res = sql_query($query);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        return $rows;
    });

    return is_array($rows) ? $rows : [];
}

function browse_sort_config(bool $canModerate): array {
    $config = [
        '1' => ['column' => 'torrents.name', 'default' => 'ASC'],
        '2' => ['column' => 'torrents.numfiles', 'default' => 'DESC'],
        '3' => ['column' => 'torrents.comments', 'default' => 'DESC'],
        // Для свежих раздач сортировка по id дешевле и эквивалентна added на этом движке.
        '4' => ['column' => 'torrents.id', 'default' => 'DESC'],
        '5' => ['column' => 'torrents.size', 'default' => 'DESC'],
        '6' => ['column' => 'torrents.times_completed', 'default' => 'DESC'],
        '7' => ['column' => 'torrents.seeders', 'default' => 'DESC'],
        '8' => ['column' => 'torrents.leechers', 'default' => 'DESC'],
        '9' => ['column' => 'torrents.owner', 'default' => 'DESC'],
    ];

    if ($canModerate) {
        $config['10'] = ['column' => 'torrents.modby', 'default' => 'DESC'];
    }

    return $config;
}

function browse_order_by_clause(?string $sort, ?string $type, bool $canModerate): array {
    $config = browse_sort_config($canModerate);
    $sort = (string)$sort;
    $type = strtolower((string)$type);

    if (!isset($config[$sort])) {
        return ['ORDER BY torrents.sticky DESC, torrents.id DESC', ''];
    }

    $direction = $type === 'asc' ? 'ASC' : ($type === 'desc' ? 'DESC' : $config[$sort]['default']);
    $column = $config[$sort]['column'];

    return [
        "ORDER BY torrents.sticky DESC, {$column} {$direction}, torrents.id DESC",
        'sort=' . rawurlencode($sort) . '&type=' . strtolower($direction) . '&',
    ];
}



parked();

// Получаем список жанров
$cats = genrelist();

$searchstr = unesc($_GET["search"] ?? '');
$cleansearchstr = htmlspecialchars($searchstr);
if (empty($cleansearchstr)) unset($cleansearchstr);

$tagstr = unesc($_GET["tag"] ?? '');
$cleantagstr = htmlspecialchars($tagstr);
if (empty($cleantagstr)) unset($cleantagstr);

$letter = trim($_GET["letter"] ?? '');
if (strlen($letter) > 3) die();
if (empty($letter)) unset($letter);
    $searchSuggestion = '';
$searchIn = trim((string)($_GET['where'] ?? 'ai'));
if (!in_array($searchIn, ['ai', 'title', 'descr', 'tags', 'all'], true)) {
    $searchIn = 'ai';
}
$searchQueryForRank = $searchstr;
$searchRankExpr = '0';
$searchMatchCountExpr = '0';
$searchMinMatches = 0;
$hasManualSort = trim((string)($_GET['sort'] ?? '')) !== '' && (int)($_GET['sort'] ?? 0) !== 0;
$formatFilter = trim((string)($_GET['format'] ?? ''));
$formatOptions = browse_format_options();
if (!array_key_exists($formatFilter, $formatOptions)) {
    $formatFilter = '';
}
$yearFilter = (int)($_GET['year'] ?? 0);

[$orderby, $pagerlink] = browse_order_by_clause(
    $_GET['sort'] ?? null,
    $_GET['type'] ?? null,
    get_user_class() >= UC_MODERATOR
);

$addparam = "";
$wherea = [];
$wherecatina = [];

if (isset($_GET["incldead"])) {
    if ($_GET["incldead"] == 1) {
        $addparam .= "incldead=1&amp;";
        if (!isset($CURUSER) || get_user_class() < UC_ADMINISTRATOR)
            $wherea[] = "banned != 'yes'";
    } elseif ($_GET["incldead"] == 2) {
        $addparam .= "incldead=2&amp;";
        $wherea[] = "visible = 'no'";
    }
}

$category = (int)($_GET["cat"] ?? 0);
$all = $_GET["all"] ?? false;

if (!$all) {
    if (!$_GET && !empty($CURUSER["notifs"])) {
        $all = true;
        foreach ($cats as $cat) {
            $catid = $cat['id'];
            $all &= $catid;
            if (strpos($CURUSER["notifs"], "[cat$catid]") !== false) {
                $wherecatina[] = $catid;
                $addparam .= "c$catid=1&amp;";
            }
        }
    } elseif ($category) {
        if (!is_valid_id($category)) {
            stderr($tracker_lang['error'], "Invalid category ID.");
        }
        $wherecatina[] = $category;
        $addparam .= "cat=$category&amp;";
    } else {
        $all = true;
        foreach ($cats as $cat) {
            $catid = $cat['id'];
            $all &= ($_GET["c$catid"] ?? false);
            if ($_GET["c$catid"] ?? false) {
                $wherecatina[] = $catid;
                $addparam .= "c$catid=1&amp;";
            }
        }
    }
}

if ($all) {
    $wherecatina = [];
    $addparam = "";
}

if (count($wherecatina) > 1)
    $wherecatin = implode(",", $wherecatina);
elseif (count($wherecatina) == 1)
    $wherea[] = "category = {$wherecatina[0]}";

$wherebase = $wherea;

if (isset($cleantagstr)) {
    $wherea[] = "torrents.tags LIKE '%" . sqlwildcardesc($tagstr) . "%'";
    $addparam .= "tag=" . urlencode($tagstr) . "&";
}

if (isset($letter)) {
    $wherea[] = "torrents.name LIKE BINARY '" . mysqli_real_escape_string($mysqli, $letter) . "%'";
    $addparam .= "letter=" . urlencode($letter) . "&amp;";
}

$formatCondition = browse_build_format_condition($formatFilter);
if ($formatCondition !== null) {
    $wherea[] = $formatCondition;
    $addparam .= "format=" . urlencode($formatFilter) . "&amp;";
}

$yearCondition = browse_build_year_condition($yearFilter);
if ($yearCondition !== null) {
    $wherea[] = $yearCondition;
    $addparam .= "year=" . $yearFilter . "&amp;";
}

if (isset($cleansearchstr)) {
    $searchCondition = browse_build_search_condition($searchstr, $searchIn);
    if ($searchCondition !== null) {
        $wherea[] = $searchCondition;
        $searchRankExpr = browse_build_search_rank_expression($searchQueryForRank, $searchIn);
        $searchMatchCountExpr = browse_build_match_count_expression($searchQueryForRank, $searchIn);
        $searchMinMatches = browse_required_match_count($searchQueryForRank);
        if ($searchMinMatches > 0) {
            $wherea[] = "({$searchMatchCountExpr}) >= {$searchMinMatches}";
        }
    }
    $addparam .= "search=" . urlencode($searchstr) . "&amp;";
    if ($searchIn !== 'ai') {
        $addparam .= "where=" . urlencode($searchIn) . "&amp;";
    }
}

$where = browse_compile_where($wherea, $wherecatin ?? '');
$count = browse_cached_count($where);
$num_torrents = $count;

if (!$count && isset($cleansearchstr)) {
    $wherea = $wherebase;
    $searcha = explode(" ", $cleansearchstr);
    $sc = 0;
    foreach ($searcha as $searchss) {
        if (strlen($searchss) <= 1) continue;
        $sc++;
        if ($sc > 5) break;
        $wherea[] = "torrents.name LIKE '%" . sqlwildcardesc($searchss) . "%'";
    }
        if ($sc) {
            if (isset($cleantagstr)) {
                $wherea[] = "torrents.tags LIKE '%" . sqlwildcardesc($tagstr) . "%'";
            }
            if (isset($letter)) {
            $wherea[] = "torrents.name LIKE BINARY '" . mysqli_real_escape_string($mysqli, $letter) . "%'";
        }
        if ($formatCondition !== null) {
            $wherea[] = $formatCondition;
        }
            if ($yearCondition !== null) {
                $wherea[] = $yearCondition;
            }
            $fallbackMatchExpr = browse_build_match_count_expression($searchstr, $searchIn);
            $fallbackMinMatches = browse_required_match_count($searchstr);
            if ($fallbackMinMatches > 0) {
                $wherea[] = "({$fallbackMatchExpr}) >= {$fallbackMinMatches}";
            }

            $where = browse_compile_where($wherea, $wherecatin ?? '');
            $count = browse_cached_count($where);
            $num_torrents = $count;
        }
}

if (!$count && isset($cleansearchstr)) {
    $fixedSearch = browse_fix_keyboard_layout($searchstr);
    if ($fixedSearch !== '' && mb_strtolower($fixedSearch, 'UTF-8') !== mb_strtolower($searchstr, 'UTF-8')) {
        $wherea = $wherebase;
        $fixedCondition = browse_build_search_condition($fixedSearch, $searchIn);
        if ($fixedCondition !== null) {
            $wherea[] = $fixedCondition;
            if (isset($cleantagstr)) {
                $wherea[] = "torrents.tags LIKE '%" . sqlwildcardesc($tagstr) . "%'";
            }
            if (isset($letter)) {
                $wherea[] = "torrents.name LIKE BINARY '" . mysqli_real_escape_string($mysqli, $letter) . "%'";
            }
            if ($formatCondition !== null) {
                $wherea[] = $formatCondition;
            }
            if ($yearCondition !== null) {
                $wherea[] = $yearCondition;
            }
            $fixedMatchExpr = browse_build_match_count_expression($fixedSearch, $searchIn);
            $fixedMinMatches = browse_required_match_count($fixedSearch);
            if ($fixedMinMatches > 0) {
                $wherea[] = "({$fixedMatchExpr}) >= {$fixedMinMatches}";
            }

            $where = browse_compile_where($wherea, $wherecatin ?? '');
            $count = browse_cached_count($where);
            $num_torrents = $count;

            if ($count > 0) {
                $searchSuggestion = $fixedSearch;
                $searchQueryForRank = $fixedSearch;
                $searchRankExpr = browse_build_search_rank_expression($searchQueryForRank, $searchIn);
                $searchMatchCountExpr = $fixedMatchExpr;
                $searchMinMatches = $fixedMinMatches;
            }
        }
    }
}

if (!$count && isset($cleansearchstr)) {
    $fuzzySearch = browse_find_fuzzy_suggestion($mysqli, $searchstr, $wherebase, $wherecatin ?? '');
    if ($fuzzySearch !== '' && mb_strtolower($fuzzySearch, 'UTF-8') !== mb_strtolower($searchstr, 'UTF-8')) {
        $wherea = $wherebase;
        $fuzzyCondition = browse_build_search_condition($fuzzySearch, $searchIn);
        if ($fuzzyCondition !== null) {
            $wherea[] = $fuzzyCondition;
            if (isset($cleantagstr)) {
                $wherea[] = "torrents.tags LIKE '%" . sqlwildcardesc($tagstr) . "%'";
            }
            if (isset($letter)) {
                $wherea[] = "torrents.name LIKE BINARY '" . mysqli_real_escape_string($mysqli, $letter) . "%'";
            }
            if ($formatCondition !== null) {
                $wherea[] = $formatCondition;
            }
            if ($yearCondition !== null) {
                $wherea[] = $yearCondition;
            }
            $fuzzyMatchExpr = browse_build_match_count_expression($fuzzySearch, $searchIn);
            $fuzzyMinMatches = browse_required_match_count($fuzzySearch);
            if ($fuzzyMinMatches > 0) {
                $wherea[] = "({$fuzzyMatchExpr}) >= {$fuzzyMinMatches}";
            }

            $where = browse_compile_where($wherea, $wherecatin ?? '');
            $count = browse_cached_count($where);
            $num_torrents = $count;

            if ($count > 0) {
                $searchSuggestion = $fuzzySearch;
                $searchQueryForRank = $fuzzySearch;
                $searchRankExpr = browse_build_search_rank_expression($searchQueryForRank, $searchIn);
                $searchMatchCountExpr = $fuzzyMatchExpr;
                $searchMinMatches = $fuzzyMinMatches;
            }
        }
    }
}

$torrentsperpage = $CURUSER["torrentsperpage"] ?? 20;

if ($count) {
    if (isset($cleansearchstr) && browse_search_uses_relevance($searchIn) && !$hasManualSort) {
        $orderby = "ORDER BY search_rank DESC, torrents.sticky DESC, torrents.id DESC";
    }

    if ($addparam != "") {
        if ($pagerlink != "") {
            if (substr($addparam, -1) != ";") {
                $addparam .= "&" . $pagerlink;
            } else {
                $addparam .= $pagerlink;
            }
        }
    } else {
        $addparam = $pagerlink;
    }

    list($pagertop, $pagerbottom, $limit) = pager2($torrentsperpage, $count, "browse.php?" . $addparam);

$query = "SELECT 
        torrents.id, torrents.modded, torrents.modby, torrents.modname,
        torrents.category, torrents.tags, torrents.leechers, torrents.seeders,
        torrents.free, torrents.name, torrents.times_completed, torrents.size,
        torrents.added, torrents.comments, torrents.numfiles, torrents.filename,
        torrents.sticky, torrents.owner,
        torrents.image1,
        COALESCE(mts.external_seeders, 0) AS external_seeders,
        COALESCE(mts.external_leechers, 0) AS external_leechers,
        COALESCE(mts.external_completed, 0) AS external_completed,
        IF(torrents.numratings < $minvotes, NULL, ROUND(torrents.ratingsum / torrents.numratings, 1)) AS rating,
        categories.name AS cat_name, categories.image AS cat_pic,
        users.username, users.class,
        {$searchRankExpr} AS search_rank
        FROM torrents
        LEFT JOIN categories ON category = categories.id
        LEFT JOIN users ON torrents.owner = users.id
        " . multitracker_stats_summary_sql('torrents') . "
        $where $orderby $limit";


    $res = browse_cached_rows($query, isset($cleansearchstr) ? 30 : 45);
} else {
    unset($res);
}

if (isset($cleansearchstr))
    stdhead($tracker_lang['search_results_for'] . " \"$searchstr\"");
elseif (isset($cleantagstr))
    stdhead("Результаты поиска по тэгу \"$tagstr\"");
else
    stdhead($tracker_lang['browse']);


?>

<STYLE TYPE="text/css" MEDIA=screen>

  a.catlink:link, a.catlink:visited{
                text-decoration: none;
        }

        a.catlink:hover {
                color: #A83838;
        }

</STYLE>

<style>
/* ===================== */
/*   design tokens       */
/* ===================== */
:root{
  --bg:#f5f7fb;
  --text:#0f172a;
  --muted:#5b6476;
  --line:rgba(0,0,0,.08);
  --glass-1:rgba(255,255,255,.55);
  --glass-2:rgba(255,255,255,.18);
  --radius:12px;
  --pad:10px;
  --shadow:0 2px 8px rgba(0,0,0,.10);
  --shadow-hover:0 6px 16px rgba(0,0,0,.14);
  --glass-border:1px solid rgba(255,255,255,.45);
  --glass-grad:linear-gradient(180deg,var(--glass-1),var(--glass-2));
}

/* graceful degradation */
.no-glass .glass, .no-glass .glass-btn{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}

/* base */
body{color:var(--text);background:var(--bg)}
small,.small{color:var(--muted)}
.h1,.h2{font-weight:800;letter-spacing:.2px}
.pd10{padding:10px}.pd20{padding:16px 18px}

/* ===================== */
/*   liquid-glass panel  */
/* ===================== */
.panel.widget{
  border:1px solid var(--line);
  border-radius:calc(var(--radius) + 2px);
  background:var(--glass-grad);
  box-shadow:var(--shadow);
  backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
}

/* ===================== */
/*   buttons / inputs    */
/* ===================== */
.btn,.glass-btn,input[type=submit].glass-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 12px;border-radius:999px;border:var(--glass-border);
  background:linear-gradient(180deg,rgba(255,255,255,.55),rgba(255,255,255,.16));
  color:var(--text);font-weight:700;font-size:12px;text-decoration:none;
  backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
  box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 1px 3px rgba(0,0,0,.08);
  transition:transform .12s ease,box-shadow .12s ease,background .12s ease;
}
.btn:hover,.glass-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-hover)}
.btn.is-active,.glass-btn.active{outline:1px solid rgba(255,255,255,.7)}

.input,.search,.browse-select{
  padding:8px 10px;font-size:13px;border:1px solid rgba(0,0,0,.14);
  border-radius:10px;background:#fff;color:var(--text);
  box-shadow:0 0 0 2px rgba(255,255,255,.6) inset;
}
.browse-fieldset{
  border:1px solid var(--line);border-radius:var(--radius);padding:var(--pad);
  background:var(--glass-grad);
  backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
}

/* ===================== */
/*   view toggle + tiles */
/* ===================== */
.view-toggle{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin:6px 0}
.view-toggle .glass-btn{padding:6px 10px;border-radius:12px}

.thumb-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,220px);
  justify-content:flex-start;
  gap:24px;
  align-items:start;
  padding:8px 6px;
}
.thumb-card{
  display:flex;
  flex-direction:column;
  gap:8px;
  width:220px;
  height:100%;
  margin:0;
  padding:10px;
  border:1px solid rgba(0,0,0,.10);
  border-radius:14px;
  background:#fff;
  color:inherit;
  box-shadow:0 2px 8px rgba(0,0,0,.08);
  transition:border-color .12s ease,box-shadow .12s ease,transform .12s ease;
}
.thumb-card:hover{transform:translateY(-2px);border-color:rgba(0,0,0,.18);box-shadow:0 8px 18px rgba(0,0,0,.12)}
.thumb-link{display:block;text-decoration:none;color:inherit;background:#fff}
.thumb-link:hover,.thumb-link:focus{background:#fff;color:inherit;text-decoration:none}
.thumb-img{
  display:block;
  width:100%;
  aspect-ratio:134/188;
  height:auto;
  object-fit:cover;
  border-radius:10px;
  border:1px solid rgba(0,0,0,.12);
  background:#f4f4f4;
}
.thumb-title{
  font:inherit;
  font-weight:700;
  line-height:1.25;
  color:inherit;
  min-height:2.5em;
  overflow:hidden;
}
.thumb-sub{
  font:inherit;
  font-size:12px;
  line-height:1.35;
  color:#555;
}
.thumb-rating{
  display:flex;
  align-items:center;
  gap:8px;
  min-height:20px;
}
.thumb-rating .rating{
  min-width:125px;
}
.thumb-rating .star-rating{
  margin:0;
}
.thumb-rating-num{
  color:#ff6600;
  font-size:14px;
  font-weight:700;
  line-height:1;
}
.thumb-meta{
  margin-top:auto;
  padding-top:4px;
  font:inherit;
  font-size:12px;
  display:flex;
  flex-wrap:wrap;
  gap:8px 10px;
  align-items:center;
  justify-content:center;
}
.ico{display:inline-flex;width:14px;height:14px;vertical-align:-2px}
.meta-pair{display:inline-flex;align-items:center;gap:4px}

/* alpha index */
.alpha{display:flex;flex-wrap:wrap;gap:6px 10px;justify-content:center;line-height:1.15;margin-top:6px}
.alpha a{text-decoration:none}
.alpha b{font-weight:800}

/* media + fallback */
@media (max-width:720px){.view-toggle{justify-content:center}.pagertop,.pagerbottom{gap:4px}}
@supports not (backdrop-filter:blur(2px)){
  .panel.widget,.browse-fieldset,.thumb-card,.glass-btn{background:#fff}
}
</style>



<script language="javascript" type="text/javascript" src="js/ajax.js"></script>
<div id="loading-layer" style="display:none;font-family: Verdana;font-size: 11px;width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000">
     <div style="font-weight:bold" id="loading-layer-text">Загрузка. Пожалуйста, подождите...</div><br />
     <img src="pic/loading.gif" border="0" />
</div>



<?php

$letter = $_GET['letter'] ?? '';


begin_frame("Список раздач");
?>

<style>
  /* компактная сетка и “стеклянные” кнопки */
  .browse-wrap{--pad:10px;--rad:12px;--gap:10px}
  .ai-hero-panel{
    border:1px solid #dde5f0;border-radius:24px;padding:22px 24px 20px;
    background:#f8fbff;
    box-shadow:0 14px 32px rgba(15,23,42,.06);
  }
  .ai-search-shell{
    max-width:1120px;margin:0 auto;display:flex;flex-direction:column;gap:16px;
  }
  .ai-search-bar{
    display:flex;align-items:center;gap:14px;
    min-height:76px;padding:0 22px 0 18px;
    border:1px solid #d6deeb;border-radius:999px;background:#fff;
    box-shadow:0 6px 20px rgba(60,64,67,.12),0 1px 3px rgba(60,64,67,.08);
    transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
  }
  .ai-search-bar:focus-within{
    border-color:#c4d5fb;
    box-shadow:0 10px 24px rgba(26,115,232,.18),0 1px 3px rgba(60,64,67,.1);
    transform:translateY(-1px);
  }
  .ai-search-icon{
    position:relative;flex:0 0 24px;width:24px;height:24px;
  }
  .ai-search-icon::before{
    content:"";position:absolute;left:2px;top:1px;
    width:15px;height:15px;border:2px solid #5f6368;border-radius:50%;
  }
  .ai-search-icon::after{
    content:"";position:absolute;right:1px;bottom:3px;
    width:9px;height:2px;border-radius:2px;background:#5f6368;
    transform:rotate(45deg);
  }
  .ai-query-input{
    flex:1 1 auto;min-width:0;
    border:0 !important;box-shadow:none !important;background:transparent !important;
    color:#202124;font-size:26px;line-height:1.25;padding:8px 0;border-radius:0;
  }
  .ai-query-input:focus{outline:none}
  .ai-query-input::placeholder{color:#9aa0a6}
  .ai-search-submit{
    flex:0 0 auto;
    display:inline-flex;align-items:center;justify-content:center;
    min-height:44px;padding:0 18px;border-radius:999px;
    border:1px solid #d6ddea;background:#f8fbff;color:#1a73e8;
    font-size:14px;font-weight:800;white-space:nowrap;cursor:pointer;
    transition:border-color .15s ease,background .15s ease,color .15s ease;
  }
  .ai-search-submit:hover{
    background:#1a73e8;color:#fff;border-color:#1a73e8;
  }
  .ai-filter-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
    margin-top:4px;
  }
  .ai-filter-card{
    display:flex;flex-direction:column;gap:8px;
    padding:0;border:0;border-radius:0;background:transparent;box-shadow:none;
  }
  .ai-filter-card label{
    font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#667085;
  }
  .ai-filter-card .browse-select{
    width:100%;
    min-height:38px;
    border-radius:10px;
    border:1px solid #d5dce8;
    background:#fff;
  }
  .search-row{display:flex;flex-wrap:wrap;gap:var(--gap);align-items:center;justify-content:center}
  .search-row .search{min-width:260px;max-width:520px;padding:8px 10px;border:1px solid #bbb;border-radius:10px}
  .browse-select{padding:7px 10px;border:1px solid #bbb;border-radius:10px}
  .glass-btn{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.4);
    background:linear-gradient(180deg,rgba(255,255,255,.45),rgba(255,255,255,.15));
    backdrop-filter:blur(6px); text-decoration:none}
  .glass-btn.active{box-shadow:0 0 0 2px rgba(0,0,0,.05) inset;font-weight:700}
  .view-toggle{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin:.5rem 0}
  .alpha{
    margin-top:6px;
    display:flex;
    flex-wrap:wrap;
    gap:6px 10px;              /* меньше «воздуха» */
    justify-content:center;
    line-height:1.15;
  }
  .alpha .grp{
    display:inline-flex;
    gap:6px;                   /* расстояние между буквами/цифрами внутри группы */
    align-items:center;
  }
  .alpha .grp-divider{
    opacity:.5;
    margin:0 6px;              /* разделитель | между группами */
  }
  .alpha a{ text-decoration:none; padding:0 } /* убираем "кнопочность" */
  .alpha b{ font-weight:700 }                 /* активный символ просто жирный */
  .index{padding:6px 8px}
  .browse-suggest{margin:0 auto 10px;max-width:1040px;padding:12px 16px;border:1px solid rgba(35,94,158,.18);border-radius:18px;background:#eef4ff;color:#235e9e;font-weight:700;box-shadow:0 4px 18px rgba(47,111,228,.08)}
  .browse-wrap .pg-wrap.pg-glass{background:#fff !important;backdrop-filter:none !important;-webkit-backdrop-filter:none !important;box-shadow:0 1px 4px rgba(0,0,0,.06)}
  .browse-wrap .pg-wrap.pg-glass .pg-summary{background:#fff !important}
  .browse-wrap .pg-wrap .pg-pill,
  .browse-wrap .pg-wrap .pg-summary,
  .browse-wrap .pg-wrap .pg-ellipsis{color:#4b5563 !important}
  .browse-wrap .pg-wrap .pg-nav{color:#235e9e !important;font-weight:700}
  .browse-wrap .pg-wrap a.pg-nav,
  .browse-wrap .pg-wrap a.pg-pill{color:#4b5563 !important;text-decoration:none}
  .browse-wrap .pg-wrap a.pg-nav{color:#235e9e !important}
  .browse-wrap .pg-wrap .pg-disabled{color:#374151 !important;font-weight:700;opacity:1 !important}
  .browse-wrap .pg-wrap .pg-current{color:#fff !important}
  .thumb-grid{display:grid;grid-template-columns:repeat(auto-fit,220px);justify-content:flex-start;gap:24px;padding:8px 6px}
  .thumb-card{display:flex;flex-direction:column;gap:8px}
  .thumb-img{border-radius:10px;object-fit:cover}
  .thumb-meta{display:flex;flex-wrap:wrap;gap:8px 10px;align-items:center;justify-content:center;font-size:12px;margin-top:auto}
  .thumb-meta .meta-pair{display:inline-flex;gap:3px;align-items:center}
  .ico{width:14px;height:14px;vertical-align:-2px}
  @media (max-width:900px){
    .ai-hero-panel{padding:24px 18px 20px}
    .ai-query-input{font-size:22px}
    .ai-filter-grid{grid-template-columns:1fr}
  }
  @media (max-width:700px){
    .search-row{justify-content:stretch}
    .view-toggle{justify-content:center}
    .ai-search-bar{flex-wrap:wrap;align-items:center;padding:14px 16px;border-radius:28px}
    .ai-query-input{font-size:18px;padding-top:2px}
    .ai-search-submit{width:100%}
    .thumb-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;padding:6px 4px}
    .thumb-card{width:auto}
  }
  
</style>

<script>
  // лёгкий debounce для suggest(); Enter должен отправлять форму
  (function(){
    var t=null;
    window.noenter=function(){ return true; };
    window.suggestDebounced=function(k,v){
      if((k||0)===13) return; // Enter — сабмитит form
      clearTimeout(t); t=setTimeout(function(){ if(window.suggest) suggest(k,v); }, 120);
    };
  })();
</script>

<form method="get" action="browse.php">
  <table class="embedded browse-wrap" align="center" cellspacing="0" cellpadding="5" width="100%">
    <tr>
      <td colspan="12" style="border:0;">
        <?php
          $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          $search_val = $h($searchstr ?? '');
          $cat_sel = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
        ?>
        <div class="ai-hero-panel">
          <div class="ai-search-shell">
            <div class="ai-search-bar">
              <span class="ai-search-icon" aria-hidden="true"></span>
              <input class="search browse-query-input ai-query-input" id="searchinput" name="search" type="text" autocomplete="off"
                     ondblclick="suggestDebounced(event.keyCode, this.value);"
                     onkeyup="suggestDebounced(event.keyCode, this.value);"
                     onkeypress="return noenter(event.keyCode);"
                     placeholder="Поиск по названию, описанию или актёру"
                     value="<?= $search_val ?>" />
              <input type="hidden" name="where" value="ai" />
              <input class="ai-search-submit" type="submit" value="Поиск" />
            </div>
            <div class="ai-filter-grid">
              <div class="ai-filter-card">
                <label for="browse-cat">Раздел</label>
                <select class="browse-select" id="browse-cat" name="cat" aria-label="Категория">
                  <option value="0">Все разделы</option>
                  <?php foreach ($cats as $cat): ?>
                    <?php $sel = ($cat_sel === (int)$cat['id']) ? ' selected="selected"' : ''; ?>
                    <option value="<?= (int)$cat['id'] ?>"<?= $sel ?>><?= $h($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ai-filter-card">
                <label for="browse-format">Формат</label>
                <select class="browse-select" id="browse-format" name="format" aria-label="Формат">
                  <?php foreach ($formatOptions as $key => $label): ?>
                    <option value="<?= $h($key) ?>"<?= $formatFilter === $key ? ' selected="selected"' : '' ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ai-filter-card">
                <label for="browse-year">Год выхода</label>
                <select class="browse-select" id="browse-year" name="year" aria-label="Год выхода">
                  <option value="0">Все года</option>
                  <?php for ($y = (int)date('Y') + 1; $y >= 1950; $y--): ?>
                    <option value="<?= $y ?>"<?= $yearFilter === $y ? ' selected="selected"' : '' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </td>
    </tr>

    <?php
      // Результаты поиска — заголовок
      if (isset($cleansearchstr)) {
          echo "<tr><td class=\"index\" colspan=\"12\">{$tracker_lang['search_results_for']} \"".$h($searchstr)."\"</td></tr>\n";
          if ($searchSuggestion !== '') {
              echo "<tr><td style=\"border:0\" colspan=\"12\"><div class=\"browse-suggest\">Возможно, вы имели в виду &laquo;" . $h($searchSuggestion) . "&raquo;</div></td></tr>\n";
          }
      }

      if ($num_torrents) {

        // безопасный ret: кодируем целиком часть после ? (включая qs)
       $qs_raw = $_SERVER['QUERY_STRING'] ?? '';
$ret = 'browse.php' . ($qs_raw !== '' ? ('?' . $qs_raw) : '');
$ret_enc = rawurlencode($ret);
$browsemode = get_browse_mode();



        echo "<tr><td style=\"border:0\" colspan=\"12\">{$pagertop}</td></tr>";
        echo "<tr><td style=\"border:0\" colspan=\"12\"><div class='view-toggle'>";
        echo   "<a class='glass-btn ".($browsemode==='thumbs'?'active':'')."' href='cookieset.php?browsemode=thumbs&ret={$ret_enc}'>Плитка</a>";
        echo   "<a class='glass-btn ".($browsemode==='list'  ?'active':'')."' href='cookieset.php?browsemode=list&ret={$ret_enc}'>Список</a>";
        echo "</div></td></tr>";
        // ===== переключатель вида =====
$browsemode = get_browse_mode();

        if ($browsemode === 'thumbs') {
          echo "<tr><td style='border:0' colspan='12'><div class='thumb-grid'>";

          // SVG-иконки (как у тебя)
          $icoUpRed = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#e53935" d="M12 3l6 6h-4v9h-4V9H6l6-6z"/></svg>';
          $icoDownGreen = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#2e7d32" d="M12 21l-6-6h4V6h4v9h4l-6 6z"/></svg>';
          $icoDone = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0ea5e9" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';

          foreach ((array)$res as $row) {
            $poster = '';
            if (!empty($row['image1']))      $poster = $h($row['image1']);
            elseif (!empty($row['poster']))  $poster = $h($row['poster']);
            else                              $poster = "pic/noposter.png";

            $ratingValue = isset($row['rating']) ? (float)$row['rating'] : null;
            if ($ratingValue !== null && $ratingValue > 0) {
              $ratingWidth = max(0, min(125, $ratingValue * 25));
              $ratingHtml = "<div class='thumb-rating'>"
                . "<div class='rating'><ul class='star-rating'><li class='current-rating' style='width:{$ratingWidth}px;'></li></ul></div>"
                . "<span class='thumb-rating-num'>" . number_format($ratingValue, 1) . "</span>"
                . "</div>";
            } else {
              $ratingHtml = $tracker_lang['no_votes'] ?? 'Нет голосов';
            }

            $freeHtml = (!empty($row['free']) && $row['free'] === 'yes') ? "<img src='pic/free.gif' alt='FREE' />" : '';

            $name = $h($row['name'] ?? '');
            $cat  = $h($row['cat_name'] ?? '');
            $usr  = isset($row['username']) && $row['username'] !== '' ? "<b>".$h($row['username'])."</b>" : "<i>(unknown)</i>";

            $added   = $h($row['added'] ?? '');
            $seeders = (int)($row['seeders'] ?? 0);
            $leech   = (int)($row['leechers'] ?? 0);
            $done    = (int)($row['times_completed'] ?? 0);
            $externalSeeders = (int)($row['external_seeders'] ?? 0);
            $externalLeechers = (int)($row['external_leechers'] ?? 0);
            $externalDone = (int)($row['external_completed'] ?? 0);
            $totalSeeders = $seeders + $externalSeeders;
            $totalLeechers = $leech + $externalLeechers;
            $totalDone = $done + $externalDone;

            echo "<div class='thumb-card'>"
              . "  <a class='thumb-link' href='details.php?id=".(int)$row['id']."&amp;hit=1' title='{$name}'>"
              . "    <img class='thumb-img' src='{$poster}' alt='{$name}' loading='lazy' decoding='async' />"
              . "  </a>"
              . "  <a class='thumb-link thumb-title' href='details.php?id=".(int)$row['id']."&amp;hit=1'>{$name}</a>"
              . "  <div class='thumb-sub'>{$cat}</div>"
              . "  <div class='thumb-sub'>Загрузил: {$usr}</div>"
              . "  <div class='thumb-sub'>Добавлен: {$added}</div>"
              . "  <div class='thumb-sub'>Пиры: локально {$seeders}/{$leech}, внешне {$externalSeeders}/{$externalLeechers}</div>"
              . "  <div class='thumb-sub'>Оценка: {$ratingHtml}</div>"
              . "  <div class='thumb-meta'>"
              . "    <span class='meta-pair' title='Качают'>{$icoDownGreen}{$totalLeechers}</span>"
              . "    <span class='meta-pair' title='Раздают'>{$icoUpRed}{$totalSeeders}</span>"
              . "    <span class='meta-pair' title='Скачан'>{$icoDone}{$totalDone}</span>"
              .      ($freeHtml !== '' ? "<span class='meta-pair'>{$freeHtml}</span>" : '')
              . "  </div>"
              . "</div>";
          }
          echo "</div></td></tr>";

        } else {
          // — НЕ ТРОГАЕМ — классический список
          torrenttable($res, "index");
        }

        echo "<tr><td style=\"border:0;\" colspan=\"12\">{$pagerbottom}</td></tr>";

      } else {
        echo "<tr><td style=\"border:0;\" colspan=\"12\">{$tracker_lang['nothing_found']}</td></tr>\n";
      }
    ?>
  </table>
</form>


<script src="js/suggest.js" type="text/javascript"></script>
<div id="suggcontainer" style="text-align:left;width:520px;display:none;">
    <div id="suggestions" style="cursor:default;position:absolute;background-color:#FFFFFF;border:1px solid #777777;"></div>
</div>

<?php

end_frame();

//////////////////////////////////////////////////////////////////////////

if (isset($cleansearchstr) || isset($cleantagstr)) {
    stdfoot();
    exit;
}


// Кеш-ключ и TTL
$key = 'help_torrents:v3';
$ttl = 300;

// 1) Кеш
global $memcached;
$res = $memcached->get($key);

if ($res === false) {
    $totalSeedersExpr = "(torrents.seeders + COALESCE(mts.external_seeders, 0))";
    $totalLeechersExpr = "(torrents.leechers + COALESCE(mts.external_leechers, 0))";
    $sql = "
        SELECT
            torrents.id,
            torrents.name,
            torrents.seeders,
            torrents.leechers,
            COALESCE(mts.external_seeders, 0) AS external_seeders,
            COALESCE(mts.external_leechers, 0) AS external_leechers
        FROM torrents
        " . multitracker_stats_summary_sql('torrents') . "
        WHERE visible = 'yes'
          AND banned  = 'no'
          AND (
                ({$totalSeedersExpr} = 0 AND {$totalLeechersExpr} = 0)                           -- показываем «мертвые» (0/0)
             OR ({$totalSeedersExpr} = 0 AND {$totalLeechersExpr} > 0)                           -- есть качающие, но нет сидов
             OR ({$totalSeedersExpr} > 0 AND ({$totalLeechersExpr} / NULLIF({$totalSeedersExpr},0)) >= 4) -- перекос спрос/предложение
          )
        ORDER BY
            -- сначала без сидов, затем по наибольшему перекосу, затем по числу качающих
            ({$totalSeedersExpr} = 0) DESC,
            ({$totalLeechersExpr} / NULLIF({$totalSeedersExpr},1)) DESC,
            {$totalLeechersExpr} DESC
        LIMIT 20
    ";
    $q = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $res = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $res[] = $row;
    }
    $memcached->set($key, $res, $ttl);
}

// 2) Вывод
begin_frame("Раздачи, нуждающиеся в сидерах");

// мини-стили прямо тут (можешь вынести в CSS)
?>
<style>
.help-list {width:100%; border-collapse:collapse; font-size:14px}
.help-list th, .help-list td {padding:10px; border-bottom:1px solid #e7e7e7; vertical-align:middle}
.help-list th {text-align:left; font-weight:600; background:#fafafa}
.help-name a {text-decoration:none}
.badge {display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; line-height:18px; border:1px solid #ddd}
.badge-zero {background:#fff4f4; border-color:#f0c4c4}
.badge-need {background:#fff9e6; border-color:#f2dea6}
.badge-ok   {background:#eef9ff; border-color:#cfe7f6}
.ratio-dot {display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; vertical-align:middle}
.dot-red   {background:#e33}
.dot-amber {background:#e6a700}
.dot-blue  {background:#3aa0e6}
.help-meta {color:#666; font-size:12px}
</style>
<?php

echo '<table class="help-list">';
echo '<tr><th>Раздача</th><th>Статус</th><th>Пиры</th></tr>';

if (empty($res)) {
    echo '<tr><td colspan="3">Сейчас нет раздач, которым особенно требуется помощь в сидировании.</td></tr>';
} else {
    foreach ($res as $arr) {
        $nameFull = (string)$arr['name'];
        $nameShort = (mb_strlen($nameFull, 'UTF-8') > 55)
            ? (mb_substr($nameFull, 0, 55, 'UTF-8') . '…')
            : $nameFull;

        $seedLocal = (int)($arr['seeders'] ?? 0);
        $leechLocal = (int)($arr['leechers'] ?? 0);
        $seedExternal = (int)($arr['external_seeders'] ?? 0);
        $leechExternal = (int)($arr['external_leechers'] ?? 0);
        $seed = $seedLocal + $seedExternal;
        $leech = $leechLocal + $leechExternal;

        // определим "серьёзность" для бейджа/точки
        if ($seed === 0 && $leech === 0) {
            $badge = '<span class="badge badge-zero"><span class="ratio-dot dot-red"></span>Нет сидов / нет пиров</span>';
        } elseif ($seed === 0 && $leech > 0) {
            $badge = '<span class="badge badge-zero"><span class="ratio-dot dot-red"></span>Нет сидов</span>';
        } elseif ($seed > 0 && $leech >= 4 * $seed) {
            $badge = '<span class="badge badge-need"><span class="ratio-dot dot-amber"></span>Нужны сиды</span>';
        } else {
            $badge = '<span class="badge badge-ok"><span class="ratio-dot dot-blue"></span>Стабильно</span>';
        }

        $nameEsc = htmlspecialchars($nameFull, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $nameShortEsc = htmlspecialchars($nameShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link = 'details.php?id='.(int)$arr['id'].'&hit=1';

        echo '<tr>';
        echo '<td class="help-name"><a href="'.$link.'" title="'.$nameEsc.'">'.$nameShortEsc.'</a>'
           . '<div class="help-meta" title="'.$nameEsc.'">'.$nameEsc.'</div></td>';
        echo '<td>'.$badge.'</td>';
        echo '<td><b>Раздают:</b> '.number_format($seed, 0, ',', ' ')
           . ' <span class="help-meta">(лок. '.number_format($seedLocal, 0, ',', ' ')
           . ' / внеш. '.number_format($seedExternal, 0, ',', ' ').')</span>'
           . ' &nbsp; <b>Качают:</b> '.number_format($leech, 0, ',', ' ')
           . ' <span class="help-meta">(лок. '.number_format($leechLocal, 0, ',', ' ')
           . ' / внеш. '.number_format($leechExternal, 0, ',', ' ').')</span></td>';
        echo '</tr>';
    }
}
echo '</table>';

end_frame();




stdfoot();  

?>
