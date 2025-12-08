<?php
require_once("include/bittorrent.php");
gzip();

dbconn(false);

/* ===== per-user browse mode (list/thumbs) ===== */
function get_browse_mode(): string {
    global $CURUSER, $memcached;
    $uid = (int)($CURUSER['id'] ?? 0);
    $def = 'list'; // дефолт ВСЕГДА список

    // 1) Memcached
    if (isset($memcached) && $memcached instanceof Memcached) {
        $k = "ui:browse_mode:{$uid}";
        $m = $memcached->get($k);
        if ($m === 'list' || $m === 'thumbs') return $m;
    }

    // 2) per-user cookie fallback
    $ck = "browsemode_u{$uid}";
    if (!empty($_COOKIE[$ck])) {
        $m = $_COOKIE[$ck];
        if ($m === 'list' || $m === 'thumbs') return $m;
    }

    // 3) дефолт
    return $def;
}



parked();

// Получаем список жанров
$cats = genrelist();

$searchstr = unesc($_GET["search"] ?? '');
$cleansearchstr = htmlspecialchars($searchstr);
if (empty($cleansearchstr)) unset($cleansearchstr);

$tagstr = unesc($_GET["tag"] ?? '');
$cleantagstr = htmlspecialchars($tagstr);
if (empty($cleantagstr)) unset($cleantagstr);

$letter = trim($_GET["letter"] ?? '');
if (strlen($letter) > 3) die();
if (empty($letter)) unset($letter);

// Сортировка
$orderby = "ORDER BY torrents.sticky ASC, torrents.id DESC";
$pagerlink = "";

if (!empty($_GET['sort']) && !empty($_GET['type'])) {
    $column = '';
    $ascdesc = '';
    switch ($_GET['sort']) {
        case '1': $column = "name"; break;
        case '2': $column = "numfiles"; break;
        case '3': $column = "comments"; break;
        case '4': $column = "added"; break;
        case '5': $column = "size"; break;
        case '6': $column = "times_completed"; break;
        case '7': $column = "seeders"; break;
        case '8': $column = "leechers"; break;
        case '9': $column = "owner"; break;
        case '10': if (get_user_class() >= UC_MODERATOR) $column = "moderatedby"; break;
        default: $column = "id"; break;
    }

    switch ($_GET['type']) {
        case 'asc': $ascdesc = "ASC"; $linkascdesc = "asc"; break;
        case 'desc': $ascdesc = "DESC"; $linkascdesc = "desc"; break;
        default: $ascdesc = "DESC"; $linkascdesc = "desc"; break;
    }

    $orderby = "ORDER BY torrents." . $column . " " . $ascdesc;
    $pagerlink = "sort=" . intval($_GET['sort']) . "&type=" . $linkascdesc . "&";
}

$addparam = "";
$wherea = [];
$wherecatina = [];

if (isset($_GET["incldead"])) {
    if ($_GET["incldead"] == 1) {
        $addparam .= "incldead=1&amp;";
        if (!isset($CURUSER) || get_user_class() < UC_ADMINISTRATOR)
            $wherea[] = "banned != 'yes'";
    } elseif ($_GET["incldead"] == 2) {
        $addparam .= "incldead=2&amp;";
        $wherea[] = "visible = 'no'";
    }
}

$category = (int)($_GET["cat"] ?? 0);
$all = $_GET["all"] ?? false;

if (!$all) {
    if (!$_GET && !empty($CURUSER["notifs"])) {
        $all = true;
        foreach ($cats as $cat) {
            $catid = $cat['id'];
            $all &= $catid;
            if (strpos($CURUSER["notifs"], "[cat$catid]") !== false) {
                $wherecatina[] = $catid;
                $addparam .= "c$catid=1&amp;";
            }
        }
    } elseif ($category) {
        if (!is_valid_id($category)) {
            stderr($tracker_lang['error'], "Invalid category ID.");
        }
        $wherecatina[] = $category;
        $addparam .= "cat=$category&amp;";
    } else {
        $all = true;
        foreach ($cats as $cat) {
            $catid = $cat['id'];
            $all &= ($_GET["c$catid"] ?? false);
            if ($_GET["c$catid"] ?? false) {
                $wherecatina[] = $catid;
                $addparam .= "c$catid=1&amp;";
            }
        }
    }
}

