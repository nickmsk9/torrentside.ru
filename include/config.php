<?php

// Защита от прямого доступа
if (!defined("IN_TRACKER") && !defined("IN_ANNOUNCE")) {
    die("Hacking attempt!");
}


// --- Основное ---
$SITE_ONLINE = true;
$SITENAME = 'TorrentSide.ru';
$SITEEMAIL = 'nickmsk9@icloud.com';

// --- Торренты ---
$max_torrent_size = 10000000;
$torrent_dir = 'torrents';
$doxpath = 'dox';

// --- Пользователи и регистрация ---
$maxusers = 77777;
$signup_timeout = 259200;
$deny_signup = false;
$allow_invite_signup = false;
$use_email_act = false;
$recover_captcha = true;

// --- Внешний вид ---
$default_theme = 'Light';
$default_language = 'russian';
$pic_base_url = './pic/';
$avatar_max_width = 100;
$avatar_max_height = 100;

// --- Очистка и автообновление ---
$autoclean_interval = 900;
$max_dead_torrent_time = 21600;
$points_per_hour = 10;
$points_per_cleanup = 2;

// --- Трекер ---
$announce_interval = 900;
$minvotes = 1;
$ttl_days = 28;
$use_ttl = false;
$ctracker = true;

// --- Дополнительно ---
$use_wait = false;
$use_lang = true;
$use_gzip = true;
$use_ipbans = false;
$use_sessions = true;
$nc = 'no';
$radio = false;

// --- SMTP и отчёты ---
$smtptype = 'advanced';
$admin_email = 'dron.9595@inbox.ru';
$report_sql_admin_pm = false;
$report_sql_admin_email = true;
$report_failed_login_email = true;

// --- Антиспам/Антифлуд ---
$as_timeout = 30;
$as_check_messages = true;
$add_tag = true;

// --- Антихакер ---
$hacker_ban_time = 5;

/// Форумные настройки
$Forum_Config = array(

/// включение отключение форума
"on" => true, /// true - вкл, false - выкл
/// причина отключения форума
"off_reason" => "", /// вводим причину

/// гость просмотр форума
"guest" => true, /// true - вкл, false - выкл

/// включение антиспама для новых пользователей
"anti_spam" => true, /// true - вкл, false - выкл
/// количество дней для удаления как прочитанное собщение из базы для каждого юзера
"readpost_expiry" => 14*86400 /// число
);