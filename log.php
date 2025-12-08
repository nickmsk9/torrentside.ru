<?php
declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";
dbconn(false);
loggedinorreturn();

$title = "Единый лог";
stdhead($title);
begin_frame($title);

// ==================== Настройки ====================
$RETENTION_DAYS = 7;
$perPage = max(5, min(100, (int)($_GET['pp'] ?? 25)));
$type    = (string)($_GET['type'] ?? 'all');          // all | sitelog | chat | sessions | news | invites | bans
$subtype = (string)($_GET['subtype'] ?? '');          // только для sitelog: tracker|torrent|...
$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$debug   = isset($_GET['debug']);                     // ?debug=1 — покажет WHERE и total

// ==================== Доступ ====================
// секция "Баны/Сессии" часто чувствительная; при желании ограничь:
if (in_array($type, ['sessions', 'bans'], true) && ($CURUSER['class'] ?? 0) < UC_SYSOP) {
    stdmsg("Ошибка", "Доступ в этот раздел закрыт.");
    end_frame(); stdfoot(); exit;
}

// ==================== Форматтеры ====================
$fmtDate = static function ($mysqlDT): string {
    $ts = strtotime((string)$mysqlDT);
    if ($ts === false) return htmlspecialchars((string)$mysqlDT, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return date('d.m.Y H:i', $ts); // 10.10.2025 09:15
};
$sanitizeHex = static function (?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '' || strcasecmp($raw, 'transparent') === 0) return 'transparent';
    $hex = preg_replace('~[^0-9a-f]~i', '', $raw) ?? '';
    if ($hex === '') return 'transparent';
    if (strlen($hex) === 3 || strlen($hex) === 6) return '#' . strtolower($hex);
    return '#' . strtolower(substr($hex, 0, 6));
};

// ==================== Ретенция только для sitelog ====================
sql_query("DELETE FROM sitelog WHERE added < (NOW() - INTERVAL $RETENTION_DAYS DAY)") or sqlerr(__FILE__, __LINE__);

// ==================== Карта вкладок ====================
$tabs = [
    'all'      => 'Все',
    'sitelog'  => 'Системный лог',
    'chat'     => 'Чат',
    'sessions' => 'Сессии',
    'news'     => 'Новости',
    'invites'  => 'Приглашения',
    'bans'     => 'Баны',
];
if (!isset($tabs[$type])) $type = 'all';

// ==================== WHERE для общего поиска ====================
$like = '';
if ($q !== '') {
    $likeVal = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $like = sqlesc($likeVal);
}

// ==================== WHERE по подтипу sitelog ====================
$whereSitelog = "1";
if ($type === 'sitelog' && $subtype !== '') {
    $whereSitelog = "`type` = " . sqlesc($subtype);
} elseif ($type !== 'sitelog' && $subtype !== '') {
    // если на другой вкладке — игнорируем подтип
    $subtype = '';
}

// ==================== Сборный COUNT ====================
$countSqlParts = [];

