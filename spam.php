<?php
ob_start("ob_gzhandler");

require "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка", "Доступ запрещён.");
}

// ---------- helpers ----------
/** safe html */
$esc = static function ($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
/** escape for SQL LIKE with ESCAPE '\' */
$likeEsc = static function (string $s): string {
    // заменяем обратный слеш первым, затем спецсимволы LIKE
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace(['%', '_'], ['\\%', '\\_'], $s);
    return $s;
};
/** нормализуем dd.mm.yyyy|yyyy-mm-dd -> yyyy-mm-dd */
$normDate = static function (?string $in): ?string {
    $in = trim((string)$in);
    if ($in === '') return null;
    // поддержим оба формата
    if (preg_match('~^\d{2}\.\d{2}\.\d{4}$~', $in)) {
        [$d,$m,$y] = explode('.', $in);
        return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
    }
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $in)) {
        return $in;
    }
    // последняя попытка через strtotime
    $ts = strtotime($in);
    return $ts ? date('Y-m-d', $ts) : null;
};

// ---------- input ----------
$q           = trim((string)($_GET['q']           ?? '')); // ключевые слова
$senderName  = trim((string)($_GET['sender']      ?? ''));
$receiverName= trim((string)($_GET['receiver']    ?? ''));
$from        = $normDate($_GET['from'] ?? null);
$to          = $normDate($_GET['to']   ?? null);
$onlySystem  = isset($_GET['only_system']) ? 1 : 0;
$perpage     = max(5, min(100, (int)($_GET['pp'] ?? 20)));
$checkedAll  = (($_GET['check'] ?? '') === 'yes');
$pageSelf    = $_SERVER['PHP_SELF'] . '?';

