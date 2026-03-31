<?php
require_once("include/bittorrent.php");

if (!mkglobal("username:password")) {
    die();
}

dbconn();

// ---- вспомогалка ошибок (единое сообщение, без утечки, существует ли юзер) ---
function bark($text = "Имя пользователя или пароль неверны"): void {
    stderr("Ошибка входа", $text);
}

// ---- троттлинг попыток: 10 сек по (логин + IP) через Memcached ----
$ip = getip();
$throttleTtl  = 10;
$throttleKey  = 'login:attempt:' . sha1(strtolower($username) . '|' . $ip);
$throttleValue = tracker_cache_get($throttleKey, $cacheHit);
$nextTryAt = $cacheHit ? (int)$throttleValue : 0;
if ($nextTryAt && time() < $nextTryAt) {
    bark("Слишком часто. Попробуйте снова через несколько секунд.");
}

// ---- получаем пользователя (строго одна запись) ----
$res = sql_query("SELECT id, passhash, secret, pss, enabled, status, last_login, ip 
                  FROM users 
                  WHERE username = " . sqlesc($username) . " 
                  LIMIT 1");
$row = mysqli_fetch_assoc($res);

// Чтобы не палить существование логина — ведём себя одинаково
if (!$row) {
    // ставим троттлинг и выходим
    tracker_cache_set($throttleKey, time() + $throttleTtl, $throttleTtl);
    bark(); // «Имя пользователя или пароль неверны»
}

// ---- проверка пароля (constant-time) ----
if (!verify_tracker_password($row, $password)) {
    // Тихо уведомим в ЛС без пароля (IP, User-Agent)
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $msg = "Неудачная попытка входа под вашим аккаунтом с IP {$ip}. UA: {$ua}. Если это не вы — смените пароль и сообщите администрации.";
    sql_query("INSERT INTO messages (poster, sender, receiver, added, msg, subject)
               VALUES (0, 0, " . (int)$row["id"] . ", " . sqlesc(get_date_time()) . ", " . sqlesc($msg) . ", 'Попытка входа под вашим аккаунтом')")
        or sqlerr(__FILE__, __LINE__);

    // троттлинг на 10 сек
    tracker_cache_set($throttleKey, time() + $throttleTtl, $throttleTtl);
    bark();
}

maybe_upgrade_tracker_password((int)$row['id'], $row, $password);

// ---- статус/доступ ----
if ($row["status"] === 'pending') {
    bark("Вы ещё не активировали аккаунт. Проверьте почту и подтвердите регистрацию.");
}
if ($row["enabled"] === "no") {
    bark("Этот аккаунт отключён.");
}

// ---- защита «активен в пирах с другим IP» (как было) ----
$peers = sql_query("SELECT COUNT(id) FROM peers WHERE userid = " . (int)$row["id"]);
[$peers_cnt] = mysqli_fetch_row($peers);
if ($peers_cnt > 0 && !empty($row['ip']) && $row['ip'] !== $ip) {
    bark("Пользователь сейчас активен. Вход с другого IP невозможен.");
}

// ---- логиним: куки + обновим last_login только при УСПЕШНОМ входе ----
logincookie($row["id"], $row["passhash"]);
sql_query("UPDATE users SET last_login = NOW(), ip = " . sqlesc($ip) . " WHERE id = " . (int)$row["id"] . " LIMIT 1");

// сбрасываем троттлинг при успехе
tracker_cache_delete($throttleKey);

// ---- редирект ----
$returnto = $_POST["returnto"] ?? '';
if (!empty($returnto)) {
    // делаем безопасный относительный редирект
    $path = ltrim($returnto, '/');
    header("Location: $DEFAULTBASEURL/$path");
} else {
    header("Location: $DEFAULTBASEURL/");
}
exit;
