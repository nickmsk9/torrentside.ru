<?php
declare(strict_types=1);

require "include/bittorrent.php";

dbconn();
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'], "Отказано в доступе.");
}

/* ========== helpers ========== */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function self_url(array $q): string {
    $base = $_SERVER['PHP_SELF'] ?? '';
    $cur  = $_GET ?? [];
    foreach ($q as $k => $v) { $cur[$k] = $v; }
    $qs   = http_build_query($cur);
    return h($base . ($qs ? '?' . $qs : ''));
}
function yesno_to_bool($v): bool {
    if ($v === 1 || $v === '1' || $v === true) return true;
    if ($v === 0 || $v === '0' || $v === false) return false;
    return strtolower((string)$v) === 'yes';
}

/* ========== memcached ========== */
global $memcached;
if (!($memcached instanceof Memcached)) {
    $memcached = new Memcached();
    $memcached->addServer('127.0.0.1', 11211);
}
$TTL = 60;

/* ========== входные параметры / сортировка / пагинация ========== */
$perPage = max(10, min(500, (int)($_GET['perpage'] ?? 100)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$sort    = (string)($_GET['sort'] ?? 'ratio');       // ratio|username|class|added|last
$dir     = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

$sortMap = [
    'username' => 'username',
    'class'    => 'class',
    'added'    => 'added',
    'last'     => 'last_access',
    'ratio'    => 'ratio',
];
$sortCol = $sortMap[$sort] ?? 'ratio';
$offset  = ($page - 1) * $perPage;

/* ========== агрегаты (кэш) ========== */
$totals = $memcached->get('warned:totals:v1');
if ($totals === false) {
    [$warnedTotal]        = mysqli_fetch_row(sql_query("SELECT COUNT(*) FROM users WHERE warned='yes'"));
    [$enabledWarnedTotal] = mysqli_fetch_row(sql_query("SELECT COUNT(*) FROM users WHERE warned='yes' AND enabled='yes'"));
    $totals = ['warnedTotal' => (int)$warnedTotal, 'enabledWarnedTotal' => (int)$enabledWarnedTotal];
    $memcached->set('warned:totals:v1', $totals, $TTL);
}
$warnedTotal        = $totals['warnedTotal'];
$enabledWarnedTotal = $totals['enabledWarnedTotal'];

/* ========== данные страницы (кэш) ========== */
$cacheKey = sprintf('warned:list:v2:%s:%s:%d:%d', $sortCol, $dir, $perPage, $offset);
$rows = $memcached->get($cacheKey);

if ($rows === false) {
    // Примечание: сортировка по ratio делается по выражению (в MySQL можно ссылаться на псевдоним).
    // Для остальных колонок — прямое поле.
    $orderExpr = ($sortCol === 'ratio')
        ? "ratio {$dir}, username ASC"
        : "{$sortCol} {$dir}, username ASC";

    $q = sprintf("
        SELECT
            id, username, class, donor, added, last_access,
            uploaded, downloaded, warneduntil, enabled,
            (uploaded / NULLIF(downloaded, 0)) AS ratio
        FROM users
        WHERE warned='yes' AND enabled='yes'
        ORDER BY %s
        LIMIT %d OFFSET %d
    ", $orderExpr, $perPage, $offset);

    $res = sql_query($q) or sqlerr(__FILE__, __LINE__);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    $memcached->set($cacheKey, $rows, $TTL);
}

/* ========== UI ========== */
stdhead("Предупреждённые пользователи");

// лёгкий CSS
echo <<<CSS
<style>
  .stat-table{width:100%;border-collapse:collapse;font-size:13px}
  .stat-table th,.stat-table td{padding:6px 8px;border-bottom:1px solid #e9ecef;white-space:nowrap}
  .stat-table th{position:sticky;top:0;background:#f8f9fa;z-index:1;text-align:left}
  .stat-table tr:hover{background:#fafafa}
  .muted{color:#6c757d}
  .pill{display:inline-block;min-width:46px;padding:2px 6px;border-radius:999px;font-size:12px;text-align:right;background:#f1f3f5}
  .pill.small{min-width:0;padding:1px 6px}
  .r{text-align:right}
  .center{text-align:center}
  .sm{font-size:12px}
  .nowrap{white-space:nowrap}
  .head-actions{margin:6px 0 10px 0;font-size:13px}
  .head-actions a{margin-right:10px}
  .legend{display:flex;gap:16px;align-items:center;justify-content:flex-end;margin-top:8px}
  .legend .dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#adb5bd}
  .action-green .dot{background:#2f9e44}
  .action-red .dot{background:#e03131}
</style>
CSS;

$enabledFmt = number_format((int)$enabledWarnedTotal);
$allFmt     = number_format((int)$warnedTotal);

begin_frame("Предупреждённые пользователи: {$enabledFmt} из {$allFmt} всего");

/* ——— пагинация и сортировки ——— */
$totalPages = max(1, (int)ceil($enabledWarnedTotal / $perPage));
echo '<div class="head-actions">';
echo 'Стр. '.$page.' из '.$totalPages.' • ';
echo 'На странице: ';
foreach ([25,50,100,200,500] as $pp) {
    $cur = $pp === $perPage ? '<b>'.$pp.'</b>' : '<a href="'.self_url(['perpage'=>$pp,'page'=>1]).'">'.$pp.'</a>';
    echo $cur.' ';
}
echo '<br>Сортировать: ';
$sortLinks = [
    'username' => 'Ник',
    'class'    => 'Класс',
    'added'    => 'Регистрация',
    'last'     => 'Последний визит',
    'ratio'    => 'Рейтинг',
];
foreach ($sortLinks as $key => $label) {
    $isCur = ($sort === $key);
    // клик по активной колонке инвертирует направление
    $nextDir = ($isCur && $dir === 'ASC') ? 'desc' : 'asc';
    $link = self_url(['sort'=>$key,'dir'=>$nextDir,'page'=>1]);
    echo $isCur ? "<b>{$label}".($dir==='ASC'?' ↑':' ↓')."</b> " : "<a href=\"{$link}\">{$label}</a> ";
}
echo '</div>';

/* ——— CSRF ——— */
if (empty($_SESSION['csrf_warned'])) {
    try {
        $_SESSION['csrf_warned'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['csrf_warned'] = md5(uniqid('', true) . microtime(true) . (string)mt_rand());
    }
}
$csrf = $_SESSION['csrf_warned'];

echo '<form action="nowarn.php" method="post">';
echo '<input type="hidden" name="csrf_token" value="'.h($csrf).'">';

echo '<table class="stat-table">';
echo '<tr>
        <th>Пользователь</th>
        <th>Зарегистрирован</th>
        <th>Последний визит</th>
        <th>Класс</th>
        <th class="r">Скачал</th>
        <th class="r">Раздал</th>
        <th class="r">Рейтинг</th>
        <th class="center">Окончание</th>
        <th class="center">Убрать</th>
        <th class="center">Откл.</th>
      </tr>';

foreach ($rows as $arr) {
    // Даты
    $added       = ($arr['added'] === '0000-00-00 00:00:00' || $arr['added'] === null) ? '—' : substr($arr['added'], 0, 10);
    $last_access = ($arr['last_access'] === '0000-00-00 00:00:00' || $arr['last_access'] === null) ? '—' : substr($arr['last_access'], 0, 10);

    // Размеры
    $uploaded   = mksize((int)$arr['uploaded']);
    $downloaded = mksize((int)$arr['downloaded']);

    // Рейтинг
    if ((int)$arr['downloaded'] > 0) {
        $ratioVal = (float)$arr['ratio'];
        $ratioTxt = number_format($ratioVal, 3);
        $ratioCol = get_ratio_color($ratioVal);
    } else {
        $ratioTxt = '—';
        $ratioCol = get_ratio_color(0.0);
    }
    $ratioHtml = '<span style="color:'.$ratioCol.'">'.$ratioTxt.'</span>';

    $className = get_user_class_name((int)$arr['class']);

    // Донорская звезда
    $isDonor = yesno_to_bool($arr['donor']);
    $donorStar = $isDonor ? " <img src=\"pic/star.gif\" alt=\"Donor\">" : "";

    $warneduntil = $arr['warneduntil'] ? h($arr['warneduntil']) : '—';

    echo '<tr>';
    echo '  <td><a href="userdetails.php?id='.(int)$arr['id'].'"><b>'.h($arr['username']).'</b></a>'.$donorStar.'</td>';
    echo '  <td class="center">'.h($added).'</td>';
    echo '  <td class="center">'.h($last_access).'</td>';
    echo '  <td class="center">'.h($className).'</td>';
    echo '  <td class="r"><span class="pill">'.h($downloaded).'</span></td>';
    echo '  <td class="r"><span class="pill">'.h($uploaded).'</span></td>';
    echo '  <td class="r">'.$ratioHtml.'</td>';
    echo '  <td class="center">'.$warneduntil.'</td>';
    echo '  <td class="center"><input type="checkbox" name="usernw[]" value="'.(int)$arr['id'].'"></td>';
    echo '  <td class="center"><input type="checkbox" name="desact[]" value="'.(int)$arr['id'].'"></td>';
    echo '</tr>';
}
echo '</table>';

/* ——— нижняя панель: пагинация + легенда ——— */
echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:8px">';
echo '  <div>';
if ($page > 1) {
    echo '<a href="'.self_url(['page'=>1]).'">« Первая</a> &nbsp; ';
    echo '<a href="'.self_url(['page'=>$page-1]).'">‹ Пред</a> &nbsp; ';
}
echo 'Стр. '. $page .' из '. $totalPages;
if ($page < $totalPages) {
    echo ' &nbsp; <a href="'.self_url(['page'=>$page+1]).'">След ›</a> &nbsp; ';
    echo '<a href="'.self_url(['page'=>$totalPages]).'">Последняя »</a>';
}
echo '  </div>';

echo '  <div class="legend sm muted">
        <span class="action-green"><span class="dot"></span>Убрать предупреждение</span>
        <span class="action-red"><span class="dot"></span>Отключить</span>
      </div>';
echo '</div>';

if (get_user_class() >= UC_ADMINISTRATOR) {
    echo '<div style="text-align:right;margin-top:10px">';
    echo '  <input type="hidden" name="nowarned" value="nowarned">';
    echo '  <input type="submit" name="submit" value="Применить">';
    echo '</div>';
}

echo '</form>';

end_frame();

stdfoot();
