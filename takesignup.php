<?php

require_once("include/bittorrent.php");
dbconn();

// --- быстрые предикаты доступа ---
if ($deny_signup && !$allow_invite_signup) {
    stderr($tracker_lang['error'], "Извините, но регистрация отключена администрацией.");
}
if ($CURUSER) {
    stderr($tracker_lang['error'], sprintf($tracker_lang['signup_already_registered'], $SITENAME));
}

$users_total = get_row_count("users");
if ($users_total >= $maxusers) {
    stderr($tracker_lang['error'], sprintf($tracker_lang['signup_users_limit'], number_format($maxusers)));
}

// --- утилиты ---
function bark(string $msg): void {
    global $tracker_lang;
    stdhead($tracker_lang['error']);
    stdmsg($tracker_lang['error'], $msg, 'error');
    stdfoot();
    exit;
}
function validusername(string $username): bool {
    if ($username === "") return false;
    // латиница, кириллица, цифры, подчёркивание
    $allowedchars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ";
    $len = mb_strlen($username);
    for ($i = 0; $i < $len; ++$i) {
        if (mb_strpos($allowedchars, mb_substr($username, $i, 1)) === false) return false;
    }
    return true;
}
function normalize(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

// --- обязательные поля из POST ---
if (!mkglobal("wantusername:wantpassword:passagain:email")) {
    bark("Прямой доступ к этому файлу не разрешён.");
}

$wantusername = normalize($wantusername);
$email        = normalize($email);

// дополнительные поля
$gender = isset($_POST["gender"]) ? (int)$_POST["gender"] : 0;       // 1/2
$country = isset($_POST["country"]) ? (int)$_POST["country"] : 0;    // id из таблицы countries
$year = (string)($_POST["year"]  ?? '0000');
$month= (string)($_POST["month"] ?? '00');
$day  = (string)($_POST["day"]   ?? '00');
$telegram = normalize((string)($_POST["telegram"] ?? ''));

// быстрые проверки содержимого
if ($wantusername === '' || $wantpassword === '' || $email === '' || $gender === 0 || $country === 0) {
    bark("Все обязательные поля должны быть заполнены.");
}
if (mb_strlen($wantusername) < 3) bark("Имя пользователя слишком короткое (минимум 3 символа).");
if (mb_strlen($wantusername) > 12) bark("Имя пользователя слишком длинное (максимум 12 символов).");
if (!validusername($wantusername)) bark("Неверное имя пользователя. Разрешены буквы, цифры и подчёркивание.");
if ($wantpassword !== $passagain) bark("Пароли не совпадают.");
if (strlen($wantpassword) < 6) bark("Пароль слишком короткий (минимум 6 символов).");
if (strlen($wantpassword) > 64) bark("Пароль слишком длинный (максимум 64 символа).");
if ($wantpassword === $wantusername) bark("Пароль не может совпадать с именем пользователя.");
if (!validemail($email)) bark("Неверный email-адрес.");

if (!is_valid_telegram($telegram)) {
    bark("Укажите корректный Telegram: @username (5–32) или https://t.me/username.");
}
$telegram = normalize_telegram($telegram);


// --- дата рождения: валидность и возраст ---
if ($year === '0000' || $month === '00' || $day === '00') {
    bark("Пожалуйста, укажите дату рождения полностью.");
}
$y = (int)$year; $m = (int)$month; $d = (int)$day;
if (!checkdate($m, $d, $y)) {
    bark("Пожалуйста, укажите корректную дату рождения.");
}
$birthday = sprintf('%04d-%02d-%02d', $y, $m, $d);
// возраст ≥ 13 лет
$today = new DateTime('now', new DateTimeZone('UTC'));
$dob   = DateTime::createFromFormat('Y-m-d', $birthday, new DateTimeZone('UTC'));
$age   = (int)$dob->diff($today)->y;
if ($age < 13) {
    bark("Регистрация доступна пользователям от 13 лет.");
}

// --- подтверждение соглашений ---
if (
    ($_POST["rulesverify"] ?? '') !== "yes" ||
    ($_POST["faqverify"]   ?? '') !== "yes" ||
    ($_POST["ageverify"]   ?? '') !== "yes"
) {
    bark("Вы должны подтвердить правила, FAQ и возраст.");
}

// --- уникальность логина и почты ---
$res = sql_query("SELECT COUNT(*) FROM users WHERE username = " . sqlesc($wantusername));
[$name_count] = mysqli_fetch_row($res);
if ($name_count > 0) {
    bark("Пользователь с именем «" . htmlspecialchars($wantusername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "» уже существует.");
}

$res = sql_query("SELECT COUNT(*) FROM users WHERE email = " . sqlesc($email));
[$email_count] = mysqli_fetch_row($res);
if ($email_count > 0) {
    bark("E-mail $email уже зарегистрирован.");
}

// --- приглашение (если включён инвайт-режим) ---
$inviter = 0; $invitedroot = 0;
if ($deny_signup && $allow_invite_signup) {
    $invite = (string)($_POST["invite"] ?? '');
    if ($invite === '' || strlen($invite) !== 32) {
        bark("Неверный или отсутствующий код приглашения.");
    }
    $res = sql_query("SELECT inviter FROM invites WHERE invite = " . sqlesc($invite) . " LIMIT 1");
    [$inviter] = mysqli_fetch_row($res) ?: [0];
    if (!$inviter) bark("Код приглашения не найден или уже использован.");

    $res = sql_query("SELECT invitedroot FROM users WHERE id = " . (int)$inviter . " LIMIT 1");
    [$invitedroot] = mysqli_fetch_row($res) ?: [0];
}

// --- IP (опционально можно вернуть анти-мультиакк; пока оставлено выключенным) ---
// $ip = getip();

// --- хеширование пароля ---
// TBDev исторически хранит md5( secret + pass + secret ), оставим совместимость,
// но НЕ будем хранить пароль открыто. Рекомендуется добавить отдельное поле bcrypt_hash (nullable).
$secret     = mksecret();
$passhash   = md5($secret . $wantpassword . $secret);

// Если у тебя есть современное поле, раскомментируй и добавь столбец `bcrypt_hash` VARCHAR(255) NULL:
// $bcrypt_hash = password_hash($wantpassword, PASSWORD_BCRYPT);

// Первому пользователю даём SYSOP + confirmed, остальным — pending при включённой email-активации
$editsecret = $users_total ? mksecret() : '';
$status     = (!$users_total || !$use_email_act) ? 'confirmed' : 'pending';
$class      = !$users_total ? UC_SYSOP : 0;
$added      = get_date_time();

// --- вставка пользователя ---
$columns = [
    "username","passhash","secret","editsecret","gender","country","icq","email","status",
    // class — только для самого первого пользователя
];
$values  = [
    sqlesc($wantusername), sqlesc($passhash), sqlesc($secret), sqlesc($editsecret),
    (int)$gender, (int)$country, sqlesc($icq), sqlesc($email), sqlesc($status)
];

// if (isset($bcrypt_hash)) { $columns[] = "bcrypt_hash"; $values[] = sqlesc($bcrypt_hash); }

if (!$users_total) { $columns[] = "class"; $values[] = (int)$class; }

$columns = array_merge($columns, ["added","birthday","invitedby","invitedroot"]);
$values  = array_merge($values, [sqlesc($added), sqlesc($birthday), (int)$inviter, (int)$invitedroot]);

$sql = "INSERT INTO users (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ")";
sql_query($sql) or bark("Ошибка регистрации. Попробуйте позже.");

$id = mysqli_insert_id($GLOBALS["mysqli"]);
if (!$id) bark("Не удалось создать пользователя.");

// инвайт одноразовый — сжигаем
if ($deny_signup && $allow_invite_signup) {
    sql_query("DELETE FROM invites WHERE invite = " . sqlesc($_POST["invite"])) or write_log("Не удалось удалить инвайт " . substr($_POST["invite"],0,6), "FF9999", "tracker");
}

write_log("Зарегистрирован новый пользователь $wantusername", "FFFFFF", "tracker");

// --- письмо подтверждения / автологин ---
$psecret = md5($editsecret ?: '');
$confirm_link = "$DEFAULTBASEURL/confirm.php?id=$id&secret=$psecret";

$ip_for_mail = $_SERVER["REMOTE_ADDR"] ?? 'unknown';
$body = <<<EOD
Вы зарегистрировались на $SITENAME и указали этот адрес ($email).

Если это были не вы — проигнорируйте это письмо. IP регистрации: {$ip_for_mail}.

Чтобы активировать аккаунт, перейдите по ссылке:

$confirm_link

Если вы этого не сделаете, аккаунт будет удалён через несколько дней.
EOD;

if ($use_email_act && $users_total) {
    if (!sent_mail($email, $SITENAME, $SITEEMAIL, "Подтверждение регистрации на $SITENAME", $body, false)) {
        // откатывать учётку не будем, пусть повторно запросит письмо через «выслать снова»
        stderr($tracker_lang['error'], "Не удалось отправить письмо для подтверждения. Попробуйте позже.");
    }
} else {
    // первый пользователь или email-активация отключена — авторизуем сразу
    logincookie($id, $passhash);
}

// --- редирект на страницу успеха ---
$tail = (!$users_total) ? "sysop" : ("signup&email=" . urlencode($email));
header("Location: ok.php?type=" . $tail);
exit;
