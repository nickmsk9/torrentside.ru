<?php
// include/smarty_init.php
// ЕДИНСТВЕННОЕ корректное подключение для вашей сборки Smarty 5 — через libs/Smarty.class.php
global $smarty;

if (!isset($smarty)) {
    $SMARTY_BASE = dirname(__DIR__) . '/smarty';

    // Регистрируем встроенный автозагрузчик из вашей сборки
    require_once $SMARTY_BASE . '/libs/Smarty.class.php';

    // Инициализация namespaced-класса Smarty 5
    $smarty = new \Smarty\Smarty();

    // Директории (проверьте пути)
    $smarty->setTemplateDir(dirname(__DIR__) . '/templates');
    $smarty->setCompileDir(dirname(__DIR__) . '/templates_c');
    $smarty->setCacheDir(dirname(__DIR__) . '/cache');
    $smarty->setConfigDir(dirname(__DIR__) . '/configs');

    // Базовые настройки
    $smarty->compile_check = false;
    $smarty->force_compile = false;
    $smarty->caching = false;

    // Безопасности/совместимость
    if (property_exists($smarty, 'escape_html')) $smarty->escape_html = true;
    if (method_exists($smarty, 'muteExpectedErrors')) $smarty->muteExpectedErrors();
}
