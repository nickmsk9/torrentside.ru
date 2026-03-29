<?php
header("Content-Type: text/html; charset=UTF-8");

require_once __DIR__ . "/include/bittorrent.php";
dbconn(false);
loggedinorreturn();

if (!isset($CURUSER) || !user_has_module('admin_access')) {
    stderr("Ошибка доступа", "У вас нет прав для доступа к панели администратора.");
    exit;
}

define("ADMIN_FILE", 1);

$op = trim((string)($_GET['op'] ?? ''));
$adminModulePath = '';

if ($op !== '') {
    $adminModulePath = __DIR__ . "/ad_min/modules/{$op}.php";
}

stdhead("Панель администратора");

if ($op !== '' && is_file($adminModulePath)) {
    require_once $adminModulePath;
} else {
    require_once __DIR__ . "/ad_min/admin.php";
}

stdfoot();
