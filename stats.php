<?php
declare(strict_types=1);

require "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'], "Доступ запрещён.");
}

stdhead("Статистика");

// ===== helpers =====
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function self_url(array $q): string {
    $base = $_SERVER['PHP_SELF'] ?? '';
    $qs   = http_build_query($q);
    return h($base . ($qs ? '?' . $qs : ''));
}

// ===== memcached (опционально) =====
/** @var Memcached|null $memcached */
global $memcached;
if (!($memcached instanceof Memcached)) {
    $memcached = new Memcached();
    $memcached->addServer('127.0.0.1', 11211);
}
$TTL = 60; // секунд

// ===== totals (кэшируем) =====
$totals = $memcached->get('stats:totals:v1');
if ($totals === false) {
    [$n_tor]   = mysqli_fetch_row(sql_query("SELECT COUNT(*) FROM torrents"));
    [$n_peers] = mysqli_fetch_row(sql_query("SELECT COUNT(*) FROM peers"));
    $totals = ['n_tor' => (int)$n_tor, 'n_peers' => (int)$n_peers];
    $memcached->set('stats:totals:v1', $totals, $TTL);
}
$n_tor   = $totals['n_tor'];
$n_peers = $totals['n_peers'];

// ===== безопасные параметры сортировки =====
$uporder  = (string)($_GET['uporder']  ?? '');
$catorder = (string)($_GET['catorder'] ?? '');
$limit    = max(50, min(500, (int)($_GET['limit'] ?? 200))); // подстраховка, на всякий

$up_map = [
    ''         => 'name',
    'uploader' => 'name',
    'lastul'   => 'last DESC, name',
    'torrents' => 'n_t DESC, name',
    'peers'    => 'n_p DESC, name',
];
$cat_map = [
    ''          => 'cname',
    'category'  => 'cname',
    'lastul'    => 'last DESC, cname',
    'torrents'  => 'n_t DESC, cname',
    'peers'     => 'n_p DESC, cname',
];
$up_order_sql  = $up_map[$uporder]   ?? $up_map[''];
$cat_order_sql = $cat_map[$catorder] ?? $cat_map[''];

// ======================== ЗАЛИВАЮЩИЕ ========================
begin_main_frame();
begin_frame("Статистика заливающих");

// кэш ключ зависит от сортировки и лимита
$cache_key_uploaders = "stats:uploaders:v2:{$up_order_sql}:{$limit}";
$uploaders = $memcached->get($cache_key_uploaders);

if ($uploaders === false) {
    // t_agg: считаем по торрентам один раз
    // p_agg: считаем пиров через JOIN с torrents, но уже агрегированно
    $sql = "
        SELECT
            u.id,
            u.username AS name,
            COALESCE(t_agg.last, NULL) AS last,
            COALESCE(t_agg.n_t, 0)     AS n_t,
            COALESCE(p_agg.n_p, 0)     AS n_p
        FROM users u
        LEFT JOIN (
            SELECT owner AS uid, MAX(added) AS last, COUNT(*) AS n_t
            FROM torrents
            GROUP BY owner
        ) AS t_agg ON t_agg.uid = u.id
        LEFT JOIN (
            SELECT t.owner AS uid, COUNT(p.id) AS n_p
            FROM peers p
            INNER JOIN torrents t ON t.id = p.torrent
            GROUP BY t.owner
        ) AS p_agg ON p_agg.uid = u.id
        WHERE u.class >= 3
        ORDER BY {$up_order_sql}
        LIMIT {$limit}
    ";
    $res = sql_query($sql);
    $uploaders = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $uploaders[] = $r;
    }
    $memcached->set($cache_key_uploaders, $uploaders, $TTL);
}

// лёгкий css (один раз на страницу)
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
  .head-actions{margin:6px 0 10px 0}
  .head-actions a{margin-right:10px}
</style>
CSS;

echo '<div class="head-actions sm muted">';
echo 'Сортировать: ';
echo '<a href="'.self_url(['uporder'=>'uploader','catorder'=>$catorder]).'">по имени</a>';
echo '<a href="'.self_url(['uporder'=>'lastul','catorder'=>$catorder]).'">по последней заливке</a>';
echo '<a href="'.self_url(['uporder'=>'torrents','catorder'=>$catorder]).'">по кол-ву торрентов</a>';
echo '<a href="'.self_url(['uporder'=>'peers','catorder'=>$catorder]).'">по пирами</a>';
echo '</div>';

