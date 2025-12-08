<?php
// Запрет прямого доступа, если не определён IN_TRACKER
if (!defined("IN_TRACKER")) {
    die("Попытка взлома!");
}

// Подключение основных файлов движка
require_once($rootpath . 'include/init.php');
require_once($rootpath . 'include/global.php');
require_once($rootpath . 'include/config.php');
require_once($rootpath . 'include/functions.php');
require_once($rootpath . 'include/secrets.php');

// Подключение модуля защиты, если включён
if (isset($ctracker) && $ctracker === "1") {
    require_once($rootpath . 'include/ctracker.php');
}

// Устанавливаем константы
define("BETA_NOTICE", "\n<br />Внимание: Это версия Release Candidate 0! Возможны ошибки в коде.");
define("DEBUG_MODE", 0); // Установите 1, чтобы отображать SQL-запросы внизу страницы


?>
