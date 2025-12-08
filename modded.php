<?php
require "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'], "Нет доступа.");
}

stdhead("Обзор проверки торрентов");

echo '<div align="center" style="padding:10px;"><a href="modded.php">Непроверенные</a> | <a href="modded.php?modded">Проверенные</a> | <a href="modded.php?top">Топ модераторов</a></div>';

//
// TOP модераторов
//
if (isset($_GET['top'])) {
    begin_frame("Топ модераторов (включая удаленные раздачи)");

    $res = sql_query(
        "SELECT id, username, class, moderated
         FROM users
         WHERE class >= " . UC_MODERATOR . " AND moderated > 0
         ORDER BY moderated DESC"
    ) or sqlerr(__FILE__, __LINE__);

    $rows = mysqli_num_rows($res);

    $out = [];
    $out[] = '<table width="100%" cellpadding="5">';
    $out[] = '<tr><td class="colhead">№</td><td class="colhead">Модератор</td><td class="colhead">Проверил</td></tr>';

    if ($rows === 0) {
        $out[] = '<tr><td colspan="3">Нет статистики</td></tr>';
    } else {
        $i = 1;
        while ($row = mysqli_fetch_assoc($res)) {
            $uid = (int)$row['id'];
            $out[] = '<tr>'
                . '<td>' . $i . '</td>'
                . '<td><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color((int)$row['class'], $row['username']) . '</a></td>'
                . '<td><a href="modded.php?moderator=' . $uid . '">' . (int)$row['moderated'] . '</a></td>'
                . '</tr>';
            $i++;
        }
    }

    $out[] = '</table>';
    echo implode("\n", $out);

    end_frame();
}
//
// Список проверенных
//
elseif (isset($_GET['modded'])) {
    // точное число для pager — БЕЗ number_format
    $count = get_row_count("torrents", "WHERE modded='yes'");
    list($pagertop, $pagerbottom, $limit) = pager(15, $count, "modded.php?modded&");

    begin_frame("Проверенные торренты [" . (int)$count . ']');

    echo $pagertop;

    $sql = "SELECT t.id, t.name, t.owner, t.modby, t.modname, t.modtime,
                   u.username, u.class
            FROM torrents AS t
            LEFT JOIN users AS u ON t.owner = u.id
            WHERE t.modded = 'yes'
            ORDER BY t.modtime DESC
            $limit";
    $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $out = [];
    $out[] = '<table width="100%" cellpadding="5">';
    $out[] = '<tr><td class="colhead">Торрент</td><td class="colhead">Загрузил</td><td class="colhead">Проверил</td><td class="colhead">Когда?</td></tr>';

    if (mysqli_num_rows($res) === 0) {
        $out[] = '<tr><td colspan="4">Нет проверенных торрентов</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($res)) {
            $tid = (int)$row['id'];
            $owner = (int)$row['owner'];
            $modby = (int)$row['modby'];
            $out[] = '<tr>'
                . '<td><a href="details.php?id=' . $tid . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</a></td>'
                . '<td><a href="userdetails.php?id=' . $owner . '">' . get_user_class_color((int)$row['class'], $row['username']) . '</a></td>'
                . '<td><a href="userdetails.php?id=' . $modby . '">' . htmlspecialchars($row['modname'], ENT_QUOTES, 'UTF-8') . '</a></td>'
                . '<td>' . htmlspecialchars($row['modtime'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    if ($count) {
        $out[] = '<tr><td colspan="4">' . $pagerbottom . '</td></tr>';
    }
    $out[] = '</table>';

    echo implode("\n", $out);
    end_frame();
}
//
// Торренты, проверенные конкретным модератором
//
elseif (isset($_GET['moderator'])) {
    $moderator = (int)$_GET['moderator'];

    // корректный count без форматирования
    $count = get_row_count("torrents", "WHERE modby = " . sqlesc($moderator));
    list($pagertop, $pagerbottom, $limit) = pager(15, $count, "modded.php?moderator=" . $moderator . "&");

    // данные модератора для заголовка
    $resHdr = sql_query(
        "SELECT u.id, u.username, u.class
         FROM users AS u
         WHERE u.id = " . sqlesc($moderator) . " LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);
    $hdr = mysqli_fetch_assoc($resHdr);

    $modLink = $hdr
        ? '<a href="userdetails.php?id=' . (int)$hdr['id'] . '">' . get_user_class_color((int)$hdr['class'], $hdr['username']) . '</a>'
        : 'Неизвестный модератор';

    begin_frame('Торренты, проверенные ' . $modLink . ' [' . (int)$count . ']');

    echo $pagertop;

    $sql = "SELECT t.id, t.name, t.owner, t.modtime,
                   u.username, u.class
            FROM torrents AS t
            LEFT JOIN users AS u ON t.owner = u.id
            WHERE t.modby = " . sqlesc($moderator) . "
            ORDER BY t.modtime DESC
            $limit";
    $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $out = [];
    $out[] = '<table width="100%" cellpadding="5">';
    $out[] = '<tr><td class="colhead">Торрент</td><td class="colhead">Загрузил</td><td class="colhead">Проверен</td></tr>';

    if (mysqli_num_rows($res) === 0 || !$moderator) {
        $out[] = '<tr><td colspan="3">Не проверено ни одного торрента этим модератором</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($res)) {
            $tid = (int)$row['id'];
            $owner = (int)$row['owner'];
            $out[] = '<tr>'
                . '<td><a href="details.php?id=' . $tid . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</a></td>'
                . '<td><a href="userdetails.php?id=' . $owner . '">' . get_user_class_color((int)$row['class'], $row['username']) . '</a></td>'
                . '<td>' . htmlspecialchars($row['modtime'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    if ($count) {
        $out[] = '<tr><td colspan="3">' . $pagerbottom . '</td></tr>';
    }
    $out[] = '</table>';

    echo implode("\n", $out);
    end_frame();
}
//
// Непроверенные
//
else {
    $count = get_row_count("torrents", "WHERE modded='no'");
    list($pagertop, $pagerbottom, $limit) = pager(15, $count, "modded.php?");

    begin_frame("Непроверенные торренты [" . (int)$count . ']');

    echo $pagertop;

    $sql = "SELECT t.id, t.name, t.owner, t.added,
                   u.username, u.class
            FROM torrents AS t
            LEFT JOIN users AS u ON t.owner = u.id
            WHERE t.modded = 'no'
            ORDER BY t.id
            $limit";
    $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $out = [];
    $out[] = '<table width="100%" cellpadding="5">';
    $out[] = '<tr><td class="colhead">Торрент</td><td class="colhead">Загрузил</td><td class="colhead">Когда?</td></tr>';

    if (mysqli_num_rows($res) === 0) {
        $out[] = '<tr><td colspan="3">Все торренты проверены</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($res)) {
            $tid = (int)$row['id'];
            $owner = (int)$row['owner'];
            $out[] = '<tr>'
                . '<td><a href="details.php?id=' . $tid . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</a></td>'
                . '<td><a href="userdetails.php?id=' . $owner . '">' . get_user_class_color((int)$row['class'], $row['username']) . '</a></td>'
                . '<td>' . htmlspecialchars($row['added'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    if ($count) {
        $out[] = '<tr><td colspan="3">' . $pagerbottom . '</td></tr>';
    }
    $out[] = '</table>';

    echo implode("\n", $out);
    end_frame();
}

stdfoot();