if ($all) {
    $wherecatina = [];
    $addparam = "";
}

if (count($wherecatina) > 1)
    $wherecatin = implode(",", $wherecatina);
elseif (count($wherecatina) == 1)
    $wherea[] = "category = {$wherecatina[0]}";

$wherebase = $wherea;

if (isset($cleansearchstr)) {
    $wherea[] = "torrents.name LIKE '%" . sqlwildcardesc($searchstr) . "%'";
    $addparam .= "search=" . urlencode($searchstr) . "&amp;";
}

if (isset($cleantagstr)) {
    $wherea[] = "torrents.tags LIKE '%" . sqlwildcardesc($tagstr) . "%'";
    $addparam .= "tag=" . urlencode($tagstr) . "&";
}

if (isset($letter)) {
    $wherea[] = "torrents.name LIKE BINARY '" . mysqli_real_escape_string($mysqli, $letter) . "%'";
    $addparam .= "letter=" . urlencode($letter) . "&amp;";
}

$where = implode(" AND ", $wherea);
if (!empty($wherecatin)) {
    $where .= ($where ? " AND " : "") . "category IN (" . $wherecatin . ")";
}
if (!empty($where)) {
    $where = "WHERE $where";
}

$res = sql_query("SELECT COUNT(*) FROM torrents $where");
$row = mysqli_fetch_array($res);
$count = $row[0];
$num_torrents = $count;

if (!$count && isset($cleansearchstr)) {
    $wherea = $wherebase;
    $searcha = explode(" ", $cleansearchstr);
    $sc = 0;
    foreach ($searcha as $searchss) {
        if (strlen($searchss) <= 1) continue;
        $sc++;
        if ($sc > 5) break;
        $wherea[] = "torrents.name LIKE '%" . sqlwildcardesc($searchss) . "%'";
    }
    if ($sc) {
        $where = implode(" AND ", $wherea);
        if (!empty($where)) $where = "WHERE $where";
        $res = sql_query("SELECT COUNT(*) FROM torrents $where");
        $row = mysqli_fetch_array($res);
        $count = $row[0];
    }
}

$torrentsperpage = $CURUSER["torrentsperpage"] ?? 20;

if ($count) {
    if ($addparam != "") {
        if ($pagerlink != "") {
            if (substr($addparam, -1) != ";") {
                $addparam .= "&" . $pagerlink;
            } else {
                $addparam .= $pagerlink;
            }
        }
    } else {
        $addparam = $pagerlink;
    }

    list($pagertop, $pagerbottom, $limit) = pager2($torrentsperpage, $count, "browse.php?" . $addparam);

$query = "SELECT 
        torrents.id, torrents.modded, torrents.modby, torrents.modname,
        torrents.category, torrents.tags, torrents.leechers, torrents.seeders,
        torrents.free, torrents.name, torrents.times_completed, torrents.size,
        torrents.added, torrents.comments, torrents.numfiles, torrents.filename,
        torrents.sticky, torrents.owner,
        torrents.image1,           
        IF(torrents.numratings < $minvotes, NULL, ROUND(torrents.ratingsum / torrents.numratings, 1)) AS rating,
        categories.name AS cat_name, categories.image AS cat_pic,
        users.username, users.class
        FROM torrents
        LEFT JOIN categories ON category = categories.id
        LEFT JOIN users ON torrents.owner = users.id
        $where $orderby $limit";


    $res = sql_query($query);
} else {
    unset($res);
}

if (isset($cleansearchstr))
    stdhead($tracker_lang['search_results_for'] . " \"$searchstr\"");
