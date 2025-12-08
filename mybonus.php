<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
dbconn(false);
loggedinorreturn();

/** @var mysqli $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) die('DB handle ($mysqli) is not available');

// ============ helpers ============
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// CSRF (soft)
function csrf_token(): string {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    return '';
}
function csrf_check_soft(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['csrf_token'])) {
        $ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
        if (!$ok) stderr('Ошибка безопасности', 'Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
}

// форматирование
function format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB','MB','GB','TB','PB']; $i=0; $val = $bytes / 1024;
    while ($val >= 1024 && $i < count($units)-1) { $val /= 1024; $i++; }
    return rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') . ' ' . $units[$i];
}
function pick_unit_from_bytes(int $bytes): array {
    $tb = 1024 ** 4; $gb = 1024 ** 3; $mb = 1024 ** 2;
    if ($bytes % $tb === 0) return [2, (int)($bytes / $tb)];
    if ($bytes % $gb === 0) return [1, (int)($bytes / $gb)];
    return [0, (int)max(1, round($bytes / $mb))];
}

// ============ state ============
$userid        = (int)($CURUSER['id'] ?? 0);
$user_bonus    = (int)($CURUSER['bonus'] ?? 0);
$pointsPerHour = (int)($GLOBALS['points_per_hour'] ?? 0);
$action        = (string)($_REQUEST['action'] ?? '');
$exchange      = ($_POST['exchange'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST';

// ============ Admin actions ============
if ($action === 'elegor') {
    csrf_check_soft();
    if (get_user_class() < UC_ADMINISTRATOR) {
        stdhead('Администрирование бонусов');
        begin_frame('Администрирование бонусов');
        echo "<div class='notice err'>У вас нет прав для доступа на эту страницу.</div>";
        end_frame(); stdfoot(); exit;
    }

    $doAdd    = isset($_POST['do_add']);
    $doUpdate = isset($_POST['do_update']);
    $doDelete = isset($_POST['do_delete']) && !empty($_POST['delete']) && is_array($_POST['delete']);

    if ($doAdd) {
        $bonus_position  = max(0, (int)($_POST['next'] ?? 0));
        $bonus_title     = trim((string)($_POST['bonus_title'] ?? ''));
        $bonus_desc      = trim((string)($_POST['bonus_description'] ?? ''));
        $bonus_points    = max(0, (int)($_POST['bonus_points'] ?? 0));
        $bonus_art_flag  = (int)($_POST['bonus_art'] ?? 0);
        $nbyt            = (int)($_POST['nbyt'] ?? 0);
        $bonus_menge_in  = (int)($_POST['bonus_menge'] ?? 0);

        $bonus_art = $bonus_art_flag === 1 ? 'invite' : 'traffic';
        if ($bonus_art === 'invite') {
            $bonus_menge = max(1, (int)$bonus_menge_in);
        } else {
            $mult = match ($nbyt) { 0 => 1024*1024, 1 => 1024*1024*1024, 2 => 1024*1024*1024*1024, default => 1 };
            $bonus_menge = max(1, $bonus_menge_in * $mult);
        }

        $errs = [];
        if ($bonus_title === '') $errs[] = 'Не указано название';
        if ($bonus_desc === '')  $errs[] = 'Не указано описание';
        if ($bonus_points <= 0)  $errs[] = 'Неверная цена в бонусах';
        if ($bonus_menge <= 0)   $errs[] = 'Неверная величина награды';

        if ($errs) {
            stdhead('Администрирование бонусов'); begin_frame('Проверка данных');
            echo "<ul class='list'>"; foreach ($errs as $e) echo "<li>".h($e)."</li>"; echo "</ul>";
            echo "<div class='mt10'><a class='btn ghost' href='mybonus.php?action=elegor'>Назад</a></div>";
            end_frame(); stdfoot(); exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO mybonus (bonus_position, bonus_title, bonus_description, bonus_points, bonus_art, bonus_menge)
                                  VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) stderr('DB error', h($mysqli->error));
        $stmt->bind_param('issisi', $bonus_position, $bonus_title, $bonus_desc, $bonus_points, $bonus_art, $bonus_menge);
        $stmt->execute(); $stmt->close();

        header('Location: mybonus.php?action=elegor&saved=1', true, 302); exit;
    }

    if ($doUpdate) {
        $res = $mysqli->query("SELECT id FROM mybonus");
        if ($res) {
            $sql  = "UPDATE mybonus SET bonus_position=?, bonus_title=?, bonus_description=?, bonus_points=?, bonus_art=?, bonus_menge=? WHERE id=?";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) stderr('DB error', h($mysqli->error));
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['id'];
                $bonus_position = max(0, (int)($_POST["bonus_position_$id"] ?? 0));
                $bonus_title    = trim((string)($_POST["bonus_title_$id"] ?? ''));
                $bonus_desc     = trim((string)($_POST["bonus_description_$id"] ?? ''));
                $bonus_points   = max(0, (int)($_POST["bonus_points_$id"] ?? 0));
                $art_flag       = (int)($_POST["bonus_art_$id"] ?? 0);
                $nbyt           = (int)($_POST["nbyt_$id"] ?? 0);
                $menge_in       = (int)($_POST["menge_$id"] ?? 0);

                $bonus_art = $art_flag === 1 ? 'invite' : 'traffic';
                if ($bonus_art === 'invite') {
                    $bonus_menge = max(1, (int)$menge_in);
                } else {
                    $mult = match ($nbyt) { 0 => 1024*1024, 1 => 1024*1024*1024, 2 => 1024*1024*1024*1024, default => 1 };
                    $bonus_menge = max(1, $menge_in * $mult);
                }

                $stmt->bind_param('issisii', $bonus_position, $bonus_title, $bonus_desc, $bonus_points, $bonus_art, $bonus_menge, $id);
                $stmt->execute();
            }
            $stmt->close(); $res->free();
        }
        header('Location: mybonus.php?action=elegor&saved=1', true, 302); exit;
    }

    if ($doDelete) {
        $ids = array_map(static fn($v) => (int)$v, $_POST['delete']);
        $ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));
        if ($ids) {
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $typ = str_repeat('i', count($ids));
            $stmt = $mysqli->prepare("DELETE FROM mybonus WHERE id IN ($in)");
            if ($stmt) { $stmt->bind_param($typ, ...$ids); $stmt->execute(); $stmt->close(); }
        }
        header('Location: mybonus.php?action=elegor&saved=1', true, 302); exit;
    }

    // ====== Admin view (тот же лёгкий стиль) ======
    stdhead('Администрирование бонусов'); begin_frame('Администрирование бонусов'); ?>
    <style>
      .wrap { max-width: 100%; }
      .topbar { display:flex; gap:16px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:10px; }
      .stats { display:flex; gap:16px; flex-wrap:wrap; }
      .stat { padding:8px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
      .small{font-size:12px; color:#6b7280}
      .btn { padding:8px 12px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; font-weight:600; text-decoration:none; }
      .btn.ghost { background:linear-gradient(180deg, #ffffff, #f9fafb); }
      .btn.primary { border-color:#6366f1; }
      .btn.warn { border-color:#ef4444; }
      .notice { padding:10px 12px; border-radius:10px; margin-bottom:10px; }
      .notice.ok { border:1px solid #bbf7d0; background:#f0fdf4; }
      .notice.err{ border:1px solid #fecaca; background:#fff1f2; }

      .tablebox { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
      table.t { width:100%; border-collapse:separate; border-spacing:0; }
      table.t th, table.t td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
      table.t thead th { background:#f8fafc; font-weight:700; border-bottom:1px solid #e5e7eb; text-align:left; }
      table.t tr:last-child td { border-bottom:none; }
      td.center { text-align:center; }
      .controls { display:flex; gap:10px; flex-wrap:wrap; }
      .grid6 { display:grid; grid-template-columns: repeat(6, 1fr); gap:10px; }
      @media (max-width:900px){ .grid6 { grid-template-columns: 1fr 1fr; } }
    </style>

    <div class="wrap">
      <?php if (!empty($_GET['saved'])): ?>
        <div class="notice ok">Изменения сохранены.</div>
      <?php endif; ?>

      <div class="topbar">
        <div class="stats">
          <div class="stat"><span class="small">Баланс</span><br><b><?= h((string)$CURUSER['bonus']) ?></b></div>
          <div class="stat"><span class="small">Начисление</span><br><b><?= $pointsPerHour>0?h((string)$pointsPerHour):'?' ?></b> <span class="small">в час</span></div>
        </div>
        <div><a class="btn ghost" href="mybonus.php">Назад к витрине</a></div>
      </div>

      <form method="post" class="tablebox" style="margin-top:10px;">
        <input type="hidden" name="action" value="elegor">
        <?php $tok = csrf_token(); if ($tok !== ''): ?><input type="hidden" name="csrf_token" value="<?= h($tok) ?>"><?php endif; ?>

        <table class="t">
          <thead>
            <tr>
              <th style="width:7%">Поз.</th>
              <th style="width:18%">Название</th>
              <th>Описание</th>
              <th style="width:10%">Цена</th>
              <th style="width:12%">Тип</th>
              <th style="width:18%">Награда</th>
              <th style="width:5%" class="center">Удал.</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $res = $mysqli->query("SELECT id, bonus_position, bonus_title, bonus_description, bonus_points, bonus_art, bonus_menge
                                   FROM mybonus
                               ORDER BY bonus_position ASC, id ASC");
          if ($res) {
              while ($r = $res->fetch_assoc()):
                  $id    = (int)$r['id'];
                  $pos   = (int)$r['bonus_position'];
                  $title = (string)$r['bonus_title'];
                  $desc  = (string)$r['bonus_description'];
                  $pts   = (int)$r['bonus_points'];
                  $art   = ($r['bonus_art'] === 'invite') ? 'invite' : 'traffic';
                  $menge = (int)$r['bonus_menge'];
                  $nbyt = 0; $val = $menge;
                  if ($art === 'traffic') { [$nbyt, $val] = pick_unit_from_bytes($menge); }
          ?>
            <tr>
              <td><input type="number" name="bonus_position_<?= $id ?>" value="<?= h((string)$pos) ?>" min="0" style="width:70px"></td>
              <td><input type="text" name="bonus_title_<?= $id ?>" value="<?= h($title) ?>" maxlength="255" style="width:100%"></td>
              <td><textarea name="bonus_description_<?= $id ?>" rows="3" style="width:100%"><?= h($desc) ?></textarea></td>
              <td><input type="number" name="bonus_points_<?= $id ?>" value="<?= h((string)$pts) ?>" min="1" style="width:100%"></td>
              <td>
                <select name="bonus_art_<?= $id ?>">
                  <option value="0" <?= $art==='traffic'?'selected':'' ?>>Трафик</option>
                  <option value="1" <?= $art==='invite'?'selected':'' ?>>Инвайт</option>
                </select>
              </td>
              <td>
                <div class="controls">
                  <input type="number" name="menge_<?= $id ?>" value="<?= h((string)$val) ?>" min="1" style="width:100px">
                  <select name="nbyt_<?= $id ?>">
                    <option value="0" <?= $nbyt===0?'selected':'' ?>>MB</option>
                    <option value="1" <?= $nbyt===1?'selected':'' ?>>GB</option>
                    <option value="2" <?= $nbyt===2?'selected':'' ?>>TB</option>
                  </select>
                </div>
              </td>
              <td class="center"><input type="checkbox" name="delete[]" value="<?= $id ?>"></td>
            </tr>
          <?php
              endwhile;
              $res->free();
          }
          ?>
            <tr>
              <td colspan="7">
                <div class="small" style="margin-bottom:6px;">Добавить новую позицию</div>
                <div class="grid6">
                  <div><label class="small">Поз.</label><input type="number" name="next" min="0" style="width:100%"></div>
                  <div style="grid-column: span 2;"><label class="small">Название</label><input type="text" name="bonus_title" maxlength="255" style="width:100%"></div>
                  <div><label class="small">Цена</label><input type="number" name="bonus_points" min="1" style="width:100%"></div>
                  <div><label class="small">Тип</label>
                    <select name="bonus_art" style="width:100%">
                      <option value="0" selected>Трафик</option>
                      <option value="1">Инвайт</option>
                    </select>
                  </div>
                  <div><label class="small">Величина</label><input type="number" name="bonus_menge" min="1" style="width:100%"></div>
                  <div><label class="small">Единицы</label>
                    <select name="nbyt" style="width:100%">
                      <option value="0">MB</option>
                      <option value="1" selected>GB</option>
                      <option value="2">TB</option>
                    </select>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div style="padding:12px; display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
          <button class="btn primary" type="submit" name="do_update" value="1">Сохранить</button>
          <button class="btn" type="submit" name="do_delete" value="1" onclick="return confirm('Удалить отмеченные позиции?')">Удалить отмеченные</button>
          <button class="btn ghost" type="submit" name="do_add" value="1">Добавить</button>
        </div>
      </form>
    </div>
    <?php
    end_frame(); stdfoot(); exit;
}

// ============ Exchange (buy) ============
if ($exchange) {
    csrf_check_soft();

    $take_points = max(0, (int)($_POST['bonus_points'] ?? 0));
    $bonus_menge = (int)($_POST['bonus_menge'] ?? 0); // инвайты: кол-во; трафик: байты
    $bonus_art   = (string)($_POST['bonus_art'] ?? '');

    if ($take_points <= 0 || $bonus_menge <= 0 || !in_array($bonus_art, ['traffic','invite'], true)) {
        stderr('Ошибка', 'Некорректные параметры обмена.');
    }

    $comment_line = gmdate('Y-m-d') . " - Обменял {$take_points} бонусов на {$bonus_art}.\n";

    if ($bonus_art === 'traffic') {
        $bytes_str = (string)$bonus_menge;
        $sql = "UPDATE users
                   SET bonus = bonus - ?,
                       uploaded = uploaded + CAST(? AS UNSIGNED),
                       modcomment = CONCAT(?, modcomment)
                 WHERE id = ? AND bonus >= ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('issii', $take_points, $bytes_str, $comment_line, $userid, $take_points);
        $apply_session = static function() use (&$CURUSER, $take_points, $bonus_menge) {
            $CURUSER['bonus']    = (int)$CURUSER['bonus'] - $take_points;
            $CURUSER['uploaded'] = (int)$CURUSER['uploaded'] + $bonus_menge;
        };
    } else {
        $inv_add = max(1, (int)$bonus_menge);
        $sql = "UPDATE users
                   SET bonus = bonus - ?,
                       invites = invites + ?,
                       modcomment = CONCAT(?, modcomment)
                 WHERE id = ? AND bonus >= ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('iisii', $take_points, $inv_add, $comment_line, $userid, $take_points);
        $apply_session = static function() use (&$CURUSER, $take_points, $inv_add) {
            $CURUSER['bonus']   = (int)$CURUSER['bonus'] - $take_points;
            $CURUSER['invites'] = (int)($CURUSER['invites'] ?? 0) + $inv_add;
        };
    }

    $stmt->execute(); $ok = ($stmt->affected_rows === 1); $stmt->close();
    if (!$ok) stderr('Ошибка', 'Недостаточно бонусов или операция недоступна.');

    // sync session + cache
    $apply_session();
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['CURUSER'] = $CURUSER;
    if (function_exists('mc_delete')) { @mc_delete("user_{$userid}"); @mc_delete("userstats_{$userid}"); }
    elseif (function_exists('cache')) { try { cache()->delete("user_{$userid}"); cache()->delete("userstats_{$userid}"); } catch (Throwable) {} }

    // редирект с подтверждением
    $amt = ($bonus_art === 'traffic') ? urlencode(format_bytes($bonus_menge)) : urlencode((string)$bonus_menge);
    header('Location: mybonus.php?ok=1&art=' . urlencode($bonus_art) . '&points=' . $take_points . '&amount=' . $amt, true, 302);
    exit;
}

// ============ Storefront (user view) ============
$title = 'Пункт обмена бонусов';
stdhead('Бонусы — ' . h($CURUSER['username']));
begin_frame($title);
?>
<style>
/* лёгкий стиль витрины */

