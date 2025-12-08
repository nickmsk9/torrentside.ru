<?php

require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_UPLOADER)
    stderr($tracker_lang['error'], "Нет доступа.");

// Просмотр списка несоединяемых пиров
if (($_GET['action'] ?? '') === "list") {

    stdhead("Пиры с которыми нельзя соединиться");

    echo "<a href='findnotconnectable.php?action=sendpm'><h3>Послать всем несоединяемым пирам массовое ПМ</h3></a>";
    echo "<a href='findnotconnectable.php'><h3>Просмотреть лог</h3></a>";
    echo "<h1>Пиры с которыми нельзя соединиться</h1>";
    echo "Это только те пользователи, которые сейчас активны на торрентах.";
    echo "<br><font color='red'>*</font> означает, что пользователь сидирует.<p>";

    // Подсчёт уникальных пиров
    $result = sql_query("SELECT DISTINCT userid FROM peers WHERE connectable = 'no'");
    $count = mysqli_num_rows($result);
    echo "$count уникальных пиров с которыми нельзя соединиться.<br><br>";

    // Выводим таблицу пиров
    $res2 = sql_query("SELECT userid, seeder, torrent, agent FROM peers WHERE connectable='no' ORDER BY userid DESC");
    if (mysqli_num_rows($res2) == 0) {
        echo "<p align='center'><b>Со всеми пирами можно соединиться!</b></p>";
    } else {
        echo "<table border='1' cellspacing='0' cellpadding='5'>
              <tr><td class='colhead'>Пользователь</td><td class='colhead'>Торрент</td><td class='colhead'>Клиент</td></tr>";

        while ($arr2 = mysqli_fetch_assoc($res2)) {
            $userid = (int)$arr2['userid'];
            $torrent = (int)$arr2['torrent'];
            $seeder = $arr2['seeder'] === 'yes';
            $agent = htmlspecialchars($arr2['agent']);

            $r2 = sql_query("SELECT username FROM users WHERE id = $userid");
            $a2 = mysqli_fetch_assoc($r2);
            $username = htmlspecialchars($a2['username']);

            echo "<tr><td><a href='userdetails.php?id=$userid'>$username</a></td>
                  <td align='left'><a href='details.php?id=$torrent&dllist=1#seeders'>$torrent";
            if ($seeder) echo "<font color='red'>*</font>";
            echo "</a></td><td align='left'>$agent</td></tr>";
        }
        echo "</table>";
    }

    stdfoot();
    exit;
}

// Обработка отправки ПМ
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dt = sqlesc(get_date_time());
    $msg = trim($_POST['msg'] ?? '');

    if (empty($msg)) stderr($tracker_lang['error'], "Введите текст сообщения");

    $query = sql_query("SELECT DISTINCT userid FROM peers WHERE connectable='no'");
    while ($dat = mysqli_fetch_assoc($query)) {
        $userid = (int)$dat['userid'];
        $subject = sqlesc("Трекер определил вас несоединяемым");
        sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES (0, $userid, NOW(), " . sqlesc($msg) . ", $subject)") or sqlerr(__FILE__, __LINE__);
    }

    sql_query("INSERT INTO notconnectablepmlog (user, date) VALUES (" . (int)$CURUSER['id'] . ", $dt)") or sqlerr(__FILE__, __LINE__);
    header("Location: findnotconnectable.php");
    exit;
}

// Форма для отправки ПМ
if (($_GET['action'] ?? '') === "sendpm") {

    stdhead("Пиры с которыми нельзя соединиться");

    $body = "Трекер определил, что вы за NAT или файрволены и не принимаете входящие соединения.\n\n" .
            "Это означает, что другие пользователи не смогут подключиться к вам, только вы к ним. " .
            "Если оба пользователя находятся за NAT — соединение невозможно. Это ухудшает общую скорость загрузки.\n\n" .
            "Решение: настройте проброс портов на роутере (port forwarding). Подробности читайте в документации к вашему роутеру или на сайте PortForward.\n\n" .
            "Если вам нужна помощь — заходите в наш чат или пишите на форуме.";

    echo "<table class='main' width='750'><tr><td class='embedded'>
          <div align='center'>
          <h1>Общее сообщение для пользователей с которыми нельзя соединиться</h1>
          <form method='post' action='findnotconnectable.php'>
          <table cellspacing='0' cellpadding='5'>
          <tr><td><textarea name='msg' cols='120' rows='15'>" . htmlspecialchars($body) . "</textarea></td></tr>
          <tr><td align='center'><input type='submit' value='Отправить' class='btn'></td></tr>
          </table>
          </form>
          </div></td></tr></table>";

    stdfoot();
    exit;
}

// Отображение лога
if (empty($_GET['action'])) {
    stdhead("Лог общих сообщений");
begin_frame("Лог общих сообщений");
    echo "<h1>Лог общих сообщений для файрволеных</h1>";
    echo "<a href='findnotconnectable.php?action=sendpm'><h3>Послать общее сообщение</h3></a>";
    echo "<a href='findnotconnectable.php?action=list'><h3>Показать список пользователей</h3></a>";
    echo "<p>Пожалуйста, не отправляйте сообщения слишком часто. Одного раза в неделю достаточно.</p>";

    $getlog = sql_query("SELECT * FROM notconnectablepmlog ORDER BY date DESC LIMIT 10");

    echo "<table border='1' cellspacing='0' cellpadding='5'>
          <tr><td class='colhead'>Пользователь</td><td class='colhead'>Дата</td><td class='colhead'>Прошло</td></tr>";

    while ($arr2 = mysqli_fetch_assoc($getlog)) {
        $userId = (int)$arr2['user'];
        $date = $arr2['date'];
        $elapsed = get_elapsed_time(sql_timestamp_to_unix_timestamp($date));

        $r2 = sql_query("SELECT username FROM users WHERE id=$userId");
        $a2 = mysqli_fetch_assoc($r2);
        $username = htmlspecialchars($a2['username']);

        echo "<tr><td><a href='userdetails.php?id=$userId'>$username</a></td><td>$date</td><td>$elapsed назад</td></tr>";
    }

    echo "</table>";
end_frame();
    stdfoot();
}
