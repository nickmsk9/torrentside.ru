<?php

require "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

// Только админ может работать с тегами
if (get_user_class() < UC_ADMINISTRATOR) {
    stderr("Ошибка", "Доступ запрещен.");
}

// Обработка POST-запроса
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["edit"])) {
        $id = (int)($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? '');
        $category = (int)($_POST["category"] ?? 0);

        if ($id <= 0 || empty($name) || $category <= 0) {
            stderr("Ошибка", "Заполните все поля корректно.");
        }

        $res = sql_query("SELECT id FROM tags WHERE id = $id");
        if (mysqli_num_rows($res) === 0) {
            stderr("Ошибка", "Тэг с таким ID не найден.");
        }

        sql_query("UPDATE tags SET name = LOWER(" . sqlesc($name) . "), category = " . sqlesc($category) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);
        header("Location: $DEFAULTBASEURL/tags-admin.php");
        exit;

    } elseif (isset($_POST["add"])) {
        $name = trim($_POST["name"] ?? '');
        $category = (int)($_POST["category"] ?? 0);

        if (empty($name) || $category <= 0) {
            stderr("Ошибка", "Вы заполнили не все поля формы");
        }

        sql_query("INSERT INTO tags (name, category) VALUES (LOWER(" . sqlesc($name) . "), " . sqlesc($category) . ")") or sqlerr(__FILE__, __LINE__);
        header("Location: $DEFAULTBASEURL/tags-admin.php");
        exit;

    } else {
        stderr("Ошибка", "Не выбрано действие");
    }
}

// Форма редактирования
elseif (isset($_GET["edit"])) {
    $id = (int)$_GET["edit"];
    $res = sql_query("SELECT * FROM tags WHERE id = $id") or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) === 0) {
        stderr("Ошибка", "Тэг не найден.");
    }

    $row = mysqli_fetch_assoc($res);

    stdhead("Редактирование тэга");
    begin_frame("Редактирование тэга «" . htmlspecialchars($row["name"]) . "»");

    echo "<form method='POST' action='tags-admin.php'>";
    echo "<input type='hidden' name='edit' value='1'>";
    echo "<input type='hidden' name='id' value='" . (int)$row["id"] . "'>";
    echo "<table>";
    echo "<tr><td>Название:</td><td><input type='text' name='name' value='" . htmlspecialchars($row["name"]) . "' style='width:200px;'></td></tr>";

    $s = "<select name='category' style='width:200px;'>\n<option value='0'>(" . $tracker_lang['choose'] . ")</option>\n";
    foreach (genrelist() as $cat) {
        $selected = $cat['id'] == $row['category'] ? " selected" : "";
        $s .= "<option value='{$cat["id"]}'$selected>" . htmlspecialchars($cat["name"]) . "</option>\n";
    }
    $s .= "</select>\n";
    echo "<tr><td>Категория:</td><td>$s</td></tr>";
    echo "<tr><td colspan='2'><input type='submit' value='Сохранить'> <input type='reset' value='Сбросить'></td></tr>";
    echo "</table></form>";

    end_frame();
    stdfoot();
}

// Форма добавления
elseif (isset($_GET["add"])) {
    stdhead("Добавление тэга");
    begin_frame("Добавление нового тэга");

    echo "<form method='POST' action='tags-admin.php'>";
    echo "<input type='hidden' name='add' value='1'>";
    echo "<table>";
    echo "<tr><td>Название:</td><td><input type='text' name='name' style='width:200px;'></td></tr>";

    $s = "<select name='category' style='width:200px;'>\n<option value='0'>(" . $tracker_lang['choose'] . ")</option>\n";
    foreach (genrelist() as $cat) {
        $s .= "<option value='{$cat["id"]}'>" . htmlspecialchars($cat["name"]) . "</option>\n";
    }
    $s .= "</select>\n";
    echo "<tr><td>Категория:</td><td>$s</td></tr>";
    echo "<tr><td colspan='2'><input type='submit' value='Сохранить'> <input type='reset' value='Сбросить'></td></tr>";
    echo "</table></form>";

    end_frame();
    stdfoot();
}

// Удаление
elseif (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];

    $res = sql_query("SELECT name FROM tags WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($res) === 0) {
        stderr("Ошибка", "Такого тэга не существует.");
    }

    sql_query("DELETE FROM tags WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    header("Location: $DEFAULTBASEURL/tags-admin.php");
    exit;
}

// Главная таблица
else {
    stdhead("Управление тэгами");
    begin_frame("Управление тэгами [ <a href='tags-admin.php?add=1'>Добавить новый</a> ]");

    echo "<style>#browsetags img { border: none; }</style>";
    echo "<table id='browsetags' width='100%'>";
    echo "<tr>
        <td class='colhead'>Тэг</td>
        <td class='colhead'>Категория</td>
        <td class='colhead' align='center'>Обзор</td>
        <td class='colhead' align='center'>Торрентов с тэгом</td>
        <td class='colhead' align='center'>Редактировать</td>
        <td class='colhead' align='center'>Удалить</td>
    </tr>";

    $count = get_row_count("tags");
    $perpage = 25;
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "tags-admin.php?");

$res = sql_query("
    SELECT t.*, c.id AS cat_id, c.name AS cat_name
    FROM tags AS t
    LEFT JOIN categories AS c ON t.category = c.id
    ORDER BY c.id $limit
") or sqlerr(__FILE__, __LINE__);


    while ($row = mysqli_fetch_assoc($res)) {
        echo "<tr>
            <td>" . htmlspecialchars($row["name"]) . "</td>
            <td>" . htmlspecialchars($row["cat_name"] ?? "—") . "</td>
            <td align='center'><a href='browse.php?tag=" . urlencode($row["name"]) . "&cat=" . (int)$row["cat_id"] . "&incldead=1'><img src='pic/viewnfo.gif'></a></td>
            <td align='center'>" . (int)$row["howmuch"] . "</td>
            <td align='center'><a href='tags-admin.php?edit=" . (int)$row["id"] . "'><img src='pic/edit_com.png'></a></td>
            <td align='center'><a href='tags-admin.php?delete=" . (int)$row["id"] . "'><img src='pic/delete.png'></a></td>
        </tr>";
    }

    echo "<tr><td colspan='6'><br>$pagerbottom</td></tr>";
    echo "</table>";

    end_frame();
    stdfoot();
}