// ---------- dynamic WHERE ----------
$where = [];
if ($q !== '') {
    $qq = $likeEsc($q);
    // ищем по тексту и теме (если поле subj есть)
    $where[] = "(m.msg LIKE '%" . $qq . "%' ESCAPE '\\' OR m.subject LIKE '%" . $qq . "%' ESCAPE '\\')";
}
if ($senderName !== '') {
    $where[] = "u_from.username LIKE '%" . $likeEsc($senderName) . "%' ESCAPE '\\'";
}
if ($receiverName !== '') {
    $where[] = "u_to.username LIKE '%" . $likeEsc($receiverName) . "%' ESCAPE '\\'";
}
if ($from !== null) {
    $where[] = "DATE(m.added) >= " . sqlesc($from);
}
if ($to !== null) {
    $where[] = "DATE(m.added) <= " . sqlesc($to);
}
if ($onlySystem) {
    // system message — sender = 0
    $where[] = "m.sender = 0";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---------- count ----------
$sqlCount = "SELECT COUNT(*) AS cnt
             FROM messages AS m
             LEFT JOIN users AS u_from ON u_from.id = m.sender
             LEFT JOIN users AS u_to   ON u_to.id   = m.receiver
             $whereSql";
$resCount = sql_query($sqlCount) or sqlerr(__FILE__, __LINE__);
$rowCount = mysqli_fetch_assoc($resCount);
$count    = (int)($rowCount['cnt'] ?? 0);

// ---------- pager ----------
[$pagertop, $pagerbottom, $limit] = pager($perpage, $count, $pageSelf . http_build_query([
    'q' => $q, 'sender' => $senderName, 'receiver' => $receiverName,
    'from' => $from, 'to' => $to, 'only_system' => $onlySystem ? '1' : null,
    'pp' => $perpage,
]) . '&');

// ---------- data ----------
$sql = "SELECT
            m.id, m.sender, m.receiver, m.msg, m.added,
            COALESCE(u_from.username, '') AS sender_name,
            COALESCE(u_to.username,   '') AS receiver_name
        FROM messages AS m
        LEFT JOIN users AS u_from ON u_from.id = m.sender
        LEFT JOIN users AS u_to   ON u_to.id   = m.receiver
        $whereSql
        ORDER BY m.id DESC
        $limit";
$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

// ---------- UI ----------
stdhead("Спам");
begin_frame("Спам-контроль");

?>
<style>
/* Минимальный «фирменный» слой. Можно вынести в styles. */
.ts-card {
  background: var(--panel, #fff);
  border: 1px solid var(--border, #e6e6e6);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(0,0,0,.04);
  padding: 12px;
  margin: 8px 0 16px;
}
.ts-title {
  font-size: 20px; font-weight: 700; margin: 0 0 8px;
  color: var(--primary, #0b5fff);
}
.ts-form { display: grid; grid-template-columns: repeat(6, minmax(120px,1fr)); gap: 8px; align-items: end; }
.ts-form .field { display: flex; flex-direction: column; gap: 4px; }
.ts-form input[type="text"], .ts-form input[type="date"], .ts-form input[type="number"] {
  border: 1px solid var(--border, #ddd); border-radius: 8px; padding: 6px 8px; background: var(--bg, #fff);
}
.ts-form .actions { grid-column: 1 / -1; display: flex; gap: 8px; flex-wrap: wrap; }
.ts-btn {
  border: 1px solid var(--primary, #0b5fff); color: var(--primary, #0b5fff);
  background: transparent; border-radius: 999px; padding: 6px 12px; cursor: pointer;
}
.ts-btn.primary { background: var(--primary, #0b5fff); color: #fff; border-color: transparent; }
.ts-table {
  width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border-radius: 12px; border: 1px solid var(--border,#e6e6e6);
}
.ts-table th, .ts-table td { padding: 10px 12px; vertical-align: top; }
.ts-table thead th {
  background: linear-gradient(to bottom, #fafafa, #f3f3f3);
  border-bottom: 1px solid var(--border,#e6e6e6); font-weight: 600; text-align: left;
}
.ts-table tbody tr + tr td { border-top: 1px solid var(--border,#f0f0f0); }
.badge {
  display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px;
  background: #eef3ff; color: var(--primary, #0b5fff); border: 1px solid #e1e9ff;
}
.msg { line-height: 1.4; }
@media (max-width: 900px) {
  .ts-form { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="ts-card">
  <div class="ts-title">Фильтры</div>
  <form method="get" class="ts-form">
    <div class="field">
      <label>Ключевые слова</label>
      <input type="text" name="q" value="<?= $esc($q) ?>" placeholder="текст сообщения">
    </div>
    <div class="field">
      <label>Отправитель (ник)</label>
      <input type="text" name="sender" value="<?= $esc($senderName) ?>" placeholder="username">
    </div>
    <div class="field">
      <label>Получатель (ник)</label>
      <input type="text" name="receiver" value="<?= $esc($receiverName) ?>" placeholder="username">
    </div>
    <div class="field">
      <label>Дата от</label>
      <input type="date" name="from" value="<?= $esc($from ?? '') ?>">
    </div>
    <div class="field">
      <label>Дата до</label>
      <input type="date" name="to" value="<?= $esc($to ?? '') ?>">
    </div>
    <div class="field">
      <label>На странице</label>
      <input type="number" min="5" max="100" name="pp" value="<?= (int)$perpage ?>">
    </div>
    <div class="field">
      <label><input type="checkbox" name="only_system" value="1" <?= $onlySystem ? 'checked' : '' ?>> Только системные</label>
    </div>
    <div class="actions">
      <button class="ts-btn primary" type="submit">Показать</button>
      <a class="ts-btn" href="<?= $esc($_SERVER['PHP_SELF']) ?>">Сброс</a>
    </div>
  </form>
</div>

<?= $pagertop ?>

<form method="post" action="/take-delmp.php" class="ts-card">
  <div class="ts-title">Сообщения (<?= (int)$count ?>)</div>
  <div style="overflow:auto;">
    <table class="ts-table">
      <thead>
        <tr>
          <th style="width:18%">Отправитель</th>
          <th style="width:18%">Получатель</th>
          <th>Содержание</th>
          <th style="width:160px">Дата</th>
          <th style="width:70px; text-align:center">
            <label><input type="checkbox" id="checkall"> все</label>
          </th>
        </tr>
      </thead>
      <tbody>
      <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <?php
          $sender = (int)$row['sender'] === 0
              ? "<span class='badge'>Система</span>"
              : ($row['sender_name'] !== ''
                   ? "<a href='userdetails.php?id=".(int)$row['sender']."'><b>".$esc($row['sender_name'])."</b></a>"
                   : "<i>неизвестно</i>");
          $receiver = $row['receiver_name'] !== ''
              ? "<a href='userdetails.php?id=".(int)$row['receiver']."'><b>".$esc($row['receiver_name'])."</b></a>"
              : "<i>неизвестно</i>";
          $msgHtml = format_comment((string)$row['msg']);
          $added   = $esc($row['added']);
          $chk     = $checkedAll ? 'checked' : '';
        ?>
        <tr>
          <td><?= $sender ?></td>
          <td><?= $receiver ?></td>
          <td class="msg"><?= $msgHtml ?></td>
          <td><?= $added ?></td>
          <td style="text-align:center">
            <input type="checkbox" name="delmp[]" value="<?= (int)$row['id'] ?>" <?= $chk ?>>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex; gap:8px; align-items:center; margin-top:10px;">
    <button type="submit" class="ts-btn primary">Удалить выбранные</button>
    <a href="<?= $esc($pageSelf . http_build_query(array_merge($_GET, ['check' => 'yes']))) ?>" class="ts-btn">Выделить всё</a>
    <a href="<?= $esc($pageSelf . http_build_query(array_merge($_GET, ['check' => 'no']))) ?>" class="ts-btn">Снять выделение</a>
  </div>
</form>

<?= $pagerbottom ?>

<script>
document.getElementById('checkall')?.addEventListener('change', function(){
  const on = this.checked;
  document.querySelectorAll('input[name="delmp[]"]').forEach(cb => cb.checked = on);
});
</script>

<?php
end_frame();
stdfoot();
