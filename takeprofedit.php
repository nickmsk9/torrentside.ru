<?php
declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";

/** Быстрая ошибка и выход */
function bark(string $msg): void {
    stderr("Ошибка", $msg);
    exit;
}

dbconn(false);
loggedinorreturn();

/** Глобалы из ядра */
global $CURUSER, $BASEUSER, $BASEURL, $SITEEMAIL;
/** @var mysqli|null $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) bark('Нет подключения к БД.');

/** ---------------------- Хелперы ---------------------- */

/** Нормализация строки: трим + схлоп пробелов */
function norm_string(?string $s): string {
    $s = (string)$s;
    $s = trim($s);
    return (string)preg_replace('/\s+/u', ' ', $s);
}

/** Валидация email (если уже есть validemail, этот блок не выполнится) */
if (!function_exists('validemail')) {
    function validemail(string $email): bool {
        $email = trim($email);
        if ($email === '' || strlen($email) > 254) return false;
        if (strpos($email, '@') === false) return false;
        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') return false;

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) return false;
            $domain = $ascii;
        }

        $normalized = $local . '@' . $domain;
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) return false;
        if (preg_match('/\.{2,}/', $normalized)) return false;
        return true;
    }
}

/** Telegram: валидатор и нормализация */
function is_valid_telegram(string $s): bool {
    $s = trim($s);
    if ($s === '') return true; // необязательное поле
    if (preg_match('~^https?://t\.me/([A-Za-z0-9_]{5,32})$~i', $s)) return true;
    if (preg_match('~^@?[A-Za-z0-9_]{5,32}$~', $s)) return true;
    return false;
}
function normalize_telegram(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('~^https?://t\.me/([A-Za-z0-9_]{5,32})$~i', $s, $m)) {
        return $m[1]; // только ник
    }
    return ltrim($s, '@');
}

/** Website: валидация/нормализация под твою логику вывода */
function sanitize_profile_website(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return '';

    // относительные и якоря разрешаем как есть
    $isRelative =
        str_starts_with($raw, '/')  ||
        str_starts_with($raw, './') ||
        str_starts_with($raw, '../')||
        str_starts_with($raw, '#')  ||
        str_starts_with($raw, '?');

    $stripCtl = static fn(string $s) => (string)preg_replace('~[\x00-\x1F\x7F]~', '', $s);

    if ($isRelative) {
        return mb_substr($stripCtl($raw), 0, 255);
    }

    // если схемы нет — добавим https://
    $normalized = preg_match('~^[a-z][a-z0-9+\-.]*://~i', $raw) ? $raw : ('https://' . $raw);

    // только http/https
    $parts  = @parse_url($normalized);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http','https'], true)) {
        return '';
    }

    $normalized = mb_substr($stripCtl($normalized), 0, 255);
    if (!filter_var($normalized, FILTER_VALIDATE_URL)) {
        return '';
    }
    return $normalized;
}

