<?php
declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";
dbconn(false);

/* ======== ГЛОБАЛЬНЫЕ НАСТРОЙКИ / UTF-8 ======== */
if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
    @mysqli_set_charset($GLOBALS['mysqli'], 'utf8mb4');
}
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

/* ======== СОСТОЯНИЕ ПОЛЬЗОВАТЕЛЯ ======== */
$is_logged   = isset($CURUSER) && !empty($CURUSER['id']);
$user_id     = $is_logged ? (int)$CURUSER['id'] : 0;
$user_class  = $is_logged ? (int)$CURUSER['class'] : 0;
$user_name   = $is_logged ? (string)$CURUSER['username'] : '';

/* ======== ДОСТУП ========
   ЛОГИНИТЬСЯ НЕ ОБЯЗАТЕЛЬНО: ГОСТЯМ РАЗРЕШАЕМ ЧТЕНИЕ.
   Если пользователь залогинен, но ему запрещён чат — блокируем. */
if ($is_logged && ($CURUSER["schoutboxpos"] ?? '') === 'no') {
    stdmsg("Ошибка", "Вам запрещено использовать чат.");
    exit;
}

/* ======== КОНСТАНТЫ ======== */
$MSG_MAX_LEN   = 50;
$ROWS_LIMIT    = 35;
$AUTO_REFRESH  = 120;
$now           = time();

/* Показывать ли приват модераторам/админам (по умолчанию — НЕТ, строго 1-к-1) */
$ALLOW_STAFF_SEE_PRIVATE = false;

/* ======== ОБНОВЛЕНИЕ ПОЛЯ page (только для залогиненных) ======== */
if ($user_id > 0) {
    mysqli_query($GLOBALS['mysqli'], "UPDATE users SET page=1 WHERE id={$user_id}") or sqlerr(__FILE__, __LINE__);
}

/* ======== УДАЛЕНИЕ СООБЩЕНИЯ (ТОЛЬКО ДЛЯ МОДЕРАТОРОВ/ВЫШЕ) ======== */
$did_mutate = false;
if ($is_logged && isset($_GET['del']) && $user_class >= UC_MODERATOR) {
    $del_id = (int)$_GET['del'];
    if ($del_id > 0) {
        sql_query("DELETE FROM shoutbox WHERE id=" . sqlesc($del_id));
        $did_mutate = true;
    }
}

/* ======== ОТПРАВКА СООБЩЕНИЯ (ТОЛЬКО ДЛЯ ЗАЛОГИНЕННЫХ) ======== */
if ($is_logged && (($_GET['sent'] ?? '') === 'yes')) {
    $text = trim((string)($_GET['shbox_text'] ?? ''));
    if ($text !== '') {
        if (mb_strlen($text, 'UTF-8') > $MSG_MAX_LEN) {
            die("Слишком длинный текст");
        }
        sql_query(
            "INSERT INTO shoutbox (userid, class, warned, donor, username, date, text, orig_text)
             VALUES (" .
                (int)$CURUSER['id'] . ", " .
                (int)$CURUSER['class'] . ", " .
                sqlesc($CURUSER['warned']) . ", " .
                sqlesc($CURUSER['donor']) . ", " .
                sqlesc($CURUSER['username']) . ", " .
                $now . ", " .
                sqlesc($text) . ", " .
                sqlesc($text) . ")"
        ) or sqlerr(__FILE__, __LINE__);
        $did_mutate = true;
    }
}

/* ======== КЭШ APCu ДЛЯ ЛЕНТЫ ======== */
$cache_enabled = function_exists('apcu_fetch') && ini_get('apcu.enabled');
$cache_key     = 'tbdev_shoutbox_last_' . $ROWS_LIMIT;

if ($did_mutate && $cache_enabled) {
    apcu_delete($cache_key);
}

/* ======== ВЫБОРКА ПОСЛЕДНИХ СООБЩЕНИЙ ======== */
$rows = false;
if ($cache_enabled) {
    $rows = apcu_fetch($cache_key);
}
if ($rows === false) {
    $q = sql_query(
        "SELECT id, userid, class, username, `date`, `text`, `orig_text`
         FROM shoutbox
         ORDER BY `date` DESC
         LIMIT " . (int)$ROWS_LIMIT
    ) or sqlerr(__FILE__, __LINE__);

    $rows = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $r['id']        = (int)$r['id'];
        $r['userid']    = (int)$r['userid'];
        $r['class']     = (int)$r['class'];
        $r['date']      = (int)$r['date'];
        $r['username']  = (string)$r['username'];
        $r['text']      = (string)$r['text'];
        $r['orig_text'] = (string)($r['orig_text'] ?? $r['text']);
        $rows[] = $r;
    }
    if ($cache_enabled) {
        apcu_store($cache_key, $rows, 8);
    }
}

/* ======== ХЕЛПЕРЫ ДЛЯ ПРИВАТОВ ======== */

