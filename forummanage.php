<?php
require_once("include/bittorrent.php");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    attacks_log($_SERVER["SCRIPT_FILENAME"] ?? 'forummanage.php');
    stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещён');
    die();
}

/** Инвалидация мемкеша списков форумов для «Быстрого перехода» */
function invalidate_forum_jump_cache(): void {
    if (function_exists('mc_del')) {
        mc_del('forum:list:jump:v1');
    }
}
/** Инвалидация типовых списков форумов (под твои ключи — безопасно no-op если mc_del отсутствует) */
function invalidate_forum_lists(int $forumid): void {
    if (!function_exists('mc_del')) return;
    $forumid = (int)$forumid;
    mc_del("forums:{$forumid}:topic_count:v1");
    for ($p = 0; $p <= 5; $p++) {
        mc_del("forums:{$forumid}:topics:list:v1:{$p}:25");
        mc_del("forums:{$forumid}:topics:list:v1:{$p}:50");
    }
}

// старый мусорный файл кеша — можно удалить молча
@unlink(ROOT_PATH . "cache/8b5efe4c9a15d0fcacf372b1bee66935.txt");

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'edit':       editForum();       break;
    case 'takeedit':   takeeditForum();   break;
    case 'delete':     deleteForum();     break;
    case 'takedelete': takedeleteForum(); break;
    case 'add':        addForum();        break;
    case 'takeadd':    takeaddForum();    break;
    default:           showForums();      break;
}

