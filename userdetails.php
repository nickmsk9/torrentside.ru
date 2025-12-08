<?php
require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

// Получаем ID пользователя из GET и проверяем
$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    stderr("Ошибка", "Некорректный ID пользователя.");
}

// Лог посещения профиля
visitorsHistorie($id);

// Текущий пользователь
$uid = (int)$CURUSER['id'];

// Одна быстрая выборка: профиль + страна + количество комментариев + метка возможности кармы
$sql = "
    SELECT 
        u.*,
        c.name     AS country_name,
        c.flagpic  AS country_flag,
        COALESCE(cc.cnt, 0) AS comment_count,
        CASE WHEN k.user IS NULL THEN 0 ELSE 1 END AS canrate
    FROM users u
    LEFT JOIN countries c        ON c.id = u.country
    LEFT JOIN (
        SELECT `user`, COUNT(*) AS cnt
        FROM comments
        GROUP BY `user`
    ) cc                          ON cc.`user` = u.id
    LEFT JOIN karma k             ON k.type = 'user' 
                                 AND k.value = u.id 
                                 AND k.`user` = {$uid}
    WHERE u.id = {$id}
    LIMIT 1
";

$res  = sql_query($sql) or sqlerr(__FILE__, __LINE__);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    stderr("Ошибка", "Пользователь с ID {$id} не найден.");
}
if ($user['status'] === 'pending') {
    stderr("Информация", "Пользователь ещё не активирован.");
}

// ---- Предварительная санитизация повторно используемых значений
$safeUsername    = htmlspecialchars($user['username'] ?? '');
$safeAdded       = htmlspecialchars($user['added'] ?? '');
$safeLastAccess  = htmlspecialchars($user['last_access'] ?? '');
$safeCountryName = htmlspecialchars($user['country_name'] ?? '');
$safeFlag        = htmlspecialchars($user['country_flag'] ?? '');

// IP-адрес (только для модераторов или владельца)
$addr = '';
if (!empty($user['ip']) && (get_user_class() >= UC_MODERATOR || (int)$user['id'] === $uid)) {
    $ip  = $user['ip'];
    $dom = @gethostbyaddr($ip);
    $addr = ($dom === $ip || @gethostbyname($dom) !== $ip) ? $ip : "{$ip} (" . strtoupper($dom) . ")";
}

// Дата регистрации
$joindate = ($user['added'] === '0000-00-00 00:00:00')
    ? 'N/A'
    : "{$safeAdded} (" . get_elapsed_time(sql_timestamp_to_unix_timestamp($user['added'])) . " назад)";

// Последнее посещение
$lastseen = ($user['last_access'] === '0000-00-00 00:00:00')
    ? $tracker_lang['never']
    : "{$safeLastAccess} (" . get_elapsed_time(sql_timestamp_to_unix_timestamp($user['last_access'])) . " назад)";

// Количество комментариев
$torrentcomments = (int)$user['comment_count'];

// Флаг страны
$country = '';
if ($safeFlag !== '') {
    $country = "<img height='15' src='pic/flag/{$safeFlag}' alt='{$safeCountryName}' title='{$safeCountryName}'>";
}

// Пол
$gender = match ($user['gender']) {
    '1' => 'Парень',
    '2' => 'Девушка',
    default => 'Не указан',
};

// Возраст (если задан день рождения)
$age = null;
if (!empty($user['birthday']) && $user['birthday'] !== '0000-00-00') {
    try {
        $tzOffsetSec = (int)($CURUSER['tzoffset'] ?? 0) * 60;
        $now         = (new DateTimeImmutable('@' . (time() + $tzOffsetSec)))->setTimezone(new DateTimeZone('UTC'));
        $birth       = new DateTimeImmutable($user['birthday'] . ' 00:00:00', new DateTimeZone('UTC'));
        $age         = (int)$now->diff($birth)->y;
    } catch (Throwable $e) {
        $age = null; // на всякий случай, если дата битая
    }
}

// Формируем список всех рангов (1 запрос, без повторного запроса на картинку)
$rangclass1 = "<option value=\"0\">---Выбрать ранг---</option>\n";
$currentRankPic  = '';
$currentRankName = '';

$res = sql_query("SELECT id, name, rangpic FROM rangclass ORDER BY name") or sqlerr(__FILE__, __LINE__);
while ($rank = mysqli_fetch_assoc($res)) {
    $rid      = (int)$rank['id'];
    $rname    = htmlspecialchars($rank['name'] ?? '');
    $selected = ((int)$user['rangclass'] === $rid) ? ' selected' : '';
    $rangclass1 .= "<option value=\"{$rid}\"{$selected}>{$rname}</option>\n";

    if ($selected) {
        $currentRankPic  = htmlspecialchars($rank['rangpic'] ?? '');
        $currentRankName = $rname;
    }
}