/** Возвращает имя адресата из текста 'privat(Name)' или null. */
function sb_extract_private_target(string $txt): ?string {
    if (stripos($txt, 'privat(') === false) return null;
    if (preg_match('/privat\(\s*([^()<>\s]+?)\s*\)/i', $txt, $m)) {
        return $m[1];
    }
    return null;
}

/** Строго ли приват виден текущему (только отправитель или адресат). */
function sb_private_visible(
    int $viewer_id,
    string $viewer_name,
    int $sender_id,
    ?string $target_name,
    bool $allow_staff,
    int $viewer_class
): bool {
    if ($target_name === null) return false;
    $is_target = ($viewer_name !== '' && strcasecmp($viewer_name, $target_name) === 0);
    if ($viewer_id === $sender_id || $is_target) {
        return true;
    }
    if ($allow_staff && $viewer_class >= UC_MODERATOR) {
        return true;
    }
    return false;
}

/* ======== ШАПКА HTML ======== */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>ShoutBox</title>
    <meta http-equiv="refresh" content="<?php echo (int)$AUTO_REFRESH; ?>; URL=shoutbox.php">
    <style>
        a { color: #000; font-weight: bold; }
        a:hover { color: #F00; }
        .small { font-size: 8pt; font-family: Tahoma, Arial, sans-serif; }
        .date { font-size: 7pt; color: #666; }
        body {
            background-color: transparent;
            scrollbar-3dlight-color: #004E98;
            scrollbar-arrow-color: #004E98;
            scrollbar-darkshadow-color: white;
            scrollbar-base-color: white;
        }
        td { vertical-align: top; }
        img { display: inline-block; }
        .pm-tag { color: red; font-weight: bold; }
    </style>
</head>
<body>
<?php

if (empty($rows)) {
    echo "<span class='small'>Сообщений нет</span>";
    echo "</body></html>";
    exit;
}

ob_start();

echo "<table border='0' cellspacing='0' cellpadding='2' width='100%' align='left' class='small'>\n";

$is_mod   = $is_logged && ($user_class >= UC_MODERATOR);
$is_admin = $is_logged && ($user_class >= UC_ADMINISTRATOR);
$del_icon = "<img width='13' height='13' src='pic/chatwarned.gif' border='0' alt='del'>";

foreach ($rows as $arr) {
    $tm = date('H:i', $arr['date']);

    // TRIM исходника для команд (на случай, если format_comment меняет строку)
    $raw = trim($arr['orig_text']);
    if ($raw === '') $raw = trim($arr['text']);

    // команда /prune — только админ (и только если залогинен)
    if ($raw === '/prune' && $is_admin) {
        sql_query("TRUNCATE TABLE shoutbox") or sqlerr(__FILE__, __LINE__);
        if ($cache_enabled) apcu_delete($cache_key);
        echo "</table><span class='small'>Сообщений нет</span>";
        echo "</body></html>";
        exit;
    }

    // формат TBDev (смайлы/BB)
    $sd = format_comment($arr['text']);

    // если гость — убираем картинки (смайлы и любые <img>)
    if (!$is_logged) {
        $sd = preg_replace('/<img\b[^>]*>/i', '', $sd);
    }

    // кнопка удаления только для модераторов
    $del = $is_mod ? "<span class='date'><a href='shoutbox.php?del={$arr['id']}'>$del_icon</a></span> " : '';

    // приват?
    $target = sb_extract_private_target($raw);
    if ($target !== null) {
        if (!sb_private_visible($user_id, $user_name, $arr['userid'], $target, $ALLOW_STAFF_SEE_PRIVATE, $user_class)) {
            continue;
        }

        // рендер: privat(Name) → "Name:" (жирным), подсвет адресата
        if ($user_name !== '' && strcasecmp($user_name, $target) === 0) {
            $sd = preg_replace('/privat\(\s*' . preg_quote($target, '/') . '\s*\)/i', "<b class='pm-tag'>{$user_name}:</b>", $sd, 1);
        } else {
            $sd = preg_replace(
                '/privat\(\s*' . preg_quote($target, '/') . '\s*\)/i',
                "<b>" . htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ":</b>",
                $sd,
                1
            );
        }

        $usercolor   = get_user_class_color($arr["class"], $arr["username"]);
        $name_render = $is_logged
            ? "<a href='javascript:window.top.SmileIT(\"privat({$arr['username']})\",\"shbox\",\"shbox_text\")'>{$usercolor}</a>"
            : $usercolor; // гостям — без клика и SmileIT
        echo "<tr><td><span class='date'>|{$tm}|</span> {$del}{$name_render} {$sd}</td></tr>\n";
        continue;
    }

    // обычное сообщение
    $usercolor   = get_user_class_color($arr["class"], $arr["username"]);
    $name_render = $is_logged
        ? "<a href='javascript:window.top.SmileIT(\"privat({$arr['username']})\",\"shbox\",\"shbox_text\")'>{$usercolor}</a>"
        : $usercolor;

    echo "<tr><td><span class='date'>|{$tm}|</span> {$del}{$name_render} {$sd}</td></tr>\n";
}

echo "</table>";

$out = ob_get_clean();
echo $out;

?>
</body>
</html>
