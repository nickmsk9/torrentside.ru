<?php
require "include/bittorrent.php";
dbconn();

header("Content-Type: text/html; charset=utf-8");

global $mysqli, $memcached, $CURUSER;

$do     = $_POST["action"]  ?? "";
$choice = isset($_POST["choice"]) ? (int)$_POST["choice"] : 0;
$pollId = isset($_POST["pollId"]) ? (int)$_POST["pollId"] : 0;
$userId = isset($CURUSER["id"])   ? (int)$CURUSER["id"]   : 0;

if ($do === "load") {
    // --- 1) Последний опрос + мой выбор (одним запросом)
    $sql = "
        SELECT p.*, pa.selection AS user_selection
        FROM polls AS p
        LEFT JOIN pollanswers AS pa
               ON pa.pollid = p.id AND pa.userid = ?
        ORDER BY p.id DESC
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $poll = $stmt->get_result()->fetch_assoc();

    if (!$poll) {
        print("Нет текущих опросов.");
        exit;
    }

    $pid = (int)$poll['id'];

    // --- 2) Массив вариантов (option0..option19) в исходном виде
    $rawOptions = [];
    for ($i = 0; $i < 20; $i++) {
        $k = "option{$i}";
        if (!empty($poll[$k])) {
            $rawOptions[$i] = (string)$poll[$k];
        }
    }

    // --- 3) Кнопки модератора (как было)
    $modop = '';
    if (get_user_class() >= UC_MODERATOR) {
        $editUrl   = "makepoll.php?action=edit&pollid={$pid}";
        $deleteUrl = "makepoll.php?action=delete&pollid={$pid}";
        $modop  .= "<a href=\"{$editUrl}\"><img src=\"pic/warned1.gif\" width=\"12\" title=\"Редактировать опрос\" border=\"0\"></a>";
        $modop  .= "<a href=\"{$deleteUrl}\"><img src=\"pic/warned2.gif\" width=\"12\" title=\"Удалить опрос\" border=\"0\"></a>";
    }

    // Заголовок
    print(
        '<div id="poll_title">' .
        format_comment($poll['question']) .
        ($modop ? '&nbsp;[' . $modop . ']&nbsp;' : '') .
        "</div>\n"
    );

    // --- 4a) Если не голосовал — показываем варианты
    if ($poll['user_selection'] === null) {
        foreach ($rawOptions as $opId => $opVal) {
            // форматируем в момент вывода
            $label = format_comment($opVal);
            echo '<div align="left">'
               . '<input type="radio" onclick="addvote(' . $opId . ')" name="choices" value="' . $opId . '" id="opt_' . $opId . '"/>'
               . '<label for="opt_' . $opId . '">&nbsp;' . $label . "</label></div>\n";
        }
        echo '<input type="hidden" name="choice" id="choice" value=""/>';
        echo '<input type="hidden" name="pollId" id="pollId" value="' . $pid . '"/>';
        echo '<div align="center"><input type="button" value="Голосовать" style="display:none;" id="vote_b" onclick="vote();"/></div>';
        exit;
    }

    // --- 4b) Уже голосовал — выводим результаты
    // Попробуем взять кеш (на 1 минуту). Ключ зависит только от pollId.
    $counts = null;
    if (isset($memcached) && $memcached instanceof Memcached) {
        $counts = $memcached->get("poll:counts:{$pid}");
    }

    if (!is_array($counts)) {
        $stmt2 = $mysqli->prepare("
            SELECT selection, COUNT(*) AS c
            FROM pollanswers
            WHERE pollid = ? AND selection BETWEEN 0 AND 19
            GROUP BY selection
        ");
        $stmt2->bind_param("i", $pid);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        $counts = [];
        while ($row = $res2->fetch_assoc()) {
            $counts[(int)$row['selection']] = (int)$row['c'];
        }

        if (isset($memcached) && $memcached instanceof Memcached) {
            $memcached->set("poll:counts:{$pid}", $counts, 60);
        }
    }

    // Собираем результаты в виде [[голоса, метка], ...]
    $total   = 0;
    $results = [];
    foreach ($rawOptions as $k => $labelRaw) {
        $v = $counts[$k] ?? 0;
        $results[] = [$v, format_comment($labelRaw)];
        $total += $v;
    }

    // Сортируем по убыванию голосов, стабильная сортировка
    usort($results, static function ($a, $b) {
        return $b[0] <=> $a[0];
    });

    // Таблица (плотная: без внутренних паддингов — зазоры минимальны)
    echo '<table id="results" class="results" width="100%" cellspacing="0" cellpadding="0" style="border:none">';

    $i = 0;
    foreach ($results as $idx => $result) {
        $votes   = (int)$result[0];
        $label   = htmlspecialchars((string)$result[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $percent = ($total > 0) ? ($votes * 100.0 / $total) : 0.0;

        // 0 / 0.5 / 12 / 12.3 / 12.34
        $pct_str = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        $barClass = ($i === 0) ? 'bar barmax' : 'bar';
        $barId    = 'poll_result_' . $idx;

        echo "<tr>\n";
        echo '  <td class="lol" align="left" width="40%" style="border:none;padding:0 6px 0 0;">' . $label . "</td>\n";
        echo '  <td class="lol" align="left" width="60%" valign="middle" style="border:none;padding:0;">'
           .  '    <div id="' . $barId . '" class="' . $barClass . '" '
           .  '         style="width:' . $pct_str . '%; --w:' . $pct_str . '%; height:18px; line-height:18px; margin:0;" '
           .  '         role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct_str . '" '
           .  '         title="' . $pct_str . '%"></div>'
           .  "</td>\n";
        echo '  <td class="lol" style="border:none;white-space:nowrap;padding:0 0 0 6px;"><b>' . $pct_str . "%</b></td>\n";
        echo "</tr>\n";

        $i++;
    }

    echo "</table>\n";
    echo '<div class="poll-total" style="text-align:center;"><b>Голосов</b>: ' . (int)$total . "</div>\n";
    exit;
}

// ------------------------ VOTE ------------------------
if ($do === "vote") {
    header("Content-Type: application/json; charset=utf-8");

    if ($pollId <= 0 || $userId <= 0) {
        echo json_encode(["status" => 0, "msg" => "Некорректные параметры."]);
        exit;
    }

    // Проверим, голосовал ли уже
    $stmt = $mysqli->prepare("SELECT 1 FROM pollanswers WHERE pollid = ? AND userid = ? LIMIT 1");
    $stmt->bind_param("ii", $pollId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) {
        echo json_encode(["status" => 0, "msg" => "Вы уже голосовали."]);
        exit;
    }

    // Проверим валидность choice (вариант должен существовать)
    $stmt2 = $mysqli->prepare("SELECT * FROM polls WHERE id = ? LIMIT 1");
    $stmt2->bind_param("i", $pollId);
    $stmt2->execute();
    $pollRow = $stmt2->get_result()->fetch_assoc();

    if (!$pollRow) {
        echo json_encode(["status" => 0, "msg" => "Опрос не найден."]);
        exit;
    }

    if ($choice < 0 || $choice > 19 || empty($pollRow['option' . $choice])) {
        echo json_encode(["status" => 0, "msg" => "Некорректный вариант ответа."]);
        exit;
    }

    // Запись голоса
    $stmt3 = $mysqli->prepare("INSERT INTO pollanswers (pollid, userid, selection) VALUES (?, ?, ?)");
    $stmt3->bind_param("iii", $pollId, $userId, $choice);

    if ($stmt3->execute()) {
        // Инвалидируем кеш счётчиков
        if (isset($memcached) && $memcached instanceof Memcached) {
            $memcached->delete("poll:counts:{$pollId}");
        }
        echo json_encode(["status" => 1]);
    } else {
        echo json_encode(["status" => 0, "msg" => "Ошибка при записи голоса."]);
    }
    exit;
}

// если экшн не распознан
echo "Некорректное действие.";
