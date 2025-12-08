<?php

require_once("include/bittorrent.php");

dbconn();
loggedinorreturn();

// Безопасно получаем параметры
$from = isset($_GET['from']) ? (int)$_GET['from'] : 0;
$toDir = $_GET['to'] ?? '';

if (!$from || ($toDir !== 'next' && $toDir !== 'pre')) {
    stderr("Ошибка", "Как вы сюда попали? <a href=\"javascript:history.go(-1)\">Назад</a>");
}

// Формируем SQL-условие в зависимости от направления
if ($toDir === 'next') {
    $passCondition = "id > $from ORDER BY id ASC";
    $errorMsg = "Вы уже были на последнем торренте. <a href=\"javascript: history.go(-1)\">Назад</a>";
} else {
    $passCondition = "id < $from ORDER BY id DESC";
    $errorMsg = "Вы были на первом торренте. <a href=\"javascript: history.go(-1)\">Назад</a>";
}

// Выполняем SQL-запрос
$res = sql_query("SELECT id FROM torrents WHERE $passCondition LIMIT 1") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_assoc($res);

// Если торрент не найден — выводим ошибку
if (!$row || !isset($row['id'])) {
    stderr("Ошибка", $errorMsg);
}

// Редирект на найденный торрент
$to = (int)$row['id'];
header("Location: $DEFAULTBASEURL/details.php?id=$to");
exit;
