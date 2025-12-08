<?php

require_once("include/bittorrent.php");

dbconn();
loggedinorreturn();


// Проверка и приведение ID торрента
$torrent = $_POST["torrent"] ?? '';
if (!ctype_digit($torrent)) {
    die("Операция невозможна");
}
$torrent = (int) $torrent;

// Проверка прав доступа
if (get_user_class() < UC_MODERATOR) {
    die("Нет доступа");
}

// Получение информации о торренте
$res = sql_query("SELECT modded, owner FROM torrents WHERE id = " . sqlesc($torrent)) or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_assoc($res);

if (!$row) {
    die("Раздача не найдена");
}

// Запрещено проверять свою раздачу
if ((int)$row["owner"] === (int)$CURUSER["id"]) {
    die("Это ваша раздача");
}

// Уже проверено
if ($row["modded"] === "yes") {
    die("Раздача уже проверена");
}

// Отмечаем раздачу как проверенную
sql_query("
    UPDATE torrents SET 
        modded = 'yes', 
        modby = " . sqlesc($CURUSER["id"]) . ", 
        modname = " . sqlesc($CURUSER["username"]) . ", 
        modtime = '" . get_date_time() . "' 
    WHERE id = " . sqlesc($torrent)
) or sqlerr(__FILE__, __LINE__);

// Увеличиваем счётчик проверок у модератора
sql_query("UPDATE users SET moderated = moderated + 1 WHERE id = " . sqlesc($CURUSER["id"])) or sqlerr(__FILE__, __LINE__);

// Повторно получаем информацию для вывода
$res = sql_query("SELECT modby, modname FROM torrents WHERE id = " . sqlesc($torrent)) or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_assoc($res);

// Выводим результат
echo "<b>Проверен:</b> <a href='userdetails.php?id=" . (int)$row["modby"] . "'>" . htmlspecialchars($row["modname"]) . "</a>";
