<?php
// Устанавливаем кодировку и вывод ошибок
header("Content-Type: text/html; charset=UTF-8");
error_reporting(E_ALL);
ini_set("display_errors", "1");

require_once __DIR__ . "/include/bittorrent.php";
dbconn(false);

if (!isset($CURUSER) || $CURUSER["class"] < UC_SYSOP) {
    stderr("Ошибка доступа", "У вас нет прав для доступа к панели администратора.");
    exit;
}

define("ADMIN_FILE", 1);

// Заголовок и начало страницы
stdhead("Панель администратора");
begin_frame("Панель администратора");

// Определяем модуль
$op = $_GET['op'] ?? '';
$admin_module_path = __DIR__ . "/ad_min/modules/{$op}.php";

// Если модуль передан — подключаем его, иначе — подключаем главную панель
if ($op && file_exists($admin_module_path)) {
    require_once $admin_module_path;
} else {
    // Главная страница админки
    require_once __DIR__ . "/ad_min/admin.php";
}

end_frame();
stdfoot();
?>
