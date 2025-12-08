<?php
/**
 * adduser.php — с расширенным логированием
 * Пишет подробные логи в ./logs/
 */

/* ===================== ЛОГГЕР (раньше всего!) ===================== */
if (!defined('IN_TRACKER')) define('IN_TRACKER', true);

// Старт буфера — поймаем любой вывод (в т.ч. "Hacking attempt!")
ob_start();

$__LOG_DIR = __DIR__ . '/logs';
$__DAY     = date('Y-m-d');
$__LOG_APP = $__LOG_DIR . "/adduser-{$__DAY}.log";
$__LOG_PHP = $__LOG_DIR . "/php-{$__DAY}.log";

// гарантируем наличие папки
if (!is_dir($__LOG_DIR)) {
    @mkdir($__LOG_DIR, 0775, true);
}

// включаем максимальные ошибки в лог PHP
@ini_set('log_errors', '1');
@ini_set('error_log', $__LOG_PHP);
error_reporting(E_ALL);

// быстрый безопасный дамп значений
function __mask_secrets(array $a): array {
    $maskKeys = ['password', 'password2', 'passhash', 'secret', 'csrf', 'PHPSESSID', 'authorization', 'http_authorization'];
    $out = [];
    foreach ($a as $k => $v) {
        $kk = is_string($k) ? strtolower($k) : $k;
        if (is_array($v)) {
            $out[$k] = __mask_secrets($v);
        } else {
            $isSecret = is_string($kk) && in_array($kk, $maskKeys, true);
            $out[$k]  = $isSecret ? '[MASKED]' : $v;
        }
    }
    return $out;
}
function __kv_dump(array $a): string {
    $lines = [];
    foreach ($a as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $lines[] = "{$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } else {
            $lines[] = "{$k}: {$v}";
        }
    }
    return implode("\n", $lines);
}
function __srv_header_extract(): array {
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = strtolower(str_replace('_', '-', substr($k, 5)));
            $out[$name] = $v;
        }
    }
    return $out;
}
function __log_app(string $stage, array $ctx = []): void {
    global $__LOG_APP;
    $line = '[' . date('Y-m-d H:i:s') . "] {$stage}\n";
    if (!empty($ctx)) {
        $line .= __kv_dump($ctx) . "\n";
    }
    $line .= "----\n";
    @file_put_contents($__LOG_APP, $line, FILE_APPEND);
}

// Заголовок Referrer-Policy до любого вывода (безопасно с OB)
header('Referrer-Policy: same-origin');

// первичный лог до подключения ядра
__log_app('REQUEST_START', [
    'method'        => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri'           => $_SERVER['REQUEST_URI'] ?? '',
    'client_ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
    'host'          => $_SERVER['HTTP_HOST'] ?? '',
    'scheme_guess'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    'referer'       => $_SERVER['HTTP_REFERER'] ?? '',
    'cookies'       => __mask_secrets($_COOKIE ?? []),
    'session_id'    => session_id() ?: '(no session yet)',
    'headers'       => __srv_header_extract(),
]);

/* ===================== ЯДРО И БАЗА ===================== */
require_once "include/bittorrent.php";
dbconn();
loggedinorreturn();

// теперь у нас может быть $DEFAULTBASEURL
__log_app('AFTER_CORE', [
    'DEFAULTBASEURL' => isset($DEFAULTBASEURL) ? $DEFAULTBASEURL : '(unset)',
]);

