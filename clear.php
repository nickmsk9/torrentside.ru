<?php

require_once("include/bittorrent.php");
dbconn(false);
loggedinorreturn();

// Только для SYSOP
if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка доступа", "У вас нет прав для очистки чата.");
    exit;
}

// Обработка отправки формы
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["clear"])) {
    sql_query("TRUNCATE TABLE shoutbox");
    header("Location: index.php");
    exit;
}

// Интерфейс подтверждения
stdhead("Очистка чата");
begin_frame("Очистка чата");

echo <<<HTML
<form method="post" action="clear.php" onsubmit="return confirm('Вы уверены, что хотите очистить чат?');">
    <input type="submit" name="clear" value="Очистить чат" style="padding: 8px 16px; font-weight: bold;">
</form>
HTML;

end_frame();
stdfoot();
