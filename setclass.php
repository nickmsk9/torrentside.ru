<?php

require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

global $memcached;

// === Функция полной перезагрузки CURUSER и обновления кэша ===
function reload_user(int $userId): void {
    global $CURUSER, $memcached;

    $res = sql_query("SELECT * FROM users WHERE id = $userId LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        if (isset($row['override_class']) && $row['override_class'] < 255) {
            $row['real_class'] = $row['class'];
            $row['class'] = $row['override_class'];
        }

        $CURUSER = $row;
        $_SESSION['curuser'] = $row;

        if ($memcached instanceof Memcached) {
            $memcached->set("curuser_{$userId}", $row, 300);
            $memcached->set("user_session_{$userId}", $row, 300);

            $pass = $_COOKIE['pass'] ?? '';
            if (!empty($pass)) {
                $memcached->set("user_session_{$userId}_{$pass}", $row, 60);
            }
        }
    }
}

// === Удаление кэша пользователя ===
function clear_user_login_cache(int $userId): void {
    global $memcached;

    if (!($memcached instanceof Memcached)) return;

    $pass = $_COOKIE['pass'] ?? '';

    $memcached->delete("curuser_$userId");
    $memcached->delete("user_session_$userId");
    if (!empty($pass)) {
        $memcached->delete("user_session_{$userId}_{$pass}");
    }
}

// === Проверка доступа ===
if ($CURUSER['class'] < UC_ADMINISTRATOR) {
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);
}

if ($CURUSER['override_class'] != 255) {
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);
}

// === Обработка запроса смены класса ===
if (isset($_GET['action']) && $_GET['action'] === 'editclass') {
    $newclass = (int)($_GET['class'] ?? 0);

    if ($newclass > $CURUSER['class']) {
        stderr($tracker_lang['error'], $tracker_lang['class_override_denied']);
    }

    $returnto = htmlspecialchars($_GET['returnto'] ?? 'index.php');
    $userId = (int)$CURUSER['id'];

    sql_query("UPDATE users SET override_class = " . sqlesc($newclass) . " WHERE id = $userId") or sqlerr(__FILE__, __LINE__);

    // Удаление и перезагрузка CURUSER
    clear_user_login_cache($userId);
    reload_user($userId);

    header("Location: $returnto");
    exit;
}

// === Отображение формы ===
stdhead("Смена класса");
begin_frame("Смена класса");
?>

<form method="get" action="setclass.php">
    <input type="hidden" name="action" value="editclass">
    <input type="hidden" name="returnto" value="userdetails.php?id=<?= (int)$CURUSER['id'] ?>">

    <table width="300" border="1" cellspacing="5" cellpadding="5">
        <tr>
            <td>Выберите класс:</td>
            <td align="left">
                <select name="class">
                    <?php
                    $maxclass = get_user_class() - 1;
                    for ($i = 0; $i <= $maxclass; ++$i) {
                        echo "<option value=\"$i\">" . htmlspecialchars(get_user_class_name($i)) . "</option>\n";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                <input type="submit" class="btn" value="Сменить класс">
            </td>
        </tr>
    </table>
</form>

<?php
end_frame();
stdfoot();
?>
