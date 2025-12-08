<?php
declare(strict_types=1);

require_once "include/bittorrent.php";
dbconn(false);
gzip();
loggedinorreturn();

global $CURUSER, $mysqli, $tracker_lang;

/** =========================================================
 *  УТИЛИТЫ
 * ========================================================= */

// Универсальная экранизация (если в кодовой базе уже есть e(), можно удалить эту заглушку)
if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Универсальный кэш-helpers: Memcached → Memcache → APCu */
function mc_get(string $key) {
    // Memcached (объектный)
    if (isset($GLOBALS['memcached']) && $GLOBALS['memcached'] instanceof Memcached) {
        $val = @$GLOBALS['memcached']->get($key);
        return $GLOBALS['memcached']->getResultCode() === Memcached::RES_SUCCESS ? $val : null;
    }
    // Memcache (процедурный/объектный)
    if (isset($GLOBALS['Memcache'])) {
        $val = @memcache_get($GLOBALS['Memcache'], $key);
        return $val === false ? null : $val;
    }
    // APCu
    if (function_exists('apcu_fetch')) {
        $ok = false;
        $val = apcu_fetch($key, $ok);
        return $ok ? $val : null;
    }
    return null;
}

function mc_set(string $key, $value, int $ttl = 300): void {
    if (isset($GLOBALS['memcached']) && $GLOBALS['memcached'] instanceof Memcached) {
        @$GLOBALS['memcached']->set($key, $value, $ttl);
        return;
    }
    if (isset($GLOBALS['Memcache'])) {
        @memcache_set($GLOBALS['Memcache'], $key, $value, 0, $ttl);
        return;
    }
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, $ttl);
    }
}

/** Безопасный PHP_SELF для pager */
function self_url_prefix(): string {
    $self = $_SERVER['PHP_SELF'] ?? '';
    // иногда в старых проектах PHP_SELF может содержать query — нормализуем
    $self = explode('?', $self, 2)[0];
    return e($self);
}

/** Кешируем HTML «карточки» юзернейма на 5 минут */
function get_username(int $userid): string {
    $cacheKey = "u:card:{$userid}";
    if (($html = mc_get($cacheKey)) !== null) return $html;

    $userid = (int)$userid;
    $res = sql_query("SELECT username, donor, warned, enabled FROM users WHERE id = $userid LIMIT 1");
    if ($res && mysqli_num_rows($res) === 1) {
        $u = mysqli_fetch_assoc($res);
        $html = "<a href='userdetails.php?id={$userid}'><b>" . e($u['username']) . "</b></a>" . get_user_icons($u, true);
    } else {
        $html = "unknown[" . $userid . "]";
    }
    mc_set($cacheKey, $html, 300);
    return $html;
}

/** =========================================================
 *  ВХОДНЫЕ ПАРАМЕТРЫ / ДОСТУП
 * ========================================================= */
$userid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!is_valid_id($userid)) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Неверный ID");
}

if ((int)$CURUSER["id"] !== $userid && get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Нет доступа");
}

$action  = $_GET["action"] ?? '';
$perpage = 25;

/** =========================================================
 *  VIEW POSTS
 * ========================================================= */
if ($action === "viewposts") {
    $subject = get_username($userid);

    // Только доступные форумы в COUNT(*)
    $from  = "posts p
              JOIN topics t ON p.topicid = t.id
              JOIN forums f ON t.forumid = f.id";
    $where = "p.userid = $userid AND f.minclassread <= " . (int)$CURUSER['class'];

    $res = sql_query("SELECT COUNT(*) FROM {$from} WHERE {$where}");
    [$postcount] = $res ? (mysqli_fetch_row($res) ?: [0]) : [0];
    if ((int)$postcount === 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Посты не найдены");
    }

    [$pagertop, $pagerbottom, $limit] = pager(
        $perpage,
        (int)$postcount,
        self_url_prefix() . "?action=viewposts&id=$userid&"
    );

    // читаем состояние «прочитано» для текущего пользователя
    $select = "
        f.id   AS f_id,
        f.name AS forum_name,
        t.id   AS t_id,
        t.subject,
        t.lastpost,
        r.lastpostread,
        p.id, p.added, p.body, p.editedby, p.editedat,
        ue.username AS editor_name
    ";
    $from2 = "{$from}
              LEFT JOIN readposts r
                     ON r.topicid = t.id
                    AND r.userid  = " . (int)$CURUSER['id'] . "
              LEFT JOIN users ue ON ue.id = p.editedby";

    $res = sql_query("SELECT {$select} FROM {$from2} WHERE {$where} ORDER BY p.id DESC {$limit}");
    if (!$res || mysqli_num_rows($res) === 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Посты не найдены");
    }

    stdhead("История постов");
    echo "<h1>История постов для {$subject}</h1>";
    if ($postcount > $perpage) echo $pagertop;

    begin_main_frame();
    begin_frame();

    while ($row = mysqli_fetch_assoc($res)) {
        $postid    = (int)$row["id"];
        $topicid   = (int)$row["t_id"];
        $forumid   = (int)$row["f_id"];
        $topicname = e($row["subject"] ?? '');
        $forumname = e($row["forum_name"] ?? '');

        $addedStr = $row["added"] ?? '';
        $addedGmt = e($addedStr);
        $addedTs  = $addedStr ? sql_timestamp_to_unix_timestamp($addedStr) : 0;
        $ago      = $addedTs ? get_elapsed_time($addedTs) : '';

        $lastread = isset($row["lastpostread"]) ? (int)$row["lastpostread"] : 0;
        $isNew    = $lastread < (int)$row["lastpost"];

        echo "<div class='post-card' style='margin:12px 0;padding:0;'>
                <table class='main' border='0' cellspacing='0' cellpadding='0' width='100%'>
                  <tr><td class='embedded'>
                    {$addedGmt} GMT" . ($ago !== '' ? " (" . e($ago) . " назад)" : "") . "
                    &nbsp;—&nbsp;<b>Форум:</b> <a href='forums.php?action=viewforum&forumid={$forumid}'>" . $forumname . "</a>
                    &nbsp;—&nbsp;<b>Тема:</b> <a href='forums.php?action=viewtopic&topicid={$topicid}'>" . $topicname . "</a>
                    &nbsp;—&nbsp;<b>Пост:</b> #<a href='forums.php?action=viewtopic&topicid={$topicid}&page=p{$postid}#{$postid}'>" . $postid . "</a>" .
                    ($isNew ? " &nbsp;<b><span style='color:#c00'>(НОВЫЙ!)</span></b>" : "") . "
                  </td></tr>
                </table>";

        begin_table(true);
        $body = format_comment($row["body"] ?? '');

        if (is_valid_id((int)$row['editedby']) && !empty($row['editor_name'])) {
            $editor   = e($row['editor_name']);
            $editedat = e($row['editedat'] ?? '');
            if ($editedat !== '') {
                $body .= "<p><span class='small' style='font-size:11px;color:#666'>
                         Последний раз редактировалось
                         <a href='userdetails.php?id=".(int)$row['editedby']."'><b>{$editor}</b></a>
                         в {$editedat} GMT</span></p>";
            }
        }

        echo "<tr valign='top'><td class='comment'>{$body}</td></tr>";
        end_table();
        echo "</div>";
    }

    end_frame();
    end_main_frame();
    if ($postcount > $perpage) echo $pagerbottom;
    stdfoot();
    exit;
}

