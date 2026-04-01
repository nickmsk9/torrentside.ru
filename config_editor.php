<?php

require "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
    stderr("Ошибка", "У вас нет доступа к конфигурации!");

$config_path = $rootpath . "include/config.php";

function config_editor_export_value(mixed $value, string $type): string
{
    return match ($type) {
        'bool' => (!empty($value) ? 'true' : 'false'),
        'number' => (string)(int)$value,
        default => var_export((string)$value, true),
    };
}

function config_editor_replace_assignment(string $configText, string $key, string $valueCode): string
{
    $pattern = '/^\s*\$' . preg_quote($key, '/') . '\s*=.*?;\s*$/m';
    $replacement = '$' . $key . ' = ' . $valueCode . ';';

    if (preg_match($pattern, $configText)) {
        return (string)preg_replace($pattern, $replacement, $configText, 1);
    }

    return rtrim($configText) . "\n" . $replacement . "\n";
}

function config_editor_save_config(string $configPath, array $configGroups, array $newValues): void
{
    $configText = (string)@file_get_contents($configPath);
    if ($configText === '') {
        $configText = "<?php\n\n// Защита от прямого доступа\nif (!defined(\"IN_TRACKER\") && !defined(\"IN_ANNOUNCE\")) {\n    die(\"Hacking attempt!\");\n}\n";
    }

    foreach ($configGroups as $items) {
        foreach ($items as $key => [, $type]) {
            $rawValue = $newValues[$key] ?? null;
            $configText = config_editor_replace_assignment(
                $configText,
                $key,
                config_editor_export_value($rawValue, $type)
            );
        }
    }

    $tmpPath = $configPath . '.tmp';
    if (@file_put_contents($tmpPath, $configText, LOCK_EX) === false) {
        stderr("Ошибка", "Не удалось записать временный файл конфигурации.");
    }

    if (is_file($configPath)) {
        @copy($configPath, $configPath . '.bak');
    }

    if (!@rename($tmpPath, $configPath)) {
        @unlink($tmpPath);
        stderr("Ошибка", "Не удалось заменить основной файл конфигурации.");
    }
}

// --- Определяем все конфиг-переменные по группам ---
$config_groups = [
    "Основное" => [
        "SITE_ONLINE" => ["Сайт онлайн", "bool"],
        "SITENAME" => ["Название сайта", "text"],
        "SITEEMAIL" => ["Email сайта", "email"],
        "site_base_url" => ["Базовый URL сайта", "text"],
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
        "external_tracker_stats_ttl" => ["TTL внешних трекеров (сек)", "number"],
        "external_tracker_http_timeout" => ["Таймаут scrape (сек)", "number"],
        "external_tracker_scrape_limit" => ["Лимит обновлений за проход", "number"],
        "kinozal_user_agent" => ["User-Agent внешнего scrape", "text"],
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

// --- Обработка отправки ДО вывода HTML ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['config']) && is_array($_POST['config'])) {
    if (!is_file($config_path)) {
        stderr("Ошибка", "Файл конфигурации не найден: " . htmlspecialchars($config_path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    if (!is_writable($config_path)) {
        stderr("Ошибка", "Файл конфигурации недоступен для записи: " . htmlspecialchars($config_path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $normalized = [];
    foreach ($config_groups as $items) {
        foreach ($items as $key => [, $type]) {
            $raw = $_POST['config'][$key] ?? '';
            $normalized[$key] = match ($type) {
                'bool' => ((string)$raw === '1'),
                'number' => (int)$raw,
                default => trim((string)$raw),
            };
        }
    }

    config_editor_save_config($config_path, $config_groups, $normalized);
    header("Location: config_editor.php?saved=1");
    exit;
}

// --- Загрузка значений ---
if (!is_file($config_path)) {
    stderr("Ошибка", "Файл конфигурации не найден: " . htmlspecialchars($config_path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}
include $config_path;

stdhead("Редактор конфигурации");
begin_frame("Редактор конфигурации");

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    echo "<div style='color: green; font-weight: bold;'>Конфигурация успешно сохранена.</div><br>";
}

echo "<p>Здесь можно менять ключевые параметры движка без ручного редактирования файла конфигурации.</p>";
echo "<form method='post' action='config_editor.php'>";
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
echo "<div style='margin-top:14px'><a href='admincp.php'><b>← Вернуться в админку</b></a></div>";
end_frame();
stdfoot();
