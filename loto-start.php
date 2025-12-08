<?php
require_once "include/bittorrent.php";

dbconn(true);
loggedinorreturn();
parked();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка доступа", "У вас нет прав для выполнения этого действия.");
}

stdhead("Розыгрыш Супер Лото вручную");

// ===== утилиты =====
function loto_cfg_active(): int {
    $res = sql_query("SELECT value FROM config WHERE config = 'active_super_loto'") or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_assoc($res);
    return isset($row['value']) ? (int)$row['value'] : 0;
}

function loto_active_tickets_count(): int {
    $res = sql_query("SELECT COUNT(*) AS c FROM super_loto_tickets WHERE active = 0") or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_assoc($res);
    return isset($row['c']) ? (int)$row['c'] : 0;
}

// ===== обработка POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['type'] ?? '') === 'loto') {
    $force = isset($_POST['force']) ? (int)$_POST['force'] : 0;

    $cfgActive   = loto_cfg_active();
    $ticketsCnt  = loto_active_tickets_count();

    begin_frame("Розыгрыш Супер Лото");

    if ($ticketsCnt <= 0) {
        stdmsg("Информация", "Активных билетов нет. Нечего разыгрывать.");
        end_frame();
        stdfoot();
        exit;
    }

    if ($cfgActive == 1 || $force === 1) {
        // запускаем розыгрыш (скрипт розыгрыша должен сам всё сделать и логировать)
        require_once "get_loto_winners.php";

        // по вашей семантике после розыгрыша флаг = 0 (игра завершена)
        sql_query("UPDATE config SET value = 0 WHERE config = 'active_super_loto'") or sqlerr(__FILE__, __LINE__);

        stdmsg("Успех", "Супер Лото успешно разыграно!");
    } else {
        stdmsg(
            "Информация",
            "Флаг активной игры выключен (active_super_loto = 0).<br>Вы можете запустить форс-розыгрыш из формы ниже."
        );
    }

    end_frame();
    stdfoot();
    exit;
}

// ===== форма =====
$cfgActive  = loto_cfg_active();
$ticketsCnt = loto_active_tickets_count();

begin_frame("Розыгрыш Супер Лото");

echo '
<form method="post" action="loto-start.php" style="text-align:center;">
  <table class="main" border="0" cellspacing="5" cellpadding="5" align="center">
    <tr>
      <td colspan="2" style="padding:6px 0;">
        Текущий статус: активных билетов — <b>' . (int)$ticketsCnt . '</b>, флаг <code>active_super_loto</code> = <b>' . (int)$cfgActive . '</b>.
      </td>
    </tr>
    <tr>
      <td><input type="radio" name="type" value="loto" checked></td>
      <td>Разыграть Супер Лото</td>
    </tr>
    <tr>
      <td><input type="checkbox" name="force" value="1"></td>
      <td>Игнорировать флаг <code>active_super_loto</code> и запустить розыгрыш, если есть активные билеты</td>
    </tr>
    <tr>
      <td colspan="2" align="center" style="padding-top:10px;">
        <button type="submit">Выполнить действие</button>
      </td>
    </tr>
  </table>
</form>
';

end_frame();
stdfoot();
