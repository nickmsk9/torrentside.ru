<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn();
loggedinorreturn();

function bark(string $msg): void {
    stdhead();
    stdmsg('Ошибка', $msg);
    stdfoot();
    exit;
}

// Кто создаёт инвайт
$actorId  = (int)$CURUSER['id'];
$userClass = (int)get_user_class();

// По умолчанию списываем с актёра; админам можно указать чужой id
$targetId = $actorId;
if ($userClass >= UC_ADMINISTRATOR && isset($_GET['id'])) {
    $tmp = (int)$_GET['id'];
    if ($tmp > 0) $targetId = $tmp;
}

$now = get_date_time();

// Генерация криптостойкого токена
$makeToken = static function (): string {
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable) {
        return hash('sha256', microtime(true) . ':' . mt_rand());
    }
};

// Старт транзакции
sql_query('START TRANSACTION') or sqlerr(__FILE__, __LINE__);

// 1) Лочим строку пользователя и проверяем остаток
$chk = sql_query('SELECT invites FROM users WHERE id = ' . (int)$targetId . ' FOR UPDATE')
    or sqlerr(__FILE__, __LINE__);

$row = is_object($chk) && method_exists($chk, 'fetch_assoc')
    ? $chk->fetch_assoc()
    : ((function($r){ if (function_exists('mysqli_fetch_assoc')) return mysqli_fetch_assoc($r);
                      if (function_exists('mysql_fetch_assoc'))  return mysql_fetch_assoc($r);
                      return null; })($chk));

if (!$row) {
    sql_query('ROLLBACK');
    bark('Пользователь не найден.');
}

$left = (int)$row['invites'];
if ($left <= 0) {
    sql_query('ROLLBACK');
    bark('У вас больше не осталось приглашений! Сейчас на аккаунте ID ' . (int)$targetId . ' доступно приглашений: ' . $left . '.');
}

// 2) Списываем 1 (строка уже под блокировкой, гонок нет)
sql_query('UPDATE users SET invites = invites - 1 WHERE id = ' . (int)$targetId)
    or sqlerr(__FILE__, __LINE__);

// 3) Создаём инвайт (с 1–2 повторами при редкой коллизии уникального индекса)
$maxAttempts = 3;
$ok = false;
for ($i = 0; $i < $maxAttempts; $i++) {
    $hash = $makeToken();
    $ok = sql_query(
        'INSERT INTO invites (inviter, invite, time_invited) VALUES (' .
        (int)$targetId . ', ' . sqlesc($hash) . ', ' . sqlesc($now) . ')'
    );
    if ($ok) break;

    $err = function_exists('mysqli_errno') && isset($GLOBALS['___mysqli_ston'])
        ? (int)mysqli_errno($GLOBALS['___mysqli_ston'])
        : (function_exists('mysql_errno') ? (int)mysql_errno() : 0);

    if ($err !== 1062) { // не дубликат токена — падаем
        sql_query('ROLLBACK');
        sqlerr(__FILE__, __LINE__);
    }
}

if (!$ok) {
    sql_query('ROLLBACK');
    bark('Не удалось создать приглашение. Повторите попытку позже.');
}

// 4) Коммит
sql_query('COMMIT') or sqlerr(__FILE__, __LINE__);

// Готово — редирект
header('Location: invite.php?id=' . (int)$targetId);
exit;
