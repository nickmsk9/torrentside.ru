<?php
require_once("include/bittorrent.php");
require_once("include/multitracker.php");
gzip();

dbconn(false);
multitracker_ensure_schema();

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
    ];
    $stop = array_fill_keys($stopwords, true);
    $terms = [];

    foreach ($parts as $part) {
        if (mb_strlen($part, 'UTF-8') <= 1) {
            continue;
        }
        if (isset($stop[$part])) {
            continue;
        }
        $terms[$part] = $part;
    }

    return array_values($terms);
}

function browse_search_candidates(string $search): array {
    $search = trim($search);
    if ($search === '') {
        return [];
    }

    $candidates = [];
    $variants = [$search];

    $fixedLayout = browse_fix_keyboard_layout($search);
    if ($fixedLayout !== '' && mb_strtolower($fixedLayout, 'UTF-8') !== mb_strtolower($search, 'UTF-8')) {
        $variants[] = $fixedLayout;
    }

    foreach ($variants as $variant) {
        $variant = trim($variant);
        if ($variant === '') {
            continue;
        }

        $variantLower = mb_strtolower($variant, 'UTF-8');
        $candidates[$variantLower] = $variantLower;

        $terms = browse_search_terms($variant);
        if ($terms) {
            $joined = implode(' ', $terms);
            $candidates[mb_strtolower($joined, 'UTF-8')] = mb_strtolower($joined, 'UTF-8');
            foreach ($terms as $term) {
                $termLower = mb_strtolower($term, 'UTF-8');
                $candidates[$termLower] = $termLower;
            }
        }
    }

    return array_values(array_filter($candidates));
}

function browse_fix_keyboard_layout(string $search): string {
    $search = trim($search);
    if ($search === '') {
        return '';
    }

    $en = "qwertyuiop[]asdfghjkl;'zxcvbnm,.`QWERTYUIOP{}ASDFGHJKL:\"ZXCVBNM<>~";
    $ru = "йцукенгшщзхъфывапролджэячсмитьбюёйЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮЁ";
    $fixed = strtr($search, $en, $ru);

    return trim($fixed);
}

