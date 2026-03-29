<?php
require_once 'include/bittorrent.php';
require_once 'include/super_loto_lib.php';
dbconn(false);
loggedinorreturn();
stdhead('Супер Лото');

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = super_loto_csrf_token();
?>
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/ajax.js"></script>

<?php begin_frame("Супер Лото 5 из 36"); ?>
<style type="text/css">
  .loto-shell{padding:8px 0 4px;font:inherit;color:inherit}
  .loto-title{margin:0 0 12px;text-align:center;font:inherit;font-weight:700;color:inherit}
  .loto-grid-wrap{
    display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap;
    padding:10px 12px 6px;border-radius:10px;background:rgba(255,255,255,.18)
  }
  .loto-grid-box{padding:4px 0}
  .loto-grid{border-collapse:separate;border-spacing:6px}
  .loto-ball{
    width:34px;height:34px;text-align:center;vertical-align:middle;cursor:pointer;
    background:url("pic/super_loto/bg.png") center/cover no-repeat;color:#334155;
    font:inherit;font-weight:700;line-height:1;
    transition:transform .12s ease,color .12s ease,filter .12s ease
  }
  .loto-ball:hover{color:#195b92;transform:translateY(-1px);filter:brightness(1.03)}
  .loto-ball.marked{background-image:url("pic/super_loto/marked_bg.png");color:#195b92}
  .loto-picked{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
  .loto-picked-cell{
    width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;
    background:url("pic/super_loto/active_bg.png") center/cover no-repeat;
    font:inherit;font-weight:700;line-height:1;color:#1f2937
  }
  .loto-side{max-width:420px;font:inherit;color:inherit}
  .loto-note{line-height:1.6;color:inherit}
  .loto-buy{margin-top:14px;padding-top:10px;border-top:1px solid rgba(0,0,0,.08)}
  .loto-buy-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}
  .loto-label{font-weight:700;color:inherit}
  .loto-buy select,
  .loto-buy input[type="button"]{
    margin-top:0 !important;
    font:inherit !important
  }
  .loto-buy select{
    min-width:92px;height:30px;padding:4px 8px;border:1px solid #b9c6d3;border-radius:6px;
    background:#fff;color:#31465b;box-shadow:none
  }
  .loto-buy input[type="button"]{
    height:31px;padding:0 14px;border:1px solid #90a1b3;border-radius:6px;
    background:linear-gradient(#fdfefe,#dfe7ee) !important;color:#31465b !important;
    font-weight:700 !important;box-shadow:none !important;text-shadow:none !important
  }
  .loto-buy input[type="button"]:hover{background:linear-gradient(#ffffff,#d5dee7) !important}
  .loto-buy input[type="button"]:disabled{opacity:.65;cursor:default}
  #price_info{
    display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border:1px solid #b9c6d3;
    border-radius:6px;background:#f7fafc;color:#31465b;font-weight:700
  }
  .loto-empty{padding:8px 0;text-align:center;color:#6b7d92}
  .loto-highlight{color:#b91c1c;font-weight:700}
  #bay_result{display:block;margin-top:12px;font:inherit}
</style>

<script type="text/javascript">
  // ================== ЛОГИКА UI ==================
  var selected_numbers = 0;
  var isSubmitting = false; // защита от двойной отправки
  var lotoCsrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function show_price() {
    if (selected_numbers === 5) {
      $("ticket_price").style.display = "inline";
      $("bay_button").style.display   = "inline";
    } else {
      $("ticket_price").style.display = "none";
      $("bay_button").style.display   = "none";
    }
  }

  function mark(el_id) {
    var number = $("number_" + el_id);
    if (!number) return;
    var isMarked = number.hasClassName ? number.hasClassName("marked") : /(^|\s)marked(\s|$)/.test(number.className);

    if (!isMarked && selected_numbers < 5) {
      number.className = "marked loto-ball";
      selected_numbers += 1;

      // кладём номер в первую пустую ячейку выбранных
      for (var i = 1; i <= 5; i++) {
        if ($("num_" + i).innerHTML === "") {
          $("num_" + i).update(el_id);
          break;
        }
      }
    }
    else if (isMarked && selected_numbers > 0) {
      number.className = "default loto-ball";
      selected_numbers -= 1;

      // удаляем этот номер из выбранных
      for (var i = 1; i <= 5; i++) {
        if ($("num_" + i).innerHTML == el_id) {
          $("num_" + i).update("");
          break;
        }
      }
    }
    show_price();
  }

  function update_price_info() {
    $("price_info").update($("price").value + " GB");
    $("price").style.display = "none";
  }

  function show_price_select() {
    $("price_info").update("");
    $("price").style.display = "inline";
  }

  function load_user_tickets() {
    $("user_ticket_info") && $("user_ticket_info").update("");
    var ajax = new tbdev_ajax();
    ajax.onShow('');
    ajax.requestFile = "super_loto_edit.php";
    ajax.setVar("action", "load_tickets");
    ajax.setVar("csrf_token", lotoCsrfToken);
    ajax.method  = "POST";
    ajax.element = "user_tickets";
    ajax.sendAJAX("");
  }

  function load_tickets_stats() {
    $("tickets_stat_info") && $("tickets_stat_info").update("");
    var ajax = new tbdev_ajax();
    ajax.onShow('');
    ajax.requestFile = "super_loto_edit.php";
    ajax.setVar("action", "load_stats");
    ajax.setVar("csrf_token", lotoCsrfToken);
    ajax.method  = "POST";
    ajax.element = "tickets_statistics";
    ajax.sendAJAX("");
  }

  function bay_ticket() {
    if (isSubmitting) return; // уже отправляем

    // проверяем, что выбрано ровно 5 чисел и нет пустот
    if (selected_numbers !== 5) {
      $("bay_result").update("Нужно выбрать ровно 5 чисел.");
      return;
    }
    var picks = [];
    for (var i = 1; i <= 5; i++) {
      var v = $("num_" + i).innerHTML.trim();
      if (v === "") {
        $("bay_result").update("Нужно выбрать ровно 5 чисел.");
        return;
      }
      picks.push(v);
    }
    var combination = picks.join(".");

    var price = $("price").value;
    if (!price || isNaN(price) || parseInt(price, 10) <= 0) {
      $("bay_result").update("Выберите корректную ставку.");
      return;
    }

    // UI: скрываем форму, показываем загрузку и блокируем кнопку
    $("bay_form").style.display = "none";
    $("bay_result").update("Загрузка. Подождите пожалуйста...");
    var btn = $("bay_button");
    if (btn) { btn.disabled = true; btn.value = "Покупка..."; }

    isSubmitting = true;

    var ajax = new tbdev_ajax();
    ajax.onShow('');
    ajax.requestFile = "super_loto_edit.php";
    ajax.setVar("action", "bay_ticket");
    ajax.setVar("combination", combination);
    ajax.setVar("price", price);
    ajax.setVar("csrf_token", lotoCsrfToken);
    ajax.method  = "POST";
    ajax.element = "bay_result";
    ajax.onCompletion = function () {
      // после ответа — обновим списки и снова покажем форму покупки
      load_user_tickets();
      load_tickets_stats();
      isSubmitting = false;
      if (btn) { btn.disabled = false; btn.value = "Купить билет"; }
    };
    ajax.sendAJAX("");
  }

  function show_bay_form() {
    $("bay_form").style.display   = "block";
    $("ticket_price").style.display = "none";
    $("bay_button").style.display   = "none";
    $("price").style.display        = "block";
    $("bay_result").update("");
    $("price_info").update("");
    $("default_price").selected = true;

    selected_numbers = 0;
    for (var i = 1; i <= 5; i++) { $("num_" + i).update(""); }
    for (var j = 1; j <= 36; j++) {
      var cell = $("number_" + j);
      var cellMarked = cell && (cell.hasClassName ? cell.hasClassName("marked") : /(^|\s)marked(\s|$)/.test(cell.className));
      if (cellMarked) {
        cell.className = "default loto-ball";
      }
    }
  }
</script>

<div class="loto-shell">
<span id="bay_form">
  <div class="loto-title">Выберите комбинацию из 5 номеров</div>
  <div class="loto-grid-wrap">
      <div class="loto-grid-box" style="border:none" width="45%" valign="top">
        <table class="loto-grid" style="padding:15px;">
          <tr>
          <?php
            $j = 0;
            for ($i = 1; $i <= 36; $i++) {
              $j++;
              echo '<td width="30" height="30" align="center"
                        id="number_'.$i.'" onClick="mark('.$i.')" class="default loto-ball">'.$i.'</td>';
              if ($j == 6) { echo '</tr><tr>'; $j = 0; }
            }
          ?>
          </tr>
        </table>
      </div>
      <div class="loto-side" style="border:none" valign="top" align="left">
        <div class="loto-picked">
            <span id="num_1" class="loto-picked-cell"></span>
            <span id="num_2" class="loto-picked-cell"></span>
            <span id="num_3" class="loto-picked-cell"></span>
            <span id="num_4" class="loto-picked-cell"></span>
            <span id="num_5" class="loto-picked-cell"></span>
        </div>
        <div class="loto-note">
        Ваш выигрыш зависит от количества угаданных цифр:<br>
        <b>5 из 5 + поочередность</b> - 5000% от ставки<br>
        <b>5 из 5</b> - 1000% от ставки<br>
        <b>4 из 5</b> - 500% от ставки<br>
        <b>3 из 5</b> - 300% от ставки<br>
        <b>2 из 5</b> - 200% от ставки<br>
        <b>1 из 5</b> - 100% от ставки<br>
        <br /><br />
        <span class="loto-highlight">Розыгрыш проходит каждый день в 18 <sup>00</sup></span>
        </div>
        <div class="loto-buy">
        <span id="ticket_price" style="display:none">
          <span class="loto-label">Ваша ставка:</span>
          <span class="loto-buy-row">
          <select name="price" id="price" onChange="update_price_info()">
            <option value="1"  id="default_price">1 GB</option>
            <option value="2">2 GB</option>
            <option value="3">3 GB</option>
            <option value="4">4 GB</option>
            <option value="5">5 GB</option>
            <option value="6">6 GB</option>
            <option value="7">7 GB</option>
            <option value="8">8 GB</option>
            <option value="9">9 GB</option>
            <option value="10">10 GB</option>
          </select>
          <span id="price_info" style="cursor:pointer" onClick="show_price_select()"
                title="Нажмите чтобы выбрать другую ставку"></span>
          </span>
        </span>
        <div class="loto-buy-row" style="margin-top:14px;">
        <input type="button" name="bay_ticket" value="Купить билет"
               onClick="bay_ticket()" id="bay_button" style="display:none">
        </div>
        </div>
      </div>
  </div>
</span>
<span id="bay_result"></span>
</div>
<?php end_frame(); ?>


<?php begin_frame("Ваши билеты"); ?>
<br><br>
<?php
  $uid = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;

  // Берём только нужные поля
  $stmt = $mysqli->prepare('
      SELECT ticket_id, combination, price
      FROM super_loto_tickets
      WHERE user_id = ? AND active = 0
      ORDER BY ticket_id DESC
  ');
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $num = $res->num_rows;

  if ($num > 0) {
?>
<table width="90%" id="user_tickets">
  <tr height="30">
    <td align="center" width="10%" class="colhead"><b>Nr.</b></td>
    <td align="center" width="40%" class="colhead"><b>Комбинация</b></td>
    <td align="center" width="30%" class="colhead"><b>Ставка</b></td>
  </tr>
  <?php
    $i = 0;
    while ($ticket = $res->fetch_assoc()) {
        $i++;
        $nums = explode('.', (string)$ticket['combination']);
        // гарантия 5 чисел
        for ($k = 0; $k < 5; $k++) {
            if (!isset($nums[$k])) $nums[$k] = '';
        }

        echo '<tr>
                <td class="lol" align="center" width="10%"><b>' . $i . '</b></td>
                <td class="lol" align="center" width="40%">
                  <table><tr>';
        for ($k = 0; $k < 5; $k++) {
            echo '<td class="lol" width="14" height="19" style="padding:4" align="center" background="pic/super_loto/bg.png">'
               . $h($nums[$k]) . '</td>';
        }
        echo     '</tr></table>
                </td>
                <td class="lol" align="center" width="30%">' . $h((int)$ticket['price']) . ' GB</td>
              </tr>';
    }
  ?>
</table>
<span id="user_ticket_info"></span>
<?php
  } else {
      echo '<span id="user_ticket_info" class="loto-empty">Вы ещё не купили ни одного лотерейного билета.</span>';
      echo '<table width="90%" id="user_tickets"></table>';
  }
  $stmt->close();
?>
<br><br>
<?php end_frame(); ?>


<?php begin_frame("Статистика продаж"); ?>
<br><br>
<?php
  // Один агрегирующий запрос вместо DISTINCT + двух функций (убираем N+1)
  $sql = '
      SELECT
          t.user_id,
          COUNT(*)                    AS tickets_cnt,
          COALESCE(SUM(t.price), 0)   AS total_price,
          u.username,
          u.class
      FROM super_loto_tickets t
      JOIN users u ON u.id = t.user_id
      WHERE t.active = 0
      GROUP BY t.user_id, u.username, u.class
      ORDER BY total_price DESC, tickets_cnt DESC
  ';
  $res2 = sql_query($sql);
  $num2 = mysqli_num_rows($res2);
?>

<?php if ($num2 > 0) { ?>
  <table width="90%" id="tickets_statistics">
    <tr height="30">
      <td align="center" width="10%" class="colhead"><b>Nr.</b></td>
      <td align="center" width="40%" class="colhead"><b>Пользователь</b></td>
      <td align="center" width="30%" class="colhead"><b>Куплено билетов</b></td>
      <td align="center" width="20%" class="colhead"><b>Общая ставка</b></td>
    </tr>
    <?php
      $i = 0;
      while ($row = mysqli_fetch_assoc($res2)) {
          $i++;
          $uid2   = (int)$row['user_id'];
          $uname  = (string)$row['username'];
          $class  = (int)$row['class'];
          $cnt    = (int)$row['tickets_cnt'];
          $total  = (int)$row['total_price'];

          // Цветной ник, если есть хелпер
          if (function_exists('get_user_class_color')) {
              $nameHtml = get_user_class_color($class, $uname);
          } else {
              $nameHtml = $h($uname);
          }

          echo '<tr>
                  <td class="lol" align="center" width="10%"><b>' . $i . '</b></td>
                  <td class="lol" align="center" width="40%">
                    <a href="userdetails.php?id=' . $uid2 . '">' . $nameHtml . '</a>
                  </td>
                  <td class="lol" align="center" width="30%">' . $h($cnt) . '</td>
                  <td class="lol" align="center" width="20%">' . $h($total) . ' GB</td>
                </tr>';
      }
    ?>
  </table>
  <span id="tickets_stat_info"></span>
<?php } else {
  echo '<span id="tickets_stat_info">Нет проданных билетов!</span>';
  echo '<table width="90%" id="tickets_statistics"></table>';
} ?>
<br><br>
<?php end_frame(); ?>



<?php begin_frame("Статистика выигрышных билетов"); ?>
<br><br>
<?php
  // Палитра по количеству совпадений (win_num + jackpot)
  $color = [
      6 => 'green',
      5 => 'blue',
      4 => 'maroon',
      3 => 'pink',
      2 => 'black',
      1 => 'gray'
  ];

  // 1) Последний розыгрыш: дата и выигрышная комбинация
  // (берём по winner_id DESC; если логика по дате иная — можно заменить на MAX(date))
  $last = sql_query("SELECT date, win_combination FROM super_loto_winners ORDER BY winner_id DESC LIMIT 1");
  if (!$last || mysqli_num_rows($last) === 0) {
      echo 'Нет победителей!';
  } else {
      $lastRow = mysqli_fetch_assoc($last);
      $lastDateRaw = (string)($lastRow['date'] ?? '');
      $winCombRaw  = (string)($lastRow['win_combination'] ?? '');

      // форматируем дату: дд.мм.гггг (если можно разбить по '-')
      $dateHuman = $lastDateRaw;
      $dat = explode('-', $lastDateRaw);
      if (count($dat) === 3) {
          $dateHuman = $h($dat[2] . '.' . $dat[1] . '.' . $dat[0]);
      } else {
          $dateHuman = $h($lastDateRaw);
      }

      // приводим комбинацию к массиву
      $comb = $winCombRaw !== '' ? explode('.', $winCombRaw) : [];

      // 2) Все победители этого розыгрыша, сортировка по значимости
      $wq = "
          SELECT
              w.user_id,
              w.combination,
              w.price,
              w.win_num,
              w.jackpot,
              w.winner_id,
              u.username,
              u.class
          FROM super_loto_winners w
          LEFT JOIN users u ON u.id = w.user_id
          WHERE w.date = '" . mysqli_real_escape_string($mysqli, $lastDateRaw) . "'
          ORDER BY (w.win_num + w.jackpot) DESC, w.price DESC, w.winner_id ASC
      ";
      $result = sql_query($wq);
      $num = $result ? mysqli_num_rows($result) : 0;

      // Шапка + факт выигрышной комбинации
      echo 'Выигрышный номер:&nbsp;<b>' . $h(implode(' ', $comb)) . '</b>&nbsp;&nbsp;(' . $dateHuman . ")<br><br>\n";

      if ($num > 0) {
          echo '<table width="90%">';
          echo '  <tr height="30">
                    <td align="center" width="10%" class="colhead"><b>Nr.</b></td>
                    <td align="center" width="30%" class="colhead"><b>Пользователь</b></td>
                    <td align="center" width="40%" class="colhead"><b>Комбинация</b></td>
                    <td align="center" width="10%" class="colhead"><b>Ставка</b></td>
                    <td align="center" width="10%" class="colhead"><b>Совп. номеров</b></td>
                  </tr>';

          $i = 0;
          while ($winner = mysqli_fetch_assoc($result)) {
              $i++;
              $uid   = (int)$winner['user_id'];
              $combW = explode('.', (string)$winner['combination']);
              $price = (int)$winner['price'];
              $wn    = (int)$winner['win_num'];
              $jp    = (int)$winner['jackpot'];
              $score = $wn + $jp;

              // цвет по количеству совпадений (с дефолтом)
              $col = isset($color[$score]) ? $color[$score] : 'black';

              // Ник: если есть ваш хелпер — используем; иначе — просто экранированный username
              if (function_exists('get_user_class_color')) {
                  $nameHtml = get_user_class_color((int)($winner['class'] ?? 0), (string)($winner['username'] ?? ''));
              } else {
                  $nameHtml = $h((string)($winner['username'] ?? ''));
              }

              echo '  <tr height="30">
                        <td class="lol" align="center" width="10%"><b>' . $i . '</b></td>
                        <td class="lol" align="center" width="30%">
                          <a href="userdetails.php?id=' . $uid . '">' . $nameHtml . '</a>
                        </td>
                        <td class="lol" align="center" width="40%">
                          <b><span style="color:' . $h($col) . ';">' . $h(implode(' ', $combW)) . '</span></b>
                        </td>
                        <td class="lol" align="center" width="10%">' . $h($price) . '&nbsp;GB</td>
                        <td class="lol" align="center" width="10%">' . $h($wn) . '</td>
                      </tr>';
          }

          echo '</table>';
      } else {
          echo 'Нет победителей!';
      }
  }
?>
<br><br>
<?php end_frame(); ?>

<div id="loading-layer" style="display:none; font-family: Lucida Sans Unicode; font-size:11px; width:200px;
     height:50px; background:#EDFCEF; padding:10px; text-align:center; border:1px solid #000">
  <div style="font-weight:bold" id="loading-layer-text">
    <img src="pic/load.gif" border="0" align="absmiddle" width="14" height="14" alt="">
    <span style="color:red"> Загрузка. Пожалуйста, подождите...</span>
  </div>
  <img src="pic/loading.gif" border="0" />
</div>

<?php stdfoot(); ?>








