<?php
declare(strict_types=1);
/*
///////////////////////////////////////////////////////////////////////////////
//
// [AJAX] Comments mod by Strong v0.2Beta with using jQuery — PHP 8.1 fix + logging
//
///////////////////////////////////////////////////////////////////////////////
*/

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/styles/include/main.php';
dbconn();

global $tracker_lang, $CURUSER;
/** @var array<string,mixed> $CURUSER */

// ---------- Простой логгер (JSON-строки в logs/ajax_comments.log) ----------
const COMMENTS_LOG_DIR  = __DIR__ . '/logs';
const COMMENTS_LOG_FILE = COMMENTS_LOG_DIR . '/ajax_comments.log';

if (!is_dir(COMMENTS_LOG_DIR)) {
    @mkdir(COMMENTS_LOG_DIR, 0775, true);
}

function app_log(string $level, string $message, array $context = []) : void {
    $row = [
        'ts'      => date('c'),
        'level'   => $level,
        'message' => $message,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'uid'     => isset($GLOBALS['CURUSER']['id']) ? (int)$GLOBALS['CURUSER']['id'] : null,
        'uri'     => $_SERVER['REQUEST_URI'] ?? null,
        'ctx'     => $context,
    ];
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents(COMMENTS_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ---------- Вспомогалки ----------
function is_ajax(): bool {
    $h = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return is_string($h) && strtolower($h) === 'xmlhttprequest';
}

/** Универсальный конвертер кодировок с безопасным фолбэком */
function convert_enc(string $s, string $from, string $to): string {
    if (strcasecmp($from, $to) === 0) return $s;
    if (function_exists('mb_convert_encoding')) {
        return @mb_convert_encoding($s, $to, $from) ?: $s;
    }
    if (function_exists('iconv')) {
        $r = @iconv($from, $to . '//IGNORE', $s);
        return $r === false ? $s : $r;
    }
    return $s;
}

/** Безопасный трим + очистка — для плейнтекста/BBCode */
function clean_text(?string $s): string {
    $s = trim((string)$s);
    // NB: оставляем BBCode, но убираем HTML.
    $s = strip_tags($s);
    // HTML-экраним для вывода/хранения как HTML; если храните “сырой” BBCode в БД,
    // можно убрать htmlspecialchars и полагаться на format_comment() при выводе.
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

// ---------- Заголовок ----------
$charset = $tracker_lang['language_charset'] ?? 'UTF-8';
header('Content-Type: text/html; charset=' . $charset);

// ---------- Только AJAX ----------
if (!is_ajax()) {
    app_log('warn', 'Non-AJAX access blocked');
    http_response_code(400);
    echo 'Bad request';
    exit;
}

// ---------- Входные параметры ----------
$do        = isset($_REQUEST['do']) ? (string)$_REQUEST['do'] : '';
$commentid = isset($_REQUEST['cid']) && is_numeric($_REQUEST['cid']) ? (int)$_REQUEST['cid'] : 0;
$torrentid = isset($_REQUEST['tid']) && is_numeric($_REQUEST['tid']) ? (int)$_REQUEST['tid'] : 0;

app_log('info', 'AJAX entry', ['do' => $do, 'cid' => $commentid, 'tid' => $torrentid]);

switch ($do) {

    // ---------------------------------------------------------------------
    // Редактирование комментария — выдача формы
    // ---------------------------------------------------------------------
    case 'edit_comment':
        if ($commentid > 0 && $torrentid > 0) {
            $sql = "
                SELECT c.id AS post_id, c.torrent, c.user AS author_id, c.text, u.class
                FROM comments AS c
                LEFT JOIN users AS u ON u.id = c.user
                WHERE c.id = $commentid AND c.torrent = $torrentid
                LIMIT 1
            ";
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

            if ($res && mysqli_num_rows($res) > 0) {
                $comment = mysqli_fetch_assoc($res);
                $authorId = (int)($comment['author_id'] ?? 0);

                if ((get_user_class() >= UC_MODERATOR) || ((int)$CURUSER['id'] === $authorId)) {
                    $text    = htmlspecialchars((string)$comment['text'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                    $post_id = (int)$comment['post_id'];
                    $torrent = (int)$comment['torrent'];

                    echo "<textarea name=\"edit_post\" id=\"edit_post\" style=\"width:100%;height:100px;\">{$text}</textarea>";
                    echo "<br /><div style=\"float:right;margin-top:5px;\">";
                    echo "<a href=\"javascript:;\" onClick=\"SE_SaveComment('{$post_id}','{$torrent}')\" style=\"font-size:9px;color:#999999;\">Сохранить изменения</a>&nbsp;|&nbsp;";
                    echo "<a href=\"javascript:;\" onClick=\"SE_CommentCancel('{$post_id}','{$torrent}')\" style=\"font-size:9px;color:#999999;\">Закончить редактирование</a>";
                    echo "</div>";

                    app_log('info', 'Edit form shown', ['cid' => $commentid, 'tid' => $torrentid]);
                } else {
                    app_log('warn', 'Edit denied: no rights', ['cid' => $commentid, 'tid' => $torrentid]);
                    http_response_code(403);
                }
            }
        }
        break;

    // ---------------------------------------------------------------------
    // Сохранение отредактированного комментария
    // ---------------------------------------------------------------------
    case 'save_comment':
        $text = $_POST['text'] ?? '';
        // Если проект живёт на cp1251 — раскомментируйте convert_enc:
        // $text = convert_enc($text, 'UTF-8', 'Windows-1251');
        $text = clean_text($text);

        if ($text !== '' && $commentid > 0 && $torrentid > 0) {
            $sql_ori  = "SELECT text FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1";
            $res_ori  = sql_query($sql_ori) or sqlerr(__FILE__, __LINE__);

            if ($res_ori && mysqli_num_rows($res_ori) > 0) {
                $ori_text = mysqli_fetch_assoc($res_ori);

                $upd_sql = "
                    UPDATE comments
                    SET
                        text     = " . sqlesc($text) . ",
                        ori_text = " . sqlesc((string)$ori_text['text']) . ",
                        editedat = " . sqlesc(date('Y-m-d H:i:s')) . ",
                        editedby = " . (int)$CURUSER['id'] . "
                    WHERE id = $commentid AND torrent = $torrentid
                ";
                $upd_res = sql_query($upd_sql) or sqlerr(__FILE__, __LINE__);

                if ($upd_res) {
                    $res = sql_query("SELECT text FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    if ($res && mysqli_num_rows($res) > 0) {
                        $comment = mysqli_fetch_assoc($res);
                        echo format_comment((string)$comment['text']);
                        app_log('info', 'Comment saved', ['cid' => $commentid, 'tid' => $torrentid]);
                    }
                } else {
                    echo format_comment((string)$ori_text['text']);
                    app_log('error', 'Update failed, original returned', ['cid' => $commentid, 'tid' => $torrentid]);
                }
            }
        } else {
            app_log('warn', 'Save rejected: empty text or bad ids', ['cid' => $commentid, 'tid' => $torrentid]);
            http_response_code(422);
            echo 'empty';
        }
        break;

    // ---------------------------------------------------------------------
    // Отмена редактирования — показать текущий текст
    // ---------------------------------------------------------------------
    case 'save_cancel':
        if ($commentid > 0 && $torrentid > 0) {
            $sql = "SELECT text FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1";
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                echo format_comment((string)$row['text']);
                app_log('info', 'Edit cancel shown', ['cid' => $commentid, 'tid' => $torrentid]);
            }
        }
        break;

    // ---------------------------------------------------------------------
    // Цитата комментария
    // ---------------------------------------------------------------------
    case 'comment_quote':
        $text = $_POST['text'] ?? '';
        // $text = convert_enc($text, 'UTF-8', 'Windows-1251');
        $text = clean_text($text);

        if ($commentid > 0 && $torrentid > 0 && !empty($CURUSER)) {
            $sql = "
                SELECT c.user, c.text, u.username
                FROM comments AS c
                LEFT JOIN users AS u ON u.id = c.user
                WHERE c.id = $commentid AND c.torrent = $torrentid
                LIMIT 1
            ";
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

            if ($res && mysqli_num_rows($res) > 0) {
                $comment  = mysqli_fetch_assoc($res);
                $username = htmlspecialchars((string)$comment['username'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                $old_text = htmlspecialchars((string)$comment['text'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

                $new_text = "{$text}[quote={$username}]{$old_text}[/quote]";
                echo $new_text;

                app_log('info', 'Quote prepared', ['cid' => $commentid, 'tid' => $torrentid]);
            }
        }
        break;

   // ---------------------------------------------------------------------
// Добавление нового комментария
// ---------------------------------------------------------------------
case 'add_comment':
    // Проверка авторизации (если не залогинен — сразу exit с редиректом/ошибкой)
    loggedinorreturn();

    $text = $_POST['text'] ?? '';
    $text = clean_text($text);

    if ($torrentid > 0 && $text !== '') {
        $user_id = (int)$CURUSER['id'];
        $ip      = sqlesc(getip());
        $added   = sqlesc(date('Y-m-d H:i:s'));

        $ins = "
            INSERT INTO comments (`user`, `torrent`, `added`, `text`, `ori_text`, `ip`)
            VALUES ($user_id, $torrentid, $added, " . sqlesc($text) . ", " . sqlesc($text) . ", $ip)
        ";
        $ok1 = sql_query($ins);
        $ok2 = sql_query("UPDATE torrents SET comments = comments + 1 WHERE id = $torrentid");

        if ($ok1 && $ok2) {
            app_log('info', 'Comment added', ['tid' => $torrentid]);
            echo "<script>location.reload();</script>";
        } else {
            app_log('error', 'Insert/update failed on add_comment', ['tid' => $torrentid]);
            http_response_code(500);
            echo 'error';
        }
    } else {
        app_log('warn', 'Add rejected', ['tid' => $torrentid, 'reason' => 'empty text']);
        http_response_code(422);
        echo 'empty';
    }
    break;


    // ---------------------------------------------------------------------
    // Удаление комментария
    // ---------------------------------------------------------------------
    case 'delete_comment':
        $limited = 10;

        if ($commentid > 0 && $torrentid > 0) {
            $sql = "
                SELECT c.user AS user_id, u.class
                FROM comments AS c
                LEFT JOIN users AS u ON u.id = c.user
                WHERE c.id = $commentid AND c.torrent = $torrentid
            ";
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
            $row = $res ? mysqli_fetch_assoc($res) : null;

            $canDelete = $row && (get_user_class() >= UC_MODERATOR || (int)$CURUSER['id'] === (int)$row['user_id']);
            if ($canDelete) {
                $ok = sql_query("DELETE FROM comments WHERE id = $commentid AND torrent = $torrentid");
                if (!$ok) {
                    app_log('error', 'Delete failed', ['cid' => $commentid, 'tid' => $torrentid]);
                    http_response_code(500);
                    echo 'error';
                    break;
                }

                $res_count = sql_query("SELECT COUNT(*) FROM comments WHERE torrent = $torrentid LIMIT 1");
                [$count] = $res_count ? mysqli_fetch_row($res_count) : [0];

                [$pagertop, $pagerbottom, $limit] = pager($limited, (int)$count, "details.php?id={$torrentid}&amp;", ['lastpagedefault' => 1]);
                app_log('info', 'Comment deleted', ['cid' => $commentid, 'tid' => $torrentid, 'left' => (int)$count]);

                if ((int)$count === 0) {
                    print("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
                    print("<tr><td class=\"colhead\" colspan=\"2\">");
                    print("<div style=\"float: left;\"> :: Список комментариев</div>");
                    print("<div style=\"float: right;\"><a href=\"#comments\" class=\"altlink_white\">Добавить комментарий</a></div>");
                    print("</td></tr><tr><td align=\"center\">Комментариев нет. <a href=\"#comments\">Желаете добавить?</a></td></tr>");
                    print("</table><br>");

                    print("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
                    print("<tr><td class=\"colhead\" colspan=\"2\"><a name=\"comments\">&nbsp;</a><b>:: Без комментариев</b></td></tr>");
                    print("<tr><td align=\"center\">");
                    print("<form name=\"comment\" id=\"comment\">");
                    $text = $_POST['text'] ?? '';
                    textbbcode("wall", "text", $text);
                    print("</form></td></tr>");
                    print("<tr><td align=\"center\" colspan=\"2\">");
                    print("<input type=\"button\" class=\"btn\" value=\"Разместить комментарий\" onClick=\"SE_SendComment('{$torrentid}')\" id=\"send_comment\" />");
                    print("</td></tr></table>");
                } else {
                    $user_id = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
                    $karma_subquery = $user_id > 0
                        ? ", (SELECT COUNT(*) FROM karma WHERE type='comment' AND value = c.id AND user = $user_id) AS canrate"
                        : "";

                    $comments_sql = "
                        SELECT c.id, c.torrent AS torrentid, c.ip, c.text, c.user, c.added, c.editedby, c.editedat, c.karma,
                               u.avatar, u.warned, u.username, u.title, u.class, u.donor, u.downloaded, u.uploaded,
                               u.gender, u.last_access, e.username AS editedbyname
                               $karma_subquery
                        FROM comments AS c
                        LEFT JOIN users AS u ON c.user = u.id
                        LEFT JOIN users AS e ON c.editedby = e.id
                        WHERE c.torrent = $torrentid
                        ORDER BY c.id $limit
                    ";
                    $subres = sql_query($comments_sql) or sqlerr(__FILE__, __LINE__);

                    $allrows = [];
                    while ($subres && ($subrow = mysqli_fetch_assoc($subres))) {
                        $allrows[] = $subrow;
                    }

                    print("<table class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">");
                    print("<tr><td class=\"colhead\" align=\"center\">");
                    print("<div style=\"float: left;\"> :: Список комментариев</div>");
                    print("<div style=\"float: right;\"><a href=\"#comments\" class=\"altlink_white\">Добавить комментарий</a></div>");
                    print("</td></tr>");
                    print("<tr><td>$pagertop</td></tr>");
                    print("<tr><td>");
                    commenttable($allrows);
                    print("</td></tr>");
                    print("<tr><td>$pagerbottom</td></tr>");
                    print("</table>");

                   begin_frame("Добавить комментарий к торренту");

print("<table style=\"margin-top: 2px;\" cellpadding=\"5\" width=\"100%\">");
print("<tr><td align=\"center\">");

print("<form name=\"comment\" id=\"comment\">");
print("<table border=\"0\" cellpadding=\"5\"><tr><td class=\"clear\">");

$text = $_POST['text'] ?? '';
textbbcode("wall", "text", $text);

print("</td></tr></table>");
print("</form>");

print("</td></tr><tr><td align=\"center\">");
print("<input type=\"button\" class=\"btn\" value=\"Разместить комментарий\" onClick=\"SE_SendComment('{$torrentid}')\" id=\"send_comment\" />");
print("</td></tr></table>");

end_frame();

                }
            } else {
                app_log('warn', 'Delete denied: no rights', ['cid' => $commentid, 'tid' => $torrentid]);
                http_response_code(403);
                echo 'forbidden';
            }
        }
        break;

    // ---------------------------------------------------------------------
    // Просмотр оригинального текста
    // ---------------------------------------------------------------------
    case 'view_original':
        if ($torrentid > 0 && $commentid > 0) {
            $sql = "SELECT text, ori_text FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1";
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

            if ($res && mysqli_num_rows($res) > 0) {
                $comment = mysqli_fetch_assoc($res);

                $content  = "<div style=\"border:1px dashed #ccc;padding:5px;\"><b>Оригинальный текст:</b><br />";
                $content .= format_comment((string)$comment['ori_text']) . "</div><br />\n";

                $content .= "<div style=\"border:1px dashed #ccc;padding:5px;\"><b>Текущий текст:</b><br />";
                $content .= format_comment((string)$comment['text']) . "</div><br />\n";

                $content .= "<div style=\"float:right;\">";
                $content .= "<a href=\"javascript:;\" style=\"font-size:9px;color:#999999;\" onClick=\"SE_RecoverOriginal('{$commentid}','{$torrentid}')\">Восстановить оригинал</a>";
                $content .= "&nbsp;|&nbsp;";
                $content .= "<a href=\"javascript:;\" style=\"font-size:9px;color:#999999;\" onClick=\"SE_CommentCancel('{$commentid}','{$torrentid}')\">Отменить</a>";
                $content .= "</div>";

                echo $content;
                app_log('info', 'Original viewed', ['cid' => $commentid, 'tid' => $torrentid]);
            }
        }
        break;

    // ---------------------------------------------------------------------
    // Восстановление оригинального текста
    // ---------------------------------------------------------------------
    case 'recover_original':
        if ($torrentid > 0 && $commentid > 0) {
            $res = sql_query("SELECT ori_text FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1") or sqlerr(__FILE__, __LINE__);

            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);

                $ok = sql_query("UPDATE comments SET text = " . sqlesc((string)$row['ori_text']) . " WHERE id = $commentid AND torrent = $torrentid");
                if ($ok) {
                    echo format_comment((string)$row['ori_text']);
                    app_log('info', 'Original recovered', ['cid' => $commentid, 'tid' => $torrentid]);
                } else {
                    app_log('error', 'Recover failed', ['cid' => $commentid, 'tid' => $torrentid]);
                    http_response_code(500);
                    echo 'error';
                }
            }
        }
        break;

    default:
        app_log('warn', 'Unknown action', ['do' => $do]);
        http_response_code(400);
        echo 'unknown';
        break;
}