/** ---------------------- CSRF (мягкая проверка) ---------------------- */
// Если в форме передан токен и в сессии он есть — сверим. Если нет — пропустим (обратная совместимость).
if (isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    && (!is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
    bark('CSRF token mismatch');
}

/** ---------------------- Обязательные поля ---------------------- */
if (!mkglobal("email:chpassword:passagain")) {
    bark("Не все обязательные поля заполнены.");
}
$email      = norm_string($email ?? '');
$chpassword = (string)($chpassword ?? '');
$passagain  = (string)($passagain ?? '');

/** ---------------------- Сбор входных данных ---------------------- */
// Для смены пароля — по желанию требуем старый пароль (если поле пришло).
$oldpassword = (string)($_POST['oldpassword'] ?? '');

// Website
$website_clear = isset($_POST['website_clear']) && $_POST['website_clear'] === '1';
$website_in    = (string)($_POST['website'] ?? '');
$website_out   = $website_clear ? '' : sanitize_profile_website($website_in);

// Часовой пояс (если есть такая колонка в БД)
$tzoffset = isset($_POST['tzoffset']) ? trim((string)$_POST['tzoffset']) : null;
if ($tzoffset !== null) {
    $allowed_tz = ["-12","-11","-10","-9","-8","-7","-6","-5","-4","-3.5","-3","-2","-1","0","1","2","3","3.5","4","4.5","5","5.5","5.75","6","6.5","7","8","9","9.5","10","11","12"];
    if (!in_array($tzoffset, $allowed_tz, true)) $tzoffset = "0";
}

/** ---------------------- Начало апдейта ---------------------- */
$updateset    = [];
$changedemail = false;

/* ---------- Смена пароля ---------- */
if ($chpassword !== '') {
    if (strlen($chpassword) > 64) {
        bark("Извините, ваш пароль слишком длинный (максимум 64 символа).");
    }
    if ($chpassword !== $passagain) {
        bark("Пароли не совпадают. Попробуйте ещё раз.");
    }

    // Если хотим требовать старый пароль — проверим (CURUSER должен содержать secret и passhash)
    if ($oldpassword === '' || !isset($CURUSER['secret'], $CURUSER['passhash'])) {
        bark("Укажите старый пароль.");
    }
    $expected = md5($CURUSER['secret'] . $oldpassword . $CURUSER['secret']);
    if (!hash_equals((string)$CURUSER['passhash'], $expected)) {
        bark("Старый пароль указан неверно.");
    }

    $sec = mksecret();
    // Историческая совместимость TBDev:
    $passhash = md5($sec . $chpassword . $sec);

    $updateset[] = "secret = " . sqlesc($sec);
    $updateset[] = "passhash = " . sqlesc($passhash);
    logincookie((int)$CURUSER["id"], $passhash);
}

/* ---------- Смена email (с подтверждением) ---------- */
if ($email !== ($CURUSER["email"] ?? '')) {
    if (!validemail($email)) {
        bark("Введён некорректный email.");
    }
    $r = mysqli_query($mysqli, "SELECT id FROM users WHERE email = " . sqlesc($email) . " AND id <> " . (int)$CURUSER['id']) or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($r) > 0) {
        bark("Адрес электронной почты $email уже используется.");
    }
    $changedemail = true;
    // email в таблицу пишем только после подтверждения — поэтому здесь НЕ обновляем поле email
}

/* ---------- Прочие поля профиля ---------- */
$acceptpms  = $_POST["acceptpms"] ?? "yes";
if (!in_array($acceptpms, ["yes","friends","no"], true)) $acceptpms = "yes";

$deletepms  = !empty($_POST["deletepms"]) ? "yes" : "no";
$savepms    = !empty($_POST["savepms"])   ? "yes" : "no";
$bot_pos    = (!empty($_POST["bot_pos"]) && $_POST["bot_pos"] === "no") ? "no" : "yes";
$avatars    = !empty($_POST["avatars"])   ? "yes" : "no";

$gender     = $_POST["gender"] ?? '1'; // '1','2','3'
if (!in_array($gender, ['1','2','3'], true)) $gender = '1';

$title      = norm_string($_POST["title"] ?? '');
$info       = (string)($_POST["info"] ?? '');
$avatar     = norm_string($_POST["avatar"] ?? '');
$stylesheet = max(1, (int)($_POST["stylesheet"] ?? 1));
$country    = max(0, (int)($_POST["country"] ?? 0));

/* ---------- Уведомления ---------- */
$pmnotif    = (($_POST["pmnotif"]    ?? "no") === "yes") ? "yes" : "no";
$emailnotif = (($_POST["emailnotif"] ?? "no") === "yes") ? "yes" : "no";

$notifs = "";
if ($pmnotif === "yes")    $notifs .= "[pm]";
if ($emailnotif === "yes") $notifs .= "[email]";

$res = mysqli_query($mysqli, "SELECT id FROM categories") or sqlerr(__FILE__, __LINE__);
while ($arr = mysqli_fetch_assoc($res)) {
    $cid = (int)$arr['id'];
    if (!empty($_POST["cat{$cid}"]) && $_POST["cat{$cid}"] === 'yes') {
        $notifs .= "[cat{$cid}]";
    }
}

