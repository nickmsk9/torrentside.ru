<?php

require_once("include/bittorrent.php");

// Получение ID пользователя и секретного ключа
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$md5 = $_GET["secret"] ?? '';

// Если ID не передан — ошибка
if ($id <= 0 || !$md5) {
    httperr();
}

dbconn();

// Получаем информацию о пользователе
$res = sql_query("SELECT passhash, editsecret, status FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_assoc($res);

// Если пользователь не найден — ошибка
if (!$row) {
    httperr();
}

// Если уже подтвержден — редирект на успех
if ($row["status"] !== "pending") {
    header("Location: ok.php?type=confirmed");
    exit();
}

// Проверка секретного ключа
$sec = hash_pad($row["editsecret"]);
if ($md5 !== md5($sec)) {
    httperr();
}

// Подтверждение аккаунта
sql_query("UPDATE users SET status = 'confirmed', editsecret = '' WHERE id = $id AND status = 'pending'") or sqlerr(__FILE__, __LINE__);

// Проверка, что действительно была изменена строка
if (mysqli_affected_rows($GLOBALS["mysqli"]) === 0) {
    httperr();
}

// Установка cookie
logincookie($id, $row["passhash"]);

// Редирект на страницу подтверждения
header("Location: ok.php?type=confirm");
exit;
