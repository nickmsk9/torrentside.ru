<?php
require "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

function puke($text = "You have forgotten here someting?") {
    global $tracker_lang;
    stderr($tracker_lang['error'], $text);
}

function barf($text = "Пользователь удален") {
    global $tracker_lang;
    stderr($tracker_lang['success'], $text);
}

if (get_user_class() < UC_MODERATOR) {
    puke($tracker_lang['access_denied']);
}

$action = $_POST['action'] ?? '';

if ($action === 'edituser') {
    // --- INPUTS (нормализуем всё сразу) ---
    $userid   = (int)($_POST['userid'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $avatar   = trim((string)($_POST['avatar'] ?? ''));
    $groups   = ($_POST['groups'] ?? 'no') === 'yes' ? 'yes' : 'no';

    $resetb   = ($_POST['resetb'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $birthdayClause = ($resetb === 'yes') ? ", birthday = '0000-00-00'" : "";

    $enabled  = ($_POST['enabled'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $warned   = ($_POST['warned']  ?? '') === 'yes' ? 'yes' : (($_POST['warned'] ?? '') === 'no' ? 'no' : '');
    $schoutboxpos = ($_POST['schoutboxpos'] ?? 'yes') === 'yes' ? 'yes' : 'no';

    $warnlength = (int)($_POST['warnlength'] ?? 0);
    $warnpm     = trim((string)($_POST['warnpm'] ?? ''));

    $donor  = ($_POST['donor'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $hiderating = ($_POST['hiderating'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $rangclass  = (int)($_POST['rangclass'] ?? 0);

    $uploadtoadd   = (float)($_POST['amountup']   ?? 0);
    $downloadtoadd = (float)($_POST['amountdown'] ?? 0);
    $formatup      = strtolower((string)($_POST['formatup']   ?? 'mb')); // 'mb' | 'gb'
    $formatdown    = strtolower((string)($_POST['formatdown'] ?? 'mb')); // 'mb' | 'gb'
    $mpup          = ($_POST['upchange']   ?? 'plus')  === 'minus' ? 'minus' : 'plus';
    $mpdown        = ($_POST['downchange'] ?? 'plus')  === 'minus' ? 'minus' : 'plus';

    $support   = ($_POST['support'] ?? 'no') === 'yes' ? 'yes' : 'no';
    // htmlspecialchars(): никогда не пускаем null и явно задаём флаги/кодировку
    $supportfor = htmlspecialchars((string)($_POST['supportfor'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $modcomm    = htmlspecialchars((string)($_POST['modcomm'] ?? ''),    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $deluser   = !empty($_POST['deluser']);
    $class     = (int)($_POST['class'] ?? 0);

    if (!is_valid_id($userid) || !is_valid_user_class($class)) {
        stderr($tracker_lang['error'], "Неверный идентификатор пользователя или класса.");
    }

    // Проверка аватара (если указан)
    if ($avatar !== '') {
        // Разрешаем http/https/ftp, файл .gif|.jpg|.jpeg|.png
        if (!preg_match('#^(?:https?|ftp)://[^\s<>"]+?\.(?:gif|jpe?g|png)$#i', $avatar)) {
            stderr($tracker_lang['error'], $tracker_lang['avatar_adress_invalid']);
        }
    }

    // Текущие данные пользователя
    $res = sql_query("SELECT warned, enabled, username, schoutboxpos, class, hiderating, uploaded, downloaded, modcomment 
                      FROM users WHERE id = " . sqlesc($userid))
           or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res) or puke("Ошибка MySQL");

    $curenabled     = (string)$arr['enabled'];
    $curschoutboxpos= (string)$arr['schoutboxpos'];
    $curclass       = (int)$arr['class'];
    $curwarned      = (string)$arr['warned'];
    $curhiderating  = (string)$arr['hiderating'];
    $curUploaded    = (float)$arr['uploaded'];
    $curDownloaded  = (float)$arr['downloaded'];
    $dbModcomment   = (string)($arr['modcomment'] ?? '');

    // Модкоммент можно редактировать только SYSOP
    if (get_user_class() == UC_SYSOP) {
        $modcomment = htmlspecialchars((string)($_POST['modcomment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } else {
        $modcomment = $dbModcomment;
    }

    // Нельзя менять пользователя своего или более высокого класса
    if ($curclass >= get_user_class()) {
        puke("Так нельзя делать!");
    }

    $updateset = [];

    // --- Корректировка upload ---
    if ($uploadtoadd > 0) {
        $delta = ($formatup === 'mb') ? ($uploadtoadd * 1048576) : ($uploadtoadd * 1073741824);
        $newupload = ($mpup === 'plus') ? ($curUploaded + $delta) : ($curUploaded - $delta);
        if ($newupload < 0) {
            stderr($tracker_lang['error'], "Вы хотите отнять у пользователя отданого больше чем у него есть!");
        }
        $updateset[] = "uploaded = " . (int)$newupload;
        $modcomment = gmdate("Y-m-d") . " - Пользователь {$CURUSER['username']} " . ($mpup === 'plus' ? "добавил " : "отнял ")
                    . $uploadtoadd . ($formatup === 'mb' ? " MB" : " GB") . " к раздаче.\n" . $modcomment;
    }

    // --- Корректировка download ---
    if ($downloadtoadd > 0) {
        $delta = ($formatdown === 'mb') ? ($downloadtoadd * 1048576) : ($downloadtoadd * 1073741824);
        $newdownload = ($mpdown === 'plus') ? ($curDownloaded + $delta) : ($curDownloaded - $delta);
        if ($newdownload < 0) {
            stderr($tracker_lang['error'], "Вы хотите отнять у пользователя скачаного больше чем у него есть!");
        }
        $updateset[] = "downloaded = " . (int)$newdownload;
        $modcomment = gmdate("Y-m-d") . " - Пользователь {$CURUSER['username']} " . ($mpdown === 'plus' ? "добавил " : "отнял ")
                    . $downloadtoadd . ($formatdown === 'mb' ? " MB" : " GB") . " к скачаному.\n" . $modcomment;
    }

    // --- Смена класса ---
    if ($curclass !== $class) {
        $what = ($class > $curclass) ? "повышены" : "понижены";
        $msg = sqlesc("Вы были $what до класса \"" . get_user_class_name($class) . "\" пользователем {$CURUSER['username']}.");
        $added = sqlesc(get_date_time());
        $subject = sqlesc("Вы были $what");
        sql_query("INSERT INTO messages (sender, receiver, msg, added, subject) VALUES(0, $userid, $msg, $added, $subject)")
            or sqlerr(__FILE__, __LINE__);

        $updateset[] = "class = " . (int)$class;
        $what2 = ($class > $curclass ? "Повышен" : "Понижен");
        $modcomment = gmdate("Y-m-d") . " - $what2 до класса \"" . get_user_class_name($class) . "\" пользователем {$CURUSER['username']}.\n" . $modcomment;
    }

    // --- Предупреждение ---
    if ($warned !== '' && $curwarned !== $warned) {
        // Снятие предупреждения
        $updateset[] = "warned = " . sqlesc($warned);
        $updateset[] = "warneduntil = '0000-00-00 00:00:00'";
        if ($warned === 'no') {
            $subject = sqlesc("Ваше предупреждение снято");
            $msg = sqlesc("Ваше предупреждение снял пользователь {$CURUSER['username']}.");
            $added = sqlesc(get_date_time());
            sql_query("INSERT INTO messages (sender, receiver, msg, added, subject) VALUES (0, $userid, $msg, $added, $subject)")
                or sqlerr(__FILE__, __LINE__);
            $modcomment = gmdate("Y-m-d") . " - Предупреждение снял пользователь {$CURUSER['username']}.\n" . $modcomment;
        }
    } elseif ($warnlength > 0) {
        if ($warnpm === '') {
            stderr($tracker_lang['error'], "Вы должны указать причину по которой ставите предупреждение!");
        }
        if ($warnlength === 255) {
            $modcomment = gmdate("Y-m-d") . " - Предупрежден пользователем {$CURUSER['username']}.\nПричина: $warnpm\n" . $modcomment;
            $msg = sqlesc("Вы получили [url=rules.php#warning]предупреждение[/url] на неограниченый срок от {$CURUSER['username']}" . ($warnpm ? "\n\nПричина: $warnpm" : ""));
            $updateset[] = "warneduntil = '0000-00-00 00:00:00'";
        } else {
            $warneduntil = get_date_time(gmtime() + $warnlength * 604800);
            $dur = $warnlength . " недел" . ($warnlength > 1 ? "и" : "ю");
            $msg = sqlesc("Вы получили [url=rules.php#warning]предупреждение[/url] на $dur от пользователя {$CURUSER['username']}" . ($warnpm ? "\n\nПричина: $warnpm" : ""));
            $modcomment = gmdate("Y-m-d") . " - Предупрежден на $dur пользователем {$CURUSER['username']}.\nПричина: $warnpm\n" . $modcomment;
            $updateset[] = "warneduntil = " . sqlesc($warneduntil);
        }
        $added = sqlesc(get_date_time());
        $subject = sqlesc("Вы получили предупреждение");
        sql_query("INSERT INTO messages (sender, receiver, msg, added, subject) VALUES (0, $userid, $msg, $added, $subject)")
            or sqlerr(__FILE__, __LINE__);
        $updateset[] = "warned = 'yes'";
    }

    // --- Бан в чате ---
    if ($schoutboxpos !== $curschoutboxpos) {
        if ($schoutboxpos === 'yes') {
            $modcomment = gmdate("Y-m-d") . " - Бан в Чате был снят пользователем {$CURUSER['username']}.\n" . $modcomment;
            $msg   = sqlesc("Вы были разблокированы в Чате пользователем {$CURUSER['username']}. Вы снова можете общаться с пользователями.");
            write_log("<font color=red>Пользователь <b>" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</b> был разбанен в Чате пользователем <b><a href=userdetails.php?id=" . (int)$CURUSER['id'] . ">" . htmlspecialchars($CURUSER['username'], ENT_QUOTES, 'UTF-8') . "</a></b>.</font>");
        } else {
            $modcomment = gmdate("Y-m-d") . " - Бан в Чате от пользователя {$CURUSER['username']}.\n" . $modcomment;
            $msg   = sqlesc("Вы были забанены в Чате пользователем {$CURUSER['username']}, теперь Вы не сможете общаться с пользователями.");
            write_log("<font color=orange><b>Пользователь <u>" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</u> был забанен в Чате пользователем <a href=userdetails.php?id=" . (int)$CURUSER['id'] . ">" . htmlspecialchars($CURUSER['username'], ENT_QUOTES, 'UTF-8') . "</a>.</font></b>");
        }
        $added = sqlesc(get_date_time());
        sql_query("INSERT INTO messages (sender, receiver, msg, added) VALUES (0, $userid, $msg, $added)")
            or sqlerr(__FILE__, __LINE__);
    }

    // --- Безограниченный рейтинг ---
    if ($hiderating !== $curhiderating) {
        if ($hiderating === 'yes') {
            $modcomment = gmdate("Y-m-d") . " - Без ограниченный рейтинг включил {$CURUSER['username']}.\n" . $modcomment;
            $msg = sqlesc("Без ограниченный рейтинг вам включил {$CURUSER['username']}. Качайте на здоровье.");
        } else {
            $modcomment = gmdate("Y-m-d") . " - Без ограниченный рейтинг отключил {$CURUSER['username']}.\n" . $modcomment;
            $msg = sqlesc("Без ограниченный рейтинг вам отключил {$CURUSER['username']}. Скорей всего это случилось из-за нарушения правил.");
        }
        $added = sqlesc(get_date_time());
        sql_query("INSERT INTO messages (sender, receiver, msg, added) VALUES (0, $userid, $msg, $added)")
            or sqlerr(__FILE__, __LINE__);
    }

    // --- Включён/отключён ---
    if ($enabled !== $curenabled) {
        if ($enabled === 'yes') {
            if (empty($_POST['enareason'])) {
                puke("Введите причину почему вы включаете пользователя!");
            }
            $enareason = htmlspecialchars((string)$_POST['enareason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $modcomment = gmdate("Y-m-d") . " - Включен пользователем {$CURUSER['username']}.\nПричина: $enareason\n" . $modcomment;
        } else {
            if (empty($_POST['disreason'])) {
                puke("Введите причину почему вы отключаете пользователя!");
            }
            $disreason = htmlspecialchars((string)$_POST['disreason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $modcomment = gmdate("Y-m-d") . " - Отключен пользователем {$CURUSER['username']}.\nПричина: $disreason\n" . $modcomment;
        }
    }

    // --- Общие апдейты ---
$updateset[] = "enabled = " . sqlesc($enabled);
$updateset[] = "schoutboxpos = " . sqlesc($schoutboxpos);
$updateset[] = "`groups` = " . sqlesc($groups);     // ← было: groups =
$updateset[] = "donor = " . sqlesc($donor);
$updateset[] = "supportfor = " . sqlesc($supportfor);
$updateset[] = "support = " . sqlesc($support);
$updateset[] = "avatar = " . sqlesc($avatar);
$updateset[] = "hiderating = " . sqlesc($hiderating);
$updateset[] = "rangclass = " . (int)$rangclass;
$updateset[] = "username = " . sqlesc($username);


    if ($modcomm !== '') {
        $modcomment = gmdate("Y-m-d") . " - Заметка от {$CURUSER['username']}: $modcomm\n" . $modcomment;
    }
    $updateset[] = "modcomment = " . sqlesc($modcomment);

    if (!empty($_POST['resetkey'])) {
        // совместимость: оставим старый стиль passkey, но можно заменить на более случайный
        $passkey = md5($CURUSER['username'] . get_date_time() . $CURUSER['passhash']);
        $updateset[] = "passkey = " . sqlesc($passkey);
    }

    sql_query("UPDATE users SET " . implode(", ", $updateset) . " $birthdayClause WHERE id = " . (int)$userid)
        or sqlerr(__FILE__, __LINE__);

    // --- Удаление пользователя ---
    if ($deluser) {
        $res = sql_query("SELECT username, email FROM users WHERE id = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        $user = mysqli_fetch_assoc($res);
        $delusername = $user['username'] ?? '';
        sql_query("DELETE FROM users    WHERE id = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM messages WHERE receiver = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM friends  WHERE userid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM friends  WHERE friendid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM blocks   WHERE userid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM blocks   WHERE blockid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM invites  WHERE inviter = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM peers    WHERE userid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM checkcomm WHERE userid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM sessions WHERE uid = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
        write_log("Пользователь " . htmlspecialchars($delusername, ENT_QUOTES, 'UTF-8') . " был удален пользователем " . htmlspecialchars($CURUSER['username'], ENT_QUOTES, 'UTF-8'));
        barf();
    } else {
        // безопасный returnto
        $returnto = (string)($_POST['returnto'] ?? 'index.php');
        // базовая защита от header injection/полных URL
        $returnto = ltrim($returnto, '/');
        header("Location: $DEFAULTBASEURL/$returnto");
        exit;
    }
}
// --- confirmuser ---
elseif ($action === 'confirmuser') {
    $userid  = (int)($_POST['userid'] ?? 0);
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!is_valid_id($userid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

$updates = [
    "`status` = " . sqlesc($confirm),            // ← было: status =
    "last_login = " . sqlesc(get_date_time()),
    "last_access = " . sqlesc(get_date_time()),
];

    sql_query("UPDATE users SET " . implode(", ", $updates) . " WHERE id = " . (int)$userid)
        or sqlerr(__FILE__, __LINE__);

    $returnto = (string)($_POST['returnto'] ?? 'index.php');
    $returnto = ltrim($returnto, '/');
    header("Location: $DEFAULTBASEURL/$returnto");
    exit;
}

puke(); // если мы сюда дошли — что-то не так
