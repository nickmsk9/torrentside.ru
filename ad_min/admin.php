<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    stderr($tracker_lang['error'], 'Что вы тут забыли?');
}

require_once $rootpath . 'ad_min/core.php';

$act = $_GET['act'] ?? '';
$op  = $_GET['op']  ?? 'Main';

function ts_admin_styles_once(): void {
    static $done = false;
    if ($done) return; $done = true;

    echo <<<CSS
<style>
:root{
  --ink:#111827; --ink-dim:#6b7280;
  --brd:#e5e7eb; --brd-2:#f1f5f9; --hl:#cbd5e1;
  --accent:#6366f1; --accent-soft:#f5f6ff;
  --bg-glass: transparent;        /* на всякий случай — используется в форме поиска */
  --rad:12px;
}

/* контейнер: без фона и без тени */
.ts-wrap{
  margin:12px 0 18px; padding:14px;
  background:transparent; border:1px solid var(--brd);
  border-radius:calc(var(--rad)+2px);
  box-shadow:none;
}

/* табы-фильтры: прозрачные «пилюли» */
.ts-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:2px 2px 12px; }
.ts-tab{
  padding:8px 12px; border-radius:999px;
  border:1px solid var(--brd);
  background:transparent; color:var(--ink);
  font-size:13px; line-height:1; cursor:pointer; user-select:none;
  transition:border-color .12s, background .12s, transform .12s;
}
.ts-tab:hover{ border-color:var(--hl); transform:translateY(-1px); }
.ts-tab[data-active="1"]{ border-color:var(--accent); background:var(--accent-soft); }

/* сетка плиток (компакт) */
.ts-grid{ display:grid; gap:10px; grid-template-columns:repeat(6,minmax(0,1fr)); }
@media (max-width:1400px){ .ts-grid{ grid-template-columns:repeat(5,1fr);} }
@media (max-width:1200px){ .ts-grid{ grid-template-columns:repeat(4,1fr);} }
@media (max-width:920px){  .ts-grid{ grid-template-columns:repeat(3,1fr);} }
@media (max-width:640px){  .ts-grid{ grid-template-columns:repeat(2,1fr);} }
@media (max-width:420px){  .ts-grid{ grid-template-columns:1fr;} }

/* карточки: без белого фона и без тени */
.ts-card{
  position:relative; display:flex; flex-direction:column; gap:6px;
  padding:10px 10px; border-radius:var(--rad);
  border:1px solid var(--brd);
  background:transparent; box-shadow:none;
  transition:transform .14s, border-color .14s;
}
.ts-card:hover{ transform:translateY(-2px); border-color:var(--hl); }
.ts-card[hidden]{ display:none; }

.ts-link{ text-decoration:none; color:inherit; display:block; }
.ts-ttl{ font-weight:600; font-size:13.5px; letter-spacing:.2px; }
.ts-desc{ font-size:12px; color:var(--ink-dim); }

/* бейдж: прозрачный */
.ts-badge{
  position:absolute; top:8px; right:8px;
  font-size:11px; padding:3px 8px; border-radius:999px;
  border:1px solid var(--brd); background:transparent; color:var(--ink-dim);
}

/* кнопки: контурные, без белого фона и без тени */
.ts-btn{
  display:inline-flex; align-items:center; justify-content:center;
  padding:8px 12px; min-height:36px; border-radius:12px;
  border:1px solid var(--brd); background:transparent; color:var(--ink);
  font-size:13px; font-weight:600; text-decoration:none;
  box-shadow:none; transition:transform .12s, border-color .12s;
}
.ts-btn:hover{ transform:translateY(-1px); border-color:var(--hl); }
.ts-btns{ display:flex; flex-wrap:wrap; gap:8px; }
.ts-btn.ghost{ background:transparent; }
.ts-btn.primary{ border-color:var(--accent); }

