<?php
/**
 * recover.php — восстановление пароля (без капчи)
 * TBDev / PHP 8.1 (mysqli)
 */

require_once 'include/bittorrent.php';

dbconn();

header('Content-Type: text/html; charset=' . $tracker_lang['language_charset']);

/** @var mysqli|null $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) die('DB handle ($mysqli) is not available');

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fail(string $msg): void {
    global $tracker_lang;
    stderr($tracker_lang['error'], $msg);
}
function ok(string $msg): void {
    global $tracker_lang;
    stderr($tracker_lang['success'], $msg);
}

/* ===================== POST: запрос восстановления ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Введите корректный E-mail.');
    }

    $res = sql_query(
        'SELECT id, email, passhash FROM users WHERE email = ' . sqlesc($email) . ' LIMIT 1'
    ) or sqlerr(__FILE__, __LINE__);

    $arr = mysqli_fetch_assoc($res);
    if (!$arr) fail('E-mail не найден.');

    $sec = mksecret();
    sql_query(
        'UPDATE users SET editsecret = ' . sqlesc($sec) . ' WHERE id = ' . (int)$arr['id'] . ' LIMIT 1'
    ) or sqlerr(__FILE__, __LINE__);

    if (mysqli_affected_rows($mysqli) < 1) {
        fail('Ошибка базы данных. Свяжитесь с администрацией.');
    }

    $hash = md5($sec . $arr['email'] . $arr['passhash'] . $sec);

    $body = <<<EOD
Здравствуйте!

Вы (или кто-то другой) запросили восстановление пароля для аккаунта ({$arr['email']}).

Чтобы подтвердить операцию, перейдите по ссылке:
$DEFAULTBASEURL/recover.php?id={$arr['id']}&secret=$hash

Если вы не запрашивали восстановление — просто игнорируйте это письмо.

--
$SITENAME
EOD;

    $sent = @sent_mail(
        $arr['email'],
        $SITENAME,
        $SITEEMAIL,
        "Подтверждение восстановления пароля на $SITENAME",
        $body
    );

    if (!$sent) fail('Не удалось отправить письмо. Сообщите администрации.');

    ok('Письмо с подтверждением отправлено. Проверьте ваш почтовый ящик.');
    exit;
}

/* ===================== GET: подтверждение восстановления ===================== */
if (isset($_GET['id'], $_GET['secret'])) {
    $id  = (int)($_GET['id']);
    $md5 = (string)$_GET['secret'];

    if ($id < 1 || $md5 === '' || !ctype_xdigit($md5) || strlen($md5) !== 32) {
        httperr();
    }

    $res = sql_query(
        'SELECT username, email, passhash, editsecret FROM users WHERE id = ' . $id . ' LIMIT 1'
    ) or sqlerr(__FILE__, __LINE__);

    $arr = mysqli_fetch_assoc($res) ?: httperr();

    $sec = hash_pad($arr['editsecret']);
    if ($sec === '' || preg_match('/^\s*$/', $sec)) httperr();

    $expected = md5($sec . $arr['email'] . $arr['passhash'] . $sec);
    if (!hash_equals($expected, $md5)) httperr();

    // Генерируем читаемый случайный пароль из непохожих символов
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $len = strlen($alphabet);
    $newpassword = '';
    for ($i = 0; $i < 10; $i++) {
        $newpassword .= $alphabet[random_int(0, $len - 1)];
    }

    $newsec      = mksecret();
    $newpasshash = md5($newsec . $newpassword . $newsec);

    sql_query(
        'UPDATE users
           SET secret = ' . sqlesc($newsec) . ',
               editsecret = \'\',
               passhash = ' . sqlesc($newpasshash) . '
         WHERE id = ' . $id . ' AND editsecret = ' . sqlesc($arr['editsecret']) . ' LIMIT 1'
    ) or sqlerr(__FILE__, __LINE__);

    if (mysqli_affected_rows($mysqli) < 1) {
        fail('Невозможно обновить данные. Свяжитесь с администрацией.');
    }

    // Лог
    define('REGISTER', true);
    define('TYPE', 'change_forum_password_admin');
    $userid     = $id;
    $chpassword = $newpassword;
    include_once 'include/community.php';

    $body = <<<EOD
Здравствуйте!

Для вашего аккаунта создан новый пароль.

Пользователь: {$arr['username']}
Пароль:       $newpassword

Войти на сайт: $DEFAULTBASEURL/login.php

--
$SITENAME
EOD;

    $sent = @sent_mail(
        $arr['email'],
        $SITENAME,
        $SITEEMAIL,
        "Новый пароль на $SITENAME",
        $body
    );

    if (!$sent) fail('Не удалось отправить письмо. Сообщите администрации.');

    ok('Новый пароль отправлен на E-mail <b>' . h($arr['email']) . '</b>.');
    exit;
}

/* ===================== ФОРМА (GET без параметров) ===================== */
stdhead('Восстановление пароля');
begin_frame('Восстановление');
?>
<form method="post" action="recover.php">
  <table border="1" cellspacing="0" cellpadding="5">
    <tr>
      <td class="colhead" colspan="2">Восстановление пароля</td>
    </tr>
    <tr>
      <td class="rowhead">E-mail</td>
      <td class="lol">
        <input
          type="email"
          name="email"
          size="40"
          required
          autocomplete="email"
          inputmode="email"
          placeholder="you@example.com">
      </td>
    </tr>
    <tr>
      <td class="colhead" colspan="2" align="center">
        <input type="submit" value="Отправить ссылку для восстановления">
      </td>
    </tr>
  </table>
</form>
<?php
end_frame();
stdfoot();
