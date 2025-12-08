<?php

require_once("include/bittorrent.php");

// Проверка структуры PATH_INFO
if (!isset($_SERVER["PATH_INFO"]) || !preg_match('#^/(\d{1,10})/([\w]{32})/(.+)$#', $_SERVER["PATH_INFO"], $matches)) {
    httperr();
}

$id = (int)$matches[1];
$md5 = $matches[2];
$email = urldecode($matches[3]);

// Проверка ID
if ($id <= 0 || empty($md5) || empty($email)) {
    httperr();
}

dbconn();

// Получаем editsecret из БД
$res = sql_query("SELECT editsecret FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_assoc($res);

if (!$row) {
    httperr();
}

// Проверка editsecret
$sec = hash_pad($row["editsecret"]);
if (preg_match('/^ *$/s', $sec)) {
    httperr();
}

// Проверка MD5-хеша
if ($md5 !== md5($sec . $email . $sec)) {
    httperr();
}

// Обновляем email и очищаем editsecret
sql_query("UPDATE users SET editsecret = '', email = " . sqlesc($email) . " WHERE id = $id AND editsecret = " . sqlesc($row["editsecret"])) or sqlerr(__FILE__, __LINE__);

// Проверка успешности обновления
if (mysqli_affected_rows($GLOBALS["mysqli"]) === 0) {
    httperr();
}

// Перенаправление на профиль с флагом
header("Location: my.php?emailch=1");
exit;
