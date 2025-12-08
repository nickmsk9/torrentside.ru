<?php
require 'include/bittorrent.php';
dbconn();
loggedinorreturn();
stdhead($lang['staff_header'] ?? 'Персонал');
begin_main_frame();

global $lang, $DEFAULTBASEURL, $memcached, $CURUSER;

/** ================== Константы ================== */
const STAFF_CACHE_KEY      = 'staff:v1'; // версия ключа
const STAFF_CACHE_TTL      = 600;        // 10 минут
const ONLINE_THRESHOLD_SEC = 600;        // онлайн, если заходил за последние N сек
const AVATAR_MAX_W         = 90;

/** Безопасный HTML-эскейп */
$h = static function (?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

/** Достаём персонал из кэша/БД (только нужные поля) */
$getStaff = static function () use ($memcached): array {
    $rows = null;
    if ($memcached instanceof Memcached) {
        $rows = $memcached->get(STAFF_CACHE_KEY);
        if ($rows === false && $memcached->getResultCode() !== Memcached::RES_SUCCESS) {
            $rows = null; // ключа нет
        }
    }
    if (!is_array($rows)) {
        $sql = "
          SELECT id, username, last_access, avatar, class, donor, warned, enabled, birthday
          FROM users
          WHERE class IN (".UC_MODERATOR.", ".UC_ADMINISTRATOR.", ".UC_SYSOP.")
          ORDER BY username
        ";
        $q = sql_query($sql) or sqlerr(__FILE__, __LINE__);
        $rows = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $rows[] = $row;
        }
        if ($memcached instanceof Memcached) {
            $memcached->set(STAFF_CACHE_KEY, $rows, STAFF_CACHE_TTL);
        }
    }
    return $rows;
};

/** Возраст по дню рождения с учётом tzoffset пользователя */
$calcAge = static function (?string $birthday) use ($CURUSER): string {
    if (!$birthday || $birthday === '0000-00-00') return '—';
    try {
        $offsetMinutes = (int)($CURUSER['tzoffset'] ?? 0);
        $tz = new DateTimeZone(sprintf('%+03d:%02d', intdiv($offsetMinutes, 60), abs($offsetMinutes % 60)));
        $now = new DateTimeImmutable('now', $tz);
        $dob = new DateTimeImmutable($birthday, $tz);
        return (string)$dob->diff($now)->y;
    } catch (Throwable) {
        return '—';
    }
};

/** Онлайн/оффлайн */
$isOnline = static function (?string $lastAccess): bool {
    $ts = $lastAccess ? @strtotime($lastAccess) : 0;
    return $ts && $ts > (time() - ONLINE_THRESHOLD_SEC);
};

/** Нормализация и рендер аватара */
$avatarTag = static function (?string $raw) use ($h): string {
    $fallback = '/pic/default_avatar.gif';
    $s = trim((string)$raw);

    // BBCode [img]...[/img]
    if ($s !== '' && preg_match('~^\[img\](.+?)\[/img\]$~i', $s, $m)) {
        $s = trim($m[1]);
    }
    // //cdn…
    if ($s !== '' && str_starts_with($s, '//')) {
        $s = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:') . $s;
    }
    // запрет опасных схем
    $lower = strtolower($s);
    if ($s === '' ||
        str_starts_with($lower, 'javascript:') ||
        (str_starts_with($lower, 'data:') && !preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $s))
    ) {
        $s = $fallback;
    }
    // http → https при https-странице
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && str_starts_with($lower, 'http://')) {
        $s = 'https://' . substr($s, 7);
    }
    // относительные пути → абсолютные от корня
    if ($s !== '' && !preg_match('~^(?:https?://|/).+~i', $s)) {
        $s = '/' . ltrim($s, '/');
    }

    $src = $h($s ?: $fallback);
    return '<img src="'.$src.'" alt="Аватар" style="max-width:'.AVATAR_MAX_W.'px;max-height:'.AVATAR_MAX_W.'px;border-radius:50%;box-shadow:0 0 4px rgba(0,0,0,0.3);border:1px double #ccc" loading="lazy" referrerpolicy="no-referrer">';
};

/** Рендер «ник + иконки», гарантируем отображение warnedbig.gif */
$renderNameWithIcons = static function (array $u): string {
    $id       = (int)$u['id'];
    $username = (string)$u['username'];
    $class    = (int)$u['class'];

    // цветной ник
    $name = function_exists('get_user_class_color')
        ? get_user_class_color($class, $username)
        : htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // базовые иконки TBDev (донор, аплоадер и т.п.)
    $iconsHtml = function_exists('get_user_icons') ? get_user_icons($u) : '';

    // устойчиво добавляем «предупреждён» (если вдруг get_user_icons это не выводит)
    $warn = $u['warned'] ?? null;
    $isWarned = !empty($warn) && $warn !== 'no' && $warn !== '0' && $warn !== 0;
    if ($isWarned) {
        $iconsHtml .= ' <img src="/pic/warnedbig.gif" alt="" title="Предупреждён" style="vertical-align:middle">';
    }

    return $name . $iconsHtml;
};