/* =========================== Список форумов =========================== */
function showForums(): void {
    stdhead("Администрирование форума");
    begin_frame("Администрирование форума");

    echo "<form method='get' action='forummanage.php'>
            <input type='hidden' name='action' value='add'>
            <input type='submit' value='Добавить новую категорию' class='btn'>
          </form><br>";


    echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
    echo "<tr>
            <td class='colhead' align='left'>Название категории</td>
            <td class='colhead' align='center'>Тем / Постов</td>
            <td class='colhead' align='center'>Минимальные права</td>
            <td class='colhead' align='center'>Действия</td>
          </tr>";

    // одна выборка со счётчиками
    $res = sql_query("
        SELECT
            f.id, f.name, f.description, f.minclassread, f.minclasswrite, f.minclasscreate, f.sort, f.visible,
            (SELECT COUNT(*) FROM topics t WHERE t.forumid = f.id)                       AS topics,
            (SELECT COUNT(*) FROM posts  p WHERE p.forumid = f.id)                       AS posts
        FROM forums f
        ORDER BY f.sort ASC, f.name ASC
    ") or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) === 0) {
        echo "<tr><td class='a' colspan='4'>Извините, нет записей!</td></tr>";
    } else {
        while ($row = mysqli_fetch_assoc($res)) {
            $id   = (int)$row['id'];
            $name = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $desc = htmlspecialchars((string)$row['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $topiccount = number_format((int)$row['topics']);
            $postcount  = number_format((int)$row['posts']);

            $read  = (int)$row['minclassread'];
            $write = (int)$row['minclasswrite'];
            $create= (int)$row['minclasscreate'];

            // Цвета/названия рангов — твои функции
            $readHtml   = get_user_class_color($read,   get_user_class_name($read));
$writeHtml  = get_user_class_color($write,  get_user_class_name($write));
$createHtml = get_user_class_color($create, get_user_class_name($create));

            echo "<tr>
                    <td class='b'>
                      <a href='forums.php?action=viewforum&amp;forumid={$id}'><b>{$name}</b></a><br>
                      <small>{$desc}</small>
                    </td>
                    <td class='a' align='center'>{$topiccount} / {$postcount}</td>
                    <td class='b'>
                      <b>Чтение:</b> {$readHtml}<br>
                      <b>Запись:</b> {$writeHtml}<br>
                      <b>Создание:</b> {$createHtml}
                    </td>
                    <td class='a' align='center' style='white-space:nowrap'>
                      <b><a href='?action=edit&amp;id={$id}'>Редактировать</a>
                      <hr>
                      <a href='forummanage.php?action=delete&amp;id={$id}' style='color:red'>Удалить</a></b>
                    </td>
                  </tr>";
        }
    }

    echo "</table>";
	end_frame();
    stdfoot();
}

/* =========================== Добавить форум (форма) =========================== */
function addForum(): void {
    stdhead("Добавление категории");

    echo "<form method='get' action='forummanage.php'>
            <input type='submit' value='Вернуться обратно' class='btn'>
          </form><br>";

    // <-- ключевая замена обёртки
    begin_main_frame();
    ?>
    <form method="post" action="forummanage.php?action=takeadd">
      <table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
        <tr align="center">
          <td colspan="2" class="colhead">Создание новой категории</td>
        </tr>
        <tr>
          <td class="b"><b>Название категории</b></td>
          <td class="a"><input name="name" type="text" size="40" maxlength="60" required></td>
        </tr>
        <tr>
          <td class="b"><b>Описание категории</b></td>
          <td class="a"><input name="desc" type="text" size="60" maxlength="200"></td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на чтение</b></td>
          <td class="a">
            <select name="readclass">
              <?php
              $maxclass = (int)get_user_class();
              for ($i = 0; $i <= $maxclass; $i++) {
                  echo "<option value='{$i}'" . ($maxclass === $i ? " selected" : "") . ">" . get_user_class_name($i) . "</option>";
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на запись</b></td>
          <td class="a">
            <select name="writeclass">
              <?php
              for ($i = 0; $i <= $maxclass; $i++) {
                  echo "<option value='{$i}'" . ($maxclass === $i ? " selected" : "") . ">" . get_user_class_name($i) . "</option>";
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на создание</b></td>
          <td class="a">
            <select name="createclass">
              <?php
              for ($i = 0; $i <= $maxclass; $i++) {
                  echo "<option value='{$i}'" . ($maxclass === $i ? " selected" : "") . ">" . get_user_class_name($i) . "</option>";
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td class="b"><b>Сортировка</b></td>
          <td class="a">
            <select name="sort">
              <?php
              $res = sql_query("SELECT COUNT(*) FROM forums");
              [$nr] = mysqli_fetch_row($res);
              $max = (int)$nr + 1;
              for ($i = 0; $i <= $max; $i++) echo "<option value='{$i}'>{$i}</option>";
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td class="b"><b>Показывать категорию в блоке</b></td>
          <td class="a">
            <label><input type="radio" name="visible" value="yes" checked> Да</label>
            <label><input type="radio" name="visible" value="no"> Нет</label>
          </td>
        </tr>
        <tr align="center">
          <td class="a" colspan="2">
            <input type="submit" name="Submit" value="Создать категорию" class="btn">
          </td>
        </tr>
      </table>
    </form>
    <?php
    // <-- закрываем правильную обёртку
    end_main_frame();

    stdfoot();
}


/* =========================== Редактировать форум (форма) =========================== */
function editForum(): void {
    $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    if ($id <= 0) stderr("Ошибка", "Плохой id");

    stdhead("Редактирование категории");

    echo "<form method='get' action='forummanage.php'>
            <input type='submit' value='К админке, обратно' class='btn'>
          </form><br>";

    $res = sql_query("SELECT * FROM forums WHERE id = " . sqlesc($id) . " LIMIT 1");
    if (mysqli_num_rows($res) === 0) {
        echo "Извините, нет записей!";
        stdfoot();
        return;
    }
    $row = mysqli_fetch_assoc($res);
    ?>
    <form method="post" action="forummanage.php?action=takeedit">
      <table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
        <tr align="center">
          <td colspan="2" class="colhead">Редактируем: <?=
            htmlspecialchars((string)$row["name"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
        </tr>
        <tr>
          <td class="b"><b>Название категории</b></td>
          <td class="a"><input name="name" type="text" size="40" maxlength="60"
                 value="<?= htmlspecialchars((string)$row["name"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required></td>
        </tr>
        <tr>
          <td class="b"><b>Описание категории</b></td>
          <td class="a"><input name="desc" type="text" size="60" maxlength="200"
                 value="<?= htmlspecialchars((string)$row["description"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на чтение</b></td>
          <td class="a"><select name="readclass">
            <?php
            $maxclass = (int)get_user_class();
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value='{$i}'" . ((int)$row["minclassread"] === $i ? " selected" : "") . ">"
                   . get_user_class_name($i) . "</option>";
            }
            ?>
          </select></td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на запись</b></td>
          <td class="a"><select name="writeclass">
            <?php
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value='{$i}'" . ((int)$row["minclasswrite"] === $i ? " selected" : "") . ">"
                   . get_user_class_name($i) . "</option>";
            }
            ?>
          </select></td>
        </tr>
        <tr>
          <td class="b"><b>Минимальные права на создание</b></td>
          <td class="a"><select name="createclass">
            <?php
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value='{$i}'" . ((int)$row["minclasscreate"] === $i ? " selected" : "") . ">"
                   . get_user_class_name($i) . "</option>";
            }
            ?>
          </select></td>
        </tr>
        <tr>
          <td class="b"><b>Сортировка</b></td>
          <td class="a"><select name="sort">
            <?php
            $rs = sql_query("SELECT COUNT(*) FROM forums");
            [$nr] = mysqli_fetch_row($rs);
            $max = (int)$nr + 1;
            for ($i = 0; $i <= $max; $i++) {
                echo "<option value='{$i}'" . ((int)$row["sort"] === $i ? " selected" : "") . ">{$i}</option>";
            }
            ?>
          </select></td>
        </tr>
        <tr>
          <td class="b"><b>Показывать категорию в блоке</b></td>
          <td class="a">
            <label><input type="radio" name="visible" value="yes" <?= ($row["visible"] === "yes" ? "checked" : ""); ?>> Да</label>
            <label><input type="radio" name="visible" value="no"  <?= ($row["visible"] === "no"  ? "checked" : ""); ?>> Нет</label>
          </td>
        </tr>
        <tr align="center">
          <td class="a" colspan="2">
            <input type="hidden" name="id" value="<?= (int)$id; ?>">
            <input type="submit" name="Submit" value="Редактировать категорию" class="btn">
          </td>
        </tr>
      </table>
    </form>
    <?php
    stdfoot();
}

/* =========================== Добавить форум (обработка) =========================== */
function takeaddForum(): void {
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['desc'] ?? ''));
    if ($name === '' && $desc === '') {
        header("Location: forummanage.php");
        die();
    }

    $sort    = (int)($_POST['sort']       ?? 0);
    $read    = (int)($_POST['readclass']  ?? 0);
    $write   = (int)($_POST['writeclass'] ?? 0);
    $create  = (int)($_POST['createclass']?? 0);
    $visible = (($_POST['visible'] ?? 'yes') === 'yes') ? 'yes' : 'no';

    $q = "INSERT INTO forums (sort, name, description, minclassread, minclasswrite, minclasscreate, visible)
          VALUES (" .
          sqlesc($sort) . ", " .
          sqlesc($name) . ", " .
          sqlesc($desc) . ", " .
          sqlesc($read) . ", " .
          sqlesc($write) . ", " .
          sqlesc($create) . ", " .
          sqlesc($visible) . ")";

    sql_query($q) or sqlerr(__FILE__, __LINE__);

    // invalidation
    invalidate_forum_jump_cache();

    if (mysqli_affected_rows($GLOBALS['mysqli']) === 1) {
        header("Refresh: 2; url=forummanage.php");
        stderr("Успешно", "Категория добавлена. <a href='forummanage.php'>Вернуться обратно</a>");
    } else {
        header("Refresh: 4; url=forummanage.php");
        stderr("Ошибка", "Неизвестная ошибка. <a href='forummanage.php'>Вернуться обратно</a>");
    }
    die();
}

/* =========================== Редактировать форум (обработка) =========================== */
function takeeditForum(): void {
    $id    = (int)($_POST['id']         ?? 0);
    $name  = trim((string)($_POST['name'] ?? ''));
    $desc  = trim((string)($_POST['desc'] ?? ''));
    if ($id <= 0 || ($name === '' && $desc === '')) {
        header("Location: forummanage.php");
        die();
    }

    $sort    = (int)($_POST['sort']       ?? 0);
    $read    = (int)($_POST['readclass']  ?? 0);
    $write   = (int)($_POST['writeclass'] ?? 0);
    $create  = (int)($_POST['createclass']?? 0);
    $visible = (($_POST['visible'] ?? 'yes') === 'yes') ? 'yes' : 'no';

    $q = "UPDATE forums SET
            sort = " . sqlesc($sort) . ",
            name = " . sqlesc($name) . ",
            description = " . sqlesc($desc) . ",
            minclassread = " . sqlesc($read) . ",
            minclasswrite = " . sqlesc($write) . ",
            minclasscreate = " . sqlesc($create) . ",
            visible = " . sqlesc($visible) . "
          WHERE id = " . sqlesc($id) . " LIMIT 1";

    sql_query($q) or sqlerr(__FILE__, __LINE__);

    // invalidation
    invalidate_forum_jump_cache();
    invalidate_forum_lists($id);

    header("Refresh: 2; url=forummanage.php?action=edit&id=" . (int)$id);
    if (mysqli_affected_rows($GLOBALS['mysqli']) >= 0) {
        // даже если 0 строк — нормально (данные могли не измениться)
        stderr("Успешно", "Категория отредактирована. <a href='forummanage.php'>К админке форума</a>");
    } else {
        stderr("Ошибка", "Не удалось отредактировать категорию. <a href='forummanage.php'>К админке форума</a>");
    }
    die();
}

/* =========================== Удалить форум (форма подтверждения/варианты) =========================== */
function deleteForum(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) stderr("Ошибка", "Не число");

    $res = sql_query("SELECT id FROM topics WHERE forumid = " . sqlesc($id) . " LIMIT 1");
    if (mysqli_num_rows($res) >= 1) {
        stdhead("Перемещение тем перед удалением");
        forum_select($id);
        stdfoot();
        exit;
    } else {
        stderr("Предупреждение",
               "Вы уверены, что хотите удалить эту категорию форума? <br>
                <a href='forummanage.php?action=takedelete&amp;id={$id}'>Удалить безвозвратно</a>");
    }
}

/* =========================== Удалить форум (обработка) =========================== */
function takedeleteForum(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) stderr("Ошибка", "Не число");

    // вариант А: удалить целиком (нет тем)
    if (!isset($_POST['deleteall'])) {
        $res = sql_query("SELECT id FROM topics WHERE forumid = " . sqlesc($id) . " LIMIT 1");
        if (mysqli_num_rows($res) > 0) {
            stderr("Ошибка", "В разделе есть темы. Сначала переместите их.");
        }

        sql_query("DELETE FROM forums WHERE id = " . sqlesc($id) . " LIMIT 1");
        $ok = mysqli_affected_rows($GLOBALS['mysqli']) > 0;

        if ($ok) {
            invalidate_forum_jump_cache();
            stderr("Успешно", "Категория форума удалена. <a href='forummanage.php'>к админке форума</a>");
        } else {
            stderr("Ошибка", "Нельзя удалить эту категорию форума!");
        }
        return;
    }

    // вариант Б: переместить темы в другой раздел и удалить
    $forumid = isset($_POST['forumid']) && ctype_digit((string)$_POST['forumid'])
        ? (int)$_POST['forumid']
        : 0;
    if ($forumid <= 0) stderr("Ошибка", "Нет данных для обработки запроса");

    $res = sql_query("SELECT id FROM topics WHERE forumid = " . sqlesc($id));
    if (mysqli_num_rows($res) === 0) {
        stderr("Ошибка тем", "Нет тем в этой категории форума!");
    }
    $tid = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $tid[] = (int)$row['id'];
    }
    $ids = implode(',', $tid);

    sql_query("UPDATE topics SET forumid = " . sqlesc($forumid) . " WHERE id IN ({$ids})") or sqlerr(__FILE__, __LINE__);

    if (mysqli_affected_rows($GLOBALS['mysqli']) >= 0) {
        // удалить исходный форум
        sql_query("DELETE FROM forums WHERE id = " . sqlesc($id) . " LIMIT 1");
        $ok = mysqli_affected_rows($GLOBALS['mysqli']) > 0;

        // инвалидация кешей на оба форума
        invalidate_forum_lists($id);
        invalidate_forum_lists($forumid);
        invalidate_forum_jump_cache();

        if ($ok) {
            stderr("Данные верны", "Категория форума успешно удалена. <a href='forummanage.php'>к админке форума</a>");
        } else {
            stderr("Данные неверны", "Нельзя удалить категорию!");
        }
    } else {
        stderr("Ошибка", "Не удалось переместить темы.");
    }
}

/* =========================== Селект выбора форума (для перемещения тем) =========================== */
function forum_select(int $currentforum = 0): void {
    $currentforum = (int)$currentforum;

    echo "<p align='center'>
            <form method='post' action='forummanage.php?action=takedelete&amp;id={$currentforum}' name='jump'>
              <input type='hidden' name='deleteall' value='true'>
              Чтобы переместить темы, выберите целевую категорию:
              <br><select name='forumid'>";

    $res = sql_query("SELECT id, name FROM forums ORDER BY name") or sqlerr(__FILE__, __LINE__);
    while ($arr = mysqli_fetch_assoc($res)) {
        $id   = (int)$arr['id'];
        $name = htmlspecialchars((string)$arr['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($id === $currentforum) continue;
        echo "<option value='{$id}'>{$name}</option>";
    }

    echo "  </select>
              <input type='submit' value='Переместить сюда...' class='btn'>
            </form>
          </p>";
}
