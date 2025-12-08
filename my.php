<?php
declare(strict_types=1);

require_once("include/bittorrent.php");

dbconn(false);
loggedinorreturn();

global $CURUSER, $DEFAULTBASEURL, $tracker_lang;
/** @var mysqli $mysqli */
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
    die('MySQL handle not available');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
<form method="post" action="takeprofedit.php" name="profileform" id="profileform">
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
tr("Адрес аватары", "<input name=\"avatar\" size=\"50\" value=\"" . h($CURUSER["avatar"] ?? "") . "\"><br>Размер ≤ 100×100 пикселей.", 1);
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
tr("Тем на страницу", "<input type=\"text\" size=\"10\" name=\"topicsperpage\" value=\"" . (int)($CURUSER["topicsperpage"] ?? 0) . "\"> (0 — по умолчанию)", 1);
tr("Сообщений на страницу", "<input type=\"text\" size=\"10\" name=\"postsperpage\" value=\"" . (int)($CURUSER["postsperpage"] ?? 0) . "\"> (0 — по умолчанию)", 1);

/* -------- ЛС -------- */
tr(
    "Разрешить ЛС от",
    "<label><input type='radio' name='acceptpms' value='yes'" . (($CURUSER["acceptpms"] ?? 'yes') === "yes" ? " checked" : "") . "> Все (кроме заблокированных)</label><br>" .
    "<label><input type='radio' name='acceptpms' value='friends'" . (($CURUSER["acceptpms"] ?? 'yes') === "friends" ? " checked" : "") . "> Только друзей</label><br>" .
    "<label><input type='radio' name='acceptpms' value='no'" . (($CURUSER["acceptpms"] ?? 'yes') === "no" ? " checked" : "") . "> Только администрации</label>"
, 1);
tr("Удалять ЛС при ответе", "<input type='checkbox' name='deletepms'" . (($CURUSER["deletepms"] ?? 'yes') === "yes" ? " checked" : "") . ">", 1);
tr("Сохранять отправленные ЛС", "<input type='checkbox' name='savepms'" . (($CURUSER["savepms"] ?? 'no') === "yes" ? " checked" : "") . ">", 1);

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

/* -------- Пол -------- */
tr(
    "Пол",
    "<label><input type=\"radio\" name=\"gender\" value=\"1\"" . (($CURUSER["gender"] ?? '1') == "1" ? " checked" : "") . "> Парень</label> ".
    "<label><input type=\"radio\" name=\"gender\" value=\"2\"" . (($CURUSER["gender"] ?? '1') == "2" ? " checked" : "") . "> Девушка</label>",
    1
);

/* -------- Telegram (вместо ICQ) -------- */
$tg_val = h($CURUSER["telegram"] ?? "");
tr(
    "<img alt=\"Telegram\" src=\"pic/telegram.png\" width=\"17\" height=\"17\"> Telegram",
    "<input maxLength=\"64\" size=\"40\" name=\"telegram\" placeholder=\"@username или https://t.me/username\" ".
    "pattern=\"(^https?://t\\.me/[A-Za-z0-9_]{5,32}$)|(^@?[A-Za-z0-9_]{5,32}$)\" ".
    "title=\"Ник Telegram: 5–32 символов или https://t.me/username\" value=\"$tg_val\">",
    1
);

/* -------- Email / пароли -------- */
tr("Email", "<input type=\"text\" name=\"email\" size=\"50\" value=\"" . h($CURUSER["email"] ?? "") . "\" />", 1);
tr("Сменить пасскей", "<input type=\"checkbox\" name=\"resetpasskey\" value=\"1\" /> (после этого нужно перекачать активные торренты)", 1);
tr("Старый пароль", "<input type=\"password\" name=\"oldpassword\" size=\"50\" />", 1);
tr("Новый пароль", "<input type=\"password\" name=\"chpassword\" size=\"50\" />", 1);
tr("Пароль ещё раз", "<input type=\"password\" name=\"passagain\" size=\"50\" />", 1);

/* -------- Юзербар -------- */
$userbar = "[url=$DEFAULTBASEURL][img]$DEFAULTBASEURL/torrentbar/bar.php?id=".(int)$CURUSER["id"]."[/img][/url]";
tr(
    $tracker_lang['my_userbar'] ?? "Мой юзербар",
    "<img src=\"torrentbar/bar.php?id=".(int)$CURUSER['id']."\" alt=\"Userbar\"><br>".
    ($tracker_lang['my_userbar_descr'] ?? "Скопируйте и вставьте этот код") . ":<br>".
    "<input type=\"text\" size=\"65\" value=\"".h($userbar)."\" readonly>",
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
