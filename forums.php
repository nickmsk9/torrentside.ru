<?php
require_once "include/bittorrent.php";

define('IN_FORUM', true);

/**
 * Мини-хелперы для кэша (общий persistent Memcached)
 */
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached('tbdev-persistent');
    if (empty($memcached->getServerList())) {
        // подстрой под свой хост/порт при необходимости
        $memcached->addServer('127.0.0.1', 11211);
    }
}

/** безопасный set */
function mc_set_safe(Memcached $mc, string $key, mixed $value, int $ttl): void {
    // TTL > 30 дней в Memcached трактуется как unix-time; нам достаточно минутных значений
    $mc->set($key, $value, $ttl);
}

/** ======================= AJAX-блок "кто на форуме" ======================= */
$xhrHeader = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (!empty($xhrHeader) && strcasecmp($xhrHeader, 'XMLHttpRequest') === 0) {
    dbconn(false, true);

    // Charset — если нет $tracker_lang['language_charset'], дефолтимся на utf-8
    $charset = 'utf-8';
    if (!empty($tracker_lang) && is_array($tracker_lang) && !empty($tracker_lang['language_charset'])) {
        $charset = (string)$tracker_lang['language_charset'];
    }
    header("Content-Type: text/html; charset={$charset}");

    $dt = get_date_time(gmtime() - 180); // 3 минуты назад

    // пользователи, кто заходил на форум за 3 минуты
    $res_s = sql_query(
        "SELECT id, username, class
         FROM users
         WHERE forum_access > " . sqlesc($dt) . "
         ORDER BY forum_access DESC"
    ) or sqlerr(__FILE__, __LINE__);

    $title_who_s = [];
    while ($ar_r = mysqli_fetch_assoc($res_s)) {
        $uid  = (int)$ar_r['id'];
        $name = (string)$ar_r['username'];
        $cls  = (int)$ar_r['class'];
        // безопасная подсветка логина по классу (get_user_class_color сам экранирует имя)
        $title_who_s[] = '<a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a>';
    }

    // гости/боты из sessions, url начинается с /forums.php
    $mc_key = 'forums:sessionbots:v1';
    $ips = $memcached->get($mc_key);
    if ($ips === false || !is_array($ips)) {
        $res_s2 = sql_query(
            "SELECT ip
             FROM sessions
             WHERE time > " . sqlesc(get_date_time(gmtime() - 180)) . "
               AND LEFT(url, 11) = '/forums.php'
               AND uid = -1
             ORDER BY time DESC"
        ) or sqlerr(__FILE__, __LINE__);

        $ips = [];
        while ($row = mysqli_fetch_assoc($res_s2)) {
            if (!empty($row['ip'])) {
                $ips[] = (string)$row['ip'];
            }
        }
        mc_set_safe($memcached, $mc_key, $ips, 60); // кэш 60 сек
    }
    if (!empty($ips)) {
        // добавляем IP как строки (как было)
        $title_who_s = array_merge($title_who_s, $ips);
    }

    if (!empty($title_who_s)) {
        $title_who_s = array_unique($title_who_s);
        echo implode(', ', $title_who_s);
    }
    exit;
}
/** ===================== конец AJAX-блока ===================== */

require_once ROOT_PATH . "include/functions_forum.php";
dbconn(false);

parse_referer();

if (!empty($Forum_Config) && isset($Forum_Config['guest']) && $Forum_Config['guest'] === false) {
    loggedinorreturn();
}

get_cleanup();

if (!empty($Forum_Config) && isset($Forum_Config['on']) && $Forum_Config['on'] === false) {
    define('LOGO', '');
    stderr_f(
        "Внимание",
        "Форум временно отключен. " .
        (!empty($Forum_Config['off_reason']) ? "Причина отключения: " . $Forum_Config['off_reason'] : "")
    );
    exit;
}

$content = '<center><font color="white">TorrentSide</font></center>';
define('LOGO', $content);

// ----------------- Параметры и входные данные -----------------
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$forum_pic_url = $DEFAULTBASEURL . "/pic/forumicons/";
$maxsubjectlength = 255;

// $postsperpage = (int) ($CURUSER["postsperpage"] ?? 0);
// if (!$postsperpage)
$postsperpage = 25;

