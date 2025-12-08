<?php

require_once("include/bittorrent.php");
dbconn();
loggedinorreturn();

// Заголовок страницы
stdhead("Оптимизация базы данных");
begin_frame("Оптимизация таблиц базы данных");

global $mysqli;

// Обработка POST-запроса на оптимизацию
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['do'] ?? '') === 'optimize' && !empty($_POST['tables'])) {
    echo "<h3 style='color: #007700;'>Результаты оптимизации таблиц:</h3>";
    foreach ($_POST['tables'] as $table) {
        // Защита от инъекций
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $result = sql_query("OPTIMIZE TABLE `$safe_table`") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<div style='padding:5px 0;'><b>{$safe_table}</b>: {$row['Msg_type']} – <span style='color: #005599;'>{$row['Msg_text']}</span></div>";
        }
    }
    echo "<br><a href='admincp.php?op=StatusDB'><b>← Вернуться назад</b></a>";
    end_frame();
    stdfoot();
    die;
}

// Вывод списка таблиц
$res = sql_query("SHOW TABLE STATUS") or sqlerr(__FILE__, __LINE__);

echo <<<HTML
<style>
.dbtable {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.dbtable th, .dbtable td {
    padding: 6px 10px;
    border: 1px solid #cccccc;
    text-align: left;
}
.dbtable th {
    background-color: #e6f0ff;
    color: #003366;
}
.dbtable tr:nth-child(even) {
    background-color: #f9f9f9;
}
input[type="submit"] {
    padding: 8px 16px;
    background: #004E98;
    color: white;
    border: none;
    border-radius: 4px;
    margin-top: 10px;
    cursor: pointer;
}
input[type="submit"]:hover {
    background: #0072cc;
}
</style>
<form method="post" action="admincp.php?op=StatusDB&do=optimize">
<table class="dbtable">
    <tr>
        <th>✓</th>
        <th>Имя таблицы</th>
        <th>Строк</th>
        <th>Размер</th>
        <th>Тип</th>
        <th>Формат хранения</th>
        <th>Комментарий</th>
    </tr>
HTML;

while ($row = mysqli_fetch_assoc($res)) {
    $name = htmlspecialchars($row['Name']);
    $rows = number_format($row['Rows']);
    $size = mksize($row['Data_length'] + $row['Index_length']);
    $engine = htmlspecialchars($row['Engine']);
    $format = htmlspecialchars($row['Row_format']);
    $comment = htmlspecialchars($row['Comment']);

    echo <<<ROW
    <tr>
        <td align="center"><input type="checkbox" name="tables[]" value="$name" checked></td>
        <td><b>$name</b></td>
        <td align="right">$rows</td>
        <td align="right">$size</td>
        <td>$engine</td>
        <td>$format</td>
        <td>$comment</td>
    </tr>
ROW;
}

echo <<<HTML
</table><br>
<input type="submit" value="Оптимизировать выбранные таблицы">
</form>
HTML;

end_frame();
stdfoot();
?>
