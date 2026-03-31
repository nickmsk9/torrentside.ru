<?php

function get_tags(): array {
    global $mysqli;

    $cacheKey = tracker_cache_key('cloud', 'tags', 'v1');
    $tags = tracker_cache_get($cacheKey, $cacheHit);

    if (!$cacheHit || !is_array($tags)) {
        $result = mysqli_query($mysqli, "SELECT name, howmuch FROM tags WHERE howmuch > 0 ORDER BY id DESC") or die(mysqli_error($mysqli));
        $tags = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $tags[] = $row;
        }

        tracker_cache_set($cacheKey, $tags, 300);
    }

    // Формируем ассоциативный массив
    $arr = [];
    foreach ($tags as $row) {
        $arr[$row['name']] = $row['howmuch'];
    }

    ksort($arr);
    return $arr;
}

function reorder_cloud_tags(array $tags): array {
    if (count($tags) < 3) {
        return $tags;
    }

    arsort($tags, SORT_NUMERIC);
    $items = [];
    foreach ($tags as $name => $count) {
        $items[] = ['name' => $name, 'count' => $count];
    }

    $mixed = [];
    $left = 0;
    $right = count($items) - 1;

    while ($left <= $right) {
        $mixed[] = $items[$left];
        $left++;
        if ($left <= $right) {
            $mixed[] = $items[$right];
            $right--;
        }
    }

    return $mixed;
}

function cloud(int $small, int $big, bool $colour = false): string {
    $tags = get_tags();

    if (empty($tags)) {
        return "Нет тэгов";
    }

    $minimum_count = min($tags);
    $maximum_count = max($tags);
    $spread = max(1, $maximum_count - $minimum_count);
    $orderedTags = reorder_cloud_tags($tags);
    $palette = ['#2f6fff', '#cc2fff', '#1eb255', '#ff6a00', '#00b7df', '#ff3fa0', '#c7d2df'];
    $sizeBuckets = [38, 32, 27, 22, 18, 14, 11];

    $cloud = [];
    $total = count($orderedTags);
    foreach ($orderedTags as $index => $item) {
        $tag = (string)$item['name'];
        $count = (int)$item['count'];
        $weight = ($count - $minimum_count) / $spread;
        $rankRatio = $total > 1 ? ($index / ($total - 1)) : 0.0;
        $bucketIndex = (int)floor($rankRatio * (count($sizeBuckets) - 1));
        $bucketIndex = max(0, min(count($sizeBuckets) - 1, $bucketIndex));
        $fontSize = $sizeBuckets[$bucketIndex];
        if ($weight > 0.92) {
            $fontSize = max($fontSize, 40);
        } elseif ($weight > 0.75) {
            $fontSize = max($fontSize, 34);
        }
        $fontWeight = max(500, min(900, (int)round(540 + ((1 - $bucketIndex / max(1, count($sizeBuckets) - 1)) * 320))));
        $opacity = 0.88 + ((1 - ($bucketIndex / max(1, count($sizeBuckets) - 1))) * 0.12);
        $color = $palette[$index % count($palette)];
        $style = 'display:inline-block !important;'
               . 'font-size:' . $fontSize . 'px !important;'
               . 'font-weight:' . $fontWeight . ' !important;'
               . 'font-family:Trebuchet MS,Verdana,Arial,sans-serif !important;'
               . 'line-height:' . max(1, round($fontSize * 0.84)) . 'px !important;'
               . 'color:' . $color . ' !important;'
               . 'opacity:' . number_format($opacity, 2, '.', '') . ' !important;'
               . 'letter-spacing:-0.02em !important;'
               . 'white-space:nowrap !important;'
               . 'text-decoration:none !important;';

        $cloud[] = '<a class="tag-cloud__tag" href="browse.php?tag=' . urlencode($tag) . '&cat=0&incldead=1" style="' . $style .
                   '" rel="tag" data-count="' . (int)$count . '" title="Содержится в ' . $count . ' торрентах">' .
                   htmlentities($tag, ENT_QUOTES, "UTF-8") . '</a>';
    }

    return implode("\n", $cloud);
}

// Заменяет flash_cloud — обычный PHP-вид
function php_cloud(int $small = 11, int $big = 40): string {
    return cloud($small, $big, true);
}

// HTML с обёрткой и стилями
function simple_cloud(int $small, int $big): string {
    $data = '<style>
        #tag_cloud a {padding: 3px; text-decoration: none; font-family: Verdana; font-weight: normal;}
        #tag_cloud a:hover {background: #ddd; border: 1px solid #bbb;}
        #tag_cloud a:active {background: #fff; border: 1px solid transparent;}
        #tag_cloud p {line-height: 28px; text-align: justify;}
    </style>';

    $data .= '<div id="tag_cloud">';
    $data .= '<p>' . php_cloud($small, $big) . '</p>';
    $data .= '</div>';

    return $data;
}

?>
