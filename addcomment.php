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

/** Безопасная нормализация — храним сырой BBCode, но убираем HTML и мусор */
function clean_text(?string $s): string {
    $s = str_replace("\0", '', (string)$s);
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = trim($s);
    return strip_tags($s);
}

function comments_supports_threads(): bool {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $check = sql_query("SHOW COLUMNS FROM comments LIKE 'parent_id'");
    if ($check && mysqli_num_rows($check) > 0) {
        return $ready = true;
    }

    $alter = sql_query("
        ALTER TABLE comments
        ADD COLUMN parent_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER torrent,
        ADD KEY idx_torrent_parent_added (torrent, parent_id, added)
    ");

    return $ready = (bool)$alter;
}

function comment_editor_html(string $formName, string $textareaName, string $text = '', ?string $sendJs = null, ?string $cancelJs = null): string {
    ob_start();
    echo '<div class="comment-editor-ajax">';
    echo '<form name="' . htmlspecialchars($formName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" method="post" action="#">';
    textbbcode($formName, $textareaName, $text);
    echo '<div style="margin-top:10px;text-align:right;">';
    if ($sendJs !== null) {
        echo '<a href="javascript:;" onclick="' . htmlspecialchars($sendJs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#1f5fa8 !important;font-size:11px;font-weight:700;">Сохранить</a>';
    }
    if ($cancelJs !== null) {
        if ($sendJs !== null) {
            echo '&nbsp;|&nbsp;';
        }
        echo '<a href="javascript:;" onclick="' . htmlspecialchars($cancelJs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#1f5fa8 !important;font-size:11px;font-weight:700;">Отмена</a>';
    }
    echo '</div>';
    echo '</form>';
    echo '</div>';
    return (string)ob_get_clean();
}

function render_comments_block_html(int $torrentid): string {
    global $CURUSER, $mysqli;

    $limited = 10;
    $isLogged = !empty($CURUSER['id']);
    $userId = $isLogged ? (int)$CURUSER['id'] : 0;
    $hasThreads = comments_supports_threads();

    $countRes = sql_query("SELECT COUNT(*) FROM comments WHERE torrent = $torrentid");
    [$commentsCount] = $countRes ? mysqli_fetch_row($countRes) : [0];
    $commentsCount = (int)$commentsCount;

    ob_start();
    echo '<div id="comments_list" class="comments-list">' . "\n";

    if ($commentsCount === 0) {
        if ($isLogged) {
            begin_frame("Комментарии");
            echo '<form name="comment" id="comment" method="post" action="#">';
            echo '<table style="margin-top:2px;" cellpadding="5" width="100%">';
            echo '<tr><td align="center">';
            textbbcode("comment", "text", '');
            echo '</td></tr><tr><td align="center">';
            echo '<input type="button" class="btn" value="Разместить комментарий" onclick="SE_SendComment(' . $torrentid . ')" id="send_comment" />';
            echo '</td></tr></table>';
            echo '</form>';
            end_frame();
        }

        echo "</div>\n";
        return (string)ob_get_clean();
    }

    [$pagertop, $pagerbottom, $limit] = pager($limited, $commentsCount, "details.php?id={$torrentid}&amp;", ['lastpagedefault' => 1]);
    $canrateCol = $isLogged
        ? ", (SELECT COUNT(*) FROM karma WHERE type = 'comment' AND value = c.id AND user = {$userId}) AS canrate"
        : "";
    $parentCol = $hasThreads ? "c.parent_id AS parent_id" : "0 AS parent_id";

    $commentsSql = "
        SELECT
            c.id,
            c.torrent AS torrentid,
            c.ip,
            c.text,
            c.user,
            c.added,
            c.editedby,
            c.editedat,
            c.karma,
            {$parentCol},
            u.avatar,
            u.warned,
            u.username,
            u.title,
            u.class,
            u.donor,
            u.downloaded,
            u.uploaded,
            u.gender,
            u.last_access,
            e.username AS editedbyname
            {$canrateCol}
        FROM comments AS c
        LEFT JOIN users AS u ON c.user = u.id
        LEFT JOIN users AS e ON c.editedby = e.id
        WHERE c.torrent = $torrentid
        ORDER BY " . ($hasThreads ? "CASE WHEN c.parent_id = 0 THEN c.id ELSE c.parent_id END, c.parent_id, c.id" : "c.id") . " $limit
    ";
    $subres = $mysqli->query($commentsSql) or sqlerr(__FILE__, __LINE__);

    $allrows = [];
    while ($subres && ($subrow = $subres->fetch_assoc())) {
        $allrows[] = $subrow;
    }

    echo '<table class="main" cellspacing="0" cellpadding="5" width="100%">';
    echo '<tr><td>';
    if (!empty($pagertop)) {
        echo '<div class="pager-wrap pager-wrap--top">' . $pagertop . '</div>';
    }
    commenttable($allrows);
    echo '</td></tr><tr><td>';
    if (!empty($pagerbottom)) {
        echo '<div class="pager-wrap pager-wrap--bottom">' . $pagerbottom . '</div>';
    }
    echo '</td></tr></table>';

    if ($isLogged) {
        begin_frame("Комментарии");
        echo '<form name="comment" id="comment" method="post" action="#">';
        echo '<table style="margin-top:2px;" cellpadding="5" width="100%">';
        echo '<tr><td align="center">';
        textbbcode("comment", "text", '');
        echo '</td></tr><tr><td align="center">';
        echo '<input type="button" class="btn" value="Разместить комментарий" onclick="SE_SendComment(' . $torrentid . ')" id="send_comment" />';
        echo '</td></tr></table>';
        echo '</form>';
        end_frame();
    }

    echo "</div>\n";
    return (string)ob_get_clean();
}

function comments_collect_delete_ids(int $torrentid, int $commentid): array {
    if (!comments_supports_threads()) {
        return [$commentid];
    }

    $res = sql_query("SELECT id, parent_id FROM comments WHERE torrent = $torrentid");
    $children = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $parentId = (int)($row['parent_id'] ?? 0);
        $children[$parentId][] = (int)$row['id'];
    }

    $queue = [$commentid];
    $deleteIds = [];
    while ($queue) {
        $current = array_shift($queue);
        if (isset($deleteIds[$current])) {
            continue;
        }
        $deleteIds[$current] = true;
        foreach ($children[$current] ?? [] as $childId) {
            $queue[] = $childId;
        }
    }

    return array_map('intval', array_keys($deleteIds));
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
                    $text    = (string)$comment['text'];
                    $post_id = (int)$comment['post_id'];
                    $torrent = (int)$comment['torrent'];
                    echo comment_editor_html(
                        "comment_edit_{$post_id}",
                        "edit_post_{$post_id}",
                        $text,
                        "SE_SaveComment('{$post_id}','{$torrent}')",
                        "SE_CommentCancel('{$post_id}','{$torrent}')"
                    );

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
        $text = clean_text($text);

        if ($text !== '' && $commentid > 0 && $torrentid > 0) {
            $sql_ori  = "SELECT text, user FROM comments WHERE id = $commentid AND torrent = $torrentid LIMIT 1";
            $res_ori  = sql_query($sql_ori) or sqlerr(__FILE__, __LINE__);

            if ($res_ori && mysqli_num_rows($res_ori) > 0) {
                $ori_text = mysqli_fetch_assoc($res_ori);
                $authorId = (int)($ori_text['user'] ?? 0);
                if ((get_user_class() < UC_MODERATOR) && ((int)($CURUSER['id'] ?? 0) !== $authorId)) {
                    app_log('warn', 'Save denied: no rights', ['cid' => $commentid, 'tid' => $torrentid]);
                    http_response_code(403);
                    echo 'forbidden';
                    break;
                }

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

    case 'reply_form':
        if ($commentid > 0 && $torrentid > 0 && !empty($CURUSER)) {
            comments_supports_threads();
            echo comment_editor_html(
                "comment_reply_{$commentid}",
                "reply_post_{$commentid}",
                '',
                "SE_SendReply('{$commentid}','{$torrentid}')",
                "SE_ReplyCancel('{$commentid}')"
            );
            app_log('info', 'Reply form shown', ['cid' => $commentid, 'tid' => $torrentid]);
        }
        break;

    // ---------------------------------------------------------------------
    // Цитата комментария
    // ---------------------------------------------------------------------
    case 'comment_quote':
        $text = $_POST['text'] ?? '';
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
                $username = preg_replace('/[\]\r\n]+/u', '', (string)($comment['username'] ?? ''));
                $old_text = clean_text((string)($comment['text'] ?? ''));

                $prefix = $text !== '' && !preg_match('/\n\s*\z/u', $text) ? "\n\n" : '';
                $new_text = $text . $prefix . "[quote={$username}]{$old_text}[/quote]";
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
    if (!user_has_module('comment_add')) {
        http_response_code(403);
        echo 'forbidden';
        break;
    }

    $text = $_POST['text'] ?? '';
    $text = clean_text($text);
    $parentId = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    $hasThreads = comments_supports_threads();

    if ($torrentid > 0 && $text !== '') {
        $user_id = (int)$CURUSER['id'];
        $ip      = sqlesc(getip());
        $added   = sqlesc(date('Y-m-d H:i:s'));
        $parentSql = ($hasThreads && $parentId > 0) ? ", `parent_id`" : "";
        $parentVal = ($hasThreads && $parentId > 0) ? ", $parentId" : "";
        if ($hasThreads && $parentId > 0) {
            $parentCheck = sql_query("SELECT id FROM comments WHERE id = $parentId AND torrent = $torrentid LIMIT 1");
            if (!$parentCheck || mysqli_num_rows($parentCheck) === 0) {
                $parentId = 0;
                $parentSql = "";
                $parentVal = "";
            }
        }

        $ins = "
            INSERT INTO comments (`user`, `torrent`, `added`, `text`, `ori_text`, `ip`{$parentSql})
            VALUES ($user_id, $torrentid, $added, " . sqlesc($text) . ", " . sqlesc($text) . ", $ip{$parentVal})
        ";
        $ok1 = sql_query($ins);
        $ok2 = sql_query("UPDATE torrents SET comments = comments + 1 WHERE id = $torrentid");

        if ($ok1 && $ok2) {
            app_log('info', 'Comment added', ['tid' => $torrentid, 'parent_id' => $parentId]);
            echo render_comments_block_html($torrentid);
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
                $deleteIds = comments_collect_delete_ids($torrentid, $commentid);
                $deleteList = implode(',', array_map('intval', $deleteIds));
                $ok = $deleteList !== ''
                    ? sql_query("DELETE FROM comments WHERE torrent = $torrentid AND id IN ($deleteList)")
                    : false;
                if (!$ok) {
                    app_log('error', 'Delete failed', ['cid' => $commentid, 'tid' => $torrentid]);
                    http_response_code(500);
                    echo 'error';
                    break;
                }

                $deletedCount = count($deleteIds);
                sql_query("UPDATE torrents SET comments = GREATEST(comments - $deletedCount, 0) WHERE id = $torrentid");
                app_log('info', 'Comment deleted', ['cid' => $commentid, 'tid' => $torrentid, 'deleted_count' => $deletedCount]);
                echo render_comments_block_html($torrentid);
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
