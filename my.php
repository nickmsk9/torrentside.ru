<?php
declare(strict_types=1);

require_once("include/bittorrent.php");

dbconn(false);
loggedinorreturn();

global $CURUSER, $DEFAULTBASEURL, $tracker_lang, $avatar_max_width, $avatar_max_height;
/** @var mysqli $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
    die('MySQL handle not available');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$currentAvatarUrl = tracker_avatar_url((string)($CURUSER['avatar'] ?? ''), '/pic/default_avatar.gif');
$publicBaseUrl = rtrim((string)tracker_public_base_url(), '/');
$profileUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/userdetails.php?id=' . (int)$CURUSER['id'];
$userbarImageUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/torrentbar/bar.php?id=' . (int)$CURUSER['id'];
$userbarBbCode = "[url={$profileUrl}][img]{$userbarImageUrl}[/img][/url]";
$userbarImageCode = "[img]{$userbarImageUrl}[/img]";
$avatarLimitWidth = max(40, (int)($avatar_max_width ?? 120));
$avatarLimitHeight = max(40, (int)($avatar_max_height ?? 120));
$avatarPreviewSize = max(40, min(120, $avatarLimitWidth, $avatarLimitHeight));

stdhead($CURUSER["username"] . " :: редактирование профиля", false);

if (!empty($_GET["edited"])) {
    echo "<h1>Профиль успешно изменён</h1>\n";
    if (!empty($_GET["mailsent"])) {
        echo "<h2>Письмо с подтверждением отправлено</h2>\n";
    }
} elseif (!empty($_GET["emailch"])) {
    echo "<h1>Email успешно изменён</h1>\n";
} else {
    $userid = (int)$CURUSER['id'];
    $username = h($CURUSER['username'] ?? '');
    echo "<h1>Добро пожаловать, <a href='userdetails.php?id=$userid'>$username</a>!</h1>\n";
}

begin_frame($CURUSER["username"]);
?>
<table border="1" cellspacing="0" cellpadding="10" align="center" width="100%">
<tr>
<td>
<form method="post" action="takeprofedit.php" name="profileform" id="profileform" enctype="multipart/form-data">
<table border="1" cellspacing="0" cellpadding="5" width="100%">
<?php
/* -------- Стили -------- */
$stylesheets = "";
$ss_sa = [];
$ss_r = mysqli_query($mysqli, "SELECT id, name FROM stylesheets");
if ($ss_r) {
    while ($ss_a = mysqli_fetch_assoc($ss_r)) {
        $ss_sa[$ss_a['name']] = (int)$ss_a['id'];
    }
    ksort($ss_sa);
    foreach ($ss_sa as $ss_name => $ss_id) {
        $selected = ($ss_id === (int)($CURUSER["stylesheet"] ?? 1)) ? " selected" : "";
        $stylesheets .= "<option value=\"$ss_id\"$selected>" . h($ss_name) . "</option>\n";
    }
}
tr("Стиль сайта", "<select name=\"stylesheet\">\n$stylesheets\n</select>", 1);

/* -------- Страны -------- */
$countries = "<option value=\"0\">— Не выбрано —</option>\n";
$ct_r = mysqli_query($mysqli, "SELECT id, name FROM countries ORDER BY name");
if ($ct_r) {
    while ($ct_a = mysqli_fetch_assoc($ct_r)) {
        $id = (int)$ct_a['id'];
        $name = h($ct_a['name']);
        $selected = (((int)($CURUSER["country"] ?? 0)) === $id) ? " selected" : "";
        $countries .= "<option value=\"$id\"$selected>$name</option>\n";
    }
}
tr("Страна", "<select name=\"country\">\n$countries\n</select>", 1);

