<?php
require_once("include/bittorrent.php");
dbconn();

// Защита от прямого вызова без параметров
if (!mkglobal("type") || empty($type)) {
    die();
}

$type = htmlspecialchars($type);

if ($type === "signup" && mkglobal("email")) {
    if (!validemail($email)) {
        stderr($tracker_lang['error'], "Это не похоже на реальный email адрес.");
    }

    stdhead($tracker_lang['signup_successful']);
    stdmsg(
        $tracker_lang['signup_successful'],
        $use_email_act
            ? sprintf($tracker_lang['confirmation_mail_sent'], htmlspecialchars($email))
            : sprintf($tracker_lang['thanks_for_registering'], $SITENAME)
    );
    stdfoot();

} elseif ($type === "sysop") {

    stdhead($tracker_lang['sysop_activated']);

    if (isset($CURUSER)) {
        stdmsg(
            $tracker_lang['sysop_activated'],
            sprintf($tracker_lang['sysop_account_activated'], $DEFAULTBASEURL)
        );
    } else {
        echo "<p>Ваш аккаунт активирован! Однако вы не были автоматически авторизованы. Возможно, у вас отключены cookies в браузере. Чтобы пользоваться аккаунтом, включите cookies и <a href=\"login.php\">войдите вручную</a>.</p>\n";
    }

    stdfoot();

} elseif ($type === "confirmed") {

    stdhead($tracker_lang['account_activated']);
    stdmsg($tracker_lang['account_activated'], $tracker_lang['this_account_activated']);
    stdfoot();

} elseif ($type === "confirm") {

    if (isset($CURUSER)) {
        stdhead("Подтверждение регистрации");
        echo "<h1>Ваш аккаунт успешно подтверждён!</h1>\n";
        echo "<p>Теперь вы вошли в систему. Вы можете <a href=\"$DEFAULTBASEURL/\"><b>перейти на главную</b></a> и начать пользоваться аккаунтом.</p>\n";
        echo "<p>Перед началом использования $SITENAME рекомендуем прочитать <a href=\"rules.php\"><b>правила</b></a> и <a href=\"faq.php\"><b>ЧаВо</b></a>.</p>\n";
        stdfoot();
    } else {
        stdhead("Подтверждение регистрации");
        echo "<h1>Аккаунт успешно подтверждён!</h1>\n";
        echo "<p>Ваш аккаунт активирован, но автоматический вход не выполнен. Возможно, у вас отключены cookies в браузере. Пожалуйста, включите их и <a href=\"login.php\">войдите вручную</a>.</p>\n";
        stdfoot();
    }

} else {
    die();
}
?>
