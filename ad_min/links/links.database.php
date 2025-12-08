<?php

if (!defined("ADMIN_FILE")) die("Illegal File Access");

if (get_user_class() == UC_SYSOP)
    BuildMenu("statusdb.php", "Оптимизация БД", "db.png");

?>
