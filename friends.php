<?php


require "include/bittorrent.php";

// ---- Аватары: как в твоём рабочем примере ----
const DEFAULT_AVATAR = 'pic/default_avatar.png'; // <- проверь расширение! (png/gif)

function avatar_url(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return htmlspecialchars(DEFAULT_AVATAR, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // если в базе встречаются &amp; и т.п., раскомментируй следующую строку:
    // $raw = html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}



// ---------- Базовая инициализация ----------
dbconn(false);
loggedinorreturn();

// Глобальный mysqli
global $mysqli;

// Отдаем правильную кодировку в HTTP
header('Content-Type: text/html; charset=UTF-8');

// Гарантируем utf8mb4 на уровне соединения
if ($mysqli instanceof mysqli) {
    mysqli_set_charset($mysqli, 'utf8mb4');
} else {
    // если у тебя обертка sql_query сама держит коннект — выставим через SQL
    @sql_query("SET NAMES utf8mb4");
}

// Локальные шорткаты
$userid = (int)$CURUSER['id'];
$tracker_lang = $tracker_lang ?? [
    'error'         => 'Ошибка',
    'invalid_id'    => 'Неверный ID',
    'access_denied' => 'Доступ запрещён',
    'friends_list'  => 'Список друзей',
    'no_friends'    => 'Нет друзей',
    'last_seen'     => 'Последний визит: ',
    'ago'           => 'назад',
    'delete'        => 'Удалить',
    'pm'            => 'ЛС',
];

// ---------- Проверки прав ----------
if (!is_valid_id($userid)) {
    stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
}
if ($userid !== (int)$CURUSER['id']) {
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);
}

// ---------- Обработка действий (accept / surrender) ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['act'])) {
    $id   = (int)($_GET['id'] ?? 0);     // id записи friends (заявка)
    $user = (int)($_GET['user'] ?? 0);   // инициатор заявки
    $me   = (int)$CURUSER['id'];         // адресат заявки

    if ($_GET['act'] === 'accept') {
        sql_query("
            UPDATE friends
            SET status = 'yes'
            WHERE id = ".sqlesc($id)."
              AND userid = ".sqlesc($user)."
              AND friendid = ".sqlesc($me)."
              AND status = 'pending'
        ") or sqlerr(__FILE__, __LINE__);

        // заменили mysqli_affected_rows($GLOBALS['___mysqli_ston'])
        if (!($mysqli instanceof mysqli) || $mysqli->affected_rows < 1) {
            stderr('Ошибка', 'Неверная или уже обработанная заявка.');
        }

        // Зеркальная запись
        sql_query("
            INSERT INTO friends (userid, friendid, status)
            VALUES (".sqlesc($me).", ".sqlesc($user).", 'yes')
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ") or sqlerr(__FILE__, __LINE__);

        // Уведомление инициатору
        $dt   = sqlesc(get_date_time());
        $msg  = sqlesc("Пользователь [url=userdetails.php?id={$me}]{$CURUSER['username']}[/url] принял ваше предложение дружбы.");
        $subj = sqlesc("Ответ на предложение дружбы.");
        sql_query("
            INSERT INTO messages (sender, receiver, added, msg, subject)
            VALUES (0, ".sqlesc($user).", $dt, $msg, $subj)
        ") or sqlerr(__FILE__, __LINE__);

        header("Refresh: 2; url={$DEFAULTBASEURL}/friends.php");
        stderr("Успешно", "Пользователь добавлен в список друзей");
    }
    elseif ($_GET['act'] === 'surrender') {
        sql_query("
            DELETE FROM friends
            WHERE id = ".sqlesc($id)."
              AND userid = ".sqlesc($user)."
              AND friendid = ".sqlesc($me)."
              AND status = 'pending'
        ") or sqlerr(__FILE__, __LINE__);

        if (!($mysqli instanceof mysqli) || $mysqli->affected_rows < 1) {
            stderr('Ошибка', 'Неверная или уже обработанная заявка.');
        }

        $dt   = sqlesc(get_date_time());
        $msg  = sqlesc("Пользователь [url=userdetails.php?id={$me}]{$CURUSER['username']}[/url] отклонил ваше предложение дружбы.");
        $subj = sqlesc("Ответ на предложение дружбы.");
        sql_query("
            INSERT INTO messages (sender, receiver, added, msg, subject)
            VALUES (0, ".sqlesc($user).", $dt, $msg, $subj)
        ") or sqlerr(__FILE__, __LINE__);

        header("Refresh: 2; url={$DEFAULTBASEURL}/friends.php");
        stderr("Успешно", "Заявка отклонена");
    }
    else {
        stderr("Ошибка", "Нет доступа");
    }
}
// ---------- UI: список друзей (вставить после блока с accept/surrender) ----------

stdhead("Мои списки пользователей");

begin_frame("Друзья");

// Облегчённый округлый UI
?>
<style>
.friends-wrap{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.friend-card{
  border:1px solid rgba(0,0,0,.08);
  border-radius:16px;padding:12px;background:rgba(255,255,255,.85);
  box-shadow:0 2px 10px rgba(0,0,0,.04);
  transition:box-shadow .12s ease,border-color .12s ease
}
/* hover без движения */
.friend-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.06);border-color:rgba(0,0,0,.12)}
.friend-head{display:flex;gap:12px;align-items:center}
.friend-avatar{width:64px;height:64px;border-radius:14px;overflow:hidden;flex:0 0 64px;background:#f3f5f7}
.friend-avatar img{width:100%;height:100%;object-fit:cover}
.friend-name{font-weight:700;font-size:16px;line-height:1.2;margin-bottom:2px}
.friend-title{opacity:.8;font-size:12px}
.friend-meta{margin-top:8px;font-size:12px;opacity:.85}
.friend-actions{display:flex;gap:8px;margin-top:10px}
.btn-chip{
  display:inline-block;padding:7px 10px;border-radius:999px;font-size:12px;text-decoration:none;
  border:1px solid rgba(0,0,0,.12);background:#fff
}
.btn-chip:hover{border-color:rgba(0,0,0,.2)}
.btn-danger{background:#fff5f5;border-color:#ffd7d7}
.btn-danger:hover{background:#ffecec;border-color:#ffbcbc}
.colhead-soft{
  border-radius:14px;padding:10px 12px;margin:-2px 0 12px;
  background:linear-gradient(180deg,rgba(0,0,0,.04),rgba(0,0,0,.02));
  border:1px solid rgba(0,0,0,.06);font-weight:700
}
.colhead-link{display:block;text-decoration:none}
.status-badge{
  display:inline-block;
  padding:4px 8px;
  font-size:12px;
  line-height:1;
  border-radius:999px;
  background:#e8f1ff;           /* светло-синий фон */
  color:#0b61ff;                 /* насыщённый синий текст */
  border:1px solid rgba(11,97,255,.2);
  white-space:nowrap;
}


</style>
<?php

echo '<div class="colhead-soft"><a name="friends">'.$tracker_lang['friends_list'].'</a></div>';

// Друзья
$q = "
    SELECT
        f.friendid AS id,
        u.username      AS name,
        u.class,
        u.avatar,
        u.title,
        u.donor,
        u.warned,
        u.enabled,
        u.last_access
    FROM friends AS f
    LEFT JOIN users AS u ON f.friendid = u.id
    WHERE f.userid = ".(int)$userid." AND f.status = 'yes'
    ORDER BY u.username
";
$res = sql_query($q) or sqlerr(__FILE__, __LINE__);

if (mysqli_num_rows($res) === 0) {
    echo '<em>'.$tracker_lang['no_friends'].'.</em>';
} else {
    echo '<div class="friends-wrap">';
    while ($friend = mysqli_fetch_assoc($res)) {
        $id         = (int)$friend['id'];
        $nameRaw    = (string)($friend['name'] ?? '');
        $nameSafe   = htmlspecialchars($nameRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $class      = (int)$friend['class'];
        $titleRaw   = (string)($friend['title'] ?? '');
$title      = $titleRaw !== '' ? $titleRaw : get_user_class_name($class);
$titleSafe  = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');


        $lastAccess = (string)($friend['last_access'] ?? '');
        $lastHuman  = $lastAccess !== '' ? get_elapsed_time(sql_timestamp_to_unix_timestamp($lastAccess)) : '—';

        // Цветной ник + иконки
        $coloredName = get_user_class_color($class, $nameRaw);
        $icons       = get_user_icons($friend);

        // ---- Аватар: нормализация + onerror fallback (не подменяем, пока есть реальный URL)
$avatarAllow = (($CURUSER['avatars'] ?? 'yes') === 'yes');
$rawAvatar   = $avatarAllow ? trim((string)($friend['avatar'] ?? '')) : '';
if ($rawAvatar === '') {
    $rawAvatar = '/pic/default_avatar.gif'; // только если совсем пусто
}

$base = rtrim((string)($DEFAULTBASEURL ?? ''), '/');

// Уберём HTML-сущности из базы (иногда сохраняют с &amp; и т.п.)
$rawAvatar = html_entity_decode($rawAvatar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Если уже абсолютный, протокол-независимый, или data: — оставляем как есть
if (preg_match('~^(https?://|//|data:image/)~i', $rawAvatar)) {
    $avatarUrl = $rawAvatar;
} else {
    // Относительный путь → делаем абсолютным (подклеиваем / и базу)
    if ($rawAvatar[0] !== '/') {
        $rawAvatar = '/' . $rawAvatar;
    }
    // Если $base пуст, браузер всё равно отдаст от текущего хоста: /pic/...
    $avatarUrl = ($base !== '') ? ($base . $rawAvatar) : $rawAvatar;
}

// Аватар — без префиксов, как есть (с фолбэком на onerror)
$avatarSafe = avatar_url($friend['avatar'] ?? '');
$fallback   = htmlspecialchars(DEFAULT_AVATAR, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div class="friend-card">';
          echo '<div class="friend-head">';
echo '<div class="friend-avatar"><img src="'.$avatarSafe.'" alt="'.$nameSafe.'" width="64" height="64" loading="lazy" decoding="async" style="object-fit:cover" onerror="this.onerror=null;this.src=\''.$fallback.'\';"></div>';
            echo '<div class="friend-info">';
              echo '<div class="friend-name"><a href="userdetails.php?id='.$id.'"><b>'.$coloredName.'</b></a> '.$icons.'</div>';
              echo '<div class="friend-title"><span class="status-badge">'.$titleSafe.'</span></div>';

              echo '<div class="friend-meta">'.$tracker_lang['last_seen']
                    . htmlspecialchars($lastAccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . ' — ('.$lastHuman.' '.$tracker_lang['ago'].')</div>';
            echo '</div>';
          echo '</div>';

          echo '<div class="friend-actions">';
            echo '<a class="btn-chip" href="message.php?action=sendmessage&amp;receiver='.$id.'">'.$tracker_lang['pm'].'</a>';
            echo '<a class="btn-chip btn-danger" href="friends.php?id='.(int)$userid.'&action=delete&type=friend&targetid='.$id.'">'.$tracker_lang['delete'].'</a>';
          echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

// Ссылка в виде такой же «плашки», как заголовок
echo '<div class="colhead-soft" style="margin-top:12px">
        <a class="colhead-link" href="users.php">Найти пользователя / Список пользователей</a>
      </div>';

end_frame();
stdfoot();
