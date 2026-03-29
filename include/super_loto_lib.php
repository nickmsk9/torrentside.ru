<?php
declare(strict_types=1);

if (!function_exists('super_loto_csrf_token')) {
    function super_loto_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)($_SESSION['csrf_token'] ?? '');
    }
}

if (!function_exists('super_loto_verify_csrf')) {
    function super_loto_verify_csrf(?string $token): bool
    {
        $expected = (string)($_SESSION['csrf_token'] ?? '');
        $token = (string)$token;
        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }
}

if (!function_exists('super_loto_normalize_combination')) {
    function super_loto_normalize_combination(?string $raw): string
    {
        $raw = trim((string)$raw);
        if (!preg_match('/^\d{1,2}(?:\.\d{1,2}){4}$/', $raw)) {
            return '';
        }
        $parts = explode('.', $raw);
        if (count($parts) !== 5) {
            return '';
        }
        $seen = [];
        $nums = [];
        foreach ($parts as $part) {
            $num = (int)$part;
            if ($num < 1 || $num > 36 || isset($seen[$num])) {
                return '';
            }
            $seen[$num] = true;
            $nums[] = (string)$num;
        }
        return implode('.', $nums);
    }
}

if (!function_exists('super_loto_cfg_active')) {
    function super_loto_cfg_active(): int
    {
        $res = sql_query("SELECT value FROM config WHERE config = 'active_super_loto' LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_assoc($res);
        return (int)($row['value'] ?? 0);
    }
}

if (!function_exists('super_loto_active_tickets_count')) {
    function super_loto_active_tickets_count(): int
    {
        $res = sql_query("SELECT COUNT(*) AS c FROM super_loto_tickets WHERE active = 0") or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_assoc($res);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('super_loto_open_log')) {
    function super_loto_open_log(string $filename): string
    {
        $logdir = __DIR__ . '/../logs';
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0775, true);
        }
        return $logdir . '/' . $filename;
    }
}

