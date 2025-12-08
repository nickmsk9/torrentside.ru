<?php
declare(strict_types=1);

require_once "include/bittorrent.php";
require_once "languages/lang_russian/lang_pages.php";

/* ===== helpers ===== */
function capture_textbbcode($form, $name, $text = '') {
    ob_start();
    textbbcode($form, $name, $text);
    return ob_get_clean();
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ===== init ===== */
dbconn();
loggedinorreturn();

$ajax   = (!empty($_GET['ajax']) && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'));
/** @var mysqli $mysqli */
$mysqli = $GLOBALS['mysqli'];
$id     = (int)($_GET['id'] ?? 0);

/* ===== memcached (mc1 / Memcache / Memcached) ===== */
$mem = $GLOBALS['mc1'] ?? ($GLOBALS['Memcache'] ?? null);
function mem_get($key) {
    global $mem; if (!$mem) return false; return @$mem->get($key);
}
function mem_set($key, $val, int $ttl = 3600) {
    global $mem; if (!$mem) return false;
    if ($mem instanceof Memcache)  return @$mem->set($key, $val, 0, $ttl);
    if ($mem instanceof Memcached) return @$mem->set($key, $val, $ttl);
    return @method_exists($mem,'set') ? $mem->set($key,$val,$ttl) : false;
}
function mem_del($key) {
    global $mem; if ($mem && method_exists($mem,'delete')) @$mem->delete($key);
}

/* ===== countries cache ===== */
function country_options_cached(mysqli $mysqli, int $selected = 0, string $firstOpt = ''): string {
    $key = 'countries:options:ru:v1';
    $options = mem_get($key);
    if ($options === false) {
        $res = sql_query("SELECT id, name FROM countries ORDER BY name");
        $buf = [];
        while ($row = mysqli_fetch_assoc($res)) $buf[(int)$row['id']] = $row['name'];
        $options = $buf;
        mem_set($key, $options, 3600);
    }
    $html = '';
    if ($firstOpt !== '') $html .= $firstOpt;
    foreach ($options as $cid => $name) {
        $sel = ($cid === $selected) ? " selected" : "";
        $html .= "<option value=\"{$cid}\"{$sel}>".h($name)."</option>";
    }
    return $html;
}
function country_name_cached(mysqli $mysqli, int $cid): string {
    if ($cid <= 0) return '';
    $key = "country:name:$cid";
    $name = mem_get($key);
    if ($name !== false) return (string)$name;
    $r = sql_query("SELECT name FROM countries WHERE id = ".(int)$cid." LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    $name = $row['name'] ?? '';
    mem_set($key, $name, 3600);
    return $name;
}

/* =========================================================
 * ADD
 * =======================================================*/
if (isset($_GET['add'])) {
    if (get_user_class() < UC_POWER_USER) stderr($tracker_lang['error'], $tracker_lang['access_denied']);

    stdhead("Добавить актёра");
    echo <<<CSS
<style>
:root{
  --glass-bg: rgba(255,255,255,0.08);
  --glass-brd: rgba(255,255,255,0.25);
  --field-brd: #c8ccd3;
  --field-bg:  rgba(255,255,255,.92);
  --field-fcs: #5b9fff;
}
.person-form input[type=text],
.person-form select,
.person-form textarea{
  width:100%;
  padding:.6rem .7rem;
  font-size:15px;
  border:1px solid var(--field-brd);
  background:var(--field-bg);
  border-radius:10px;
  -webkit-appearance:none; appearance:none;
  color:#111;
}
.person-form textarea{ min-height:140px; resize:vertical; }
.person-form input[type=text]::placeholder,
.person-form textarea::placeholder{ color:#9aa1ab; }
.person-form input[type=text]:focus,
.person-form select:focus,
.person-form textarea:focus{
  outline:0;
  border-color:var(--field-fcs);
  box-shadow:0 0 0 3px rgba(91,159,255,.25);
  background:#fff;
}
.person-form .hint{ color:#8a8f98; font-size:12px }
.glass{
  margin:8px 0; padding:14px 16px;
  background:var(--glass-bg);
  border:1px solid var(--glass-brd);
  border-radius:14px;
  -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
  box-shadow:0 6px 24px rgba(0,0,0,.12);
}
</style>
CSS;

    begin_frame("Добавить актёра");

    $countries = country_options_cached($mysqli, 0, "<option value=\"0\">{$tracker_lang['signup_not_selected']}</option>");

    print("<div class='glass'><form class='person-form' action=\"persons.php?saveadd\" method=\"post\"><table width=\"100%\" border=\"0\" cellpadding=\"6\" cellspacing=\"0\">");
    print("<tr><td class=\"colhead\" colspan=\"2\">{$tracker_lang['page_content']}</td></tr>");
    tr("Имя и Фамилия",
       "<input type='text' name='name' size='80' required><div class='hint'><b>Только по-русски и с большой буквы</b></div>", 1);
    tr("Дата рождения",
       "<input type='text' name='date' size='80' placeholder='ДД.ММ.ГГГГ'><div class='hint'><b>Формат: ДД.ММ.ГГГГ</b></div>", 1);
    tr($tracker_lang['my_country'], "<select name='country'>$countries</select>", 1);
    tr("Фотография персоны", "<input type='text' name='img' size='80' placeholder='https://...'><div class='hint'><b>URL картинки</b></div>", 1);

    $bbcodeEditor = capture_textbbcode("upload", "content");
    tr($tracker_lang['description'], $bbcodeEditor, 1);

    for ($i = 1; $i <= 4; $i++) {
        tr("Скриншот из фильма", "<input type='text' name='img$i' size='80' placeholder='https://...'>", 1);
    }
    print("<tr><td colspan=\"2\" align=\"center\">
        <input type='submit' value='Добавить'>
        <input type='button' onclick=\"ajaxpreview('content');\" value='Предпросмотр'>
        </td></tr></table></form></div>
<script src='js/ajax.js'></script>
<div id='loading-layer' style='display:none'>Загрузка...<br><img src='pic/loading.gif' alt=''></div>
<div id='preview' class='glass' style='margin-top:10px'></div>");
    end_frame();
    stdfoot();
    exit;
}

/* =========================================================
 * SAVE ADD
 * =======================================================*/
if (isset($_GET['saveadd'])) {
    if (get_user_class() < UC_POWER_USER) stderr($tracker_lang['error'], $tracker_lang['access_denied']);

    $fields = ['img','name','content','date','country','img1','img2','img3','img4'];
    $data = [];
    foreach ($fields as $f) $data[$f] = $_POST[$f] ?? '';
    $stmt = $mysqli->prepare("INSERT INTO pages (img, name, content, date, country, img1, img2, img3, img4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssss', $data['img'], $data['name'], $data['content'], $data['date'], $data['country'], $data['img1'], $data['img2'], $data['img3'], $data['img4']);
    $stmt->execute();
    $id = $mysqli->insert_id;

    mem_del('countries:options:ru:v1');

    stderr($tracker_lang['success'], "{$tracker_lang['adding_page']} <a href='persons.php?id=$id'>" . h($data['name']) . "</a>", 'success');
    exit;
}

/* =========================================================
 * EDIT
 * =======================================================*/
if (isset($_GET['edit']) && $id) {
    if (get_user_class() < UC_ADMINISTRATOR) stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    $res = sql_query("SELECT * FROM pages WHERE id = $id LIMIT 1");
    $actor = mysqli_fetch_assoc($res) ?: stderr($tracker_lang['error'], $tracker_lang['no_page_with_this_id']);

    stdhead("Редактировать: " . $actor['name']);
    echo <<<CSS
<style>
:root{
  --glass-bg: rgba(255,255,255,0.08);
  --glass-brd: rgba(255,255,255,0.25);
  --field-brd: #c8ccd3;
  --field-bg:  rgba(255,255,255,.92);
  --field-fcs: #5b9fff;
}
.person-form input[type=text],
.person-form select,
.person-form textarea{
  width:100%;
  padding:.6rem .7rem;
  font-size:15px;
  border:1px solid var(--field-brd);
  background:var(--field-bg);
  border-radius:10px;
  -webkit-appearance:none; appearance:none;
  color:#111;
}
.person-form textarea{ min-height:140px; resize:vertical; }
.person-form input[type=text]::placeholder,
.person-form textarea::placeholder{ color:#9aa1ab; }
.person-form input[type=text]:focus,
.person-form select:focus,
.person-form textarea:focus{
  outline:0;
  border-color:var(--field-fcs);
  box-shadow:0 0 0 3px rgba(91,159,255,.25);
  background:#fff;
}
.glass{
  margin:8px 0; padding:14px 16px;
  background:var(--glass-bg);
  border:1px solid var(--glass-brd);
  border-radius:14px;
  -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
  box-shadow:0 6px 24px rgba(0,0,0,.12);
}
</style>
CSS;

    begin_frame("Редактировать актёра: " . h($actor['name']));

    $countries = country_options_cached($mysqli, (int)$actor['country'], "<option value='0'>---- Не выбрано ----</option>");

    print("<div class='glass'><form class='person-form' action='persons.php?saveedit&id=$id' method='post'><table width='100%' border='0' cellpadding='6' cellspacing='0'>");
    tr("Имя и Фамилия", "<input type='text' name='name' size='80' value=\"" . h($actor['name']) . "\">", 1);
    tr("Дата рождения", "<input type='text' name='date' size='80' value=\"" . h($actor['date']) . "\">", 1);
    tr($tracker_lang['my_country'], "<select name='country'>$countries</select>", 1);
    tr("Фотография", "<input type='text' name='img' size='80' value=\"" . h($actor['img']) . "\">", 1);
$bbEdit = capture_textbbcode('upload', 'content', $actor['content']);
tr($tracker_lang['description'], $bbEdit, 1);    for ($i = 1; $i <= 4; $i++) {
        tr("Скриншот $i", "<input type='text' name='img$i' size='80' value=\"" . h($actor["img$i"]) . "\">", 1);
    }
    print("<tr><td colspan='2' align='center'><input type='submit' value='{$tracker_lang['edit']}'></td></tr></table></form></div>");
    end_frame(); stdfoot(); exit;
}

/* =========================================================
 * SAVE EDIT
 * =======================================================*/
if (isset($_GET['saveedit']) && $id) {
    if (get_user_class() < UC_ADMINISTRATOR) stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    $fields = ['name','img','content','date','country','img1','img2','img3','img4'];
    $data = [];
    foreach ($fields as $f) $data[$f] = $_POST[$f] ?? '';
    $stmt = $mysqli->prepare("UPDATE pages SET name=?, img=?, content=?, date=?, country=?, img1=?, img2=?, img3=?, img4=? WHERE id=?");
    $stmt->bind_param('sssssssssi', $data['name'], $data['img'], $data['content'], $data['date'], $data['country'], $data['img1'], $data['img2'], $data['img3'], $data['img4'], $id);
    $stmt->execute();

    mem_del('countries:options:ru:v1');

    stderr($tracker_lang['success'], "{$tracker_lang['editing_page']} <a href='persons.php?id=$id'>" . h($data['name']) . "</a>", 'success');
    exit;
}

/* =========================================================
 * DELETE
 * =======================================================*/
if (isset($_GET['delete']) && $id) {
    if (get_user_class() < UC_ADMINISTRATOR) stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    sql_query("DELETE FROM pages WHERE id = $id LIMIT 1");
    stderr($tracker_lang['success'], $tracker_lang['page_deleted'], 'success');
    exit;
}

/* =========================================================
 * helpers: render person tab (for AJAX tabs)
 * =======================================================*/
function render_person_tab(string $tab, array $actor, string $country, mysqli $mysqli): string {
    ob_start();
    if ($tab === 'info') {
        echo "<div class='person-view glass'>
          <table width='100%' border='0' cellpadding='6' cellspacing='0'>
            <tr>
              <td width='200' valign='top' style='padding-right:14px'>
                <img class='poster' src='".h($actor['img'])."' alt='".h($actor['name'])."' loading='lazy'>
              </td>
              <td valign='top'>
                <div class='title'>".h($actor['name'])."</div>
                <div class='meta'>Дата: ".h($actor['date'])." · Страна: ".h($country)."</div>
                <div>".format_comment($actor['content'])."</div>
              </td>
            </tr>
          </table>";
        $shots = [];
        for ($i = 1; $i <= 4; $i++) if (!empty($actor["img$i"])) {
            $shots[] = "<img src='".h($actor["img$i"])."' alt='s$i' loading='lazy'>";
        }
        if ($shots) {
            echo "<div class='screens' style='margin-top:12px'><div class='screens-grid'>".implode("", $shots)."</div></div>";
        }
        echo "</div>";
    } elseif ($tab === 'releases') {
        $like = "%".mysqli_real_escape_string($mysqli, $actor['name'])."%";
        $res2 = sql_query("SELECT id, name FROM torrents WHERE descr LIKE '$like' ORDER BY id DESC");
        echo "<table class='films-list' width='100%' cellpadding='5'>";
        while ($r = mysqli_fetch_assoc($res2)) {
            echo "<tr><td><a href='details.php?id={$r['id']}'><b>".h($r['name'])."</b></a></td></tr>";
        }
        echo "</table>";
    } else { // top
        $like = "%".mysqli_real_escape_string($mysqli, $actor['name'])."%";
        $res3 = sql_query("SELECT id, name, seeders FROM torrents WHERE descr LIKE '$like' ORDER BY seeders DESC LIMIT 20");
        echo "<table width='100%' cellpadding='5'>";
        while ($r = mysqli_fetch_assoc($res3)) {
            echo "<tr><td><a href='details.php?id={$r['id']}'><b>".h($r['name'])."</b></a></td><td align='right'>".(int)$r['seeders']."</td></tr>";
        }
        echo "</table>";
    }
    return ob_get_clean();
}

/* =========================================================
 * VIEW (персона)
 * =======================================================*/
$tab = $_GET['tab'] ?? 'info';

if ($id) {
    $res = sql_query("SELECT * FROM pages WHERE id = $id LIMIT 1");
    $actor = mysqli_fetch_assoc($res) ?: stderr($tracker_lang['error'], $tracker_lang['no_page_with_this_id']);
    $country = country_name_cached($mysqli, (int)$actor['country']);

    // AJAX контент вкладок
    if ($ajax && (($_GET['type'] ?? '') === 'tab')) {
        $tab = $_GET['tab'] ?? 'info';
        echo render_person_tab($tab, $actor, $country, $mysqli);
        exit;
    }

   $canEdit = (get_user_class() >= UC_SYSOP);
$editBtnHtml = $canEdit ? "<div class='person-actions'><a class='btn-edit' href='persons.php?edit&id=$id'>✎ Редактировать</a></div>" : "";





  stdhead("Актёр: " . $actor['name']);
echo <<<CSS
<style>
:root{ --glass-bg: rgba(255,255,255,0.08); --glass-brd: rgba(255,255,255,0.25); --text-dim:#8a8f98; }
.person-view{ font-size:16px; line-height:1.6 }
.person-view .title{ font-size:28px; font-weight:800; margin:0 0 6px }
.person-view .meta{ color:var(--text-dim); margin-bottom:10px }
.person-view .poster{ width:180px; height:240px; border-radius:10px; border:1px solid var(--glass-brd); box-shadow:0 6px 18px rgba(0,0,0,.18); object-fit:cover; background:#f6f6f6; }
.person-view .screens img{ width:100%; height:auto; aspect-ratio:16/9; object-fit:cover; border-radius:10px; border:1px solid var(--glass-brd); box-shadow:0 6px 18px rgba(0,0,0,.12); }
.person-view .screens-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:10px }
@media (max-width: 900px){ .person-view .screens-grid{grid-template-columns:repeat(2,1fr)} }
@media (max-width: 600px){ .person-view .screens-grid{grid-template-columns:1fr} }
.glass{ margin:8px 0; padding:14px 16px; background:var(--glass-bg); border:1px solid var(--glass-brd); border-radius:14px; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); box-shadow:0 6px 24px rgba(0,0,0,.12); }
.person-view table{ border-collapse:separate; border-spacing:0 }
.person-view td{ vertical-align:top }
/* вкладки */
.tabs{ margin:6px 0 10px; border-bottom:1px solid #e2e2e2; background:linear-gradient(#f7fbff,#eef5ff); padding:6px 8px 0; }
.tabs a{ display:inline-block; margin-right:10px; padding:6px 10px; font-weight:bold; color:#2b2b2b; text-decoration:none; border:1px solid transparent; border-radius:4px 4px 0 0; }
.tabs a.active{ background:#fff; border-color:#d9e5ff #d9e5ff #fff; }
/* отдельная кнопка */
.person-actions{ display:flex; justify-content:flex-end; margin:6px 0 2px; }
.btn-edit{ display:inline-block; padding:6px 10px; font-size:12px; background:#e7ecf3; border:1px solid #b8c4d4; border-radius:6px; text-decoration:none; color:#1d2a3a; }
.btn-edit:hover{ background:#dfe7f2; }
</style>
CSS;

begin_frame($actor['name']);        // без кнопки в заголовке
echo $editBtnHtml;                  // отдельным блоком над вкладками


    echo "<div class='tabs' id='person-tabs' data-id='$id'>";
    echo "  <a href='persons.php?id=$id&tab=info' data-tab='info' class='".($tab==='info'?'active':'')."'>Информация</a>";
    echo "  <a href='persons.php?id=$id&tab=releases' data-tab='releases' class='".($tab==='releases'?'active':'')."'>Раздачи персоны</a>";
    echo "  <a href='persons.php?id=$id&tab=top' data-tab='top' class='".($tab==='top'?'active':'')."'>Топ раздач персоны</a>";
    echo "</div>";

    echo "<div id='person-tab'>". render_person_tab($tab, $actor, $country, $mysqli) ."</div>";

    end_frame();
    ?>
    <script>
    (function(){
      // AJAX-вкладки на странице персоны
      const tabs = document.getElementById('person-tabs');
      const box  = document.getElementById('person-tab');
      if (!tabs || !box) return;
      const id = tabs.dataset.id;

      tabs.addEventListener('click', async (e)=>{
        const a = e.target.closest('a[data-tab]');
        if (!a) return;
        e.preventDefault();
        const tab = a.dataset.tab;
        const url = `persons.php?id=${id}&ajax=1&type=tab&tab=${encodeURIComponent(tab)}`;
        const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        box.innerHTML = await r.text();
        tabs.querySelectorAll('a').forEach(el => el.classList.toggle('active', el===a));
        history.replaceState(null, '', `persons.php?id=${id}&tab=${tab}`);
      });
    })();
    </script>
    <?php
    stdfoot(); exit;
}

/* ===== AJAX name suggest (для круглой строки поиска) ===== */
if (!empty($_GET['ajax']) && (($_GET['type'] ?? '') === 'suggest')) {
    header('Content-Type: application/json; charset=UTF-8');
    $term = trim((string)($_GET['q'] ?? ''));
    if ($term === '') { echo json_encode([]); exit; }
    $like = '%' . mysqli_real_escape_string($mysqli, $term) . '%';
    $r = sql_query("SELECT id, name, img FROM pages WHERE name LIKE '$like' ORDER BY name LIMIT 8");
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id'   => (int)$row['id'],
            'name' => $row['name'],
            'img'  => (string)($row['img'] ?: 'styles/images/nophoto.png'),
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
 * LIST + SEARCH  (GRID)
 * =======================================================*/
$placeholder = 'styles/images/nophoto.png';

// входные параметры
$q     = trim($_GET['q'] ?? '');
$today = isset($_GET['bday']) ? 1 : 0;

// WHERE
$where = [];
if ($q !== '') {
    $qesc = mysqli_real_escape_string($mysqli, $q);
    $where[] = "name LIKE '%$qesc%'";
}
if ($today) {
    $dm = date('d.m');
    $where[] = "SUBSTRING_INDEX(date, '.', 2) = '$dm'";
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// всего строк
$total = get_row_count('pages', $whereSql);
// пагинация
list($pagertop, $pagerbottom, $limit) = pager2(
    48,
    $total,
    "persons.php?" . ($q!=='' ? "q=".urlencode($q)."&" : "") . ($today ? "bday=1&" : "")
);

// сформировать список
$res = sql_query("SELECT id, name, img, date FROM pages $whereSql ORDER BY id DESC $limit");

ob_start();
echo "<div class='index'>$pagertop</div>";
echo "<div class='grid'>";
while ($row = mysqli_fetch_assoc($res)) {
    $pid  = (int)$row['id'];
    $nm   = h($row['name']);
    $date = h($row['date']);
    $img  = trim((string)$row['img']) !== '' ? h($row['img']) : $placeholder;
    echo "<a class='card' href='persons.php?id={$pid}'>";
    echo   "<img class='pic' src='{$img}' alt='{$nm}' loading='lazy'>";
    echo   "<div class='nm'>{$nm}</div>";
    if ($date !== '') echo "<div class='meta'>{$date}</div>";
    echo "</a>";
}
echo "</div>";
echo "<div class='index'>$pagerbottom</div>";
$listHtml = ob_get_clean();

// === AJAX ответ только списком, БЕЗ шапок ===
if ($ajax && (($_GET['type'] ?? '') !== 'suggest')) { echo $listHtml; exit; }

// ======= Дальше — только обычная страница (не AJAX) =======
stdhead("Актёры");
echo <<<CSS
<style>
.persons-bar { display:flex; align-items:center; gap:10px; margin:8px 0 12px; flex-wrap:wrap; }
.persons-bar .search { position:relative; display:flex; align-items:center; background:#f3f5f8; border:1px solid #cfd6df; border-radius:999px; padding:6px 10px; min-width:280px; }
.persons-bar .search input[type="text"]{ border:0; outline:0; background:transparent; width:220px; font-size:14px; }
.persons-bar .search button{ border:1px solid #b8c4d4; background:#e7ecf3; border-radius:999px; padding:6px 10px; font-size:13px; cursor:pointer; }
.persons-bar .search .clear{ position:absolute; right:84px; border:0; background:transparent; cursor:pointer; font-size:16px; line-height:1; opacity:.6; }
.persons-bar .chip{ display:inline-flex; align-items:center; gap:6px; border:1px solid #cfd6df; background:#fbfbfc; border-radius:999px; padding:6px 10px; font-size:13px; }
.persons-bar .chip input{ margin:0 }
.persons-bar .hint{ color:#7b8794; font-size:12px }
.grid{ display:flex; flex-wrap:wrap; gap:10px; }
.card{ width:150px; text-decoration:none; background:#f7f7f7; border:1px solid #d9d9d9; border-radius:4px; overflow:hidden; }
.card:hover{ box-shadow:0 2px 8px rgba(0,0,0,.08); }
.card .pic{ display:block; width:150px; height:200px; object-fit:cover; background:#eee; border-bottom:1px solid #e3e3e3; }
.card .nm{ padding:6px 6px 2px; color:#1f1f1f; font-weight:bold; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.card .meta{ padding:0 6px 8px; color:#666; font-size:12px; }
.suggest{ position:absolute; top:100%; left:0; right:0; z-index:30; background:#fff; border:1px solid #cfd6df; border-radius:10px; margin-top:6px; box-shadow:0 8px 24px rgba(0,0,0,.08); overflow:hidden; }
.suggest-item{ display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:pointer; }
.suggest-item:hover{ background:#f3f7ff; }
.suggest-item img{ width:32px; height:32px; object-fit:cover; border-radius:6px; border:1px solid #e3e7ee; }
</style>
CSS;

begin_frame("Персоны" . (get_user_class() >= UC_POWER_USER ? " <small>[<a href='persons.php?add'>{$tracker_lang['adding_page']}</a>]</small>" : ""));

// тулбар
$clearDisplay = ($q !== '') ? 'block' : 'none';
$checkedAttr  = $today ? 'checked' : '';
echo <<<HTML
<div class="persons-bar">
  <form id="persons-form" class="search" action="persons.php" method="get" autocomplete="off">
    <input type="text" name="q" id="q" placeholder="Поиск персон…" value="{$q}">
    <button type="button" class="clear" title="Очистить" aria-label="Очистить" style="display:{$clearDisplay}">×</button>
    <button type="submit" id="search-btn">Найти</button>
    <div id="suggest" class="suggest" style="display:none"></div>
  </form>
  <label class="chip"><input type="checkbox" name="bday" id="bday" value="1" {$checkedAttr}> Сегодня день рождения</label>
  <div class="hint">Совет: начинай печатать имя — подсказки выпадут сразу</div>
</div>
HTML;

echo "<div id='persons-list'>{$listHtml}</div>";
end_frame();
?>

<script>
(function(){
  const root  = document.getElementById('persons-list');
  const form  = document.getElementById('persons-form');
  const input = document.getElementById('q');
  const bday  = document.getElementById('bday');
  const clearBtn = form.querySelector('.clear');
  const suggest  = document.getElementById('suggest');
  const btn = document.getElementById('search-btn');
  let t = null;

  function qs(obj){
    const p = new URLSearchParams(obj);
    return 'persons.php?' + p.toString();
  }

  async function load(urlOrParams){
    let url = (typeof urlOrParams === 'string') ? urlOrParams : qs(urlOrParams);
    url += (url.includes('?') ? '&' : '?') + 'ajax=1';
    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const html = await r.text();
    root.innerHTML = html;
    history.replaceState(null, '', url.replace('&ajax=1','').replace('?ajax=1',''));
    wirePager();
  }

  async function suggestLoad(q){
    if (!q) { suggest.style.display='none'; suggest.innerHTML=''; return; }
    const url = qs({ajax:1, type:'suggest', q});
    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const data = await r.json();
    if (!Array.isArray(data) || data.length===0) { suggest.style.display='none'; suggest.innerHTML=''; return; }
    suggest.innerHTML = data.map(it =>
      `<div class="suggest-item" data-id="${it.id}">
         <img src="${it.img}" alt="">
         <div>${escapeHtml(it.name)}</div>
       </div>`).join('');
    suggest.style.display='block';
  }

  function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m])); }

  // ---- события поиска (AJAX)
  form.addEventListener('submit', e => {
    e.preventDefault();
    suggest.style.display='none';
    load({ q: input.value.trim(), bday: bday.checked ? 1 : '' });
  });

  input.addEventListener('input', () => {
    const v = input.value.trim();
    clearBtn.style.display = v ? 'block' : 'none';
    btn.disabled = false;
    clearTimeout(t);
    t = setTimeout(()=> suggestLoad(v), 250);
  });

  clearBtn.addEventListener('click', () => {
    input.value = '';
    clearBtn.style.display='none';
    suggest.style.display='none';
    load({ q:'', bday: bday.checked ? 1 : '' });
  });

  bday.addEventListener('change', () => {
    load({ q: input.value.trim(), bday: bday.checked ? 1 : '' });
  });

  suggest.addEventListener('click', (e)=>{
    const item = e.target.closest('.suggest-item');
    if (!item) return;
    const id = item.getAttribute('data-id');
    window.location.href = 'persons.php?id=' + id;
  });

  // ---- AJAX пагинация/фильтры внутри списка
  function wirePager(){
    root.querySelectorAll('a').forEach(a=>{
      if (/persons\.php/.test(a.href) && !/persons\.php\?id=/.test(a.href)) {
        a.addEventListener('click', (e)=>{
          e.preventDefault();
          load(a.href);
        });
      }
    });
  }
  wirePager();
})();
</script>
<?php
stdfoot();
exit;