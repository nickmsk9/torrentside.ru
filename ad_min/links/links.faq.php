<?php

if (!defined('ADMIN_FILE')) die("Illegal File Access");
if (get_user_class() == UC_SYSOP)
$admin_file = $admin_file ?? "index"; // значение по умолчанию
BuildMenu("{$admin_file}.php?op=FaqAdmin", "Настройки ЧаВо", "faq.png");

?>