// Картинка текущего ранга (если есть)
$rangclass = '';
if ($currentRankPic !== '') {
    $rangclass = "<img src=\"/pic/{$currentRankPic}\" alt=\"{$currentRankName}\" title=\"{$currentRankName}\" style='margin-left:5pt' align=\"top\">";
}

// Заголовок страницы
stdhead("Просмотр профиля {$safeUsername}");


// ====================== Начало блока профиля ======================
begin_frame(
    "Профиль пользователя "
    . get_user_class_color($user['class'], $safeUsername)
    . get_user_icons($user, true)
    . " " . $country
);

// --- Статус (стили оставлены как есть) ---
$status = trim((string)($user['title'] ?? ''));
$status = ($status !== '')
    ? htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : 'Статус не указан';
?>

<style>
  /* можно вынести в общий CSS */
  .bubble { margin: 8px 0 14px; }
  .bubble .rounded {
    border: 1px solid #ddd; border-radius: 10px;
    padding: 8px; background: #fff;
  }
  .bubble blockquote, .bubble p { margin: 0; padding: 0; }
  .bubble p { line-height: 1.35; word-wrap: break-word; }
</style>

<div class="bubble">
  <div class="rounded">
    <blockquote><p><?= $status ?></p></blockquote>
  </div>
</div>

<table border="1" cellspacing="0" cellpadding="4" style="float:left;width:260px;margin-right:12px;">

<?php
// Аватар
$raw = trim((string)($user['avatar'] ?? ''));
if ($raw === '') $raw = '/pic/default_avatar.gif';