elseif (isset($cleantagstr))
    stdhead("Результаты поиска по тэгу \"$tagstr\"");
else
    stdhead($tracker_lang['browse']);


?>

<STYLE TYPE="text/css" MEDIA=screen>

  a.catlink:link, a.catlink:visited{
                text-decoration: none;
        }

        a.catlink:hover {
                color: #A83838;
        }

</STYLE>

<style>
/* ===================== */
/*   design tokens       */
/* ===================== */
:root{
  --bg:#f5f7fb;
  --text:#0f172a;
  --muted:#5b6476;
  --line:rgba(0,0,0,.08);
  --glass-1:rgba(255,255,255,.55);
  --glass-2:rgba(255,255,255,.18);
  --radius:12px;
  --pad:10px;
  --shadow:0 2px 8px rgba(0,0,0,.10);
  --shadow-hover:0 6px 16px rgba(0,0,0,.14);
  --glass-border:1px solid rgba(255,255,255,.45);
  --glass-grad:linear-gradient(180deg,var(--glass-1),var(--glass-2));
}

/* graceful degradation */
.no-glass .glass, .no-glass .glass-btn{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}

/* base */
body{color:var(--text);background:var(--bg)}
small,.small{color:var(--muted)}
.h1,.h2{font-weight:800;letter-spacing:.2px}
.pd10{padding:10px}.pd20{padding:16px 18px}

/* ===================== */
/*   liquid-glass panel  */
/* ===================== */
.panel.widget{
  border:1px solid var(--line);
  border-radius:calc(var(--radius) + 2px);
  background:var(--glass-grad);
  box-shadow:var(--shadow);
  backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
}

/* ===================== */
/*   buttons / inputs    */
/* ===================== */
.btn,.glass-btn,input[type=submit].glass-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 12px;border-radius:999px;border:var(--glass-border);
  background:linear-gradient(180deg,rgba(255,255,255,.55),rgba(255,255,255,.16));
  color:var(--text);font-weight:700;font-size:12px;text-decoration:none;
  backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
  box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 1px 3px rgba(0,0,0,.08);
  transition:transform .12s ease,box-shadow .12s ease,background .12s ease;
}
.btn:hover,.glass-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-hover)}
.btn.is-active,.glass-btn.active{outline:1px solid rgba(255,255,255,.7)}

.input,.search,.browse-select{
  padding:8px 10px;font-size:13px;border:1px solid rgba(0,0,0,.14);
  border-radius:10px;background:#fff;color:var(--text);
  box-shadow:0 0 0 2px rgba(255,255,255,.6) inset;
}
.browse-fieldset{
  border:1px solid var(--line);border-radius:var(--radius);padding:var(--pad);
  background:var(--glass-grad);
  backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
}

/* ===================== */
/*   view toggle + tiles */
/* ===================== */
.view-toggle{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin:6px 0}
.view-toggle .glass-btn{padding:6px 10px;border-radius:12px}

.thumb-card{
  display:flex;flex-direction:column;align-items:center;gap:6px;width:100%;
  background:linear-gradient(180deg,rgba(255,255,255,.30),rgba(255,255,255,.10));
  border:1px solid rgba(255,255,255,.5);border-radius:14px;padding:10px 8px;
  box-shadow:var(--shadow);transition:transform .12s ease,box-shadow .12s ease;
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
}
.thumb-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.18)}
.thumb-img{width:100%;height:auto;border-radius:10px;border:1px solid rgba(0,0,0,.12);display:block}
.thumb-meta{margin-top:4px;font-size:12px;display:flex;gap:10px;align-items:center;justify-content:center}
.ico{display:inline-flex;width:14px;height:14px;vertical-align:-2px}
.meta-pair{display:inline-flex;align-items:center;gap:4px}

