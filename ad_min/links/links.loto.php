<?php

if (!defined('ADMIN_FILE')) die("Illegal File Access");
if (get_user_class() == UC_SYSOP)
BuildMenu("loto-start.php", "Разыграть Супер-Лото", "lt.png");

?>