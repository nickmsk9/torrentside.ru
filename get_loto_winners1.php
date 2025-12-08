<?php
require_once "include/bittorrent.php"; // подключаем движок
dbconn(); // соединение с базой

global $mysqli;

// === ЛОГ ===
$logdir  = __DIR__ . "/logs";
$logfile = $logdir . "/super_loto_cron.log";
if (!is_dir($logdir)) { @mkdir($logdir, 0775, true); }

function loto_log(string $text): void {
    global $logfile;
    $time = date("[Y-m-d H:i:s]");
    @file_put_contents($logfile, "$time $text\n", FILE_APPEND);
}

// === Условия запуска: воскресенье после 18:00 ===
$day  = (int)date('N'); // 1..7, где 7 = воскресенье
$hour = (int)date('H'); // 00..23

if ($day !== 7 || $hour < 18) {
    loto_log("Сейчас не время розыгрыша (день=$day, время={$hour}:00). Выход.");
    return;
}

// === Призовые коэффициенты (множитель к ставке в GB) ===
$prize = [
    1 => 1,
    2 => 2,
    3 => 3,
    4 => 5,
    5 => 10,
    6 => 50 // джекпот: все 5 по порядку
];

// === Генерируем 5 уникальных выигрышных чисел (1..36) ===
$win_numbers = [];
while (count($win_numbers) < 5) {
    $n = random_int(1, 36);
    if (!in_array($n, $win_numbers, true)) {
        $win_numbers[] = $n;
    }
}
loto_log("Выпавшие номера: " . implode(', ', $win_numbers));

// === Получаем все несыгранные билеты (active=0) ===
$q = "SELECT ticket_id, user_id, combination, price
      FROM super_loto_tickets
      WHERE active = 0";
$res = $mysqli->query($q);
if (!$res) {
    loto_log("Ошибка запроса билетов: " . $mysqli->error);
    return;
}

$winners = [];
$players_cnt = 0;

if ($res->num_rows > 0) {
    while ($t = $res->fetch_assoc()) {
        $players_cnt++;

        // Нормализация комбинации билета
        $raw = (string)$t['combination'];
        $parts = explode('.', $raw);
        $nums  = [];
        foreach ($parts as $p) {
            $n = (int)$p;
            if ($n < 1 || $n > 36) { $nums = []; break; }
            $nums[] = $n;
        }
        if (count($nums) !== 5) {
            loto_log("Билет #{$t['ticket_id']} у пользователя {$t['user_id']} имеет неверный формат комбинации: {$raw}");
            continue;
        }

        // Совпадения без порядка
        $win_num = 0;
        $hits    = [];
        for ($i = 0; $i < 5; $i++) {
            if (in_array($nums[$i], $win_numbers, true)) {
                $win_num++;
                $hits[] = $nums[$i];
            }
        }

        // Джекпот — полное совпадение по позициям
        $jackpot = 1;
        for ($i = 0; $i < 5; $i++) { // FIX: раньше было <= 5
            if ($nums[$i] !== $win_numbers[$i]) {
                $jackpot = 0;
                break;
            }
        }

        if ($win_num > 0) {
            $winners[] = [
                'user_id'     => (int)$t['user_id'],
                'combination' => implode('.', $nums),      // нормализованная
                'price'       => (int)$t['price'],
                'win_num'     => $win_num,
                'numbers'     => $hits,
                'jackpot'     => $jackpot
            ];
        }
    }
} else {
    loto_log("Нет несыгранных билетов (active=0).");
}

// === Подготавливаем выражения для начислений/вставок ===
$updUpload = $mysqli->prepare("UPDATE users SET uploaded = uploaded + ? WHERE id = ?");
$insWinner = $mysqli->prepare("
    INSERT INTO super_loto_winners
        (user_id, price, combination, win_num, numbers, jackpot, win_combination, date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$insMsg = $mysqli->prepare("
    INSERT INTO messages (sender, receiver, added, subject, msg, unread, location, saved)
    VALUES (0, ?, ?, ?, ?, 'yes', 1, 'no')
");

$win_comb_str = implode('.', $win_numbers);
$todayDate    = date('Y-m-d');
$nowDT        = date('Y-m-d H:i:s');

// === Обрабатываем выигрыши ===
$wcnt = 0;
foreach ($winners as $w) {
    $amount    = ($w['jackpot'] == 1) ? 6 : $w['win_num'];      // ключ в $prize
    $mult      = $prize[$amount] ?? 0;
    $win_prize = $w['price'] * $mult;                           // в GB
    $bytes     = (int)$win_prize * 1024 * 1024 * 1024;          // FIX: 1024^3

    if ($mult > 0 && $bytes > 0) {
        $updUpload->bind_param('ii', $bytes, $w['user_id']);
        if ($updUpload->execute()) {
            loto_log("Победа user_id={$w['user_id']}: +{$win_prize} GB ({$bytes} bytes), совпадений={$w['win_num']}, джекпот={$w['jackpot']}.");
        } else {
            loto_log("Ошибка начисления трафика user_id={$w['user_id']}: " . $mysqli->error);
        }
    } else {
        loto_log("Нет приза (mult=0): user_id={$w['user_id']}, совпадений={$w['win_num']}, джекпот={$w['jackpot']}.");
    }

    // Записываем победителя
    $numbers_str = implode('.', $w['numbers']);
    $insWinner->bind_param(
        'iisissss',
        $w['user_id'],
        $w['price'],
        $w['combination'],
        $w['win_num'],
        $numbers_str,
        $w['jackpot'],
        $win_comb_str,
        $todayDate
    );
    if (!$insWinner->execute()) {
        loto_log("Ошибка записи победителя (user_id={$w['user_id']}): " . $mysqli->error);
    }

    // ЛС победителю
    $combSpaces = str_replace('.', ' ', $w['combination']);
    $winSpaces  = str_replace('.', ' ', $win_comb_str);
    $hitSpaces  = str_replace('.', ' ', $numbers_str);

    $subject = 'Вы выиграли в Супер Лото!';
    $message = "[b]Поздравляем![/b]\n\n"
             . "Вы выиграли в Супер Лото (5 из 36) [b]{$win_prize} GB[/b] к раздаче.\n"
             . "Вы угадали {$w['win_num']} из 5 номеров.\n"
             . "Ваш билет: [b]{$combSpaces}[/b]\n\n"
             . "Выпала комбинация: [b]{$winSpaces}[/b]\n"
             . "Совпали: [b]{$hitSpaces}[/b]\n\n"
             . "[b]Играйте ещё! Удачи![/b]";

    $insMsg->bind_param('isss', $w['user_id'], $nowDT, $subject, $message);
    if (!$insMsg->execute()) {
        loto_log("Ошибка отправки ЛС user_id={$w['user_id']}: " . $mysqli->error);
    }

    $wcnt++;
}

// === Завершаем розыгрыш: закрываем билеты и сбрасываем флаг ===
$mysqli->query("UPDATE super_loto_tickets SET active = 1, game_date = '{$todayDate}' WHERE active = 0");

// Семантика: 0 — игра не активна/завершена
$mysqli->query("UPDATE config SET value = 0 WHERE config = 'active_super_loto'");

loto_log("Розыгрыш завершён. Участников: {$players_cnt}, победителей: {$wcnt}. Билеты помечены как сыгранные, флаг активной игры = 0.");