/* -------- Часовой пояс -------- */
$tzoffset = isset($CURUSER['tzoffset']) ? (string)$CURUSER['tzoffset'] : "0";
$timezones = [
    "-12"=>"(GMT -12:00) Эниветок, Кваджалейн","-11"=>"(GMT -11:00) О-в Мидуэй, Самоа","-10"=>"(GMT -10:00) Гавайи",
    "-9"=>"(GMT -09:00) Аляска","-8"=>"(GMT -08:00) Тихоокеанское (США/Канада)","-7"=>"(GMT -07:00) Горное (США/Канада)",
    "-6"=>"(GMT -06:00) Центральное (США/Канада), Мехико","-5"=>"(GMT -05:00) Восточное (США/Канада)","-4"=>"(GMT -04:00) Атлантическое",
    "-3.5"=>"(GMT -03:30) Ньюфаундленд","-3"=>"(GMT -03:00) Бразилия, Аргентина","-2"=>"(GMT -02:00) Среднеатлантическое",
    "-1"=>"(GMT -01:00) Азоры, Кабо-Верде","0"=>"(GMT) Лондон, Касабланка","1"=>"(GMT +01:00) Центральная Европа",
    "2"=>"(GMT +02:00) Киев, Минск","3"=>"(GMT +03:00) Москва (лето), Найроби","3.5"=>"(GMT +03:30) Тегеран",
    "4"=>"(GMT +04:00) Баку, Маскат","4.5"=>"(GMT +04:30) Кабул","5"=>"(GMT +05:00) Карачи, Екатеринбург",
    "5.5"=>"(GMT +05:30) Дели","5.75"=>"(GMT +05:45) Катманду","6"=>"(GMT +06:00) Алматы, Дакка",
    "6.5"=>"(GMT +06:30) Янгон","7"=>"(GMT +07:00) Бангкок, Ханой","8"=>"(GMT +08:00) Пекин, Сингапур",
    "9"=>"(GMT +09:00) Токио, Сеул","9.5"=>"(GMT +09:30) Аделаида, Дарвин","10"=>"(GMT +10:00) Сидней, Владивосток",
    "11"=>"(GMT +11:00) Магадан","12"=>"(GMT +12:00) Окленд, Камчатка"
];
$timezone_opts = "";
foreach ($timezones as $k=>$v) {
    $sel = ($tzoffset === $k) ? " selected" : "";
    $timezone_opts .= "<option value=\"$k\"$sel>$v</option>\n";
}
tr("Часовой пояс", "<select name=\"tzoffset\">\n$timezone_opts\n</select>", 1);

/* -------- Аватара, статус -------- */
$avatarEditor = ''
    . '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">'
    .   '<div style="min-width:' . $avatarPreviewSize . 'px;text-align:center">'
    .     '<img src="' . h($currentAvatarUrl) . '" alt="Текущий аватар" width="' . $avatarPreviewSize . '" height="' . $avatarPreviewSize . '"'
    .     ' style="display:block;width:' . $avatarPreviewSize . 'px;height:' . $avatarPreviewSize . 'px;border-radius:14px;object-fit:cover;border:1px solid #d7dfe8;box-shadow:0 4px 14px rgba(0,0,0,.08)">'
    .     '<div style="margin-top:6px;font-size:11px;color:#66758c">Текущий аватар</div>'
    .   '</div>'
    .   '<div style="flex:1 1 320px;min-width:260px">'
    .     '<input name="avatar" size="50" value="' . h($CURUSER["avatar"] ?? "") . '" placeholder="https://... или pic/avatars/..." style="width:98%"><br>'
    .     '<div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
    .       '<input type="file" name="avatar_file" accept=".png,.jpg,.jpeg,.gif">'
    .       '<button type="submit" name="avatar_upload_submit" value="1" style="padding:4px 12px;cursor:pointer">Загрузить</button>'
    .     '</div>'
    .     '<label style="display:inline-block;margin-top:8px"><input type="checkbox" name="avatar_clear" value="1"> убрать аватар и вернуть стандартный</label>'
    .     '<div style="margin-top:8px;color:#66758c;font-size:11px;line-height:1.45">'
    .       'Можно указать прямую ссылку на картинку или загрузить файл. Загруженные аватары сохраняются в <b>pic/avatars</b> и автоматически приводятся к размеру до <b>' . $avatarLimitWidth . 'x' . $avatarLimitHeight . '</b>.'
    .     '</div>'
    .   '</div>'
    . '</div>';
