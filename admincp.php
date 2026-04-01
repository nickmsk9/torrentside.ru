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
$admin_file = 'admincp';

require_once __DIR__ . '/ad_min/routes.php';

$admincpRoute = admincp_resolve_route((string)($_REQUEST['op'] ?? ''));
$op = (string)$admincpRoute['dispatch_op'];
$adminModulePath = $admincpRoute['module_path'];
$pageTitle = $admincpRoute['is_dashboard']
    ? 'Панель администратора'
    : 'Админка: ' . (string)$admincpRoute['title'];

stdhead($pageTitle);

if (!$admincpRoute['is_dashboard']) {
    echo '<div style="margin:0 auto 14px;max-width:1320px;padding:10px 14px;border:1px solid rgba(24,39,75,.12);border-radius:12px;background:rgba(255,255,255,.86)">'
        . '<a href="admincp.php"><b>Админка</b></a>'
        . ' <span style="color:#8a94a6">/</span> '
        . htmlspecialchars((string)$admincpRoute['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</div>';
}

if (!$admincpRoute['is_dashboard'] && is_string($adminModulePath) && is_file($adminModulePath)) {
    require_once $adminModulePath;
} else {
    require_once __DIR__ . "/ad_min/admin.php";
}

stdfoot();