/* notice остаются цветными (не белыми) */
.notice{ padding:10px 12px; border-radius:10px; margin-bottom:10px; }
.notice.ok{ border:1px solid #bbf7d0; background:#f0fdf4; }
.notice.err{ border:1px solid #fecaca; background:#fff1f2; }

/* доступность */
.ts-tab:focus,.ts-btn:focus,.ts-link:focus{
  outline:2px solid rgba(99,102,241,.45); outline-offset:2px; border-radius:12px;
}
</style>
<script>
(function(){
  const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
  document.addEventListener('DOMContentLoaded', ()=>{
    const tabs=qsa('.ts-tab');
    const cards=qsa('.ts-card');
    function setCat(cat){
      tabs.forEach(t=>t.dataset.active = (t.dataset.cat===cat?'1':'0'));
      cards.forEach(c=>{
        const ok = (cat==='__all' || c.dataset.cat===cat);
        if (ok) c.removeAttribute('hidden'); else c.setAttribute('hidden','');
      });
    }
    tabs.forEach(t=>t.addEventListener('click', ()=>setCat(t.dataset.cat)));
    setCat('__all');
  });
})();
</script>
CSS;
}



/** =========================
 *  Линки: только новый формат
 *  ========================= */
function ts_load_links_strict(string $dir='ad_min/links'): array {
    $got = [];
    if (!is_dir($dir)) return $got;
    $h = opendir($dir); if (!$h) return $got;

    while(false !== ($f = readdir($h))){
        if (!preg_match('/\.php$/i', $f)) continue;
        $file = $dir.'/'.$f;
        $ret = include $file;
        if (!is_array($ret)) continue; // игнор всего, что не return [...]
        foreach ($ret as $row){
            $title = trim((string)($row['title'] ?? ''));
            $url   = trim((string)($row['url'] ?? ''));
            if ($title==='' || $url==='') continue;
            $got[] = [
                'title'=>$title,
                'url'=>$url,
                'icon'=>trim((string)($row['icon'] ?? '')),
                'category'=>trim((string)($row['category'] ?? 'Прочее')),
                'desc'=>trim((string)($row['desc'] ?? '')),
                'badge'=>trim((string)($row['badge'] ?? '')),
            ];
        }
    }
    closedir($h);
    return $got;
}

/** =========================
 *  Рендер сетки по категориям
 *  ========================= */
function ts_render_admin_grid_strict(array $links): void {
    ts_admin_styles_once();

    // группировка по категории (для табов), но выводим одной сеткой
    $cats = [];
    foreach ($links as $l) {
        $cat = trim($l['category'] ?? '') ?: 'Прочее';
        $cats[$cat] = true;
    }
    ksort($cats, SORT_NATURAL | SORT_FLAG_CASE);

    echo '<div class="ts-wrap">';

    // табы
    echo '<div class="ts-tabs">';
    echo '<div class="ts-tab" data-cat="__all" data-active="1">Все</div>';
    foreach (array_keys($cats) as $cat) {
        $esc = htmlspecialchars($cat, ENT_QUOTES | ENT_SUBSTITUTE);
        echo '<div class="ts-tab" data-cat="'.$esc.'">'.$esc.'</div>';
    }
    echo '</div>';

    // единая сетка плиток (без иконок)
    echo '<div class="ts-grid">';
    foreach ($links as $it){
        $title = htmlspecialchars(trim((string)($it['title'] ?? '')), ENT_QUOTES|ENT_SUBSTITUTE);
        $url   = htmlspecialchars(trim((string)($it['url'] ?? '')),   ENT_QUOTES|ENT_SUBSTITUTE);
        $descR = trim((string)($it['desc'] ?? ''));
        $desc  = $descR !== '' ? htmlspecialchars($descR, ENT_QUOTES|ENT_SUBSTITUTE) : '';
        $badge = trim((string)($it['badge'] ?? ''));
        $cat   = htmlspecialchars(trim((string)($it['category'] ?? 'Прочее')) ?: 'Прочее', ENT_QUOTES|ENT_SUBSTITUTE);

        if ($title === '' || $url === '') continue;

        echo '<article class="ts-card" data-cat="'.$cat.'">';
        if ($badge !== '') {
            echo '<div class="ts-badge">'.htmlspecialchars($badge, ENT_QUOTES|ENT_SUBSTITUTE).'</div>';
        }
        echo '<a class="ts-link" href="'.$url.'">';
        echo   '<div class="ts-ttl">'.$title.'</div>';
        if ($desc !== '') echo '<div class="ts-desc">'.$desc.'</div>';
        echo '</a>';
        echo '</article>';
    }
    echo '</div>'; // .ts-grid

    echo '</div>'; // .ts-wrap
}

/** =========================
 *  Роутинг
 *  ========================= */
switch ($op) {
    case 'Main':
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="6"><tr><td class="colhead">Панель администратора</td></tr></table>';

        // грузим ссылки нового формата и рендерим сетку
        $links = ts_load_links_strict('ad_min/links');
        ts_render_admin_grid_strict($links);

        // Инструменты (плитки)
        if (get_user_class() >= UC_ADMINISTRATOR) {
			end_frame();
            begin_frame('Инструменты');
            echo '<div class="ts-wrap"><div class="ts-btns">';
            $tools = [
                ['warned.php',        'Предупр. юзеры'],
                ['adduser.php',       'Добавить юзера'],
                ['recover.php',       'Восстан. юзера'],
                ['uploaders.php',     'Аплоадеры'],
                ['users.php',         'Список юзеров'],
                ['tags.php',          'Теги'],
                ['smilies.php',       'Смайлы'],
                ['delacctadmin.php',  'Удалить юзера'],
                ['stats.php',         'Статистика'],
                ['testip.php',        'Проверка IP'],
                ['ipcheck.php',       'Повторные IP'],
                ['findnotconnectable.php','Юзеры за NAT'],
            ];
            foreach ($tools as [$u,$t]) {
                echo '<a class="ts-btn" href="'.htmlspecialchars($u, ENT_QUOTES|ENT_SUBSTITUTE).'">'
                   . htmlspecialchars($t, ENT_QUOTES|ENT_SUBSTITUTE).'</a>';
            }
            echo '</div></div>';
            end_frame();
        }

        // Средства модератора (плитки + форма)
        if (get_user_class() >= UC_MODERATOR) {
            begin_frame('Средства');
            echo '<div class="ts-wrap ts-btns">';
            echo '<a class="ts-btn" href="staff.php?act=users">Пользователи с рейтингом ниже 0.20</a>';
            echo '<a class="ts-btn" href="staff.php?act=banned">Отключенные пользователи</a>';
            echo '<a class="ts-btn" href="staff.php?act=last">Новые пользователи</a>';
            echo '<a class="ts-btn" href="log.php">Лог сайта</a>';
            echo '</div>';
            end_frame();

            begin_frame('Искать пользователя');
            echo '<div class="ts-wrap">';
            echo '<form method="get" action="users.php" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
            echo 'Поиск:&nbsp;<input type="text" size="30" name="search" style="padding:8px;border-radius:12px;border:1px solid var(--brd);background:var(--bg-glass);color:var(--ink)">';
            echo '<select name="class" style="padding:8px;border-radius:12px;border:1px solid var(--brd);background:var(--bg-glass);color:var(--ink)">';
            echo '<option value="-">(Выберите)</option>';
            echo '<option value="0">Пользователь</option>';
            echo '<option value="1">Опытный пользователь</option>';
            echo '<option value="2">VIP</option>';
            echo '<option value="3">Заливающий</option>';
            echo '<option value="4">Модератор</option>';
            echo '<option value="5">Администратор</option>';
            echo '<option value="6">Владелец</option>';
            echo '</select>';
            echo '<button type="submit" class="ts-btn">Искать</button>';
            echo '</form>';
            echo '<div style="margin-top:10px" class="ts-btns"><a class="ts-btn" href="usersearch.php">Административный поиск</a></div>';
            echo '</div>';
            end_frame();
        }
        break;

    default:
        if ($handle = opendir('ad_min/modules')) {
            while (($file = readdir($handle)) !== false) {
                if (preg_match('/\.php$/i', $file)) {
                    require_once 'ad_min/modules/' . $file;
                }
            }
            closedir($handle);
        }
        break;
}