tr("Аватар", $avatarEditor, 1);
tr("Статус", "<input name=\"title\" size=\"50\" value=\"" . h($CURUSER["title"] ?? "") . "\">", 1);


/* -------- Сайт -------- */
tr(
    "Сайт (личная страница)",
    '<input name="website" size="50" value="' . h($CURUSER["website"] ?? "") . '" placeholder="https://example.com">'
    . ' <label style="margin-left:6px;"><input type="checkbox" name="website_clear" value="1"> очистить</label>',
    1
);


/* -------- Пагинация -------- */
tr("Торрентов на страницу", "<input type=\"text\" size=\"10\" name=\"torrentsperpage\" value=\"" . (int)($CURUSER["torrentsperpage"] ?? 0) . "\"> (0 — по умолчанию)", 1);

/* -------- ЛС -------- */
tr(
    "Разрешить ЛС от",
    "<label><input type='radio' name='acceptpms' value='yes'" . (($CURUSER["acceptpms"] ?? 'yes') === "yes" ? " checked" : "") . "> Все (кроме заблокированных)</label><br>" .
    "<label><input type='radio' name='acceptpms' value='friends'" . (($CURUSER["acceptpms"] ?? 'yes') === "friends" ? " checked" : "") . "> Только друзей</label><br>" .
    "<label><input type='radio' name='acceptpms' value='no'" . (($CURUSER["acceptpms"] ?? 'yes') === "no" ? " checked" : "") . "> Только администрации</label>"
, 1);
tr("Удалять ЛС при ответе", "<input type='checkbox' name='deletepms'" . (($CURUSER["deletepms"] ?? 'yes') === "yes" ? " checked" : "") . ">", 1);
tr("Сохранять отправленные ЛС", "<input type='checkbox' name='savepms'" . (($CURUSER["savepms"] ?? 'no') === "yes" ? " checked" : "") . ">", 1);

/* -------- Уведомления -------- */
$notifsRaw = (string)($CURUSER['notifs'] ?? '');
$pmChecked = (strpos($notifsRaw, '[pm]') !== false) ? " checked" : "";
$emChecked = (strpos($notifsRaw, '[email]') !== false) ? " checked" : "";
tr(
    "Уведомление о ЛС",
    "<label><input type='checkbox' name='pmnotif' value='yes'{$pmChecked}> Показывать заметное уведомление о новых личных сообщениях</label>"
    . "<div style='margin-top:4px;color:#66758c;font-size:11px;line-height:1.45'>Это относится к уведомлению внутри сайта. Письма на email включаются отдельной строкой ниже.</div>",
    1
);
tr("Email-уведомления", "<input type='checkbox' name='emailnotif' value='yes'{$emChecked}> Включить", 1);

/* -------- Приватность -------- */
$privacy = (string)($CURUSER['privacy'] ?? 'normal');
$privacyOpts = '';
foreach (['strong' => 'Сильная', 'normal' => 'Обычная', 'low' => 'Низкая'] as $k => $label) {
    $sel = ($privacy === $k) ? ' selected' : '';
    $privacyOpts .= "<option value=\"{$k}\"{$sel}>{$label}</option>\n";
}
tr("Приватность профиля", "<select name=\"privacy\">{$privacyOpts}</select>", 1);

/* -------- Категория по умолчанию — как «Страна» --------
   Для совместимости бэкенда (ждёт cat<ID>=yes) при сабмите добавим скрытый input.
*/
$defaultCatId = 0;
if (!empty($CURUSER['notifs']) && preg_match('~\[cat(\d+)\]~', (string)$CURUSER['notifs'], $m)) {
    $defaultCatId = (int)$m[1];
}
$cat_options = "<option value=\"0\">— Не выбрано —</option>\n";
if ($r = mysqli_query($mysqli, "SELECT id, name FROM categories ORDER BY name")) {
    while ($a = mysqli_fetch_assoc($r)) {
        $cid = (int)$a['id'];
        $nm  = h($a['name']);
        $sel = ($cid === $defaultCatId) ? " selected" : "";
        $cat_options .= "<option value=\"$cid\"$sel>$nm</option>\n";
    }
}
tr("Категория по умолчанию", "<select name=\"defaultcat\" id=\"defaultcat\">\n$cat_options\n</select>", 1);