/** Одна карточка сотрудника */
$staffCard = static function (array $u) use ($DEFAULTBASEURL, $h, $calcAge, $isOnline, $avatarTag, $renderNameWithIcons): string {
    $id          = (int)$u['id'];
    $nameHtml    = $renderNameWithIcons($u);
    $onlineHtml  = $isOnline($u['last_access'])
        ? '<span class="badge-online">Онлайн</span>'
        : '<span class="badge-offline">Оффлайн</span>';
    $avatar      = $avatarTag($u['avatar']);
    $profileUrl  = rtrim($DEFAULTBASEURL ?? '', '/') . "/userdetails.php?id={$id}";
    $pmUrl       = "message.php?action=sendmessage&amp;receiver={$id}";

    // надписи (с локализацией)
    $L_nick     = $h($lang['staff_nick']     ?? 'Ник');
    $L_age      = $h($lang['staff_age']      ?? 'Возраст');
    $L_status   = $h($lang['staff_status']   ?? 'Статус');
    $L_contact  = $h($lang['staff_contact']  ?? 'Связь');
    $L_pm       = $h($lang['staff_pm']       ?? 'ЛС');

    return <<<HTML
<td class="embedded" width="33%">
  <div id="rounded-box-3">
    <b class="r3"></b><b class="r1"></b><b class="r1"></b>
    <div class="inner-box">
      <table border="0" cellpadding="10" cellspacing="0" width="100%">
        <tr>
          <td class="embedded">
            <div class="left-card" style="width:240px;min-height:105px;margin:3px;">
              <table class="inlay" width="100%">
                <tr valign="top">
                  <td width="90">{$avatar}</td>
                  <td width="140">
                    <p><b>{$L_nick}:</b> <a href="{$h($profileUrl)}">{$nameHtml}</a></p>
                    <p><b>{$L_age}:</b> {$h($calcAge($u['birthday']))}</p>
                    <p><b>{$L_status}:</b> {$onlineHtml}</p>
                    <p><b>{$L_contact}:</b> <a href="{$pmUrl}">{$L_pm}</a></p>
                  </td>
                </tr>
              </table>
            </div>
          </td>
        </tr>
      </table>
    </div>
    <b class="r1"></b><b class="r1"></b><b class="r3"></b>
  </div>
</td>
HTML;
};

/** Секция (по 3 карточки в строке) */
$renderSection = static function (string $title, array $users) use ($staffCard, $h): string {
    if (!$users) return '';
    $html = [];
    $html[] = '<tr><td class="embedded" colspan="12"><div class="c_title">'.$h($title).'</div></td></tr>';
    $html[] = '<tr><td class="embedded" colspan="12"><hr color="#4040c0" size="1"></td></tr>';

    $col = 0; $rowOpen = false;
    foreach ($users as $u) {
        if ($col === 0) { $html[] = '<tr>'; $rowOpen = true; }
        $html[] = $staffCard($u);
        if (++$col === 3) { $html[] = '</tr>'; $rowOpen = false; $col = 0; }
    }
    if ($rowOpen) {
        while ($col < 3) { $html[] = '<td class="embedded" width="33%"></td>'; $col++; }
        $html[] = '</tr>';
    }
    $html[] = '<tr><td class="embedded" colspan="12">&nbsp;</td></tr>';
    return implode("\n", $html);
};

/** ------ Данные ------ */
$all = $getStaff();

/** Группировка по классам */
$byClass = [
    UC_SYSOP         => [],
    UC_ADMINISTRATOR => [],
    UC_MODERATOR     => [],
];
foreach ($all as $u) {
    $cls = (int)$u['class'];
    if (isset($byClass[$cls])) $byClass[$cls][] = $u;
}

/** Заголовки секций */
$T_sys  = $lang['class_sysop']         ?? 'Сисопы';
$T_adm  = $lang['class_administrator'] ?? 'Администраторы';
$T_mod  = $lang['class_moderator']     ?? 'Модераторы';