/* ===================== CSRF BOOT ===================== */
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
function __local_csrf_token(): string {
    if (function_exists('csrf_token')) {
        return csrf_token();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function __local_csrf_check(): void {
    if (function_exists('csrf_check')) {
        // ядро само выкинет stderr/exit при ошибке
        csrf_check();
        return;
    }
    $got = (string)($_POST['csrf'] ?? '');
    $exp = (string)($_SESSION['csrf_token'] ?? '');
    if ($got === '' || $exp === '' || !hash_equals($exp, $got)) {
        __log_app('CSRF_FAIL', [
            'post'    => __mask_secrets($_POST ?? []),
            'session' => __mask_secrets($_SESSION ?? []),
        ]);
        stderr('Ошибка', 'CSRF-проверка не пройдена.');
    }
}

// Глобальные обработчики: error/exception/shutdown
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    __log_app('PHP_ERROR', [
        'errno'   => $errno,
        'errstr'  => $errstr,
        'file'    => $errfile,
        'line'    => $errline,
    ]);
    return false; // пусть стандартный обработчик тоже отработает (и уйдёт в php-*.log)
});
set_exception_handler(function ($ex) {
    __log_app('UNCAUGHT_EXCEPTION', [
        'class'   => get_class($ex),
        'code'    => $ex->getCode(),
        'message' => $ex->getMessage(),
        'file'    => $ex->getFile(),
        'line'    => $ex->getLine(),
        'trace'   => $ex->getTraceAsString(),
    ]);
    // Перебросим наверх как 500
    http_response_code(500);
    die('Internal error.');
});
register_shutdown_function(function () {
    $last = error_get_last();
    $out  = ob_get_contents();
    __log_app('SHUTDOWN', [
        'last_error' => $last ? __mask_secrets($last) : null,
        'output_len' => is_string($out) ? strlen($out) : 0,
        // запишем небольшой префикс вывода, чтобы увидеть "Hacking attempt!"
        'output_head'=> is_string($out) ? substr($out, 0, 500) : '',
    ]);
    // Не глушим вывод пользователю
});

/* ===================== ACL ===================== */
if (get_user_class() < UC_ADMINISTRATOR) {
    __log_app('ACCESS_DENIED', [
        'user_id' => $CURUSER['id'] ?? null,
        'class'   => $CURUSER['class'] ?? null,
    ]);
    stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещён');
}