/** =========================================================
 *  VIEW COMMENTS
 * ========================================================= */
if ($action === "viewcomments") {
    $subject = get_username($userid);

    $from  = "comments c LEFT JOIN torrents t ON c.torrent = t.id";
    $where = "c.user = $userid";

    $res = sql_query("SELECT COUNT(*) FROM {$from} WHERE {$where}");
    [$commentcount] = $res ? (mysqli_fetch_row($res) ?: [0]) : [0];
    if ((int)$commentcount === 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Комментарии не найдены");
    }

    [$pagertop, $pagerbottom, $limit] = pager(
        $perpage,
        (int)$commentcount,
        self_url_prefix() . "?action=viewcomments&id=$userid&"
    );

    $select = "c.id, c.added, c.text, c.torrent AS t_id, t.name AS t_name";
    $res = sql_query("SELECT {$select} FROM {$from} WHERE {$where} ORDER BY c.id DESC {$limit}");
    if (!$res || mysqli_num_rows($res) === 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Комментарии не найдены");
    }

    stdhead("История комментариев");
    echo "<h1>История комментариев для {$subject}</h1>";
    if ($commentcount > $perpage) echo $pagertop;

    begin_main_frame();
    begin_frame();

    while ($row = mysqli_fetch_assoc($res)) {
        $commentid = (int)$row["id"];
        $torrentid = (int)$row["t_id"];

        $name = $row["t_name"] ?? "[Удален]";
        $name = mb_strlen($name) > 55 ? mb_substr($name, 0, 52) . "..." : $name;
        $name = e($name);

        $addedStr = $row["added"] ?? '';
        $addedGmt = e($addedStr);
        $addedTs  = $addedStr ? sql_timestamp_to_unix_timestamp($addedStr) : 0;
        $ago      = $addedTs ? get_elapsed_time($addedTs) : '';

        echo "<div class='comment-card' style='margin:12px 0;padding:0;'>
                <table class='main' border='0' cellspacing='0' cellpadding='0' width='100%'>
                  <tr><td class='embedded'>
                    {$addedGmt} GMT" . ($ago !== '' ? " (" . e($ago) . " назад)" : "") . "
                    &nbsp;—&nbsp;<b>Торрент:</b> <a href='details.php?id={$torrentid}&tocomm=1#{$commentid}'>" . $name . "</a>
                    &nbsp;—&nbsp;<b>Комментарий:</b> #<a href='details.php?id={$torrentid}&tocomm=1#{$commentid}'>" . $commentid . "</a>
                  </td></tr>
                </table>";

        begin_table(true);
        $body = format_comment($row["text"] ?? '');
        echo "<tr valign='top'><td class='comment'>{$body}</td></tr>";
        end_table();
        echo "</div>";
    }

    end_frame();
    end_main_frame();
    if ($commentcount > $perpage) echo $pagerbottom;

    stdfoot();
    exit;
}

/** =========================================================
 *  DEFAULT
 * ========================================================= */

if ($action !== '') {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Неизвестное действие.");
}

// сюда попадает запрос без action
stderr($tracker_lang['error'] ?? 'Ошибка', "Неверный или отсутствующий запрос.");
