<?php

if (!defined("ADMIN_FILE")) die("Illegal File Access");

global $mysqli;
$dbname = $mysqli->query("SELECT DATABASE()")->fetch_row()[0];

// Функция для отображения и выполнения оптимизации/ремонта
function StatusDB() {
    global $admin_file, $dbname, $mysqli;

    // Отображение формы
    $result = $mysqli->query("SHOW TABLES FROM `$dbname`");
    $content = '';
    while ($row = $result->fetch_array()) {
        $tbl = htmlspecialchars($row[0]);
        $content .= "<option value=\"$tbl\" selected>$tbl</option>\n";
    }

    echo "<form method='post' action='{$admin_file}.php'>
        <input type='hidden' name='op' value='StatusDB'>
        <table border='0' cellspacing='0' cellpadding='5' align='center'>
        <tr>
            <td>
                <select name='datatable[]' size='12' multiple style='width:400px;'>$content</select>
            </td>
            <td>
                <table border='0' cellpadding='4'>
                    <tr>
                        <td><input type='radio' name='type' value='Optimize' checked></td>
                        <td><b>Оптимизация</b><br /><small>Уменьшает размер таблиц и ускоряет работу</small></td>
                    </tr>
                    <tr>
                        <td><input type='radio' name='type' value='Repair'></td>
                        <td><b>Ремонт</b><br /><small>Исправляет повреждённые таблицы после сбоев</small></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr><td colspan='2' align='center'><input type='submit' value='Выполнить действие'></td></tr>
        </table>
    </form><br />";

    // Обработка действия
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
        $type = $_POST['type'];
        $tables = $_POST['datatable'] ?? [];

        if (!is_array($tables) || count($tables) == 0) {
            echo "<center><b style='color:red;'>Не выбраны таблицы!</b></center>";
            return;
        }

        $i = 0;
        $totalsize = 0;
        $totalfree = 0;
        $report = '';

        foreach ($tables as $table) {
            $table = $mysqli->real_escape_string($table);
            $status = $mysqli->query("SHOW TABLE STATUS LIKE '$table'")->fetch_assoc();
            $size = $status['Data_length'] + $status['Index_length'];
            $free = $status['Data_free'] ?? 0;
            $totalsize += $size;
            $totalfree += $free;
            $i++;

            if ($type === "Optimize") {
                $mysqli->query("OPTIMIZE TABLE `$table`");
                $statusText = $free > 0 ? "<span style='color:green;'>Оптимизирована</span>" : "<span style='color:gray;'>Не требуется</span>";

                $report .= "<tr>
                    <td align='center'>$i</td>
                    <td>$table</td>
                    <td align='right'>" . mksize($size) . "</td>
                    <td align='right'>$statusText</td>
                    <td align='right'>" . mksize($free) . "</td>
                </tr>";

            } elseif ($type === "Repair") {
                $result = $mysqli->query("REPAIR TABLE `$table`")->fetch_assoc();
                $statusText = $result['Msg_text'] === 'OK'
                    ? "<span style='color:green;'>OK</span>"
                    : "<span style='color:red;'>{$result['Msg_text']}</span>";

                $report .= "<tr>
                    <td align='center'>$i</td>
                    <td>$table</td>
                    <td align='right'>" . mksize($size) . "</td>
                    <td align='right'>$statusText</td>
                </tr>";
            }
        }

        // Вывод отчёта
        echo "<center><h3>" . ($type === "Optimize" ? "Оптимизация" : "Ремонт") . " базы данных: <b>$dbname</b></h3>";
        echo "<b>Общий размер:</b> " . mksize($totalsize);
        if ($type === "Optimize") echo " | <b>Накладные:</b> " . mksize($totalfree);
        echo "</center><br />";

        echo "<table border='1' cellspacing='0' cellpadding='4' width='80%' align='center'>
            <tr>
                <th>№</th>
                <th>Таблица</th>
                <th>Размер</th>
                <th>Статус</th>";
        if ($type === "Optimize") echo "<th>Накладные</th>";
        echo "</tr>$report</table>";
    }
}

// Запуск по параметру
$op = $_REQUEST['op'] ?? '';
if ($op === 'StatusDB') {
    StatusDB();
}