/* ===================== POST ===================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    __log_app('POST_START', [
        'post'    => __mask_secrets($_POST ?? []),
        'session' => __mask_secrets($_SESSION ?? []),
    ]);

    __local_csrf_check();

    // ===== входные =====
    $username  = (string)($_POST["username"]  ?? '');
    $password  = (string)($_POST["password"]  ?? '');
    $password2 = (string)($_POST["password2"] ?? '');
    $email_raw = (string)($_POST["email"]     ?? '');
    $email     = $email_raw;

    if ($username === '' || $password === '' || $email === '') {
        __log_app('VALIDATION_FAIL', ['reason' => 'missing_form_data']);
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['missing_form_data'] ?? 'Заполните все обязательные поля.');
    }
    if ($password !== $password2) {
        __log_app('VALIDATION_FAIL', ['reason' => 'password_mismatch']);
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['password_mismatch'] ?? 'Пароли не совпадают.');
    }

    // ===== уникальность username/email =====
    $username_try = $username;
    $email_try    = $email;

    $rand3 = static function(): string {
        $abc = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $s = '';
        for ($i = 0; $i < 3; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        return $s;
    };

    do {
        $u_esc  = sqlesc($username_try);
        $res    = sql_query("SELECT 1 FROM users WHERE LOWER(username) = LOWER($u_esc) LIMIT 1");
        $busy_u = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
        if ($busy_u) $username_try = $username . '_' . $rand3();
    } while (!empty($busy_u));

    $mkEmailVariant = static function(string $src, callable $suffix) {
        $at = strrpos($src, '@');
        if ($at !== false && $at > 0) {
            $local = substr($src, 0, $at);
            $dom   = substr($src, $at);
            return $local . '+' . $suffix() . $dom;
        }
        return $src . '_' . $suffix();
    };
    do {
        $e_esc  = sqlesc($email_try);
        $res    = sql_query("SELECT 1 FROM users WHERE LOWER(email) = LOWER($e_esc) LIMIT 1");
        $busy_e = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
        if ($busy_e) $email_try = $mkEmailVariant($email, $rand3);
    } while (!empty($busy_e));

    // ===== секреты/даты/хэши =====
    $secret       = mksecret();
    $passhash     = sqlesc(md5($secret . $password . $secret)); // TBDev совместимость
    $secret_esc   = sqlesc($secret);
    $now          = sqlesc(get_date_time());
    $ip           = getip();
    $ip_esc       = sqlesc($ip);
    $pss          = sqlesc('');

    $username_ins = sqlesc($username_try);
    $email_ins    = sqlesc($email_try);

    // ===== passkey =====
    $gen_passkey = static function (): string {
        return bin2hex(random_bytes(16));
    };
    do {
        $passkey_raw = $gen_passkey();
        $pk_esc      = sqlesc($passkey_raw);
        $res         = sql_query("SELECT 1 FROM users WHERE passkey = $pk_esc LIMIT 1");
        $busy_pk     = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
    } while (!empty($busy_pk));

    $passkey_esc    = sqlesc($passkey_raw);
    $passkey_ip_esc = sqlesc($ip);

    $insert_sql = "
        INSERT INTO users
            (added, last_access, secret, username, passhash, status, email, ip, pss, passkey, passkey_ip)
        VALUES
            ($now,  $now,        $secret_esc, $username_ins, $passhash, 'confirmed', $email_ins, $ip_esc, $pss, $passkey_esc, $passkey_ip_esc)
    ";

    __log_app('INSERT_TRY', [
        // показываем значения без секрета/пароля
        'username'    => $username_try,
        'email'       => $email_try,
        'ip'          => $ip,
        'status'      => 'confirmed',
        'sql_preview' => preg_replace('/\s+/', ' ', trim($insert_sql)),
    ]);

    try {
        sql_query($insert_sql);
        __log_app('INSERT_OK');
    } catch (mysqli_sql_exception $e) {
        __log_app('INSERT_FAIL', [
            'code'    => (int)$e->getCode(),
            'message' => $e->getMessage(),
        ]);

        if ((int)$e->getCode() === 1062) {
            // повторная попытка при коллизии
            do {
                $username_try = $username . '_' . $rand3();
                $u_esc        = sqlesc($username_try);
                $res          = sql_query("SELECT 1 FROM users WHERE LOWER(username) = LOWER($u_esc) LIMIT 1");
                $busy_u       = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
            } while (!empty($busy_u));
            do {
                $email_try = $mkEmailVariant($email, $rand3);
                $e_esc     = sqlesc($email_try);
                $res       = sql_query("SELECT 1 FROM users WHERE LOWER(email) = LOWER($e_esc) LIMIT 1");
                $busy_e    = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
            } while (!empty($busy_e));
            do {
                $passkey_raw = $gen_passkey();
                $pk_esc      = sqlesc($passkey_raw);
                $res         = sql_query("SELECT 1 FROM users WHERE passkey = $pk_esc LIMIT 1");
                $busy_pk     = ($res instanceof mysqli_result) ? (mysqli_num_rows($res) > 0) : false;
            } while (!empty($busy_pk));
            $username_ins = sqlesc($username_try);
            $email_ins    = sqlesc($email_try);
            $passkey_esc  = sqlesc($passkey_raw);

            $insert_sql = "
                INSERT INTO users
                    (added, last_access, secret, username, passhash, status, email, ip, pss, passkey, passkey_ip)
                VALUES
                    ($now,  $now,        $secret_esc, $username_ins, $passhash, 'confirmed', $email_ins, $ip_esc, $pss, $passkey_esc, $passkey_ip_esc)
            ";
            __log_app('INSERT_RETRY', [
                'username'    => $username_try,
                'email'       => $email_try,
                'sql_preview' => preg_replace('/\s+/', ' ', trim($insert_sql)),
            ]);
            sql_query($insert_sql);
            __log_app('INSERT_OK_AFTER_RETRY');
        } else {
            throw $e;
        }
    }

    global $mysqli;
    $id = (int)$mysqli->insert_id;
    __log_app('INSERT_RESULT', ['insert_id' => $id]);

    if ($id <= 0) {
        __log_app('INSERT_ID_FAIL');
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['unable_to_create_account'] ?? 'Не удалось создать аккаунт.');
    }

    @include_once('./include/community.php');

    __log_app('REDIRECT', ['to' => "$DEFAULTBASEURL/userdetails.php?id=$id"]);
    header("Location: $DEFAULTBASEURL/userdetails.php?id=$id");
    exit;
}

/* ===================== UI ===================== */
stdhead($tracker_lang['add_user'] ?? 'Добавить пользователя');
begin_frame($tracker_lang['add_user'] ?? 'Добавить пользователя');
?>
<style>
:root { --ink:#111; --muted:#666; --bd:#e5e7eb; --bd2:#d1d5db; --accent:#0ea5e9; --bg:#fff; }
@media (prefers-color-scheme: dark){
  :root { --ink:#e5e7eb; --muted:#9ca3af; --bd:#252a31; --bd2:#2f3640; --bg:#111317; --accent:#38bdf8; }
}
.min-wrap{max-width:640px;margin:8px auto;padding:8px 0;color:var(--ink)}
.min-form{width:100%}
.min-form .row{display:flex;gap:12px;align-items:center;margin:8px 0}
.min-form .row label{width:180px;color:var(--muted);font-size:13px}
.min-form .row .ctl{flex:1}
.input{
  width:100%; box-sizing:border-box; padding:9px 10px;
  border:1px solid var(--bd); border-radius:6px; background:var(--bg); color:var(--ink);
}
.input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 20%, transparent)}
.help{font-size:12px;color:var(--muted);margin-top:4px}
.actions{display:flex;gap:8px;justify-content:flex-start;margin-top:12px}
.btn{padding:8px 12px;border:1px solid var(--bd2);background:var(--bg);color:var(--ink);border-radius:6px;cursor:pointer}
.btn:focus{outline:none;border-color:var(--accent)}
.btn.primary{border-color:var(--accent)}
.small{font-size:12px;color:var(--muted)}
.inline{display:flex;align-items:center;gap:6px}
</style>

