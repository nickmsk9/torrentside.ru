<?php
  require "include/bittorrent.php";
  dbconn(false);
  loggedinorreturn();
  stdhead("Супер Лото");
?>
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/ajax.js"></script>

<?php begin_frame("Супер Лото 5 из 36"); ?>
<br><br>
<style type="text/css">
  td.default  { color:#333333; }
  td.marked   { color:#0099FF; font-weight:bold; }
  td.default:hover { color:#0099FF; font-weight:bold; }
  td.marked:hover  { color:#333333; font-weight:bold; }
</style>

<script type="text/javascript">
  // ================== ЛОГИКА UI ==================
  var selected_numbers = 0;
  var isSubmitting = false; // защита от двойной отправки

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

    if (number.className === "default" && selected_numbers < 5) {
      number.className = "marked";
      number.style.background = "pic/super_loto/marked_bg.png";
      selected_numbers += 1;

      // кладём номер в первую пустую ячейку выбранных
      for (var i = 1; i <= 5; i++) {
        if ($("num_" + i).innerHTML === "") {
          $("num_" + i).update(el_id);
          break;
        }
      }
    }
    else if (number.className === "marked" && selected_numbers > 0) {
      number.className = "default";
      number.style.background = "pic/super_loto/bg.png";
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
      if (cell && cell.className === "marked") {
        cell.className = "default";
        cell.style.background = "pic/super_loto/bg.png";
      }
    }
  }
</script>

<span id="bay_form">
  <center><b>Выберите комбинацию из 5 номеров</b></center>
  <br>
  <table width="100%">
    <tr>
      <td style="border:none" width="45%" valign="top">
        <table style="padding:15px;">
          <tr>
          <?php
            $j = 0;
            for ($i = 1; $i <= 36; $i++) {
              $j++;
              echo '<td width="30" height="30" align="center" style="cursor:pointer;" background="pic/super_loto/bg.png"
                        id="number_'.$i.'" onClick="mark('.$i.')" class="default">'.$i.'</td>';
              if ($j == 6) { echo '</tr><tr>'; $j = 0; }
            }
          ?>
          </tr>
        </table>
      </td>
      <td style="border:none" valign="top" align="left">
        <table cellspacing="5">
          <tr>
            <td id="num_1" width="30" height="30" align="center" background="pic/super_loto/active_bg.png"
                style="font-weight:bold; border:none; background-repeat:no-repeat; padding-top:7px"></td>
            <td id="num_2" width="30" height="30" align="center" background="pic/super_loto/active_bg.png"
                style="font-weight:bold; border:none; background-repeat:no-repeat; padding-top:7px"></td>
            <td id="num_3" width="30" height="30" align="center" background="pic/super_loto/active_bg.png"
                style="font-weight:bold; border:none; background-repeat:no-repeat; padding-top:7px"></td>
            <td id="num_4" width="30" height="30" align="center" background="pic/super_loto/active_bg.png"
                style="font-weight:bold; border:none; background-repeat:no-repeat; padding-top:7px"></td>
            <td id="num_5" width="30" height="30" align="center" background="pic/super_loto/active_bg.png"
                style="font-weight:bold; border:none; background-repeat:no-repeat; padding-top:7px"></td>
          </tr>
        </table>
        <br>
        Ваш выигрыш зависит от количества угаданных цифр:<br>
        <b>5 из 5 + поочередность</b> - 5000% от ставки<br>
        <b>5 из 5</b> - 1000% от ставки<br>
        <b>4 из 5</b> - 500% от ставки<br>
        <b>3 из 5</b> - 300% от ставки<br>
        <b>2 из 5</b> - 200% от ставки<br>
        <b>1 из 5</b> - 100% от ставки<br>
        <br /><br />
        <b><big><font color="#FF0000">!</font></big> Розыгрыш проходит каждый день в 18 <sup>00</sup></b>
        <br>
        <span id="ticket_price" style="display:none">
          Ваша ставка:
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
        <br><br><br>
        <input type="button" name="bay_ticket" value="Купить билет"
               onClick="bay_ticket()" id="bay_button" style="display:none">
      </td>
    </tr>
  </table>
</span>
<span id="bay_result"></span>
<br><br>
<?php end_frame(); ?>


<?php begin_frame("Ваши билеты"); ?>
<br><br>
<?php
  global $mysqli, $CURUSER;

  // Экранирование
  $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
      echo '<span id="user_ticket_info">Вы ещё не купили ни одного лотерейного билета!</span>';
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
  // Экранируем вывод
  $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
          SELECT user_id, combination, price, win_num, jackpot
          FROM super_loto_winners
          WHERE date = '" . mysqli_real_escape_string($mysqli, $lastDateRaw) . "'
          ORDER BY (win_num + jackpot) DESC, price DESC, winner_id ASC
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
                  $nameHtml = get_user_class_color((int)get_user('class', $uid), (string)get_user('username', $uid));
              } else {
                  $nameHtml = $h((string)get_user('username', $uid));
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
    <img src="pic/class_success.gif" border="0" align="absmiddle" width="14">
    <span style="color:red"> Загрузка. Пожалуйста, подождите...</span>
  </div>
  <img src="pic/loading.gif" border="0" />
</div>

<?php stdfoot(); ?>













