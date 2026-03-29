<?php
require_once "include/bittorrent.php";
require_once "include/super_loto_lib.php";

dbconn(true);
loggedinorreturn();
parked();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка доступа", "У вас нет прав для выполнения этого действия.");
}

stdhead("Розыгрыш Супер Лото вручную");

// ===== обработка POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['type'] ?? '') === 'loto') {
    if (!super_loto_verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        stderr('Ошибка безопасности', 'Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }

    $force = isset($_POST['force']) ? (int)$_POST['force'] : 0;

    $cfgActive   = super_loto_cfg_active();
    $ticketsCnt  = super_loto_active_tickets_count();

    begin_frame("Розыгрыш Супер Лото");

    if ($ticketsCnt <= 0) {
        stdmsg("Информация", "Активных билетов нет. Нечего разыгрывать.");
        end_frame();
        stdfoot();
        exit;
    }

    if ($cfgActive == 1 || $force === 1) {
        $result = super_loto_run_draw([
            'strict_schedule' => false,
            'log_file' => 'super_loto.log',
        ]);
        if ($result['ok']) {
            stdmsg("Успех", "Супер Лото успешно разыграно!");
        } else {
            stdmsg("Информация", htmlspecialchars((string)$result['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
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
$cfgActive  = super_loto_cfg_active();
$ticketsCnt = super_loto_active_tickets_count();
$csrf = super_loto_csrf_token();

begin_frame("Розыгрыш Супер Лото");

echo '
<form method="post" action="loto-start.php" style="text-align:center;">
  <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">
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
