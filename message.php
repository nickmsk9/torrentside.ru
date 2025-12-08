<?php

// ===== ГЛОБАЛЬНОЕ ЛОГИРОВАНИЕ ОШИБОК (в /logs) =====
date_default_timezone_set('Europe/Amsterdam'); // чтобы метки времени были корректные

// Корень сайта (если файл лежит в корне — __DIR__ уже корень)
$siteRoot = $_SERVER['DOCUMENT_ROOT'] ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : __DIR__;
// Папка для логов
$logDir = $siteRoot . '/logs';

// Создаём /logs при необходимости + .htaccess чтобы не отдавалось в веб
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
    // на всякий случай, чтобы через веб не скачивали логи
    @file_put_contents($logDir . '/.htaccess', "Require all denied\nDeny from all\n", LOCK_EX);
}

// Лог текущего дня
$logFile = $logDir . '/php-' . date('Y-m-d') . '.log';

// Включаем логирование всех ошибок в файл
ini_set('display_errors', '0');           // ничего не показываем в браузер
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);
error_reporting(E_ALL);

// Доп. обработчики, чтобы поймать ВСЁ (включая фатальные)
set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    // Превращаем в исключение, чтобы поймать в общем обработчике
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) use ($logFile) {
    $req = [
        'METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
        'URI'    => $_SERVER['REQUEST_URI'] ?? '',
        'GET'    => $_GET,
        'POST'   => array_map(fn($v)=>is_string($v)?mb_substr($v,0,1000):$v, $_POST), // не логируем слишком длинные поля
        'USER'   => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    $entry = sprintf(
        "[%s] EXCEPTION %s: %s in %s:%d\nTrace:\n%s\nREQUEST: %s\n----\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString(),
        json_encode($req, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    );
    error_log($entry);
});
register_shutdown_function(function () use ($logFile) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $entry = sprintf(
            "[%s] FATAL: %s in %s:%d\nREQUEST_URI: %s\n----\n",
            date('Y-m-d H:i:s'),
            $err['message'] ?? '',
            $err['file'] ?? '',
            $err['line'] ?? 0,
            $_SERVER['REQUEST_URI'] ?? ''
        );
        // Используем file_put_contents на случай, если error_log не успеет
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
});

