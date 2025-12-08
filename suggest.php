<?php

require("include/bittorrent.php");
dbconn(false);
loggedinorreturn();

// Установка заголовка с кодировкой
header("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

// Проверка длины поискового запроса
$q = $_GET['q'] ?? '';
if (mb_strlen($q) > 3) {
    // Подготовка строк для поиска: пробел → точка и наоборот
    $q_like1 = "%" . str_replace(" ", ".", $q) . "%";
    $q_like2 = "%" . str_replace(".", " ", $q) . "%";

    // Экранирование для LIKE
    $q_esc1 = sqlesc($q_like1);
    $q_esc2 = sqlesc($q_like2);

    // Запрос к базе данных
    $res = sql_query("SELECT id, name FROM torrents WHERE name LIKE $q_esc1 OR name LIKE $q_esc2 ORDER BY id DESC LIMIT 10");

    // Если есть результаты — выводим ссылки
    if (mysqli_num_rows($res) > 0) {
        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $id = (int)$row['id'];
            $name = trim(str_replace("\t", "", $row['name']));
            $out[] = "<a href=\"details.php?id=$id&amp;hit=1\">" . htmlspecialchars($name) . "</a>";
        }
        echo implode("\r\n", $out);
    }
}
