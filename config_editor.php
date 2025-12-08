<?php

require "include/bittorrent.php";

// === фиксы пустой страницы ===
if (!defined('IN_TRACKER')) {
    define('IN_TRACKER', true);   // чтобы include/config.php не делал die()
}
dbconn(false);
loggedinorreturn();

stdhead("Мои списки пользователей");
begin_frame("Мои списки пользователей");

if (get_user_class() < UC_SYSOP)
    stderr("Ошибка", "У вас нет доступа к конфигурации!");

$config_path = $rootpath . "include/config.php";

// --- Определяем все конфиг-переменные по группам ---
$config_groups = [
    "Основное" => [
        "SITE_ONLINE" => ["Сайт онлайн", "bool"],
        "SITENAME" => ["Название сайта", "text"],
        "SITEEMAIL" => ["Email сайта", "email"],
    ],
    "Торренты" => [
        "max_torrent_size" => ["Макс. размер .torrent (байт)", "number"],
        "torrent_dir" => ["Папка торрентов", "text"],
        "doxpath" => ["Папка документации", "text"],
    ],
    "Пользователи и регистрация" => [
        "maxusers" => ["Макс. пользователей", "number"],
        "signup_timeout" => ["Таймаут регистрации (сек)", "number"],
        "deny_signup" => ["Запретить регистрацию", "bool"],
        "allow_invite_signup" => ["Регистрация по инвайтам", "bool"],
        "use_email_act" => ["Активация по email", "bool"],
        "recover_captcha" => ["Капча при восстановлении", "bool"],
    ],
    "Внешний вид" => [
        "default_theme" => ["Тема по умолчанию", "text"],
        "default_language" => ["Язык по умолчанию", "text"],
        "pic_base_url" => ["Путь к картинкам", "text"],
        "avatar_max_width" => ["Макс. ширина аватара", "number"],
        "avatar_max_height" => ["Макс. высота аватара", "number"],
    ],
    "Очистка и автообновление" => [
        "autoclean_interval" => ["Интервал очистки (сек)", "number"],
        "max_dead_torrent_time" => ["Макс. время мёртвого торрента (сек)", "number"],
        "points_per_hour" => ["Бонусов в час", "number"],
        "points_per_cleanup" => ["Бонусов за очистку", "number"],
    ],
    "Трекер" => [
        "announce_interval" => ["Интервал announce (сек)", "number"],
        "minvotes" => ["Мин. голосов", "number"],
        "ttl_days" => ["TTL раздачи (дней)", "number"],
        "use_ttl" => ["Использовать TTL", "bool"],
        "ctracker" => ["Оптимиз. CTracker", "bool"],
    ],
    "Дополнительно" => [
        "use_wait" => ["Система ожидания", "bool"],
        "use_lang" => ["Разрешить смену языка", "bool"],
        "use_gzip" => ["GZip-сжатие", "bool"],
        "use_ipbans" => ["Бан по IP", "bool"],
        "use_sessions" => ["Использовать сессии", "bool"],
        "nc" => ["NC параметр", "text"],
        "radio" => ["Онлайн радио", "bool"],
    ],
    "SMTP и отчёты" => [
        "smtptype" => ["Тип SMTP", "text"],
        "admin_email" => ["Email администратора", "email"],
        "report_sql_admin_pm" => ["SQL-отчёт в ЛС", "bool"],
        "report_sql_admin_email" => ["SQL-отчёт на Email", "bool"],
        "report_failed_login_email" => ["Email при ошибках входа", "bool"],
    ],
    "Антиспам/Антифлуд" => [
        "as_timeout" => ["Задержка между ЛС (сек)", "number"],
        "as_check_messages" => ["Проверка дубликатов ЛС", "bool"],
        "add_tag" => ["Разрешить теги", "bool"],
    ],
    "Антихакер" => [
        "hacker_ban_time" => ["Бан при взломе (мин)", "number"],
    ],
];

// --- Обработка отправки ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['config'])) {
    $new = $_POST['config'];
    $out = "<?php\n\n// Защита от прямого доступа\nif (!defined(\"IN_TRACKER\") && !defined(\"IN_ANNOUNCE\")) {\n    die(\"Hacking attempt!\");\n}\n\n";

    foreach ($config_groups as $group => $items) {
        $out .= "\n// --- $group ---\n";
        foreach ($items as $key => [$label, $type]) {
            $val = trim($new[$key] ?? '');
            switch ($type) {
                case "bool":
                    $val = $val === "1" ? "true" : "false";
                    break;
                case "number":
                    $val = (int)$val;
                    break;
                default:
                    $val = "'" . addslashes($val) . "'";
            }
            $out .= "\$$key = $val;\n";
        }
    }

    file_put_contents($config_path, $out);
    header("Location: admincp.php?op=Config&saved=1");
    exit;
}

// --- Загрузка значений ---
if (!is_file($config_path)) {
    stderr("Ошибка", "Файл конфигурации не найден: " . htmlspecialchars($config_path));
}
include $config_path;



if (isset($_GET['saved'])) {
    echo "<div style='color: green; font-weight: bold;'>Конфигурация успешно сохранена.</div><br>";
}

echo "<form method='post' action='admincp.php?op=Config'>";
echo "<table width='100%' class='main' cellspacing='5' cellpadding='4'>";

foreach ($config_groups as $group => $items) {
    echo "<tr><td colspan='2' class='colhead'><b>$group</b></td></tr>";
    foreach ($items as $var => [$label, $type]) {
        $value = $$var ?? '';
        $input = '';

        switch ($type) {
            case "bool":
                $input = "<select name='config[$var]'>
                    <option value='1'" . ($value ? " selected" : "") . ">Включено</option>
                    <option value='0'" . (!$value ? " selected" : "") . ">Выключено</option>
                </select>";
                break;
            case "number":
                $input = "<input type='number' name='config[$var]' value='$value' style='width:100px'>";
                break;
            case "email":
                $input = "<input type='email' name='config[$var]' value='" . htmlspecialchars($value) . "' style='width:98%'>";
                break;
            default:
                $input = "<input type='text' name='config[$var]' value='" . htmlspecialchars($value) . "' style='width:98%'>";
        }

        echo "<tr><td class='rowhead' width='50%'><b>$label</b></td><td class='embedded'>$input</td></tr>";
    }
}

echo "</table><br><center><input type='submit' value='Сохранить изменения' style='padding: 8px 16px; font-weight: bold;'></center></form>";
end_frame();
stdfoot();