/** Заголовок страницы */
begin_frame($lang['staff_header'] ?? 'Персонал');
?>
<style>
  table.inlay td { border: none; padding: 3px; }
  table.inlay p  { margin: 0; }
  .badge-online, .badge-offline { display:inline-block; padding:2px 6px; font-size:12px; border-radius:10px; }
  .badge-online  { background:#e6ffed; color:#036703; }
  .badge-offline { background:#fdeaea; color:#8a0404; }
  .c_title { font-weight:700; font-size:15px; }
</style>

<table width="100%" cellspacing="0">
  <tr><td class="embedded" colspan="12"><?=$h($lang['staff_notice_rules'] ?? 'Вопросы, на которые есть ответы в правилах или FAQ, будут оставлены без внимания.')?></td></tr>
  <tr><td class="embedded" colspan="12"><span style="color:red"><b><?=$h($lang['staff_notice_beg'] ?? 'Навязчивые просьбы на должность Администратора и Модератора будут караться баном!')?></b></span></td></tr>
  <tr><td class="embedded" colspan="12">&nbsp;</td></tr>

  <?= $renderSection($T_sys, $byClass[UC_SYSOP] ?? []) ?>
  <?= $renderSection($T_adm, $byClass[UC_ADMINISTRATOR] ?? []) ?>
  <?= $renderSection($T_mod, $byClass[UC_MODERATOR] ?? []) ?>
</table>
<?php
end_frame();
end_main_frame();

/** Универсальный эскейп для HTML */
$e = static function (mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};


/** --------------------------
 *  Поддержка
 *  -------------------------- */
if (get_user_class() >= UC_USER) {

    // онлайн-порог (например, 15 минут)
    $dt_limit = time() - 15 * 60;

    // Экранирующий хелпер
    $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Кэшируем список поддержки на 60 сек.
    $cache_key = 'support_list:v1';
    $rows = '';

    // --- чтение из кэша
    $cached = function_exists('mc_get') ? mc_get($cache_key) : null;
    if ($cached === null) {
        if (isset($memcached) && $memcached instanceof Memcached) {
            $cached = $memcached->get($cache_key);
        } elseif (isset($Memcached) && $Memcached instanceof Memcached) {
            $cached = $Memcached->get($cache_key);
        }
    }

    if (is_string($cached)) {
        $rows = $cached;
    } else {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.last_access,
                u.supportfor,
                c.name    AS country_name,
                c.flagpic AS flagpic
            FROM users AS u
            LEFT JOIN countries AS c ON u.country = c.id
            WHERE u.support = 'yes'
              AND u.status  = 'confirmed'
            ORDER BY u.username
            LIMIT 10
        ";
        $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

        while ($user = mysqli_fetch_assoc($res)) {
            // last_access может быть unix ts или datetime
            $la_raw = $user['last_access'] ?? '';
            $lastAccessTs = is_numeric($la_raw) ? (int)$la_raw : (int)strtotime((string)$la_raw);

            $is_online    = $lastAccessTs > $dt_limit;
            $online_badge = $is_online
                ? '<span class="badge-online">Online</span>'
                : '<span class="badge-offline">Offline</span>';

            // Экраним всё заранее
            $uid         = (int)($user['id'] ?? 0);
            $uname       = $e($user['username']     ?? '');
            $countryName = $e($user['country_name'] ?? '');
            $flagpic     = $e($user['flagpic']      ?? '');
            $supportfor  = $e($user['supportfor']   ?? '');

            // Флаг: если нет — оставляем пустую ячейку (не <td> пропускать!)
            $flagHtml = '';
            if ($flagpic !== '') {
                $flagHtml = '<img src="pic/flag/'.$flagpic.'" title="'.$countryName
                        . '" alt="'.$countryName.'" class="flag-icon" loading="lazy">';
            }

            // Порядок колонок строго как в THEAD: Флаг | Пользователь | Статус | ЛС | Область помощи
            $rows .=
                '<tr>'
              .   '<td class="col-flag">'.$flagHtml.'</td>'
              .   '<td class="col-user user-cell"><a href="userdetails.php?id='.$uid.'">'.$uname.'</a></td>'
              .   '<td class="col-status">'.$online_badge.'</td>'
              .   '<td class="col-pm"><a href="message.php?action=sendmessage&amp;receiver='.$uid
              .       '" title="Личное сообщение"><img src="pic/button_pm.gif" alt="PM" class="pm-icon" loading="lazy"></a></td>'
              .   '<td class="col-area"><span class="area-cell">'.$supportfor.'</span></td>'
              . '</tr>'."\n";
        }

        // --- запись в кэш (любой доступный механизм)
        $cache_put_ok = false;
        if (function_exists('mc_set')) {
            $cache_put_ok = mc_set($cache_key, $rows, 60);
        }
        if (!$cache_put_ok && isset($memcached) && $memcached instanceof Memcached) {
            $memcached->set($cache_key, $rows, 60);
            $cache_put_ok = true;
        }
        if (!$cache_put_ok && isset($Memcached) && $Memcached instanceof Memcached) {
            $Memcached->set($cache_key, $rows, 60);
        }
    }

    begin_frame($lang['support_header'] ?? 'Поддержка сайта');

    echo <<<HTML
<style>
  /* ——— компактный, «ровный» стол без центровок ——— */
  .staff-support { width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; }
  .staff-support thead th {
    text-align:left; font-weight:600; font-size:13px; padding:8px 10px; border-bottom:1px solid #e6e6e6;
    background:#fafafa;
  }
  .staff-support tbody td {
    text-align:left; font-size:13px; padding:8px 10px; border-bottom:1px solid #f2f2f2; vertical-align:middle;
  }
  /* фиксируем ширины, чтобы строки не «гуляли» */
  .col-flag   { width:44px;  }
  .col-user   { width:220px; }
  .col-status { width:90px;  }
  .col-pm     { width:52px;  text-align:center; } /* центр только для иконки ЛС */
  .col-area   { width:auto;  }

  .flag-icon { width:20px; height:14px; vertical-align:middle; display:inline-block; }
  .user-cell { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .area-cell { overflow:hidden; display:inline-block; white-space:nowrap; text-overflow:ellipsis; max-width:100%; }

  .pm-icon { width:20px; height:12px; vertical-align:middle; opacity:.9; }
  .pm-icon:hover { opacity:1; }

  .badge-online, .badge-offline {
    display:inline-block; padding:2px 6px; font-size:12px; line-height:1; border-radius:999px;
    border:1px solid transparent; user-select:none;
  }
  .badge-online  { background:#eaffea; color:#1b7a1b; border-color:#cfe9cf; }
  .badge-offline { background:#fff1f1; color:#8a1a1a; border-color:#f1d0d0; }

  /* убираем лишнюю вертикальную «воздушность» в begin_frame-вёрстке, если она есть */
  .staff-support-wrap { margin-top:2px; }
</style>

<div class="staff-support-wrap">
  <table class="staff-support">
    <colgroup>
      <col class="col-flag"><col class="col-user"><col class="col-status"><col class="col-pm"><col class="col-area">
    </colgroup>
    <thead>
      <tr>
        <th>Флаг</th>
        <th>Пользователь</th>
        <th>Статус</th>
        <th>ЛС</th>
        <th>Область помощи</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>
</div>
HTML;

    end_frame();
}

/** --------------------------
 *  Инструменты администратора (единый стиль)
 *  -------------------------- */
if (get_user_class() >= UC_ADMINISTRATOR) {
    begin_frame($lang['admin_tools_header'] ?? "Инструменты администратора <span style='color:#009900'>(видно только администраторам)</span>");
    echo <<<HTML
<style>
  /* Единый компактный стиль, созвучный staff-support */
  .admin-toolbar { 
    display:flex; flex-wrap:wrap; gap:8px; 
    align-items:center; justify-content:flex-start;
    margin:2px 0 4px;
  }
  .admin-toolbar .tool-btn {
    display:inline-block;
    padding:8px 12px;
    font-size:13px; line-height:1.2;
    color:#222; text-decoration:none;
    background:#fff; border:1px solid #dddddd;
    border-radius:999px; /* чипы */
    transition:background .15s ease, border-color .15s ease, box-shadow .15s ease;
    user-select:none;
  }
  .admin-toolbar .tool-btn:hover {
    background:#f7f7f7; border-color:#cfcfcf;
  }
  .admin-toolbar .tool-btn:active {
    background:#f1f1f1; border-color:#c5c5c5;
  }
  .admin-toolbar .tool-btn:focus {
    outline:none; box-shadow:0 0 0 3px rgba(0,118,255,.15);
  }
</style>

<nav class="admin-toolbar" aria-label="Инструменты администратора">
  <a class="tool-btn" href="warned.php">Предупр. юзеры</a>
  <a class="tool-btn" href="adduser.php">Добавить юзера</a>
  <a class="tool-btn" href="recover.php">Восстановить юзера</a>
  <a class="tool-btn" href="uploaders.php">Аплоадеры</a>
  <a class="tool-btn" href="users.php">Список юзеров</a>
  <a class="tool-btn" href="tags.php">Теги</a>
  <a class="tool-btn" href="smilies.php">Смайлы</a>
  <a class="tool-btn" href="delacctadmin.php">Удалить юзера</a>
  <a class="tool-btn" href="stats.php">Статистика</a>
  <a class="tool-btn" href="testip.php">Проверка IP</a>
  <a class="tool-btn" href="ipcheck.php">Повторные IP</a>
  <a class="tool-btn" href="findnotconnectable.php">Юзеры за NAT</a>
</nav>
HTML;
    end_frame();
}


stdfoot();
