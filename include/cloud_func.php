<?php

function get_tags(): array {
    global $mysqli, $memcached;

    // Инициализация Memcached при необходимости
    if (!isset($memcached) || !($memcached instanceof Memcached)) {
        $memcached = new Memcached();
        $memcached->addServer("127.0.0.1", 11211);
    }

    // Пытаемся получить теги из кеша
    $tags = $memcached->get("tags");

    if ($memcached->getResultCode() !== Memcached::RES_SUCCESS) {
        $result = mysqli_query($mysqli, "SELECT name, howmuch FROM tags WHERE howmuch > 0 ORDER BY id DESC") or die(mysqli_error($mysqli));
        $tags = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $tags[] = $row;
        }

        $memcached->set("tags", $tags, 300);
    }

    // Формируем ассоциативный массив
    $arr = [];
    foreach ($tags as $row) {
        $arr[$row['name']] = $row['howmuch'];
    }

    ksort($arr);
    return $arr;
}

function cloud(int $small, int $big, bool $colour = false): string {
    $tags = get_tags();

    if (empty($tags)) {
        return "Нет тэгов";
    }

    $minimum_count = min($tags);
    $maximum_count = max($tags);
    $spread = max(1, $maximum_count - $minimum_count);

    $cloud = [];
    $colours = ['#003EFF', '#0000FF', '#7EB6FF', '#0099CC', '#62B1F6'];

    foreach ($tags as $tag => $count) {
        $size = $small + ($count - $minimum_count) * ($big - $small) / $spread;
        $style = "font-size:" . floor($size) . "px;";
        if ($colour) {
            $style .= "color:" . $colours[mt_rand(0, count($colours) - 1)] . "; ";
        }

        $cloud[] = '<a href="browse.php?tag=' . urlencode($tag) . '&cat=0&incldead=1" style="' . $style .
                   '" rel="tag" title="Содержится в ' . $count . ' торрентах">' .
                   htmlentities($tag, ENT_QUOTES, "UTF-8") . '</a>';
    }

    return implode("\n", $cloud);
}

// Заменяет flash_cloud — обычный PHP-вид
function php_cloud(int $small = 12, int $big = 28): string {
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
