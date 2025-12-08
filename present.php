<?php

require_once("include/bittorrent.php");

// Подключаемся к базе
dbconn();

// Заголовок контента
header("Content-Type: text/html; charset=" . $tracker_lang['language_charset']);

// Проверка, что это AJAX-запрос методом POST
if (
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
    && $_SERVER["REQUEST_METHOD"] === 'POST'
) {
    // Получаем и фильтруем входные значения
    $from = (int)($_POST["from"] ?? 0);
    $to = (int)($_POST["to"] ?? 0);
    $amount = (int)($_POST["amount"] ?? 0);

    // Проверка валидности значений
    if ($from <= 0 || $to <= 0 || $amount <= 0) {
        die("Прямой доступ закрыт");
    }

    // Запрос бонуса отправителя
    $res = sql_query("SELECT bonus FROM users WHERE id = " . sqlesc($from));
    $row = mysqli_fetch_assoc($res);

    if (!$row) {
        die("Пользователь отправитель не найден.");
    }

    if ($row['bonus'] < $amount) {
        die("У вас недостаточно бонусов.");
    }

    if ($from === $to) {
        die("Вы не можете дарить бонусы себе.");
    }

    // Обновляем бонусы
    sql_query("UPDATE users SET bonus = bonus + " . sqlesc($amount) . " WHERE id = " . sqlesc($to));
    sql_query("UPDATE users SET bonus = bonus - " . sqlesc($amount) . " WHERE id = " . sqlesc($from));

    // Успешный вывод
    die("Вы подарили пользователю $amount бонусов.");
}

die("Прямой доступ закрыт");

?>
