<?php

if (!defined('ADMIN_FILE')) die("Illegal File Access");
if (get_user_class() == UC_SYSOP)
BuildMenu("category.php", "Категории", "cat.png");

?>