/* ---------- На страницу ---------- */
$tpp    = min(100, max(0, (int)($_POST["torrentsperpage"] ?? 0)));
$toppp  = min(100, max(0, (int)($_POST["topicsperpage"]  ?? 0)));
$postpp = min(100, max(0, (int)($_POST["postsperpage"]   ?? 0)));

/* ---------- Telegram (вместо ICQ) ---------- */
$telegram_in = trim((string)unesc($_POST['telegram'] ?? ''));
if (!is_valid_telegram($telegram_in)) {
    bark('Укажите корректный Telegram: @username (5–32) или https://t.me/username.');
}
$telegram = normalize_telegram($telegram_in);

/* ---------- Смена пасскея (опционально, если чекбокс в форме) ---------- */
if (!empty($_POST['resetpasskey']) && $_POST['resetpasskey'] === '1') {
    $newpasskey = md5(mksecret() . (string)$CURUSER['username'] . microtime(true));
    $updateset[] = "passkey = " . sqlesc($newpasskey);
}

/** ---------------------- Формирование UPDATE ---------------------- */
$updateset[] = "acceptpms = " . sqlesc($acceptpms);
$updateset[] = "deletepms = " . sqlesc($deletepms);
$updateset[] = "savepms = " . sqlesc($savepms);
$updateset[] = "bot_pos = " . sqlesc($bot_pos);
$updateset[] = "avatars = " . sqlesc($avatars);
$updateset[] = "notifs = " . sqlesc($notifs);
$updateset[] = "gender = " . sqlesc($gender);
$updateset[] = "title = " . sqlesc($title);
$updateset[] = "info = " . sqlesc($info);
$updateset[] = "avatar = " . sqlesc($avatar);
$updateset[] = "country = " . (int)$country;
$updateset[] = "stylesheet = " . (int)$stylesheet;
$updateset[] = "torrentsperpage = " . (int)$tpp;
$updateset[] = "topicsperpage = " . (int)$toppp;
$updateset[] = "postsperpage = " . (int)$postpp;
$updateset[] = "telegram = " . sqlesc($telegram);
$updateset[] = "website = " . sqlesc($website_out);

if ($tzoffset !== null) {
    $updateset[] = "tzoffset = " . sqlesc($tzoffset);
}

/** ---------------------- Подтверждение email ---------------------- */
$urladd = "";
if ($changedemail) {
    $sec  = mksecret();
    $hash = md5($sec . $email . $sec);
    $updateset[] = "editsecret = " . sqlesc($sec);

    $thishost   = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "localhost";
    $thisdomain = preg_replace('/^www\./i', "", $thishost);
    $obemail    = urlencode($email);

    $body = <<<EOD
Вы запросили изменение email-адреса для профиля пользователя {$CURUSER["username"]}
на сайте {$thisdomain}. Новый email: {$email}.

Если это сделали не вы — проигнорируйте это сообщение. IP-адрес отправителя: {$_SERVER["REMOTE_ADDR"]}.

Для подтверждения смены email перейдите по ссылке:

http://{$thishost}/confirmemail.php/{$CURUSER["id"]}/{$hash}/{$obemail}

Если вы не перейдёте по ссылке, текущий email останется без изменений.
EOD;

    // Если у тебя есть sent_mail(), лучше использовать её
    @mail($email, "{$thisdomain}: подтверждение смены email", $body, "From: {$SITEEMAIL}\r\n");
    $urladd .= "&mailsent=1";
}

/** ---------------------- Выполнить UPDATE ---------------------- */
$sql = "UPDATE users SET " . implode(", ", $updateset) . " WHERE id = " . (int)$CURUSER["id"];
mysqli_query($mysqli, $sql) or sqlerr(__FILE__, __LINE__);

/** ---------------------- Инвалидация кэша (если используешь) ---------------------- */
if (isset($memcached) && $memcached instanceof Memcached) {
    $memcached->delete('user:' . (int)$CURUSER['id']);
}

/** ---------------------- Редирект ---------------------- */
header("Location: {$BASEURL}/my.php?edited=1{$urladd}");
exit;
