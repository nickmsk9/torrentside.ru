<?php

require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

global $memcached;

// === Функция полной перезагрузки CURUSER и обновления кэша ===
function reload_user(int $userId): void {
    global $CURUSER, $memcached;

    $res = sql_query("SELECT * FROM users WHERE id = $userId LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        if (isset($row['override_class']) && $row['override_class'] < 255) {
            $row['real_class'] = $row['class'];
            $row['class'] = $row['override_class'];
        }

        $CURUSER = $row;
        $_SESSION['curuser'] = $row;

        if ($memcached instanceof Memcached) {
            $memcached->set("curuser_{$userId}", $row, 300);
            $memcached->set("user_session_{$userId}", $row, 300);

            $pass = $_COOKIE['pass'] ?? '';
            if (!empty($pass)) {
                $memcached->set("user_session_{$userId}_{$pass}", $row, 60);
            }
        }
    }
}

$userId = (int)$CURUSER['id'];

// Сброс override_class
sql_query("UPDATE users SET override_class = 255 WHERE id = $userId") or sqlerr(__FILE__, __LINE__);

// Очистка кэша
if ($memcached instanceof Memcached) {
    $pass = $_COOKIE['pass'] ?? '';
    $memcached->delete("curuser_$userId");
    $memcached->delete("user_session_$userId");
    if (!empty($pass)) {
        $memcached->delete("user_session_{$userId}_{$pass}");
    }
}

// Перезагрузка CURUSER и обновление сессии + кэша
reload_user($userId);

// Редирект
header("Location: $DEFAULTBASEURL/index.php");
exit;
