<?php
require_once "include/bittorrent.php";
dbconn();

global $mysqli;

// === ЛОГ ===
$logdir  = __DIR__ . "/logs";
$logfile = $logdir . "/super_loto.log";
if (!is_dir($logdir)) { @mkdir($logdir, 0775, true); }

function loto_log(string $text): void {
    global $logfile;
    $time = date("[Y-m-d H:i:s]");
    @file_put_contents($logfile, "$time $text\n", FILE_APPEND);
}

// Призовые коэффициенты (в X от ставки в GB)
$prize = [
    1 => 1,
    2 => 2,
    3 => 3,
    4 => 5,
    5 => 10,
    6 => 50, // джекпот (все 5 по порядку): используем ключ 6
];

// === Генерируем 5 уникальных выигрышных чисел 1..36 ===
$win_numbers = [];
while (count($win_numbers) < 5) {
    $n = random_int(1, 36);
    if (!in_array($n, $win_numbers, true)) {
        $win_numbers[] = $n;
    }
}
loto_log("Выпавшие номера: " . implode(', ', $win_numbers));

// Берём все ещё-НЕсыгранные билеты (active = 0)
$sql = "SELECT ticket_id, user_id, combination, price FROM super_loto_tickets WHERE active = 0";
$result = $mysqli->query($sql);

$winners = [];
$losers  = [];

if ($result && $result->num_rows > 0) {
    while ($ticket = $result->fetch_assoc()) {
        // Безопасно разбираем комбинацию
        $ticket_numbers = explode('.', (string)$ticket['combination']);
        // Нормализуем и проверяем — должно быть ровно 5 позиций 1..36
        $nums = [];
        foreach ($ticket_numbers as $idx => $val) {
            $n = (int)$val;
            if ($n < 1 || $n > 36) { $nums = []; break; }
            $nums[] = $n;
        }
        if (count($nums) !== 5) {
            loto_log("Билет #{$ticket['ticket_id']} у пользователя {$ticket['user_id']} имеет неверный формат комбинации: {$ticket['combination']}");
            $losers[] = (int)$ticket['user_id'];
            continue;
        }

        // Подсчёт совпадений (без порядка)
        $win_num = 0;
        $hit     = [];
        for ($i = 0; $i < 5; $i++) {
            if (in_array($nums[$i], $win_numbers, true)) {
                $win_num++;
                $hit[] = $nums[$i];
            }
        }

        // Джекпот — полное совпадение по позиции
        $jackpot = 1;
        for ($i = 0; $i < 5; $i++) { // FIX: раньше было <= 5
            if ($nums[$i] !== $win_numbers[$i]) {
                $jackpot = 0;
                break;
            }
        }

        if ($win_num > 0) {
            $winners[] = [
                'user_id'     => (int)$ticket['user_id'],
                'combination' => implode('.', $nums), // нормализованная
                'price'       => (int)$ticket['price'],
                'win_num'     => $win_num,
                'numbers'     => $hit,
                'jackpot'     => $jackpot,
            ];
        } else {
            $losers[] = (int)$ticket['user_id'];
        }
    }
} else {
    loto_log("Нет несыгранных билетов (active=0).");
}

// === Обработка выигрышей ===
// Подготовим выражения заранее
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

foreach ($winners as $w) {
    // Определяем мультипликатор: джекпот -> 6, иначе кол-во совпадений
    $amount    = ($w['jackpot'] == 1) ? 6 : $w['win_num'];
    $mult      = $prize[$amount] ?? 0;
    $win_prize = $w['price'] * $mult; // в GB
    $bytes     = (int)($win_prize) * 1024 * 1024 * 1024; // FIX: 1024^3

    // Начисляем upload
    if ($mult > 0 && $bytes > 0) {
        $updUpload->bind_param('ii', $bytes, $w['user_id']);
        if ($updUpload->execute() && $updUpload->affected_rows >= 0) {
            loto_log("Начислено пользователю ID {$w['user_id']}: +{$win_prize} GB ({$bytes} bytes), совпадений={$w['win_num']}, джекпот={$w['jackpot']}.");
        } else {
            loto_log("Ошибка начисления трафика пользователю ID {$w['user_id']}: " . $mysqli->error);
        }
    } else {
        loto_log("Призовой множитель 0: пользователь {$w['user_id']}, совпадений={$w['win_num']}, джекпот={$w['jackpot']}.");
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

    // Отправляем ЛС
    $combSpaces = str_replace('.', ' ', $w['combination']);
    $winSpaces  = str_replace('.', ' ', $win_comb_str);
    $hitSpaces  = str_replace('.', ' ', $numbers_str);

    $subject = 'Вы выиграли в Супер Лото!';
    $message = "[b]Поздравляем!![/b]\n\n"
             . "Вы выиграли в Супер Лото (5 из 36) [b]{$win_prize} GB[/b] к раздачи.\n"
             . "Вы угадали {$w['win_num']} из 5 номера(ов).\n"
             . "Комбинация вашего билета: [b]{$combSpaces}[/b]\n\n"
             . "Выпавшая комбинация: [b]{$winSpaces}[/b]\n"
             . "Совпавшие номера: [b]{$hitSpaces}[/b]\n\n"
             . "[b]Играйте ещё! Удачи! ;)[/b]";

    $insMsg->bind_param('isss', $w['user_id'], $nowDT, $subject, $message);
    if (!$insMsg->execute()) {
        loto_log("Ошибка отправки ЛС пользователю ID {$w['user_id']}: " . $mysqli->error);
    }
}

// === Завершение игры ===
// Помечаем все текущие билеты как «сыгранные»
$mysqli->query("UPDATE super_loto_tickets SET active = 1, game_date = '{$todayDate}' WHERE active = 0");

// Флаг в config (если нужен)
$mysqli->query("UPDATE config SET value = 1 WHERE config = 'active_super_loto'");

loto_log("Игра завершена. Помечено билетов как сыгранных: " . $mysqli->affected_rows . ". Флаг обновлён.");