function browse_search_fields(string $scope): array {
    return match ($scope) {
        'descr' => ['torrents.descr', 'torrents.ori_descr'],
        'tags' => ['torrents.tags'],
        'all' => ['torrents.name', 'torrents.search_text', 'torrents.descr', 'torrents.ori_descr', 'torrents.tags'],
        default => ['torrents.name', 'torrents.search_text'],
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
    $fields = browse_search_fields($scope);
    foreach (browse_search_candidates($search) as $candidate) {
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
$searchIn = trim((string)($_GET['where'] ?? 'title'));
if (!in_array($searchIn, ['title', 'descr', 'tags', 'all'], true)) {
    $searchIn = 'title';
}
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

if (isset($cleansearchstr)) {
    $searchCondition = browse_build_search_condition($searchstr, $searchIn);
    if ($searchCondition !== null) {
        $wherea[] = $searchCondition;
    }
    $addparam .= "search=" . urlencode($searchstr) . "&amp;";
    if ($searchIn !== 'title') {
        $addparam .= "where=" . urlencode($searchIn) . "&amp;";
    }
}

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

$where = implode(" AND ", $wherea);
if (!empty($wherecatin)) {
    $where .= ($where ? " AND " : "") . "category IN (" . $wherecatin . ")";
}
if (!empty($where)) {
    $where = "WHERE $where";
}

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
        $where = implode(" AND ", $wherea);
        if (!empty($where)) $where = "WHERE $where";
        $count = browse_cached_count($where);
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

            $where = implode(" AND ", $wherea);
            if (!empty($wherecatin)) {
                $where .= ($where ? " AND " : "") . "category IN (" . $wherecatin . ")";
            }
            if (!empty($where)) {
                $where = "WHERE $where";
            }

            $count = browse_cached_count($where);
            $num_torrents = $count;

            if ($count > 0) {
                $searchSuggestion = $fixedSearch;
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

            $where = implode(" AND ", $wherea);
            if (!empty($wherecatin)) {
                $where .= ($where ? " AND " : "") . "category IN (" . $wherecatin . ")";
            }
            if (!empty($where)) {
                $where = "WHERE $where";
            }

            $count = browse_cached_count($where);
            $num_torrents = $count;

            if ($count > 0) {
                $searchSuggestion = $fuzzySearch;
            }
        }
    }
}

$torrentsperpage = $CURUSER["torrentsperpage"] ?? 20;

if ($count) {
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
        users.username, users.class
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
  .browse-fieldset{border:1px solid #999;border-radius:var(--rad);padding:var(--pad)}
  .browse-search-table{table-layout:auto}
  .browse-search-table td{vertical-align:middle}
  .browse-search-top{display:flex;align-items:center;gap:8px;flex-wrap:nowrap}
  .browse-search-top .browse-query-input{flex:1 1 auto;min-width:0}
  .browse-search-top .browse-where-select{flex:0 0 190px}
  .browse-search-top .browse-sort-select{flex:0 0 170px}
  .browse-search-top .browse-type-select{flex:0 0 130px}
  .browse-search-top .search-submit{flex:0 0 140px}
  .browse-where-select{display:block;min-width:190px;width:100%;margin-top:0 !important;margin-bottom:0 !important;padding:7px 28px 7px 10px;box-sizing:border-box}
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
  .browse-suggest{margin:0 0 10px;padding:10px 12px;border:1px solid rgba(35,94,158,.18);border-radius:10px;background:rgba(255,255,255,.82);color:#235e9e;font-weight:700}
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
  .search-submit{background:#2f6fe4 !important;color:#fff !important;border-color:#2f6fe4 !important;font-weight:700}
  .search-submit:hover{background:#245ec4 !important;color:#fff !important}
  .thumb-grid{display:grid;grid-template-columns:repeat(auto-fit,220px);justify-content:flex-start;gap:24px;padding:8px 6px}
  .thumb-card{display:flex;flex-direction:column;gap:8px}
  .thumb-img{border-radius:10px;object-fit:cover}
  .thumb-meta{display:flex;flex-wrap:wrap;gap:8px 10px;align-items:center;justify-content:center;font-size:12px;margin-top:auto}
  .thumb-meta .meta-pair{display:inline-flex;gap:3px;align-items:center}
  .ico{width:14px;height:14px;vertical-align:-2px}
  @media (max-width:700px){
    .search-row{justify-content:stretch}
    .view-toggle{justify-content:center}
    .browse-search-top{flex-wrap:wrap}
    .browse-search-top .browse-query-input,
    .browse-search-top .browse-where-select,
    .browse-search-top .search-submit,
    .browse-search-top .browse-sort-select,
    .browse-search-top .browse-type-select{flex:1 1 100%}
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
        <fieldset class="browse-fieldset">
          <legend><b>Поиск раздач</b></legend>
          <?php
            $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $search_val = $h($searchstr ?? '');
            $cat_sel = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
            $sort_sel = (int)($_GET['sort'] ?? 4);
            if (!in_array($sort_sel, [1, 4, 5, 6, 7, 8], true)) {
                $sort_sel = 4;
            }
            $dir_sel = ($_GET['type'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $whereOptions = [
                'title' => 'В названии',
                'descr' => 'В описании',
                'tags' => 'В тегах',
                'all' => 'Везде',
            ];
            $sortOptions = [
                4 => 'По дате',
                1 => 'По названию',
                5 => 'По размеру',
                6 => 'По скачиваниям',
                7 => 'По сидам',
                8 => 'По личам',
            ];
          ?>
          <table class="embedded browse-search-table" width="100%" cellspacing="0" cellpadding="4">
            <tr>
              <td colspan="5">
                <div class="browse-search-top">
                  <input class="search browse-query-input" id="searchinput" name="search" type="text" autocomplete="off"
                         ondblclick="suggestDebounced(event.keyCode, this.value);"
                         onkeyup="suggestDebounced(event.keyCode, this.value);"
                         onkeypress="return noenter(event.keyCode);"
                         value="<?= $search_val ?>" />
                  <select class="browse-select browse-where-select" name="where" aria-label="Где искать">
                    <?php foreach ($whereOptions as $key => $label): ?>
                      <option value="<?= $h($key) ?>"<?= $searchIn === $key ? ' selected="selected"' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input class="glass-btn search-submit" type="submit" value="Поиск"/>
                  <select class="browse-select browse-sort-select" name="sort" aria-label="Сортировка">
                    <?php foreach ($sortOptions as $key => $label): ?>
                      <option value="<?= (int)$key ?>"<?= $sort_sel === (int)$key ? ' selected="selected"' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select class="browse-select browse-type-select" name="type" aria-label="Направление сортировки">
                    <option value="desc"<?= $dir_sel === 'desc' ? ' selected="selected"' : '' ?>>убыв.</option>
                    <option value="asc"<?= $dir_sel === 'asc' ? ' selected="selected"' : '' ?>>возр.</option>
                  </select>
                </div>
              </td>
            </tr>
            <tr>
              <td>
                <div style="font-weight:bold; margin-bottom:4px;">Выбор раздела</div>
                <select class="browse-select" name="cat" style="width:100%" aria-label="Категория">
                  <option value="0">Поиск по разделам</option>
                  <?php foreach ($cats as $cat): ?>
                    <?php $sel = ($cat_sel === (int)$cat['id']) ? ' selected="selected"' : ''; ?>
                    <option value="<?= (int)$cat['id'] ?>"<?= $sel ?>><?= $h($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td colspan="2">
                <div style="font-weight:bold; margin-bottom:4px;">Выбор формата</div>
                <select class="browse-select" name="format" style="width:100%" aria-label="Формат">
                  <?php foreach ($formatOptions as $key => $label): ?>
                    <option value="<?= $h($key) ?>"<?= $formatFilter === $key ? ' selected="selected"' : '' ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td colspan="2">
                <div style="font-weight:bold; margin-bottom:4px;">Год выхода</div>
                <select class="browse-select" name="year" style="width:100%" aria-label="Год выхода">
                  <option value="0">Все года</option>
                  <?php for ($y = (int)date('Y') + 1; $y >= 1950; $y--): ?>
                    <option value="<?= $y ?>"<?= $yearFilter === $y ? ' selected="selected"' : '' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </td>
            </tr>
          </table>
        </fieldset>
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


// Кеш-ключ и TTL
$key = 'help_torrents:v2';
$ttl = 300;

// 1) Кеш
global $memcached;
$res = $memcached->get($key);

if ($res === false) {
    $sql = "
        SELECT id, name, seeders, leechers
        FROM torrents
        WHERE visible = 'yes'
          AND banned  = 'no'
          AND (
                (seeders = 0 AND leechers = 0)                       -- показываем «мертвые» (0/0)
             OR (seeders = 0 AND leechers > 0)                       -- есть качающие, но нет сидов
             OR (seeders > 0 AND (leechers / NULLIF(seeders,0)) >= 4) -- перекос спрос/предложение
          )
        ORDER BY
            -- сначала без сидов, затем по наибольшему перекосу, затем по числу качающих
            (seeders = 0) DESC,
            (leechers / NULLIF(seeders,1)) DESC,
            leechers DESC
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

        $seed = (int)$arr['seeders'];
        $leech = (int)$arr['leechers'];

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
        $link = 'details.php?id='.(int)$arr['id'].'&hit=1';

        echo '<tr>';
        echo '<td class="help-name"><a href="'.$link.'" title="'.$nameEsc.'">'.htmlspecialchars($nameShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>'
           . '<div class="help-meta" title="'.$nameEsc.'">'.htmlspecialchars($nameEsc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></td>';
        echo '<td>'.$badge.'</td>';
        echo '<td><b>Раздают:</b> '.number_format($seed, 0, ',', ' ')
           . ' &nbsp; <b>Качают:</b> '.number_format($leech, 0, ',', ' ').'</td>';
        echo '</tr>';
    }
}
echo '</table>';

end_frame();




stdfoot();  

?>
