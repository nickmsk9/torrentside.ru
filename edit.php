<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn();
loggedinorreturn();

/** Helpers */
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** id (strict) */
if (!mkglobal('id')) {
    stdhead('Ошибка');
    stdmsg('Ошибка', 'Не указан идентификатор торрента.');
    stdfoot();
    exit;
}
$id = max(0, (int)$id);
if ($id === 0) {
    stdhead('Ошибка');
    stdmsg('Ошибка', 'Некорректный идентификатор торрента.');
    stdfoot();
    exit;
}

/** fetch torrent row (safe) */
$res = sql_query('SELECT * FROM torrents WHERE id = ' . sqlesc($id));
if (!$res || mysqli_num_rows($res) === 0) {
    stdhead('Ошибка');
    stdmsg('Ошибка', 'Торрент не найден.');
    stdfoot();
    exit;
}
$row = mysqli_fetch_assoc($res) ?: [];
mysqli_free_result($res);

/** access check */
global $CURUSER;
if (!isset($CURUSER['id'])) {
    stdhead('Ошибка');
    stdmsg('Ошибка', 'Требуется авторизация.');
    stdfoot();
    exit;
}
$can_edit = ((int)$CURUSER['id'] === (int)$row['owner']) || (get_user_class() >= UC_MODERATOR);

stdhead('Редактирование торрента "' . h($row['name']) . '"');
begin_frame('Редактировать торрент');

if (!$can_edit) {
    stdmsg('Ошибка', 'Вы не можете редактировать этот торрент.');
    end_frame();
    stdfoot();
    exit;
}

/** one-time CSRF token (ensure session started in bittorrent.php) */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/** keep original behavior: image fields and names как у вас,
 *  BBCode редактор получает НЕэкранированный текст, т.к. он сам экранирует при выводе.
 */

/** returnto: allow only relative (internal) links */
$returnto = '';
if (isset($_GET['returnto'])) {
    $rt = (string)$_GET['returnto'];
    if (str_starts_with($rt, '/')
        || (!preg_match('~^[a-z]+://~i', $rt) && !str_starts_with($rt, '//'))) {
        $returnto = $rt;
    }
}

echo "<form name=\"edit\" method=\"post\" action=\"takeedit.php\" enctype=\"multipart/form-data\">\n";
echo '<input type="hidden" name="id" value="' . $id . '">' . "\n";
echo '<input type="hidden" name="csrf_token" value="' . h($csrf) . '">' . "\n";
if ($returnto !== '') {
    echo '<input type="hidden" name="returnto" value="' . h($returnto) . '">' . "\n";
}

echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
echo "<tr><td class=\"colhead\" colspan=\"2\">Редактировать торрент</td></tr>\n";

/** Название */
tr('Название торрента', '<input type="text" name="name" value="' . h($row['name']) . '" size="80" maxlength="255">', 1);

/** Главное изображение (сохраняю ваш нейминг: image2 берёт значение из image1) */
tr('Главное изображение', '<input type="text" name="image2" value="' . h($row['image1']) . '" size="80"><br><i>Укажите URL</i>', 1);

/** Описание — BBCode editor */
ob_start();
echo textbbcode('edit', 'descr', (string)$row['descr']); // стало

$editor = ob_get_clean();
tr('Описание', "<div style='max-width:660px'>{$editor}<br><small>HTML <b>не</b> разрешён. <a href='tags.php'>Теги</a></small></div>", 1);

/** Скриншоты */
$shots = []
    + ['image3' => $row['image2'] ?? '']
    + ['image4' => $row['image3'] ?? '']
    + ['image5' => $row['image4'] ?? '']
    + ['image6' => $row['image5'] ?? ''];
$shots_html = '';
foreach ($shots as $name => $val) {
    $shots_html .= '<input type="text" name="' . $name . '" value="' . h((string)$val) . "\" size=\"80\"><br>\n";
}
$shots_html = rtrim($shots_html, "<br>\n");
tr('Скриншоты', $shots_html, 1);

/** Тип категории */
$cats = genrelist(); // кэшируйте в memcached при желании
$s = '<select name="type">';
$currentCat = (int)($row['category'] ?? 0);
foreach ($cats as $subrow) {
    $cid = (int)$subrow['id'];
    $selected = ($cid === $currentCat) ? ' selected' : '';
    $s .= '<option value="' . $cid . '"' . $selected . '>' . h($subrow['name']) . "</option>\n";
}
$s .= '</select>';
tr('Тип', $s, 1);

