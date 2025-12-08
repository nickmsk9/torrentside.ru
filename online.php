<?php
// Подключаем необходимые файлы
include_once("include/bittorrent.php");

// Устанавливаем соединение с базой
dbconn();

// Устанавливаем кодировку
header("Content-Type: text/html; charset=utf-8");

// Начало блока онлайн-пользователей
echo '<div id="wol">';

// Получаем пользователей, которые были активны за последние 3 минуты (180 секунд)
$dt = gmtime() - 180;
$dt_sql = sqlesc(get_date_time($dt));

// Выполняем запрос к базе данных
$res = sql_query("SELECT id, username, class, donor, warned, parked FROM users WHERE last_access >= $dt_sql ORDER BY username") or sqlerr(__FILE__, __LINE__);

// Перебираем пользователей
while ($arr = mysqli_fetch_assoc($res)) {
    $username = htmlspecialchars($arr['username']);
    $id = (int)$arr['id'];

    // Кнопка для приватного сообщения и ссылка на профиль
    echo '<font size="1">';
    echo '<span onclick="parent.document.shoutform.shout.focus(); parent.document.shoutform.shout.value=\'privat(' . $username . ') \' + parent.document.shoutform.shout.value; return false;" style="cursor: pointer; color: red; font-weight: bold;">P</span> ';
    echo '<a href="userdetails.php?id=' . $id . '" onclick="parent.document.shoutform.shout.focus(); parent.document.shoutform.shout.value=\'[' . $username . '] \' + parent.document.shoutform.shout.value; return false;" target="_blank">';
    echo get_user_class_color($arr["class"], $username);
    echo '</a></font><br>';
}

echo '</div>';