$avatarUrl = $raw;
if (!preg_match('~^(https?://|//|data:image/)~i', $raw)) {
    if ($raw[0] !== '/') $raw = '/' . $raw;
    $base = rtrim((string)($DEFAULTBASEURL ?? ''), '/');
    $avatarUrl = $base . $raw;
}
$avatarEsc = htmlspecialchars($avatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Ранг / класс
$userClassName = get_user_class_name($user['class']); // ← текст
// ВАЖНО: get_user_class_color возвращает готовый HTML — НЕ экранируем:
$userClassHtml = get_user_class_color($user['class'], $userClassName);

// Доп.поля только как текст
$rangclass = htmlspecialchars((string)($user['rangclass'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Фолбэк для onerror
$fallback = htmlspecialchars(($DEFAULTBASEURL ?? '') . '/pic/default_avatar.gif', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

echo <<<HTML
<tr>
  <td class="lol" align="center" colspan="2">
    <div id="screenshots">
      <img src="{$avatarEsc}" alt="avatar" width="100" height="100"
           loading="lazy" decoding="async" style="object-fit:cover;border-radius:12px"
           onerror="this.onerror=null;this.src='{$fallback}';">
      <br>
      {$userClassHtml}
    </div>
  </td>
</tr>
HTML;


?>


<script>
function karma(id, type, act) {
  $jq.post("karma.php", { id, act, type })
    .done(function (html) { $jq("#karma").html(html); })
    .fail(function () { alert("Не удалось выполнить действие кармы. Повторите позже."); });
}
</script>

<?php
// ------ Блок кармы
echo '<tr><td class="lol" align="center" colspan="2" id="karma">';

$karma_value   = karma((int)$user['karma']); // как у тебя
$isOwnProfile  = isset($CURUSER['id']) && (int)$CURUSER['id'] === (int)$user['id'];
$canRate       = isset($CURUSER['id']) && !$isOwnProfile && (int)($user['canrate'] ?? 0) > 0;
$uid           = (int)($CURUSER['id'] ?? 0);
$profileIdSafe = (int)$id;

if (!$canRate) {
    echo '<img src="pic/minus-dis.png" title="Вы не можете голосовать" /> '
       . $karma_value
       . ' <img src="pic/plus-dis.png" title="Вы не можете голосовать" />';
} else {
    echo '<img src="pic/minus.png" style="cursor:pointer;" title="Уменьшить карму" onclick="karma(\'' . $profileIdSafe . '\',\'user\',\'minus\');" /> '
       . $karma_value
       . ' <img src="pic/plus.png" style="cursor:pointer;" title="Увеличить карму" onclick="karma(\'' . $profileIdSafe . '\',\'user\',\'plus\');" />';
}

echo '</td></tr>';

// ------ Залито / Скачано / Рейтинг
$uploaded   = (float)($user['uploaded']   ?? 0);
$downloaded = (float)($user['downloaded'] ?? 0);
$upload_all = mksize($uploaded);
$down_all   = mksize($downloaded);

// корректный расчёт дней жизни аккаунта (минимум 1 день)
$addedRaw = $user['added'] ?? '0000-00-00 00:00:00';
if ($addedRaw !== '0000-00-00 00:00:00') {
    try {
        $addedDT = new DateTimeImmutable($addedRaw);
        $nowDT   = new DateTimeImmutable('now');
        $days    = max(1, (int)$addedDT->diff($nowDT)->days);
    } catch (Throwable $e) {
        $days = 1;
    }
} else {
    $days = 1;
}

$upped_per_day = '(' . mksize($uploaded   / $days) . '/день)';
$down_per_day  = '(' . mksize($downloaded / $days) . '/день)';

if (($user['hiderating'] ?? 'no') !== 'yes') {
    echo <<<HTML
<tr><td class="rowhead">Залито <img src="pic/arrowup.gif" alt=""></td><td class="lol" align="left">{$upload_all} {$upped_per_day}</td></tr>
<tr><td class="rowhead">Скачано <img src="pic/arrowdown.gif" alt=""></td><td class="lol" align="left">{$down_all} {$down_per_day}</td></tr>
HTML;

    if ($downloaded > 0) {
        $sr = $uploaded / $downloaded;
        $face = match (true) {
            $sr >= 4    => 'w00t',
            $sr >= 2    => 'grin',
            $sr >= 1    => 'smile1',
            $sr >= 0.5  => 'noexpression',
            $sr >= 0.25 => 'sad',
            default     => 'cry',
        };
        $sr_fmt   = number_format($sr, 3, '.', '');
        $sr_color = get_ratio_color($sr);
        $sr_output = '<table><tr><td class="embedded"><span style="color:'.$sr_color.'">'.$sr_fmt.'</span></td><td><img src="/pic/smilies/'.$face.'.gif" alt=""></td></tr></table>';
    } else {
        $sr_output = 'Нет';
    }

    echo '<tr><td class="rowhead">Рейтинг <img src="pic/rating.gif" alt=""></td><td class="lol" align="left">'.$sr_output.'</td></tr>';
} else {
    // бейдж скрытого рейтинга (оставлено)
    echo <<<HTML
<tr>
  <td colspan="2" align="center">
    <style>
      .rating-badge {
        display: inline-block;
        padding: 5px 12px;
        margin: 5px 0;
        border-radius: 6px;
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        border: 1px solid #86efac;
        color: #064e3b;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.3;
      }
      .rating-badge .mark {
        color: #e02424;
        font-weight: 700;
      }
    </style>
    <div class="rating-badge"
         title="Пользователю не учитывается рейтинг или загрузки, можно качать без ограничений">
      Рейтинг — <span class="mark">100</span> — Ограничений НЕТ
    </div>
  </td>
</tr>
HTML;
}

// ------ Ранг
if ($rangclass !== '') {
    echo '<tr><td class="rowhead">Ранг <img src="pic/logs.gif"></td><td class="lol" align="left">' . $rangclass . '</td></tr>';
}

// ------ Бонусы
echo '<tr><td class="rowhead">Бонус <img src="pic/kredit.gif"></td><td class="lol" align="left"><a href="mybonus.php">' . (int)($user['bonus'] ?? 0) . '</a></td></tr>';

// ------ Приглашения (число)
if (get_user_class() >= UC_MODERATOR) {
    echo '<tr><td class="rowhead">Приглаш. <img src="pic/relizer.gif"></td><td class="lol" align="left"><a href="invite.php?id=' . $profileIdSafe . '">' . (int)($user['invites'] ?? 0) . '</a></td></tr>';
}

// ------ Кто пригласил
if (!empty($user['invitedby'])) {
    $inviter_id = (int)$user['invitedby'];
    if ($stmt = mysqli_prepare($mysqli, 'SELECT username FROM users WHERE id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 'i', $inviter_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $inviter_username);
        if (mysqli_stmt_fetch($stmt)) {
            $inviter_name_safe = htmlspecialchars($inviter_username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<tr><td class="rowhead">Приглас. <img src="pic/relizer.gif"></td><td class="lol" align="left"><a href="userdetails.php?id=' . $inviter_id . '">' . $inviter_name_safe . '</a></td></tr>';
        }
        mysqli_stmt_close($stmt);
    }
}

// ------ Комментарии
echo '<tr><td class="rowhead">Коммент. <img src="pic/uplrules.gif"></td>';
$torrentcomments = (int)($torrentcomments ?? 0);
if ($torrentcomments && ((int)$user['id'] === $uid || get_user_class() >= UC_MODERATOR)) {
    echo '<td class="lol" align="left"><a href="userhistory.php?action=viewcomments&id=' . $profileIdSafe . '">' . $torrentcomments . '</a></td></tr>';
} else {
    echo '<td class="lol" align="left">' . $torrentcomments . '</td></tr>';
}

// ------ Онлайн (180 сек)
$lastTs = !empty($user['last_access']) ? strtotime($user['last_access']) : false;
$is_online = ((int)$user['id'] === $uid) ? true : ($lastTs !== false && $lastTs > time() - 180);
$online_status = $is_online
    ? '<font color="green"><b>Да</b></font> / <font color="gray">Нет</font>'
    : '<font color="gray">Да</font> / <font color="red"><b>Нет</b></font>';

echo '<tr><td class="rowhead">Онлайн <img src="pic/mygroup.gif"></td><td class="lol" align="left">' . $online_status . '</td></tr>';

// ------ Connectable
$connectable_html = "<b><font color='blue'>Ожидаем</font></b>";
if ($stmt = mysqli_prepare($mysqli, 'SELECT connectable FROM peers WHERE userid = ? LIMIT 1')) {
    mysqli_stmt_bind_param($stmt, 'i', $profileIdSafe);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $connectable);
    if (mysqli_stmt_fetch($stmt)) {
        $connectable_html = ($connectable === 'yes')
            ? "<b><font color='green'>Открыт</font></b>"
            : "<b><font color='red'>Закрыт</font></b>";
    }
    mysqli_stmt_close($stmt);
}
echo '<tr><td class="rowhead">Порт <img src="pic/zapros.gif"></td><td class="lol" align="left">' . $connectable_html . '</td></tr>';

// ------ Аккаунт отключен
if (($user['enabled'] ?? 'yes') !== 'yes') {
    echo '<tr><td colspan="2" class="lol" align="center"><b>Этот аккаунт отключен</b></td></tr>';
}

// ------ Подарить бонусы
echo '<tr><td colspan="2" align="center" class="lol"><div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;max-width:240px;margin:0 auto;">';
foreach ([10, 25, 50, 100] as $amount) {
    $a = (int)$amount;
    echo '<img src="pic/' . $a . '.png" alt="' . $a . '" title="Подарить ' . $a . ' бонусов" style="cursor:pointer;" onclick="present(\'' . $uid . '\', \'' . $profileIdSafe . '\', \'' . $a . '\');" />';
}
echo '</div></td></tr>';

?>
</table>

<table class="mainp" cellpadding="4" cellspacing="0" style="margin-left:272px;width:auto;">
<tr><td class="rowhead" width="1%">Зарегистрирован</td><td class="lol" width="99%" align="left"><?= $joindate ?></td></tr>
<tr><td class="rowhead" width="1%">Был на трекере</td><td class="lol" width="99%" align="left"><?= $lastseen ?></td></tr>

<?php
// --- Контакты модераторам ---
if (get_user_class() >= UC_MODERATOR) {
    $emailSafe = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($emailSafe !== '') {
        echo '<tr><td class="rowhead">Email</td><td class="lol" align="left"><a href="mailto:' . $emailSafe . '">' . $emailSafe . '</a></td></tr>' . "\n";
    }
}

// --- IP (2ip/WHOIS) ---
if (!empty($addr)) {
    $addrSafe = htmlspecialchars($addr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $infoUrl  = 'https://2ip.ru/lookup/?ip=' . rawurlencode($addr);
    $whoisUrl = 'https://2ip.ru/whois/?query=' . rawurlencode($addr);
    $isLocal = filter_var($addr, FILTER_VALIDATE_IP)
        && (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    $badge = $isLocal ? ' <span title="Частный/локальный диапазон">[локальный]</span>' : '';
    echo '<tr><td class="rowhead">IP</td><td class="lol" align="left">'
       . $addrSafe . $badge
       . ' [<a href="' . htmlspecialchars($infoUrl, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">2IP: Инфо</a>]'
       . ' [<a href="' . htmlspecialchars($whoisUrl, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">WHOIS</a>]'
       . '</td></tr>' . "\n";
}

// --- Пол ---
echo '<tr><td class="rowhead">Пол</td><td class="lol" align="left">' . $gender . '</td></tr>' . "\n";

// --- ДР / Возраст / Зодиак ---
if (!empty($user['birthday']) && $user['birthday'] !== '0000-00-00') {
    $birthdayRaw = $user['birthday'];
    $birthdayTs  = strtotime($birthdayRaw);
    $currentTs   = time() + (int)(($CURUSER['tzoffset'] ?? 0) * 60);

    if ($birthdayTs !== false) {
        try {
            $birthDT = (new DateTimeImmutable('@' . $birthdayTs))->setTimezone(new DateTimeZone('UTC'));
            $nowDT   = (new DateTimeImmutable('@' . $currentTs))->setTimezone(new DateTimeZone('UTC'));
            $ageVal  = (int)$nowDT->diff($birthDT)->y;
        } catch (Throwable $e) {
            $ageVal = '';
        }
        if ($ageVal !== '') {
            echo '<tr><td class="rowhead">Возраст</td><td class="lol" align="left">' . $ageVal . '</td></tr>' . "\n";
        }
        $birthdayFmt = date('d.m.Y', $birthdayTs);
        echo '<tr><td class="rowhead">Дата Рождения</td><td class="lol" align="left">' . $birthdayFmt . '</td></tr>' . "\n";
    }

// $zodiac = [[name, img, "dd-mm"/"dd.mm"], ...]
$birthdayRaw = (string)($user['birthday'] ?? $CURUSER['birthday'] ?? '');
$m = (int)substr($birthdayRaw, 5, 2);
$d = (int)substr($birthdayRaw, 8, 2);

$img = '';
if ($m && $d && !empty($zodiac) && is_array($zodiac)) {
    $cur = $m*100 + $d;
    $pick = null; $best = -1; $maxZ = null; $maxV = -1;

    foreach ($zodiac as $z) {
        if (!preg_match('~^(\d{2})[.\-](\d{2})$~', (string)$z[2], $mm)) continue;
        $v = (int)$mm[2]*100 + (int)$mm[1];        // ммдд из "dd-mm"
        if ($v > $maxV)         { $maxV = $v;  $maxZ = $z; }
        if ($v <= $cur && $v>$best) { $best = $v; $pick = $z; }
    }
    $chosen = $pick ?? $maxZ;
    if ($chosen && !empty($chosen[1])) {
        $rel = '/pic/zodiac/' . rawurlencode($chosen[1]);
        $img = '<img src="' . rtrim((string)($DEFAULTBASEURL ?? ''), '/') . $rel . '" alt="" width="20" height="20" style="vertical-align:middle">';
    }
}

echo '<tr><td class="rowhead">Знак зодиака</td><td class="lol" align="left">' . $img . '</td></tr>' . "\n";

}

/* =================== САЙТ ПОЛЬЗОВАТЕЛЯ =================== */
echo '<tr><td class="rowhead">Сайт</td><td class="lol">';
$siteUrlRaw = trim((string)($user['website'] ?? ''));
if ($siteUrlRaw !== '') {
    $isRelative = str_starts_with($siteUrlRaw, '/') || str_starts_with($siteUrlRaw, './') || str_starts_with($siteUrlRaw, '../') || str_starts_with($siteUrlRaw, '#') || str_starts_with($siteUrlRaw, '?');
    if ($isRelative) {
        $siteUrlSafe = htmlspecialchars($siteUrlRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $siteUrlSafe . '" rel="noopener noreferrer">' . $siteUrlSafe . '</a>';
    } else {
        $normalized = preg_match('~^[a-z][a-z0-9+\-.]*://~i', $siteUrlRaw) ? $siteUrlRaw : ('https://' . $siteUrlRaw);
        if (!filter_var($normalized, FILTER_VALIDATE_URL)) {
            echo htmlspecialchars($siteUrlRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $siteUrlSafe = htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<a href="' . $siteUrlSafe . '" target="_blank" rel="noopener noreferrer ugc nofollow">' . $siteUrlSafe . '</a>';
        }
    }
} else {
    echo 'Не указан';
}
echo "</td></tr>\n";

/* =================== ПОСЛЕДНЯЯ ПОСЕЩЁННАЯ СТРАНИЦА =================== */
$uidLocal = (int)($user['id'] ?? 0);
$lastUrl = null;

// поддержка $memcached и $mc
$cacheGet = static function (string $key) use (&$memcached, &$mc) {
    if ($memcached instanceof Memcached) return $memcached->get($key);
    if (isset($mc) && $mc instanceof Memcached) return $mc->get($key);
    return null;
};
$cacheSet = static function (string $key, $val, int $ttl = 60) use (&$memcached, &$mc) {
    if ($memcached instanceof Memcached) return $memcached->set($key, $val, $ttl);
    if (isset($mc) && $mc instanceof Memcached) return $mc->set($key, $val, $ttl);
    return false;
};

$cacheKey = "user:lasturl:$uidLocal";
if ($uidLocal > 0) {
    $lastUrl = $cacheGet($cacheKey);
    if ($lastUrl === null) {
        if ($stmt = mysqli_prepare($mysqli, 'SELECT url FROM sessions WHERE uid = ? ORDER BY time DESC LIMIT 1')) {
            mysqli_stmt_bind_param($stmt, 'i', $uidLocal);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $urlDb);
            if (mysqli_stmt_fetch($stmt)) {
                $lastUrl = (string)$urlDb;
            }
            mysqli_stmt_close($stmt);
        }
        $cacheSet($cacheKey, $lastUrl, 60);
    }
}
if (!empty($lastUrl)) {
    $urlSafe = htmlspecialchars($lastUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<tr><td class="rowhead">Сейчас на</td><td class="lol"><a href="' . $urlSafe . '">' . $urlSafe . '</a></td></tr>' . "\n";
}

/* =================== ВИЗИТЁРЫ СТРАНИЦЫ =================== */
// CSRF (оставлено)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$visUrl     = htmlspecialchars($VIS_URL ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$visTimeout = (int)($VIS_TIMEOUT ?? 15);
$csrfRaw    = $_SESSION['csrf_token'];
$csrfAttr   = htmlspecialchars($csrfRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$wid      = 'vh_' . (int)($user['id'] ?? random_int(1, 999999));
$boxId    = "visitors-$wid";
$btnId    = "visitors-refresh-$wid";
$statusId = "visitors-status-$wid";
$endpoint = '/updateVisitors.php';

// Рендер через visitorsList — как у тебя (строки ТАБЛИЦЫ корректно внутри этой таблицы)
echo visitorsList('
  <tr><td class="lol" colspan="12"></td></tr>
  <tr>
    <td class="colhead" colspan="12" style="text-align:center">
      Кто был на этой странице за последние ' . $visTimeout . ' мин
      <a href="#" id="' . $btnId . '" class="altlink" style="margin-left:6px">[обновить]</a>
      <span id="' . $statusId . '" class="small" style="margin-left:8px;opacity:.7"></span>
    </td>
  </tr>
  <tr>
    <td class="rowhead" colspan="12" style="text-align:left">
      <div id="' . $boxId . '"
           data-url="' . $visUrl . '"
           data-timeout-min="' . $visTimeout . '"
           data-csrf="' . $csrfAttr . '"
           data-endpoint="' . htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">[VISITORS]</div>
    </td>
  </tr>
', $VISITORS);

// Закрываем правую таблицу прямо здесь:
echo "</table>\n";
?>

<!-- Стили ДЛЯ БЛОКА ВИЗИТОРОВ — оставлены как у тебя, но вынесены ВНЕ таблицы -->
<style>
  /* аккуратный вывод списка */
  #<?= $boxId ?> { text-align:left; display:inline-flex; flex-wrap:wrap; gap:6px; align-items:center; }
  #<?= $boxId ?> a { white-space:nowrap; }
</style>

<script>
(function () {
  const box    = document.getElementById('<?= $boxId ?>');
  const btn    = document.getElementById('<?= $btnId ?>');
  const status = document.getElementById('<?= $statusId ?>');
  if (!box || !btn) return;

  const CSRF = '<?= $csrfRaw ?>';
  const ENDPOINT = '<?= $endpoint ?>';

  async function loadVisitors() {
    status.textContent = 'Обновляю...';

    const body = new URLSearchParams({
      action: 'list',
      url: box.dataset.url || (location.pathname + location.search),
      timeout: String(box.dataset.timeoutMin || 15),
      csrf_token: CSRF
    });

    try {
      const res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-CSRF-Token': CSRF
        },
        body,
        credentials: 'same-origin'
      });

      if (!res.ok) {
        const t = await res.text().catch(()=> '');
        throw new Error(`HTTP ${res.status} ${res.statusText}${t ? ' | ' + t.slice(0,200) : ''}`);
      }

      const data = await res.json();
      if (!data || data.ok !== true) {
        throw new Error(data?.error || 'Endpoint error');
      }

      box.innerHTML = data.html || '<em>Пусто</em>';
      status.textContent = '';
      setTimeout(() => (status.textContent = ''), 1500);

    } catch (e) {
      console.error('visitors fetch failed:', e);
      status.textContent = 'Не удалось обновить';
    }
  }

  btn.addEventListener('click', (ev) => { ev.preventDefault(); loadVisitors(); });
  (document.readyState === 'loading')
    ? document.addEventListener('DOMContentLoaded', loadVisitors)
    : loadVisitors();
})();
</script>

<?php
/* =================== НИЖНИЕ ССЫЛКИ ДЕЙСТВИЙ =================== */
$actionsStyle = 'style="margin-top:20px; clear:both; min-height:32px;"';

if ((int)($CURUSER['id'] ?? 0) !== (int)$id) {
    echo "<p>\n";
    echo '<a href="javascript:void(0);" onclick="ls(' . (int)$id . ');">Личное сообщение</a>&nbsp;| ' . "\n";

    $res = sql_query(
        "SELECT id FROM friends
         WHERE userid=" . sqlesc((int)($CURUSER['id'] ?? 0)) . "
           AND friendid=" . (int)$id . "
           AND status='yes'"
    ) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) > 0) {
        echo '<a href="javascript:void(0);" onclick="addtofriends(' . (int)$id . ', \'delete\');">Удалить из друзей</a>&nbsp;| ' . "\n";
    } else {
        echo '<a href="javascript:void(0);" onclick="addtofriends(' . (int)$id . ', \'add\');">Добавить в друзья</a>&nbsp;| ' . "\n";
    }

    if (get_user_class() >= UC_MODERATOR && (int)$user['class'] < get_user_class()) {
        echo '<a href="javascript:void(0);" onclick="moderate(' . (int)$id . ');">Модерирование</a>' . "\n";
    }
    echo "</p>\n";
    echo '<div id="actions" ' . $actionsStyle . '></div>' . "\n";
} else {
    echo '<div id="actions" ' . $actionsStyle . '></div>' . "\n";
}

// ====================== Конец блока профиля ======================
end_frame();


/////////////////////////////////////////////////////////////////////////////////////

echo '<div style="margin:20px 0; clear:both;"></div>';

begin_frame('Стена');

$id = (int)$id;
$perPage = 5;

// — счётчик с кэшем
$wallCount = function_exists('mc_get') ? mc_get("wall:count:$id") : null;
if (!is_int($wallCount)) {
    $wallCount = (int)get_row_count('wall', "WHERE owner = $id");
    if (function_exists('mc_set')) mc_set("wall:count:$id", $wallCount, 60);
}

// — пейджер
[$pagertop, $pagerbottom, $limit] = pager($perPage, $wallCount, "userdetails.php?id=$id&", ['lastpagedefault' => 1]);

// — записи
$sql = "
    SELECT
        w.id, w.owner, w.user, w.text, w.added,
        u.username, u.class,
        COALESCE(NULLIF(u.avatar,''), '/pic/default_avatar.gif') AS avatar
    FROM wall AS w
    LEFT JOIN users AS u ON u.id = w.user
    WHERE w.owner = $id
    ORDER BY w.added DESC $limit
";
$res = sql_query($sql);

// контейнер со «сбросом» центрирования
echo '<div id="wall" style="text-align:left;">';
echo '<div class="pager" style="margin-bottom:8px;">' . $pagertop . '</div>';

if (!$res || mysqli_num_rows($res) === 0) {
    echo '<p style="margin:0;">Нет записей.</p>';
} else {
    // совместимость: если вдруг fetch_all недоступен — fallback
    if (function_exists('mysqli_fetch_all')) {
        $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    } else {
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    }

    echo '<ul class="wall-list" style="list-style:none;margin:0;padding:0;">';

    foreach ($rows as $r) {
        $wid   = (int)$r['id'];
        $owner = (int)$r['owner'];
        $uid   = (int)$r['user'];
        $class = (int)$r['class'];

        $avatar = htmlspecialchars((string)$r['avatar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // имя отдаём через твою функцию цвета класса (она сама рендерит HTML)
        $uname  = get_user_class_color($class, (string)($r['username'] ?? '[Аноним]'));
        $text   = (string)format_comment((string)$r['text']); // BBCode → HTML (как у тебя)
        $added  = htmlspecialchars((string)nicetime((string)$r['added'], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $canDel = ((int)($CURUSER['id'] ?? 0) === $owner)
               || ((int)($CURUSER['id'] ?? 0) === $uid)
               || ((int)get_user_class() >= UC_MODERATOR);

        echo '
        <li id="wall-item-'.$wid.'" style="margin:0 0 10px 0;padding:10px;border:1px solid #e3e3e3;border-radius:10px;">
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <img src="'.$avatar.'" alt="" width="46" height="46" style="border-radius:8px;border:1px solid #ccc;object-fit:cover;flex:0 0 46px;">
            <div style="flex:1;min-width:0;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <a href="userdetails.php?id='.$uid.'">'.$uname.'</a>
                <div style="font-size:11px;color:#888;display:flex;gap:10px;align-items:center;">
                  <span>'.$added.'</span>'.
                  ($canDel
                    ? '<button type="button" class="wall-del" data-id="'.$wid.'" data-owner="'.$owner.'" title="Удалить" aria-label="Удалить запись" style="border:0;background:transparent;cursor:pointer;padding:0;margin:0;">
                         <img src="pic/warned2.gif" alt="Удалить" width="12" height="12">
                       </button>'
                    : ''
                  ).'
                </div>
              </div>
              <div class="wall-text" style="margin-top:6px; line-height:1.4;">'.$text.'</div>
            </div>
          </div>
        </li>';
    }
    echo '</ul>';
}

echo '<div class="pager" style="margin-top:8px;">' . $pagerbottom . '</div>';

// разделитель
echo '<hr noshade size="1" color="#CCCCCC" style="margin:10px 0;" />';

// форма новой записи (без центрирования текста, но кнопки по центру — как у тебя)
?>
<form id="wallform" style="text-align:center;" onsubmit="return false;">
  <?php textbbcode('wall', 'text', ''); ?>

  <!-- Оставляем твою разметку -->
  <div style="margin-top:10px; text-align:center;">
    <button type="submit" id="wall-send" class="glass-btn">Отправить</button>
    <button type="reset" class="glass-btn">Очистить</button>
  </div>

  <input type="hidden" name="owner" value="<?php echo (int)$id; ?>">
</form>

<script>
// При нажатии Enter в поле ввода — отправляем форму
document.getElementById('wallform').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    document.getElementById('wall-send').click();
  }
});
</script>

<script>
(function(){
  const form   = document.getElementById('wallform');
  const sendBtn= document.getElementById('wall-send');
  const wallEl = document.getElementById('wall');

  function b64(s){ try { return btoa(unescape(encodeURIComponent(s))); } catch(e){ return ''; } }

  async function postJSON(url, params) {
    const body = new URLSearchParams(params);
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept':'application/json,text/plain;q=0.8,*/*;q=0.5'
      },
      body
    });
    let data;
    try { data = await resp.json(); }
    catch (_) {
      const txt = await resp.text();
      throw new Error('Ответ не JSON: ' + txt.slice(0,200));
    }
    if (!resp.ok || data.ok === false) {
      throw new Error(data?.err || ('HTTP ' + resp.status));
    }
    return data;
  }

  // ОТПРАВКА
  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    const ta = form.querySelector('textarea[name="text"]');
    const owner = form.querySelector('input[name="owner"]').value;
    const text = (ta?.value || '').trim();
    if (!text) { ta && ta.focus(); return; }

    sendBtn.disabled = true;
    try {
      await postJSON('/wall.php', { act:'send', owner:String(owner), text:b64(text) });
      location.reload();
    } catch(e) {
      alert('Не удалось отправить: ' + e.message);
    } finally {
      sendBtn.disabled = false;
    }
  });

  // УДАЛЕНИЕ
  wallEl.addEventListener('click', async function(ev){
    const btn = ev.target.closest('.wall-del');
    if (!btn) return;
    if (!confirm('Удалить запись?')) return;

    const id = btn.getAttribute('data-id');
    try {
      await postJSON('/wall.php', { act:'delete', owner:'<?php echo (int)$id; ?>', post:String(id) });
      const li = document.getElementById('wall-item-' + id);
      if (li) li.remove();
    } catch(e) {
      alert('Не удалось удалить: ' + e.message);
    }
  });
})();
</script>

<?php
echo '</div>'; // #wall
end_frame();
echo '<div style="clear: both;"></div>';

/////////////////////////////////////////////////////////////////////////////////////

begin_frame("Информация о подключениях и передача бонусов");

// Подключаем JS
echo '<script src="js/user.js" type="text/javascript" defer></script>' . "\n";
?>
<div id="tabs">
    <span class="tab active" id="info">О себе</span>
    <span class="tab" id="friends">Друзья</span>
    <span class="tab" id="downloaded">Скачал</span>
    <span class="tab" id="uploaded">Загрузил</span>
    <span class="tab" id="downloading">Сейчас качает</span>
    <span class="tab" id="uploading">Сейчас раздает</span>
    <span id="loading"></span>

    <div id="body" user="<?php echo (int)$id; ?>">
        <?php
        if (empty($user['info'])) {
            echo '<div class="tab_error">Пользователь не сообщил эту информацию.</div>';
        } else {
            echo (string)format_comment((string)$user['info']);
        }
        ?>
    </div>
</div>
<?php
end_frame();

stdfoot();