/* -------- Язык -------- */
$currentLang = preg_replace('~[^a-z0-9_-]+~i', '', (string)($CURUSER['language'] ?? 'russian'));
$langOpts = '';
foreach (glob(__DIR__ . '/languages/lang_*', GLOB_ONLYDIR) ?: [] as $dir) {
    $code = preg_replace('~^lang_~', '', basename($dir));
    if ($code === '') continue;
    $sel = ($code === $currentLang) ? ' selected' : '';
    $langOpts .= "<option value=\"" . h($code) . "\"{$sel}>" . h(ucfirst($code)) . "</option>\n";
}
if ($langOpts === '') {
    $langOpts = "<option value=\"russian\" selected>Russian</option>\n";
}
tr("Язык интерфейса", "<select name=\"language\">\n{$langOpts}</select>", 1);

/* -------- Пол -------- */
tr(
    "Пол",
    "<label><input type=\"radio\" name=\"gender\" value=\"1\"" . (($CURUSER["gender"] ?? '1') == "1" ? " checked" : "") . "> Парень</label> ".
    "<label><input type=\"radio\" name=\"gender\" value=\"2\"" . (($CURUSER["gender"] ?? '1') == "2" ? " checked" : "") . "> Девушка</label> ".
    "<label><input type=\"radio\" name=\"gender\" value=\"3\"" . (($CURUSER["gender"] ?? '1') == "3" ? " checked" : "") . "> Не указывать</label>",
    1
);

/* -------- День рождения -------- */
$birthdayVal = '';
if (!empty($CURUSER['birthday']) && $CURUSER['birthday'] !== '0000-00-00') {
    $birthdayVal = h((string)$CURUSER['birthday']);
}
tr("Дата рождения", "<input type=\"date\" name=\"birthday\" value=\"{$birthdayVal}\">", 1);

/* -------- Telegram -------- */
$tg_val = h($CURUSER["telegram"] ?? "");
tr(
    "<img alt=\"Telegram\" src=\"pic/telegram.png\" width=\"17\" height=\"17\"> Telegram",
    "<input maxLength=\"64\" size=\"40\" name=\"telegram\" placeholder=\"@username или https://t.me/username\" ".
    "pattern=\"(^https?://t\\.me/[A-Za-z0-9_]{5,32}$)|(^@?[A-Za-z0-9_]{5,32}$)\" ".
    "title=\"Ник Telegram: 5–32 символов или https://t.me/username\" value=\"$tg_val\">",
    1
);

/* -------- Отображение -------- */
tr(
    "Показывать аватары",
    "<label><input type='checkbox' name='avatars' value='yes'" . (($CURUSER["avatars"] ?? 'yes') === "yes" ? " checked" : "") . "> Показывать мини-аватары</label>"
    . "<div style='margin-top:4px;color:#66758c;font-size:11px;line-height:1.45'>Влияет на список друзей и на вкладку друзей в профиле.</div>",
    1
);
$botPos = (string)($CURUSER['bot_pos'] ?? 'yes');
tr(
    "Показывать в онлайн-блоке",
    "<label><input type=\"radio\" name=\"bot_pos\" value=\"yes\"" . ($botPos === 'yes' ? " checked" : "") . "> Да</label> " .
    "<label><input type=\"radio\" name=\"bot_pos\" value=\"no\"" . ($botPos === 'no' ? " checked" : "") . "> Нет</label>",
    1
);

