<?php
require_once 'include/bittorrent.php';
dbconn(false);
loggedinorreturn();

global $CURUSER, $mysqli;

// HTML-экранирование
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Пределы ставки (GB)
const PRICE_MIN_GB = 1;
const PRICE_MAX_GB = 1024;

$action = $_POST['action'] ?? '';

/**
 * Нормализуем и валидируем комбинацию формата "a.b.c.d.e"
 * Требования:
 *  - ровно 5 чисел
 *  - каждое число в диапазоне 1..36
 *  - все числа уникальны
 * Порядок сохраняем таким, как выбрал пользователь.
 * Возвращает "a.b.c.d.e" или '' при ошибке.
 */
function normalize_combination(?string $raw): string {
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
    foreach ($parts as $p) {
        $n = (int)$p;
        if ($n < 1 || $n > 36) { // диапазон игры 1..36
            return '';
        }
        if (isset($seen[$n])) {   // уникальность
            return '';
        }
        $seen[$n] = true;
        $nums[] = (string)$n;     // убираем лидирующие нули
    }
    return implode('.', $nums);
}

if ($action === 'bay_ticket') {
    $user_id     = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
    $combination = normalize_combination($_POST['combination'] ?? '');
    $priceGB     = (int)($_POST['price'] ?? 0);

    if ($user_id <= 0) {
        echo 'Ошибка: пользователь не найден.';
        exit;
    }
    if ($combination === '') {
        echo 'Ошибка: неверная комбинация (нужно 5 уникальных чисел от 1 до 36).';
        exit;
    }
    if ($priceGB < PRICE_MIN_GB || $priceGB > PRICE_MAX_GB) {
        echo 'Ошибка: недопустимая ставка.';
        exit;
    }

    $bytes = 1024 * 1024 * 1024 * $priceGB;

    // Транзакция: блокируем баланс, вставляем билет active=0, списываем объём
    $mysqli->begin_transaction();
    try {
        // 1) баланс с блокировкой
        $stmt = $mysqli->prepare('SELECT uploaded FROM users WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($uploaded);
        $ok = $stmt->fetch();
        $stmt->close();

        if (!$ok) {
            throw new RuntimeException('Пользователь не найден.');
        }
        if ((int)$uploaded < $bytes) {
            $mysqli->rollback();
            echo 'Неудачно! Недостаточно раздачи для покупки билета на сумму ' . $h($priceGB) . ' GB.';
            exit;
        }

        // (Опционально) запрет дубликатов для активных билетов:
        // $stmt = $mysqli->prepare('SELECT 1 FROM super_loto_tickets WHERE user_id = ? AND combination = ? AND active = 0 LIMIT 1');
        // $stmt->bind_param('is', $user_id, $combination);
        // $stmt->execute();
        // $stmt->store_result();
        // if ($stmt->num_rows > 0) { $stmt->close(); $mysqli->rollback(); echo 'Такая комбинация у вас уже есть.'; exit; }
        // $stmt->close();

        // 2) создаём билет (active = 0, чтобы он отображался в «Ваши билеты»)
        $stmt = $mysqli->prepare('
            INSERT INTO super_loto_tickets (user_id, combination, price, active)
            VALUES (?, ?, ?, 0)
        ');
        $stmt->bind_param('isi', $user_id, $combination, $priceGB);
        if (!$stmt->execute()) {
            throw new RuntimeException('Ошибка при сохранении билета.');
        }
        $stmt->close();

        // 3) списываем объём
        $stmt = $mysqli->prepare('UPDATE users SET uploaded = uploaded - ? WHERE id = ?');
        $stmt->bind_param('ii', $bytes, $user_id);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            throw new RuntimeException('Не удалось списать средства.');
        }
        $stmt->close();

        $mysqli->commit();

        $numbers = explode('.', $combination);
        echo 'Удачно! Вы купили билет с комбинацией <b>' . $h(implode(' ', $numbers)) . '</b>&nbsp;
              <input type="button" value="Купить ещё" onclick="show_bay_form()">';
    } catch (Throwable $e) {
        $mysqli->rollback();
        echo 'Ошибка: ' . $h($e->getMessage());
    }
}
// ———————————————————————————————————————————————————————
elseif ($action === 'load_tickets') {
    $user_id = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
    if ($user_id <= 0) {
        exit;
    }

    // При необходимости добавьте пагинацию
    $stmt = $mysqli->prepare('
        SELECT ticket_id, combination, price
        FROM super_loto_tickets
        WHERE user_id = ? AND active = 0
        ORDER BY ticket_id DESC
        LIMIT 500
    ');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    echo '<table width="90%">
            <tr height="30">
                <td align="center" width="10%" class="colhead"><b>Nr.</b></td>
                <td align="center" width="40%" class="colhead"><b>Комбинация</b></td>
                <td align="center" width="30%" class="colhead"><b>Ставка</b></td>
            </tr>';

    $i = 0;
    while ($t = $res->fetch_assoc()) {
        $i++;
        $nums = explode('.', (string)$t['combination']);
        // На всякий случай гарантируем 5 ячеек
        for ($k = 0; $k < 5; $k++) {
            if (!isset($nums[$k])) $nums[$k] = '';
        }

        echo '<tr>
                <td align="center"><b>' . $i . '</b></td>
                <td align="center">
                    <table><tr>';
        for ($k = 0; $k < 5; $k++) {
            echo '<td width="14" height="19" style="padding:4" align="center" background="pic/super_loto/bg.png">' . $h($nums[$k]) . '</td>';
        }
        echo        '</tr></table>
                </td>
                <td align="center">' . $h((int)$t['price']) . ' GB</td>
              </tr>';
    }
    echo '</table>';
    $stmt->close();
}
// ———————————————————————————————————————————————————————
elseif ($action === 'load_stats') {
    // Агрегат по активным билетам
    $sql = '
        SELECT
            t.user_id,
            COUNT(*)                    AS tickets_cnt,
            COALESCE(SUM(t.price), 0)   AS total_price,
            u.username,
            u.class
        FROM super_loto_tickets t
        JOIN users u ON u.id = t.user_id
        WHERE t.active = 0
        GROUP BY t.user_id, u.username, u.class
        ORDER BY total_price DESC, tickets_cnt DESC
    ';
    $res = sql_query($sql);

    if ($res && mysqli_num_rows($res) > 0) {
        echo '<tr height="30">
                <td align="center" width="10%" class="colhead"><b>Nr.</b></td>
                <td align="center" width="40%" class="colhead"><b>Пользователь</b></td>
                <td align="center" width="30%" class="colhead"><b>Куплено билетов</b></td>
                <td align="center" width="20%" class="colhead"><b>Общая ставка</b></td>
              </tr>';

        $i = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $i++;
            $uid   = (int)$row['user_id'];
            $uname = (string)$row['username'];
            $class = (int)$row['class'];

            // Цветной ник, если есть хелпер
            if (function_exists('get_user_class_color')) {
                $nameHtml = get_user_class_color($class, $uname);
            } else {
                $nameHtml = $h($uname);
            }

            echo '<tr>
                    <td align="center"><b>' . $i . '</b></td>
                    <td align="center"><a href="userdetails.php?id=' . $uid . '">' . $nameHtml . '</a></td>
                    <td align="center">' . $h((int)$row['tickets_cnt']) . '</td>
                    <td align="center">' . $h((int)$row['total_price']) . ' GB</td>
                  </tr>';
        }
    }
}
