<?php
/*************************************************
 * Casino: BlackJack vs Users (TBDev / PHP 8.1)
 * Оптимизировано, русифицировано, защищено от инъекций
 * Без внешнего CSS (минимум инлайна для кнопок)
 *************************************************/

require_once __DIR__ . "/include/bittorrent.php";
dbconn(false);
loggedinorreturn();

global $CURUSER, $mysqli;

/*-------------------- НАСТРОЙКИ (гибкие) --------------------*/
$CFG = [
    // Доступные ставки (ключ — сумма, значение — цвет кнопки)
    'bets'        => [
        15  => '#74AE04',
        20  => '#9E9E9E',
        30  => '#0574C9',
        40  => '#DB48A2',
        50  => '#D8DB04',
        70  => '#EFA900',
        100 => '#DC0000',
    ],
    'bank_user_id'   => 1,              // кому уходит банк при «переборе у обоих»
    'pm_sender_id'   => 0,              // от кого отправлять системные ЛС
    'max_open_self'  => 10,             // максимум одновременно начатых игр у пользователя (в ожидании)
    'draw_img_path'  => 'pic/cards/',   // путь к картинкам карт
];

/*-------------------- ЛОКАЛИЗАЦИЯ --------------------*/
$L = [
    'title'      => 'Казино',
    'welcome'    => 'Добро пожаловать,',
    'game_hdr'   => 'Игра BlackJack на бонусы',
    'open_games' => 'Открытые игры:',
    'started_by' => 'Начал',
    'taken_by'   => 'Принял',
    'time'       => 'Время',
    'play'       => 'Сыграть',
    'more'       => 'Ещё',
    'stop'       => 'Хватит',
    'points'     => 'Очков',
    'nobody'     => 'Никто не выиграл',
    'bank'       => 'Банк',
    'error'      => 'Ошибка',
    'sorry'      => 'Извините',
    'not_enough' => 'У Вас не хватает средств для игры.',
    'too_many'   => 'Вы не можете начинать игру, пока у вас открыто более %d игр.',
    'same_self'  => 'Играть с самим собой нельзя.',
    'game_not_found' => 'Игра не найдена.',
    'game_finished'  => 'Вы закончили эту игру.',
    'end'        => 'Конец игры',
    'back'       => 'Назад',
];

/*-------------------- ВСПОМОГАТЕЛЬНЫЕ --------------------*/
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function i(int $v): int { return $v; }
function curuser_name(): string { global $CURUSER; return $CURUSER['username']; }
function curuser_id(): int { global $CURUSER; return (int)$CURUSER['id']; }
function user_bonus(): int { global $CURUSER; return (int)$CURUSER['bonus']; }
function insert_id(): int { global $mysqli; return (int)$mysqli->insert_id; }

