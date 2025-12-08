<?php

if (!defined('ADMIN_FILE')) die("Illegal File Access");
if (get_user_class() == UC_SYSOP)
BuildMenu("staffmess.php", "Массовое ЛС", "ls.png");

?>