.wrap { max-width: 100%; }
.topbar { display:flex; gap:16px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:10px; }
.stats { display:flex; gap:16px; flex-wrap:wrap; }
.stat { padding:8px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
.small{font-size:12px; color:#6b7280}
.btn { padding:8px 12px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; font-weight:600; text-decoration:none; }
.btn.ghost { background:linear-gradient(180deg, #ffffff, #f9fafb); }
.btn.primary { border-color:#6366f1; }

.notice { padding:10px 12px; border-radius:10px; margin-bottom:10px; }
.notice.ok { border:1px solid #bbf7d0; background:#f0fdf4; }
.notice.err{ border:1px solid #fecaca; background:#fff1f2; }

.tablebox { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }

/* FIX: фиксированная раскладка таблицы и ширины колонок через colgroup */
table.bon { width:100%; border-collapse:separate; border-spacing:0; table-layout: fixed; } /* <-- */
table.bon th, table.bon td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
table.bon thead th { background:#f8fafc; font-weight:700; border-bottom:1px solid #e5e7eb; text-align:left; }
table.bon tr:last-child td { border-bottom:none; }

/* одинаковые выравнивания для заголовков и ячеек */
td.price, th.price { text-align:right; white-space:nowrap; }
td.action, th.action { text-align:right; }

/* чтобы описание красиво переносилось при fixed-раскладке */
td.desc { word-break: break-word; }


/* --- FIX: выравнивание и сетка последней пары колонок --- */

/* та же раскладка */
.tablebox { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
table.bon { width:100%; border-collapse:separate; border-spacing:0; table-layout: fixed; }
table.bon th, table.bon td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:top; box-sizing: border-box; }

/* колонки — те же, что у тебя в colgroup */
td.price, th.price   { text-align: right !important; white-space: nowrap; }
td.action, th.action { text-align: right !important; }

/* чтобы колонки не “плясали” на узких экранах */
table.bon col:nth-child(5) { min-width: 110px; } /* Цена */
table.bon col:nth-child(6) { min-width: 140px; } /* Обмен */

/* заголовок таблицы */
table.bon thead th { background:#f8fafc; font-weight:700; border-bottom:1px solid #e5e7eb; text-align:left; }

/* перенос описания при fixed-раскладке */
td.desc { word-break: break-word; }


</style>

<div class="wrap">
  <?php if (isset($_GET['ok'])):
      $art = ($_GET['art'] ?? '') === 'traffic' ? 'трафик' : 'инвайты';
      $pts = (int)($_GET['points'] ?? 0);
      $amt = h((string)($_GET['amount'] ?? ''));
  ?>
    <div class="notice ok">Обмен успешно выполнен: списано <b><?= $pts ?></b> бонусов, получено <b><?= $amt ?></b> (<?= $art ?>).</div>
  <?php endif; ?>

  <div class="topbar">
    <div class="stats">
      <div class="stat"><span class="small">Пользователь</span><br><b><?= h($CURUSER['username'] ?? '') ?></b></div>
      <div class="stat"><span class="small">Баланс</span><br><b><?= h((string)$user_bonus) ?></b> <span class="small">бонусов</span></div>
      <div class="stat"><span class="small">Начисление</span><br><b><?= $pointsPerHour>0?h((string)$pointsPerHour):'?' ?></b> <span class="small">в час</span></div>
    </div>
    <?php if (get_user_class() >= UC_ADMINISTRATOR): ?>
      <div><a class="btn ghost" href="mybonus.php?action=elegor">Администрирование</a></div>
    <?php endif; ?>
  </div>

  <div class="tablebox">
    <table class="bon">
      <!-- FIX: colgroup с теми же ширинами, что и заголовки -->
      <colgroup>
        <col style="width:6%">
        <col style="width:34%">
        <col> <!-- описание тянется -->
        <col style="width:16%">
        <col style="width:12%">
        <col style="width:12%">
      </colgroup>
      <thead>
        <tr>
          <th>№</th>
          <th>Название</th>
          <th>Описание</th>
          <th>Награда</th>
          <th class="price">Цена</th>    <!-- FIX: класс и в th -->
          <th class="action">Обмен</th>  <!-- FIX: класс и в th -->
        </tr>
      </thead>
      <tbody>
      <?php
      $res = $mysqli->query("SELECT id, bonus_position, bonus_title, bonus_description, bonus_points, bonus_art, bonus_menge
                               FROM mybonus
                           ORDER BY bonus_position ASC, id ASC");
      if ($res && $res->num_rows > 0):
        while ($row = $res->fetch_assoc()):
          $pos=(int)$row['bonus_position'];
          $title=h($row['bonus_title']);
          $desc=nl2br(h($row['bonus_description']));
          $points=(int)$row['bonus_points'];
          $art=($row['bonus_art']==='invite')?'invite':'traffic';
          $menge=(int)$row['bonus_menge'];
          $reward_hint = ($art==='invite') ? ($menge.' инвайт(ов)') : (format_bytes($menge).' аплоада');
          $canBuy = ($user_bonus >= $points);
      ?>
        <tr>
          <td><?= $pos ?></td>
          <td><b><?= $title ?></b></td>
          <td class="desc"><?= $desc ?></td>
          <td><?= h($reward_hint) ?></td>
          <td class="price"><b><?= $points ?></b></td>
          <td class="action">
            <form method="post" style="margin:0">
              <input type="hidden" name="exchange" value="1">
              <?php $tok = csrf_token(); if ($tok !== ''): ?>
                <input type="hidden" name="csrf_token" value="<?= h($tok) ?>">
              <?php endif; ?>
              <input type="hidden" name="bonus_menge" value="<?= h((string)$menge) ?>">
              <input type="hidden" name="bonus_points" value="<?= h((string)$points) ?>">
              <input type="hidden" name="bonus_art" value="<?= h($art) ?>">
              <?php if ($canBuy): ?>
                <button class="btn primary" type="submit" title="Списать <?= $points ?> бонусов">Обменять</button>
              <?php else: ?>
                <button class="btn" type="button" disabled title="Недостаточно бонусов">Недостаточно</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php
        endwhile; $res->free();
      else: ?>
        <tr><td colspan="6">Позиции отсутствуют. Обратитесь к администрации для добавления вариантов обмена.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
end_frame();
stdfoot();