if (!function_exists('get_user_id')) {
    // Универсальная подстраховка — если в твоём билде TBDev нет этой функции.
    function get_user_id(string $username): int {
        $username = sqlesc($username);
        $res = sql_query("SELECT id FROM users WHERE username = $username LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) return (int)$row['id'];
        return 0;
    }
}

/**
 * Безопасная выборка одной карты.
 * Поскольку набор небольшой (52), ORDER BY RAND() достаточно быстро.
 */
function draw_card(): ?array {
    $res = sql_query("SELECT id, points, pic FROM cards ORDER BY RAND() LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function print_glass_css_once(): void {
    static $printed = false;
    if ($printed) return;
    $printed = true;

    echo <<<CSS
<style>
  .glass-btn{
    --btn-bg: #0d6efd;               /* цвет задаём инлайном через style */
    display:inline-flex; align-items:center; justify-content:center;
    padding:8px 14px; min-width:70px; min-height:34px;
    border-radius:999px; border:1px solid rgba(255,255,255,.6);
    background:
      linear-gradient(180deg, rgba(255,255,255,.35), rgba(255,255,255,.15)) ,
      var(--btn-bg);
    color:#fff; font-weight:600; font-size:14px; line-height:1;
    box-shadow:
      inset 0 1px 0 rgba(255,255,255,.35),
      inset 0 -1px 0 rgba(0,0,0,.08),
      0 6px 18px rgba(0,0,0,.18);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    text-decoration:none; cursor:pointer; user-select:none;
    transition: transform .06s ease, box-shadow .06s ease;
  }
  .glass-btn:active{ transform: scale(.98); box-shadow:
      inset 0 1px 0 rgba(0,0,0,.12),
      0 2px 10px rgba(0,0,0,.16);
  }
  .glass-btn[disabled], .glass-btn:disabled{
    opacity:.55; cursor:not-allowed; filter:saturate(.6);
    box-shadow: inset 0 1px 0 rgba(0,0,0,.06), 0 0 0 transparent;
  }
  .bets-wrap{ display:flex; flex-wrap:wrap; gap:8px; margin:10px 0 16px; }
</style>
CSS;
}

/**
 * Рисуем кнопку ставки.
 */
function bet_button(int $bet, string $color): string {
    // гарантируем вывод CSS один раз
    print_glass_css_once();

    $val = i($bet);
    $style = "--btn-bg: {$color}";
    return "<button class='glass-btn' type='submit' name='bet' value='{$val}' style='{$style}'>{$val}</button>";
}

/**
 * Рендер шапки/подвала.
 */
function page_head(string $title = ''): void { stdhead($title ?: ''); }
function page_foot(): void { stdfoot(); }

/*-------------------- ВХОДНЫЕ ДАННЫЕ --------------------*/
$now      = time();
$game     = $_POST['game']   ?? '';
$bet_in   = isset($_POST['bet']) ? (int)$_POST['bet'] : 0;
$takegame = isset($_GET['takegame']) ? (int)$_GET['takegame'] : 0;

/*-------------------- ВАЛИДАЦИЯ СТАВКИ --------------------*/
$bet = 0;
if ($bet_in > 0 && isset($CFG['bets'][$bet_in])) {
    $bet = $bet_in;
}

/*-------------------- ОБРАБОТКА --------------------*/
if ($game === 'start' || $takegame > 0 || $game === 'cont' || $game === 'stop') {

    // START: начать игру
    if ($game === 'start') {

        if (!$bet) {
            stderr($L['error'], $L['game_not_found']);
        }

        // Проверка средств
        if (user_bonus() < $bet) {
            stderr($L['sorry'], $L['not_enough']);
        }

        // Сколько открытых у юзера
        $u = sqlesc(curuser_name());
        $resCnt = sql_query("SELECT COUNT(*) AS c FROM bj WHERE placeholder = $u AND plstat = 'waiting'");
        $rowCnt = $resCnt ? mysqli_fetch_assoc($resCnt) : ['c' => 0];
        if ((int)$rowCnt['c'] >= $CFG['max_open_self']) {
            stderr($L['error'], sprintf($L['too_many'], $CFG['max_open_self']));
        }

        // Списать бонусы
        sql_query("UPDATE users SET bonus = bonus - ".i($bet)." WHERE id = ".i(curuser_id()));

        // Первая карта
        $card = draw_card();
        if (!$card) stderr($L['error'], 'Нет карт в базе.');

        $points = (int)$card['points'];
        $cards  = (string)$card['id'];

        // Создать свою запись
        sql_query(
            "INSERT INTO bj (placeholder, points, plstat, bet, cards, date) 
             VALUES (".sqlesc(curuser_name()).", ".i($points).", 'playing', ".i($bet).", ".sqlesc($cards).", ".i($now).")"
        );
        $id = insert_id();

        // Показать ход
        page_head($L['title']);
        begin_frame($L['title']);
        echo "<h1>{$L['welcome']} <a href='userdetails.php?id=".i(curuser_id())."'>".h(curuser_name())."</a>!</h1>";
        echo "<table cellspacing='0' cellpadding='5' width='600'><tr><td>";
        echo "<form method='post' action='casino.php'>";
        echo "<table width='100%' cellspacing='0' cellpadding='5'>";
        echo "<tr><td align='center'><img src='".h($CFG['draw_img_path'].$card['pic'])."' alt='' /></td></tr>";
        echo "<tr><td align='center'><b>{$L['points']} = ".i($points)."</b></td></tr>";
        echo "<tr><td align='center'>
                <input type='hidden' name='id' value='".i($id)."' />
                <input type='hidden' name='game' value='cont' />
                <button class='glass-btn' type='submit' style='--btn-bg:#0574C9'>".h($L['more'])."</button>

              </td></tr>";
        echo "</table></form>";
        echo "</td></tr></table>";
        end_frame();
        page_foot();
        exit;
    }

    // TAKEGAME: принять чужую игру
    if ($takegame > 0) {
        $gid = $takegame;
        $res = sql_query("SELECT id, bet, gamer, placeholder FROM bj WHERE id = ".i($gid)." LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!$row) stderr($L['error'], $L['game_not_found']);

        $betVal = (int)$row['bet'];
        if (user_bonus() < $betVal) stderr($L['sorry'], $L['not_enough']);

        if (!empty($row['gamer'])) {
            header("Location: casino.php");
            exit;
        }

        if ($row['placeholder'] === curuser_name()) {
            stderr($L['error'], $L['same_self']);
        }

        // Списываем у принимающего
        sql_query("UPDATE users SET bonus = bonus - ".i($betVal)." WHERE id = ".i(curuser_id()));

        // Помечаем в оригинальной записи «кто принял»
        sql_query("UPDATE bj SET gamer = ".sqlesc(curuser_name())." WHERE id = ".i($gid));

        // Создаём запись принимающего (связка gamewithid -> id «создателя»)
        $card = draw_card();
        if (!$card) stderr($L['error'], 'Нет карт в базе.');
        sql_query(
            "INSERT INTO bj (placeholder, points, plstat, bet, cards, date, gamewithid)
             VALUES (".sqlesc(curuser_name()).", ".i((int)$card['points']).", 'playing', ".i($betVal).", ".sqlesc((string)$card['id']).", ".i($now).", ".i($gid).")"
        );
        $id = insert_id();

        // Экран хода
        page_head($L['title']);
        begin_frame($L['title']);
        echo "<h1>{$L['welcome']} <a href='userdetails.php?id=".i(curuser_id())."'>".h(curuser_name())."</a>!</h1>";
        echo "<table cellspacing='0' cellpadding='5' width='600'><tr><td>";
        echo "<form method='post' action='casino.php'>";
        echo "<table width='100%' cellspacing='0' cellpadding='5'>";
        echo "<tr><td align='center'><img src='".h($CFG['draw_img_path'].$card['pic'])."' alt='' /></td></tr>";
        echo "<tr><td align='center'><b>{$L['points']} = ".i((int)$card['points'])."</b></td></tr>";
        echo "<tr><td align='center'>
                <input type='hidden' name='id' value='".i($id)."' />
                <input type='hidden' name='game' value='cont' />
                <input type='submit' value='".h($L['more'])."' />
              </td></tr>";
        echo "</table></form>";
        echo "</td></tr></table>";
        end_frame();
        page_foot();
        exit;
    }

    // CONT: добрать карту
    if ($game === 'cont') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $res = sql_query("SELECT * FROM bj WHERE id = ".i($id)." LIMIT 1");
        $me  = $res ? mysqli_fetch_assoc($res) : null;
        if (!$me || $me['plstat'] === 'waiting') {
            stderr($L['error'], $L['game_finished']);
        }

        // Уже вытянутые карты
        $usedIds = array_values(array_filter(array_map('trim', explode(' ', (string)$me['cards']))));
        $usedIds = array_map('intval', $usedIds);

        // Тянем новую уникальную карту (для 52 карт этого достаточно)
        do {
            $card = draw_card();
            if (!$card) stderr($L['error'], 'Нет карт в базе.');
        } while (in_array((int)$card['id'], $usedIds, true));

        $newPoints = (int)$me['points'] + (int)$card['points'];
        $newCards  = trim($me['cards'].' '.$card['id']);

        sql_query("UPDATE bj SET points = ".i($newPoints).", cards = ".sqlesc($newCards)." WHERE id = ".i($id));

        // Проверки 21 / перебор
        $opponentId = (int)($me['gamewithid'] ?? 0);

        // 21 — мгновенное завершение
        if ($newPoints === 21) {
            sql_query("UPDATE bj SET plstat = 'waiting', date = ".i($now)." WHERE id = ".i($id));

            if ($opponentId > 0) {
                $r = sql_query("SELECT * FROM bj WHERE id = ".i($opponentId)." LIMIT 1");
                $a = $r ? mysqli_fetch_assoc($r) : null;
                if ($a && (int)$a['points'] !== 21) {
                    // выигрыш принимающего
                    sql_query("UPDATE users SET bonus = bonus + ".i(((int)$a['bet']) * 2)." WHERE id = ".i(curuser_id())."");
                    if (!empty($a['placeholder'])) {
                        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                  VALUES (".i($GLOBALS['CFG']['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                  ".sqlesc('Вы проиграли '.((int)$a['bet']).' пользователю '.curuser_name().' (Вы набрали '.$a['points'].' очков, '.curuser_name().' набрал 21).').", 0)");
                    }
                    sql_query("UPDATE bj SET winner = ".sqlesc(curuser_name()).", gamewithid = ".i($newPoints).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($opponentId));
                } else {
                    // ничья
                    sql_query("UPDATE bj SET winner = ".sqlesc($GLOBALS['L']['nobody']).", gamewithid = ".i($newPoints).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($opponentId));
                    sql_query("UPDATE users SET bonus = bonus + ".i((int)$a['bet'])." WHERE id = ".i(curuser_id())."");
                    if (!empty($a['placeholder'])) {
                        sql_query("UPDATE users SET bonus = bonus + ".i((int)$a['bet'])." WHERE id = ".i(get_user_id($a['placeholder']))."");
                        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                  VALUES (".i($GLOBALS['CFG']['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                  ".sqlesc('Никто не выиграл. Вы набрали '.$a['points'].' очков, '.curuser_name().' набрал '.$newPoints.' очков.').", 0)");
                    }
                }
                sql_query("DELETE FROM bj WHERE id = ".i($id));
                stderr($L['end'], "Вы набрали ".i($newPoints)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i((int)$a['points'])." очков. <a href='casino.php'>".$L['back']."</a>");
            } else {
                // 21 у начинавшего
                stderr($L['end'], "Вы набрали 21. Вам придёт личное сообщение о результате игры. <a href='casino.php'>{$L['back']}</a>");
            }
        }

        // Перебор > 21 — завершение
        if ($newPoints > 21) {
            sql_query("UPDATE bj SET plstat = 'waiting', date = ".i($now)." WHERE id = ".i($id));

            if ($opponentId > 0) {
                $r = sql_query("SELECT * FROM bj WHERE id = ".i($opponentId)." LIMIT 1");
                $a = $r ? mysqli_fetch_assoc($r) : null;

                if ($a) {
                    $aPts = (int)$a['points'];
                    $betA = (int)$a['bet'];

                    if ($aPts > 21) {
                        // перебор у обоих -> банк
                        sql_query("UPDATE bj SET winner = ".sqlesc($L['bank']).", gamewithid = ".i($newPoints).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($opponentId));
                        sql_query("UPDATE users SET bonus = bonus + ".i($betA * 2)." WHERE id = ".i($GLOBALS['CFG']['bank_user_id']));
                        if (!empty($a['placeholder'])) {
                            sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                      VALUES (".i($GLOBALS['CFG']['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                      ".sqlesc('Вашим оппонентом был '.curuser_name().'. Никто не выиграл, всё ушло в банк.').", 0)");
                        }
                        sql_query("DELETE FROM bj WHERE id = ".i($id));
                        stderr($L['end'], "Вы набрали ".i($newPoints)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i($aPts)." очков. Никто не выиграл, всё ушло в банк. <a href='casino.php'>{$L['back']}</a>");
                    }

                    if ($aPts <= 21) {
                        // победа оппонента
                        if (!empty($a['placeholder'])) {
                            sql_query("UPDATE users SET bonus = bonus + ".i($betA * 2)." WHERE username = ".sqlesc($a['placeholder']));
                            sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                      VALUES (".i($GLOBALS['CFG']['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                      ".sqlesc('Вы выиграли '.$betA.' у пользователя '.curuser_name().' (Вы набрали '.$aPts.' очков, '.curuser_name().' набрал '.$newPoints.' очков).').", 0)");
                        }
                        sql_query("UPDATE bj SET winner = ".sqlesc($a['placeholder']).", gamewithid = ".i($newPoints).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($opponentId));
                        sql_query("DELETE FROM bj WHERE id = ".i($id));
                        stderr($L['end'], "Вы набрали ".i($newPoints)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i($aPts)." очков. Вы проиграли ".$betA.". <a href='casino.php'>{$L['back']}</a>");
                    }
                }
            } else {
                // перебор у начинавшего
                stderr($L['end'], "Вы набрали ".i($newPoints)." очков (больше 21). Вам придёт личное сообщение о результате игры. <a href='casino.php'>{$L['back']}</a>");
            }
        }

        // Игра продолжается
        page_head($L['title']);
        begin_frame($L['title']);
        echo "<h1>{$L['welcome']} <a href='userdetails.php?id=".i(curuser_id())."'>".h(curuser_name())."</a>!</h1>";
        echo "<table cellspacing='0' cellpadding='5' width='600'><tr><td>";
        echo "<table width='100%' cellspacing='0' cellpadding='5'>";

        // Показать все карты
        $imgs = '';
        foreach (array_filter(array_map('trim', explode(' ', $newCards))) as $cid) {
            $r = sql_query("SELECT pic FROM cards WHERE id = ".i((int)$cid)." LIMIT 1");
            if ($r && $rr = mysqli_fetch_assoc($r)) {
                $imgs .= "<img src='".h($CFG['draw_img_path'].$rr['pic'])."' alt='' /> ";
            }
        }
        echo "<tr><td align='center'>{$imgs}</td></tr>";
        echo "<tr><td align='center'><b>{$L['points']} = ".i($newPoints)."</b></td></tr>";

        echo "<tr><td align='center'>
                <form method='post' action='casino.php' style='display:inline'>
                    <input type='hidden' name='id' value='".i($id)."' />
                    <input type='hidden' name='game' value='cont' />
                    <input type='submit' value='".h($L['more'])."' />
                </form>
                &nbsp;
                <form method='post' action='casino.php' style='display:inline' onsubmit='this.submit.disabled=true'>
                    <input type='hidden' name='id' value='".i($id)."' />
                    <input type='hidden' name='game' value='stop' />
                    <input type='submit' name='submit' value='".h($L['stop'])."' />
                </form>
              </td></tr>";

        echo "</table>";
        echo "</td></tr></table>";
        end_frame();
        page_foot();
        exit;
    }

    // STOP: остановить набор карт
    if ($game === 'stop') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rMe = sql_query("SELECT * FROM bj WHERE id = ".i($id)." LIMIT 1");
        $me  = $rMe ? mysqli_fetch_assoc($rMe) : null;
        if (!$me || $me['plstat'] === 'waiting') {
            stderr($L['error'], $L['game_finished']);
        }

        // Остановили набор
        sql_query("UPDATE bj SET plstat = 'waiting', date = ".i($now)." WHERE id = ".i($id));

        // Если я принимал игру
        if (!empty($me['gamewithid'])) {
            $gid = (int)$me['gamewithid'];
            $rA  = sql_query("SELECT * FROM bj WHERE id = ".i($gid)." LIMIT 1");
            $a   = $rA ? mysqli_fetch_assoc($rA) : null;

            if ($a) {
                $myPts = (int)$me['points'];
                $aPts  = (int)$a['points'];
                $betA  = (int)$a['bet'];

              if ($aPts === $myPts && $aPts < 22 && $myPts < 22) {
    // ничья — вернуть ставки
    sql_query("UPDATE users SET bonus = bonus + " . i($betA) . " WHERE id = " . i(curuser_id()));

    if (!empty($a['placeholder'])) {
        // вернуть ставку оппоненту по username
        sql_query("UPDATE users SET bonus = bonus + " . i($betA) . " WHERE username = " . sqlesc($a['placeholder']));

        // найти id оппонента по username и отправить ЛС
        $res = sql_query("SELECT id FROM users WHERE username = " . sqlesc($a['placeholder']) . " LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $opponentId = (int)$row['id'];
            sql_query(
                "INSERT INTO messages (sender, receiver, added, msg, poster)
                 VALUES ("
                    . i($CFG['pm_sender_id']) . ", "
                    . i($opponentId) . ", NOW(), "
                    . sqlesc('Вашим оппонентом был ' . curuser_name() . '. Никто не выиграл.') . ", 0)"
            );
        }
    }

    sql_query("UPDATE bj SET winner = " . sqlesc($L['nobody']) . ", gamewithid = " . i($myPts) . ", date = " . i($now) . ", plstat = 'finished' WHERE id = " . i($gid));
    sql_query("DELETE FROM bj WHERE id = " . i($id));

    stderr(
        $L['end'],
        "Вы набрали " . i($myPts) . " очков, вашим оппонентом был " . h($a['placeholder']) . ", он набрал " . i($aPts) . " очков. " . $L['nobody'] . ". <a href='casino.php'>{$L['back']}</a>"
    );
}


                // Победа принимающего
                if ( ($myPts > $aPts && $myPts < 22) || ($aPts > 21 && $myPts < 22) ) {
                    sql_query("UPDATE users SET bonus = bonus + ".i($betA * 2)." WHERE id = ".i(curuser_id()));
                    if (!empty($a['placeholder'])) {
                        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                  VALUES (".i($CFG['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                  ".sqlesc('Вы проиграли '.$betA.' пользователю '.curuser_name().' (Вы набрали '.$aPts.' очков, '.curuser_name().' набрал '.$myPts.' очков).').", 0)");
                    }
                    sql_query("UPDATE bj SET winner = ".sqlesc(curuser_name()).", gamewithid = ".i($myPts).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($gid));
                    sql_query("DELETE FROM bj WHERE id = ".i($id));
                    stderr($L['end'], "Вы набрали ".i($myPts)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i($aPts)." очков. Вы выиграли ".i($betA).". <a href='casino.php'>{$L['back']}</a>");
                }

                // Победа автора
                if ($aPts <= 21 && $aPts > $myPts) {
                    if (!empty($a['placeholder'])) {
                        sql_query("UPDATE users SET bonus = bonus + ".i($betA * 2)." WHERE username = ".sqlesc($a['placeholder']));
                        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                  VALUES (".i($CFG['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                  ".sqlesc('Вы выиграли '.$betA.' у пользователя '.curuser_name().' (Вы набрали '.$aPts.' очков, '.curuser_name().' набрал '.$myPts.' очков).').", 0)");
                    }
                    sql_query("UPDATE bj SET winner = ".sqlesc($a['placeholder']).", gamewithid = ".i($myPts).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($gid));
                    sql_query("DELETE FROM bj WHERE id = ".i($id));
                    stderr($L['end'], "Вы набрали ".i($myPts)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i($aPts)." очков. Вы проиграли ".i($betA).". <a href='casino.php'>{$L['back']}</a>");
                }

                // Перебор у обоих -> банк
                if ($aPts > 21 && $myPts > 21) {
                    sql_query("UPDATE bj SET winner = ".sqlesc($L['bank']).", gamewithid = ".i($myPts).", date = ".i($now).", plstat = 'finished' WHERE id = ".i($gid));
                    sql_query("UPDATE users SET bonus = bonus + ".i($betA * 2)." WHERE id = ".i($CFG['bank_user_id']));
                    if (!empty($a['placeholder'])) {
                        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                                  VALUES (".i($CFG['pm_sender_id']).", ".i(get_user_id($a['placeholder'])).", NOW(),
                                  ".sqlesc('Вашим оппонентом был '.curuser_name().'. Никто не выиграл, всё ушло в банк.').", 0)");
                    }
                    stderr($L['end'], "Вы набрали ".i($myPts)." очков, вашим оппонентом был ".h($a['placeholder']).", он набрал ".i($aPts)." очков. Никто не выиграл — всё ушло в банк. <a href='casino.php'>{$L['back']}</a>");
                }
            }

            // fallback
            header("Location: casino.php");
            exit;
        }

        // Если остановился тот, кто НАЧАЛ игру — просто ждём соперника
        header("Location: casino.php");
        exit;
    }

} else {
    /*-------------------- ЭКРАН: СПИСОК ИГР + СТАРТ --------------------*/
    page_head('Игры');
    begin_frame($L['title']);

    echo "<h2>".h($L['game_hdr'])."</h2>";

    // Форма старта игры (кнопки ставок)
    echo "<form method='post' action='casino.php' style='margin:10px 0'>";
    echo "<input type='hidden' name='game' value='start' />";
    foreach ($CFG['bets'] as $b => $color) {
        echo bet_button((int)$b, $color)." ";
    }
    echo "</form>";

    // Открытые (waiting) и завершённые (finished)
    echo "<table width='650' cellspacing='0' cellpadding='5'>";
    echo "<tr><td colspan='4' align='center'><b>".h($L['open_games'])."</b></td></tr>";
    echo "<tr>
            <td align='center' width='15%'><b>".h($L['started_by'])."</b></td>
            <td align='center' width='15%'><b>".h($L['taken_by'])."</b></td>
            <td align='center' width='20%'><b>".h($L['time'])."</b></td>
            <td align='center' width='40%'><b>".h($L['play'])."</b></td>
          </tr>";

    // WAITING
    $qr = sql_query("SELECT placeholder, gamer, id, date, winner, bet 
                     FROM bj 
                     WHERE plstat = 'waiting' 
                     ORDER BY gamer ASC, date DESC");
    while ($arr = $qr ? mysqli_fetch_assoc($qr) : null) {
        $self   = ($arr['placeholder'] === curuser_name() || !empty($arr['gamer'])) ? 'disabled' : '';
        $color  = $CFG['bets'][(int)$arr['bet']] ?? '#444';
        $winner = (!empty($arr['gamer']) && empty($arr['winner'])) ? "&nbsp;---><b>&nbsp;???????</b>&nbsp;" : '';

        $btnStyle = "width:70px;height:18px;background:$color;color:#fff;font-weight:normal;border:1px solid #fff;cursor:pointer";
print_glass_css_once();
$disabled = ($self ? ' disabled' : '');
$btn = "<button class='glass-btn' style='--btn-bg:{$color}' onclick=\"window.location.href='casino.php?takegame=".i((int)$arr['id'])."'\"{$disabled}>".i((int)$arr['bet'])."</button>";

        echo "<tr>
                <td align='center'>".h($arr['placeholder'])."</td>
                <td align='center'>".h($arr['gamer'])."</td>
                <td align='center'>".date("Y-m-d H:i:s", (int)$arr['date'])."</td>
                <td align='".($winner ? "left" : "center")."'>"
                    .($winner ? "<span style='margin-left:70px;display:inline-block'></span>" : "")
                    .$btn.$winner.
               "</td>
              </tr>";
    }

    // FINISHED (последние 50)
    $qr2 = sql_query("SELECT placeholder, gamer, points, id, date, winner, bet, gamewithid 
                      FROM bj 
                      WHERE plstat = 'finished' 
                      ORDER BY date DESC 
                      LIMIT 50");
    while ($arr = $qr2 ? mysqli_fetch_assoc($qr2) : null) {
        $bg   = (curuser_name() === $arr['gamer'] || curuser_name() === $arr['placeholder']) ? " style='background:#F5F4EA'" : "";
        $color = $CFG['bets'][(int)$arr['bet']] ?? '#444';
        $winner = $arr['winner'] ? "&nbsp;---><b>&nbsp;".h($arr['winner'])."</b>&nbsp;" : '';
        $pts = $arr['winner'] ? " ".i((int)$arr['points'])." | ".i((int)$arr['gamewithid']) : '';
        $btnStyle = "width:70px;height:18px;background:$color;color:#fff;font-weight:normal;border:1px solid #fff;cursor:pointer";
       print_glass_css_once();
$btn = "<button class='glass-btn' style='--btn-bg:{$color}' disabled>".i((int)$arr['bet'])."</button>";

        echo "<tr>
                <td$bg align='center'>".h($arr['placeholder'])."</td>
                <td$bg align='center'>".h($arr['gamer'])."</td>
                <td$bg align='center'>".date("Y-m-d H:i:s", (int)$arr['date'])."</td>
                <td$bg align='".($winner ? "left" : "center")."'>"
                    .($winner ? "<span style='margin-left:30px;display:inline-block'></span>" : "")
                    .$btn.$winner.$pts.
               "</td>
              </tr>";
    }

    echo "</table>";

    end_frame();
    page_foot();
}