// sitelog
if ($type === 'all' || $type === 'sitelog') {
    $part = "SELECT COUNT(*) AS c FROM sitelog WHERE {$whereSitelog}";
    if ($q !== '') $part .= " AND (`txt` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// chat (shoutbox)
if ($type === 'all' || $type === 'chat') {
    $part = "SELECT COUNT(*) AS c FROM shoutbox WHERE 1";
    if ($q !== '') $part .= " AND (`orig_text` LIKE {$like} ESCAPE '\\\\' OR `username` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// sessions
if ($type === 'all' || $type === 'sessions') {
    $part = "SELECT COUNT(*) AS c FROM sessions WHERE 1";
    if ($q !== '') $part .= " AND (`username` LIKE {$like} ESCAPE '\\\\' OR `ip` LIKE {$like} ESCAPE '\\\\' OR `url` LIKE {$like} ESCAPE '\\\\' OR `useragent` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// news
if ($type === 'all' || $type === 'news') {
    $part = "SELECT COUNT(*) AS c FROM news WHERE 1";
    if ($q !== '') $part .= " AND (`subject` LIKE {$like} ESCAPE '\\\\' OR `body` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// invites
if ($type === 'all' || $type === 'invites') {
    $part = "SELECT COUNT(*) AS c FROM invites WHERE 1";
    if ($q !== '') $part .= " AND (`invite` LIKE {$like} ESCAPE '\\\\' OR `confirmed` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// bans
if ($type === 'all' || $type === 'bans') {
    $part = "SELECT COUNT(*) AS c FROM bans WHERE 1";
    if ($q !== '') $part .= " AND (`comment` LIKE {$like} ESCAPE '\\\\')";
    $countSqlParts[] = $part;
}

// Выполняем COUNT’ы и суммируем
$total = 0;
foreach ($countSqlParts as $sqlC) {
    $resC = sql_query($sqlC) or sqlerr(__FILE__, __LINE__);
    [$cnt] = mysqli_fetch_row($resC) ?: [0];
    $total += (int)$cnt;
}

// Пейджинг
$params = [];
$params[] = "type={$type}";
if ($subtype !== '') $params[] = "subtype=" . urlencode($subtype);
if ($q !== '')       $params[] = "q=" . urlencode($q);
[$pagertop, $pagerbottom, $limit] = pager($perPage, (int)$total, "log.php?" . implode('&', $params) . "&");

// ==================== Основной SELECT через UNION ALL ====================
// Приводим всё к общей схеме:
// dt | source | subtype | color | txt | id
$union = [];

if ($type === 'all' || $type === 'sitelog') {
    $sql = "
        SELECT added AS dt,
               'sitelog' AS source,
               `type`    AS subtype,
               `color`   AS color,
               `txt`     AS txt,
               id        AS id
        FROM sitelog
        WHERE {$whereSitelog}";
    if ($q !== '') $sql .= " AND (`txt` LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

if ($type === 'all' || $type === 'chat') {
    // Возьмём orig_text (нефильтрованный исходник) и username
    $sql = "
        SELECT date_dt AS dt,
               'chat'   AS source,
               ''       AS subtype,
               '6aa7ff' AS color,
               CONCAT('Чат — ', username, ': ', orig_text) AS txt,
               id       AS id
        FROM shoutbox
        WHERE 1";
    if ($q !== '') $sql .= " AND (orig_text LIKE {$like} ESCAPE '\\\\' OR username LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

if ($type === 'all' || $type === 'sessions') {
    // Покажем юзера, URL, IP. useragent укоротим на сервере (оставим для поиска в WHERE)
    $sql = "
        SELECT time_dt AS dt,
               'sessions' AS source,
               ''         AS subtype,
               'c4b5fd'   AS color,
               CONCAT('Сессия — ', username, ' → ', url, ' (', ip, '), UA: ', LEFT(COALESCE(useragent,''), 120)) AS txt,
               0          AS id
        FROM sessions
        WHERE 1";
    if ($q !== '') $sql .= " AND (username LIKE {$like} ESCAPE '\\\\' OR ip LIKE {$like} ESCAPE '\\\\' OR url LIKE {$like} ESCAPE '\\\\' OR useragent LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

if ($type === 'all' || $type === 'news') {
    $sql = "
        SELECT added AS dt,
               'news' AS source,
               ''     AS subtype,
               '9ee493' AS color,
               CONCAT('Новость — ', subject, ' (uid=', userid, ')') AS txt,
               id AS id
        FROM news
        WHERE 1";
    if ($q !== '') $sql .= " AND (subject LIKE {$like} ESCAPE '\\\\' OR body LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

if ($type === 'all' || $type === 'invites') {
    $sql = "
        SELECT time_invited AS dt,
               'invites' AS source,
               confirmed  AS subtype,
               CASE WHEN confirmed='yes' THEN '5ddb6e' ELSE 'f59e0b' END AS color,
               CONCAT('Инвайт — ', invite, ' | от uid=', inviter, ' → uid=', inviteid, ' | подтвержден: ', confirmed) AS txt,
               id AS id
        FROM invites
        WHERE 1";
    if ($q !== '') $sql .= " AND (invite LIKE {$like} ESCAPE '\\\\' OR confirmed LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

if ($type === 'all' || $type === 'bans') {
    $sql = "
        SELECT added AS dt,
               'bans' AS source,
               ''     AS subtype,
               'ef4444' AS color,
               CONCAT('Бан — ', comment,
                      COALESCE(CONCAT(' [', INET_NTOA(first), '–', INET_NTOA(last), ']'), ''),
                      COALESCE(CONCAT(' до ', DATE_FORMAT(`until`, '%d.%m.%Y %H:%i')), '')
                     ) AS txt,
               id AS id
        FROM bans
        WHERE 1";
    if ($q !== '') $sql .= " AND (comment LIKE {$like} ESCAPE '\\\\')";
    $union[] = $sql;
}

// Если ничего не выбрано (не должно случиться) — безопасная заглушка
if (empty($union)) {
    $union[] = "SELECT NOW() AS dt, 'none' AS source, '' AS subtype, 'transparent' AS color, 'Нет источников' AS txt, 0 AS id";
}

// Собираем единый запрос
$finalSql = "SELECT * FROM (" . implode("\nUNION ALL\n", $union) . "\n) AS U\nORDER BY dt DESC\n{$limit}";
$res = sql_query($finalSql) or sqlerr(__FILE__, __LINE__);

// ==================== Иконки ====================
$icon = [
    'sitelog'  => '📚',
    'chat'     => '💬',
    'sessions' => '🌐',
    'news'     => '📰',
    'invites'  => '✉️',
    'bans'     => '⛔',
];

// ==================== UI ====================
?>
<style>
.log-tabs { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin:6px 0 14px; }
.log-tab { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; text-decoration:none;
  border:1px solid rgba(255,255,255,.35); background:linear-gradient(180deg, rgba(255,255,255,.35), rgba(255,255,255,.15));
  backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); box-shadow:0 6px 16px rgba(0,0,0,.10); font-weight:600; }
.log-tab.is-active { border-color:rgba(99,102,241,.6); box-shadow:0 8px 20px rgba(99,102,241,.25) inset; }
.log-subtype { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin:0 0 10px; }
.log-subtype a { font-size:12px; padding:6px 10px; border-radius:999px; border:1px solid rgba(0,0,0,.08); text-decoration:none; background:rgba(255,255,255,.8);}
.log-search { display:flex; justify-content:center; gap:8px; margin:6px 0 14px; }
.log-search input[type="text"]{ width:min(520px,90%); padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(255,255,255,.85); }
.log-search button{ padding:10px 14px; border-radius:10px; border:1px solid rgba(0,0,0,.08); background:linear-gradient(180deg,#ffffff,#f3f4f6); font-weight:600; cursor:pointer; }
.log-list { display:flex; flex-direction:column; gap:10px; }
.log-item { border-radius:16px; padding:12px 14px; position:relative; border:1px solid rgba(0,0,0,.06);
  background:radial-gradient(120% 120% at 0% 0%, rgba(255,255,255,.95) 0%, rgba(248,250,252,.9) 100%); box-shadow:0 8px 30px rgba(2,6,23,.06); }
.log-item .stripe{ position:absolute; left:0; top:0; bottom:0; width:6px; border-top-left-radius:16px; border-bottom-left-radius:16px; background:var(--stripe, transparent); }
.log-row{ display:flex; gap:10px; align-items:flex-start; }
.log-ico{ font-size:18px; flex:0 0 auto; margin-left:6px; }
.log-body{ flex:1 1 auto; }
.log-text{ margin:2px 0 4px; line-height:1.45; word-wrap:anywhere; }
.log-meta{ font-size:12px; color:#6b7280; display:flex; gap:14px; flex-wrap:wrap; }
.log-empty{ text-align:center; padding:24px; border-radius:16px; border:1px dashed rgba(0,0,0,.1); color:#6b7280; background:rgba(255,255,255,.7); }
</style>

<div class="log-tabs">
<?php foreach ($tabs as $k => $label): $active = ($type === $k) ? ' is-active' : ''; ?>
  <a class="log-tab<?= $active ?>" href="log.php?type=<?= htmlspecialchars($k) ?><?= $q!==''? '&q='.urlencode($q) : '' ?>">
    <?= $icon[$k] ?? '📄' ?> <span><?= htmlspecialchars($label) ?></span>
  </a>
<?php endforeach; ?>
</div>

<?php if ($type === 'sitelog'): ?>
  <div class="log-subtype">
    <?php
      // быстрый список подтипов из sitelog
      $subRes = sql_query("SELECT `type`, COUNT(*) c FROM sitelog GROUP BY `type` ORDER BY c DESC") or sqlerr(__FILE__, __LINE__);
      echo '<a href="log.php?type=sitelog">Все подтипы</a>';
      while ($r = mysqli_fetch_assoc($subRes)) {
          $k = $r['type']; $c = (int)$r['c'];
          $u = 'log.php?type=sitelog&subtype='.urlencode($k).($q!==''? '&q='.urlencode($q) : '');
          echo '<a href="'.$u.'">'.htmlspecialchars($k).' ('.$c.')</a>';
      }
    ?>
  </div>
<?php endif; ?>

<form class="log-search" method="get" action="log.php">
  <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
  <?php if ($type === 'sitelog' && $subtype !== ''): ?>
    <input type="hidden" name="subtype" value="<?= htmlspecialchars($subtype) ?>">
  <?php endif; ?>
  <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE) ?>" placeholder="Поиск по логам…">
  <button type="submit">Найти</button>
</form>

<?php
if ($debug) {
    echo '<div style="font:12px/1.3 monospace; color:#6b7280; text-align:center; margin:6px 0;">DEBUG WHERE sitelog: '
         . htmlspecialchars($whereSitelog)
         . ' | total='.(int)$total.'</div>';
}

echo $pagertop;

// ===== Вывод карточек =====
echo '<div class="log-list">';
if ((int)$total === 0) {
    echo '<div class="log-empty">Ничего не найдено</div>';
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        $src  = (string)$row['source'];
        $clr  = ($src === 'sitelog') ? $sanitizeHex($row['color'] ?? 'transparent')
                                     : $sanitizeHex($row['color'] ?? 'transparent');
        $txt  = htmlspecialchars((string)$row['txt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Автоссылка на “Торрент №123” (в тексте sitelog и новостей)
        if (preg_match('~Торрент\s*№\s*(\d+)~u', $txt, $m)) {
            $tid = (int)$m[1];
            $link = '<a href="details.php?id='.$tid.'">Торрент №'.$tid.'</a>';
            $txt = preg_replace('~Торрент\s*№\s*' . $tid . '~u', $link, $txt, 1);
        }

        $dt = $fmtDate($row['dt']);
        $id = (int)$row['id'];
        $sub = (string)$row['subtype'];

        echo '<div class="log-item" style="--stripe:'.htmlspecialchars($clr).'">';
        echo '  <div class="stripe"></div>';
        echo '  <div class="log-row">';
        echo '      <div class="log-ico">'.($icon[$src] ?? '📄').'</div>';
        echo '      <div class="log-body">';
        echo '          <div class="log-text">'.$txt.'</div>';
        echo '          <div class="log-meta">';
        echo '              <span>'.htmlspecialchars($tabs[$src] ?? $src).($sub!==''? ' · '.htmlspecialchars($sub):'').'</span>';
        if ($id > 0) echo '  <span title="ID записи">#'.$id.'</span>';
        echo '              <span>'.$dt.'</span>';
        echo '          </div>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
}
echo '</div>';

echo $pagerbottom;

echo "<div style='margin-top:10px; text-align:center; color:#6b7280; font-size:12px;'>Старше {$RETENTION_DAYS} дней из системного лога удаляется автоматически. Остальные разделы — без автоудаления.</div>";

end_frame();
stdfoot();