/** Теги */
echo <<<HTML
<style type="text/css">
    code {font:99.9%/1.2 consolas,'courier new',monospace;}
    #from a {margin:2px 2px;font-weight:normal;}
    #tags {width:36em;}
    a.selected {background:#c00; color:#fff;}
    .addition {margin-top:2em; text-align:right;}
</style>
<script src="js/jquery.js"></script>
<script src="js/tagto.js"></script>
<script>$(function(){ $("#from").tagTo("#tags"); });</script>
HTML;

$tagsInput  = '<input type="hidden" name="oldtags" value="' . h($row['tags'] ?? '') . '">';
$tagsInput .= '<input type="text" id="tags" name="tags" value="' . h($row['tags'] ?? '') . '">';
$tagsInput .= '<div id="from">';
$tags = taggenrelist($currentCat); // это тоже можно кэшировать
foreach ($tags as $tag) {
    $tagsInput .= "<a href='#'>" . h($tag['name']) . "</a> ";
}
$tagsInput .= "</div>\n";
tr('Теги', $tagsInput, 1);

/** Видимость */
$visibleChecked = ((string)($row['visible'] ?? '') === 'yes') ? ' checked' : '';
tr('Видимость', '<input type="checkbox" name="visible"' . $visibleChecked . ' value="1"> Торрент виден на главной', 1);

/** Замок видимости (не скрывать клинапом) */
$lockChecked = (!empty($row['visible_lock']) && (int)$row['visible_lock'] === 1) ? ' checked' : '';
tr(
    'Зафиксировать видимость',
    '<label><input type="checkbox" name="visible_lock" value="1"' . $lockChecked . '> Не скрывать при неактивности (cleanup)</label>',
    1
);


/** Бан — только админу */
if (get_user_class() >= UC_ADMINISTRATOR) {
    $bannedChecked = ((string)($row['banned'] ?? '') === 'yes') ? ' checked' : '';
    tr('Забанен', '<input type="checkbox" name="banned"' . $bannedChecked . ' value="1">', 1);
}

/** Freeleech — аплоадерам+ */
if (get_user_class() >= UC_UPLOADER) {
    $curFree = (int)($row['free'] ?? 0);
    $prc = '<select name="free">';
    for ($i = 0; $i <= 10; ++$i) {
        $val = $i * 10;
        $sel = ($curFree === $val) ? ' selected' : '';
        $prc .= '<option value="' . $val . '"' . $sel . '>' . $val . '</option>';
    }
    $prc .= '</select> %';
    tr('Загрузку не учитывать на', $prc, 1);
}

/** Sticky — только админу */
if (get_user_class() >= UC_ADMINISTRATOR) {
    $stickyChecked = ((string)($row['sticky'] ?? '') === 'yes') ? ' checked' : '';
    tr('Прикрепить', '<input type="checkbox" name="sticky"' . $stickyChecked . ' value="yes"> Торрент будет наверху', 1);
}

/** buttons */
echo "<tr><td colspan=\"2\" align=\"center\">
        <input type=\"submit\" value=\"Сохранить\" style=\"height:25px;width:100px\">
        <input type=\"reset\" value=\"Сбросить\" style=\"height:25px;width:100px\">
    </td></tr>\n";

echo "</table>\n";
echo "</form>\n";

/** Delete form */
echo "<p><form method=\"post\" action=\"delete.php\">\n";
echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
echo "<tr><td class=\"colhead\" colspan=\"2\">Удаление торрента — причина:</td></tr>\n";

$reasons = [
    1 => 'Мертвяк',
    2 => 'Дупликат',
    3 => 'Nuked',
    4 => 'Нарушение правил',
    5 => 'Другое',
];
foreach ($reasons as $key => $label) {
    $checked = ($key === 5) ? ' checked' : '';
    $required = ($key >= 4) ? ' (Обязательно)' : '';
    echo "<tr><td><input type=\"radio\" name=\"reasontype\" value=\"{$key}\"{$checked}> {$label}</td>
          <td><input type=\"text\" name=\"reason[]\" size=\"40\">{$required}</td></tr>\n";
}
echo '<input type="hidden" name="id" value="' . $id . '">' . "\n";
if ($returnto !== '') {
    echo '<input type="hidden" name="returnto" value="' . h($returnto) . '">' . "\n";
}
echo '<input type="hidden" name="csrf_token" value="' . h($csrf) . '">' . "\n";
echo "<tr><td colspan=\"2\" align=\"center\">
        <input type=\"submit\" value=\"Удалить торрент\" style=\"height:25px\">
    </td></tr>\n";
echo "</table>\n";
echo "</form></p>\n";

end_frame();
stdfoot();
