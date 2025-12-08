<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
dbconn(true);

stdhead('Аплоадеры');
begin_frame('Аплоадеры');

/** ---------- helpers ---------- */
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ru_plural(int $n, string $one, string $few, string $many): string {
    $n = abs($n) % 100; $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $many;
    if ($n1 > 1 && $n1 < 5) return $few;
    if ($n1 == 1) return $one;
    return $many;
}
/** Если у тебя есть константа UC_UPLOADER — используй её */
$uploaderClass = defined('UC_UPLOADER') ? UC_UPLOADER : 3;

/** ---------- data ---------- */
$sql = "
    SELECT
        u.id, u.username, u.added,
        u.uploaded, u.downloaded,
        u.donor, u.warned,
        COALESCE(t.cnt, 0)      AS torrents_cnt,
        t.last_added            AS last_added
    FROM users AS u
    LEFT JOIN (
        SELECT owner, COUNT(*) AS cnt, MAX(added) AS last_added
        FROM torrents
        GROUP BY owner
    ) AS t ON t.owner = u.id
    WHERE u.class = " . sqlesc($uploaderClass) . "
    ORDER BY u.username ASC
";
$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

$uploaders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $uploaders[] = $row;
}
$num = count($uploaders);

/** ---------- UI ---------- */
?>
<style>
.uploader-wrap { margin: 8px 0 4px }
.uploader-title { font-size: 15px; margin: 0 0 6px }
.uploader-sub {
  margin: 0 0 12px; opacity:.85
}
.table-glass {
  width: 100%; border-collapse: separate; border-spacing: 0;
  background: rgba(255,255,255,.55);
  backdrop-filter: blur(6px);
  border: 1px solid rgba(0,0,0,.08);
  border-radius: 14px; overflow: hidden;
}
.table-glass th {
  text-align: left; padding: 10px 12px;
  background: linear-gradient(180deg, rgba(255,255,255,.8), rgba(255,255,255,.5));
  border-bottom: 1px solid rgba(0,0,0,.08);
  font-weight: 700;
}
.table-glass td { padding: 10px 12px; vertical-align: middle; }
.table-glass tr:nth-child(even) td { background: rgba(0,0,0,.02); }
.badge {
  display: inline-block; padding: 2px 8px; border-radius: 999px;
  font-size: 12px; line-height: 18px; border: 1px solid rgba(0,0,0,.08)
}
.badge-ratio { font-weight: 600; }
.badge-ok { background: #e8f7ed; }
.badge-warn { background: #fff3e6; }
.badge-inf { background: #eef3ff; }
.user-flags img { vertical-align: text-bottom; margin-left: 4px }
.pm-btn img { vertical-align: middle }
.num { text-align:center; width: 64px }
.nowrap { white-space: nowrap }
</style>

<div class="uploader-wrap">
  <h2 class="uploader-title">Информация об аплоадерах</h2>
  <p class="uploader-sub">
    У нас <?= (int)$num . ' ' . ru_plural($num, 'аплоадер', 'аплоадера', 'аплоадеров'); ?>.
  </p>

<?php if ($num > 0): ?>
  <table class="table-glass">
    <thead>
      <tr>
        <th class="num">№</th>
        <th>Пользователь</th>
        <th>Раздал / Скачал</th>
        <th>Рейтинг</th>
        <th>Залил торрентов</th>
        <th>Последняя заливка</th>
        <th class="num">ЛС</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 0;
      foreach ($uploaders as $u):
          $i++;
          $id         = (int)$u['id'];
          $username   = (string)$u['username'];
          $uploaded_b = (int)$u['uploaded'];
          $download_b = (int)$u['downloaded'];
          $uploaded   = mksize($uploaded_b);
          $downloaded = mksize($download_b);
          $tcount     = (int)$u['torrents_cnt'];
          $lastAdded  = $u['last_added'] ?? null;

          // ratio
          if ($download_b > 0) {
              $ratioVal = $uploaded_b / $download_b;
              $ratioFmt = number_format($ratioVal, 3);
              $color    = get_ratio_color($ratioVal);
              $ratio    = '<span class="badge badge-ratio" style="color:' . h((string)$color) . '">' . h($ratioFmt) . '</span>';
          } elseif ($uploaded_b > 0) {
              $ratio    = '<span class="badge badge-inf">Inf.</span>';
          } else {
              $ratio    = '<span class="badge">—</span>';
          }

          // flags
          $flags = '';
          if (($u['donor'] ?? '') === 'yes') {
              $flags .= '<img src="pic/star.gif" alt="donor" title="Донор">';
          }
          if (($u['warned'] ?? '') === 'yes') {
              $flags .= '<img src="pic/warned8.gif" alt="warned" title="Предупреждён">';
          }

          // last upload
          if ($tcount > 0 && !empty($lastAdded)) {
              $ago  = get_elapsed_time(sql_timestamp_to_unix_timestamp($lastAdded)) . ' назад';
              $date = date('d.m.Y', strtotime($lastAdded));
              $last = '<span class="nowrap" title="' . h($lastAdded) . '">' . h($ago) . ' (' . h($date) . ')</span>';
          } else {
              $last = '—';
          }
      ?>
      <tr>
        <td class="num"><?= $i ?></td>
        <td>
          <a href="userdetails.php?id=<?= $id ?>"><?= h($username) ?></a>
          <span class="user-flags"><?= $flags ?></span>
        </td>
        <td class="nowrap"><?= h($uploaded) ?> / <?= h($downloaded) ?></td>
        <td><?= $ratio ?></td>
        <td><?= $tcount ?> <?= ru_plural($tcount, 'торрент', 'торрента', 'торрентов') ?></td>
        <td><?= $last ?></td>
        <td class="num pm-btn">
          <a href="message.php?action=sendmessage&amp;receiver=<?= $id ?>" title="Отправить ЛС">
            <img src="pic/button_pm.gif" alt="PM">
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="uploader-sub">Пока нет пользователей с ролью аплоадера.</div>
<?php endif; ?>
</div>

<?php
end_frame();
stdfoot();
