<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    stderr("Ошибка доступа", $tracker_lang['access_denied']);
}

$action = (string)($_REQUEST['action'] ?? '');

// =================== helpers + csrf ===================
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_check_soft(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['csrf_token'])) {
        $ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
        if (!$ok) stderr('Ошибка безопасности', 'Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
}

// =================== компактный UI ===================
echo <<<CSS
<style>
:root{
  --ink:#111827; --ink-dim:#6b7280;
  --brd:#e5e7eb; --brd2:#f1f5f9; --hl:#cbd5e1; --accent:#6366f1; --accent-soft:#f5f6ff;
  --rad:8px; --fz:13px; --fz-sm:11.5px;
}

/* прозрачные, очень компактные */
.ts-wrap{ margin:8px 0 12px; padding:10px; background:transparent; border:1px solid var(--brd); border-radius:var(--rad); }
.notice{ padding:8px 10px; border-radius:8px; margin-bottom:8px; font-size:var(--fz); }
.notice.ok{ border:1px solid #bbf7d0; background:#f0fdf4; }
.notice.err{ border:1px solid #fecaca; background:#fff1f2; }
.notice.warn{ border:1px solid #fde68a; background:#fffbeb; }

/* кнопки */
.ts-btn{ display:inline-flex; align-items:center; justify-content:center; gap:6px;
  padding:6px 10px; min-height:28px; border-radius:8px; border:1px solid var(--brd);
  background:transparent; color:var(--ink); font-size:var(--fz); font-weight:600; text-decoration:none;
  transition:transform .12s, border-color .12s; }
.ts-btn:hover{ transform:translateY(-1px); border-color:var(--hl); }
.ts-btn.primary{ border-color:var(--accent); }
.ts-btns{ display:flex; flex-wrap:wrap; gap:6px; }

/* форма — плотная сетка 160/1fr */
.ts-form{ display:grid; grid-template-columns: 160px 1fr; gap:8px 10px; font-size:var(--fz); }
.ts-form .row{ display:contents; }
.ts-input,.ts-number{
  width:100%; padding:6px 8px; border:1px solid var(--brd); border-radius:8px; background:transparent; color:var(--ink); font-size:var(--fz);
}
.ts-actions{ display:flex; gap:6px; justify-content:flex-end; margin-top:6px; }
.small{ font-size:var(--fz-sm); color:var(--ink-dim); }

/* таблица — compact + sticky head + строгие колонки */
.tablebox{ border:1px solid var(--brd); border-radius:8px; overflow:hidden; background:transparent; }
.tablebox .scroll{ max-height:60vh; overflow:auto; } /* чтобы не тянуть страницу */
table.t{ width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; font-size:var(--fz); }
thead th{ position:sticky; top:0; background:rgba(255,255,255,.6); backdrop-filter:saturate(1.2) blur(2px);
  font-weight:700; border-bottom:1px solid var(--brd); text-align:left; padding:8px 10px; }
tbody td{ padding:7px 10px; border-bottom:1px solid var(--brd2); vertical-align:middle; }
tbody tr:last-child td{ border-bottom:none; }
td.center,th.center{ text-align:center; }
td.right, th.right{ text-align:right; white-space:nowrap; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }

/* изображения — мельче */
img.cat{ width:28px; height:28px; object-fit:contain; border-radius:6px; border:1px solid var(--brd); background:transparent; }

/* топ-панель с пагинацией */
.topbar{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin:6px 0 10px; }
.topbar .left{ display:flex; gap:8px; align-items:center; }
.topbar .right{ display:flex; gap:6px; align-items:center; }
select.ts-input{ height:28px; padding:0 8px; }
</style>
CSS;

// =================== HEAD ===================
stdhead('Категории');
begin_frame('Управление категориями');

// =================== DELETE ===================
if ($action === 'delete' && is_valid_id((int)($_GET['id'] ?? 0))) {
    $id = (int)$_GET['id'];
    $cat = (string)($_GET['cat'] ?? '');
    if (($_GET['sure'] ?? '') === 'yes') {
        sql_query("DELETE FROM categories WHERE id = $id LIMIT 1");
        echo "<div class='notice ok'>Категория <b>".h($cat)."</b> удалена. <a class='ts-btn' href='category.php'>Назад</a></div>";
        end_frame(); stdfoot(); exit;
    } else {
        echo "<div class='notice warn'>Удалить категорию <b>".h($cat)."</b>?
              <div class='ts-btns' style='margin-top:6px'>
                <a class='ts-btn primary' href='?action=delete&id=$id&cat=".urlencode($cat)."&sure=yes'>Да</a>
                <a class='ts-btn' href='category.php'>Нет</a>
              </div></div>";
        end_frame(); stdfoot(); exit;
    }
}

// =================== EDIT (POST) ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && is_valid_id((int)($_POST['id'] ?? 0))) {
    csrf_check_soft();
    $id   = (int)$_POST['id'];
    $name = trim((string)($_POST['name'] ?? ''));
    $img  = trim((string)($_POST['img']  ?? ''));
    $sort = (int)($_POST['sort'] ?? 0);

    sql_query("UPDATE categories SET name = " . sqlesc($name) . ", image = " . sqlesc($img) . ", sort = $sort WHERE id = $id LIMIT 1");
    echo "<div class='notice ok'>Категория обновлена. <a class='ts-btn' href='category.php'>Назад</a></div>";
    end_frame(); stdfoot(); exit;
}

// =================== ADD (POST) ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    csrf_check_soft();
    $name = trim((string)($_POST['name'] ?? ''));
    $img  = trim((string)($_POST['img']  ?? ''));
    $sort = (int)($_POST['sort'] ?? 0);

    if ($name !== '') {
        sql_query("INSERT INTO categories (name, image, sort) VALUES (" . sqlesc($name) . ", " . sqlesc($img) . ", $sort)");
        echo "<div class='notice ok'>Категория добавлена. <a class='ts-btn' href='category.php'>Назад</a></div>";
    } else {
        echo "<div class='notice err'>Название не может быть пустым.</div>";
    }
    end_frame(); stdfoot(); exit;
}

// =================== EDIT FORM ===================
if ($action === 'editform' && is_valid_id((int)($_GET['id'] ?? 0))) {
    $id  = (int)$_GET['id'];
    $res = sql_query("SELECT * FROM categories WHERE id = $id LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        $name = h($row['name']);
        $img  = h($row['image']);
        $sort = (int)$row['sort'];
        $tok  = csrf_token();

        echo "<div class='ts-wrap'>
<h3 style='margin:0 0 6px; font-size:14px'>Редактировать категорию</h3>
<form method='post' action='category.php' class='ts-form'>
  <input type='hidden' name='action' value='edit'>
  <input type='hidden' name='id' value='{$id}'>
  ".($tok!=='' ? "<input type='hidden' name='csrf_token' value='".h($tok)."'>" : "")."
  <div class='row'><label class='small'>Название</label><input class='ts-input' type='text' name='name' value='{$name}' maxlength='255' required></div>
  <div class='row'><label class='small'>Картинка (файл)</label><input class='ts-input' type='text' name='img' value='{$img}' maxlength='255' placeholder='cat.png'></div>
  <div class='row'><label class='small'>Сортировка</label><input class='ts-number mono' type='number' name='sort' value='{$sort}'></div>
  <div class='row'><div></div><div class='ts-actions'>
    <a class='ts-btn' href='category.php'>Отмена</a>
    <button class='ts-btn primary' type='submit'>Сохранить</button>
  </div></div>
</form>
</div>";
        end_frame(); stdfoot(); exit;
    }
}

// =================== ADD FORM (compact) ===================
$tok = csrf_token();
echo "<div class='ts-wrap'>
<h3 style='margin:0 0 6px; font-size:14px'>Добавить новую категорию</h3>
<form method='post' action='category.php' class='ts-form'>
  <input type='hidden' name='action' value='add'>
  ".($tok!=='' ? "<input type='hidden' name='csrf_token' value='".h($tok)."'>" : "")."
  <div class='row'><label class='small'>Название</label><input class='ts-input' type='text' name='name' maxlength='255' required></div>
  <div class='row'><label class='small'>Картинка (файл)</label><input class='ts-input' type='text' name='img' maxlength='255' placeholder='cat.png'></div>
  <div class='row'><label class='small'>Сортировка</label><input class='ts-number mono' type='number' name='sort' value='0'></div>
  <div class='row'><div></div><div class='ts-actions'>
    <button class='ts-btn primary' type='submit'>Добавить</button>
  </div></div>
</form>
</div>";

// =================== LIST + пагинация ===================
$pp   = max(10, min(100, (int)($_GET['pp'] ?? 30)));     // на странице
$page = max(1, (int)($_GET['page'] ?? 1));
list($total) = mysqli_fetch_row(sql_query("SELECT COUNT(*) FROM categories"));
$pages  = max(1, (int)ceil($total / $pp));
$page   = min($page, $pages);
$offset = ($page - 1) * $pp;

$q = sql_query("SELECT * FROM categories ORDER BY sort, id LIMIT $offset, $pp");

// topbar (компактная)
$queryBase = function(array $extra = []) use ($pp) {
    $q = ['pp' => $pp] + $extra;
    return 'category.php?' . http_build_query($q);
};

echo "<div class='topbar'>
  <div class='left small'>Всего: <b>{$total}</b> • Стр.: <b>{$page}</b>/<b>{$pages}</b></div>
  <div class='right'>
    ".($page>1 ? "<a class='ts-btn' href='".$queryBase(['page'=>$page-1])."'>&laquo; Назад</a>" : "<span class='small' style='opacity:.6'>Начало</span>")."
    ".($page<$pages ? "<a class='ts-btn' href='".$queryBase(['page'=>$page+1])."'>Вперёд &raquo;</a>" : "<span class='small' style='opacity:.6'>Конец</span>")."
    <form method='get' action='category.php' style='display:inline-flex;gap:6px;align-items:center;margin-left:6px'>
      <input type='hidden' name='page' value='1'>
      <label class='small'>На странице</label>
      <select name='pp' class='ts-input'>
        <option ".($pp===20?'selected':'')." value='20'>20</option>
        <option ".($pp===30?'selected':'')." value='30'>30</option>
        <option ".($pp===50?'selected':'')." value='50'>50</option>
        <option ".($pp===100?'selected':'')." value='100'>100</option>
      </select>
      <button class='ts-btn' type='submit'>OK</button>
    </form>
  </div>
</div>";

echo "<div class='tablebox'><div class='scroll'>
<table class='t'>
  <colgroup>
    <col style='width:64px'>
    <col style='width:30%'>
    <col style='width:72px'>
    <col style='width:96px'>
    <col style='width:108px'>
    <col style='width:116px'>
    <col style='width:104px'>
  </colgroup>
  <thead>
    <tr>
      <th>ID</th>
      <th>Название</th>
      <th class='center'>Картинка</th>
      <th class='right'>Сортировка</th>
      <th class='center'>Просмотр</th>
      <th class='center'>Редактировать</th>
      <th class='center'>Удалить</th>
    </tr>
  </thead>
  <tbody>";

while ($row = mysqli_fetch_assoc($q)) {
    $id   = (int)$row['id'];
    $name = h($row['name']);
    $img  = h($row['image']);
    $sort = (int)$row['sort'];

    $imgSrc = rtrim((string)$DEFAULTBASEURL, '/') . '/pic/cats/' . rawurlencode($img);
    $view   = "browse.php?cat={$id}";
    $edit   = "category.php?action=editform&id={$id}";
    $del    = "category.php?action=delete&id={$id}&cat=" . urlencode($name);

    echo "<tr>
      <td class='mono'>{$id}</td>
      <td><b>{$name}</b></td>
      <td class='center'>".($img !== '' ? "<img class='cat' src='{$imgSrc}' alt=''>" : "<span class='small'>—</span>")."</td>
      <td class='right mono'>{$sort}</td>
      <td class='center'><a class='ts-btn' href='{$view}'>Открыть</a></td>
      <td class='center'><a class='ts-btn' href='{$edit}'>Править</a></td>
      <td class='center'><a class='ts-btn' href='{$del}'>Удалить</a></td>
    </tr>";
}

echo "  </tbody>
</table>
</div></div>";

end_frame();
stdfoot();