/* -------- Email / пароли -------- */
tr("Email", "<input type=\"text\" name=\"email\" size=\"50\" value=\"" . h($CURUSER["email"] ?? "") . "\" />", 1);
$currentPasskey = h((string)($CURUSER['passkey'] ?? ''));
$passkeyEditor = '<input type="text" value="' . $currentPasskey . '" readonly style="width:100%;max-width:380px;box-sizing:border-box;font-family:monospace" onclick="this.select()">'
    . '<div style="margin-top:4px;color:#66758c;font-size:11px;line-height:1.45">Текущий пасскей. Поле только для просмотра и быстрого копирования.</div>'
    . '<label style="display:inline-block;margin-top:8px"><input type="checkbox" name="resetpasskey" value="1" /> Сгенерировать новый пасскей</label>'
    . '<div style="margin-top:4px;color:#66758c;font-size:11px;line-height:1.45">После смены нужно перекачать активные торрент-файлы, иначе старые раздачи перестанут анонсироваться.</div>';
tr("Сменить пасскей", $passkeyEditor, 1);
tr("Старый пароль", "<input type=\"password\" name=\"oldpassword\" size=\"50\" />", 1);
tr("Новый пароль", "<input type=\"password\" name=\"chpassword\" size=\"50\" />", 1);
tr("Пароль ещё раз", "<input type=\"password\" name=\"passagain\" size=\"50\" />", 1);

/* -------- Юзербар -------- */
tr(
    $tracker_lang['my_userbar'] ?? "Мой юзербар",
    '<div style="display:grid;grid-template-columns:minmax(0,350px) minmax(260px,1fr);gap:14px;align-items:start">'
    . '<div style="max-width:100%">'
    .   '<img src="' . h($userbarImageUrl) . '" alt="Юзербар" width="350" height="60" style="display:block;width:100%;max-width:350px;height:auto;border:1px solid #d7dfe8;border-radius:10px;background:#f4f8fb">'
    . '</div>'
    . '<div style="min-width:0">'
    . ($tracker_lang['my_userbar_descr'] ?? "Скопируйте и вставьте этот код") . ":<br>"
    . '<div style="margin-top:6px">BBCode со ссылкой на профиль:<br><input type="text" value="' . h($userbarBbCode) . '" readonly style="width:100%;box-sizing:border-box"></div>'
    . '<div style="margin-top:8px">Только картинка:<br><input type="text" value="' . h($userbarImageCode) . '" readonly style="width:100%;box-sizing:border-box"></div>'
    . '<div style="margin-top:8px;color:#66758c;font-size:11px;line-height:1.45">Ссылка ведёт прямо на ваш профиль, а картинка открывается по прямому URL без внутренней авторизации.</div>'
    . '</div></div>',
    1
);

/* -------- О себе (BBCode) -------- */
ob_start();
textbbcode("profileform", "info", (string)($CURUSER["info"] ?? ""));
$bbcode_editor = ob_get_clean();
tr("О себе", $bbcode_editor . "<br>Показывается на вашей публичной странице. Допустимы <a href=\"tags.php\" target=\"_blank\">BB-коды</a>.", 1);
?>

<tr><td class="lol" colspan="2" align="center">
  <input type="submit" value="Обновить профиль" style="height:25px">
  <input type="reset" value="Отменить изменения" style="height:25px">
</td></tr>

</table>
<!-- сюда JS положит совместимое скрытое поле cat<ID>=yes -->
<div id="catHiddenHolder"></div>
</form>
</td>
</tr>
</table>

<script>
// Совместимость: бэкенд ждёт cat<ID>=yes. Конвертируем выбор defaultcat.
(function(){
  var f = document.getElementById('profileform');
  if (!f) return;
  f.addEventListener('submit', function(){
    var holder = document.getElementById('catHiddenHolder');
    if (!holder) return;
    holder.innerHTML = '';
    var sel = document.getElementById('defaultcat');
    var val = sel ? parseInt(sel.value, 10) : 0;
    if (val > 0) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'cat' + val;
      inp.value = 'yes';
      holder.appendChild(inp);
    }
  });
})();
</script>

<?php
end_frame();
stdfoot();