// Полезно один раз за запрос пометить начало
error_log(sprintf("[%s] --- REQUEST %s %s ---\n", date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'] ?? 'CLI', $_SERVER['REQUEST_URI'] ?? ''));
// ===== КОНЕЦ БЛОКА ЛОГИРОВАНИЯ =====

require_once ("include/bittorrent.php");

gzip();

// Connect to DB & check login
dbconn();
loggedinorreturn();
parked();

// Define constants
define('PM_DELETED',0); // Message was deleted
define('PM_INBOX',1); // Message located in Inbox for reciever
define('PM_SENTBOX',-1); // GET value for sent box

// Determine action
$action = $_POST['action'] ?? $_GET['action'] ?? 'viewmailbox';

// начало просмотр почтового ящика
if ($action === "viewmailbox") {
    // Mailbox
    $mailbox = isset($_GET['box']) ? (int)$_GET['box'] : PM_INBOX;
    $mailbox = ($mailbox === PM_SENTBOX) ? PM_SENTBOX : PM_INBOX;

    $mailbox_name = ($mailbox === PM_INBOX)
        ? ($tracker_lang['inbox']  ?? 'Входящие')
        : ($tracker_lang['outbox'] ?? 'Отправленные');

    $tzoffset = isset($CURUSER['tzoffset']) ? (int)$CURUSER['tzoffset'] : 0;
    $curuser_id = (int)($CURUSER['id'] ?? 0);

    stdhead($mailbox_name); ?>
    <script type="text/javascript">
    let checkflag = false;
    function check(field) {
        for (let i = 0; i < field.length; i++) {
            const el = field[i];
            if (el.type === 'checkbox' && el.name === 'messages[]') el.checked = !checkflag;
        }
        checkflag = !checkflag;
    }
    </script>
    <script type="text/javascript" src="js/functions.js"></script>
<?php
    begin_frame($mailbox_name);
?>
<!-- ==== компактная панель почты (обновлённая) ==== -->
<style>
  .pm-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin:6px 0 10px}
  .pm-group{display:flex;flex-wrap:wrap;gap:6px}
  .pm-link{display:inline-block;padding:5px 10px;font-size:14px;border:1px solid #cfd6dc;border-radius:4px;text-decoration:none;color:#111;background:#fff}
  .pm-link.active{border-color:#aeb6be;background:#f6f8f9}
  .pm-compose{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
  .pm-input{height:30px;padding:4px 8px;border:1px solid #c8ccd0;border-radius:4px;font-size:14px}
  .pm-btn{height:30px;padding:0 10px;font-size:14px;border:1px solid #c8ccd0;border-radius:4px;background:#f6f8f9;cursor:pointer}
  .pm-results{position:relative}
  .pm-dropdown{position:absolute;z-index:30;left:0;right:0;max-height:220px;overflow:auto;background:#fff;border:1px solid #c8ccd0;border-radius:4px;margin-top:2px;display:none}
  .pm-item{padding:6px 8px;cursor:pointer}
  .pm-item:hover{background:#eef1f4}
  @media (max-width:600px){.pm-toolbar{gap:6px}.pm-link,.pm-btn{font-size:13px}}
</style>

<div class="pm-toolbar">
  <div class="pm-group">
    <a class="pm-link <?= $mailbox === PM_INBOX ? 'active' : '' ?>"
       href="message.php?action=viewmailbox&amp;box=1"><?= $tracker_lang['inbox'] ?? 'Входящие' ?></a>
    <a class="pm-link <?= $mailbox === PM_SENTBOX ? 'active' : '' ?>"
       href="message.php?action=viewmailbox&amp;box=-1"><?= $tracker_lang['outbox'] ?? 'Отправленные' ?></a>
  </div>

  <!-- Новое сообщение: поиск по имени + запасной вариант по ID -->
  <form class="pm-compose" action="message.php" method="get" onsubmit="return pmComposeSubmit(this);">
    <input type="hidden" name="action" value="sendmessage">
    <div class="pm-results">
      <input class="pm-input" type="text" name="user_query" id="pm_user_query"
             placeholder="<?= htmlspecialchars($tracker_lang['enter_username'] ?? 'Имя пользователя', ENT_QUOTES) ?>"
             autocomplete="off">
      <div id="pm_dropdown" class="pm-dropdown"></div>
    </div>
    <span>или ID:</span>
    <input class="pm-input" type="number" name="receiver" id="pm_receiver_id" min="1" step="1" placeholder="ID">
    <button class="pm-btn" type="submit"><?= $tracker_lang['new_message'] ?? 'Создать сообщение' ?></button>
  </form>
</div>

<script>
// ====== Поиск получателя по имени (AJAX, insensitive) ======
(function(){
  const input   = document.getElementById('pm_user_query');
  const box     = document.getElementById('pm_dropdown');
  const idField = document.getElementById('pm_receiver_id');
  let timer = null;

  function hide(){ box.style.display = 'none'; box.innerHTML=''; }
  function show(){ box.style.display = 'block'; }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  function render(items){
    if (!items || !items.length){ hide(); return; }
    box.innerHTML = items.map(u =>
      '<div class="pm-item" data-id="'+u.id+'" data-name="'+escapeHtml(u.username)+'">#'+u.id+' — '+escapeHtml(u.username)+'</div>'
    ).join('');
    show();
  }

  // Пробуем users.php, если нет — message.php (чтобы не менять серверный роутер)
  function fetchUserSearch(q){
    const u1 = 'users.php?action=ajax_user_search&q='+encodeURIComponent(q);
    const u2 = 'message.php?action=ajax_user_search&q='+encodeURIComponent(q);
    return fetch(u1, {credentials:'same-origin'}).then(r => r.ok ? r.json() : Promise.reject())
      .catch(()=> fetch(u2, {credentials:'same-origin'}).then(r => r.ok ? r.json() : []))
      .catch(()=> []);
  }

  input.addEventListener('input', function(){
    const q = input.value.trim();
    if (timer) clearTimeout(timer);
    if (q.length < 2){ hide(); return; }
    timer = setTimeout(() => {
      fetchUserSearch(q).then(data => render(data)).catch(hide);
    }, 160);
  });

  box.addEventListener('click', function(e){
    const el = e.target.closest('.pm-item'); if (!el) return;
    idField.value = el.dataset.id;
    input.value   = el.dataset.name;
    hide();
  });

  document.addEventListener('click', function(e){
    if (!box.contains(e.target) && e.target !== input) hide();
  });
})();

// submit: требуется либо ID, либо удачный поиск по имени — перед отправкой попробуем добить ID
function pmComposeSubmit(form){
  const idField = document.getElementById('pm_receiver_id');
  const qField  = document.getElementById('pm_user_query');
  if (idField.value.trim() !== '') return true;
  const q = qField.value.trim();
  if (q.length < 2) return false;
  return (async () => {
    const list = await (async function(q){
      const u1 = 'users.php?action=ajax_user_search&q='+encodeURIComponent(q);
      const u2 = 'message.php?action=ajax_user_search&q='+encodeURIComponent(q);
      try {
        const r1 = await fetch(u1,{credentials:'same-origin'}); if (r1.ok) return r1.json();
      } catch(e){}
      try {
        const r2 = await fetch(u2,{credentials:'same-origin'}); if (r2.ok) return r2.json();
      } catch(e){}
      return [];
    })(q);
    if (list && list.length){ idField.value = list[0].id; form.submit(); return false; }
    return false;
  })();
}
</script>


<div align="right">
  <form action="message.php" method="get">
    <input type="hidden" name="action" value="viewmailbox">
    <?= $tracker_lang['go_to'] ?? 'Перейти к'; ?>:
    <select name="box">
      <option value="1"  <?= $mailbox === PM_INBOX   ? "selected" : "" ?>><?= $tracker_lang['inbox']  ?? 'Входящие'; ?></option>
      <option value="-1" <?= $mailbox === PM_SENTBOX ? "selected" : "" ?>><?= $tracker_lang['outbox'] ?? 'Отправленные'; ?></option>
    </select>
    <input type="submit" value="<?= $tracker_lang['go_go_go'] ?? 'Перейти'; ?>">
  </form>
</div>

<form action="message.php" method="post" name="form1">
  <input type="hidden" name="action" value="moveordel">
  <input type="hidden" name="box"    value="<?= (int)$mailbox; ?>">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
    <tr>
      <td width="2%"  class="colhead">&nbsp;</td>
      <td width="51%" class="colhead"><?= $tracker_lang['subject']  ?? 'Тема'; ?></td>
      <td width="35%" class="colhead"><?= $mailbox === PM_INBOX ? ($tracker_lang['sender'] ?? 'Отправитель') : ($tracker_lang['receiver'] ?? 'Получатель'); ?></td>
      <td width="10%" class="colhead"><?= $tracker_lang['date']     ?? 'Дата'; ?></td>
      <td width="2%"  class="colhead">
        <input type="checkbox" title="<?= $tracker_lang['mark_all'] ?? 'Отметить все'; ?>" onclick="check(document.form1.elements);">
      </td>
    </tr>
<?php
    if ($mailbox !== PM_SENTBOX) {
        // Входящие
        $res = sql_query("
            SELECT m.*, u.username AS sender_username, s.id AS sfid, r.id AS rfid
            FROM messages m
            LEFT JOIN users u ON m.sender = u.id
            LEFT JOIN friends r ON r.userid = {$curuser_id} AND r.friendid = m.receiver
            LEFT JOIN friends s ON s.userid = {$curuser_id} AND s.friendid = m.sender
            WHERE m.receiver = " . sqlesc($curuser_id) . "
              AND m.location = " . sqlesc(PM_INBOX) . "
            ORDER BY m.id DESC
        ") or sqlerr(__FILE__, __LINE__);
    } else {
        // Отправленные (сохранённые)
        $res = sql_query("
            SELECT m.*, u.username AS receiver_username, s.id AS sfid, r.id AS rfid
            FROM messages m
            LEFT JOIN users u ON m.receiver = u.id
            LEFT JOIN friends r ON r.userid = {$curuser_id} AND r.friendid = m.receiver
            LEFT JOIN friends s ON s.userid = {$curuser_id} AND s.friendid = m.sender
            WHERE m.sender = " . sqlesc($curuser_id) . "
              AND m.saved  = 'yes'
            ORDER BY m.id DESC
        ") or sqlerr(__FILE__, __LINE__);
    }

    if (!$res || mysqli_num_rows($res) === 0): ?>
      <tr>
        <td class="lol" colspan="5" align="center"><?= ($tracker_lang['no_messages'] ?? 'Сообщений нет'); ?>.</td>
      </tr>
<?php
    else:
        while ($row = mysqli_fetch_assoc($res)):
            $subject = htmlspecialchars($row['subject'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($subject === '') $subject = ($tracker_lang['no_subject'] ?? 'Без темы');

            $is_unread = ($row['unread'] ?? '') === 'yes' && $mailbox !== PM_SENTBOX;
            $icon = $is_unread ? 'pn_inboxnew.gif' : 'pn_inbox.gif';
            $alt  = $is_unread ? ($tracker_lang['mail_unread'] ?? 'Непрочитано')
                               : ($tracker_lang['mail_read']   ?? 'Прочитано');

            if ($mailbox !== PM_SENTBOX) {
                $username = ($row['sender'] ?? 0) != 0
                    ? "<a href=\"userdetails.php?id=".(int)$row['sender']."\">".htmlspecialchars($row['sender_username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</a>"
                    : ($tracker_lang['from_system'] ?? 'Система');
            } else {
                $username = ($row['receiver'] ?? 0) != 0
                    ? "<a href=\"userdetails.php?id=".(int)$row['receiver']."\">".htmlspecialchars($row['receiver_username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</a>"
                    : ($tracker_lang['from_system'] ?? 'Система');
            }

            $added_ts = !empty($row['added']) ? strtotime($row['added']) : 0;
            $date     = $added_ts ? display_date_time($added_ts, $tzoffset) : '—';
?>
      <tr>
        <td class="lol"><img src="pic/<?= $icon ?>" alt="<?= htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></td>
        <td class="lol">
          <a href="message.php?action=viewmessage&amp;id=<?= (int)($row['id'] ?? 0) ?>"><?= $subject ?></a>
        </td>
        <td class="lol"><?= $username ?></td>
        <td class="lol" nowrap><?= htmlspecialchars((string)$date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td class="lol">
          <input type="checkbox" name="messages[]" value="<?= (int)($row['id'] ?? 0) ?>">
        </td>
      </tr>
<?php
        endwhile;
    endif;
?>
    <tr class="colhead">
      <td colspan="5" align="right">
        <input type="submit" name="delete"   value="<?= $tracker_lang['delete']    ?? 'Удалить'; ?>" onclick="return confirm('<?= $tracker_lang['sure_mark_delete'] ?? 'Точно удалить отмеченные?'; ?>')">
        <input type="submit" name="markread" value="<?= $tracker_lang['mark_read'] ?? 'Отметить прочитанными'; ?>" onclick="return confirm('<?= $tracker_lang['sure_mark_read'] ?? 'Отметить отмеченные как прочитанные?'; ?>')">
      </td>
    </tr>
  </table>
</form>
<?php
    end_frame();
    stdfoot();
}
// конец просмотр почтового ящика



// начало просмотр тела сообщения
if ($action === "viewmessage") {
    $pm_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($pm_id <= 0) {
        stderr($tracker_lang['error'], "У вас нет прав для просмотра этого сообщения.");
    }

    $adminview = false;

    // Получаем сообщение: обычный пользователь — своё входящее или сохранённое исходящее
    if (get_user_class() !== UC_SYSOP) {
        $res = sql_query("
            SELECT m.*
            FROM messages m
            WHERE m.id = " . sqlesc($pm_id) . "
              AND (
                    m.receiver = " . sqlesc((int)$CURUSER['id']) . "
                 OR (m.sender   = " . sqlesc((int)$CURUSER['id']) . " AND m.saved = 'yes')
              )
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);
    } else {
        $res = sql_query("
            SELECT m.*
            FROM messages m
            WHERE m.id = " . sqlesc($pm_id) . "
            LIMIT 1
        ") or sqlerr(__FILE__, __LINE__);
        $adminview = true;
    }

    if (!$res || mysqli_num_rows($res) === 0) {
        stderr($tracker_lang['error'], "Такого сообщения не существует.");
    }

    // Подготовка
    $message   = mysqli_fetch_assoc($res);
    $sender_id = isset($message['sender'])   ? (int)$message['sender']   : 0;
    $recv_id   = isset($message['receiver']) ? (int)$message['receiver'] : 0;
    $is_sender = ($sender_id === (int)$CURUSER['id']);

    $from   = '';
    $sender = '';
    $reply  = '';

    if ($is_sender) {
        // Мы отправитель: показываем "Кому"
        $from = "Кому";
        $rres = sql_query("SELECT username FROM users WHERE id = " . sqlesc($recv_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $rowR = $rres ? mysqli_fetch_row($rres) : null;
        $name = htmlspecialchars($rowR[0] ?? 'Неизвестно', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sender = '<a href="userdetails.php?id=' . $recv_id . '">' . $name . '</a>';
        // Ответ не нужен для исходящих
    } else {
        $from = "От кого";
        if ($sender_id === 0) {
            $sender = "Системное";
        } else {
            $sres = sql_query("SELECT username FROM users WHERE id = " . sqlesc($sender_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $rowS = $sres ? mysqli_fetch_row($sres) : null;
            $name = htmlspecialchars($rowS[0] ?? 'Неизвестно', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $sender = '<a href="userdetails.php?id=' . $sender_id . '">' . $name . '</a>';
            $reply  = '<a href="message.php?action=sendmessage&amp;receiver=' . $sender_id . '&amp;replyto=' . $pm_id . '">Ответить</a>';
        }
    }

    // Тело/дата
    $body = format_comment($message['msg'] ?? '');

    $tzoffset = isset($CURUSER['tzoffset']) ? (int)$CURUSER['tzoffset'] : 0;
    $added_ts = !empty($message['added']) ? strtotime($message['added']) : 0;
    $added    = $added_ts ? display_date_time($added_ts, $tzoffset) : '—';

    // Метка "новое" (для модераторов видна, если мы отправитель и у получателя ещё непрочитано)
    $unread = '';
    if (get_user_class() >= UC_MODERATOR && $is_sender && (($message['unread'] ?? '') === 'yes')) {
        $unread = '<span style="color:#FF0000;"><b>(Новое)</b></span>';
    }

    // Тема
    $subject = htmlspecialchars(trim($message['subject'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($subject === '') $subject = 'Без темы';

    // Пометить прочитанным (кроме случая, когда админ смотрит чужую переписку)
    if (!$adminview || (int)$CURUSER['id'] === $recv_id || (int)$CURUSER['id'] === $sender_id) {
        sql_query("
            UPDATE messages
            SET unread = 'no'
            WHERE id = " . sqlesc($pm_id) . "
              AND receiver = " . sqlesc((int)$CURUSER['id']) . "
            LIMIT 1
        ");
    }

    // Вывод
    stdhead("Личное Сообщение (Тема: {$subject})"); ?>
<?php begin_frame("Заголовок: {$subject}"); ?>
    <table width="660" border="0" cellpadding="4" cellspacing="0">
        <tr>
            <td width="50%" class="colhead"><?= $from ?></td>
            <td width="50%" class="colhead">Дата отправки</td>
        </tr>
        <tr>
            <td class="lol"><?= $sender ?></td>
            <td class="lol"><?= htmlspecialchars($added, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>&nbsp;&nbsp;<?= $unread ?></td>
        </tr>
        <tr>
            <td class="lol" colspan="2"><?= $body ?></td>
        </tr>
        <tr>
            <td class="lol" colspan="2">
                <div style="float:right; text-align:right; padding:3px 12px 0 11px">
                    <ul class="nNav">
                        <li>
                            <b class="nc"><b class="nc1"><b></b></b><b class="nc2"><b></b></b></b>
                            <span class="ncc">
                                <a href="message.php?action=deletemessage&amp;id=<?= (int)$pm_id ?>">Удалить</a>
                            </span>
                            <b class="nc"><b class="nc2"><b></b></b><b class="nc1"><b></b></b></b>
                        </li>
                        <li>
                            <div>
                                <b class="nc"><b class="nc1"><b></b></b><b class="nc2"><b></b></b></b>
                                <span class="ncc"><?= $reply ?></span>
                                <b class="nc"><b class="nc2"><b></b></b><b class="nc1"><b></b></b></b>
                            </div>
                        </li>
                        <li>
                            <div>
                                <b class="nc"><b class="nc1"><b></b></b><b class="nc2"><b></b></b></b>
                                <span class="ncc">
                                    <a href="message.php?action=forward&amp;id=<?= (int)$pm_id ?>">Переслать</a>
                                </span>
                                <b class="nc"><b class="nc2"><b></b></b><b class="nc1"><b></b></b></b>
                            </div>
                        </li>
                    </ul>
                </div>
            </td>
        </tr>
    </table>
<?php
    end_frame();
    stdfoot();
}
// конец просмотр тела сообщения



// начало просмотр посылка сообщения
if ($action === "sendmessage") {

    // Безопасно читаем GET-параметры
    $receiver = isset($_GET['receiver']) ? (int)$_GET['receiver'] : 0;
    if (!is_valid_id($receiver)) {
        stderr($tracker_lang['error'], "Неверное ID получателя");
    }

    $replyto = isset($_GET['replyto']) ? (int)$_GET['replyto'] : 0;
    if ($replyto && !is_valid_id($replyto)) {
        stderr($tracker_lang['error'], "Неверное ID сообщения");
    }

    $auto = $_GET['auto'] ?? null;
    $std  = $_GET['std']  ?? null;

    if (($auto || $std) && get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'], "Доступ запрещен.");
    }

    // Получатель
    $res  = sql_query("SELECT id, username FROM users WHERE id = " . sqlesc($receiver) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $user = $res ? mysqli_fetch_assoc($res) : null;
    if (!$user) {
        stderr($tracker_lang['error'], "Пользователя с таким ID не существует.");
    }

    // Текст/тема по умолчанию
    $body    = '';
    $subject = '';

    // Автоответы/шаблоны (если есть такие массивы)
    if ($auto && isset($pm_std_reply[$auto])) {
        $body = (string)$pm_std_reply[$auto];
    }
    if ($std && isset($pm_template[$std][1])) {
        $body = (string)$pm_template[$std][1];
    }

    // Ответ на сообщение
    if ($replyto) {
        $res  = sql_query("SELECT id, sender, receiver, subject, msg FROM messages WHERE id = " . sqlesc($replyto) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $msga = $res ? mysqli_fetch_assoc($res) : null;
        if (!$msga || (int)$msga['receiver'] !== (int)$CURUSER['id']) {
            stderr($tracker_lang['error'], "Вы пытаетесь ответить не на своё сообщение!");
        }

        $res2 = sql_query("SELECT username FROM users WHERE id = " . sqlesc((int)$msga['sender']) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $usra = $res2 ? mysqli_fetch_assoc($res2) : null;

        $sender_name = htmlspecialchars($usra['username'] ?? 'Неизвестно', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $quoted_msg  = htmlspecialchars($msga['msg'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $quoted_subj = htmlspecialchars($msga['subject'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body    .= "\n\n\n-------- {$sender_name} писал(а): --------\n{$quoted_msg}\n";
        $subject  = "Re: {$quoted_subj}";
    }

    stdhead("Отсылка сообщений", false);
    begin_frame("Отсылка сообщения");
    ?>
    <table class="main" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td class="embedded">
          <form id="message" name="message" method="post" action="message.php">
            <input type="hidden" name="action" value="takemessage">
            <input type="hidden" name="receiver" value="<?= (int)$receiver; ?>">
            <?php if ($replyto): ?>
              <input type="hidden" name="origmsg" value="<?= (int)$replyto; ?>">
            <?php endif; ?>

            <table class="message" cellspacing="0" cellpadding="5">
              <tr>
                <td colspan="2" class="colhead">
                  Сообщение для
                  <a class="altlink_white" href="userdetails.php?id=<?= (int)$receiver; ?>">
                      <?= htmlspecialchars($user['username'] ?? 'Неизвестно', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                  </a>
                </td>
              </tr>

              <tr>
                <td class="lol" colspan="2">
                  <b>Тема:&nbsp;&nbsp;</b>
                  <input name="subject" type="text" size="60"
                         value="<?= htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         maxlength="255">
                </td>
              </tr>

              <tr>
                <td class="lol" colspan="2">
                  <?php
                  // Используй ту же функцию редактора, что в проекте (у тебя была textbbcode)
                  textbbcode("message", "msg", $body, "0");
                  ?>
                </td>
              </tr>

              <tr>
                <?php if ($replyto): ?>
                <td class="lol" align="center">
                  <label>
                    <input type="checkbox" name="delete" value="yes" <?= (($CURUSER['deletepms'] ?? '') === 'yes') ? 'checked' : ''; ?>>
                    Удалить сообщение после ответа
                  </label>
                </td>
                <?php endif; ?>
                <td class="lol" align="center" <?= $replyto ? '' : 'colspan="2"'; ?>>
                  <label>
                    <input type="checkbox" name="save" value="yes" <?= (($CURUSER['savepms'] ?? '') === 'yes') ? 'checked' : ''; ?>>
                    Сохранить сообщение в отправленных
                  </label>
                </td>
              </tr>

              <tr>
                <td class="lol" colspan="2" align="center">
                  <input type="submit" value="Послать!" class="btn">
                </td>
              </tr>
            </table>
          </form>
        </td>
      </tr>
    </table>
    <?php
    end_frame();
    stdfoot();
}
// конец посылка сообщения



// начало прием посланного сообщения
if ($action === 'takemessage') {
    $receiver = isset($_POST['receiver']) ? (int)$_POST['receiver'] : 0;
    $origmsg  = isset($_POST['origmsg'])  ? (int)$_POST['origmsg']  : 0;
    $save     = ($_POST['save'] ?? '') === 'yes' ? 'yes' : 'no';
    $returnto = $_POST['returnto'] ?? '';

    if (!is_valid_id($receiver) || ($origmsg && !is_valid_id($origmsg))) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Неверный ID");
    }

    $msg     = trim($_POST['msg']     ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if ($msg === '')     stderr($tracker_lang['error'] ?? 'Ошибка', "Пожалуйста введите сообщение!");
    if ($subject === '') stderr($tracker_lang['error'] ?? 'Ошибка', "Пожалуйста введите тему сообщения!");

    // получатель
    $res  = sql_query("SELECT email, acceptpms, notifs, parked, UNIX_TIMESTAMP(last_access) AS la
                       FROM users WHERE id=" . sqlesc($receiver) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $user = $res ? mysqli_fetch_assoc($res) : null;
    if (!$user) stderr($tracker_lang['error'] ?? 'Ошибка', "Нет пользователя с таким ID $receiver.");

    if ($user['parked'] === 'yes') {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Этот аккаунт припаркован.");
    }

    // Права отправки с учетом acceptpms (без blocks)
    if (get_user_class() < UC_MODERATOR) {
        if ($user['acceptpms'] === 'friends') {
            // только от друзей
            $res2 = sql_query("SELECT 1 FROM friends
                               WHERE userid = " . sqlesc($receiver) . "
                                 AND friendid = " . sqlesc((int)$CURUSER['id']) . "
                               LIMIT 1") or sqlerr(__FILE__, __LINE__);
            if (!$res2 || mysqli_num_rows($res2) !== 1) {
                stderr('Отклонено', 'Этот пользователь принимает сообщения только от друзей.');
            }
        } elseif ($user['acceptpms'] === 'no') {
            stderr('Отклонено', 'Этот пользователь не принимает сообщения.');
        }
        // acceptpms = 'yes' — без дополнительных проверок
    }

    // вставка
    $q = "INSERT INTO messages (poster, sender, receiver, added, msg, subject, saved, location, unread)
          VALUES (" . sqlesc((int)$CURUSER['id']) . ",
                  " . sqlesc((int)$CURUSER['id']) . ",
                  " . sqlesc($receiver) . ",
                  NOW(),
                  " . sqlesc($msg) . ",
                  " . sqlesc($subject) . ",
                  " . sqlesc($save) . ",
                  " . sqlesc(PM_INBOX) . ",
                  'yes')";
    sql_query($q) or sqlerr(__FILE__, __LINE__);
    $sended_id = $mysqli->insert_id;

    // удалить исходное при ответе
    if ($origmsg && (($_POST['delete'] ?? '') === 'yes')) {
        $res3 = sql_query("SELECT receiver, saved FROM messages WHERE id=" . sqlesc($origmsg) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        if ($res3 && ($arr = mysqli_fetch_assoc($res3)) && (int)$arr['receiver'] === (int)$CURUSER['id']) {
            if ($arr['saved'] === 'no') {
                sql_query("DELETE FROM messages WHERE id=" . sqlesc($origmsg) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            } else {
                sql_query("UPDATE messages SET location = " . sqlesc(PM_DELETED) . " WHERE id=" . sqlesc($origmsg) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            }
        }
    }

    // редирект
    $box = ($save === 'yes') ? PM_SENTBOX : PM_INBOX;
    $to  = $returnto !== '' ? $returnto : "message.php?action=viewmailbox&box={$box}";
    header("Location: {$to}");
    exit;
}
// конец прием посланного сообщения



//начало массовая рассылка
if ($action == 'mass_pm') {
        if (get_user_class() < UC_MODERATOR)
                stderr($tracker_lang['error'], $tracker_lang['access_denied']);
        $n_pms = 0 + $_POST['n_pms'];
        $pmees = $_POST['pmees'];
        $auto = $_POST['auto'];

        if ($auto)
                $body=$mm_template[$auto][1];

        stdhead("Отсылка сообщений", false);
        ?>
<?
begin_frame("Массовое Сообщение");
?>
        <table class=main border=0 cellspacing=0 cellpadding=0>
        <tr><td class=embedded><div align=center>
        <form id=message method=post action=<?=$_SERVER['PHP_SELF']?> name=message>
        <input type=hidden name=action value=takemass_pm>
        <? if ($_SERVER["HTTP_REFERER"]) { ?>
        <input type=hidden name=returnto value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"]);?>">
        <? } ?>
        <table border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead colspan=2>Массовая рассылка для <?=$n_pms?> пользовате<?=($n_pms>1?"лей":"ля")?></td></tr>
        <TR>
        <TD class=lol colspan="2"><B>Тема:&nbsp;&nbsp;</B>
        <INPUT name="subject" type="text" size="60" maxlength="255"></TD>
        </TR>
        <tr><td colspan="2"><div align="center">
        <?=textbbcode("message","msg","$body","0");?>
        </div></td></tr>
        <tr><td colspan="2"><div align="center"><b>Комментарий:&nbsp;&nbsp;</b>
        <input name="comment" type="text" size="70">
        </div></td></tr>
        <tr><td><div align="center"><b>От:&nbsp;&nbsp;</b>
        <?=$CURUSER['username']?>
        <input name="sender" type="radio" value="self" checked>
        &nbsp; Системное
        <input name="sender" type="radio" value="system">
        </div></td>
        <td><div align="center"><b>Take snapshot:</b>&nbsp;<input name="snap" type="checkbox" value="1">
         </div></td></tr>
        <tr><td colspan="2" align=center><input type=submit value="Послать!" class=btn>
        </td></tr></table>
        <input type=hidden name=pmees value="<?=$pmees?>">
        <input type=hidden name=n_pms value=<?=$n_pms?>>
        </form><br /><br />
        </div>
        </td>
        </tr>
        </table>
        <?
end_frame();
        stdfoot();

}
//конец массовая рассылка


//начало прием сообщений из массовой рассылки
if ($action == 'takemass_pm') {
        if (get_user_class() < UC_MODERATOR)
                stderr($tracker_lang['error'], $tracker_lang['access_denied']);
        $msg = trim($_POST["msg"]);
        if (!$msg)
                stderr($tracker_lang['error'],"Пожалуйста введите сообщение.");
        $sender_id = ($_POST['sender'] == 'system' ? 0 : $CURUSER['id']);
        $from_is = mysql_real_escape_string(unesc($_POST['pmees']));
        // Change
        $subject = trim($_POST['subject']);
        $query = "INSERT INTO messages (sender, receiver, added, msg, subject, location, poster) ". "SELECT $sender_id, u.id, '" . get_date_time(time()) . "', " .
        sqlesc($msg) . ", " . sqlesc($subject) . ", 1, $sender_id " . sqlesc($from_is);
        // End of Change
        sql_query($query) or sqlerr(__FILE__, __LINE__);
        $n = mysql_affected_rows();
        $n_pms = (int) $_POST['n_pms'];
        $comment = (string) $_POST['comment'];
        $snapshot = (int) $_POST['snap'];
        // add a custom text or stats snapshot to comments in profile
        if ($comment || $snapshot)
        {
                $res = sql_query("SELECT u.id, u.uploaded, u.downloaded, u.modcomment ".sqlesc($from_is)) or sqlerr(__FILE__, __LINE__);
                if (mysql_num_rows($res) > 0)
                {
                        $l = 0;
                        while ($user = mysql_fetch_array($res))
                        {
                                unset($new);
                                $old = $user['modcomment'];
                                if ($comment)
                                        $new = $comment;
                                        if ($snapshot)
                                        {
                                                $new .= ($new?"\n":"") . "MMed, " . date("Y-m-d") . ", " .
                                                "UL: " . mksize($user['uploaded']) . ", " .
                                                "DL: " . mksize($user['downloaded']) . ", " .
                                                "r: " . (($user['downloaded'] > 0)?($user['uploaded']/$user['downloaded']) : 0) . " - " .
                                                ($_POST['sender'] == "system"?"System":$CURUSER['username']);
                                        }
                                        $new .= $old?("\n".$old):$old;
                                        sql_query("UPDATE users SET modcomment = " . sqlesc($new) . " WHERE id = " . $user['id']) or sqlerr(__FILE__, __LINE__);
                                        if (mysql_affected_rows())
                                                $l++;
                        }
                }
        }
        header ("Refresh: 3; url=message.php");
        stderr($tracker_lang['success'], (($n_pms > 1) ? "$n сообщений из $n_pms было" : "Сообщение было")." успешно отправлено!" . ($l ? " $l комментарий(ев) в профиле " . (($l>1) ? "были" : " был") . " обновлен!" : ""));
}
//конец прием сообщений из массовой рассылки


//начало перемещение, помечание как прочитанного
if ($action == "moveordel") {
        $pm_id = (int) $_POST['id'];
        $pm_box = (int) $_POST['box'];
        $pm_messages = $_POST['messages'];
        if ($_POST['move']) {
                if ($pm_id) {
                        // Move a single message
                        @sql_query("UPDATE messages SET location=" . sqlesc($pm_box) . ", saved = 'yes' WHERE id=" . sqlesc($pm_id) . " AND receiver=" . $CURUSER['id'] . " LIMIT 1");
                }
                else {
                        // Move multiple messages
                        @sql_query("UPDATE messages SET location=" . sqlesc($pm_box) . ", saved = 'yes' WHERE id IN (" . implode(", ", array_map("sqlesc", array_map("intval", $pm_messages))) . ') AND receiver=' . $CURUSER['id']);
                }
                // Check if messages were moved
                if (@mysql_affected_rows() == 0) {
                        stderr($tracker_lang['error'], "Не возможно переместить сообщения!");
                }
                header("Location: message.php?action=viewmailbox&box=" . $pm_box);
                exit();
        }
        elseif ($_POST['delete']) {
                if ($pm_id) {
                        // Delete a single message
                        $res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                        $message = mysql_fetch_assoc($res);
                        if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no') {
                                sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                        }
                        elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED) {
                                sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                        }
                        elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes') {
                                sql_query("UPDATE messages SET location=0 WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                        }
                        elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED) {
                                sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                        }
                } else {
                        // Delete multiple messages
                        if (is_array($pm_messages))
                        foreach ($pm_messages as $id) {
                                $res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc((int) $id));
                                $message = mysql_fetch_assoc($res);
                                if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no') {
                                        sql_query("DELETE FROM messages WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
                                }
                                elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED) {
                                        sql_query("DELETE FROM messages WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
                                }
                                elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes') {
                                        sql_query("UPDATE messages SET location=0 WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
                                }
                                elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED) {
                                        sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
                                }
                        }
                }
                // Check if messages were moved
                if (@mysql_affected_rows() == 0) {
                        stderr($tracker_lang['error'],"Сообщение не может быть удалено!");
                }
                else {
                        header("Location: message.php?action=viewmailbox&box=" . $pm_box);
                        exit();
                }
        }
        elseif ($_POST["markread"]) {
                //помечаем одно сообщение
                if ($pm_id) {
                        sql_query("UPDATE messages SET unread='no' WHERE id = " . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
                }
                //помечаем множество сообщений
                else {
                		if (is_array($pm_messages))
                        foreach ($pm_messages as $id) {
                                $res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc((int) $id));
                                $message = mysql_fetch_assoc($res);
                                sql_query("UPDATE messages SET unread='no' WHERE id = " . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
                        }
                }
                // Проверяем, были ли помечены сообщения
                if (@mysql_affected_rows() == 0) {
                        stderr($tracker_lang['error'], "Сообщение не может быть помечено как прочитанное! ");
                }
                else {
                        header("Location: message.php?action=viewmailbox&box=" . $pm_box);
                        exit();
                }
        }

stderr($tracker_lang['error'],"Нет действия.");
}
//конец перемещение, помечание как прочитанного


// начало пересылка
if ($action === "forward") {
    $curuser_id   = (int)($CURUSER['id'] ?? 0);
    $curuser_name = (string)($CURUSER['username'] ?? 'Система');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // --- Показ формы
        $pm_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($pm_id <= 0 || $curuser_id <= 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', "Некорректный запрос.");
        }

        // Получаем сообщение и убеждаемся, что оно принадлежит текущему пользователю
        $res = sql_query(
            'SELECT * FROM messages
             WHERE id=' . sqlesc($pm_id) . '
               AND (receiver=' . sqlesc($curuser_id) . ' OR sender=' . sqlesc($curuser_id) . ')
             LIMIT 1'
        ) or sqlerr(__FILE__, __LINE__);

        if (!$res || mysqli_num_rows($res) === 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', "У вас нет разрешения пересылать это сообщение.");
        }

        $message = mysqli_fetch_assoc($res);

        // Готовим данные
        $orig_sender_id   = (int)($message['sender'] ?? 0);
        $orig_receiver_id = (int)($message['receiver'] ?? 0);

        // Забираем имена отправителя и получателя (если есть)
        $user_ids = array_filter([$orig_sender_id, $orig_receiver_id]);
        $names = [];
        if ($user_ids) {
            $resU = sql_query(
                "SELECT id, username FROM users WHERE id IN (" . implode(',', array_map('intval', $user_ids)) . ")"
            ) or sqlerr(__FILE__, __LINE__);
            while ($u = mysqli_fetch_assoc($resU)) {
                $names[(int)$u['id']] = $u['username'];
            }
        }

        // Оригинальный отправитель
        if ($orig_sender_id === 0) {
            $orig_sender_link = $tracker_lang['from_system'] ?? 'Системное';
            $orig_sender_name = $orig_sender_link;
        } else {
            $orig_sender_name = $names[$orig_sender_id] ?? ('#' . $orig_sender_id);
            $orig_sender_link = '<a href="userdetails.php?id=' . $orig_sender_id . '">' .
                htmlspecialchars($orig_sender_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
        }

        // Оригинальный получатель (на всякий случай покажем)
        if ($orig_receiver_id === 0) {
            $orig_recv_link = $tracker_lang['from_system'] ?? 'Системное';
        } else {
            $orig_recv_name = $names[$orig_receiver_id] ?? ('#' . $orig_receiver_id);
            $orig_recv_link = '<a href="userdetails.php?id=' . $orig_receiver_id . '">' .
                htmlspecialchars($orig_recv_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
        }

        $subject = 'Fwd: ' . htmlspecialchars((string)($message['subject'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body    = "-------- Оригинальное сообщение от " .
            htmlspecialchars($orig_sender_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            ": --------<br>" . format_comment((string)($message['msg'] ?? ''));

        stdhead($subject);
        begin_frame($subject);
        ?>
        <form action="message.php" method="post">
            <input type="hidden" name="action" value="forward">
            <input type="hidden" name="id" value="<?= (int)$pm_id ?>">
            <table border="0" cellpadding="4" cellspacing="0">
                <tr><td class="colhead" colspan="2"><?= $subject ?></td></tr>
                <tr>
                    <td><?= $tracker_lang['to'] ?? 'Кому:'; ?></td>
                    <td><input type="text" name="to" value="" size="83" placeholder="<?= htmlspecialchars($tracker_lang['enter_username'] ?? 'Введите имя', ENT_QUOTES) ?>"></td>
                </tr>
                <tr>
                    <td><?= $tracker_lang['original_sender'] ?? 'Оригинальный отправитель:'; ?></td>
                    <td><?= $orig_sender_link ?></td>
                </tr>
                <tr>
                    <td><?= $tracker_lang['original_receiver'] ?? 'Оригинальный получатель:'; ?></td>
                    <td><?= $orig_recv_link ?></td>
                </tr>
                <tr>
                    <td><?= $tracker_lang['from'] ?? 'От:'; ?></td>
                    <td><a href="userdetails.php?id=<?= $curuser_id ?>"><?= htmlspecialchars($curuser_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                </tr>
                <tr>
                    <td><?= $tracker_lang['subject'] ?? 'Тема:'; ?></td>
                    <td><input type="text" name="subject" value="<?= $subject ?>" size="83"></td>
                </tr>
                <tr>
                    <td><?= $tracker_lang['message'] ?? 'Сообщение:'; ?></td>
                    <td>
                        <textarea name="msg" cols="80" rows="8"></textarea><br>
                        <?= $body ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="center">
                        <?= $tracker_lang['save_message'] ?? 'Сохранить сообщение'; ?>
                        <input type="checkbox" name="save" value="1"<?= (($CURUSER['savepms'] ?? '') === 'yes') ? ' checked' : '' ?>>
                        &nbsp;
                        <input type="submit" value="<?= $tracker_lang['forward'] ?? 'Переслать'; ?>">
                    </td>
                </tr>
            </table>
        </form>
        <?php
        end_frame();
        stdfoot();
    } else {
        // --- Пересылаем
        $pm_id = (int)($_POST['id'] ?? 0);
        if ($pm_id <= 0 || $curuser_id <= 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', "Некорректный запрос.");
        }

        // Проверяем доступ к сообщению
        $res = sql_query(
            'SELECT * FROM messages
             WHERE id=' . sqlesc($pm_id) . '
               AND (receiver=' . sqlesc($curuser_id) . ' OR sender=' . sqlesc($curuser_id) . ')
             LIMIT 1'
        ) or sqlerr(__FILE__, __LINE__);

        if (!$res || mysqli_num_rows($res) === 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', "У вас нет разрешения пересылать это сообщение.");
        }

        $message  = mysqli_fetch_assoc($res);
        $subject  = (string)($_POST['subject'] ?? '');
        $username = trim(strip_tags((string)($_POST['to'] ?? '')));

        if ($username === '') {
            stderr($tracker_lang['error'] ?? 'Ошибка', "Не указано имя получателя.");
        }

        // Ищем получателя (регистронезависимо)
        $res = sql_query(
            "SELECT id, username, acceptpms
             FROM users
             WHERE LOWER(username) = LOWER(" . sqlesc($username) . ")
             LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        if (!$res || mysqli_num_rows($res) === 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', "Пользователя с таким именем не существует.");
        }

        $to_user = mysqli_fetch_assoc($res);
        $to_id   = (int)$to_user['id'];

        // Имя исходного отправителя для подвала
        if ((int)($message['sender'] ?? 0) === 0) {
            $orig_from_name = $tracker_lang['from_system'] ?? 'Системное';
        } else {
            $res2 = sql_query("SELECT username FROM users WHERE id=" . sqlesc((int)$message['sender']) . " LIMIT 1")
                or sqlerr(__FILE__, __LINE__);
            $row2 = mysqli_fetch_assoc($res2);
            $orig_from_name = (string)($row2['username'] ?? ('#' . (int)$message['sender']));
        }

        // Тело письма
        $body_user = (string)($_POST['msg'] ?? '');
        $body_full = $body_user .
            "\n-------- Оригинальное сообщение от " . $orig_from_name . ": --------\n" .
            (string)($message['msg'] ?? '');

        // Сохранить копию
        $save = !empty($_POST['save']) ? 'yes' : 'no';

        // Ограничения получателя (!!! проверяем получателя, а не отправителя)
        if ((int)get_user_class() < (int)UC_MODERATOR) {
            $accept = (string)($to_user['acceptpms'] ?? 'yes'); // yes|friends|no

            if ($accept === 'no') {
                stderr($tracker_lang['denied'] ?? 'Отклонено', "Этот пользователь не принимает сообщения.");
            }

            if ($accept === 'friends') {
                $resF = sql_query("SELECT 1 FROM friends WHERE userid={$to_id} AND friendid={$curuser_id} LIMIT 1")
                    or sqlerr(__FILE__, __LINE__);
                if (mysqli_num_rows($resF) !== 1) {
                    stderr($tracker_lang['denied'] ?? 'Отклонено', "Этот пользователь принимает сообщения только от друзей.");
                }
            }

            // Блок-лист
            $resB = sql_query("SELECT 1 FROM blocks WHERE userid={$to_id} AND blockid={$curuser_id} LIMIT 1")
                or sqlerr(__FILE__, __LINE__);
            if (mysqli_num_rows($resB) === 1) {
                stderr($tracker_lang['denied'] ?? 'Отклонено', "Этот пользователь добавил вас в чёрный список.");
            }
        }

        // Вставляем сообщение
        sql_query(
            "INSERT INTO messages (poster, sender, receiver, added, subject, msg, location, saved)
             VALUES (" .
                (int)$curuser_id . ", " .
                (int)$curuser_id . ", " .
                (int)$to_id . ", " .
                "'" . get_date_time() . "', " .
                sqlesc($subject) . ", " .
                sqlesc($body_full) . ", " .
                sqlesc(PM_INBOX) . ", " .
                sqlesc($save) .
            ")"
        ) or sqlerr(__FILE__, __LINE__);

        stderr($tracker_lang['success'] ?? 'Удачно', "ЛС переслано.");
    }
}
// конец пересылка


// начало удаление сообщения
if ($action === "deletemessage") {
    $pm_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($pm_id <= 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Некорректный ID сообщения.");
    }

    // Берём сообщение
    $res = sql_query("SELECT id, sender, receiver, saved, location FROM messages WHERE id=" . sqlesc($pm_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
    if (!$res || mysqli_num_rows($res) === 0) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Сообщения с таким ID не существует.");
    }
    $message = mysqli_fetch_assoc($res);

    $user_id = (int)$CURUSER['id'];
    $is_receiver = ((int)$message['receiver'] === $user_id);
    $is_sender   = ((int)$message['sender']   === $user_id);

    if (!$is_receiver && !$is_sender && get_user_class() !== UC_SYSOP) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "У вас нет прав на удаление этого сообщения.");
    }

    // Готовим действие
    $res2 = false;
    if ($is_receiver && ($message['saved'] === 'no')) {
        // Получатель удаляет входящее, которое не сохранено отправителем -> удалить строку
        $res2 = sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $redirect_box = PM_INBOX;
    } elseif ($is_sender && ((int)$message['location'] === PM_DELETED)) {
        // Отправитель удаляет своё, когда у получателя уже удалено -> удалить строку
        $res2 = sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $redirect_box = PM_SENTBOX;
    } elseif ($is_receiver && ($message['saved'] === 'yes')) {
        // Получатель «скрывает» входящее, если отправитель сохранил — переносим в корзину для получателя
        $res2 = sql_query("UPDATE messages SET location=" . sqlesc(PM_DELETED) . " WHERE id=" . sqlesc($pm_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $redirect_box = PM_INBOX;
    } elseif ($is_sender && ((int)$message['location'] !== PM_DELETED)) {
        // Отправитель убирает из «Отправленных» но у получателя остаётся
        $res2 = sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc($pm_id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $redirect_box = PM_SENTBOX;
    } else {
        // Нечего делать
        stderr($tracker_lang['error'] ?? 'Ошибка', "Невозможно удалить это сообщение.");
    }

    // Проверим, что действительно что-то изменилось
    global $mysqli; // если у тебя используется глобальный $mysqli
    if (!$res2 || ($mysqli && $mysqli->affected_rows === 0)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Невозможно удалить сообщение.");
    }

    header("Location: message.php?action=viewmailbox&box=" . (int)$redirect_box);
    exit;
}
// конец удаление сообщения

?>