<div class="min-wrap">
  <form class="min-form" method="post" action="<?= $DEFAULTBASEURL ?>/adduser.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(__local_csrf_token(), ENT_QUOTES) ?>">

    <div class="row">
      <label for="f_username"><?= htmlspecialchars($tracker_lang['username'] ?? 'Имя пользователя', ENT_QUOTES) ?></label>
      <div class="ctl">
        <input class="input" id="f_username" type="text" name="username" required>
      </div>
    </div>

    <div class="row">
      <label for="f_password"><?= htmlspecialchars($tracker_lang['password'] ?? 'Пароль', ENT_QUOTES) ?></label>
      <div class="ctl">
        <input class="input" id="f_password" type="password" name="password" required>
        <div class="inline small" style="margin-top:6px">
          <input id="showpw" type="checkbox" onclick="var p=document.getElementById('f_password'); var r=document.getElementById('f_password2'); p.type=p.type==='password'?'text':'password'; r.type=r.type==='password'?'text':'password';">
          <label for="showpw" class="small">Показать пароль</label>
          <a href="#" class="small" onclick="genPw('f_password','f_password2');return false;">Сгенерировать</a>
        </div>
      </div>
    </div>

    <div class="row">
      <label for="f_password2"><?= htmlspecialchars($tracker_lang['repeat_password'] ?? 'Повторите пароль', ENT_QUOTES) ?></label>
      <div class="ctl">
        <input class="input" id="f_password2" type="password" name="password2" required>
      </div>
    </div>

    <div class="row">
      <label for="f_email">E-mail</label>
      <div class="ctl">
        <input class="input" id="f_email" type="email" name="email" required>
        <div class="help">При занятости логина/e-mail добавится короткий суффикс автоматически.</div>
      </div>
    </div>

    <div class="actions">
      <button class="btn primary" type="submit">Создать</button>
      <button class="btn" type="reset">Сброс</button>
    </div>
  </form>
</div>

<script>
function genPw(id1,id2){
  var s='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%_-+=', n=14, r='';
  for(var i=0;i<n;i++) r+=s[Math.floor(Math.random()*s.length)];
  var a=document.getElementById(id1), b=document.getElementById(id2);
  if(a) a.value=r; if(b && !b.value) b.value=r;
}
</script>
<?php
end_frame();
stdhead(); // поможем увидеть, если внезапно оборвётся на stdfoot
$__OUT_PREV = ob_get_length();
stdfoot();
__log_app('UI_RENDERED', [
    'buffer_len_before_stdfoot' => $__OUT_PREV,
    'buffer_len_after_stdfoot'  => ob_get_length(),
]);