if (!$uploaders) {
    stdmsg("Извините", "Нет заливающих.");
} else {
    echo '<table class="stat-table">';
    echo '<tr>
            <th>Заливающий</th>
            <th>Последняя заливка</th>
            <th class="r">Торрентов</th>
            <th class="r">Доля</th>
            <th class="r">Пиров</th>
            <th class="r">Доля</th>
          </tr>';
    foreach ($uploaders as $u) {
        $name = h($u['name']);
        $last = $u['last']
            ? h($u['last']).' <span class="muted sm">('.get_elapsed_time(sql_timestamp_to_unix_timestamp($u['last'])).' назад)</span>'
            : '<span class="muted">—</span>';
        $nt   = (int)$u['n_t'];
        $np   = (int)$u['n_p'];
        $pt   = ($n_tor   > 0) ? number_format($nt * 100 / $n_tor, 1) . '%' : '—';
        $pp   = ($n_peers > 0) ? number_format($np * 100 / $n_peers, 1) : 0;
        echo '<tr>';
        echo '  <td><a href="userdetails.php?id='.(int)$u['id'].'"><b>'.$name.'</b></a></td>';
        echo '  <td class="nowrap">'.$last.'</td>';
        echo '  <td class="r"><span class="pill">'.number_format($nt).'</span></td>';
        echo '  <td class="r"><span class="pill small">'.$pt.'</span></td>';
        echo '  <td class="r"><span class="pill">'.number_format($np).'</span></td>';
        echo '  <td class="r"><span class="pill small">'.($n_peers>0? h(number_format($pp,1)).'%':'—').'</span></td>';
        echo '</tr>';
    }
    echo '</table>';
}
end_frame();

// ======================== КАТЕГОРИИ ========================
begin_frame("Активность категорий");

// кэш ключ зависит от сортировки
$cache_key_cats = "stats:cats:v2:{$cat_order_sql}";
$cats = $memcached->get($cache_key_cats);

if ($cats === false) {
    $sql = "
        SELECT
            c.id,
            c.name AS cname,
            COALESCE(t_agg.last, NULL) AS last,
            COALESCE(t_agg.n_t, 0)     AS n_t,
            COALESCE(p_agg.n_p, 0)     AS n_p
        FROM categories c
        LEFT JOIN (
            SELECT category AS cid, MAX(added) AS last, COUNT(*) AS n_t
            FROM torrents
            GROUP BY category
        ) AS t_agg ON t_agg.cid = c.id
        LEFT JOIN (
            SELECT t.category AS cid, COUNT(p.id) AS n_p
            FROM peers p
            INNER JOIN torrents t ON t.id = p.torrent
            GROUP BY t.category
        ) AS p_agg ON p_agg.cid = c.id
        ORDER BY {$cat_order_sql}
    ";
    $res = sql_query($sql);
    $cats = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $cats[] = $r;
    }
    $memcached->set($cache_key_cats, $cats, $TTL);
}

echo '<div class="head-actions sm muted">';
echo 'Сортировать: ';
echo '<a href="'.self_url(['uporder'=>$uporder,'catorder'=>'category']).'">по названию</a>';
echo '<a href="'.self_url(['uporder'=>$uporder,'catorder'=>'lastul']).'">по последней заливке</a>';
echo '<a href="'.self_url(['uporder'=>$uporder,'catorder'=>'torrents']).'">по кол-ву торрентов</a>';
echo '<a href="'.self_url(['uporder'=>$uporder,'catorder'=>'peers']).'">по пирами</a>';
echo '</div>';

if (!$cats) {
    stdmsg("Извините", "Данные по категориям отсутствуют!");
} else {
    echo '<table class="stat-table">';
    echo '<tr>
            <th>Категория</th>
            <th>Последняя заливка</th>
            <th class="r">Торрентов</th>
            <th class="r">Доля</th>
            <th class="r">Пиров</th>
            <th class="r">Доля</th>
          </tr>';
    foreach ($cats as $c) {
        $last = $c['last']
            ? h($c['last']).' <span class="muted sm">('.get_elapsed_time(sql_timestamp_to_unix_timestamp($c['last'])).' назад)</span>'
            : '<span class="muted">—</span>';
        $nt = (int)$c['n_t'];
        $np = (int)$c['n_p'];
        $pt = ($n_tor   > 0) ? number_format($nt * 100 / $n_tor, 1) . '%' : '—';
        $pp = ($n_peers > 0) ? number_format($np * 100 / $n_peers, 1) : 0;

        echo '<tr>';
        echo '  <td class="rowhead">'.h($c['cname']).'</td>';
        echo '  <td class="nowrap">'.$last.'</td>';
        echo '  <td class="r"><span class="pill">'.number_format($nt).'</span></td>';
        echo '  <td class="r"><span class="pill small">'.$pt.'</span></td>';
        echo '  <td class="r"><span class="pill">'.number_format($np).'</span></td>';
        echo '  <td class="r"><span class="pill small">'.($n_peers>0? h(number_format($pp,1)).'%':'—').'</span></td>';
        echo '</tr>';
    }
    echo '</table>';
}
end_frame();
end_main_frame();

stdfoot();
