<?php
require_once("include/bittorrent.php");

dbconn();
loggedinorreturn();

if ($CURUSER['class'] < UC_SYSOP) {
    stderr("Ошибка доступа", "У вас нет прав для просмотра этой страницы.");
}

// Удаление записи
if (isset($_GET['del'])) {
    $del = (int)$_GET['del'];
    mysqli_query($GLOBALS['mysqli'], "DELETE FROM hackers WHERE id = $del");
    header("Location: /hackers.php");
    exit;
}

// Очистка таблицы
if (isset($_GET['truncate2'])) {
    mysqli_query($GLOBALS['mysqli'], "TRUNCATE TABLE hackers");
    header("Location: /hackers.php");
    exit;
}

stdhead("Хакеры");
begin_frame("Хакеры");

// Подсчёт записей
$res = mysqli_query($GLOBALS['mysqli'], "SELECT COUNT(*) FROM hackers");
$row = mysqli_fetch_row($res);
$count = $row[0];

if ($count == 0) {
    echo "Записей нет!";
    end_frame();
    stdfoot();
    exit;
}

list($pagertop, $pagerbottom, $limit) = pager(50, $count, "/hackers.php?");
echo $pagertop;

// Таблица
begin_table();
print("<tr>
    <td class=colhead>ID</td>
    <td class=colhead>IP</td>
    <td class=colhead>Система</td>
    <td class=colhead>Откуда</td>
    <td class=colhead>GET</td>
    <td class=colhead>POST</td>
    <td class=colhead>Событие</td>
</tr>");

$res = mysqli_query($GLOBALS['mysqli'], "SELECT * FROM hackers ORDER BY id DESC $limit");

$allips = [];

while ($row = mysqli_fetch_assoc($res)) {
    $ip = $row['ip'];
    $addusers = [];

    // Поиск пользователей с этим IP
    $ip_esc = mysqli_real_escape_string($GLOBALS['mysqli'], $ip);
    $a = mysqli_query($GLOBALS['mysqli'],
        "SELECT id FROM users WHERE ip = '$ip_esc'
         UNION
         SELECT userid AS id FROM peers WHERE ip = '$ip_esc'");

    if (mysqli_num_rows($a) > 0) {
        $ip_display = "<a href=\"/usersearch.php?ip=$ip\" style=\"color:red;\">$ip</a>";
        while ($u = mysqli_fetch_assoc($a)) {
            $uid = (int)$u['id'];
            $uinfo = mysqli_fetch_assoc(mysqli_query($GLOBALS['mysqli'], "SELECT username, class FROM users WHERE id = $uid"));
            $addusers[] = "<a href=\"userdetails.php?id=$uid\">" . get_user_class_color($uinfo['class'], $uinfo['username']) . "</a>";
        }
    } else {
        $ip_display = "<a target=\"_blank\" href=\"https://who.is/whois-ip/ip-address/$ip\" style=\"color:green;\">$ip</a>";
    }

    // Распаковка события
    $events = explode("||", $row['event']);
    $get = nl2br(htmlspecialchars(print_r(@unserialize($events[0]), true)));
    $post = nl2br(htmlspecialchars(print_r(@unserialize($events[1]), true)));
    $event = nl2br(htmlspecialchars($events[2] ?? ''));
    $event .= isset($events[3]) ? "<hr>" . htmlspecialchars($events[3]) : "";
    $ref = htmlspecialchars($events[3] ?? '');

    // Команда iptables (для админов)
    if (!in_array($ip, $allips)) {
        $allips[] = $ip;
        $ip_parts = explode('.', $ip);
        if (count($ip_parts) == 4) {
            $subnet = "{$ip_parts[0]}.{$ip_parts[1]}.{$ip_parts[2]}.0";
            // Можно сохранить в лог для безопасности
            // $iptables .= "iptables -A INPUT -s {$subnet}/24 -p tcp --dport 80 -j DROP\n";
        }
    }

    echo "<tr>
        <td class=lol><center>{$row['id']}<br><a href=\"/hackers.php?del={$row['id']}\"><img src=\"/pic/warned2.gif\"></a></td>
        <td class=lol>{$ip_display}<br>" . implode(", ", $addusers) . "</td>
        <td class=lol>" . htmlspecialchars($row['system']) . "</td>
        <td class=lol>$ref</td>
        <td class=lol>$get</td>
        <td class=lol>$post</td>
        <td class=lol>$event<hr>" . elapsedtime($row['added']) . " назад</td>
    </tr>";
}

end_table();
echo $pagerbottom;

end_frame();
stdfoot();


// Подсчёт времени с момента события
function elapsedtime($date, $showseconds = true, $unix = false) {
    if ($date == "0000-00-00 00:00:00") return "---";
    $U = $unix ? $date : strtotime($date);
    $N = time();
    $diff = $N - $U;

    $units = [
        'year' => 31536000,
        'month' => 2629800,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];

    $parts = [];
    foreach ($units as $unit => $seconds) {
        if ($diff >= $seconds) {
            $count = floor($diff / $seconds);
            $diff -= $count * $seconds;
            $parts[] = "$count " . rusdate($count, $unit);
            if ($unit === 'minute' && !$showseconds) break;
        }
    }

    return implode(" ", $parts);
}

// Склонения для русских слов
function rusdate($num, $type) {
    $rus = [
        "year"   => ["лет", "год", "года"],
        "month"  => ["месяцев", "месяц", "месяца"],
        "week"   => ["недель", "неделя", "недели"],
        "day"    => ["дней", "день", "дня"],
        "hour"   => ["часов", "час", "часа"],
        "minute" => ["минут", "минута", "минуты"],
        "second" => ["секунд", "секунда", "секунды"],
    ];

    $num = abs($num);
    $form = ($num % 10 == 1 && $num % 100 != 11) ? 1 : (($num % 10 >= 2 && $num % 10 <= 4 && ($num % 100 < 10 || $num % 100 >= 20)) ? 2 : 0);
    return $rus[$type][$form];
}
?>