if (!function_exists('super_loto_write_log')) {
    function super_loto_write_log(string $filename, string $text): void
    {
        $path = super_loto_open_log($filename);
        $time = date('[Y-m-d H:i:s]');
        @file_put_contents($path, $time . ' ' . $text . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('super_loto_payout_map')) {
    function super_loto_payout_map(): array
    {
        return [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 5,
            5 => 10,
            6 => 50,
        ];
    }
}

if (!function_exists('super_loto_generate_win_numbers')) {
    function super_loto_generate_win_numbers(): array
    {
        $numbers = [];
        while (count($numbers) < 5) {
            $n = random_int(1, 36);
            if (!in_array($n, $numbers, true)) {
                $numbers[] = $n;
            }
        }
        return $numbers;
    }
}

if (!function_exists('super_loto_run_draw')) {
    function super_loto_run_draw(array $opts = []): array
    {
        global $mysqli;

        $strictSchedule = !empty($opts['strict_schedule']);
        $logFile = (string)($opts['log_file'] ?? 'super_loto.log');

        if ($strictSchedule) {
            $day = (int)date('N');
            $hour = (int)date('H');
            if ($day !== 7 || $hour < 18) {
                super_loto_write_log($logFile, "Сейчас не время розыгрыша (день={$day}, время={$hour}:00).");
                return ['ok' => false, 'message' => 'Сейчас не время розыгрыша.'];
            }
        }

        $today = date('Y-m-d');
        $lockName = 'super_loto_draw_lock';
        $lockRes = $mysqli->query("SELECT GET_LOCK('" . $mysqli->real_escape_string($lockName) . "', 3) AS lck");
        $lockRow = $lockRes ? $lockRes->fetch_assoc() : null;
        if ((int)($lockRow['lck'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Розыгрыш уже выполняется другим процессом.'];
        }

        try {
            $alreadyRes = $mysqli->query("SELECT COUNT(*) AS c FROM super_loto_winners WHERE date = '{$today}'");
            $alreadyRow = $alreadyRes ? $alreadyRes->fetch_assoc() : ['c' => 0];
            if ((int)($alreadyRow['c'] ?? 0) > 0) {
                return ['ok' => false, 'message' => 'Розыгрыш за сегодня уже проведён.'];
            }

            $tickets = [];
            $res = $mysqli->query("
                SELECT ticket_id, user_id, combination, price
                FROM super_loto_tickets
                WHERE active = 0
                ORDER BY ticket_id ASC
            ");
            while ($res && ($row = $res->fetch_assoc())) {
                $combination = super_loto_normalize_combination((string)($row['combination'] ?? ''));
                if ($combination === '') {
                    super_loto_write_log($logFile, "Билет #{$row['ticket_id']} имеет неверную комбинацию: " . (string)$row['combination']);
                    continue;
                }
                $row['combination'] = $combination;
                $tickets[] = $row;
            }

            if (!$tickets) {
                super_loto_write_log($logFile, 'Нет активных билетов для розыгрыша.');
                return ['ok' => false, 'message' => 'Нет активных билетов для розыгрыша.'];
            }

            $prizeMap = super_loto_payout_map();
            $winNumbers = super_loto_generate_win_numbers();
            $winCombination = implode('.', $winNumbers);
            super_loto_write_log($logFile, 'Выпавшие номера: ' . implode(', ', $winNumbers));

            $winners = [];
            foreach ($tickets as $ticket) {
                $nums = array_map('intval', explode('.', (string)$ticket['combination']));
                $hits = [];
                foreach ($nums as $num) {
                    if (in_array($num, $winNumbers, true)) {
                        $hits[] = $num;
                    }
                }
                $jackpot = ($nums === $winNumbers) ? 1 : 0;
                $winNum = count($hits);
                if ($winNum <= 0) {
                    continue;
                }
                $winners[] = [
                    'user_id' => (int)$ticket['user_id'],
                    'price' => (int)$ticket['price'],
                    'combination' => (string)$ticket['combination'],
                    'win_num' => $winNum,
                    'numbers' => implode('.', $hits),
                    'jackpot' => $jackpot,
                ];
            }

            $mysqli->begin_transaction();

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

            $winnersCount = 0;
            $playersCount = count($tickets);
            $nowDt = date('Y-m-d H:i:s');

            foreach ($winners as $winner) {
                $amountKey = $winner['jackpot'] === 1 ? 6 : $winner['win_num'];
                $mult = (int)($prizeMap[$amountKey] ?? 0);
                $winPrize = (int)$winner['price'] * $mult;
                $bytes = $winPrize * 1024 * 1024 * 1024;

                if ($mult > 0 && $bytes > 0) {
                    $updUpload->bind_param('ii', $bytes, $winner['user_id']);
                    if (!$updUpload->execute()) {
                        throw new RuntimeException('Ошибка начисления выигрыша пользователю #' . $winner['user_id']);
                    }
                }

                $insWinner->bind_param(
                    'iisissss',
                    $winner['user_id'],
                    $winner['price'],
                    $winner['combination'],
                    $winner['win_num'],
                    $winner['numbers'],
                    $winner['jackpot'],
                    $winCombination,
                    $today
                );
                if (!$insWinner->execute()) {
                    throw new RuntimeException('Ошибка записи победителя #' . $winner['user_id']);
                }

                $subject = 'Вы выиграли в Супер Лото!';
                $message = "[b]Поздравляем![/b]\n\n"
                    . "Вы выиграли в Супер Лото (5 из 36) [b]{$winPrize} GB[/b] к раздаче.\n"
                    . "Вы угадали {$winner['win_num']} из 5 номеров.\n"
                    . "Ваш билет: [b]" . str_replace('.', ' ', $winner['combination']) . "[/b]\n\n"
                    . "Выпала комбинация: [b]" . str_replace('.', ' ', $winCombination) . "[/b]\n"
                    . "Совпали: [b]" . str_replace('.', ' ', $winner['numbers']) . "[/b]\n\n"
                    . "[b]Играйте ещё! Удачи![/b]";
                $insMsg->bind_param('isss', $winner['user_id'], $nowDt, $subject, $message);
                $insMsg->execute();

                super_loto_write_log($logFile, "Победа user_id={$winner['user_id']}: +{$winPrize} GB, совпадений={$winner['win_num']}, джекпот={$winner['jackpot']}.");
                $winnersCount++;
            }

            if (!$mysqli->query("UPDATE super_loto_tickets SET active = 1, game_date = '{$today}' WHERE active = 0")) {
                throw new RuntimeException('Не удалось закрыть сыгранные билеты.');
            }

            if (!$mysqli->query("UPDATE config SET value = 0 WHERE config = 'active_super_loto'")) {
                throw new RuntimeException('Не удалось обновить флаг active_super_loto.');
            }

            $mysqli->commit();

            super_loto_write_log($logFile, "Розыгрыш завершён. Участников: {$playersCount}, победителей: {$winnersCount}.");
            return [
                'ok' => true,
                'message' => 'Розыгрыш успешно завершён.',
                'players' => $playersCount,
                'winners' => $winnersCount,
                'win_numbers' => $winNumbers,
            ];
        } catch (Throwable $e) {
            @$mysqli->rollback();
            super_loto_write_log($logFile, 'Ошибка розыгрыша: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            $mysqli->query("DO RELEASE_LOCK('" . $mysqli->real_escape_string($lockName) . "')");
        }
    }
}
