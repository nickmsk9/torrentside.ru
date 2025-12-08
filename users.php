<?php
declare(strict_types=1);

require 'include/bittorrent.php';
dbconn();
loggedinorreturn();
// --- AJAX: поиск пользователей по имени ---
if (($_GET['action'] ?? '') === 'ajax_user_search') {
    header('Content-Type: application/json; charset=UTF-8');

    // Если нужен доступ только авторизованным
    if (!isset($CURUSER['id'])) { echo '[]'; exit; }

    global $mysqli;

    $q = trim($_GET['q'] ?? '');
    if ($q === '' || mb_strlen($q, 'UTF-8') < 2) { echo '[]'; exit; }

    // Подготовим шаблоны LIKE (регистронезависимость обеспечит колlation колонки username)
    $safe      = $mysqli->real_escape_string($q);
    $likeStart = $safe . '%';
    $likeAny   = '%' . $safe . '%';
    $isNumeric = ctype_digit($q) ? (int)$q : 0;

    // Поиск: приоритет — имя, начинающееся с запроса; далее — любое вхождение.
    // Если ввели цифры — даём шанс прямому совпадению по ID.
    $sql = "
      SELECT id, username
      FROM users
      WHERE " . ($isNumeric ? "id = " . sqlesc($isNumeric) . " OR " : "") . "
            username LIKE " . sqlesc($likeStart) . "
         OR username LIKE " . sqlesc($likeAny) . "
      ORDER BY
        CASE WHEN username LIKE " . sqlesc($likeStart) . " THEN 0 ELSE 1 END,
        CHAR_LENGTH(username) ASC
      LIMIT 10
    ";

    $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
    $out = [];
    if ($res) {
        while ($u = mysqli_fetch_assoc($res)) {
            $out[] = ['id' => (int)$u['id'], 'username' => (string)$u['username']];
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
// --- /AJAX ---

global $mysqli, $pic_base_url;

/** ------------------------- INPUT ------------------------- */
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$class  = isset($_GET['class'])  ? (string)$_GET['class'] : '';
$letter = isset($_GET['letter']) ? trim((string)$_GET['letter']) : '';
$page   = isset($_GET['page'])   ? max(1, (int)$_GET['page']) : 1;

$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] === '1')
       || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

/** ------------------------- VALIDATION ------------------------- */
if ($class === '-' || !is_valid_user_class($class)) {
    $class = '';
}
// НЕТ автоприсвоения letter='a' — по умолчанию показываем новые регистрации.
// Если передали букву, нормализуем к нижнему регистру и одной латинской букве.
if ($letter !== '') {
    $letter = strtolower($letter);
    if (strlen($letter) !== 1 || strpos('abcdefghijklmnopqrstuvwxyz', $letter) === false) {
        $letter = '';
    }
}

/** ------------------------- UTILS ------------------------- */
function column_exists(mysqli $db, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $st->store_result();
    $exists = $st->num_rows > 0;
    $st->close();
    return $exists;
}

// Авто-детект колонки донора
$donorMode = null; // 'donated' | 'donor' | 'donoruntil' | null
if (column_exists($mysqli, 'users', 'donated'))      $donorMode = 'donated';
elseif (column_exists($mysqli, 'users', 'donor'))     $donorMode = 'donor';
elseif (column_exists($mysqli, 'users', 'donoruntil'))$donorMode = 'donoruntil';

/** ------------------------- QUERY BUILDER ------------------------- */
$perpage = 100;
$offset  = ($page - 1) * $perpage;

$where = ["u.status='confirmed'"];
$params = [];
$types  = '';

if ($search !== '') { // регистронезависимый LIKE
    $like = '%' . sqlwildcardesc($search) . '%';
    $where[] = 'LOWER(u.username) LIKE LOWER(?)';
    $params[] = $like;
    $types   .= 's';
}

if ($letter !== '') { // регистронезависимое начало никнейма
    $where[] = 'LOWER(u.username) LIKE LOWER(?)';
    $params[] = $letter . '%';
    $types   .= 's';
}

if ($class !== '' && is_valid_user_class($class)) {
    $where[] = 'u.class = ?';
    $params[] = (int)$class;
    $types   .= 'i';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Сортировка: по умолчанию — по дате регистрации (новые сверху).
// Если явно выбрана буква или поиск — оставим сортировку по имени второстепенной.
$defaultListing = ($search === '' && $class === '' && $letter === '');
$orderSql = $defaultListing ? 'ORDER BY u.added DESC, u.username ASC'
                            : 'ORDER BY u.added DESC, u.username ASC'; // можно всегда так — удобно и ожидаемо

/** ------------------------- COUNT ------------------------- */
$total = 0;
$countSql = "SELECT COUNT(*) AS cnt FROM users u $whereSql";
$stmt = $mysqli->prepare($countSql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$pages = max(1, (int)ceil(($total ?: 0) / $perpage));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $perpage;
}

/** ------------------------- DATA SELECT ------------------------- */
$donorSelect = '';
if ($donorMode === 'donated')      $donorSelect = ', u.donated AS donor_col';
elseif ($donorMode === 'donor')    $donorSelect = ', u.donor AS donor_col';
elseif ($donorMode === 'donoruntil') $donorSelect = ', u.donoruntil AS donor_col';

$dataSql = "
SELECT u.id, u.username, u.class, u.added, u.last_access,
       u.uploaded, u.downloaded, u.gender, u.country
       $donorSelect,
       c.name AS country_name, c.flagpic
FROM users AS u
LEFT JOIN countries AS c ON c.id = u.country
$whereSql
$orderSql
LIMIT ?, ?
";
$stmt = $mysqli->prepare($dataSql);

$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $offset;
$params2[] = $perpage;

$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();

/** ------------------------- HELPERS ------------------------- */
function format_ratio(int $up, int $down): array {
    if ($down > 0) {
        $r = $up / $down;
        $disp = ($r > 100) ? '100+' : number_format($r, 2, '.', '');
    } else {
        $disp = ($up > 0) ? 'Inf.' : 'Нет';
    }
    $color = get_ratio_color($disp);
    return [$disp, $color];
}

function render_country(?string $flagpic, ?string $name, ?int $country): string {
    if (!empty($country) && !empty($flagpic)) {
        $nm = htmlspecialchars($name ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pic = htmlspecialchars($flagpic, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<td style="padding:0" align="center"><img src="pic/flag/' . $pic . '" alt="' . $nm . '" title="' . $nm . '"></td>';
    }
    return '<td align="center">---</td>';
}

if (empty($pic_base_url)) $pic_base_url = 'pic/';
function render_gender(?string $gender, string $pic_base_url): string {
    if ($gender === '1') return '<img src="'.$pic_base_url.'male.gif" alt="Парень" style="margin-left:4pt">';
    if ($gender === '2') return '<img src="'.$pic_base_url.'female.gif" alt="Девушка" style="margin-left:4pt">';
    return '';
}
function is_donor(array $row, ?string $mode): bool {
    if ($mode === null) return false;
    $v = $row['donor_col'] ?? null;
    if ($mode === 'donated')     return (int)$v > 0;
    if ($mode === 'donor')       return (int)$v === 1;
    if ($mode === 'donoruntil')  return ($v && $v !== '0000-00-00 00:00:00' && strtotime((string)$v) > time());
    return false;
}

/** ------------------------- ROWS RENDER ------------------------- */
$rowsHtml = [];
while ($row = $res->fetch_assoc()) {
    [$ratioDisp, $ratioColor] = format_ratio((int)$row['uploaded'], (int)$row['downloaded']);
    $ratioHtml = '<font color="' . htmlspecialchars($ratioColor, ENT_QUOTES) . '">' . $ratioDisp . '</font>';

    $added = ($row['added'] === '0000-00-00 00:00:00' || $row['added'] === null) ? '-' : $row['added'];
    $last  = ($row['last_access'] === '0000-00-00 00:00:00' || $row['last_access'] === null) ? '-' : $row['last_access'];

    $unameColored = get_user_class_color((int)$row['class'], (string)$row['username']);
    $unameLink = '<a href="userdetails.php?id='.(int)$row['id'].'"><b>'.$unameColored.'</b></a>';

    $donor = is_donor($row, $donorMode) ? ' <img src="pic/star.gif" alt="Donor">' : '';

    $gender = render_gender($row['gender'], $pic_base_url);
    $className = get_user_class_name((int)$row['class']);

    $countryTd = render_country($row['flagpic'] ?? null, $row['country_name'] ?? null, (int)($row['country'] ?? 0));

    $rowsHtml[] = '<tr>'
        . '<td align="left">' . $unameLink . $donor . '</td>'
        . '<td>' . $added . '</td>'
        . '<td>' . $last  . '</td>'
        . '<td>' . $ratioHtml . '</td>'
        . '<td>' . $gender . '</td>'
        . '<td align="left">' . htmlspecialchars($className, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>'
        . $countryTd
        . '</tr>';
}
$stmt->close();

// Если нет результатов, подготовим "пустую" строку с нужным сообщением
if ($total == 0) {
    $msg = ($search !== '') ? 'Пользователей с таким никнеймом отсутствует'
                            : 'Пользователи не найдены';
    $rowsHtml[] = '<tr><td colspan="7" align="center"><i>'.$msg.'</i></td></tr>';
    $pages = 1; // чтобы пагинация не плодила пустые страницы
}

/** ------------------------- PAGINATION ------------------------- */
function build_query_string(string $search, string $class, string $letter): string {
    $q = [];
    if ($search !== '') $q[] = 'search=' . urlencode($search);
    if ($class !== '')  $q[] = 'class=' . (int)$class;
    if ($letter !== '') $q[] = 'letter=' . urlencode($letter);
    return implode('&', $q);
}
$q = build_query_string($search, $class, $letter);

$pagemenu = $browsemenu = '';
if ($pages > 1) {
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $page) $pagemenu .= "<b>$i</b> ";
        else $pagemenu .= '<a data-page="'.$i.'" href="users.php?'.$q.'&page='.$i.'"><b>'.$i.'</b></a> ';
    }
    if ($page > 1) $browsemenu .= '<a data-page="'.($page-1).'" href="users.php?'.$q.'&page='.($page-1).'"><b>&laquo; Пред</b></a>';
    else $browsemenu .= '<b>&laquo; Пред</b>';

    $browsemenu .= str_repeat('&nbsp;', 6);

    if ($page < $pages) $browsemenu .= '<a data-page="'.($page+1).'" href="users.php?'.$q.'&page='.($page+1).'"><b>След &raquo;</b></a>';
    else $browsemenu .= '<b>След &raquo;</b>';
}

/** ------------------------- AJAX RESPONSE ------------------------- */
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'total'     => (int)$total,
        'page'      => (int)$page,
        'pages'     => (int)$pages,
        'rows_html' => $rowsHtml,
        'pagemenu'  => $pagemenu,
        'browsemenu'=> $browsemenu,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** ------------------------- FULL PAGE ------------------------- */
stdhead('Пользователи');
begin_frame('Пользователи');
echo "<h1>Пользователи</h1>\n";

echo '<form id="users-search" method="get" action="users.php" autocomplete="off">';
echo 'Поиск: <input type="text" size="30" name="search" value="', htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '"> ';
echo '<select name="class">';
echo '<option value="-">(Все уровни)</option>';
for ($i = 0; ; ++$i) {
    $c = get_user_class_name($i);
    if (!$c) break;
    $sel = ($class !== '' && (int)$class === $i) ? ' selected' : '';
    echo '<option value="', $i, '"', $sel, '>', htmlspecialchars($c, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), "</option>\n";
}
echo '</select> ';
echo '<input type="submit" value="Вперед">';
echo '</form>';

echo '<p>';
for ($i = 97; $i <= 122; ++$i) {
    $l = chr($i);
    $L = strtoupper($l);
    if ($l === $letter) echo "<b>$L</b>\n";
    else echo '<a class="letter-link" href="users.php?letter='.$l.'"><b>'.$L.'</b></a> ';
}
echo '</p>';

echo "<p id=\"browse-top\">$browsemenu<br>$pagemenu</p>";

echo '<table id="users-table" border="1" cellspacing="0" cellpadding="5">';
echo '<tr><td class="colhead" align="left">Имя</td><td class="colhead">Зарегистрирован</td><td class="colhead">Последний вход</td><td class="colhead">Рейтинг</td><td class="colhead">Пол</td><td class="colhead" align="left">Уровень</td><td class="colhead">Страна</td></tr>';
echo implode("\n", $rowsHtml);
echo '</table>';

echo "<p id=\"browse-bottom\">$pagemenu<br>$browsemenu</p>";

?>
<script>
(function(){
  const form = document.getElementById('users-search');
  const table = document.getElementById('users-table');
  const browseTop = document.getElementById('browse-top');
  const browseBottom = document.getElementById('browse-bottom');

  let debounceTimer = null;

  function qs(obj) {
    const p = new URLSearchParams(obj);
    return p.toString();
  }

  function currentParams() {
    const fd = new FormData(form);
    const o = Object.fromEntries(fd.entries());
    const url = new URL(window.location.href);
    const letter = url.searchParams.get('letter') || '';
    if (!o.search && !o.class && letter) o.letter = letter;
    return o;
  }

  function renderRows(rows) {
    const head = table.querySelector('tr');
    table.innerHTML = '';
    table.appendChild(head);
    for (const html of rows) {
      const wrap = document.createElement('tbody');
      wrap.innerHTML = html;
      table.appendChild(wrap.firstElementChild);
    }
  }

  function updateMenus(pagemenu, browsemenu) {
    browseTop.innerHTML = browsemenu + '<br>' + pagemenu;
    browseBottom.innerHTML = pagemenu + '<br>' + browsemenu;
  }

  async function fetchPage(obj) {
    obj.ajax = '1';
    const url = 'users.php?' + qs(obj);
    const resp = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} });
    if (!resp.ok) return;
    const data = await resp.json();
    renderRows(data.rows_html);
    updateMenus(data.pagemenu, data.browsemenu);
  }

  function pushUrl(obj) {
    const url = 'users.php?' + qs(obj);
    history.pushState(null, '', url);
  }

  function triggerSearch(obj) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      obj.page = 1;
      pushUrl(obj);
      fetchPage(obj).catch(()=>{});
    }, 180);
  }

  form.addEventListener('input', () => {
    const obj = currentParams();
    triggerSearch(obj);
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const obj = currentParams();
    triggerSearch(obj);
  });

  function navHandler(e) {
    const a = e.target.closest('a');
    if (!a) return;
    const url = new URL(a.href);
    const params = Object.fromEntries(url.searchParams.entries());
    params.ajax = '1';
    e.preventDefault();
    fetchPage(params).then(()=>{ history.pushState(null,'',a.href); }).catch(()=>{});
  }
  browseTop.addEventListener('click', navHandler);
  browseBottom.addEventListener('click', navHandler);

  document.addEventListener('click', function(e){
    const a = e.target.closest('a.letter-link');
    if (!a) return;
    e.preventDefault();
    const url = new URL(a.href);
    const params = Object.fromEntries(url.searchParams.entries());
    params.ajax = '1';
    fetchPage(params).then(()=>{ history.pushState(null,'',a.href); }).catch(()=>{});
  });

  window.addEventListener('popstate', () => {
    const url = new URL(location.href);
    const params = Object.fromEntries(url.searchParams.entries());
    params.ajax = '1';
    fetchPage(params).catch(()=>{});
  });
})();
</script>
<?php
end_frame();
stdfoot();


