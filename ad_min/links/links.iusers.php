<?php

if (!defined("ADMIN_FILE")) die("Illegal File Access");
if (get_user_class() == UC_SYSOP)
BuildMenu("".$admin_file.".php?op=iUsers", "Смена парол¤", "password.png");

?>