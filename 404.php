<?php

// Проверка на доступ к админке — если есть 'admin' в URI, логируем попытку
if (strpos($_SERVER['REQUEST_URI'], 'admin') !== false) {
    require_once 'include/bittorrent.php';
    dbconn();
    hacker('404 {' . htmlspecialchars($_SERVER['REQUEST_URI']) . '}');
} else {
    require_once 'include/bittorrent.php';
    dbconn();
}

// Заголовок страницы
stdhead("Страница не найдена!");

// Начало контента
begin_frame("Страница не найдена!");
?>

<center>
    <div style="font-weight:bold; display:block; border: 3px solid blue; padding:10px; margin:10px; font-size:15px;">
        К сожалению, запрошенная Вами страница не найдена.<br>
        Если Вы попали сюда по ссылке на нашем сайте, пожалуйста, сообщите об этом
        <a href="staff.php" style="color:blue; text-decoration:none;">администрации!</a><br><br>
        <a href="javascript:history.go(-1);" style="color:blue; text-decoration:none;">Назад</a>
    </div>
</center>

<?php
// Завершение страницы
end_frame();
stdfoot();
?>
