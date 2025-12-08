<?php

require_once("include/bittorrent.php");
dbconn();

// Проверка условий для регистрации
if ($deny_signup && !$allow_invite_signup) {
    stderr($tracker_lang['error'], "Извините, но регистрация отключена администрацией.");
}

if ($CURUSER) {
    stderr($tracker_lang['error'], sprintf($tracker_lang['signup_already_registered'], $SITENAME));
}

list($users) = mysqli_fetch_array(sql_query("SELECT COUNT(id) FROM users"));
if ($users >= $maxusers) {
    stderr($tracker_lang['error'], sprintf($tracker_lang['signup_users_limit'], number_format($maxusers)));
}

// ======== Показываем форму регистрации сразу ========

stdhead($tracker_lang['signup_signup']);
begin_frame("Регистрация");

// Список стран
$countries = "<option value=\"0\">".$tracker_lang['signup_not_selected']."</option>\n";
$ct_r = sql_query("SELECT id, name FROM countries ORDER BY name");
while ($ct_a = mysqli_fetch_array($ct_r)) {
    $countries .= "<option value=\"{$ct_a['id']}\">{$ct_a['name']}</option>\n";
}

// Выбор даты рождения
$year = "<select name=\"year\"><option value=\"0.0000\">".$tracker_lang['my_year']."</option>\n";
for ($i = 1920; $i <= date('Y') - 13; $i++) {
    $year .= "<option value=\"$i\">$i</option>\n";
}
$year .= "</select>\n";

$birthmonths = [
    "01" => $tracker_lang['my_months_january'],
    "02" => $tracker_lang['my_months_february'],
    "03" => $tracker_lang['my_months_march'],
    "04" => $tracker_lang['my_months_april'],
    "05" => $tracker_lang['my_months_may'],
    "06" => $tracker_lang['my_months_june'],
    "07" => $tracker_lang['my_months_jule'],
    "08" => $tracker_lang['my_months_august'],
    "09" => $tracker_lang['my_months_september'],
    "10" => $tracker_lang['my_months_october'],
    "11" => $tracker_lang['my_months_november'],
    "12" => $tracker_lang['my_months_december'],
];
$month = "<select name=\"month\"><option value=\"00\">".$tracker_lang['my_month']."</option>\n";
foreach ($birthmonths as $month_no => $show_month) {
    $month .= "<option value=\"$month_no\">$show_month</option>\n";
}
$month .= "</select>\n";

$day = "<select name=\"day\"><option value=\"00\">".$tracker_lang['my_day']."</option>\n";
for ($i = 1; $i <= 31; $i++) {
    $val = str_pad($i, 2, "0", STR_PAD_LEFT);
    $day .= "<option value=\"$val\">$val</option>\n";
}
$day .= "</select>\n";

if ($deny_signup && $allow_invite_signup) {
    stdmsg("Внимание", "Регистрация доступна только тем у кого есть код приглашения!");
}
?>

<script src="js/ajax.js" type="text/javascript"></script>

<form method="post" action="takesignup.php">
    <table align="center" border="2" cellspacing="0" cellpadding="10">
        <?php
        tr("Никнэйм", "<input type=\"text\" size=\"60\" name=\"wantusername\" id=\"wantusername\" onblur=\"signup_check('username'); return false;\"/><div id=\"check_username\"></div>", 1);
        tr("Пароль", "<input type=\"password\" size=\"60\" name=\"wantpassword\" id=\"wantpassword\"/>", 1);
        tr("Повторите Пароль", "<input type=\"password\" size=\"60\" name=\"passagain\" id=\"passagain\" onblur=\"signup_check('password'); return false;\"/><div id=\"check_password\"></div>", 1);
        tr("E-Mail Адрес", "<input type=\"text\" size=\"60\" name=\"email\" id=\"email\" onblur=\"signup_check('email'); return false;\"/><div id=\"check_email\"></div>", 1);
        tr("Пол", "<input type=\"radio\" name=\"gender\" value=\"1\"><img src=\"pic/ico_m.gif\"> <input type=\"radio\" name=\"gender\" value=\"2\"><img src=\"pic/ico_f.gif\">", 1);
        tr($tracker_lang['my_birthdate'], "<b>$year$month$day</b>", 1);
        tr($tracker_lang['my_country'], "<select name=\"country\">$countries</select>", 1);
tr(
    $tracker_lang['signup_contact'],
    '<img alt="Telegram" src="pic/telegram.png" width="17" height="17" style="vertical-align:middle;margin-right:6px;">' .
    '<input name="telegram" maxlength="64" size="25" ' .
    'placeholder="@username или https://t.me/username" ' .
    'pattern="(^https?://t\.me/[A-Za-z0-9_]{5,32}$)|(^@?[A-Za-z0-9_]{5,32}$)" ' .
    'title="Ник Telegram: 5–32 символов (буквы, цифры, подчёркивания) или ссылка https://t.me/username">',
    1
);


        tr("Соглашения", "
            <input type=\"checkbox\" name=\"rulesverify\" value=\"yes\">".$tracker_lang['signup_i_have_read_rules']."<br />
            <input type=\"checkbox\" name=\"faqverify\" value=\"yes\">".$tracker_lang['signup_i_will_read_faq']."<br />
            <input type=\"checkbox\" name=\"ageverify\" value=\"yes\">Мне больше 18 лет
        ", 1);
        tr("Регистрация", "<input type=\"submit\" value=\"Регистрация\">", 1);
        ?>
    </table>
</form>

<br>
<?php
end_frame();
print("<div id='loading-layer'></div>");
stdfoot();
?>