/* alpha index */
.alpha{display:flex;flex-wrap:wrap;gap:6px 10px;justify-content:center;line-height:1.15;margin-top:6px}
.alpha a{text-decoration:none}
.alpha b{font-weight:800}

/* media + fallback */
@media (max-width:720px){.view-toggle{justify-content:center}.pagertop,.pagerbottom{gap:4px}}
@supports not (backdrop-filter:blur(2px)){
  .panel.widget,.browse-fieldset,.thumb-card,.glass-btn{background:#fff}
}
</style>



<script language="javascript" type="text/javascript" src="js/ajax.js"></script>
<div id="loading-layer" style="display:none;font-family: Verdana;font-size: 11px;width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000">
     <div style="font-weight:bold" id="loading-layer-text">Загрузка. Пожалуйста, подождите...</div><br />
     <img src="pic/loading.gif" border="0" />
</div>



<?php

$letter = $_GET['letter'] ?? '';


begin_frame("Список раздач");
?>

<style>
  /* компактная сетка и “стеклянные” кнопки */
  .browse-wrap{--pad:10px;--rad:12px;--gap:10px}
  .browse-fieldset{border:1px solid #999;border-radius:var(--rad);padding:var(--pad)}
  .search-row{display:flex;flex-wrap:wrap;gap:var(--gap);align-items:center;justify-content:center}
  .search-row .search{min-width:260px;max-width:520px;padding:8px 10px;border:1px solid #bbb;border-radius:10px}
  .browse-select{padding:7px 10px;border:1px solid #bbb;border-radius:10px}
  .glass-btn{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.4);
    background:linear-gradient(180deg,rgba(255,255,255,.45),rgba(255,255,255,.15));
    backdrop-filter:blur(6px); text-decoration:none}
  .glass-btn.active{box-shadow:0 0 0 2px rgba(0,0,0,.05) inset;font-weight:700}
  .view-toggle{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin:.5rem 0}
  .alpha{
    margin-top:6px;
    display:flex;
    flex-wrap:wrap;
    gap:6px 10px;              /* меньше «воздуха» */
    justify-content:center;
    line-height:1.15;
  }
  .alpha .grp{
    display:inline-flex;
    gap:6px;                   /* расстояние между буквами/цифрами внутри группы */
    align-items:center;
  }
  .alpha .grp-divider{
    opacity:.5;
    margin:0 6px;              /* разделитель | между группами */
  }
  .alpha a{ text-decoration:none; padding:0 } /* убираем "кнопочность" */
  .alpha b{ font-weight:700 }                 /* активный символ просто жирный */
  .index{padding:6px 8px}
  /* плитки: слегка подшлифовал вид, без тяжёлых теней */
  .thumb-card{display:flex;flex-direction:column;align-items:center;gap:6px}
  .thumb-img{border-radius:8px;object-fit:cover}
  .thumb-meta{display:flex;gap:10px;align-items:center;justify-content:center;font-size:12px;margin-top:2px}
  .thumb-meta .meta-pair{display:inline-flex;gap:3px;align-items:center}
  .ico{width:14px;height:14px;vertical-align:-2px}
  @media (max-width:700px){
    .search-row{justify-content:stretch}
    .view-toggle{justify-content:center}
  }
  
</style>

<script>
  // лёгкий debounce для suggest(); запрет Enter
  (function(){
    var t=null;
    window.noenter=function(k){ if((k||0)===13) return false; };
    window.suggestDebounced=function(k,v){
      if((k||0)===13) return; // Enter — сабмитит form
      clearTimeout(t); t=setTimeout(function(){ if(window.suggest) suggest(k,v); }, 120);
    };
  })();
</script>

<form method="get" action="browse.php">
  <table class="embedded browse-wrap" align="center" cellspacing="0" cellpadding="5" width="100%">
    <tr>
      <td colspan="12" style="border:0;">
        <fieldset class="browse-fieldset">
          <legend><b>Поиск</b></legend>

          <div class="search-row">
            <?php
              // мини-хелпер
              $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

              // текущее значение поиска
              $search_val = $h($searchstr ?? '');

              // выбранная категория
              $cat_sel = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
            ?>
            <input class="search" id="searchinput" name="search" type="text" size="34" autocomplete="off"
                   ondblclick="suggestDebounced(event.keyCode, this.value);"
                   onkeyup="suggestDebounced(event.keyCode, this.value);"
                   onkeypress="return noenter(event.keyCode);"
                   value="<?= $search_val ?>"/>

            <select class="browse-select" name="cat" aria-label="Категория">
              <option value="0">(<?= $tracker_lang['all_types']; ?>)</option>
              <?php foreach ($cats as $cat): ?>
                <?php $sel = ($cat_sel === (int)$cat['id']) ? ' selected="selected"' : ''; ?>
                <option value="<?= (int)$cat['id'] ?>"<?= $sel ?>><?= $h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <input class="glass-btn" type="submit" value="<?= $tracker_lang['search']; ?>!"/>
          </div>

         <?php
  // ——— Генерация букв/цифр (компактные группы) ———
  
?>

        </fieldset>
      </td>
    </tr>

    <?php
      // Результаты поиска — заголовок
      if (isset($cleansearchstr)) {
          echo "<tr><td class=\"index\" colspan=\"12\">{$tracker_lang['search_results_for']} \"".$h($searchstr)."\"</td></tr>\n";
      }

      if ($num_torrents) {

        // безопасный ret: кодируем целиком часть после ? (включая qs)
       $qs_raw = $_SERVER['QUERY_STRING'] ?? '';
$ret = 'browse.php' . ($qs_raw !== '' ? ('?' . $qs_raw) : '');
$ret_enc = rawurlencode($ret);
$browsemode = get_browse_mode();



        echo "</td></tr>";

        echo "<tr><td style=\"border:0\" colspan=\"12\">{$pagertop}</td></tr>";
echo "<div class='view-toggle'>";
echo   "<a class='glass-btn ".($browsemode==='thumbs'?'active':'')."' href='cookieset.php?browsemode=thumbs&ret={$ret_enc}'>Плитка</a>";
echo   "<a class='glass-btn ".($browsemode==='list'  ?'active':'')."' href='cookieset.php?browsemode=list&ret={$ret_enc}'>Список</a>";
echo "</div>";
        // ===== переключатель вида =====
$browsemode = get_browse_mode();

        if ($browsemode === 'thumbs') {
          // ---- настройки плитки (оставил как у тебя) ----
          $thumb_w = 134; $thumb_h = 188; $per_row = 5;

          $ratingImg = static function (?float $num): string {
            if ($num === null) return '';
            $r = round($num / 2);
            if ($r < 1 || $r > 5) return '';
            return "<img src='pic/rating/{$r}.gif' alt='{$num}' />";
          };

          echo "<tr><td style='border:0' colspan='12'>";
          echo "<table class='embedded' style='width:100%;table-layout:fixed' cellspacing='0' cellpadding='6'><tr>";

          $i = 0;

          // SVG-иконки (как у тебя)
          $icoUpRed = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#e53935" d="M12 3l6 6h-4v9h-4V9H6l6-6z"/></svg>';
          $icoDownGreen = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#2e7d32" d="M12 21l-6-6h4V6h4v9h4l-6 6z"/></svg>';
          $icoDone = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0ea5e9" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';

          while ($row = mysqli_fetch_assoc($res)) {
            $poster = '';
            if (!empty($row['image1']))      $poster = $h($row['image1']);
            elseif (!empty($row['poster']))  $poster = $h($row['poster']);
            else                              $poster = "pic/noposter.png";

            $ratingHtml = $ratingImg(isset($row['rating']) ? (float)$row['rating'] : null);
            if ($ratingHtml === '') $ratingHtml = $tracker_lang['no_votes'] ?? 'Нет голосов';

            $freeHtml = (!empty($row['free']) && $row['free'] === 'yes') ? "<img src='pic/free.gif' alt='FREE' />" : '';

            $name = $h($row['name'] ?? '');
            $cat  = $h($row['cat_name'] ?? '');
            $usr  = isset($row['username']) && $row['username'] !== '' ? "<b>".$h($row['username'])."</b>" : "<i>(unknown)</i>";

            $added   = $h($row['added'] ?? '');
            $cmts    = (int)($row['comments'] ?? 0);
            $files   = (int)($row['numfiles'] ?? 0);
            $size    = mksize((int)($row['size'] ?? 0));
            $seeders = (int)($row['seeders'] ?? 0);
            $leech   = (int)($row['leechers'] ?? 0);
            $done    = (int)($row['times_completed'] ?? 0);

            $tooltip = "<div style=\\'padding:5px;\\'><table id=\\'thumbs\\'>"
              . "<tr><td colspan=\\'2\\'><pre>{$name}</pre></td></tr>"
              . "<tr><td>Категория</td><td>{$cat}</td></tr>"
              . "<tr><td>Оценка</td><td>{$ratingHtml}</td></tr>"
              . "<tr><td>Загрузил</td><td>{$usr}</td></tr>"
              . "<tr><td>Добавлен</td><td><pre style=\\'font-weight:normal;\\'>{$added}</pre></td></tr>"
              . "<tr><td>Комментариев</td><td>{$cmts}</td></tr>"
              . "<tr><td>Файлов</td><td>{$files}</td></tr>"
              . "<tr><td>Размер</td><td>{$size}</td></tr>"
              . "</table></div>";

            echo "<td style='width:{$thumb_w}px;border:0;vertical-align:top;text-align:center'>"
              . "  <div class='thumb-card'>"
              . "    <a href='details.php?id=".(int)$row['id']."&amp;hit=1' "
              . "       onmouseover=\"return overlib('{$tooltip}');\" onmouseout=\"return nd();\">"
              . "       <img class='thumb-img' src='{$poster}' width='{$thumb_w}' height='{$thumb_h}' alt='' />"
              . "    </a>"
              . "    <div class='thumb-meta'>"
              . "      <span class='meta-pair' title='Качают'>{$icoDownGreen}{$leech}</span>"
              . "      <span class='meta-pair' title='Раздают'>{$icoUpRed}{$seeders}</span>"
              . "      <span class='meta-pair' title='Скачан'>{$icoDone}{$done}</span>"
              . "      {$freeHtml}"
              . "    </div>"
              . "  </div>"
              . "</td>";

            $i++; if ($i % $per_row === 0) echo "</tr><tr>";
          }

          if ($i % $per_row !== 0) {
            $rest = $per_row - ($i % $per_row);
            echo str_repeat("<td style='border:0'>&nbsp;</td>", $rest);
          }

          echo "</tr></table>";
          echo "</td></tr>";

        } else {
          // — НЕ ТРОГАЕМ — классический список
          torrenttable($res, "index");
        }

        echo "<tr><td style=\"border:0;\" colspan=\"12\">{$pagerbottom}</td></tr>";

      } else {
        echo "<tr><td style=\"border:0;\" colspan=\"12\">{$tracker_lang['nothing_found']}</td></tr>\n";
      }
    ?>
  </table>
</form>


<script src="js/suggest.js" type="text/javascript"></script>
<div id="suggcontainer" style="text-align:left;width:520px;display:none;">
    <div id="suggestions" style="cursor:default;position:absolute;background-color:#FFFFFF;border:1px solid #777777;"></div>
</div>

<?php

end_frame();

//////////////////////////////////////////////////////////////////////////


// Кеш-ключ и TTL
$key = 'help_torrents:v2';
$ttl = 300;

// 1) Кеш
global $memcached;
$res = $memcached->get($key);

if ($res === false) {
    $sql = "
        SELECT id, name, seeders, leechers
        FROM torrents
        WHERE visible = 'yes'
          AND banned  = 'no'
          AND (
                (seeders = 0 AND leechers = 0)                       -- показываем «мертвые» (0/0)
             OR (seeders = 0 AND leechers > 0)                       -- есть качающие, но нет сидов
             OR (seeders > 0 AND (leechers / NULLIF(seeders,0)) >= 4) -- перекос спрос/предложение
          )
        ORDER BY
            -- сначала без сидов, затем по наибольшему перекосу, затем по числу качающих
            (seeders = 0) DESC,
            (leechers / NULLIF(seeders,1)) DESC,
            leechers DESC
        LIMIT 20
    ";
    $q = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $res = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $res[] = $row;
    }
    $memcached->set($key, $res, $ttl);
}

// 2) Вывод
begin_frame("Раздачи, нуждающиеся в сидерах");

// мини-стили прямо тут (можешь вынести в CSS)
?>
<style>
.help-list {width:100%; border-collapse:collapse; font-size:14px}
.help-list th, .help-list td {padding:10px; border-bottom:1px solid #e7e7e7; vertical-align:middle}
.help-list th {text-align:left; font-weight:600; background:#fafafa}
.help-name a {text-decoration:none}
.badge {display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; line-height:18px; border:1px solid #ddd}
.badge-zero {background:#fff4f4; border-color:#f0c4c4}
.badge-need {background:#fff9e6; border-color:#f2dea6}
.badge-ok   {background:#eef9ff; border-color:#cfe7f6}
.ratio-dot {display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; vertical-align:middle}
.dot-red   {background:#e33}
.dot-amber {background:#e6a700}
.dot-blue  {background:#3aa0e6}
.help-meta {color:#666; font-size:12px}
</style>
<?php

echo '<table class="help-list">';
echo '<tr><th>Раздача</th><th>Статус</th><th>Пиры</th></tr>';

if (empty($res)) {
    echo '<tr><td colspan="3">Сейчас нет раздач, которым особенно требуется помощь в сидировании.</td></tr>';
} else {
    foreach ($res as $arr) {
        $nameFull = (string)$arr['name'];
        $nameShort = (mb_strlen($nameFull, 'UTF-8') > 55)
            ? (mb_substr($nameFull, 0, 55, 'UTF-8') . '…')
            : $nameFull;

        $seed = (int)$arr['seeders'];
        $leech = (int)$arr['leechers'];

        // определим "серьёзность" для бейджа/точки
        if ($seed === 0 && $leech === 0) {
            $badge = '<span class="badge badge-zero"><span class="ratio-dot dot-red"></span>Нет сидов / нет пиров</span>';
        } elseif ($seed === 0 && $leech > 0) {
            $badge = '<span class="badge badge-zero"><span class="ratio-dot dot-red"></span>Нет сидов</span>';
        } elseif ($seed > 0 && $leech >= 4 * $seed) {
            $badge = '<span class="badge badge-need"><span class="ratio-dot dot-amber"></span>Нужны сиды</span>';
        } else {
            $badge = '<span class="badge badge-ok"><span class="ratio-dot dot-blue"></span>Стабильно</span>';
        }

        $nameEsc = htmlspecialchars($nameFull, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link = 'details.php?id='.(int)$arr['id'].'&hit=1';

        echo '<tr>';
        echo '<td class="help-name"><a href="'.$link.'" title="'.$nameEsc.'">'.htmlspecialchars($nameShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>'
           . '<div class="help-meta" title="'.$nameEsc.'">'.htmlspecialchars($nameEsc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></td>';
        echo '<td>'.$badge.'</td>';
        echo '<td><b>Раздают:</b> '.number_format($seed, 0, ',', ' ')
           . ' &nbsp; <b>Качают:</b> '.number_format($leech, 0, ',', ' ').'</td>';
        echo '</tr>';
    }
}
echo '</table>';

end_frame();




stdfoot();  

?>