// =====================================================================================
// =                                   EDIT TOPIC                                      =
// =====================================================================================
if ($action === 'edittopic') {

    if (get_user_class() < UC_MODERATOR) {
        die('Вы не с администрации');
    }

    $topicid = isset($_GET['topicid']) ? (int)$_GET['topicid'] : 0;
    if (!is_valid_id($topicid)) {
        die('id не цифра');
    }

    $res = sql_query("SELECT * FROM topics WHERE id = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);

    if (!$arr) {
        die('Такой темы не существует в базе данных.');
    }

    $topic_name = format_comment($arr['subject']);
    stdhead_f('Редактирование топика');

    $modcomment = htmlspecialchars((string)$arr['t_com'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $forums     = htmlspecialchars((string)$arr['subject'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sticky     = (string)$arr['sticky'];
    $visible    = (string)$arr['visible'];
    $locked     = (string)$arr['locked'];
    $forumid    = isset($arr['forumid']) ? (int)$arr['forumid'] : 0;
    $user_class = (int)get_user_class();

    echo '<table style="margin-top: 2px;" cellpadding="5" width="100%">';
    echo '<tr><td class="colhead" align="center" colspan="2"><a name="comments"></a><b>.::: Администрирование темы :::.</b><br /><a class="altlink_white" title="Вернутся обратно к показу сообщений в теме" href="' . $DEFAULTBASEURL . '/forums.php?action=viewtopic&topicid=' . $topicid . '">' . $topic_name . '</a></td></tr>';

    echo '<form method="post" action="' . $DEFAULTBASEURL . '/forums.php?action=edittopicmod&topicid=' . $topicid . '">';
    echo '<tr><td class="a"><b>Прикрепить тему</b>: 
        <label><input type="radio" name="sticky" value="yes" ' . ($sticky === 'yes' ? 'checked' : '') . '> Да</label> 
        <label><input type="radio" name="sticky" value="no" ' . ($sticky === 'no' ? 'checked' : '') . '> Нет</label> 
        <i>по умолчанию все темы без прикрепления (без важности)</i></td></tr>';

    echo '<tr><td class="a"><b>Видимая тема</b>:
        <label><input type="radio" name="visible" value="yes" ' . ($visible === 'yes' ? 'checked' : '') . '> Да</label>
        <label><input type="radio" name="visible" value="no" ' . ($visible === 'no' ? 'checked' : '') . '> Нет</label></td></tr>';

    echo '<tr><td class="a"><b>Заблокировать тему</b>:
        <label><input type="radio" name="locked" value="yes" ' . ($locked === 'yes' ? 'checked' : '') . '> Да</label>
        <label><input type="radio" name="locked" value="no" ' . ($locked === 'no' ? 'checked' : '') . '> Нет</label> 
        <i>пользователи не смогут писать сообщения в теме.</i></td></tr>';

    echo '<tr><td class="a"><b>Переименовать тему</b>: 
        <input type="text" name="subject" size="60" maxlength="' . (int)$maxsubjectlength . '" value="' . $forums . '"></td></tr>';

    // -------- список форумов (кэш через Memcached, 24 часа) --------
    $forums_list_key = 'forums:id_name:v1';
    $forums_list = $memcached->get($forums_list_key);
    if ($forums_list === false || !is_array($forums_list)) {
        $res_forums = sql_query("SELECT id, name, minclasswrite, minclassread FROM forums ORDER BY name")
            or sqlerr(__FILE__, __LINE__);
        $forums_list = [];
        while ($f = mysqli_fetch_assoc($res_forums)) {
            $forums_list[] = [
                'id'            => (int)$f['id'],
                'name'          => (string)$f['name'],
                'minclasswrite' => (int)$f['minclasswrite'],
                'minclassread'  => (int)$f['minclassread'],
            ];
        }
        mc_set_safe($memcached, $forums_list_key, $forums_list, 60 * 60 * 24);
    }

    $select = '';
    foreach ($forums_list as $f) {
        if ((int)$f['id'] !== $forumid && $user_class >= (int)$f['minclasswrite']) {
            $fname = htmlspecialchars($f['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $select .= '<option value="' . (int)$f['id'] . '">' . $fname . '</option>' . "\n";
        }
    }

    echo '<tr><td class="a">
        <b>Переместить тему в</b>: 
        <select name="forumid">
            <option value="0">выбрать из списка</option>' . $select . '
        </select>
    </td></tr>';

    echo '<tr><td class="a">
        <b>Удалить тему</b>: <label><input name="delete_topic" value="1" type="checkbox"> <i>удаляем полностью тему</i></label><br />
        <input type="text" name="reson" size="60" maxlength="' . (int)$maxsubjectlength . '" value="не подходит под правила"> <i>причина обязательна</i>
    </td></tr>';

    // cols должен быть числом — задаём width через стиль
    echo '<tr><td class="a">История темы <b>' . $forums . '</b> и её прилегающих сообщений [' . strlen($modcomment) . ']<br />
        <textarea style="width:100%;" rows="6"' . (get_user_class() < UC_SYSOP ? ' readonly' : ' name="modcomment"') . '>' . $modcomment . '</textarea>
    </td></tr>
    <tr><td class="a"><b>Добавить заметку</b>: <textarea style="width:100%;" rows="3" name="modcomm"></textarea></td></tr>';

    echo '<tr><td align="center" colspan="2">';
    echo '<input type="hidden" value="' . $topicid . '" name="topicid"/>';
    echo '<input type="submit" class="btn" value="Выполнить действие" />';
    echo '</td></tr></table></form><br />';

    stdfoot_f();
    exit;
}


  // =========================== CATCHUP ===========================
if ($action === 'catchup') {
    // помечаем всё прочитанным
    catch_up();

    // мягкий автопереход + страница с кнопкой (как у тебя)
    header("Refresh: 5; url={$DEFAULTBASEURL}/forums.php");
    stderr_f(
        'Успешно: автопереход через 5 сек',
        'Все сообщения и темы помечены как прочитанные.<br>Нажмите <a href="' . $DEFAULTBASEURL . '/forums.php">ЗДЕСЬ</a>, если не хотите ждать.'
    );
    exit;
}

// ====================== FORUM MOD COMMENT (SYSOP/MOD) ======================
if ($action === 'forum_fcom' && get_user_class() >= UC_MODERATOR) {

    $forumfid = (int)($_POST['forumfid'] ?? 0);
    if (!is_valid_id($forumfid)) {
        die('id не цифра');
    }

    // принимаем поля; экранируем, чтобы не словить notice и XSS
    $mod_comment = htmlspecialchars((string)($_POST['modcomment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mod_comm    = htmlspecialchars((string)($_POST['modcomm'] ?? ''),    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $res_fo  = sql_query('SELECT f_com FROM forums WHERE id = ' . sqlesc($forumfid)) or sqlerr(__FILE__, __LINE__);
    $arr_for = mysqli_fetch_assoc($res_fo);

    if (!$arr_for) {
        die('Такой категории не существует в базе данных.');
    } else {
        // только SYSOP может перезаписывать весь мод-комментарий
        if (get_user_class() === UC_SYSOP) {
            $modik = $mod_comment;
        } else {
            $modik = (string)$arr_for['f_com']; // оригинал комментария категории форума
        }

        // добавляем заметку в начало, если она есть
        if (!empty($mod_comm)) {
            $u = $CURUSER['username'] ?? 'unknown';
            $modik = date('Y-m-d') . " - Заметка от {$u}: {$mod_comm}\n" . $modik;

            sql_query(
                'UPDATE forums SET f_com = ' . sqlesc($modik) . ' WHERE id = ' . sqlesc($forumfid)
            ) or sqlerr(__FILE__, __LINE__);

            // инвалидация связанного кэша, если он используется где-то ещё
            if (isset($memcached) && $memcached instanceof Memcached) {
                $memcached->delete('forums:id_name:v1');
            }
        }
    }

    header("Refresh: 10; url={$DEFAULTBASEURL}/forums.php?action=viewforum&forumid={$forumfid}");
    stderr_f(
        'Успешно: автопереход через 10 сек',
        'Заметка ' . (!empty($mod_comm) ? 'добавлена' : 'обновлена') . '.<br>Нажмите <a href="' . $DEFAULTBASEURL . '/forums.php?action=viewforum&forumid=' . $forumfid . '">ЗДЕСЬ</a>, если не хотите ждать.'
    );
    exit;
}

// ============================== ROUTER ==============================
switch ($action) {

    // ============================== VIEWFORUM ==============================
    case 'viewforum': {

        $forumid = (int)($_GET['forumid'] ?? 0);
        if (!is_valid_id($forumid)) {
            header("Location: {$DEFAULTBASEURL}/forums.php");
            exit;
        }

        $page   = isset($_GET['page']) ? max(0, (int)$_GET['page']) : 0;
        $userid = (int)($CURUSER['id'] ?? 0);

        // читаем сам форум
        $res = sql_query(
            'SELECT name, minclassread, description, f_com
             FROM forums
             WHERE id = ' . sqlesc($forumid)
        ) or sqlerr(__FILE__, __LINE__);

        if (mysqli_num_rows($res) === 0) {
            stderr_f('Внимание', 'В доступе отказано. Такой категории на форуме нет.');
            exit;
        }

        $arr         = mysqli_fetch_assoc($res);
        $f_com       = (string)$arr['f_com'];
        $forumname   = htmlspecialchars((string)$arr['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = htmlspecialchars((string)$arr['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // проверка доступа
        $min_read = (int)$arr['minclassread'];
        if ($min_read > (int)get_user_class() && $min_read !== 0) {
            header("Location: {$DEFAULTBASEURL}/forums.php");
            exit;
        }

        // пагинация
        // $perpage = (int)($CURUSER["postsperpage"] ?? 0);
        // if (!$perpage)
        $perpage = 25;

        // ---------- COUNT(*) тем (с коротким кэшем) ----------
        $count = 0;
        if (isset($memcached) && $memcached instanceof Memcached) {
            $ckey = "forums:{$forumid}:topic_count:v1";
            $cval = $memcached->get($ckey);
            if ($cval !== false) {
                $count = (int)$cval;
            } else {
                $res_cnt = sql_query(
                    "SELECT COUNT(*) AS c
                     FROM topics
                     WHERE forumid = " . sqlesc($forumid) . " AND visible = 'yes'"
                ) or sqlerr(__FILE__, __LINE__);
                $row_cnt = mysqli_fetch_assoc($res_cnt);
                $count   = (int)($row_cnt['c'] ?? 0);
                $memcached->set($ckey, $count, 30); // 30 сек — короткий TTL
            }
        } else {
            $res_cnt = sql_query(
                "SELECT COUNT(*) AS c
                 FROM topics
                 WHERE forumid = " . sqlesc($forumid) . " AND visible = 'yes'"
            ) or sqlerr(__FILE__, __LINE__);
            $row_cnt = mysqli_fetch_assoc($res_cnt);
            $count   = (int)($row_cnt['c'] ?? 0);
        }

        [$pagertop, $pagerbottom, $limit] = pager(
            $perpage,
            $count,
            "{$DEFAULTBASEURL}/forums.php?action=viewforum&forumid={$forumid}&"
        );

        // ---------- список тем (с коротким кэшем на страницу) ----------
        $topics_sql =
            "SELECT t.*,
                    (SELECT COUNT(*) FROM posts p WHERE p.topicid = t.id) AS num_po
             FROM topics t
             WHERE t.forumid = " . sqlesc($forumid) . " AND t.visible = 'yes'
             ORDER BY (t.sticky = 'yes') DESC, t.lastpost DESC {$limit}";

        $topic_rows = null;
        $can_mc     = (isset($memcached) && $memcached instanceof Memcached);
        $list_key   = "forums:{$forumid}:topics:list:v1:{$page}:{$perpage}";

        if ($can_mc) {
            $topic_rows = $memcached->get($list_key);
        }
        if ($topic_rows === false || !is_array($topic_rows)) {
            $topicsres  = sql_query($topics_sql) or sqlerr(__FILE__, __LINE__);
            $topic_rows = [];
            while ($r = mysqli_fetch_assoc($topicsres)) {
                $topic_rows[] = $r;
            }
            if ($can_mc) {
                $memcached->set($list_key, $topic_rows, 30); // 30 сек
            }
        }

        // stdhead_f("Форум :: Просмотр категории"); // оставляю как у тебя

        // отметка доступа на форум (не чаще раза в минуту)
        if (!empty($CURUSER) && !empty($CURUSER['forum_access'])) {
            $last = strtotime((string)$CURUSER['forum_access']) ?: 0;
            if (time() - $last >= 60) {
                sql_query(
                    'UPDATE users SET forum_access = ' . sqlesc(get_date_time()) . ' WHERE id = ' . sqlesc($userid)
                ) or sqlerr(__FILE__, __LINE__);
            }
        }

        $numtopics = is_array($topic_rows) ? count($topic_rows) : 0;

        if ($numtopics > 0) {
            $forum_view1 = '<a class="altlink_white" href="' . $DEFAULTBASEURL . '/forums.php?action=viewunread">Непрочитанные темы</a>' . "\n";
        } else {
            $forum_view1 = '';
        }

        if (!empty($CURUSER) && (($CURUSER['forum_com'] ?? '') === '0000-00-00 00:00:00')) {
            $forum_view2 = '<a class="altlink_white" href="' . $DEFAULTBASEURL . '/forums.php?action=newtopic&forumid=' . $forumid . '">Создать тему в этой категории</a>' . "\n";
        } else {
            // сохраняю твою логику показа: ссылка всегда видна
            $forum_view2 = '<a class="altlink_white" href="' . $DEFAULTBASEURL . '/forums.php?action=newtopic&forumid=' . $forumid . '">Создать тему в этой категории</a>' . "\n";
        }
// ========================= ШАПКА / ДО КОММЕНТА =========================
echo '<!DOCTYPE html>
<html lang="ru">
<head>
' . meta_forum((int)($forumid ?? 0)) . '
<link rel="stylesheet" type="text/css" href="js/style_forums.css" />
<link rel="search" type="application/opensearchdescription+xml" title="Muz-Tracker Форум" href="' . $DEFAULTBASEURL . '/js/forum.xml">
<script type="text/javascript" src="js/jquery.js"></script>
<script type="text/javascript" src="js/forums.js"></script>
<script type="text/javascript" src="js/swfobject.js"></script>
<script type="text/javascript" src="js/functions.js"></script>
<script type="text/javascript" src="js/tooltips.js"></script>
<title>Форум - ' . htmlspecialchars((string)$SITENAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>
</head>
<body>

<table cellpadding="0" cellspacing="0" id="main">
<tr>
<td class="main_col1"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="main_col2"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="main_col3"><img src="/pic/forumicons/clear.gif" alt="" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign="top">
<table cellpadding="0" cellspacing="0" id="header">
<tr>
<td id="logo">' . (defined('LOGO') ? LOGO : '') . '</td>

<td class="login">
  <div id="login_box"><span class="smallfont">';

$__newmessage = $newmessage ?? '';
if (!empty($CURUSER)) {
    $u_id   = (int)($CURUSER['id'] ?? 0);
    $u_name = htmlspecialchars((string)($CURUSER['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $u_acc  = htmlspecialchars((string)($CURUSER['forum_access'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<div>Здравствуйте, <a href="' . $DEFAULTBASEURL . '/userdetails.php?id=' . $u_id . '">' . $u_name . '</a></div>
          <div>Последнее обновление: <span class="time">' . $u_acc . '</span></div>';
    if (!empty($__newmessage)) {
        echo '<div>' . $__newmessage . '</div>';
    }
} else {
    echo 'для просмотра полной версии данных,
          <div>пожалуйста, <a href="' . $DEFAULTBASEURL . '/login.php">авторизуйтесь</a>.</div>
          <div>Права просмотра: Гость</div>';
}

echo '  </span></div>
</td>
</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>

<tr>
<td>&nbsp;</td>
<td>
<table cellpadding="0" cellspacing="0" id="menu_h">
<tr>
<td class="first"><a href="' . $DEFAULTBASEURL . '/index.php">Главная сайта</a></td>
<td class="shad"><a href="' . $DEFAULTBASEURL . '/browse.php">Торренты</a></td>
<td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php">Главная форума</a></td>';

if (!empty($CURUSER)) {
    echo '<td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php?action=search">Поиск</a></td>
          <td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php?action=viewunread">Непрочитанные комментарии</a></td>
          <td class="shad"><a title="Пометить все сообщения прочитанными" href="' . $DEFAULTBASEURL . '/forums.php?action=catchup">Все как прочитанное</a></td>';
}

echo '</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>

<tr>
<td>&nbsp;</td>
<td valign="top">
<table cellpadding="0" cellspacing="0" id="content_s">
<tr>
<td class="content_col1"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="content_col_left">&nbsp;</td>
<td class="content_col5"><img src="/pic/forumicons/clear.gif" alt="" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign="top">
<br />';

echo '<div class="tcat_t"><div class="tcat_r"><div class="tcat_l"><div class="tcat_tl"><div class="tcat_simple">
<table cellspacing="0" cellpadding="0"><tr><td class="tcat_name">
Данный раздел форума посвящен категории &quot;' . htmlspecialchars((string)$forumname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '&quot; <br class="tcat_clear" /> ' . htmlspecialchars((string)$description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
    ((int)get_user_class() === UC_SYSOP ? ' [<a class="altlink_white" href="forummanage.php">Создать новую категорию</a>]' : '') . '
</td></tr></table>
<br class="tcat_clear" />
</div></div></div></div></div>';

// ---------- комментарий категории (берём уже прочитанный $f_com, без лишнего SQL) ----------
$comment_f = htmlspecialchars((string)($f_com ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// ---------- карта прочитанного пользователем (кэш на 60с) ----------
$array_read = [];
if (!empty($CURUSER)) {
    $uid    = (int)($CURUSER['id'] ?? 0);
    $can_mc = (isset($memcached) && $memcached instanceof Memcached);
    $rk     = "readposts:user:{$uid}:v1";

    if ($can_mc) {
        $cached_map = $memcached->get($rk);
        if ($cached_map !== false && is_array($cached_map)) {
            $array_read = $cached_map;
        }
    }

    if (!$array_read) {
        $r = sql_query('SELECT lastpostread, topicid FROM readposts WHERE userid = ' . sqlesc($uid)) or sqlerr(__FILE__, __LINE__);
        while ($a = mysqli_fetch_assoc($r)) {
            $array_read[(int)$a['topicid']] = (int)$a['lastpostread'];
        }
        if ($can_mc) {
            $memcached->set($rk, $array_read, 60); // короткий TTL
        }
    }
}

// ========================== СПИСОК ТЕМ ==========================
if ($numtopics > 0) {

    echo '<div class="post_body" id="collapseobj_forumbit_5" style="">
<table cellspacing="0" cellpadding="0" class="forums">
<tr>
<td class="f_thead_1">Тема</td>
<td class="f_thead_2">Ответов / Просмотров</td>
<td class="f_thead_2">Автор</td>
<td class="f_thead_2">Последний</td>
</tr>';

    // у нас выше в рефакторинге есть $topic_rows; но если вдруг его нет — поддержим старый путь с $topicsres
    $iter = [];
    if (isset($topic_rows) && is_array($topic_rows)) {
        $iter = $topic_rows;
    } else {
        // fallback: считать из $topicsres
        if (!empty($topicsres)) {
            while ($__r = mysqli_fetch_assoc($topicsres)) {
                $iter[] = $__r;
            }
        }
    }

    $postsperpage = 20;
    $da = 0; // счётчик для «зебры»

    foreach ($iter as $topicarr) {
        $topicid      = (int)($topicarr['id']      ?? 0);
        $topic_userid = (int)($topicarr['userid']  ?? 0);
        $topic_views  = (int)($topicarr['views']   ?? 0);
        $views        = number_format($topic_views);
        $locked       = ((string)($topicarr['locked'] ?? 'no')  === 'yes');
        $sticky       = ((string)($topicarr['sticky'] ?? 'no')  === 'yes');
        $polls_view   = ((string)($topicarr['polls']  ?? 'no')  === 'yes')
                      ? ' <img width="13" title="Данная тема имеет опрос" src="pic/forumicons/polls.gif" alt="poll">'
                      : '';

        $posts   = (int)($topicarr['num_po'] ?? 0);
        $replies = max(0, $posts - 1);

        // разбиение по страницам темы
        $tpages = ($postsperpage > 0) ? (int)ceil($posts / $postsperpage) : 1;
        $topicpages = '';
        if ($tpages > 1) {
            $tp = [];
            for ($i = 1; $i <= $tpages; $i++) {
                $tp[] = '<a title="' . $i . ' страница" href="forums.php?action=viewtopic&topicid=' . $topicid . '&page=' . $i . '">' . $i . '</a>';
            }
            $topicpages = ' [' . implode(' ', $tp) . ']';
        }

        // ===== последний пост темы + тело первого поста (превью) =====
        // используем подзапрос для первого сообщения в теме (устраняем дубликаты)
        $lastPostSql = "
            SELECT 
                p.*,
                t.forumid,
                t.visible,
                (SELECT fp.body 
                   FROM posts fp 
                  WHERE fp.topicid = p.topicid 
                  ORDER BY fp.id ASC 
                  LIMIT 1) AS bodypost,
                u.username AS ed_username, 
                u.class    AS ed_class,
                o.username AS or_username, 
                o.class    AS or_class
            FROM posts p
            INNER JOIN topics t ON t.id = p.topicid
            LEFT JOIN users  u  ON u.id = p.userid
            LEFT JOIN users  o  ON o.id = " . sqlesc($topic_userid) . "
            WHERE p.topicid = " . sqlesc($topicid) . "
            ORDER BY p.id DESC
            LIMIT 1
        ";

        $res = sql_query($lastPostSql) or sqlerr(__FILE__, __LINE__);
        $arr = mysqli_fetch_assoc($res) ?: [];

        // -------- превью из первого поста темы --------
        $subject_f  = (string)$forumname;
        $raw_preview = (string)($arr['bodypost'] ?? '');
        // убираем BBCode в квадратных скобках, затем теги
        $combody_f = strip_tags(preg_replace("/\[((\s|.)+?)\]/is", "", $raw_preview));

        if (mb_strlen($combody_f, 'UTF-8') >= 255) {
            $combody_f = mb_substr($combody_f, 0, 255, 'UTF-8')
                       . ' <a title="К первому сообщению в этой теме" href="forums.php?action=viewtopic&topicid=' . $topicid . '">....</a>';
        }
        // безопасно в title/тексте
        $combody_f_safe = htmlspecialchars($combody_f, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // -------- данные по последнему посту --------
        $lppostid  = (int)($arr['id']    ?? 0);
        $lppostadd = (string)($arr['added'] ?? '');
        $lpuserid  = (int)($arr['userid'] ?? 0);
        $lpadded   = '<nobr>' . htmlspecialchars($lppostadd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</nobr>';

        // автор последнего поста (ed_* как у тебя)
        if (!empty($arr['ed_username']) && $lpuserid > 0) {
            $lpusername = '<a href="userdetails.php?id=' . $lpuserid . '"><b>' . get_user_class_color((int)($arr['ed_class'] ?? 0), (string)$arr['ed_username']) . '</b></a>';
        } else {
            $lpusername = 'id: ' . $lpuserid;
        }

        // автор темы (original)
        if (!empty($arr['or_username']) && $topic_userid > 0) {
            $lpauthor = '<a href="userdetails.php?id=' . $topic_userid . '"><b>' . get_user_class_color((int)($arr['or_class'] ?? 0), (string)$arr['or_username']) . '</b></a>';
        } else {
            $lpauthor = 'id: ' . $topic_userid;
        }

        // -------- логика «новое/прочитано» --------
        $read_expiry = (int)($Forum_Config['readpost_expiry'] ?? (7 * 24 * 3600)); // 7 дней в секундах
        $is_new = 0;
        if ($lppostid > 0) {
            $deadline  = get_date_time(gmtime() - $read_expiry);
            $hasUnread = (!empty($CURUSER) && isset($array_read[$topicid]) && $lppostid > (int)$array_read[$topicid]) || empty($CURUSER);
            $isFresh   = ($lppostadd > $deadline);
            $is_new    = ($hasUnread && $isFresh) ? 1 : 0;
        }

        $topicpic = ($locked ? ($is_new ? 'lockednew'   : 'locked')
                             : ($is_new ? 'unlockednew' : 'unlocked'));
        $view = ($locked
            ? ($is_new ? 'Есть новые Непрочитанные комментарии' : 'Тема заблокирована')
            : ($is_new ? 'Есть новые Непрочитанные комментарии' : 'В данной теме нет непрочитаных сообщений'));

        // заголовок темы + постраничка
        $subject_html = htmlspecialchars((string)($topicarr['subject'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject_link = '<a title="' . $subject_html . '" href="forums.php?action=viewtopic&topicid=' . $topicid . '&page=last"><b>' . format_comment_light((string)($topicarr['subject'] ?? '')) . '</b></a>';
        $subject      = ($sticky ? '<b>Важная</b>: ' : '') . $subject_link . $topicpages;

        // зебра строк
        $class = (($da % 2) === 1) ? 'f_row_off' : 'f_row_on';

        // -------- вывод строки темы --------
        echo '<tr>
<td class="' . $class . '" width="100%">
  <table>
    <tr>
      <td align="left" width="5%"><img title="' . htmlspecialchars($view, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" src="' . $forum_pic_url . $topicpic . '.gif" alt=""></td>
      <td align="left" title="' . $combody_f_safe . '">
        ' . format_comment_light($subject) . $polls_view . ' ' . (((string)($arr['visible'] ?? 'yes') === 'no') ? '[<b>Скрытая Тема</b>]' : '') . '<br />
        <small>' . $combody_f_safe . '</small>
      </td>
    </tr>
  </table>
</td>
<td class="' . $class . '" id="f60"><div class="smallfont">' . (int)$replies . ' / ' . $views . '</div></td>
<td class="' . $class . '" id="f60"><div class="smallfont">' . $lpauthor . '</div></td>
<td class="' . $class . '" id="f60"><div class="smallfont">' . $lpadded . '<br />' . $lpusername . ' </div></td>
</tr>';

        ++$da;
    }

    echo '</table></div>';
}
// ===== Администрирование категории (видно модераторам) =====
if (get_user_class() >= UC_MODERATOR) {

    // если нет постов — подтянем текст из уже загруженного $f_com
    if (empty($posts)) {
        $comment_f = htmlspecialchars((string)($f_com ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    echo '<table style="margin-top: 2px;" cellpadding="5" width="100%">
<tr><td class="colhead" align="center" colspan="2"><hr><a name="comments"></a><b><center>.::: Администрирование категории :::.</center></b></td></tr>
<form method="post" action="' . $DEFAULTBASEURL . '/forums.php?action=forum_fcom">
<input type="hidden" name="forumfid" value="' . (int)$forumid . '">
<tr><td class="a" align="center">История этой категории <b>' . htmlspecialchars((string)($subject_f ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b> и её прилегающих тем и сообщений [' . strlen($comment_f) . ']<br />
<textarea style="width:100%;" rows="6"' . (get_user_class() < UC_SYSOP ? ' readonly' : ' name="modcomment"') . '>' . $comment_f . '</textarea>
</td></tr>
<tr><td class="a" align="center"><b>Добавить заметку</b>: <textarea style="width:100%;" rows="3" name="modcomm"></textarea>
<br><input type="submit" class="btn" value="Выполнить действие — добавить заметку в категорию ' . htmlspecialchars((string)($subject_f ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">
</td></tr>
</form>
</table>';

} // конец админ-блока

// --- НИКАКИХ дополнительных } тут НЕ ДОЛЖНО быть ---
// (foreach и if ($numtopics > 0) уже закрыты раньше в блоке «список тем»)

insert_quick_jump_menu((int)$forumid);

stdfoot_f();

/* --- конец обработчика case 'viewforum' --- */
break;
} // закрываем фигурную скобку блока case 'viewforum'

// ======================================================================
// =                               viewtopic                            =
// ======================================================================
case 'viewtopic': {
    $topicid = (int)($_GET['topicid'] ?? 0);

    // page может быть числом или строкой 'last'
    $page_raw = $_GET['page'] ?? null; // обработаем ниже

    if (!is_valid_id($topicid)) {
        die('Для вас ошибка, неверный id');
    }

    $userid = (int)($CURUSER['id'] ?? 0);

    // тема + количество постов
    $res = sql_query(
        "SELECT t.*,
                (SELECT COUNT(*) FROM posts WHERE topicid = " . sqlesc($topicid) . ") AS num_com
         FROM topics t
         WHERE t.id = " . sqlesc($topicid)
    ) or sqlerr(__FILE__, __LINE__);

    $arr = mysqli_fetch_assoc($res);
    if (!$arr) {
        stderr_f('Форум ошибка', 'Не найдено сообщение');
        exit;
    }

    $t_com_arr   = (string)($arr['t_com'] ?? '');
    $locked_flag = ((string)($arr['locked'] ?? 'no') === 'yes');
    $locked      = $locked_flag ? 'Да' : 'Нет';
    $subject     = format_comment((string)($arr['subject'] ?? ''));
    $sticky      = ((string)($arr['sticky'] ?? 'no') === 'yes') ? 'Да' : 'Нет';
    $forumid     = (int)($arr['forumid'] ?? 0);
    $topic_polls = (string)($arr['polls'] ?? 'no');
    $views       = number_format((int)($arr['views'] ?? 0));
    $num_com     = number_format((int)($arr['num_com'] ?? 0));

    // +1 к просмотрам
    sql_query("UPDATE topics SET views = views + 1 WHERE id = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);

    // форум, в котором находится тема
    $res = sql_query("SELECT * FROM forums WHERE id = " . sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
    $arr_forum = mysqli_fetch_assoc($res);
    if (!$arr_forum) {
        die('Нет такого форума с таким id: ' . (int)$forumid);
    }

    $forum   = htmlspecialchars((string)($arr_forum['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $minread = (int)($arr_forum['minclassread'] ?? 0);

    // доступ к чтению
    if ($minread > (int)get_user_class() && $minread !== 0) {
        stderr_f('Ошибка прав', 'Данная категория и её сообщения недоступны к показу.');
        die;
    }

    // реальное количество постов (для pager)
    $res_cnt   = sql_query("SELECT COUNT(*) AS c FROM posts WHERE topicid = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
    $row_cnt   = mysqli_fetch_assoc($res_cnt);
    $postcount = (int)($row_cnt['c'] ?? 0);

    // Настройка пагинации: поддержка ?page=last и чисел
    $perpage = 20;
    if ($page_raw === 'last') {
        $last_page = max(1, (int)ceil($postcount / $perpage));
        $_GET['page'] = $last_page;               // чтобы pager() взял правильную страницу
    } elseif (is_numeric($page_raw)) {
        $_GET['page'] = max(1, (int)$page_raw);   // нормализуем к минимум 1
    } else {
        unset($_GET['page']);                     // без параметра — первая страница
    }

    $count = $postcount;
    [$pagertop, $pagerbottom, $limit] = pager($perpage, $count, "forums.php?action=viewtopic&topicid=" . $topicid . "&");

    // блок «новые сообщения»
    if (!empty($CURUSER)) {
        $unread      = (int)($CURUSER['unread'] ?? 0);
        $newmessage1 = $unread . ' нов' . ($unread > 1 ? 'ых' : 'ое');
        $newmessage2 = ' сообщен' . ($unread > 1 ? 'ий' : 'ие');
        if ($unread) {
            $newmessage = "<b><a href='" . $DEFAULTBASEURL . "/message.php?action=new'>У вас " . $newmessage1 . ' ' . $newmessage2 . "</a></b>";
        } else {
            $newmessage = '';
        }
    } else {
        $newmessage = '';
    }

    // ===== Шапка страницы темы (HTML5, без Quirks) =====
    echo '<!DOCTYPE html>
<html lang="ru">
<head>
' . meta_forum('', $topicid) . '
<link rel="stylesheet" type="text/css" href="js/style_forums.css" />
<link rel="search" type="application/opensearchdescription+xml" title="Muz-Tracker Форум" href="' . $DEFAULTBASEURL . '/js/forum.xml">
<script type="text/javascript" src="js/jquery.js"></script>
<script type="text/javascript" src="js/forums.js"></script>
<script type="text/javascript" src="js/swfobject.js"></script>
<script type="text/javascript" src="js/functions.js"></script>
<script type="text/javascript" src="js/tooltips.js"></script>
<title>' . strip_tags($subject) . ' :: ' . $forum . ' - ' . htmlspecialchars((string)$SITENAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>
</head>
<body>

<table cellpadding="0" cellspacing="0" id="main">
<tr>
<td class="main_col1"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="main_col2"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="main_col3"><img src="/pic/forumicons/clear.gif" alt="" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign="top">
<table cellpadding="0" cellspacing="0" id="header">
<tr>
<td id="logo">' . (defined('LOGO') ? LOGO : '') . '</td>

<td class="login">
<div id="login_box"><span class="smallfont">';

    if (!empty($CURUSER)) {
        $u_id  = (int)($CURUSER['id'] ?? 0);
        $u_n   = htmlspecialchars((string)($CURUSER['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $u_acc = htmlspecialchars((string)($CURUSER['forum_access'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<div>Здравствуйте, <a href="' . $DEFAULTBASEURL . '/userdetails.php?id=' . $u_id . '">' . $u_n . '</a></div>
<div>Последнее обновление: <span class="time">' . $u_acc . '</span></div>';
        if (!empty($newmessage)) {
            echo '<div>' . $newmessage . '</div>';
        }
    } else {
        echo 'для просмотра полной версии данных,
<div>пожалуйста, <a href="' . $DEFAULTBASEURL . '/login.php">авторизуйтесь</a>.</div>
<div>Права просмотра: Гость</div>';
    }

    echo '</span></div>
</td>
</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>

<tr>
<td>&nbsp;</td>
<td>
<table cellpadding="0" cellspacing="0" id="menu_h">
<tr>
<td class="first"><a href="' . $DEFAULTBASEURL . '/index.php">Главная сайта</a></td>
<td class="shad"><a href="' . $DEFAULTBASEURL . '/browse.php">Торренты</a></td>
<td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php">Главная форума</a></td>';

    if (!empty($CURUSER)) {
        echo '<td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php?action=search">Поиск</a></td>
<td class="shad"><a href="' . $DEFAULTBASEURL . '/forums.php?action=viewunread">Непрочитанные комментарии</a></td>
<td class="shad"><a title="Пометить все сообщения прочитанными" href="' . $DEFAULTBASEURL . '/forums.php?action=catchup">Все как прочитанное</a></td>';
    }

    echo '</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>

<tr>
<td>&nbsp;</td>
<td valign="top">
<table cellpadding="0" cellspacing="0" id="content_s">
<tr>
<td class="content_col1"><img src="/pic/forumicons/clear.gif" alt="" /></td>
<td class="content_col_left">&nbsp;</td>
<td class="content_col5"><img src="/pic/forumicons/clear.gif" alt="" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign="top">
<br />';


// ========================= ОПРОС В ТЕМЕ =========================
if ($topic_polls === 'yes') {
    if (!empty($CURUSER)) {
        echo '<script type="text/javascript" src="' . $DEFAULTBASEURL . '/js/forums_poll.core.js"></script>
<link href="' . $DEFAULTBASEURL . '/js/poll.core.css" type="text/css" rel="stylesheet" />
<script type="text/javascript">$(document).ready(function(){loadpoll(' . (int)$topicid . ');});</script>';

        echo '<div id="poll_container">
<div id="loading_poll" style="display:none"></div>
<noscript><b>Пожалуйста, включите выполнение скриптов</b></noscript>
</div><hr>';
    } else {
        echo 'Опрос виден только для авторизованных пользователей.';
    }
}

// ========================= Шапка блока темы =========================
// $subject уже отформатирован через format_comment, но title/категорию экранируем
echo '<div class="tcat_t"><div class="tcat_r"><div class="tcat_l">
<div class="tcat_tl"><div class="tcat_submenu"><span class="smallfont">
<div class="tcat_popup"><b>Просмотров</b>: ' . $views . '</div>
<div class="tcat_popup" id="threadtools"><b>Комментариев</b>: ' . $num_com . '</div>
<div class="tcat_popup"><b>Важная</b>: ' . $sticky . '</div>
<div class="tcat_popup" id="threadrating"><b>Заблокирована</b>: ' . $locked . '</div></span>';

echo '<table cellspacing="0" cellpadding="0"><tr><td class="tcat_name"><b>' . $subject . ' </b>
<br />Категория: <a name="poststop" id="poststop" href="' . $DEFAULTBASEURL . '/forums.php?action=viewforum&forumid=' . (int)$forumid . '">' . htmlspecialchars((string)$forum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>
' . (get_user_class() >= UC_MODERATOR ? '<br /><a href="' . $DEFAULTBASEURL . '/forums.php?action=edittopic&topicid=' . (int)$topicid . '">Администрирование темы</a>' : '') . '
</td></tr>
</table>
<br class="tcat_clear"/></div></div></div></div></div>';

// история мод-комментариев темы
$t_com = '<textarea style="width:100%;" rows="5" readonly>' . htmlspecialchars((string)$t_com_arr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea>';

if (get_user_class() >= UC_MODERATOR) {
    echo '<div align="center" width="100%" class="tcat_t">
<div class="spoiler-wrap" id="115"><div class="spoiler-head folded clickable">История этого топика (Тестовый режим)</div><div class="spoiler-body" style="display: none;">'
        . $t_com .
    '</div></div>
</div>';
}

// ========================= Выборка постов темы =========================
$res = sql_query("
    SELECT 
        p.*,
        u.username,
        u.class,
        u.last_access,
        u.ip,
        u.signatrue,
        u.forum_com,
        u.signature,
        u.avatar,
        u.title,
        u.enabled,
        u.warned,
        u.hiderating,
        u.uploaded,
        u.downloaded,
        u.donor,
        e.username AS ed_username,
        e.class    AS ed_class,
        (SELECT COUNT(*) FROM posts WHERE userid = p.userid) AS num_topuser
    FROM posts p
    LEFT JOIN users u ON u.id = p.userid
    LEFT JOIN users e ON e.id = p.editedby
    WHERE p.topicid = " . sqlesc($topicid) . "
    ORDER BY p.id {$limit}
") or sqlerr(__FILE__, __LINE__);

// отметка посещения форума раз в минуту
if (!empty($CURUSER) && get_date_time(gmtime() - 60) >= ($CURUSER['forum_access'] ?? '0000-00-00 00:00:00')) {
    sql_query(
        'UPDATE users SET forum_access = ' . sqlesc(get_date_time()) . ' WHERE id = ' . sqlesc((int)$CURUSER['id'])
    ) or sqlerr(__FILE__, __LINE__);
}

// сколько постов на странице
$pc = mysqli_num_rows($res);
$pn = 0;

// lastpostread для подсветки/якорей (если нужно)
$lpr = 0;
if (!empty($CURUSER)) {
    $r  = sql_query(
        'SELECT lastpostread FROM readposts WHERE userid = ' . sqlesc((int)$CURUSER['id']) .
        ' AND topicid = ' . sqlesc((int)$topicid)
    ) or sqlerr(__FILE__, __LINE__);
    $a   = mysqli_fetch_assoc($r);
    $lpr = (int)($a['lastpostread'] ?? 0);
}

echo '<div class="post_body"><div id="posts">';

$num = 1;

while ($arr = mysqli_fetch_assoc($res)) {

    ++$pn;

    $ed_username  = (string)($arr['ed_username'] ?? '');
    $ed_class     = (int)($arr['ed_class'] ?? 0);
    $postid       = (int)$arr['id'];
    $posterid     = (int)$arr['userid'];
    $added_raw    = (string)$arr['added'];
    $postername   = (string)($arr['username'] ?? '');
    $posterclass  = (int)($arr['class'] ?? 0);

    // автор блока "by"
    if ($postername === '' && $posterid !== 0) {
        $by = '<b>id</b>: ' . $posterid;
    } elseif ($posterid === 0 && $postername === '') {
        $by = '<i>Сообщение от </i><font color="gray">[<b>System</b>]</font>';
    } else {
        $by = '<a href="' . $DEFAULTBASEURL . '/userdetails.php?id=' . $posterid . '"><b>' . get_user_class_color($posterclass, $postername) . '</b></a>';
    }

    // online/offline
    $online = 'offline';
    $online_text = 'Не на форуме';
    if ($posterid !== 0 && $postername !== '') {
        if (strtotime((string)($arr['last_access'] ?? '')) > gmtime() - 600) {
            $online = 'online';
            $online_text = 'На форуме';
        }
    }

    // ratio
    $print_ratio = '---';
    if ($posterid !== 0 && $postername !== '') {
        if ((int)($arr['downloaded'] ?? 0) > 0) {
            $ratio = (float)($arr['uploaded'] ?? 0) / (float)$arr['downloaded'];
            $ratio = number_format($ratio, 2);
        } elseif ((int)($arr['uploaded'] ?? 0) > 0) {
            $ratio = 'Infinity';
        } else {
            $ratio = '---';
        }
        // была опечатка: $row["hiderating"]; используем $arr["hiderating"]
        if ((string)($arr['hiderating'] ?? 'no') === 'yes') {
            $print_ratio = '<b>+100%</b>';
        } else {
            $print_ratio = $ratio;
        }
    }

    // PM кнопка
    $cansendpm = '';
    if (!empty($CURUSER)
        && (string)($CURUSER['cansendpm'] ?? '') === 'yes'
        && (int)($CURUSER['id'] ?? 0) !== $posterid
        && $posterid !== 0
        && $postername !== ''
    ) {
        $cansendpm = ' <a href="' . $DEFAULTBASEURL . '/message.php?action=sendmessage&amp;receiver=' . $posterid . '"><img src="' . $DEFAULTBASEURL . '/pic/button_pm.gif" border="0" alt="Отправить сообщение"></a>';
    }

    // бан на форуме
    $ban = '';
    if ((string)($arr['forum_com'] ?? '0000-00-00 00:00:00') !== '0000-00-00 00:00:00' && $postername !== '') {
        $ban = '<div><b>Бан до </b>' . htmlspecialchars((string)$arr['forum_com'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    }

    // индикатор online
    $online_view = '<img src="' . $DEFAULTBASEURL . '/pic/button_' . $online . '.gif" alt="' . $online_text . '" title="' . $online_text . '" style="position: relative; top: 2px;" border="0" height="14">';

    // ссылка на конкретный пост
    $numb_view = '<a title="Число, под которым это сообщение в базе: ' . $postid . '" href="' . $DEFAULTBASEURL . '/forums.php?action=viewpost&id=' . $postid . '">Постоянная ссылка для этого сообщения [<b>' . $postid . '</b>]</a>';

    // аватар
    if (!empty($arr['avatar'])) {
        $av_src = $DEFAULTBASEURL . '/pic/avatar/' . rawurlencode((string)$arr['avatar']);
        if (!empty($CURUSER) && (int)$CURUSER['id'] === $posterid) {
            $avatar = '<a href="' . $DEFAULTBASEURL . '/my.php"><img alt="Аватар, по клику переход в настройки" title="Аватар, по клику переход в настройки" width="80" height="80" src="' . $av_src . '"/></a>';
        } else {
            $avatar = '<img width="80" height="80" src="' . $av_src . '" alt="avatar"/>';
        }
    } else {
        $avatar = '<img width="80" height="80" src="' . $DEFAULTBASEURL . '/pic/avatar/default_avatar.gif" alt="avatar"/>';
    }

    // тело поста
    $body = format_comment((string)($arr['body'] ?? ''));

    // право видеть "оригинал" (если колонка называется body_orig)
    $viworiginal = (get_user_class() >= UC_MODERATOR && !empty($arr['body_orig']));

    // якоря
    echo '<a name="' . $postid . '"></a>';
    if ($pn === $pc) {
        echo '<a name="last"></a>';
    }

    // рендер поста
    echo '<div>
<table cellpadding="0" cellspacing="0" width="100%">
<tr>
<td class="postbit_top">
<div class="postbit_head">
  <div class="normal" style="float:right">' . $numb_view . '</div>
  <div class="normal">' . normaltime($added_raw, true) . '</div>
</div>

<table cellpadding="0" cellspacing="10" width="100%">
<tr>
<td>' . $avatar . '</td>
<td nowrap="nowrap">
  <div>' . $by . ' ' . $online_view . $cansendpm . '</div>
  <div class="smallfont">' . get_user_class_name($posterclass) . ' ' . (empty($arr['title']) ? '' : '(' . htmlspecialchars((string)$arr['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')') . '<br />
  ' . ((string)($arr['donor'] ?? 'no') === 'yes' ? '<img src="' . $DEFAULTBASEURL . '/pic/star.gif" alt="Донор">' : '') .
     ((string)($arr['enabled'] ?? 'yes') === 'no' ? '<img src="' . $DEFAULTBASEURL . '/pic/disabled.gif" alt="Этот аккаунт отключен" style="margin-left: 2px">' :
      ((string)($arr['warned'] ?? 'no') === 'yes' ? '<img src="' . $DEFAULTBASEURL . '/pic/warned.gif" alt="Предупрежден" border="0">' : '')) . '
  </div>
</td>

<td width="100%">&nbsp;</td>
<td valign="top" nowrap="nowrap" class="n_postbit_info">

<table cellpadding="0" cellspacing="10" width="100%">
<tr>
<td valign="top" nowrap="nowrap"><div class="smallfont"></div></td>
<td valign="top" nowrap="nowrap">
  <div class="smallfont">
    <div><b>Рейтинг</b>: ' . $print_ratio . '</div>
    <div><b>Залил</b>: ' . mksize((int)($arr['uploaded'] ?? 0)) . ' </div>
    <div><b>Скачал</b>: ' . mksize((int)($arr['downloaded'] ?? 0)) . '</div>
    ' . $ban . '
    ' . (!empty($CURUSER) ? '<b>Сообщений на форуме</b>: <a href="' . $DEFAULTBASEURL . '/forums.php?action=search_post&userid=' . $posterid . '" title="Поиск всех сообщений у ' . htmlspecialchars($postername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . (int)($arr['num_topuser'] ?? 0) . '</a>' : '') . '
  </div>
</td></tr></table>

<img src="/pic/forumicons/clear.gif" alt="" width="225" height="1" border="0" />
</td></tr>
</table>
</td>
</tr>
<tr>
<td class="alt1">
<hr size="1" />
<div class="pad_12">
  <div class="img_rsz">' . $body . '</div>

' . ((is_valid_id((int)($arr['editedby'] ?? 0)) && !empty($arr['editedby']))
    ? '<hr>
<div class="post_edited smallfont">
<em>Последний раз редактировалось ' . (get_user_class() >= UC_MODERATOR
        ? '<a href="' . $DEFAULTBASEURL . '/userdetails.php?id=' . (int)$arr['editedby'] . '"><b> ' . get_user_class_color($ed_class, $ed_username) . ' </b></a>'
        : '') . ' в <span class="time">' . htmlspecialchars((string)($arr['editedat'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>. ' .
        ($viworiginal ? '<a href="' . $DEFAULTBASEURL . '/forums.php?action=viewpost&id=' . $postid . '&ori">К Оригинальному сообщению.</a>' : '') . '</em>
</div>'
    : '') . '

  <div style="margin-top: 10px" align="right">
  ' . (((string)($arr['signatrue'] ?? 'no') === 'yes' && !empty($arr['signature'])) ? '  <span class="smallfont">' . format_comment((string)$arr['signature']) . '</span>' : '') . '
  </div>
</div>
</td>
</tr>
</table>

<div class="pad_12 alt1" style="border-top: 1px solid #ccc;">
<table cellpadding="0" cellspacing="0" width="100%">
<tr>';


// ===== КНОПКИ ДЕЙСТВИЙ ПОД ПОСТОМ =====
if (get_user_class() > UC_MODERATOR) {
    echo '[<a title="Удалить сообщение (тему — если первое сообщение) и забанить пользователя" href="'
        . $DEFAULTBASEURL . '/forums.php?action=banned&userid=' . (int)$posterid . '&postid=' . (int)$postid
        . '"><b>СПАМ</b></a>] ';
}

if (!empty($posterid)) {
    echo '[<a href="' . $DEFAULTBASEURL . '/forums.php?action=search_post&userid=' . (int)$posterid . '"><b>Найти все сообщения</b></a>] ';
}

if (!empty($CURUSER)) {
    echo (!empty($posterid) ? '[<a href="' . $DEFAULTBASEURL . '/userdetails.php?id=' . (int)$posterid . '"><b>Профиль</b></a>] ' : '');
}

if (!empty($CURUSER) && (int)$CURUSER['id'] !== (int)$posterid) {
    echo (!empty($posterid) ? '[<a href="' . $DEFAULTBASEURL . '/message.php?receiver=' . (int)$posterid . '&action=sendmessage"><b>Послать Сообщение</b></a>] ' : '');
}

if (!empty($CURUSER) && (int)$CURUSER['id'] !== (int)$posterid) {
    echo ($posterid !== 0 && !empty($postername) && (($CURUSER['forum_com'] ?? '') === '0000-00-00 00:00:00')
        ? '[<a href="' . $DEFAULTBASEURL . '/forums.php?action=quotepost&topicid=' . (int)$topicid . '&postid=' . (int)$postid . '"><b>Цитировать</b></a>] '
        : '');
}

if (get_user_class() >= UC_MODERATOR) {
    $ip_title = 'Искать этот IP адрес в базе через административный поиск';
    $ip_val   = htmlspecialchars((string)($arr['ip'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($ip_val !== '') {
        echo '[<a title="' . $ip_title . '" href="' . $DEFAULTBASEURL . '/usersearch.php?ip=' . rawurlencode($ip_val) . '"><b>' . $ip_val . '</b></a>] ';
    }
}

if (((($CURUSER['forum_com'] ?? '') === '0000-00-00 00:00:00') && (int)$CURUSER['id'] === (int)$posterid) || get_user_class() >= UC_MODERATOR) {
    echo '[<a href="' . $DEFAULTBASEURL . '/forums.php?action=editpost&postid=' . (int)$postid . '"><b>Редактировать</b></a>] ';
}

if (get_user_class() >= UC_MODERATOR || (!empty($CURUSER) && (int)$CURUSER['id'] === (int)$posterid)) {
    echo '[<a href="' . $DEFAULTBASEURL . '/forums.php?action=deletepost&postid=' . (int)$postid . '"><b>Удалить</b></a>] ';
}

echo '</span></td>';
echo '</tr></table></div></div>';
echo '<div id="lastpost"></div></div></div>';

++$num;
} // end while ($arr = mysqli_fetch_assoc($res))


// ===== ОТМЕТКА ПРОЧИТАННОГО ДЛЯ ПОЛЬЗОВАТЕЛЯ =====
if (!empty($CURUSER)) {
    // $postid — id последнего поста, проставленный в цикле
    if (!empty($postid) && (int)$postid > (int)$lpr) {
        $uid = (int)$CURUSER['id'];
        // обновим/вставим lastpostread
        if (!empty($lpr)) {
            sql_query(
                'UPDATE readposts 
                 SET lastpostread = ' . sqlesc((int)$postid) . ' 
                 WHERE userid = ' . sqlesc($uid) . ' AND topicid = ' . sqlesc((int)$topicid)
            ) or sqlerr(__FILE__, __LINE__);
        } else {
            sql_query(
                'INSERT INTO readposts (userid, topicid, lastpostread) 
                 VALUES (' . sqlesc($uid) . ', ' . sqlesc((int)$topicid) . ', ' . sqlesc((int)$postid) . ')'
            ) or sqlerr(__FILE__, __LINE__);
        }
    }
}


// ===== НИЖНЯЯ ПАНЕЛЬ: ПАГИНАЦИЯ =====
echo '<div class="tcat_b"><div class="tcat_bl"><div class="tcat_br"></div></div></div><br />' . $pagerbottom . '<br />';

// ===== БЫСТРЫЙ ОТВЕТ =====
if (!empty($CURUSER) && (($CURUSER['forum_com'] ?? '') === '0000-00-00 00:00:00')) {

    echo '<br />
<div class="tcat_t"><div class="tcat_r"><div class="tcat_l"><div class="tcat_tl"><div class="tcat_simple">
<a style="float:right" href="#top" onclick="return toggle_collapse(\'forumbit_5\')">
<img id="collapseimg_forumbit_5" src="nulled_v4/buttons/collapse_tcat.gif" alt="" class="collapse" />
</a>

<div align="center"><a name="comments"></a><b>.::: Добавить сообщение к теме :::.</b></div>

<br class="tcat_clear" />
</div></div></div></div></div>
<div class="post_body" id="collapseobj_forumbit_5" align="center" style="">
<table cellspacing="0" cellpadding="0" class="forums">';

    echo '<div align="center"><form name="comment" method="post" action="' . $DEFAULTBASEURL . '/forums.php?action=post"></div>';

    echo '<center><table border="0"><tr><td class="clear">';
    echo '<div align="center">' . textbbcode('comment', 'body', '', 1) . '</div>';

    echo '</td></tr><tr><td align="center" class="a" colspan="2">';
    echo '<label><input type="checkbox" title="Убрать весь BB код из текста" name="nobb" value="1">nobb</label> ';
    echo '<label><input type="checkbox" title="Добавить к фото ссылкам — тег [img]" name="addurl" value="1">[img]</label><br />';

    echo '<input type="hidden" name="topicid" value="' . (int)$topicid . '"/>';
    echo '<input type="submit" name="post" title="CTRL+ENTER разместить сообщение" class="btn" value="Разместить сообщение" />';
    echo '</form>';

    echo '</table></div>
<div class="off"><div class="tcat_b"><div class="tcat_bl"><div class="tcat_br"></div></div></div>
</div><br />';
}

insert_quick_jump_menu((int)$forumid, $CURUSER ?? []);

stdfoot_f();
}
exit();
break;


// ======================================================================
// =                               banned                               =
// ======================================================================
case 'banned': {

    // только ранг выше модератора (как у тебя было: > UC_MODERATOR)
    if (get_user_class() <= UC_MODERATOR) {
        stderr_f("Ошибка прав", "Недостаточно прав для совершения бана над этим пользователем.");
        die;
    }

    $postid = (int)($_GET["postid"] ?? 0);

    $posted = sql_query("SELECT topicid FROM posts WHERE id = " . sqlesc($postid)) or sqlerr(__FILE__, __LINE__);
    $postp1 = mysqli_fetch_assoc($posted);
    if (!$postp1) {
        stderr_f("Ошибка", "Сообщение не найдено.");
        die;
    }
    $topicid = (int)$postp1["topicid"];

    $userid = (int)($_GET["userid"] ?? 0);

    if (!empty($userid)) {
        $num_post1 = sql_query("
            SELECT 
                COUNT(*) AS usercount,
               (SELECT COUNT(*) FROM topics WHERE userid = " . sqlesc($userid) . ") AS usertopics,
               (SELECT COUNT(*) FROM posts  WHERE topicid = " . sqlesc($topicid) . " AND userid = " . sqlesc($userid) . ") AS usertpost
            FROM posts 
     WHERE userid = " . sqlesc($userid)
) or sqlerr(__FILE__, __LINE__);
        $num_p1    = mysqli_fetch_assoc($num_post1) ?: ['usercount' => 0, 'usertopics' => 0, 'usertpost' => 0];

        $num_count  = number_format((int)$num_p1["usercount"]);
        $num_count2 = number_format((int)$num_p1["usertopics"]);
        $num_count3 = number_format((int)$num_p1["usertpost"]);
    }

    // ===== форма отправлена =====
    if (isset($_POST["userid"])) {

        $userid = (int)($_POST["userid"] ?? 0);

        $res = sql_query("SELECT id, username, avatar, ip, email, class FROM users WHERE id = " . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
        $arr = mysqli_fetch_assoc($res);

        if (!$arr) {
            stderr_f("Ошибка", "Пользователь не найден.");
            die;
        }

        // нельзя банить равных/выше и себя
        if ((int)$arr["class"] >= (int)get_user_class() || (int)$arr["id"] === (int)$CURUSER["id"]) {
            stderr_f("Ошибка прав", "Недостаточно прав для совершения бана над этим пользователем.");
            die;
        }

        $postid = (int)($_POST["postid"] ?? 0);
        $posted = sql_query("SELECT topicid FROM posts WHERE id = " . sqlesc($postid)) or sqlerr(__FILE__, __LINE__);
        $postp1 = mysqli_fetch_assoc($posted);
        if (!$postp1) {
            stderr_f("Ошибка", "Сообщение не найдено.");
            die;
        }
        $topicid = (int)$postp1["topicid"];

        if (empty($userid)) {
            stderr_f("Ошибка", "Пользователя с таким id нет.");
            die;
        }

        // --- удаления ---
        $dellmsg = (int)($_POST["dellmsg"] ?? 0);
        if (!empty($dellmsg)) {
            // удалить сообщения пользователя в теме; если пост единственный — удалить всю тему
            $res4 = sql_query("
                SELECT id AS first, 
                       (SELECT COUNT(*) FROM posts WHERE topicid = " . sqlesc($topicid) . ") AS count
                FROM posts 
                WHERE topicid = " . sqlesc($topicid) . " 
                ORDER BY id ASC 
                LIMIT 1
            ") or sqlerr(__FILE__, __LINE__);
            $arr4 = mysqli_fetch_assoc($res4) ?: ['first' => 0, 'count' => 0];

            if ((int)$arr4["count"] > 1 && (int)$arr4["first"] !== (int)$postid) {
                // удаляем только сообщения пользователя в теме
                sql_query("DELETE FROM posts WHERE userid = " . sqlesc($userid) . " AND topicid = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
            } else {
                // удаляем всю тему
                sql_query("DELETE FROM posts  WHERE topicid = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM topics WHERE id      = " . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
            }
        }

        $dellall = (int)($_POST["dellall"] ?? 0);
        if (!empty($dellall)) {
            // удалить все темы пользователя и все его посты
            $res2 = sql_query("SELECT id FROM topics WHERE userid = " . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            while ($arr2 = mysqli_fetch_assoc($res2)) {
                sql_query("DELETE FROM posts  WHERE topicid = " . sqlesc((int)$arr2["id"])) or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM topics WHERE id      = " . sqlesc((int)$arr2["id"])) or sqlerr(__FILE__, __LINE__);
            }
            sql_query("DELETE FROM posts WHERE userid = " . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
        }

        // --- баны ---
        $bansact = (int)($_POST["bansact"] ?? 0);
        if (!empty($bansact)) {

            // ban по IP (если выбран не «только email», т.е. != 2)
            if ($bansact !== 2 && !empty($arr["ip"])) {
                $true_ban = true;

                $first = trim((string)$arr["ip"]);
                $last  = trim((string)$arr["ip"]);
                $firstL = ip2long($first);
                $lastL  = ip2long($last);

                if (!is_numeric($firstL) || !is_numeric($lastL)) {
                    $true_ban = false;
                }

                // уже есть пересечение?
                $ip_bans = get_row_count("bans", "WHERE " . sqlesc($firstL) . " >= first AND " . sqlesc($lastL) . " <= last");

                if ($firstL == -1 || $lastL == -1 || !empty($ip_bans)) {
                    $true_ban = false;
                }

                // защита от широких банов
                $allusers  = get_row_count("users");
                $banua     = get_row_count("users", "WHERE ip>=" . sqlesc(long2ip($firstL)) . " AND ip<=" . sqlesc(long2ip($lastL)) . " AND class >=" . UC_MODERATOR);
                $banuaall  = get_row_count("users", "WHERE ip>=" . sqlesc(long2ip($firstL)) . " AND ip<=" . sqlesc(long2ip($lastL)));

                if (!empty($banua) || (int)$banuaall > max(1, (int)$allusers / 10)) {
                    $true_ban = false;
                }

                if ($true_ban === true) {
                    sql_query("
                        INSERT INTO bans (added, addedby, first, last, bans_time, comment)
                        VALUES (" . sqlesc(get_date_time()) . ", " . sqlesc((int)$CURUSER["id"]) . ", " . sqlesc($firstL) . ", " . sqlesc($lastL) . ", " . sqlesc("0000-00-00 00:00:00") . ", " . sqlesc("Спамер форум: (" . htmlspecialchars((string)$arr["username"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")") . ")
                    ") or sqlerr(__FILE__, __LINE__);

                    write_log(
                        "IP адрес" . (long2ip($firstL) === long2ip($lastL)
                            ? " " . long2ip($firstL) . " был забанен"
                            : "а с " . long2ip($firstL) . " по " . long2ip($lastL) . " были забанены") . " пользователем " . $CURUSER["username"] . ".",
                        "ff3a3a",
                        "bans"
                    );
                }
            }

            // ban по email (если выбран не «только IP», т.е. != 1)
            if ($bansact !== 1 && !empty($arr["email"])) {

                $bmail = get_row_count("bannedemails", "WHERE email = " . sqlesc($arr["email"]));

                if (empty($bmail)) {
                    sql_query("
                        INSERT IGNORE INTO bannedemails (added, addedby, comment, email)
                        VALUES (" . sqlesc(get_date_time()) . ", " . sqlesc((int)$CURUSER["id"]) . ", " . sqlesc("Спамер форум: (" . htmlspecialchars((string)$arr["username"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")") . ", " . sqlesc($arr["email"]) . ")
                    ") or sqlerr(__FILE__, __LINE__);
                }
            }
        }
// --- удаление пользователя или отключение (по флагу actban) ---
$actban = (int)($_POST["actban"] ?? 0);

/// удаление сообщения и темы пользователя
if (!empty($actban)) {

    // каскадные удаления по пользователю
    sql_query("DELETE FROM cheaters    WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM users       WHERE id       = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM messages    WHERE receiver = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM karma       WHERE user     = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM friends     WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM friends     WHERE friendid = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM bookmarks   WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM invites     WHERE inviter  = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM peers       WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM readposts   WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM report      WHERE userid   = " . sqlesc((int)$arr["id"]) . " OR usertid = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM simpaty     WHERE fromuserid = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM pollanswers WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM shoutbox    WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM ratings     WHERE user     = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM snatched    WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM thanks      WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM checkcomm   WHERE userid   = " . sqlesc((int)$arr["id"])) or sqlerr(__FILE__, __LINE__);

    if (!empty($arr["avatar"])) {
        @unlink(ROOT_PATH . "pic/avatar/" . $arr["avatar"]);
    }
    @unlink(ROOT_PATH . "cache/monitoring_" . (int)$arr["id"] . ".txt");

    if (!empty($arr["username"])) {
        write_log(
            "Пользователь " . $arr["username"] . " (" . $arr["email"] . ") был удален пользователем " . $CURUSER["username"] . ". Причина: Спамер форум.",
            "590000",
            "bans"
        );
    }

} else {
    // мягкий бан: отключение аккаунта + запись в modcomment
    $modcomment = gmdate("Y-m-d") . " - Отключен пользователем " . $CURUSER['username'] . ".\nПричина: Спамер форум\n";
    sql_query(
        "UPDATE users 
         SET modcomment = CONCAT_WS('', " . sqlesc($modcomment) . ", modcomment), 
             enabled    = 'no'  
         WHERE id = " . sqlesc((int)$arr["id"])
    ) or sqlerr(__FILE__, __LINE__);
}

unsql_cache("bans_first_last");
unlinks();

header("Location: " . $DEFAULTBASEURL . "/forums.php");
die;
} // конец if (isset($_POST["userid"]))

// ===== форма выбора бана =====
if (!is_valid_id((int)$userid)) {
    header("Location: " . $DEFAULTBASEURL . "/forums.php");
    die;
}

if (get_user_class() < UC_MODERATOR) {
    stderr_f("Ошибка", "Нет доступа.");
    die;
}

stdhead_f("Выбор бана по спаму");

echo '<table style="margin-top: 2px;" cellpadding="5" width="100%">
<tr><td class="colhead" align="center" colspan="2">Выберите действие для бана</td></tr>

<form method="post" action="' . $DEFAULTBASEURL . '/forums.php?action=banned">
<tr><td class="a"><b>Удалить все сообщения из темы</b>: 
  <label><input type="radio" name="dellmsg" value="1" checked> Удалить</label>
  <label><input type="radio" name="dellmsg" value="0"> Оставить</label> (сообщений: ' . $num_count3 . ')</td></tr>

<tr><td class="a"><b>Удалить все сообщения и темы пользователя</b>: 
  <label><input type="radio" name="dellall" value="1" checked> Удалить</label> 
  <label><input type="radio" name="dellall" value="0"> Оставить</label> (сообщений: ' . $num_count . ', тем: ' . $num_count2 . ')</td></tr>

<tr><td class="a">
  <b>Дополнительно выполнить</b>: 
  <select name="bansact">
     <option value="0">выбрать из списка</option>
     <option value="1">Забанить только по ip</option>
     <option value="2">Забанить только по почте</option>
     <option value="3" selected>Забанить по ip и почте</option>
  </select>
</td></tr>

<tr><td class="a"><b>Тип бана</b>: 
  <label><input type="radio" name="actban" value="1">Удалить полностью</label> 
  <label><input type="radio" name="actban" value="0" checked>Не удалять, но отключить</label></td></tr>

<tr><td align="center" colspan="2">
  <input type="hidden" name="userid" value="' . (int)$userid . '"/>
  <input type="hidden" name="postid" value="' . (int)$postid . '"/>
  <input type="submit" class="btn" value="Применить" />
</td></tr></form></table><br />';

stdfoot_f();
die;

}
exit();
break;


// ======================================================================
// =                               reply / quotepost                    =
// ======================================================================
case 'reply':
case 'quotepost': {

    if (($CURUSER["forum_com"] ?? '') !== "0000-00-00 00:00:00") {
        if (!empty($CURUSER)) {
            header("Refresh: 15; url=" . $DEFAULTBASEURL . "/forums.php");
            stderr_f("Успешно автопереход через 15 сек", "Вам запрещено писать/создавать/цитировать до: " . $CURUSER["forum_com"] . ". Доступ запрещён.");
            die;
        } else {
            stderr_f("Ошибка данных", "Вам запрещено писать/создавать/цитировать сообщение на форуме, пока не авторизовались. Доступ запрещён.");
            die;
        }
    }

    if ($action === "reply")  {
        $topicid = (int)($_GET["topicid"] ?? 0);
        if (!is_valid_id($topicid)) {
            header("Location: " . $DEFAULTBASEURL . "/forums.php");
            die;
        }
        stdhead_f("Создание ответа");
        begin_main_frame();
        insert_compose_frame($topicid, false);
        end_main_frame();
        stdfoot_f();
        die;
    }

    if ($action === "quotepost") {
        $topicid = (int)($_GET["topicid"] ?? 0);
        if (!is_valid_id($topicid)) {
            header("Location: " . $DEFAULTBASEURL . "/forums.php");
            die;
        }
        stdhead_f("Создание ответа");
        insert_compose_frame($topicid, false, true);
        stdfoot_f();
        die;
    }
}
exit();
break;

// ======================================================================
// =                               post                                 =
// ======================================================================
case 'post': {

    // ---- базовые: должен быть залогинен ----
    if (empty($CURUSER) || empty($CURUSER['id'])) {
        stderr_f('Ошибка данных', 'Чтобы написать сообщение, авторизуйтесь.');
        break;
    }

    $uClass        = (int)get_user_class();
    $isStaffBypass = ($uClass >= UC_MODERATOR); // при желании поставь UC_SYSOP

    // ---- форум-бан (forum_com) ----
    $blockedUntilStr = (string)($CURUSER['forum_com'] ?? '0000-00-00 00:00:00');
    $blockedUntilTs  = ($blockedUntilStr && $blockedUntilStr !== '0000-00-00 00:00:00')
        ? strtotime($blockedUntilStr) : 0;

    if (!$isStaffBypass && $blockedUntilTs && $blockedUntilTs > time()) {
        header('Refresh: 15; url=' . $DEFAULTBASEURL . '/forums.php');
        stderr_f(
            'Успешно автопереход через 15 сек',
            'Вам запрещено писать/создавать/цитировать до: ' . htmlspecialchars($blockedUntilStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '. Доступ запрещён.'
        );
        break;
    }

    // ---- антиспам-триггер (НЕ действует на модераторов+) ----
    if (
        !$isStaffBypass &&
        !empty($Forum_Config['anti_spam']) &&
        empty($CURUSER['downloaded']) &&
        (int)$CURUSER['uploaded'] <= 10737418240 && // 10 ГБ
        $uClass < UC_VIP
    ) {
        if (stristr((string)$CURUSER['usercomment'], 'попытка два на форуме') === false) {
            $usercomment = get_date_time() . " - возможно спамер (попытка два на форуме).\n" . (string)$CURUSER['usercomment'];
            sql_query('UPDATE users SET usercomment = ' . sqlesc($usercomment) . ' WHERE id = ' . sqlesc((int)$CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
        }
        stderr_f('Антиспам предупреждение', 'В доступе отказано. Вы зарегистрировались недавно → ни качали, ни отдавали, ни сидировали ничего, пожалуйста повторите попытку позже.');
        break;
    }

    // входные параметры
    $forumid = (int)($_POST['forumid'] ?? 0); // для НОВОЙ темы
    $topicid = (int)($_POST['topicid'] ?? 0); // для ОТВЕТА в существующую тему

    // должно быть валидно ХОТЯ БЫ одно из значений
    if (!is_valid_id($forumid) && !is_valid_id($topicid)) {
        stderr_f('Ошибка', 'Неверный id.');
        break;
    }

    // определяем режим: новая тема или ответ в тему
    $newtopic = is_valid_id($forumid) && !is_valid_id($topicid);

    // заголовок обязателен только для новой темы
    if ($newtopic) {
        $subject = htmlspecialchars_uni(strip_tags((string)($_POST['subject'] ?? '')));
        if ($subject === '') {
            stderr_f('Ошибка', 'Вы не ввели тему сообщения.');
            break;
        }
        if (mb_strlen($subject, 'UTF-8') > (int)$maxsubjectlength) {
            stderr_f('Ошибка', 'Тема сообщения имеет лимит — ' . (int)$maxsubjectlength . ' символов.');
            break;
        }
    } else {
        // для ответа получим forumid по topicid
        $forumid = (int)(get_topic_forum($topicid) ?: 0);
        if (!$forumid) {
            stderr_f('Ошибка', 'Нет такой категории.');
            break;
        }
    }

    // уровни доступа
    $acc = get_forum_access_levels($forumid) or die('Нет такой категории');
    if ($uClass < (int)$acc['write'] || ($newtopic && $uClass < (int)$acc['create'])) {
        stderr_f('Ошибка', 'В доступе отказано.');
        break;
    }

    // тело сообщения
    $body = htmlspecialchars_uni((string)($_POST['body'] ?? ''));
    if ($body === '') {
        stderr_f('Ошибка', 'Не ввели сообщение.');
        break;
    }

    // опции обработки текста
    if (!empty($_POST['nobb'])) {
        $body = preg_replace("/\[((\s|.)+?)\]/is", '', $body);
    }
    if (!empty($_POST['addurl'])) {
        $body = preg_replace("/(https?:\/\/[^\s'\"<>]+?\.(?:jpg|jpeg|gif|png))/i", "[img]\\1[/img]", $body);
    }

    // ——— антиспам по количеству ссылок (не для staff) ———
    if (!$isStaffBypass) {
        @preg_match_all("/\[url=(https?:\/\/[^()<>\s]+?)\]((\s|.)+?)\[\/url\]/i", $body, $s1);
        $numlinksin  = is_array($s1[0] ?? null) ? count($s1[0]) : 0;
        $numlinksout = is_array($s1[0] ?? null) ? count(array_unique($s1[0])) : 0;

        if ($numlinksin !== $numlinksout && $numlinksin >= 2 && stristr((string)$CURUSER['usercomment'], 'спамер') !== false) {
            $modcomment   = get_date_time() . " - Отключен по причине спамер (ВС $numlinksin, реклама $numlinksout " . ($numlinksout == 1 ? 'сайта' : 'сайтов') . ").\n" . (string)$CURUSER['usercomment'];
            $forumbanutil = get_date_time(gmtime() + 4 * 604800); // 4 недели
            $forum_dur    = '4 недели';
            $modcomment   = gmdate('Y-m-d') . " - Форум бан на $forum_dur от SYSTEM (антиспам).\n" . $modcomment;

            sql_query(
                'UPDATE users 
                 SET usercomment = CONCAT_WS(\'\', ' . sqlesc($modcomment) . ', usercomment),
                     forum_com   = ' . sqlesc($forumbanutil) . ' 
                 WHERE id = ' . sqlesc((int)$CURUSER['id'])
            ) or sqlerr(__FILE__, __LINE__);

            $DEFURL = htmlspecialchars_uni($_SERVER['HTTP_HOST'] ?? '');

            $subj = 'Сработало правило спама на форуме';
            $all  = "Пытался написать сообщение на форуме: http://$DEFURL/userdetails.php?id={$CURUSER['id']}  - {$CURUSER['username']} ({$CURUSER['email']})
Истории пользователя: {$CURUSER['usercomment']} {$CURUSER['modcomment']}
Сообщение: (ВС $numlinksin, реклама $numlinksout " . ($numlinksout == 1 ? 'сайта' : 'сайтов') . ")
/////////////////////////////////////////////////
$body
/////////////////////////////////////////////////";

            if (!empty($war_email)) {
                @sent_mail($war_email, $SITENAME, $SITEEMAIL, $subj, $all, false);
            }

            stderr_f('Антиспам предупреждение', 'Сработало правило антиспама, вы забанены на форуме. <br />Если считаете это ошибкой, пожалуйста напишите администрации сайта.');
            break;
        }
    }
    // ——— конец антиспама ———

    $userid = (int)$CURUSER['id'];

    // если создаём новую тему — сначала row в topics
    if ($newtopic) {
        $subject_esc = sqlesc($subject);
        sql_query(
            "INSERT INTO topics (userid, forumid, subject, t_com)
             VALUES (" . sqlesc($userid) . ", " . sqlesc($forumid) . ", $subject_esc, " . sqlesc('') . ")"
        ) or sqlerr(__FILE__, __LINE__);

        // получить id вставленной темы
        global $mysqli;
        $topicid = (int)$mysqli->insert_id;
        if ($topicid <= 0) {
            stderr_f('Ошибка', 'Не удалось получить идентификатор темы.');
            break;
        }
    } else {
        // проверим тему и её состояние
        $res = sql_query('SELECT * FROM topics WHERE id = ' . sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
        $arr = mysqli_fetch_assoc($res) or die('Topic id n/a');

        if ((string)($arr['locked'] ?? 'no') === 'yes' && $uClass < UC_MODERATOR) {
            stderr_f('Ошибка', 'Эта тема заблокирована.');
            break;
        }

        $forumid = (int)($arr['forumid'] ?? 0);
    }

    // вставляем сам пост
    $added     = get_date_time();
$body_esc  = sqlesc($body);
$bodyo_esc = sqlesc($body); // исходник (если хочешь — сохрани «сырой» до фильтров)

sql_query(
    'INSERT INTO posts (topicid, forumid, userid, added, body, body_orig)
     VALUES (' . sqlesc((int)$topicid) . ', ' . sqlesc((int)$forumid) . ', ' . sqlesc($userid) . ', ' . sqlesc($added) . ', ' . $body_esc . ', ' . $bodyo_esc . ')'
) or sqlerr(__FILE__, __LINE__);


    global $mysqli;
    $postid = (int)$mysqli->insert_id;
    if ($postid <= 0) {
        die('Post id n/a');
    }

    // обновим lastpost у темы и почистим хвосты
    update_topic_last_post((int)$topicid, $added);
    unlinks();

    // редирект
    if ($newtopic) {
        header('Refresh: 5; url=' . $DEFAULTBASEURL . '/forums.php?action=viewtopic&topicid=' . (int)$topicid . '&page=last');
        stderr_f(
            'Успешно автопереход через 5 сек',
            'Сообщение добавлено, сейчас будет переадресация к последнему сообщению.<br /> Нажмите <a href="' . $DEFAULTBASEURL . '/forums.php?action=viewtopic&topicid=' . (int)$topicid . '&page=last">ЗДЕСЬ</a>, если не хотите ждать.'
        );
        die('newtopic');
    } else {
        header('Refresh: 5; url=' . $DEFAULTBASEURL . '/forums.php?action=viewtopic&topicid=' . (int)$topicid . '&page=last#' . (int)$postid);
        stderr_f(
            'Успешно автопереход через 5 сек',
            'Сообщение было добавлено. Время добавления: ' . $added . '<br /> Нажмите <a href="' . $DEFAULTBASEURL . '/forums.php?action=viewtopic&topicid=' . (int)$topicid . '&page=last#' . (int)$postid . '">ЗДЕСЬ</a>, если не хотите ждать.'
        );
        die('catch_up');
    }
}
break;


 

// ======================================================================
// =                              newtopic                              =
// ======================================================================
case 'newtopic': {

    // --- базовые проверки ---
    $forumid = (int)($_GET['forumid'] ?? 0);
    if (!is_valid_id($forumid)) {
        header("Location: {$DEFAULTBASEURL}/forums.php");
        break;
    }

    if (empty($CURUSER['id'])) {
        stderr_f('Ошибка доступа', 'Чтобы создать тему, авторизуйтесь.');
        break;
    }

    // --- права на раздел ---
    $acl = get_forum_access_levels($forumid); // ожидается ['read','write','create']
    if ($acl === false) {
        stderr_f('Ошибка', 'Категория не найдена или недоступна.');
        break;
    }

    $uClass = (int)get_user_class();

    // 1) Чтение раздела
    if ($uClass < (int)$acl['read']) {
        header("Location: {$DEFAULTBASEURL}/forums.php");
        break;
    }

    // 2) Право СОЗДАВАТЬ темы
    if ($uClass < (int)$acl['create']) {
        stderr_f('Нет прав', 'У вас недостаточно прав для создания темы в этой категории.');
        break;
    }

    // --- блокировки на форуме (форум-бан до даты) ---
    // Поле может быть '0000-00-00 00:00:00' либо отсутствовать
    $blockedUntilStr = (string)($CURUSER['forum_com'] ?? '0000-00-00 00:00:00');
    $blockedUntilTs  = ($blockedUntilStr && $blockedUntilStr !== '0000-00-00 00:00:00')
        ? strtotime($blockedUntilStr) : 0;

    // Модераторы и выше проходят без блокировки
    $isStaffBypass = ($uClass >= UC_MODERATOR);

    if (!$isStaffBypass && $blockedUntilTs && $blockedUntilTs > time()) {
        $human = htmlspecialchars($blockedUntilStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        header("Refresh: 15; url={$DEFAULTBASEURL}/forums.php");
        stderr_f('Успешно: автопереход через 15 сек',
                 'Вам запрещено писать/создавать/цитировать сообщения на форуме до: <b>' . $human . '</b>. В доступе отказано.');
        break;
    }

    // --- всё ок: рисуем форму создания темы ---
    stdhead_f('Новая тема');
    begin_main_frame();
    // Параметры insert_compose_frame:
    // 1) $forumid — куда создаём;
    // 2) true — создаём НОВУЮ тему (а не ответ);
    // 3) false — без цитаты по умолчанию
    insert_compose_frame($forumid, true, false);
    end_main_frame();
    stdfoot_f();

    break;
}


case 'search_post':  {
///////////////поиск постов///////////////
  
    $userid = (int)$_GET["userid"];

   if (!is_valid_id($userid))
     header("Location: ".$DEFAULTBASEURL."/forums.php");
     
/*
    if ($CURUSER["forum_com"]<>"0000-00-00 00:00:00"){
   	
    	if ($CURUSER){
     header("Refresh: 15; url=".$DEFAULTBASEURL."/forums.php");
		stderr_f("Успешно автопереход через 15 сек", "Вам запрещенно писать или создавать или цитировать любое сообщение на форуме до: ".$CURUSER["forum_com"].". В доступе отказано.");
		die;
		}		else		{
		stderr_f("Ошибка данных", "Вам запрещенно писать или создавать или цитировать любое сообщение на форуме пока не авторизовались. В доступе отказано.");
		die;	
		}
      }
      */

	stdhead_f("Поиск сообщений");

   $res = sql_query("SELECT COUNT(*) AS count, u.username FROM posts 
   LEFT JOIN users AS u ON u.id=userid
   WHERE userid=".sqlesc($userid)." GROUP BY userid") or sqlerr(__FILE__, __LINE__);
   $arr = mysqli_fetch_assoc($res);
   $sear=$arr["username"];

	$perpage = 25;
    $count  = $arr["count"];  

list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "forums.php?action=search_post&userid=".$userid."&");


$res = sql_query("SELECT p. *, u.ip, u.signatrue,u.forum_com, u.signature,u.avatar, e.username AS ed_username,e.class AS ed_class,f.name AS for_name,f.description,t.subject
 FROM posts p 
 LEFT JOIN forums AS f ON f.id=p.forumid
 LEFT JOIN topics AS t ON t.id=p.topicid
 LEFT JOIN users u ON u.id = p.userid 
 LEFT JOIN users e ON e.id = p.editedby
 WHERE p.userid=".sqlesc($userid)." ORDER BY p.id $limit") or sqlerr(__FILE__, __LINE__);
      

    print("<table border='1' cellspacing='0' cellpadding='8' width='100%'>
	<tr><td class=colhead><a title=\"Перейти к главным категориям форума\"class=\"altlink_white\" href=".$DEFAULTBASEURL."/forums.php>Главная страничка форума</a> </td></tr></table>\n");
 
 begin_frame("Поиск всех сообщений (".$count.") ".(empty($count) ?"":" у пользователя ".$sear)."", true);

if (!empty($count)){
   echo $pagertop;
   }
  begin_table();
   
    $num=1;
    
    while ($arr = mysqli_fetch_assoc($res))  {

      ++$pn;
      $ed_username = $arr["ed_username"];
      $ed_class = $arr["ed_class"];
        $postid = $arr["id"];
       $forum_name= "<a title=\"".(format_comment($arr["description"]))."\" href=\"".$DEFAULTBASEURL."/forums.php?action=viewforum&forumid=".($arr["forumid"])."\">".$arr["for_name"]."</a>";
        
		$topic_name="<a href=\"".$DEFAULTBASEURL."/forums.php?action=viewtopic&topicid=".$arr["topicid"]."#$postid\">".$arr["subject"]."</a>";
      
    
      $posterid = $arr["userid"];
      $added = ($arr['added']);


if ($arr["forum_com"]<>"0000-00-00 00:00:00" && !empty($postername)){
$ban=": <b>Бан до </b>".$arr["forum_com"]."";
}

//if (get_user_class() > UC_MODERATOR){
$numb_view="<a title=\"Число, под которым это сообщение в базе: ".$postid."\" href='".$DEFAULTBASEURL."/forums.php?action=viewpost&id=".$postid."'>Постоянная ссылка для этого сообщения [<b>$postid</b>]</a>";	
//}

if (!empty($arr["avatar"])){
$avatar = ($CURUSER["id"]==$posterid ? "<a href=\"".$DEFAULTBASEURL."/my.php\"><img alt=\"Аватар, по клику переход в настройки\" title=\"Аватар, по клику переход в настройки\" width=100 height=100 src=\"".$DEFAULTBASEURL."/pic/avatar/".$arr["avatar"]."\"/></a>":"<img width=100 height=100 src=\"".$DEFAULTBASEURL."/pic/avatar/".$arr["avatar"]."\"/>");
} else
$avatar = "<img width=100 src=\"".$DEFAULTBASEURL."/pic/avatar/default_avatar.gif\"/>";


echo("<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr>");
echo("	  
<td width=\"100\" align=\"center\" class=\"a\"><b>#</b>".$num.$numb_view."
<td class=\"a\"><table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
<tr>
<td class=\"a\" width=\"300\"  align=\"left\">
".$topic_name."
</td>
<td class=\"a\" width=\"300\"  align=\"rigth\">
".$forum_name."
</td>
</table>
<td class=b align=center width=2%><a class=\"altlink_white\" href=#top><img src=\"pic/forumicons/top.gif\" border=0 alt='Наверх'></a></td>
</td></td>
</tr>");

echo("</p>\n");

   
$body = format_comment($arr["body"]);

if (is_valid_id($arr['editedby']) AND $arr['editedat']<>0 AND $arr['editedby']<>0 AND get_user_class() >= UC_MODERATOR || $CURUSER["id"] == $posterid)      {
$body .= "<p align=right><font size=1 class=small>Последний раз редактировалось <a href=userdetails.php?id={$arr['editedby']}><b> ".get_user_class_color($ed_class, $ed_username)." </b></a> в ".($arr['editedat'])."</font></p>\n";
unset($ed_class);
unset($ed_username);
}

echo("<tr valign=top><td width=100 align=left>" .$avatar. "</td><td>".$body."</td><td class=a></td></tr>\n");
		
if (($CURUSER["forum_com"]=="0000-00-00 00:00:00"  && $CURUSER["id"]==$posterid && !$locked) || get_user_class() >= UC_MODERATOR){
$edit=("".($posterid<>0 && !empty($postername) ? " -":"")." [<a href=forums.php?action=editpost&postid=".$postid."><b>Редактировать</b></a>]");
}


if (get_user_class() >= UC_MODERATOR){
$delet=" - [<a href=".$DEFAULTBASEURL."/forums.php?action=deletepost&postid=".$postid."><b>Удалить</b></a>]</td></tr>";

$found=" ".($arr["ip"] ? "[<a title=\"Искать этот ip адресс в базе данных через административный поиск\" href=\"".$DEFAULTBASEURL."/usersearch.php?ip=".$arr["ip"]."\" ><b>".$arr["ip"]."</b></a>] -":"")."";
}

$citat=($posterid<>0 && !empty($postername) && $CURUSER["forum_com"]=="0000-00-00 00:00:00" ? " [<a href=".$DEFAULTBASEURL."/forums.php?action=quotepost&topicid=".$topicid."&postid=".$postid."><b>Цитировать</b></a>]":"");	

if (!$locked || get_user_class() >= UC_MODERATOR){

if (get_user_class() >= UC_MODERATOR){
$strlen="<td class=\"a\" align=\"left\" width=\"20\"><a title=\"Размер данного сообщения\">[".strlen($arr["body"])."]</a></td>";}
}

			
echo "<td width=\"100\" align=\"center\" class=\"a\">".$added."
<td class=\"a\"><table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
<tr>
".$strlen."
<td class=\"a\" nowrap=\"\" width=\"2%\" align=\"right\">
".$found.$citat.$edit.$delet."
</td>


</tr></table></td>
<td class=b align=center width=2%><a class=\"altlink_white\" href=#top><img src=\"".$DEFAULTBASEURL."/pic/forumicons/top.gif\" border=0 alt='Наверх'></a></td>
</td>";

unset($avatar);
unset($edit);
unset($delet);
unset($found);
unset($strlen);
unset($citat);
}
        
if (empty($count)){
echo "<table border=0 cellspacing=0 cellpadding=0><tr>    	  
У этого пользователя сообщений на форуме нет!
</tr>";
}
  end_table();
  end_frame();
  echo $pagerbottom; 
    stdfoot_f();
    die;   
///////////////поиск постов///////////////

}
      exit();
      break;
    
//////////////////////
    
    case 'editpoll': {

	$pnew = (string) $_POST["new"];
	$topics = (int)$_GET["topics"];
    $postid = (int)$_POST["postid"];
	
//	die($topicid);
	if (!is_valid_id($topics))
		stderr_f($tracker_lang['error'],$tracker_lang['invalid_id']);
		
//	$res = sql_query("SELECT COUNT(*) FROM polls WHERE forum=".$topics."")	or sqlerr(__FILE__, __LINE__);
//	if (mysql_num_rows($res) == 0)
//	stderr_f($tracker_lang['error'],"Нет опроса с таким ID.");

	$res = sql_query("SELECT * FROM topics WHERE id=".sqlesc($topics))	or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) == 0)
	stderr_f($tracker_lang['error'],"Нет темы с таким ID.");
	$arrpoll = mysqli_fetch_assoc($res);
	
 	if ($CURUSER["id"] <> $arrpoll["userid"] && get_user_class() < UC_MODERATOR)
 	{
 	stderr_f($tracker_lang['error'],"Вы не владелец этой темы.");
 	}

	
  $question = htmlspecialchars_uni($_POST["question"]);
  $option0 = htmlspecialchars($_POST["option0"]);
  $option1 = htmlspecialchars($_POST["option1"]);
  $option2 = htmlspecialchars($_POST["option2"]);
  $option3 = htmlspecialchars($_POST["option3"]);
  $option4 = htmlspecialchars($_POST["option4"]);
  $option5 = htmlspecialchars($_POST["option5"]);
  $option6 = htmlspecialchars($_POST["option6"]);
  $option7 = htmlspecialchars($_POST["option7"]);
  $option8 = htmlspecialchars($_POST["option8"]);
  $option9 = htmlspecialchars($_POST["option9"]);
  $option10 = htmlspecialchars($_POST["option10"]);
  $option11 = htmlspecialchars($_POST["option11"]);
  $option12 = htmlspecialchars($_POST["option12"]);
  $option13 = htmlspecialchars($_POST["option13"]);
  $option14 = htmlspecialchars($_POST["option14"]);
  $option15 = htmlspecialchars($_POST["option15"]);
  $option16 = htmlspecialchars($_POST["option16"]);
  $option17 = htmlspecialchars($_POST["option17"]);
  $option18 = htmlspecialchars($_POST["option18"]);
  $option19 = htmlspecialchars($_POST["option19"]);
  
	 
  if (!$question || !$option0 || !$option1)
    stderr_f($tracker_lang['error'], "Заполните все поля формы!");

  if ($_POST["ready"]=="yes"){
		sql_query("UPDATE polls SET " .		
		"editby = " . sqlesc($CURUSER[id]) . ", " .
		"edittime = " . sqlesc(get_date_time()) . ", " .
		"question = " . sqlesc($question) . ", " .
		"option0 = " . sqlesc($option0) . ", " .
		"option1 = " . sqlesc($option1) . ", " .
		"option2 = " . sqlesc($option2) . ", " .
		"option3 = " . sqlesc($option3) . ", " .
		"option4 = " . sqlesc($option4) . ", " .
		"option5 = " . sqlesc($option5) . ", " .
		"option6 = " . sqlesc($option6) . ", " .
		"option7 = " . sqlesc($option7) . ", " .
		"option8 = " . sqlesc($option8) . ", " .
		"option9 = " . sqlesc($option9) . ", " .
		"option10 = " . sqlesc($option10) . ", " .
		"option11 = " . sqlesc($option11) . ", " .
		"option12 = " . sqlesc($option12) . ", " .
		"option13 = " . sqlesc($option13) . ", " .
		"option14 = " . sqlesc($option14) . ", " .
		"option15 = " . sqlesc($option15) . ", " .
		"option16 = " . sqlesc($option16) . ", " .
		"option17 = " . sqlesc($option17) . ", " .
		"option18 = " . sqlesc($option18) . ", " .
		"option19 = " . sqlesc($option19) . ", " .
		"sort = " . sqlesc() . ", " .
		"comment = " . sqlesc() . " " .
    "WHERE forum=".sqlesc($topics)."") or sqlerr(__FILE__, __LINE__);
    @unlink(ROOT_PATH."cache/forums_ptop-".$topics.".txt");
    
       	sql_query("UPDATE topics SET polls='yes' WHERE id=".sqlesc($topics)) or sqlerr(__FILE__, __LINE__);
		}
		
  if ($pnew == "yes"){
  	
  	  	
  	sql_query("INSERT INTO polls VALUES(0" .
  	", " . sqlesc("") .
  	", " . sqlesc("") .
  	", " . sqlesc("$CURUSER[id]") .
  	", '" . get_date_time() . "'" .
	", " . sqlesc($question) .
    ", " . sqlesc($option0) .
    ", " . sqlesc($option1) .
    ", " . sqlesc($option2) .
    ", " . sqlesc($option3) .
    ", " . sqlesc($option4) .
    ", " . sqlesc($option5) .
    ", " . sqlesc($option6) .
    ", " . sqlesc($option7) .
    ", " . sqlesc($option8) .
    ", " . sqlesc($option9) .
 	", " . sqlesc($option10) .
	", " . sqlesc($option11) .
	", " . sqlesc($option12) .
	", " . sqlesc($option13) .
	", " . sqlesc($option14) .
	", " . sqlesc($option15) .
	", " . sqlesc($option16) .
	", " . sqlesc($option17) .
	", " . sqlesc($option18) .
	", " . sqlesc($option19) . 
	", " . sqlesc("") . 
    ", " . sqlesc("") .
     ", " . sqlesc($topics) .
  	")") or sqlerr(__FILE__, __LINE__);
  	@unlink(ROOT_PATH."cache/forums_ptop-".$topics.".txt");
  	sql_query("UPDATE topics SET polls='yes' WHERE id=".sqlesc($topics)) or sqlerr(__FILE__, __LINE__);
}

header("Location: ".$DEFAULTDEFAULTBASEURL."/forums.php?action=editpost&postid=".$postid);
die;      
}

exit();
break;

//////////////////////
    case 'deletepost':
    case 'editpost':
    case 'deletetopic':
    case 'editpostmod';
    case 'edittopicmod';  
	{
    if ($CURUSER["forum_com"]<>"0000-00-00 00:00:00"){
   	
    	if ($CURUSER){
     header("Refresh: 15; url=".$DEFAULTBASEURL."/forums.php");
		stderr_f("Успешно автопереход через 15 сек", "Вам запрещенно писать или создавать или цитировать любое сообщение на форуме до: ".$CURUSER["forum_com"].". В доступе отказано.");
		die;
		}		else		{
		stderr_f("Ошибка данных", "Вам запрещенно писать или создавать или цитировать любое сообщение на форуме пока не авторизовались. В доступе отказано.");
		die;	
		}
      }
    	

  if ($action == "edittopicmod" && get_user_class() >= UC_MODERATOR)
  {
    $topicid = (int)$_GET["topicid"]; /// какое сообщение

    if (!is_valid_id($topicid))
      die("Не число в edittopicmod");

    //$res = mysql_query("SELECT p.*, f.name AS name_forum, t.t_com,t.forumid FROM posts AS p    LEFT JOIN topics AS t ON t.id=p.topicid	LEFT JOIN forums AS f ON f.id=t.forumid	WHERE p.id=$postid") or sqlerr(__FILE__, __LINE__);

 $res = sql_query("SELECT t.* FROM topics AS t WHERE t.id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);

		if (mysql_num_rows($res) == 0)
		stderr_f("Ошибка", "Нет темы с таким id $topicid.");

	$arr = mysqli_fetch_assoc($res);

 $delete_topic = (int)$_POST['delete_topic'];
 $reson_topic = htmlspecialchars($_POST['reson']);
  
  if ($delete_topic == 1) {
  	if (empty($reson_topic)) {
  	stderr_f("Ошибка удаления", "Вы не указали причину удаления этой темы.");
  	}
  	else
  	{
  	   $r13 = sql_query("SELECT f_com FROM forums WHERE id=".sqlesc($arr["forumid"])."") or sqlerr(__FILE__, __LINE__);
      $ro13 = mysqli_fetch_assoc($r13);

   $mod=date("Y-m-d") . " - $CURUSER[username] удалил тему ".(htmlspecialchars($arr["subject"]))." (".($arr["id"]).") по причине: $reson_topic.\n". $ro13["f_com"];

   mysql_query("UPDATE forums SET f_com=".sqlesc($mod)." WHERE id=".sqlesc($arr["forumid"])."") or sqlerr(__FILE__, __LINE__);
  	
  	mysql_query("DELETE FROM topics WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM posts WHERE topicid=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM polls WHERE forum=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM pollanswers WHERE forum=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
  	//// удаляем посты и тему и опросы и ответы на опросы
  	unlinks(); /// очищаем кеш
	
	    header("Refresh: 5; url=".$DEFAULTBASEURL."/forums.php?action=viewforum&forumid=".$arr["forumid"]."");
		stderr_f("Успешно автопереход через 5 сек", "Удаленна тема с ее сообщениями.<br /> Нажмите <a href=\"".$DEFAULTBASEURL."/forums.php?action=viewforum&forumid=".$arr["forumid"]."\">ЗДЕСЬ</a>, если не хотите ждать.");
	 die("edittopicmod");
  	}
  	
  	
  }
 
	if (get_user_class() == UC_SYSOP){
	$modi = htmlspecialchars($_POST["modcomment"]);
    }   else    $modi=$arr["t_com"]; /// оригинал комментария категории форума

   $subject = htmlspecialchars_uni($_POST['subject']);
   if ($subject <> $arr["subject"]) {
   $updateset[] = "subject=".sqlesc($subject)."";
   $modi=date("Y-m-d") . " - ".$CURUSER["username"]." поменял название темы с (".htmlspecialchars($arr["subject"]).") на (".$subject.").\n". $modi;
   }

   $locked = (string) $_POST["locked"];
   $lock_arr = $arr["locked"];

   if ($locked=="yes" && $lock_arr=="no")  {
   	$updateset[] = "locked = 'yes'";
   	$modi=date("Y-m-d") . " - ".$CURUSER["username"]." заблокировал тему.\n". $modi;
   }
   if ($locked=="no" && $lock_arr=="yes")   {
   	$updateset[] = "locked = 'no'";
   	$modi=date("Y-m-d") . " - ".$CURUSER["username"]." разблокировал тему.\n". $modi;
   }
   



   if ($_POST["visible"]=="no" && $arr["visible"]=="yes") {
   	$updateset[] = "visible = 'no'";
   	$modi=date("Y-m-d") . " - $CURUSER[username] скрыл тему.\n". $modi;
   }
   if ($_POST["visible"]=="yes" && $arr["visible"]=="no") {
   	$updateset[] = "visible = 'yes'";
   	$modi=date("Y-m-d") . " - $CURUSER[username] вкл показ темы.\n". $modi;
   }
  
   $sticky = (string)$_POST["sticky"];
   $sti_arr = $arr["sticky"];
   
    if ($sticky=="yes" && $sti_arr=="no"){
    $updateset[] = "sticky = 'yes'";
    $modi=date("Y-m-d") . " - ".$CURUSER["username"]." прикрепил.\n". $modi;
    } elseif ($sticky=="no" && $sti_arr=="yes"){
    $updateset[] = "sticky = 'no'";
    $modi=date("Y-m-d") . " - ".$CURUSER["username"]." снял важность.\n". $modi;
    }
    
   $forumid = (int) $_POST["forumid"];
   $for_arr = $arr["forumid"];
   
   if ($forumid<>$for_arr && is_valid_id($forumid) && $forumid<>0){
   	
   $re3 = sql_query("SELECT name FROM forums WHERE id=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
   $rom = mysqli_fetch_assoc($re3);
   
   $ree3 = sql_query("SELECT name FROM forums WHERE id=".sqlesc($for_arr)) or sqlerr(__FILE__, __LINE__);
   $room = mysqli_fetch_assoc($ree3);
   
   $new_f = $rom["name"];
   $now_f = $room["name"];
   
   if ($new_f){
  $updateset[] = "forumid = ".sqlesc($forumid)."";
  $modi=date("Y-m-d") . " - ".$CURUSER["username"]." переместил тему с (">$now_f.") в (".$new_f.").\n". $modi;
  }
   }
    
 //   $modi=date("Y-m-d") . " - $CURUSER[username] сменил автора сообщения ($postid) $orig_user_post на ".$ro["username"].".\n". $modi;
 
  
    $modcomm=htmlspecialchars($_POST["modcomm"]);
	if (!empty($modcomm))
	$modi = date("Y-m-d") . " - Заметка от ".$CURUSER["username"].": ".$modcomm."\n" . $modi;
	
	$updateset[] = "t_com =".sqlesc($modi)."";	
		
   sql_query("UPDATE topics SET " . implode(", ", $updateset) . " WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
    
   unlinks(); /// очищаем кеш

///$returnto = htmlspecialchars($_POST["returnto"]);
///	$returnto .= "&page=p$postid#$postid";

header("Refresh: 3; url=$DEFAULTBASEURL/forums.php?action=edittopic&topicid=$topicid");
		stderr_f("Успешно", "Обновление без ошибок, автопереход через 3 сек.<br /> Нажмите <a href=\"$DEFAULTBASEURL/forums.php?action=edittopic&topicid=$topicid\">ЗДЕСЬ</a>, если не хотите ждать.");
	 die("edittopicmod");
 //////////////////////////////
}
  

 if ($action == "editpostmod" && get_user_class() >= UC_MODERATOR)
  {
    $postid = (int)$_GET["postid"]; /// какое сообщение

    if (!is_valid_id($postid))
      die("Не число в editpostmod");

    $res = mysql_query("SELECT p.*, f.name AS name_forum, t.id AS topic_id,t.t_com,t.forumid FROM posts AS p
    LEFT JOIN topics AS t ON t.id=p.topicid
	LEFT JOIN forums AS f ON f.id=t.forumid
	WHERE p.id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);

		if (mysql_num_rows($res) ==0)
			stderr_f("Ошибка", "Нет сообщения с таким id.");

	$arr = mysqli_fetch_assoc($res);

	if (get_user_class() == UC_SYSOP)
        {
		$modi = htmlspecialchars($_POST["modcomment"]);
        }
    	else
    $modi=$arr["t_com"]; /// оригинал комментария категории форума

    $forumid=$arr["forumid"];///
   $release = (int) $_POST['release'];
   
   if (!empty($release) && get_user_class() >= UC_ADMINISTRATOR){
   $re = sql_query("SELECT username FROM users WHERE id=".sqlesc($release)."") or sqlerr(__FILE__, __LINE__);
   $ro = mysqli_fetch_assoc($re); 
   
   
   $r1 = sql_query("SELECT username FROM users WHERE id=".sqlesc($arr["userid"])."") or sqlerr(__FILE__, __LINE__);
   $re1 = mysqli_fetch_assoc($r1); 
   $orig_user_post=$re1["username"];
   
   
   if (!empty($ro["username"]) && $orig_user_post<>$ro["username"]){
   	
   	///// вычисляем сколько сообщений если одно значить тема обновляет в topics - последн автора=автор оригинальный
    $one_ans = mysql_query("SELECT COUNT(*) FROM posts WHERE topicid=".sqlesc($arr["topicid"])."") or sqlerr(__FILE__, __LINE__);
    $one = mysql_fetch_row($one_ans);
    $topicidi = $one[0];
/// die("$topicid");
    if ($topicidi<=1){
///	die($arr["topic_id"]);
     mysql_query("UPDATE topics SET userid=".sqlesc($release)." WHERE id=".sqlesc($arr["topic_id"])."") or sqlerr(__FILE__, __LINE__);	
    }
	/////////

    mysql_query("UPDATE posts SET userid=".sqlesc($release).", forumid=".sqlesc($forumid)." WHERE id=".sqlesc($postid)."") or sqlerr(__FILE__, __LINE__);
    
    $modi=date("Y-m-d") . " - $CURUSER[username] сменил автора сообщения ($postid) $orig_user_post на ".$ro["username"].".\n". $modi;
    
   }
   }
   
  $set_system = (int)$_POST["set_system"];
  if ($set_system==1 && $arr["userid"]<>0 && get_user_class() >= UC_ADMINISTRATOR)
  {
  /// ставим 0 в id как от системы
  mysql_query("UPDATE posts SET userid=0, forumid=".sqlesc($forumid)." WHERE id=$postid") or sqlerr(__FILE__, __LINE__);
  $modi=date("Y-m-d") . " - $CURUSER[username] присвоил автора сообщения ($postid) - System.\n". $modi;
  }
  
    $modcomm=htmlspecialchars($_POST["modcomm"]);
	if (!empty($modcomm))
	$modi = date("Y-m-d") . " - Заметка от $CURUSER[username]: $modcomm\n" . $modi;
		
    mysql_query("UPDATE topics SET t_com=".sqlesc($modi)." WHERE id=".sqlesc($arr["topicid"])."") or sqlerr(__FILE__, __LINE__);
    
  unlinks(); /// очищаем кеш


		///$returnto = htmlspecialchars($_POST["returnto"]);

		
			///	$returnto .= "&page=p$postid#$postid";
			///	header("Location: forums.php?action=editpost&postid=$postid");
		//	}
		//	else
			
			header("Refresh: 3; url=$DEFAULTBASEURL/forums.php?action=editpost&postid=$postid");
		stderr_f("Успешно", "Сообщение было отредактированно, автопереход через 3 сек.<br /> Нажмите <a href=\"$DEFAULTBASEURL/forums.php?action=editpost&postid=$postid\">ЗДЕСЬ</a>, если не хотите ждать.");
	 die("editpostmod");
				
			///	stderr_f("Успешно", "Сообщение было отредактированно.");
								// 	die;
    

 //////////////////////////////
  }
  
  
  
  if ($action == "editpost")
  {
    $postid = (int)$_GET["postid"];

    if (!is_valid_id($postid))
      die("Не число в editpost");

    $res = sql_query("SELECT p.*, t.t_com,t.polls,t.forumid, t.subject,  u.username,u.class
	FROM posts AS p
    LEFT JOIN topics AS t ON t.id=p.topicid
	LEFT JOIN users AS u ON u.id=p.userid 	
	WHERE p.id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) == 0)
	stderr_f("Ошибка", "Нет сообщения с таким id.");

	$arr = mysqli_fetch_assoc($res);
    $forumi=$arr["forumid"];
    $res2 = sql_query("SELECT locked FROM topics WHERE id = " . sqlesc($arr["topicid"])) or sqlerr(__FILE__, __LINE__);
	$arr2 = mysqli_fetch_assoc($res2);

 	if (mysql_num_rows($res)==0)
	stderr_f("Ошибка", "Нет категории для этого сообщения.");

		$locked = ($arr2["locked"] == 'yes');

    if (($CURUSER["id"] <> $arr["userid"] || $locked) && get_user_class() < UC_MODERATOR)
      stderr_f("Ошибка прав", "Доступ запрещен");

    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
    	$body = htmlspecialchars_uni($_POST['body']);

    	if (empty($body))
    	  stderr_f("Ошибка", "Сообщение не может быть пустым!");

if (isset($_POST["nobb"]) && !empty($_POST["nobb"]))
$body = preg_replace("/\[((\s|.)+?)\]/is", "", $body); /// чистм от мусора [] и тд

if (isset($_POST["addurl"]) && !empty($_POST["addurl"]))
$body = preg_replace("/(http:\/\/[^\s'\"<>]+(\.(jpg|jpeg|gif|png)))/is", "[img]\\1[/img]", $body);

if ($body<>$arr["body"]) {

$editedat = get_date_time();

if (empty($arr["body_orig"]))
$updatbody[] = "body_orig = ".sqlesc(htmlspecialchars($arr["body"]));

$updatbody[] = "forumid = ".sqlesc($forumi);
$updatbody[] = "editedby = ".sqlesc($CURUSER["id"]);
$updatbody[] = "editedat = ".sqlesc($editedat);
$updatbody[] = "body = ".sqlesc($body);

sql_query("UPDATE posts SET " . implode(",", $updatbody) . " WHERE id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);

//  sql_query("UPDATE posts SET body=".$body.", editedat=".$editedat.", editedby=".sqlesc($CURUSER["id"]).",forumid=".sqlesc($forumi)." WHERE id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);

  unlinks();
}
  
		$returnto = htmlspecialchars_uni($_POST["returnto"]);

			if (!empty($returnto))
			{
			 $returnto .= "#$postid"; /// переходим этому сообщению сразу
			
			   header("Refresh: 5; url=$returnto");
		stderr_f("Успешно автопереход через 5 сек", "Сообщение было отредактировано. Время обновления помеченно как: ".($editedat).".<br /> Нажмите <a href=\"$returnto\">ЗДЕСЬ</a>, если не хотите ждать.");
		die("editpost");
			
			//	header("Location: $returnto");
			}
			else
			header("Refresh: 5; url=forums.php?action=viewforum&forumid=".$arr["forumid"]."#$postid");
		stderr_f("Успешно автопереход через 5 сек", "Сообщение было отредактировано. Время обновления помеченно как: ".($editedat).".<br /> Нажмите <a href=\"forums.php?action=viewforum&forumid=".$arr["forumid"]."#$postid\">ЗДЕСЬ</a>, если не хотите ждать.");
		die("editpost");
			///	stderr_f("Успешно", "Сообщение было отредактированно.");
    }

  stdhead_f("Редактирование сообщения");

  print("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
  print("<tr><td class=colhead align=\"center\" colspan=\"2\"><a name=comments></a><b>.::: Редактирование комментария :::.</b><br />
  <a href=\"forums.php?action=viewtopic&topicid=".$arr["topicid"]."\">Вернутся к показу темы</a>
  </td></tr>");
  print("<tr><td width=\"100%\" align=\"center\" >");
  print("<form name=\"comment\" method=\"post\" action=\"forums.php?action=editpost&postid=$postid\">");
  print("<center><table border=\"0\"><tr><td class=\"clear\">");
  print("<div align=\"center\">". textbbcode("comment","body",($arr["body"]), 1) ."</div>");
  print("</td></tr></table></center>");
  print("</td></tr><tr><td align=\"center\" colspan=\"2\">");

  echo "<label><input type=\"checkbox\" title=\"Убрать весь BB код из текста\" name=\"nobb\" value=\"1\">nobb</label> \n";
  echo "<label><input type=\"checkbox\" title=\"Добавить к фото ссылкам - тег [img]\" name=\"addurl\" value=\"1\">[img]</label><br />\n";

 
  print("<input type=\"hidden\" value=\"$topicid\" name=\"topicid\"/>
  <input type=hidden name=returnto value=\"forums.php?action=viewtopic&topicid=".$arr["topicid"]."\">");
  print("<input type=\"submit\" name=\"post\" title=\"CTRL+ENTER отредактировать сообщение\"  class=btn value=\"Редактировать сообщение\" />");
 print("</td></tr></table></form><br />");


    $res1 = mysql_query("SELECT id AS first, (SELECT COUNT(*) FROM posts WHERE topicid=".sqlesc($arr["topicid"]).") AS count FROM posts WHERE topicid=".sqlesc($arr["topicid"])." ORDER BY id ASC LIMIT 1") or sqlerr(__FILE__, __LINE__);

    $arr1 = mysqli_fetch_assoc($res1);
    
  	
  	if (($arr["first"]==$postid && $CURUSER["id"]==$arr["userid"]) || get_user_class() >= UC_MODERATOR) {
 

	$res = sql_query("SELECT * FROM polls WHERE forum=".sqlesc($arr["topicid"])) or sqlerr(__FILE__, __LINE__);
	//	stderr_f($tracker_lang['error'],"Нет опроса с таким ID.");
	$poll = mysql_fetch_array($res);

if (empty($poll["id"]) && $arr["polls"]=="no"){
print("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
print("<tr><td class=colhead align=\"center\" colspan=\"2\"><a name=comments></a><b><center>.::: Создание опроса для данной темы :::.</b></center></td></tr>");
print("<form method=\"post\" action=\"forums.php?action=editpoll&topics=".$arr["topicid"]."\">");
print("<tr><td>");
	
echo"<table border=0 width=\"100%\" cellspacing=0 cellpadding=5>
<tr><td class=rowhead>Вопрос <font color=red>*</font></td><td align=left><input name=question size=80 maxlength=255 value=".htmlspecialchars($poll['question'])."></td></tr>
<tr><td class=rowhead>Вариант 1 <font color=red>*</font></td><td align=left><input name=option0 size=80 maxlength=255 value=".htmlspecialchars($poll['option0'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 2 <font color=red>*</font></td><td align=left><input name=option1 size=80 maxlength=255 value=".htmlspecialchars($poll['option1'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 3</td><td align=left><input name=option2 size=80 maxlength=255 value=".htmlspecialchars($poll['option2'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 4</td><td align=left><input name=option3 size=80 maxlength=255 value=".htmlspecialchars($poll['option3'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 5</td><td align=left><input name=option4 size=80 maxlength=255 value=".htmlspecialchars($poll['option4'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 6</td><td align=left><input name=option5 size=80 maxlength=255 value=".htmlspecialchars($poll['option5'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 7</td><td align=left><input name=option6 size=80 maxlength=255 value=".htmlspecialchars($poll['option6'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 8</td><td align=left><input name=option7 size=80 maxlength=255 value=".htmlspecialchars($poll['option7'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 9</td><td align=left><input name=option8 size=80 maxlength=255 value=".htmlspecialchars($poll['option8'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 10</td><td align=left><input name=option9 size=80 maxlength=255 value=".htmlspecialchars($poll['option9'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 11</td><td align=left><input name=option10 size=80 maxlength=255 value=".htmlspecialchars($poll['option10'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 12</td><td align=left><input name=option11 size=80 maxlength=255 value=".htmlspecialchars($poll['option11'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 13</td><td align=left><input name=option12 size=80 maxlength=255 value=".htmlspecialchars($poll['option12'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 14</td><td align=left><input name=option13 size=80 maxlength=255 value=".htmlspecialchars($poll['option13'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 15</td><td align=left><input name=option14 size=80 maxlength=255 value=".htmlspecialchars($poll['option14'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 16</td><td align=left><input name=option15 size=80 maxlength=255 value=".htmlspecialchars($poll['option15'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 17</td><td align=left><input name=option16 size=80 maxlength=255 value=".htmlspecialchars($poll['option16'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 18</td><td align=left><input name=option17 size=80 maxlength=255 value=".htmlspecialchars($poll['option17'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 19</td><td align=left><input name=option18 size=80 maxlength=255 value=".htmlspecialchars($poll['option18'])."><br /></td></tr>
<tr><td class=rowhead>Вариант 20</td><td align=left><input name=option19 size=80 maxlength=255 value=".htmlspecialchars($poll['option19'])."><br /></td></tr>

<tr><td class=rowhead>Подтверждение создания</td><td>
<input type=radio name=new value=yes>Да
<input type=radio name=new checked value=no> Нет
</td></tr>
</table>";
 
echo("<tr><td align=\"center\" colspan=\"2\">");
echo("<input type=\"hidden\" value=\"".$postid."\" name=\"postid\"/>");
echo("<input type=\"submit\" class=btn value=\"Создать опрос\" />");
echo("</td></tr></table></form><br />");
}
elseif (!empty($poll["id"]) && $arr["polls"]=="yes")	
{
echo("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
echo("<tr><td class=colhead align=\"center\" colspan=\"2\"><a name=comments></a><b><center>.::: Администрирование опроса для данной темы :::.</b></center></td></tr>");
echo("<form method=\"post\" action=\"forums.php?action=editpoll&topics=".$arr["topicid"]."\">");
echo("<tr><td>");
echo"<table border=0 width=\"100%\" cellspacing=0 cellpadding=5>
<tr><td class=rowhead>Вопрос <font color=red>*</font></td><td align=left><input name=question size=80 maxlength=255 value='".$poll['question']."'><br />".$poll['question']."</td></tr>
<tr><td class=rowhead>Вариант 1 <font color=red>*</font></td><td align=left><input name=option0 size=80 maxlength=255 value='".$poll['option0']."'><br />".$poll['option0']."</td></tr>
<tr><td class=rowhead>Вариант 2 <font color=red>*</font></td><td align=left><input name=option1 size=80 maxlength=255 value='".$poll['option1']."'><br />".$poll['option1']."</td></tr>
<tr><td class=rowhead>Вариант 3</td><td align=left><input name=option2 size=80 maxlength=255 value='".$poll['option2']."'><br />".$poll['option2']."</td></tr>
<tr><td class=rowhead>Вариант 4</td><td align=left><input name=option3 size=80 maxlength=255 value='".$poll['option3']."'><br />".$poll['option3']."</td></tr>
<tr><td class=rowhead>Вариант 5</td><td align=left><input name=option4 size=80 maxlength=255 value='".$poll['option4']."'><br />".$poll['option4']."</td></tr>
<tr><td class=rowhead>Вариант 6</td><td align=left><input name=option5 size=80 maxlength=255 value='".$poll['option5']."'><br />".$poll['option5']."</td></tr>
<tr><td class=rowhead>Вариант 7</td><td align=left><input name=option6 size=80 maxlength=255 value='".$poll['option6']."'><br />".$poll['option6']."</td></tr>
<tr><td class=rowhead>Вариант 8</td><td align=left><input name=option7 size=80 maxlength=255 value='".$poll['option7']."'><br />".$poll['option7']."</td></tr>
<tr><td class=rowhead>Вариант 9</td><td align=left><input name=option8 size=80 maxlength=255 value='".$poll['option8']."'><br />".$poll['option8']."</td></tr>
<tr><td class=rowhead>Вариант 10</td><td align=left><input name=option9 size=80 maxlength=255 value='".$poll['option9']."'><br />".$poll['option9']."</td></tr>
<tr><td class=rowhead>Вариант 11</td><td align=left><input name=option10 size=80 maxlength=255 value='".$poll['option10']."'><br />".$poll['option10']."</td></tr>
<tr><td class=rowhead>Вариант 12</td><td align=left><input name=option11 size=80 maxlength=255 value='".$poll['option11']."'><br />".$poll['option11']."</td></tr>
<tr><td class=rowhead>Вариант 13</td><td align=left><input name=option12 size=80 maxlength=255 value='".$poll['option12']."'><br />".$poll['option12']."</td></tr>
<tr><td class=rowhead>Вариант 14</td><td align=left><input name=option13 size=80 maxlength=255 value='".$poll['option13']."'><br />".$poll['option13']."</td></tr>
<tr><td class=rowhead>Вариант 15</td><td align=left><input name=option14 size=80 maxlength=255 value='".$poll['option14']."'><br />".$poll['option14']."</td></tr>
<tr><td class=rowhead>Вариант 16</td><td align=left><input name=option15 size=80 maxlength=255 value='".$poll['option15']."'><br />".$poll['option15']."</td></tr>
<tr><td class=rowhead>Вариант 17</td><td align=left><input name=option16 size=80 maxlength=255 value='".$poll['option16']."'><br />".$poll['option16']."</td></tr>
<tr><td class=rowhead>Вариант 18</td><td align=left><input name=option17 size=80 maxlength=255 value='".$poll['option17']."'><br />".$poll['option17']."</td></tr>
<tr><td class=rowhead>Вариант 19</td><td align=left><input name=option18 size=80 maxlength=255 value='".$poll['option18']."'><br />".$poll['option18']."</td></tr>
<tr><td class=rowhead>Вариант 20</td><td align=left><input name=option19 size=80 maxlength=255 value='".$poll['option19']."'><br />".$poll['option19']."</td></tr>

<tr><td class=rowhead>Подтверждение замены</td><td>
<input type=radio name=ready value=yes>Да
<input type=radio name=ready checked value=no> Нет
</td></tr>
</table>";
 
echo("<tr><td align=\"center\" colspan=\"2\">");
echo("<input type=\"hidden\" value=\"".$postid."\" name=\"postid\"/>");
echo("<input type=\"submit\" class=btn value=\"Редактировать опрос\" />");
echo("</td></tr></table></form><br />");
}
}
  if (get_user_class() >= UC_MODERATOR) {
  	
  $modcomment=htmlspecialchars($arr["t_com"]);
  $forums=htmlspecialchars($arr["subject"]);
  echo("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
  echo("<tr><td class=colhead align=\"center\" colspan=\"2\"><a name=comments></a><b><center>.::: Администрирование сообщения или темы :::.</b></center></td></tr>");
  
  $user_orig="<a href=\"userdetails.php?id=".$arr["userid"]."\">".get_user_class_color($arr["class"], $arr["username"]) . "</a>";

  echo("<form method=\"post\" action=\"forums.php?action=editpostmod&postid=$postid\">");
  echo("<tr><td>");
 
  if (get_user_class() >= UC_ADMINISTRATOR) {
  echo("<tr><td class=\"a\"><b>Сделать это сообщение от system</b>:  <input type=checkbox name=set_system value=1> <i>собщение данное будет от системы</i></td></tr>");
  
  echo("<tr><td class=\"a\"><b>Присвоить автора (ввести новый id)</b>:  <input type=\"text\" size=\"8\" name=\"release\"> <i> прежний [$user_orig] будет заменен новым</i></td></tr>");
   }
   
   echo("<tr><td class=\"a\">История темы <b>$forums</b> и ее прилегающих сообщений [".strlen($modcomment)."]<br />
  <textarea cols=100% rows=6".(get_user_class() < UC_SYSOP ? " readonly" : " name=modcomment").">$modcomment</textarea>
    </td></tr>  
	<tr><td class=\"a\"><b>Добавить заметку</b>: <textarea cols=100% rows=3 name=modcomm></textarea>
    </td></tr>
  ");

  echo("<tr><td align=\"center\" colspan=\"2\">");
  echo("<input type=\"hidden\" value=\"$topicid\" name=\"topicid\"/>");
  echo("<input type=\"submit\" class=btn value=\"Редактировать\" />");
 echo("</td></tr></table></form><br />");
 }
////
    stdfoot_f();
  	die;
  }

  if ($action == "deletepost")
  {
    $postid = (int) $_GET["postid"];

    $sure = (int) $_GET["sure"];

    if (!is_valid_id($postid))
      die("Не число");


    $res = sql_query("SELECT topicid,userid FROM posts WHERE id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res) or stderr_f("Ошибка", "Не найденно сообщение");

    $topicid = $arr["topicid"];
    $userid=$arr["userid"];
    
   if ($userid<>$CURUSER["id"]){
  
   	if (get_user_class() < UC_MODERATOR)	
	   die("Не ваше сообщение");
   	
   }
   

  if ($userid<>$CURUSER["id"] && get_user_class() < UC_MODERATOR)
   die("Нет прав доступа");


    $res = mysql_query("SELECT id AS first, (SELECT COUNT(*) FROM posts WHERE topicid=".sqlesc($topicid).") AS count FROM posts WHERE topicid=".sqlesc($topicid)." ORDER BY id ASC LIMIT 1") or sqlerr(__FILE__, __LINE__);

    $arr = mysqli_fetch_assoc($res);

//die($arr["first"]);
if ($arr["count"] < 2 || $arr["first"]==$postid)
stderr_f("Подтверждение", "Данное сообщение связанно с первой темой, удаление его повлечет удалением темы, вы уверены? \n" . "<a href=forums.php?action=deletetopic&topicid=$topicid&sure=1>да, и жму тут</a> для продолжения.\n");


    $res = mysql_query("SELECT id FROM posts WHERE topicid=".sqlesc($topicid)." AND id < ".sqlesc($postid)." ORDER BY id DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) == 0)
			$redirtopost = "";
		else
		{
			$arr = mysql_fetch_row($res);
			//$redirtopost = "&page=p$arr[0]#$arr[0]";
		}

    if (!$sure) {
    stderr_f("Удаление", "Если хотите удалить сообщение, Нажмите \n" . "<a href=forums.php?action=deletepost&postid=$postid&sure=1>здесь</a> иначе вернитесь обратно.");
    }

  
  	$res_fo = sql_query("SELECT t.subject,t.forumid,t.t_com, u.username
	FROM topics AS t
   LEFT JOIN forums AS f ON f.id=t.forumid
   LEFT JOIN users AS u ON u.id=".sqlesc($userid)."
   WHERE t.id = ".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	$arr_for = mysqli_fetch_assoc($res_fo);
   if (!empty($arr_for)){
   $modiki=$arr_for["t_com"]; /// оригинал комментария категории форума	
   $subject=$arr_for["subject"]; 
   if (empty($arr_for["username"]) && $userid<>0) {
   $usera="user: $userid";
   }
   elseif (empty($arr_for["username"]) && $userid==0) {
   $usera="System";
   } else
   $usera=$arr_for["username"];
   
   $modiki = date("Y-m-d") . " - $CURUSER[username] удалил сообщение ($usera).\n" . $modiki;
   sql_query("UPDATE topics SET t_com =".sqlesc($modiki)." WHERE id=".sqlesc($topicid)."") or sqlerr(__FILE__, __LINE__);
    }
  
    sql_query("DELETE FROM posts WHERE id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);

     $added = "0";
    update_topic_last_post($topicid,$added,$postid);
    unlinks();
  
  	header("Refresh: 3; url=$DEFAULTBASEURL/forums.php?action=viewtopic&topicid=$topicid");
		stderr_f("Успешно", "Сообщение было удалено, автопереход через 3 сек.<br /> Нажмите <a href=\"$DEFAULTBASEURL/forums.php?action=viewtopic&topicid=$topicid\">ЗДЕСЬ</a>, если не хотите ждать.");
	 die("deletepost");
  }

  if ($action == "deletetopic"){
	$topicid = (int)$_GET["topicid"];
	
	if (!is_valid_id($topicid))
		die("не число");

	$sure = ((int)$_GET["sure"]);
	if ($sure <> "1"){
		die("нет подтверждения на удаление");
	}

  	$res_fo = mysql_query("SELECT t.subject,t.forumid,f.f_com,t.userid
	   FROM topics AS t
   LEFT JOIN forums AS f ON f.id=t.forumid
	  WHERE t.id = ".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	$arr_for = mysqli_fetch_assoc($res_fo);
	
		if ($CURUSER["id"]<>$arr_for["userid"]){
		
		if (get_user_class() < UC_MODERATOR)
		die("Не ваше сообщение для удаления");
	}
	
  	if (!empty($arr_for))
  	{
  		
    ///die($arr_for["f_com"]);
  		
    $modik=$arr_for["f_com"]; /// оригинал комментария категории форума	
   $subject=$arr_for["subject"]; 
 	$modik = date("Y-m-d") . " - $CURUSER[username] удалил тему $subject ($topicid) и сообщения связ с ней.\n" . $modik;
		$forumfid=$arr_for["forumid"];
   mysql_query("UPDATE forums SET f_com =".sqlesc($modik)." WHERE id=".sqlesc($forumfid)."") or sqlerr(__FILE__, __LINE__);
    }
    

	mysql_query("DELETE FROM topics WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM posts WHERE topicid=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM polls WHERE forum=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
	mysql_query("DELETE FROM pollanswers WHERE forum=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
			
  unlinks();
  
  if ($forumfid){
  	
  	  	header("Refresh: 3; url=$DEFAULTBASEURL/forums.php?action=viewforum&forumid=$forumfid");
		stderr_f("Успешно", "Тема с сообщениями была удаленна, автопереход через 3 сек.<br /> Нажмите <a href=\"$DEFAULTBASEURL/forums.php?action=viewforum&forumid=$forumfid\">ЗДЕСЬ</a>, если не хотите ждать.");
	 die("deletepost");
	}
	else
		@header("Location: forums.php");	
	die;
}
	
	
    	
    }
      exit();
      break;
      
    case 'viewunread':
    {

/////////////// orum_view_unread ////////////////////
    $userid = $CURUSER['id'];

    $maxresults = 25;

	$dt = get_date_time(gmtime() - $Forum_Config["readpost_expiry"]);
		
	$res = mysql_query("SELECT st.views, st.id, st.forumid, st.subject, st.lastpost, u.class,u.username, sp.added,sp.userid,sp.id AS lastposti
	FROM topics AS st 
	LEFT JOIN posts AS sp ON st.lastpost = sp.id 
	LEFT JOIN users AS u ON u.id=sp.userid
  	WHERE st.visible='yes' AND sp.added >".sqlesc($dt)." ORDER BY forumid") or sqlerr(__FILE__, __LINE__);

    stdhead_f();

     echo "<table border='0' cellspacing='0' cellpadding='5' width='100%'>
	<tr><td class=colhead align=center>Темы с непрочитанными сообщениями</td></tr></table>";
    $n = 0;

    $uc = get_user_class();

    while ($arr = mysqli_fetch_assoc($res))
    {
      $topicid = $arr['id'];
      $lastposti=$arr['lastposti'];
      $forumid = $arr['forumid'];
       $username = $arr['username'];
         $class = $arr['class'];
         $time = ($arr["added"]);
        $use_id= $arr["userid"];
        
        if ($use_id==0 && empty($username))
        {
        	$user_view="<font color=gray>[System]</font>";
        }
        elseif($use_id<>0 && empty($username))
        {
        $user_view="<b>id</b>: $use_id";
        }
        else
        $user_view="<b><a href=\"userdetails.php?id=".$use_id."\">".get_user_class_color($class, $username)."</a></b>";
        
      $r = mysql_query("SELECT lastpostread FROM readposts WHERE userid=".sqlesc($userid)." AND topicid=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);

      $a = mysql_fetch_row($r);

      if ($a && $a[0] == $arr['lastpost'])
        continue;

      /// $last=$a[0];
      $r = mysql_query("SELECT name, minclassread FROM forums WHERE id=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);


///p.added, p.topicid, p.userid, u.username,u.class, t.subject 							FROM posts p							LEFT JOIN users u ON p.userid = u.id							LEFT JOIN topics t ON p.topicid = t.id							WHERE p.id = $lastpostid
      $a = mysqli_fetch_assoc($r);

      if ($uc < $a['minclassread'])
        continue;

      $n++;

      if ($n > $maxresults)
        break;

      $forumname = $a['name'];

      if ($n == 1)
      {
        echo("<table border=0 cellspacing=0 width=100% cellpadding=5>\n");
        echo("<tr>
		<td class=colhead align=left>Тема</td>
		<td class=colhead align=left>Категория</td>
		</tr>\n");
      }

      echo("<tr><td align=left><table border=0 cellspacing=0 cellpadding=0><tr>
	  <td class=b>" .
      "<img src=\"{$forum_pic_url}unlockednew.gif\" style='margin-right: 5px'></td>
	  <td class=embedded><a href=forums.php?action=viewtopic&topicid=$topicid&page=last#$lastposti><b>" . format_comment($arr["subject"]) .
      "</b></a><br />Последнее сообщение от: $user_view в $time
	  </td>
	  </tr></table></td>
	  <td align=left class=\"a\"><a href=forums.php?action=viewforum&amp;forumid=$forumid><b>$forumname</b></a></td></tr>\n");
    }
    if ($n > 0)
    {
      print("</table>\n");

      if ($n > $maxresults)
        print("<p><b>Найденно больше чем $maxresults результатов, показаны первые из $maxresults из них.</b></p>\n");

    echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
	<tr><td class=colhead align=center><a class=\"altlink_white\" href=forums.php?action=catchup><b>Пометить как прочитанные</b></a></td></tr></table>\n";
    }
    else
     
	 echo "<table class=\"main\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr><td class=\"embedded\"><div align=\"center\" class=\"error\">Ничего не найденно</div></td></tr></table>";

    stdfoot_f();
    die;
/////////////// view_unread ////////////////////
     }
     
      exit();
      break;
      
    case 'search':
    {
    	
    stdhead_f("Поиск на форуме");

$keywords = isset($_GET['keywords']) ? trim((string)$_GET['keywords']) : '';

if ($keywords !== '') {
    // настройки пагинации
    $perpage = 25;
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    // подготовка строки поиска
    $search = htmlspecialchars_uni($keywords);

    // для LIKE
    $q2 = sqlesc('%' . sqlwildcardesc(trim($search)) . '%');

    // вычищаем скобки и опасные символы, собираем слова для BOOLEAN MODE
    $search = preg_replace("/\(((\s|.)+?)\)/is", "", preg_replace("/\[((\s|.)+?)\]/is", "", $search));
    $search = str_replace(["'", "\"", "%", "$", "/", "`", "<", ">"], " ", $search);
    $terms  = array_values(array_filter(array_map('trim', explode(' ', $search))));
    $bool   = [];
    foreach ($terms as $t) {
        if (mb_strlen($t, 'UTF-8') >= 3) $bool[] = '+' . $t;
    }

    // считаем кол-во попаданий
    if (mb_strlen($search, 'UTF-8') >= 4 && count($bool) > 0) {
        $sql_cnt = "SELECT COUNT(*) FROM posts WHERE MATCH (body) AGAINST ('" . implode(' ', $bool) . "' IN BOOLEAN MODE)";
    } else {
        $sql_cnt = "SELECT COUNT(*) FROM posts WHERE body LIKE {$q2}";
    }
    $res_cnt = sql_query($sql_cnt) or sqlerr(__FILE__, __LINE__);
    [$hits]  = mysqli_fetch_row($res_cnt);

    echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
<tr><td class='b' align='center'>Поиск по \"" . htmlspecialchars($keywords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"</td></tr></table>";

    if ((int)$hits === 0) {
        echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
<tr><td class='b' align='center'>Извините, ничего не найдено.</td></tr></table>";
    } else {
        $count = (int)$hits;
        // pager()
        list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "forums.php?action=search&keywords=" . urlencode($keywords) . "&");

        // основной выбор
        if (mb_strlen($search, 'UTF-8') >= 4 && count($bool) > 0) {
            $sql = "SELECT id, topicid, userid, added
                    FROM posts
                    WHERE MATCH (body) AGAINST ('" . implode(' ', $bool) . "' IN BOOLEAN MODE)
                    ORDER BY added DESC {$limit}";
        } else {
            $sql = "SELECT id, topicid, userid, added
                    FROM posts
                    WHERE body LIKE {$q2}
                    ORDER BY added DESC {$limit}";
        }

        $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

        echo "<div style='margin:8px 0'>{$pagertop}</div>";
        echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>\n";
        echo "<tr>
<td class='colhead'>#</td>
<td class='colhead' align='left'>Тема</td>
<td class='colhead' align='center'>Категория</td>
<td class='colhead' align='right'>Автор</td>
</tr>\n";

        $i = 0;
        while ($post = mysqli_fetch_assoc($res)) {
            $i++;

            // тема
            $rsTopic = sql_query("SELECT forumid, subject FROM topics WHERE id = " . sqlesc((int)$post['topicid'])) or sqlerr(__FILE__, __LINE__);
            $topic   = mysqli_fetch_assoc($rsTopic);
            if (!$topic) continue;

            // форум
            $rsForum = sql_query("SELECT name, minclassread, description FROM forums WHERE id = " . sqlesc((int)$topic['forumid'])) or sqlerr(__FILE__, __LINE__);
            $forum   = mysqli_fetch_assoc($rsForum);
            if (!$forum || $forum['name'] === '' || (int)$forum['minclassread'] > (int)get_user_class()) {
                $hits--; // недоступно — пропускаем
                continue;
            }

            // автор
            $rsUser = sql_query("SELECT username, class FROM users WHERE id = " . sqlesc((int)$post['userid'])) or sqlerr(__FILE__, __LINE__);
            $urow   = mysqli_fetch_assoc($rsUser);
            if (empty($urow['username'])) {
                $userHtml = "id: " . (int)$post['userid'];
            } else {
                $userHtml = "<a href='userdetails.php?id=" . (int)$post['userid'] . "'>" .
                            get_user_class_color((int)$urow['class'], htmlspecialchars($urow['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) .
                            "</a>";
            }

            $forumName        = htmlspecialchars((string)$forum['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $forumDescription = htmlspecialchars((string)$forum['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $subjectHtml      = format_comment((string)$topic['subject']); // у тебя уже есть фильтр

            $cl1 = ($i % 2 === 0) ? "class='f_row_on'"  : "class='f_row_off'";
            $cl2 = ($i % 2 === 0) ? "class='f_row_off'" : "class='f_row_on'";

            $pid = (int)$post['id'];
            $tid = (int)$post['topicid'];
            $added = htmlspecialchars((string)$post['added'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo "<tr>
<td {$cl2}>{$pid}</td>
<td {$cl1} align='left'><a title='{$added}' href='forums.php?action=viewtopic&amp;topicid={$tid}&amp;page=p{$pid}#{$pid}'><b>{$subjectHtml}</b></a></td>
<td {$cl2} align='center'><a href='forums.php?action=viewforum&amp;forumid=" . (int)$topic['forumid'] . "'><b>{$forumName}</b></a><br /><small>{$forumDescription}</small></td>
<td {$cl1} align='right'><b>{$userHtml}</b><br /> в {$added}</td>
</tr>\n";
        }

        echo "</table>\n";
        echo "<div style='margin:8px 0'>{$pagerbottom}</div>";

        echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
<tr><td class='colhead' align='center'>Найдено " . (int)$hits . " сообщени" . (((int)$hits !== 1) ? "я" : "е") . ".</td></tr></table>";
    }
}

// форма поиска (даже если пустой запрос)
echo "<form method='get' action='forums.php'>
<input type='hidden' name='action' value='search'>
<table border='1' cellspacing='0' cellpadding='5' width='100%'>
<tr>
  <td class='a'><b>Поиск</b></td>
  <td class='a' align='left'>
    <input type='text' size='55' name='keywords' value=\"" . htmlspecialchars($keywords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"><br>
    <span class='small'>Введите одно-два слова. Слова короче 3 символов игнорируются для полнотекстового поиска.</span>
  </td>
</tr>
<tr><td align='center' colspan='2'><input type='submit' value='Поиск' class='btn'></td></tr>
</table>
</form>";

stdfoot_f();
die;

    	
    }
      exit();
      break;
      
      
//////////////////
      
      
   case 'viewpost': {

///	echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'><tr><td class=colhead align=center><a class=\"altlink_white\" href=forums.php>На главную форума</a></strong> -  Поиск на форуме [в режиме тестирования]</td></tr></table>";

$id = (int) $_GET["id"];

if (!is_valid_id($id)){
stderr_f("Неверные данные","$id (id) - не число! Проверьте вводимые данные.");
die;
}

    $res = sql_query("SELECT * FROM topics WHERE id=(SELECT topicid FROM posts WHERE id=".sqlesc($id).")") or sqlerr(__FILE__,__LINE__);
    $arr = mysqli_fetch_assoc($res) or stderr_f("Форум ошибка", "Не найдено сообщение");

    $t_com_arr=$arr["t_com"];
    $locked = ($arr["locked"] == 'yes' ? "Да":"Нет");
    $subject = format_comment($arr["subject"]);
    $sticky = ($arr["sticky"] == "yes" ? "Да":"Нет");
    $forumid = $arr["forumid"];
    $topic_polls = $arr["polls"];
    $views=number_format($arr["views"]);
    
    $num_com = number_format(get_row_count("posts", "WHERE topicid=".sqlesc($arr["id"])));

 //   sql_query("UPDATE topics SET views = views + 1 WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);

    $res = sql_query("SELECT * FROM forums WHERE id=(SELECT forumid FROM posts WHERE id=".sqlesc($id).")") or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res) or die("Нет форума для id: ".$id);
    $forum = htmlspecialchars($arr["name"]);


    if ($CURUSER)
   $cll = get_user_class();
   else
   $cll = 0;
   
  if ($arr["minclassread"]<=$cll && !empty($arr["minclassread"])){
  stderr_f("Ошибка прав","Данная категория и ее сообщения недоступны к показу.");
  die;
  }

if (get_user_class()>= UC_MODERATOR && isset($_GET["ori"]))
$viworiginal = true;
else
$viworiginal = false;

$res2 = sql_query("SELECT p. *, u.username, u.class, u.last_access, u.ip, u.signatrue,u.forum_com, u.signature,u.avatar, u.title, u.enabled, u.warned, u.hiderating,u.uploaded,u.downloaded,u.donor, e.username AS ed_username,e.class AS ed_class,
(select count(*) FROM posts WHERE userid=p.userid) AS num_topuser
FROM posts p 
LEFT JOIN users u ON u.id = p.userid
LEFT JOIN users e ON e.id = p.editedby
WHERE p.id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);

$count = mysql_num_rows($res2);
if (empty($count))
stderr_f("Ошибка данных","Нет сообщения с таким id ($id).");

$arr = mysqli_fetch_assoc($res2);
$topicid = $arr["topicid"];

if ($viworiginal == true)
stdhead_f("Просмотр оригинального сообщения (до первого редактирования)");
else
stdhead_f("Просмотр сообщения");

echo "<div class=\"tcat_t\"><div class=\"tcat_r\"><div class=\"tcat_l\">
<div class=\"tcat_tl\"><div class=\"tcat_submenu\"><span class=smallfont>
<div class=\"tcat_popup\"><b>Просмотров</b>: ".$views."</div>
<div class=\"tcat_popup\" id=\"threadtools\"><b>Комментариев</b>: ".$num_com."</div>
<div class=\"tcat_popup\"><b>Важная</b>: ".$sticky."</div>
<div class=\"tcat_popup\" id=\"threadrating\"><b>Заблокирована</b>: ".$locked."</div>

".($viworiginal == true ? "<div class=\"tcat_popup\" id=\"threadrating\"><b>Оригинальное сообщение</b>: Да</div>":"")."

</span>";


echo "<table cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"tcat_name\">Сообщение из темы: <b><a href=\"".$DEFAULTBASEURL."/forums.php?action=viewtopic&topicid=".$topicid."\">".$subject." </a></b>
<br />Категория: <a name=\"poststop\" id=\"poststop\" href=\"".$DEFAULTBASEURL."/forums.php?action=viewforum&forumid=".$forumid."\">".$forum."</a>
".(get_user_class()>= UC_MODERATOR ? "<br /><a href='".$DEFAULTBASEURL."/forums.php?action=edittopic&topicid=".$topicid."'>Администрирование темы</a>":"")."
</td></tr>
</table>
<br class=\"tcat_clear\"/></div></div></div></div></div>";


$t_com="<textarea cols=\"115%\" rows=\"5\" readonly>".$t_com_arr."</textarea>";

if (get_user_class()>= UC_MODERATOR)
echo "<div align=\"center\" width=\"100%\"class=\"tcat_t\">
<div class=\"spoiler-wrap\" id=\"115\"><div class=\"spoiler-head folded clickable\">История этого топика (Тестовый режим)</div><div class=\"spoiler-body\" style=\"display: none;\">
$t_com
</div></div>
</div>";

	if ($CURUSER && get_date_time(gmtime() - 60) >= $CURUSER["forum_access"]){
	sql_query("UPDATE users SET forum_access = ".sqlesc(get_date_time())." WHERE id = ".sqlesc($CURUSER["id"])) or sqlerr(__FILE__, __LINE__);
    }
        
    echo "<div class=\"post_body\"><div id=\"posts\">";

      $ed_username = $arr["ed_username"];
      $ed_class= $arr["ed_class"];
      $postid = $arr["id"];
      $posterid = $arr["userid"];
      $added = ($arr['added']);
      $postername = $arr["username"];
      $posterclass = $arr["class"];

      if (empty($postername) && $posterid<>0)
      {
        $by = "<b>id</b>: $posterid";
      }
      elseif ($posterid==0 && empty($postername)) {
      	$by="<i>Сообщение от </i><font color=gray>[<b>System</b>]</font>";
      }
      else
      {
         $by = "<a href='".$DEFAULTBASEURL."/userdetails.php?id=".$posterid."'><b>" .get_user_class_color($posterclass,  $postername). "</b></a>";
      }
      
  
if ($posterid<>0 && !empty($postername)){
	     if (strtotime($arr["last_access"]) > gmtime() - 600) {
			     	$online = "online";
			     	$online_text = "На форуме";
			     } else {
			     	$online = "offline";
			     	$online_text = "Не на форуме";
			     }
	
		    if ($arr["downloaded"] > 0) {
			    	$ratio = $arr['uploaded'] / $arr['downloaded'];
			    	$ratio = number_format($ratio, 2);
			    } elseif ($arr["uploaded"] > 0) {
			    	$ratio = "Infinity";
			    } else {
			    	$ratio = "---";
			    }

if ($row["hiderating"]=="yes"){
$print_ratio="<b>+100%</b>";
} else
$print_ratio=$ratio;///: $ratio
} else {
unset($print_ratio);
unset($ratio);
unset($online_text);
unset($online);
}


if ($CURUSER["cansendpm"]=='yes' && ($CURUSER["id"]<>$posterid && $posterid<>0 && !empty($postername))){
$cansendpm=" <a href='".$DEFAULTBASEURL."/message.php?action=sendmessage&amp;receiver=".$posterid."'><img src='".$DEFAULTBASEURL."/pic/button_pm.gif' border=0 alt=\"Отправить сообщение\" ></a>";	
}
else {
unset($cansendpm);}


if ($arr["forum_com"]<>"0000-00-00 00:00:00" && !empty($postername)){
$ban="<div><b>Бан до </b>".$arr["forum_com"]."</div>";
} else unset($ban);

//if (get_user_class() >= UC_VIP){
$online_view="<img src=\"".$DEFAULTBASEURL."/pic/button_".$online.".gif\" alt=\"".$online_text."\" title=\"".$online_text."\" style=\"position: relative; top: 2px;\" border=\"0\" height=\"14\">";
//}

//if (get_user_class() > UC_MODERATOR){
$numb_view="<a title=\"Число, под которым это сообщение в базе: ".$postid."\" href='".$DEFAULTBASEURL."/forums.php?action=viewpost&id=".$postid."'>Постоянная ссылка для этого сообщения [<b>$postid</b>]</a>";	
//}

if (!empty($arr["avatar"])){
$avatar = ($CURUSER["id"]==$posterid ? "<a href=\"".$DEFAULTBASEURL."/my.php\"><img alt=\"Аватар, по клику переход в настройки\" title=\"Аватар, по клику переход в настройки\" width=80 height=80 src=\"".$DEFAULTBASEURL."/pic/avatar/".$arr["avatar"]."\"/></a>":"<img width=80 height=80 src=\"".$DEFAULTBASEURL."/pic/avatar/".$arr["avatar"]."\"/>");
} else
$avatar = "<img width=80 height=80 src=\"".$DEFAULTBASEURL."/pic/avatar/default_avatar.gif\"/>";

if ($viworiginal == true)
$body = format_comment($arr["body_orig"]);
else
$body = format_comment($arr["body"]);

echo("<a name=".$postid.">\n");

if ($pn == $pc){
echo("<a name=last>\n");
}


echo "<div>
<table cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
<tr>
<td class=\"postbit_top\">
<div class=\"postbit_head\" >
<div class=\"normal\" style=\"float:right\">".$numb_view."</div>
<div class=\"normal\">".normaltime(($arr['added']),true)."</div>
</div>
      
<table cellpadding=\"0\" cellspacing=\"10\" width=\"100%\">
<tr>
<td>".$avatar."</td>
<td nowrap=\"nowrap\">
<div>".$by." ".$online_view.$cansendpm."
</div>
<div class=\"smallfont\">".get_user_class_name($arr["class"])." ".(empty($arr["title"]) ? "": "(".htmlspecialchars($arr["title"]).")")."<br />
".($arr["donor"] == "yes" ? "<img src=\"".$DEFAULTBASEURL."/pic/star.gif\" alt='Донор'>" : "")
.($arr["enabled"] == "no" ? "<img src=\"".$DEFAULTBASEURL."/pic/disabled.gif\" alt=\"Этот аккаунт отключен\" style='margin-left: 2px'>" : ($arr["warned"] == "yes" ? "<img src=\"".$DEFAULTBASEURL."/pic/warned.gif\" alt=\"Предупрежден\" border=0>" : "")) . "
</div>
</td>

<td width=\"100%\">&nbsp;</td>
<td valign=\"top\" nowrap=\"nowrap\" class=\"n_postbit_info\"> 

<table cellpadding=\"0\" cellspacing=\"10\" width=\"100%\">
<tr>
<td valign=\"top\" nowrap=\"nowrap\"><div class=\"smallfont\"></div></td>
<td valign=\"top\" nowrap=\"nowrap\">
<div class=\"smallfont\">
<div><b>Рейтинг</b>:  ".$print_ratio."</div>
<div ><b>Залил</b>: ".mksize($arr["uploaded"]) ." </div>
<div><b>Скачал</b>: ".mksize($arr["downloaded"])."</div>
".$ban."
".($CURUSER? "<b>Сообщений на форуме</b>: <a href=\"".$DEFAULTBASEURL."/forums.php?action=search_post&userid=".$posterid."\" title=\"Поиск всех сообщений у ".$postername."\">".$arr["num_topuser"]."</a>":"")."
</div>
</td></tr></table>

<img src=\"/pic/forumicons/clear.gif\" alt=\"\" width=\"225\" height=\"1\" border=\"0\" />
</td></tr>
</table>
</td>
</tr>
<tr>
<td class=\"alt1\"> 
<hr size=\"1\" style=\"color:; background-color:\" />
<div class=\"pad_12\">
<div class=\"img_rsz\">".$body."</div>

".((is_valid_id($arr['editedby']) && !empty($arr['editedby'])) ? "<hr>
<div class=\"post_edited smallfont\">
<em>Последний раз редактировалось ".(get_user_class() >= UC_MODERATOR ? "<a href='".$DEFAULTBASEURL."/userdetails.php?id=".$arr["editedby"]."'><b> ".get_user_class_color($ed_class, $ed_username)." </b></a>":"")." в <span class=\"time\">".($arr['editedat'])."</span>. ".($viworiginal == true ? "<a href='".$DEFAULTBASEURL."/forums.php?action=viewpost&id=".$postid."&ori'>К Оригинальному сообщению.</a>":"")."</em>
</div>
":"")."
      
<div style=\"margin-top: 10px\" align=\"right\">
".(($arr["signatrue"]=="yes" && $arr["signature"]) ? "  <span class=\"smallfont\">".format_comment($arr["signature"])."</span>": "")."
</div>
</div>
</td>


</tr>
</table>
<div class=\"pad_12 alt1\" style=\"border-top: 1px solid #ccc;\">
<table cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
<tr>";

echo "<td class=\"alt2\" align=right><span class=smallfont>";

if (get_user_class() > UC_MODERATOR)
echo "[<a title=\"Удалить сообщение (тему - если первое сообщение) и забанить пользователя\" href=\"".$DEFAULTBASEURL."/forums.php?action=banned&userid=".$posterid."&postid=".$postid."\" ><b>СПАМ</b></a>] ";

if (!empty($posterid)){
echo (!empty($posterid) ? "[<a href='".$DEFAULTBASEURL."/forums.php?action=search_post&userid=".$posterid."'><b>Найти все сообщения</b></a>] ":"");
}

if ($CURUSER){
echo (!empty($posterid) ? "[<a href='".$DEFAULTBASEURL."/userdetails.php?id=".$posterid."'><b>Профиль</b></a>] ":"");
}

if ($CURUSER && $CURUSER["id"]<>$posterid){
echo (!empty($posterid) ? "[<a href='".$DEFAULTBASEURL."/message.php?receiver=".$posterid."&action=sendmessage'><b>Послать Сообщение</b></a>] ":"");
}

if ($CURUSER && $CURUSER["id"]<>$posterid){
echo ($posterid<>0 && !empty($postername) && $CURUSER["forum_com"]=="0000-00-00 00:00:00" ? "[<a href='".$DEFAULTBASEURL."/forums.php?action=quotepost&topicid=".$topicid."&postid=".$postid."'><b>Цитировать</b></a>] ":"");
}
  
if (get_user_class() >= UC_MODERATOR)
echo ($arr["ip"] ? "[<a title=\"Искать этот ip адресс в базе данных через административный поиск\" href=\"".$DEFAULTBASEURL."/usersearch.php?ip=".$arr["ip"]."\" ><b>".$arr["ip"]."</b></a>] ":"");
  
if (($CURUSER["forum_com"]=="0000-00-00 00:00:00" && $CURUSER["id"]==$posterid) || get_user_class() >= UC_MODERATOR){
echo "[<a href='".$DEFAULTBASEURL."/forums.php?action=editpost&postid=".$postid."'><b>Редактировать</b></a>] ";
}

if (get_user_class() >= UC_MODERATOR || $CURUSER["id"]==$posterid)
echo "[<a href='".$DEFAULTBASEURL."/forums.php?action=deletepost&postid=".$postid."'><b>Удалить</b></a>] ";

echo "</span></td>";

echo "</tr></table></div></div>";



	stdfoot_f();
	die;
}
      exit();
      break;
//////////////////////////////     
      
    default:
      std_view();
      break;
  }


function std_view() {

  global $Forum_Config, $CURUSER,$SITENAME,$DEFAULTBASEURL;
  
  $forum_pic_url=$DEFAULTBASEURL."/pic/forumicons/";

  $added = get_date_time();
 // $forums_res = sql_query("SELECT f.sort, f.id, f.name, f.description, f.minclassread, f.minclasswrite, f.minclasscreate, p.added, p.topicid AS topicidi, p.userid, u.username,u.class, t.subject, top.lastpost, top.lastdate,(SELECT COUNT(*) FROM topics WHERE forumid=f.id) AS numtopics, (SELECT COUNT(*) FROM posts WHERE forumid=f.id) AS numposts   FROM forums AS f LEFT JOIN topics AS top ON top.lastdate = (SELECT MAX(lastdate) FROM topics WHERE forumid=f.id) LEFT JOIN posts AS p ON p.id = top.lastpost LEFT JOIN users u ON p.userid = u.id LEFT JOIN topics t ON p.topicid = t.id   ORDER BY sort, name") or sqlerr(__FILE__, __LINE__);
/// GROUP BY sort, name 
/// LOW_PRIORITY 
//  stdhead_f("Форум [2.76* версия за 18 января 2010]");

  if ($CURUSER && get_date_time(gmtime() - 60) >= $CURUSER["forum_access"]){
	sql_query("UPDATE users SET forum_access = ".sqlesc(get_date_time())." WHERE id = ".sqlesc($CURUSER["id"])."") or sqlerr(__FILE__, __LINE__);
  }

// Блок «новые сообщения» — безопасно, без Undefined array key
$newmessage = '';
if (!empty($CURUSER)) {
    $unread = isset($CURUSER['unread']) ? (int)$CURUSER['unread'] : 0;

    // простая форма склонения — чтобы не городить: 0/1 -> "новое", >1 -> "новых"
    $newmessage1 = $unread . ' нов' . ($unread === 1 ? 'ое' : 'ых');
    $newmessage2 = ' сообщен' . ($unread === 1 ? 'ие' : 'ий');

    if ($unread > 0) {
        $newmessage = "<b><a href='" . $DEFAULTBASEURL . "/message.php?action=new'>У вас " . $newmessage1 . ' ' . $newmessage2 . "</a></b>";
    }
}


echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Frameset//EN\"  \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd\">
<html>
<head>
".meta_forum()."
<link rel=\"stylesheet\" type=\"text/css\" href=\"".$DEFAULTBASEURL."/js/style_forums.css\" />
<link rel=\"search\" type=\"application/opensearchdescription+xml\" title=\"Muz-Tracker Форум\" href=\"".$DEFAULTBASEURL."/js/forum.xml\">
<script language=\"javascript\" type=\"text/javascript\" src=\"".$DEFAULTBASEURL."/js/jquery.js\"></script>
<script language=\"javascript\" type=\"text/javascript\" src=\"".$DEFAULTBASEURL."/js/forums.js\"></script>
<script language=\"javascript\" type=\"text/javascript\" src=\"".$DEFAULTBASEURL."/js/swfobject.js\"></script> 
<script language=\"javascript\" type=\"text/javascript\" src=\"".$DEFAULTBASEURL."/js/functions.js\"></script>
<script language=\"javascript\" type=\"text/javascript\" src=\"".$DEFAULTBASEURL."/js/tooltips.js\"></script>

<title>Форум - ".$SITENAME."</title>

</head>
 
<table cellpadding=\"0\" cellspacing=\"0\" id=\"main\">
<tr>
<td class=\"main_col1\"><img src=\"".$forum_pic_url."clear.gif\" alt=\"\" /></td>
<td class=\"main_col2\"><img src=\"".$forum_pic_url."clear.gif\" alt=\"\" /></td>
<td class=\"main_col3\"><img src=\"".$forum_pic_url."clear.gif\" alt=\"\" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign=\"top\">
<table cellpadding=\"0\" cellspacing=\"0\" id=\"header\">
<tr>
<td id=\"logo\">".LOGO."</td>

<td class=\"login\">
<div id=\"login_box\"><span class=smallfont>
<div>Здравствуйте, ".($CURUSER ? "<a href='".$DEFAULTBASEURL."/userdetails.php?id=".$CURUSER["id"]."'>".$CURUSER["username"]."</a>
<div>Последнее обновление: <span class=\"time\">".$CURUSER["forum_access"]."</span></div>
".($CURUSER ? "<div>".$newmessage."</div>":"")."
":" для просмотра полной версии данных,  
<div>пожалуйста, <a href='".$DEFAULTBASEURL."/login.php'>авторизуйтесь</a>.
<div>Права просмотра: Гость</div>
</div>
")."</span></div>
</div>
</td>
</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>
<tr>
<td>&nbsp;</td>

<td>
<table cellpadding=\"0\" cellspacing=\"0\" id=\"menu_h\">
<tr>
<td class=\"first\"><a href=\"".$DEFAULTBASEURL."/index.php\">Главная сайта</a></td> 
<td class=\"shad\"><a href=\"".$DEFAULTBASEURL."/browse.php\">Торренты</a></td> 
<td class=\"shad\"><a href=\"".$DEFAULTBASEURL."/forums.php\">Главная форума</a></td>

".($CURUSER ? "<td class=\"shad\"><a href=\"".$DEFAULTBASEURL."/forums.php?action=search\">Поиск</a></td>
<td class=\"shad\"><a href=\"".$DEFAULTBASEURL."/forums.php?action=viewunread\">Непрочитанные комментарии</a></td>
<td class=\"shad\"><a title=\"Поменить все сообщения прочитанными\" href=\"".$DEFAULTBASEURL."/forums.php?action=catchup\">Все как прочитанное</a></td>":"")."

</tr>
</table>
</td>
<td>&nbsp;</td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign=\"top\">
<table cellpadding=\"0\" cellspacing=\"0\" id=\"content_s\">
<tr>
<td class=\"content_col1\"><img src=\"".$forum_pic_url."clear.gif\" alt=\"\" /></td>
<td class=\"content_col_left\">&nbsp;</td>
<td class=\"content_col5\"><img src=\"".$forum_pic_url."clear.gif\" alt=\"\" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign=\"top\">
<br />
";
///<a name=\"poststop\" id=\"poststop\" href=\"".$DEFAULTBASEURL."/forums.php\">Главная страничка форума</a> <hr>


echo "
<div class=\"tcat_t\"><div class=\"tcat_r\"><div class=\"tcat_l\"><div class=\"tcat_tl\"><div class=\"tcat_simple\">

<table cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"tcat_name\">
<h1>Встроенный Форум сайта ".$SITENAME." </h1><font color=\"white\">(v. 2.0 beta:08.08.11)</font> ".(get_user_class() == UC_SYSOP ? "[<a class=\"altlink_white\" href=\"".$DEFAULTBASEURL."/forummanage.php\">Администрировать категории / Создать новую категорию</a>]":"")."

</td></tr></table>
<br class=\"tcat_clear\" />
</div></div></div></div></div>
<div class=\"post_body\" id=\"collapseobj_forumbit_5\" style=\"\">
<table cellspacing=\"0\" cellpadding=\"0\" class=\"forums\">
<tr>
<td class=\"f_thead_1\">Статус</td>
<td class=\"f_thead_2\">Категория</td>
</tr>";

// ====== Список форумов (с Memcached) ======
$da = 0;
global $memcached; // инициализирован раньше
$canMC = ($memcached instanceof Memcached);

// Ключ списка форумов + lastpost-меты
$listKey = "forum:list:main:v1";
$rows = $canMC ? $memcached->get($listKey) : false;
if ($rows === false || !is_array($rows)) {
    $res = sql_query("
        SELECT
          f.sort, f.id, f.name, f.description,
          f.minclassread, f.minclasswrite, f.minclasscreate,
          p.added, p.topicid AS topicidi, p.userid, u.username, u.class,
          t.subject, top.lastpost, top.locked, top.lastdate,
          (SELECT COUNT(*) FROM topics WHERE forumid = f.id)  AS numtopics,
          (SELECT COUNT(*) FROM posts  WHERE forumid = f.id)  AS numposts
        FROM forums AS f
        LEFT JOIN topics AS top
          ON top.lastdate = (
               SELECT MAX(lastdate)
               FROM topics
               WHERE forumid = f.id AND visible = 'yes'
             )
         AND top.visible = 'yes'
        LEFT JOIN posts  AS p ON p.id = top.lastpost
        LEFT JOIN users  AS u ON p.userid = u.id
        LEFT JOIN topics AS t ON p.topicid = t.id AND t.visible = 'yes'
        ORDER BY f.sort, f.name
    ") or sqlerr(__FILE__, __LINE__);

    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    if ($canMC) $memcached->set($listKey, $rows, 30); // короткий TTL
}

// текущий класс юзера
$uClass   = function_exists('get_user_class') ? (int)get_user_class() : 0;
$isGuest  = empty($CURUSER);

// Нарисуем таблицу
if (!empty($rows)) {
    foreach ($rows as $forums_arr) {
        // счётчики
        $topiccount = (int)($forums_arr['numtopics'] ?? 0);
        $postcount  = (int)($forums_arr['numposts']  ?? 0);

        $forumid          = (int)$forums_arr['id'];
        $forumname        = htmlspecialchars((string)$forums_arr['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $forumdescription = htmlspecialchars((string)($forums_arr['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $topiccount_s = $topiccount ? number_format($topiccount) : "нет";
        $postcount_s  = $postcount  ? number_format($postcount)  : "нет";

        // последний пост по форуму (может отсутствовать)
        $lastpostid   = (int)($forums_arr['lastpost']  ?? 0);
        $lasttopicid  = (int)($forums_arr['topicidi']  ?? 0);
        $lastposterid = (int)($forums_arr['userid']    ?? 0);
        $lastpostdate = !empty($forums_arr['added']) ? normaltime($forums_arr['added'], true) : '';
        $lastposter   = htmlspecialchars((string)($forums_arr['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lasttopic    = !empty($forums_arr['subject']) ? format_comment_light((string)$forums_arr['subject']) : '';

        // кто писал
        if ($lastposterid === 0 && $lastposter === '') {
            $view_user = "<span style='color:gray'>[System]</span>";
        } elseif ($lastposterid !== 0 && $lastposter === '') {
            $view_user = "<i><b>id</b>: {$lastposterid}</i>";
        } else {
            $view_user = "<a href=\"{$DEFAULTBASEURL}/userdetails.php?id={$lastposterid}\">" .
                         "<b>" . get_user_class_color((int)($forums_arr['class'] ?? 0), $lastposter) . "</b></a> ".
                         "<a href=\"forums.php?action=search_post&amp;userid={$lastposterid}\"><img title=\"Искать все сообщения этого пользователя\" src=\"{$DEFAULTBASEURL}/pic/pm.gif\" alt='pm'></a>";
        }

        // проверка «новых сообщений» (только если есть lastpost)
        $npostcheck = 0;
        if (!empty($CURUSER) && $lasttopicid > 0 && $lastpostid > 0) {
            $recentThreshold = get_date_time(gmtime() - (int)$Forum_Config['readpost_expiry']);
            if (!empty($forums_arr['added']) && $forums_arr['added'] > $recentThreshold) {
                $r = sql_query(
                    "SELECT lastpostread
                       FROM readposts
                      WHERE userid  = " . sqlesc((int)$CURUSER['id']) . "
                        AND topicid = " . sqlesc($lasttopicid) . "
                      LIMIT 1"
                ) or sqlerr(__FILE__, __LINE__);
                $a = mysqli_fetch_row($r);
                $npostcheck = (!$a || $lastpostid > (int)$a[0]) ? 1 : 0;
            }
        }

        // права
        $minRead   = (int)($forums_arr['minclassread']   ?? 0);
        $minWrite  = (int)($forums_arr['minclasswrite']  ?? 0);
        $minCreate = (int)($forums_arr['minclasscreate'] ?? 0);

        // Показывать строку форума? (чтение разрешено)
        $canRead = ($uClass >= $minRead) || ($minRead === 0 && $isGuest);
        if (!$canRead) continue;

        // Можно писать/создавать?
        $canWrite  = !$isGuest && $uClass >= $minWrite;
        $canCreate = !$isGuest && $uClass >= $minCreate;

        // статусная иконка (new/locked)
        $now_2day = get_date_time(gmtime() - 86400);
        $isNew    = (!empty($forums_arr['added']) && $forums_arr['added'] > $now_2day);
        $locked   = (string)($forums_arr['locked'] ?? 'no') === 'yes';
        $topicpic = $locked ? ($isNew ? "lockednew" : "locked") : ($isNew ? "unlockednew" : "unlocked");

        // Чёт/нечет строка
        $class = ($da % 2 === 1) ? "f_row_off" : "f_row_on";

        // ссылка «создать тему», если можно
        $createLink = $canCreate
            ? " <span class=\"smallfont\">[ <a href=\"{$DEFAULTBASEURL}/forums.php?action=newtopic&amp;forumid={$forumid}\">Создать тему</a> ]</span>"
            : "";

        // рендер
        echo "<tr>
<td class=\"{$class}\"><img title=\"Статус новых сообщений\" src=\"{$forum_pic_url}{$topicpic}.gif\" alt='st'></td>
<td class=\"{$class}\" id=\"f{$forumid}\">
  <div>
    <a href=\"{$DEFAULTBASEURL}/forums.php?action=viewforum&amp;forumid={$forumid}\"><b>{$forumname}</b></a>
    <span class=\"smallfont\">(Тем <b>{$topiccount_s}</b>, Сообщений <b>{$postcount_s}</b>)</span>{$createLink}
  </div>
  <div class=\"smallfont\">{$forumdescription}</div>
  " . (empty($lastpostid) ? "
  <div class=\"last_post_cl\"><div class=\"smallfont\">Сообщений нет.</div></div>
  " : "
  <div class=\"last_post_cl\">
    <div class=\"smallfont\">
      Последний пост &#8594; <a href=\"{$DEFAULTBASEURL}/forums.php?action=viewtopic&amp;topicid={$lasttopicid}&amp;page=last#{$lastpostid}\"><strong>{$lasttopic}</strong></a>
      от {$view_user} <span class=\"time\">{$lastpostdate}</span>
      " . ($npostcheck ? " <span title='Есть непрочитанные'>&#128276;</span>" : "") . "
    </div>
  </div>
  ") . "
</td>
</tr>";

        $da++;
    }
}


}

/// статистика (Memcached)
if (!empty($CURUSER)) {
    global $memcached; // уже инициализирован выше
    $uid = (int)$CURUSER['id'];

    // Ключи кэша
    $k_user_posts   = "forums:stats:user_posts:{$uid}";
    $k_total_posts  = "forums:stats:total_posts:v1";
    $k_edited_posts = "forums:stats:edited_posts:v1";
    $k_dist_users   = "forums:stats:distinct_users:v1";

    // TTL
    $TTL_USER = 600;       // 10 минут — персональная метрика
    $TTL_ALL  = 86400;     // 1 сутки — агрегаты

    // --- 1) Сколько постов у текущего пользователя ---
    $my_user = $memcached->get($k_user_posts);
    if ($my_user === false && $memcached->getResultCode() !== Memcached::RES_SUCCESS) {
        $res = sql_query("SELECT COUNT(*) AS c FROM posts WHERE userid = " . sqlesc($uid));
        $row = mysqli_fetch_assoc($res) ?: ['c' => 0];
        $my_user = (int)$row['c'];
        $memcached->set($k_user_posts, $my_user, $TTL_USER);
    }
    // совместимость со старым кодом
    $num_p1 = ['my_user' => number_format($my_user)];

    // --- 2) Общие посты и «отредактированные» (editedat > 1970-01-01) ---
    $total_posts = $memcached->get($k_total_posts);
    $edited_posts = $memcached->get($k_edited_posts);

    if (($total_posts === false && $memcached->getResultCode() !== Memcached::RES_SUCCESS) ||
        ($edited_posts === false && $memcached->getResultCode() !== Memcached::RES_SUCCESS)) {

        // Одним запросом посчитаем оба значения
        $sql = "
            SELECT
                COUNT(*) AS cou,
                SUM(editedat > '1970-01-01 00:00:00') AS mod_cou
            FROM posts
        ";
        $res = sql_query($sql);
        $row = mysqli_fetch_assoc($res) ?: ['cou' => 0, 'mod_cou' => 0];

        $total_posts  = (int)$row['cou'];
        $edited_posts = (int)$row['mod_cou'];

        $memcached->set($k_total_posts,  $total_posts,  $TTL_ALL);
        $memcached->set($k_edited_posts, $edited_posts, $TTL_ALL);
    }
    $num_p = [
        'cou'     => number_format($total_posts),
        'mod_cou' => number_format($edited_posts),
    ];

    // --- 3) Сколько уникальных пользователей писали посты ---
    $distinct_users = $memcached->get($k_dist_users);
    if ($distinct_users === false && $memcached->getResultCode() !== Memcached::RES_SUCCESS) {
        $res = sql_query("SELECT COUNT(DISTINCT userid) AS c FROM posts");
        $row = mysqli_fetch_assoc($res) ?: ['c' => 0];
        $distinct_users = (int)$row['c'];
        $memcached->set($k_dist_users, $distinct_users, $TTL_ALL);
    }
    $num_p2 = ['cou_user' => number_format($distinct_users)];
}



// ====== СТАТИСТИКА (приведение типов + защита от 0) ======
$val = static function($v): int {
    // безопасно превращаем '12,345' или '12 345' в 12345
    if ($v === null) return 0;
    $s = preg_replace('/[^\d]/u', '', (string)$v);
    return (int)$s;
};

$num_p_f    = $val($num_p['cou']      ?? 0); // всего постов
$num_p_mod  = $val($num_p['mod_cou']  ?? 0); // отредактированных
$num_p_u    = $val($num_p2['cou_user']?? 0); // уникальных авторов
$num_p_m    = $val($num_p1['my_user'] ?? 0); // постов у текущего юзера

// удаляем битую строку с несуществующей переменной
// $num_cat_id=$num_cat_sql["name"]; // <-- её нет и не нужна

// дни с момента регистрации (минимум 1)
$time_user = isset($CURUSER['added']) ? sql_timestamp_to_unix_timestamp($CURUSER['added']) : time();
$time_now  = time();
$diff      = abs($time_now - $time_user);
$day       = max(1, (int)floor($diff / 86400));

// расчёты
$avg_per_day = $num_p_m / $day;
$share_all   = ($num_p_f > 0) ? (100.0 * $num_p_m / $num_p_f) : 0.0;

// строки для вывода
$fmt = static fn($n) => number_format($n, 0, '.', ' ');
$fmt2= static fn($n) => number_format($n, 2, '.', ' ');

echo "
</table>
</div>
<div class=\"off\">
<div class=\"tcat_b\"><div class=\"tcat_bl\"><div class=\"tcat_br\"></div></div></div>
</div><br />
";

if (!empty($CURUSER) && !empty($da)) {
    echo "<div class=\"tcat_t\"><div class=\"tcat_r\"><div class=\"tcat_l\"><div class=\"tcat_tl\"><div class=\"tcat_simple\">
<table cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"tcat_name\">
Статистика форума</td></tr></table>

<br class=\"tcat_clear\" />
</div></div></div></div></div>
 
<div class=\"post_body\">
<div class=\"f_dark\">
<b>»</b> Всего Ваших сообщений на форуме: <b>".$fmt($num_p_m)."</b> | <b>".$fmt2($avg_per_day)."</b> сообщений в день.<br/>
<b>»</b> У вас <b>".$fmt2($share_all)."%</b> от всех сообщений на форуме.<br/><br/>
<b>» »</b> Всего сообщений на форуме: <b>".$fmt($num_p_f)."</b> (отредактированных: <b>".$fmt($num_p_mod)."</b>)<br /> 
<b>» »</b> Оставлены пользователями: <b>".$fmt($num_p_u)."</b><br/>
</div>
</div>
<div class=\"on\">
<div class=\"tcat_b\"><div class=\"tcat_bl\"><div class=\"tcat_br\"></div></div></div>
</div><br />
";
}

if (empty($da)) {
    echo "<div class=\"tcat_t\"><div class=\"tcat_r\"><div class=\"tcat_l\"><div class=\"tcat_tl\"><div class=\"tcat_simple\">
<table cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"tcat_name\">
Нет категорий выше и желаете активировать ? см ниже совет:</td></tr></table>

<br class=\"tcat_clear\" />
</div></div></div></div></div>
 
<div class=\"post_body\">
<div class=\"f_dark\">
Если хотите создать сообщение, выберите ниже в поле <strong>Быстрый переход:</strong> нужную вам категорию, там будет .::: <strong>Создать тему для обсуждения</strong> :::.
</div>
</div>
<div class=\"on\">
<div class=\"tcat_b\"><div class=\"tcat_bl\"><div class=\"tcat_br\"></div></div></div>
</div><br />
";
}

insert_quick_jump_menu(false); // или null/0 — без предвыбора
stdfoot_f();
die;


?>