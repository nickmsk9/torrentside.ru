<?php
require "include/bittorrent.php";
gzip();
dbconn(true);
stdhead($tracker_lang['war']);

/** =========================
 *  Сервисные настройки
 *  ========================= */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Amsterdam');
}

// ---- Memcached (persistent) ----
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached('tbdev-persistent');
    if (empty($memcached->getServerList())) {
        $memcached->addServer('127.0.0.1', 11211);
    }
    $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
    $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);
    $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 80);
    $memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
    $memcached->setOption(Memcached::OPT_POLL_TIMEOUT, 80);
}

/** =========================
 *  Ключи кэша/локов
 *  ========================= */
const NS                 = 'home:';                         // namespace
const MC_KEY_CFG         = NS . 'cfg:active_super_loto';
const MC_TTL_CFG         = 300;
const MC_LOCK_KEY_PREFIX = NS . 'loto:lock:';               // + YYYY-MM-DD
const MC_LOCK_SHORT      = 300;                              // 5 мин
const MC_NEWS_KEY        = NS . 'news:last';
const MC_NEWS_TTL        = 300;

/** =========================
 *  Время и границы
 *  ========================= */
$secTillMidnight = strtotime('tomorrow 00:00:00') - time();
$day   = (int)date('N');   // 1..7 (1-пн, 7-вс)
$hour  = (int)date('H');
$today = date('Y-m-d');

/** =========================
 *  active_super_loto (кэш флага)
 *  ========================= */
$active_loto = (int)$memcached->get(MC_KEY_CFG);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS) {
    $res = sql_query("SELECT `value` FROM `config` WHERE `config`='active_super_loto' LIMIT 1");
    $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_row($res) : null;
    $active_loto = (int)($row[0] ?? 0);
    $memcached->set(MC_KEY_CFG, $active_loto, MC_TTL_CFG);
}

/** =========================
 *  Доп. защиты
 *  ========================= */

// A) Уже разыгрывали сегодня? (быстрее через EXISTS)
$already = 0;
$qAlready = sql_query("SELECT EXISTS(SELECT 1 FROM `super_loto_winners` WHERE `date` = " . sqlesc($today) . ") AS ex");
if ($qAlready) {
    $already = (int)mysqli_fetch_row($qAlready)[0];
    if ($already === 1 && $active_loto != 0) {
        sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
        $memcached->set(MC_KEY_CFG, 0, MC_TTL_CFG);
    }
}

// B) Есть ли активные билеты?
$hasTickets = 0;
$qTickets = sql_query("SELECT EXISTS(SELECT 1 FROM `super_loto_tickets` WHERE `active`=0) AS ex");
if ($qTickets) {
    $hasTickets = (int)mysqli_fetch_row($qTickets)[0];
}

/** =========================
 *  Логика запуска розыгрыша
 *  ========================= */
if ($day === 7 && $hour >= 18 && $active_loto === 0 && $already === 0 && $hasTickets === 1) {
    $lockKey = MC_LOCK_KEY_PREFIX . $today;

    if ($memcached->add($lockKey, 1, MC_LOCK_SHORT)) {
        // поднять флаг на время розыгрыша
        sql_query("UPDATE `config` SET `value`=1 WHERE `config`='active_super_loto'");
        $memcached->set(MC_KEY_CFG, 1, MC_TTL_CFG);

        try {
            // ВАЖНО: корректный файл с логикой розыгрыша
            include __DIR__ . '/get_loto_winners1.php';

            // суточный лок, чтобы повторно не запустили сегодня
            $memcached->set($lockKey, 1, $secTillMidnight);
        } finally {
            // в любом случае опускаем флаг
            sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
            $memcached->set(MC_KEY_CFG, 0, MC_TTL_CFG);
        }
    }
} elseif ($day !== 7 && $active_loto === 1) {
    // вне воскресенья — флаг должен быть опущен
    sql_query("UPDATE `config` SET `value`=0 WHERE `config`='active_super_loto'");
    $memcached->set(MC_KEY_CFG, 0, MC_TTL_CFG);
}

/** =========================
 *  UI: рамка для постера (оставлено)
 *  ========================= */
?>
<style>
  .glass-frame {
    display:block; text-align:left; width:fit-content;
    padding:6px; border-radius:14px;
    background:rgba(255,255,255,0.1);
    border:1px solid rgba(255,255,255,0.3);
    box-shadow:0 4px 12px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.2);
    backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); margin:0;
  }
  .glass-frame img { display:block; border-radius:10px; width:200px; height:300px; object-fit:cover; }
</style>
<script>
(function(){
  const onMove=e=>{
    const el=e.currentTarget, r=el.getBoundingClientRect();
    const x=((e.clientX??(e.touches?.[0]?.clientX||0))-r.left)/r.width*100;
    const y=((e.clientY??(e.touches?.[0]?.clientY||0))-r.top)/r.height*100;
    const tiltX=(50-y)/30, tiltY=(x-50)/30;
    el.style.transform=`perspective(700px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
  };
  const reset=e=>{ e.currentTarget.style.transform='none' };
  for (const el of document.querySelectorAll('.glass-wrap')){
    el.addEventListener('pointermove', onMove, {passive:true});
    el.addEventListener('pointerleave', reset);
    el.addEventListener('touchmove', onMove, {passive:true});
    el.addEventListener('touchend', reset);
  }
})();
function SmileIT(smile, form, text) {
  document.forms[form].elements[text].value += " " + smile + " ";
  document.forms[form].elements[text].focus();
}
function mySubmit(){ setTimeout(()=>document.shbox.reset(),10); }
</script>
<?php

/** =========================
 *  Блок новостей (оптим.)
 *  =========================
 *  Раньше тянули 10 штук и брали первую; теперь — сразу LIMIT 1.
 *  Кэшируем ровно то, что выводим.
 */
$news = $memcached->get(MC_NEWS_KEY);
if ($memcached->getResultCode() !== Memcached::RES_SUCCESS) {
    $q = sql_query("SELECT id, body FROM news ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $news = $q && mysqli_num_rows($q) ? mysqli_fetch_assoc($q) : null;
    $memcached->set(MC_NEWS_KEY, $news, MC_NEWS_TTL);
}

$content = '';
if (!empty($news)) {
    $adminTools = '';
    if (get_user_class() >= UC_ADMINISTRATOR) {
        $adminTools = ' - <font class="small">[<a class="altlink" href="news.php?action=edit&newsid='
            . (int)$news['id'] . '&returnto=' . urlencode($_SERVER['PHP_SELF'])
            . '"><b>Редактировать</b></a>]</font>';
    }
    $content .= '<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">';
    $content .= '<div>' . format_comment((string)$news['body']) . '</div>';
    $content .= '</td></tr></table>';
}

/** =========================
 *  Чат (как было)
 *  ========================= */
$content .= <<<HTML
<br>
<iframe src="shoutbox.php" width="95%" height="200" align="center" frameborder="0" name="sbox" marginwidth="0" marginheight="0" style="border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></iframe>
<br>
HTML;

if (!empty($CURUSER)) {
    $content .= <<<HTML
<form action="shoutbox.php" method="get" target="sbox" name="shbox" onSubmit="mySubmit()" style="text-align: center; margin-top: 10px;">
    <input type="text" name="shbox_text" size="100" placeholder="Введите сообщение..." style="
        padding: 6px; border: 1px solid #ccc; border-radius: 6px;
        font-family: Verdana, sans-serif; font-size: 12px;">
    <input type="hidden" name="sent" value="yes">
    <input type="submit" value="Сказать" style="
        padding: 6px 12px; margin-left: 6px; border: none;
        background: linear-gradient(to right, #5b5bff, #9e72ff);
        color: #fff; border-radius: 6px; font-weight: bold; cursor: pointer;">
    &nbsp;
    <a href="shoutbox.php" target="sbox" style="
        padding: 6px 10px; background: #eee; border-radius: 6px;
        text-decoration: none; font-weight: bold; font-size: 12px; color: #333; margin-left: 5px;">Обновить</a>
    <br><br>
    <fieldset style="
        display: inline-block; border: 1px solid #ccc; padding: 10px; margin-top: 10px;
        border-radius: 10px; background: #f5f5ff; max-width: 90%; text-align: left;">
        <legend style="font-weight: bold;">Смайлы</legend>
HTML;

    $smiles = [
        'smile2','laugh','wink','noexpression','wavecry','whistle','yes','no','love','blink','rolleyes','devil',
        'baby','ras','evilmad','hmm','kiss','unsure','shifty','nugget','snap','shutup','evo','shit','spidey','ike'
    ];
    $codes = [
        ':smile:',':lol:',';-)',':-|',':wavecry:',':whistle:',':yes:',':no:',':love:',':blink:',':rolleyes:',':devil:',
        ':baby:',':ras:',':evilmad:',':hmm:',':kiss:',':unsure:',':shifty:',':nugget:',':snap:',':shutup:',':evo:',':shit:',':spidey:',':ike:'
    ];
    foreach ($smiles as $i => $img) {
        $code = htmlspecialchars($codes[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $src  = "pic/smilies/{$img}.gif";
        $content .= "<a href=\"javascript: SmileIT('$code','shbox','shbox_text')\"><img src=\"$src\" style=\"margin: 2px; vertical-align: middle;\"></a>";
    }
    $content .= "</fieldset></form>";
}

/** =========================
 *  Заголовок и вывод блока «Новости и чат»
 *  ========================= */
$cls = (int)($CURUSER['class'] ?? 0);
$newschat = "Новости и чат";
if ($cls >= UC_MODERATOR) {
    $newschat .= ' - <font class="small">[<a class="altlink" href="news.php"><b>Создать</b></a>]</font>';
}

begin_frame($newschat, "100", false, 10, true);
echo $content;
end_frame();


////////////////////////////////////////////////
// ОПРОС — только для залогиненных с классом >= UC_USER
$cls  = get_user_class();                     // int|null
$uid  = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
$can_show_poll = ($uid > 0 && $cls !== null && $cls >= UC_USER);

if ($can_show_poll) {
    $is_mod = ($cls >= UC_MODERATOR);

    $votetitle = "Опрос";
    if ($is_mod) {
        $votetitle .= ' - <font class="small">[<a href="makepoll.php?returnto=/index.php"><b>Создать</b></a>]</font>';
    }

    // ---- Memcached (lazy, с единым persistent-имени) ----
    if (!isset($memcached) || !($memcached instanceof Memcached)) {
        $memcached = new Memcached('tbdev-persistent');
        if (empty($memcached->getServerList())) {
            $memcached->addServer('127.0.0.1', 11211);
        }
    }

    // Кэшируем блок в двух вариантах (mod/non-mod), 5 минут
    $cache_key   = 'poll:block:v1:mod:' . (int)$is_mod;
    $poll_block  = $memcached->get($cache_key);

    if ($poll_block === false) {
        ob_start();

        begin_frame($votetitle);
        ?>
        <?php if (!defined('POLL_JS_LOADED')): ?>
            <?php define('POLL_JS_LOADED', true); ?>
            <script src="js/poll.core.js" defer></script>
            <style>
              /* центрирование без таблицы */
              #poll_container{ text-align:left; max-width:720px; margin:0 auto; }
              #loading_poll{ text-align:center; padding:6px 0; }
            </style>
        <?php endif; ?>

        <script>
          // безопасный старт: ждём jQuery-алиас $jq и DOM
          (function waitPollInit(){
            if (typeof window.$jq === "function" && document.readyState !== "loading") {
              $jq(function(){ if (typeof loadpoll === "function") loadpoll(); });
            } else {
              setTimeout(waitPollInit, 50);
            }
          })();
        </script>

        <div id="poll_container">
          <div id="loading_poll" style="display:none"></div>
          <noscript><b>Для отображения опроса включите JavaScript.</b></noscript>
        </div>
        <?php
        end_frame();

        $poll_block = ob_get_clean();
        $memcached->set($cache_key, $poll_block, 300);
    }

    echo $poll_block;
}
// ВАЖНО: никаких return/exit — ниже могут быть другие блоки
////////////////////////////////////////////////

////////////////////////////////////////////////


//////////////////////////////////////////////////////////////
// NEW: «Новые поступления» — оптимизированный блок

// Рекомендованные индексы (выполнить один раз в БД):
// 1) основной под сортировку и фильтры
//   CREATE INDEX idx_torrents_vis_mod_id ON torrents (visible, moderated, id DESC);
// 2) если сможете заменить "category <> 31" на whitelist IN (...):
//   CREATE INDEX idx_torrents_vis_mod_cat_id ON torrents (visible, moderated, category, id DESC);

$CACHE_KEY  = 'home:new_torrents:v2';
$CACHE_TTL  = 180; // 3 минуты. Можно 60–300 c.
$res = $memcached->get($CACHE_KEY);

if ($res === false) {
    // NB: тянем только нужные поля, и режем descr на стороне SQL (меньше трафика и I/O).
    // "SUBSTRING(descr, 1, 1200)" даёт запас под форматирование и усечём до 400 позже.
    $sql = "
        SELECT
            id,
            name,
            size,
            image1,
            leechers,
            seeders,
            times_completed,
            added,
            free,
            SUBSTRING(descr, 1, 1200) AS descr
        FROM torrents
        WHERE category <> 31
          AND visible = 'yes'
          AND moderated = 'yes'
        ORDER BY id DESC
        LIMIT 5
    ";
    $result = sql_query($sql) or sqlerr(__FILE__, __LINE__);

    $res = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Приводим типы и добавляем производные поля тут, чтобы кэшировать уже готовые данные
        $row['id']              = (int)$row['id'];
        $row['size']            = (int)$row['size'];
        $row['leechers']        = (int)$row['leechers'];
        $row['seeders']         = (int)$row['seeders'];
        $row['times_completed'] = (int)$row['times_completed'];
        $row['free']            = (int)$row['free']; // tinyint процентов freeleech (по вашей схеме)

        $res[] = $row;
    }

    $memcached->set($CACHE_KEY, $res, $CACHE_TTL);
}

// Заголовок блока (как было)
$blocktitle = "Новые поступления" . (get_user_class() >= UC_MODERATOR
    ? "<font class=\"small\"> - [<a class=\"altlink\" href=\"upload.php\"><b>Загрузить</b></a>]</font>"
    : "<font class=\"small\"> - (торренты без сидов - не отображаются!)</font>");

foreach ($res as $row) {
    // Корректировки «*_net» убираю — этих полей нет в вашей схеме, оставлю мягкий фолбэк:
    if (isset($row['leechers_net']))        $row['leechers']        += (int)$row['leechers_net'];
    if (isset($row['seeders_net']))         $row['seeders']         += (int)$row['seeders_net'];
    if (isset($row['times_completed_net'])) $row['times_completed'] += (int)$row['times_completed_net'];

    // Размер
    $size = mksize($row['size']);

    // Безопасные строки
    $nameSafe = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // FREELEECH (у вас нет поля external; оставляю отображение процента free)
    $freeLabel = $row['free'] > 0 ? ($row['free'] . '%') : '0%';

    // Постер
    $img = '';
    if (!empty($row['image1'])) {
        $imgUrl = htmlspecialchars((string)$row['image1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $img = "<div class='glass-frame'><img src='{$imgUrl}' alt='Постер'></div><br>";
    }

    // Описание: форматируем укороченный текст, потом дополнительно режем до 400 с многобайтной безопасностью
    $rawDescr = (string)$row['descr'];
    $descFormatted = format_comment($rawDescr);

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($descFormatted, 'UTF-8') > 500) {
            $descFormatted = mb_substr($descFormatted, 0, 400, 'UTF-8') . '...';
        }
    } else {
        if (strlen($descFormatted) > 500) {
            $descFormatted = substr($descFormatted, 0, 400) . '...';
        }
    }

    // Вывод (оставил вашу разметку)
    echo "
<div class=\"c\">
<div class=\"c1\">
    <div class=\"c2\">
        <div class=\"c3\">
            <div class=\"c4\">
                <div class=\"c5\">
                    <div class=\"c6\">
                        <div class=\"c7\">
                            <div class=\"c8\">
                                <div class=\"ci\" align=\"left\">
                                    <div class=\"c_tit\">{$nameSafe}</div>
                                    <table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"3\">
                                        <tr valign=\"top\">
                                            <td width=\"100%\" class=\"text\">
                                                <div align=\"center\"><a href=\"details.php?id={$row['id']}\">{$img}</a></div>
                                                <div>{$descFormatted}</div>
                                            </td>
                                        </tr>
                                    </table><br>
                                    <!-- rating -->
                                    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr>
                                        <td>
                                            <div class=\"sbg\"><div class=\"ss1\"><div class=\"rat st\">
                                                {$row['added']}
                                                <div class=\"cl\"></div>
                                            </div></div></div>
                                        </td>
                                        <td><div class=\"ss2\"></div></td>
                                    </tr></table>
                                    <!-- /rating -->
                                    <div class=\"s\"><div class=\"s1\"><div class=\"s2\">
                                        <div class=\"st\">
                                            <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
                                                <tr>
                                                    <td>
                                                        <b>
                                                            <font color=\"green\"><img src=\"pic/up.gif\" title=\"Раздают\" /> {$row['seeders']}</font> |
                                                            <font color=\"red\"><img src=\"pic/ardown.gif\" title=\"Качают\" /> {$row['leechers']}</font> |
                                                            <font color=\"orange\">Скачавших: {$row['times_completed']}</font> |
                                                            <font color=\"#999\">Размер: {$size}</font> |
                                                            <font color=\"#999\">Free: {$freeLabel}</font>
                                                        </b>
                                                    </td>
                                                    <td align=\"right\" class=\"r\">
                                                        <a href=\"details.php?id={$row['id']}\"><font color=\"#ADFF2F\">Подробнее/Скачать</font></a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
";
}
end_frame();
//////////////////////////////////////////////////////////////



////////////////////////////////////////////////
// «Наши герои» — оптимизированный блок

$heroestitle = "Наши герои";
begin_frame($heroestitle);

// Один кэш-ключ на весь блок (меньше сетевых хитов к Memcached)
$CACHE_KEY = 'heroes:block:v3';
$TTL       = 300; // 5 минут
$data = $memcached->get($CACHE_KEY);

if ($data === false) {
    $data = [
        'bonus'    => [],
        'karma'    => [],
        'comments' => [],
    ];

    // BONUS TOP 10 (users.bonus — DECIMAL(10,2))
    $qBonus = "
        SELECT id, class, username, bonus
        FROM users
        WHERE bonus > 1
        ORDER BY bonus DESC
        LIMIT 10
    ";
    $rs = sql_query($qBonus) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']    = (int)$row['id'];
        $row['class'] = (int)$row['class'];
        // bonus оставляем как строку/десятичное, форматнём при выводе
        $data['bonus'][] = $row;
    }

    // KARMA TOP 10 (users.karma — INT)
    $qKarma = "
        SELECT id, class, username, karma
        FROM users
        WHERE karma >= 1
        ORDER BY karma DESC
        LIMIT 10
    ";
    $rs = sql_query($qKarma) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']    = (int)$row['id'];
        $row['class'] = (int)$row['class'];
        $row['karma'] = (int)$row['karma'];
        $data['karma'][] = $row;
    }

    // COMMENTS TOP 10:
    // сначала дешёвая агрегация по comments.user, затем JOIN к users
    // (требуется индекс: CREATE INDEX idx_comments_user ON comments(`user`);
    $qComments = "
        SELECT u.id, u.class, u.username, t.num_comm
        FROM (
            SELECT `user` AS uid, COUNT(*) AS num_comm
            FROM comments
            GROUP BY `user`
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ) AS t
        JOIN users u ON u.id = t.uid
        ORDER BY t.num_comm DESC
    ";
    $rs = sql_query($qComments) or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($rs)) {
        $row['id']       = (int)$row['id'];
        $row['class']    = (int)$row['class'];
        $row['num_comm'] = (int)$row['num_comm'];
        $data['comments'][] = $row;
    }

    $memcached->set($CACHE_KEY, $data, $TTL);
}

// безопасная обёртка имени
$h = static function (?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '
<table width="100%" border="0" cellspacing="0" cellpadding="2">
<tr><td align="center" class="embedded">
<table width="100%" class="main" border="1" cellspacing="0" cellpadding="10">
<tr>
';

/* ========================== Блок Бонусов ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Бонусы</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['bonus'] as $b) {
    $uid  = (int)$b['id'];
    $cls  = (int)$b['class'];
    $name = $h($b['username']);
    $bonusVal = is_numeric($b['bonus']) ? number_format((float)$b['bonus'], 2, '.', '') : $h((string)$b['bonus']);
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $bonusVal . '</td>
    </tr>';
}

echo '</table></td>';

/* ========================== Блок Кармы ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Карма</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['karma'] as $k) {
    $uid  = (int)$k['id'];
    $cls  = (int)$k['class'];
    $name = $h($k['username']);
    $karmaVal = (int)$k['karma'];
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $karmaVal . '</td>
    </tr>';
}

echo '</table></td>';

/* ========================== Блок Комментариев ========================== */
echo '
<td class="lol" width="33%" align="center" valign="top">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" colspan="3" align="center">Комментарии</td></tr>
<tr><td class="lol" align="left">Пользователь</td><td class="lol" align="center">Кол.</td></tr>
';

foreach ($data['comments'] as $c) {
    $uid  = (int)$c['id'];
    $cls  = (int)$c['class'];
    $name = $h($c['username']);
    $num  = (int)$c['num_comm'];
    echo '<tr>
        <td class="lol" width="100%"><a href="userdetails.php?id=' . $uid . '">' . get_user_class_color($cls, $name) . '</a></td>
        <td class="lol" align="center">' . $num . '</td>
    </tr>';
}

echo '</table></td></tr></table></td></tr></table>';

end_frame();

////////////////////////////////////////////////


//////////////////////////////////////////////////////
begin_frame("Статистика");

global $tracker_lang, $ss_uri, $maxusers, $CURUSER, $use_sessions, $mysqli;

// Подключение к Memcached (если ещё нет подключения)
if (!isset($memcached) || !($memcached instanceof Memcached)) {
    $memcached = new Memcached();
    $memcached->addServer("127.0.0.1", 11211); // По умолчанию
}

$cache_key = 'stats';
$stats = $memcached->get($cache_key);

if ($memcached->getResultCode() !== Memcached::RES_SUCCESS) {
    // Один SQL-запрос, собирающий все нужные агрегаты
    $query = "
        SELECT
            (SELECT COUNT(*) FROM users) AS registered,
            (SELECT COUNT(*) FROM torrents) AS torrents,
            (SELECT COUNT(*) FROM peers WHERE seeder = 'yes') AS seeders,
            (SELECT COUNT(*) FROM peers WHERE seeder = 'no') AS leechers,
            (SELECT COUNT(*) FROM users WHERE gender = '1') AS male,
            (SELECT COUNT(*) FROM users WHERE gender = '2') AS female,
            (SELECT SUM(size) FROM torrents) AS total_size,
            (SELECT SUM(downloaded) FROM users) AS totaldl,
            (SELECT SUM(uploaded) FROM users) AS totalul
    ";

    $res = mysqli_query($mysqli, $query) or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_assoc($res);

    // Форматируем значения
    $registered = number_format((int)$row["registered"]);
    $torrents = number_format((int)$row["torrents"]);
    $seeders = (int)$row["seeders"];
    $leechers = (int)$row["leechers"];
    $seeders_fmt = number_format($seeders);
    $leechers_fmt = number_format($leechers);
    $male = number_format((int)$row["male"]);
    $female = number_format((int)$row["female"]);
    $total_size = $row["total_size"] ?? 0;
    $total_downloaded = $row["totaldl"] ?? 0;
    $total_uploaded = $row["totalul"] ?? 0;
    $test = mksize($total_uploaded + $total_downloaded);

    $stats = [
        "registered" => $registered,
        "torrents" => $torrents,
        "seeders" => $seeders_fmt,
        "leechers" => $leechers_fmt,
        "male" => $male,
        "female" => $female,
        "total_size" => $total_size,
        "test" => $test
    ];

    // Кэшируем на 5 минут (300 секунд)
    $memcached->set($cache_key, $stats, 300);
} else {
    // Получаем из кэша
    $registered = $stats["registered"];
    $torrents = $stats["torrents"];
    $seeders_fmt = $stats["seeders"];
    $leechers_fmt = $stats["leechers"];
    $male = $stats["male"];
    $female = $stats["female"];
    $total_size = $stats["total_size"];
    $test = $stats["test"];

    // Преобразуем в int для подсчёта
    $seeders = (int)str_replace(",", "", $seeders_fmt);
    $leechers = (int)str_replace(",", "", $leechers_fmt);
}

// Суммарное количество пиров
$peers = $seeders + $leechers;

// Вывод таблицы
print("<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">
<td align=\"center\" style=\"border: none;\">
<table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">
<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"10\">
  <tr>
    <td width=\"50%\" align=\"center\" style=\"border: none;\">
<table class=\"main\" border=\"0\" width=\"100%\">
<tr><td class=\"lol\" align=left><b>Мест на трекере</b></td><td align=right class=\"lol\">$maxusers</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['users_registered']."</b></td><td class=\"lol\" align=right class=\"b\">$registered</td></tr>
<tr><td class=\"lol\" align=left><b>Парней</b></td><td align=right class=\"lol\">$male</td></tr>
<tr><td class=\"lol\" align=left><b>Девушек</b></td><td align=right class=\"lol\">$female</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_torrents']."</b></td><td class=\"lol\" align=right class=\"a\">$torrents</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_peers']."</b></td><td class=\"lol\" align=right class=\"a\">$peers</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_seeders']."</b></td><td class=\"lol\" align=right class=\"b\">$seeders</td></tr>
<tr><td class=\"lol\" align=left><b>".$tracker_lang['tracker_leechers']."</b></td><td class=\"lol\" align=right class=\"a\">$leechers</td></tr>
<tr><td class=\"lol\" align=left><b>Всего траффика</b></td><td class=\"lol\" align=right class=\"a\">$test</td></tr>
<tr><td class=\"lol\" align=left><b>Общий размер раздач</b></td><td class=\"lol\" align=right class=\"a\">".mksize($total_size)."</td></tr>
");

print("</table></td>");
print("</table></td></tr></table>");

end_frame();


////////////////////////////ФОРУМ//////////////////////////////////

/**
 * Блок «Форум: последние/активные/важные темы»
 * PHP 8.1+, Memcached-кэш
 *
 * Требует:
 *  - глобальные функции/переменные TBDev: begin_frame(), end_frame(), sql_query(), sqlerr(), sqlesc(),
 *    format_comment(), get_user_class_color(), $CURUSER, $memcached.
 */

global $CURUSER, $memcached;

$blocktitle = ".:: <a title=\"На главную форума\" class=\"altlink_white\" href='forums.php'>Главная</a> :: <a title=\"Поиск выражений на форуме\" class=\"altlink_white\" href='forums.php?action=search'>Поиск на форуме</a> :: <a title=\"Перейти к непрочитанным сообщениям\" class=\"altlink_white\" href='forums.php?action=viewunread'>К непрочитанным сообщениям</a> :: <a title=\"Пометить все сообщения как прочитанные\" class=\"altlink_white\" href='forums.php?action=catchup'>Пометить прочитанным</a> ::.";

// Определяем класс пользователя (fallback = 1)
$curuserclass = isset($CURUSER['class']) && (int)$CURUSER['class'] > 0 ? (int)$CURUSER['class'] : 1;

// Ключ и TTL кэша (1 час)
$CACHE_KEY = "block:forum:tabs:class:{$curuserclass}:v3";
$CACHE_TTL = 3600;

// Пытаемся взять из кэша
$content = $memcached->get($CACHE_KEY);
if (!is_string($content) || $content === '') {

    // Собираем контент с нуля
    ob_start();
    ?>
    <style type="text/css">
    #tabs_f{ text-align:left; padding-top:7px }
    #tabs_f .tab_f{
        border:1px solid #cecece; padding:5px 10px; margin-right:5px;
        line-height:23px; cursor:pointer;
    }
    #tabs_f span{ position:relative; border-bottom:1px solid #FAFAFA !important; top:-1px;
        border-top-left-radius:4px; border-top-right-radius:4px;
    }
    #tabs_f span:hover{ background:#FAFAFA; }
    #tabs_f .active{ color:#C60000; font-weight:bold; }
    #tabs_f #body_f{ border:1px solid #cecece; padding:5px; margin-bottom:10px; background:#FAFAFA }
    table.tt{ width:100% } table.tt td{ padding:5px } table.tt td.tt{ background-color:#777; padding:7px }
    </style>

    <script type="text/javascript">
    (function ($) {
        "use strict";
        var loading = '<img src="pic/loading.gif" alt="Загрузка.." title="Загрузка.."/>';

        $(function () {
            $(".tab_f").on("click", function(){
                var $self = $(this);
                if ($self.hasClass("active")) return;

                $("#loading").html(loading);
                var act = $self.attr("id");
                $self.addClass("active").siblings("span").removeClass("active");

                $.post("block-forums_jquery.php", { act: act })
                    .done(function (response) {
                        $("#body_f").empty().append(response);
                    })
                    .always(function () {
                        $("#loading").empty();
                    });
            });

            // простая «зебра» для строк таблиц (без :even из CSS ради совместимости)
            $("#body_f .zebra").filter(":even").css({ backgroundColor: "#EEEEEE" });
        });
    })(jQuery);
    </script>

    <div id="tabs_f">
        <span class="tab_f active" id="0">Последние комментарии</span>
        <span title="Важные темы на форуме" class="tab_f" id="5">Важные</span>
        <span title="Самые активные темы на форуме (по количеству сообщений)" class="tab_f" id="1">Активные</span>
        <span title="Скрытые темы (скрываемые на главной страничке форума и в блоке последних комментариев)" class="tab_f" id="2">Скрытые</span>
        <span title="Самые просматриваемые темы на форуме по количеству просмотров" class="tab_f" id="3">Просматриваемые</span>
        <?php if (!empty($CURUSER)): ?>
            <span title="Последние, вами созданные, темы" class="tab_f" id="4">Мои</span>
        <?php endif; ?>
        <span id="loading"></span>

        <div id="body_f">
            <?php
            // ====== Таблица по умолчанию: последние обновлённые темы ======
            echo "<table class=\"tt\" cellpadding=\"0\" cellspacing=\"0\">
            <tr>
                <td class=\"colhead\" align=\"left\" width=\"70%\">&nbsp;Тема сообщения&nbsp;</td>
                <td class=\"colhead\" align=\"left\" width=\"30%\">&nbsp;Категория&nbsp;</td>
                <td class=\"colhead\" align=\"center\">&nbsp;Ответ / Просмотр</td>
                <td class=\"colhead\" align=\"center\">&nbsp;Автор&nbsp;</td>
                <td class=\"colhead\" align=\"right\">&nbsp;Последний&nbsp;</td>
            </tr>";

            // Основной запрос по темам
            $q = "
                SELECT
                    ft.*,
                    ff.name AS forumname,
                    ff.description,
                    ff.minclassread,
                    (SELECT COUNT(*) FROM posts WHERE topicid = ft.id) AS post_num
                FROM topics AS ft
                INNER JOIN forums AS ff ON ff.id = ft.forumid
                WHERE ft.visible = 'yes'
                  AND ff.visible = 'yes'
                  AND ff.minclassread <= " . sqlesc($curuserclass) . "
                ORDER BY ft.lastpost DESC
                LIMIT 10
            ";
            $res = sql_query($q) or sqlerr(__FILE__, __LINE__);

            while ($topic = mysqli_fetch_assoc($res)) {
                $polls_view = ($topic["polls"] === "yes" ? " <img width='13' title=\"Данная тема имеет опрос\" src=\"pic/forumicons/polls.gif\" alt=\"poll\">" : "");

                $posts = (int)$topic["post_num"];
                $postsperpage = 20;
                $tpages = (int)ceil(max(1, $posts) / $postsperpage);

                $topicpages = "";
                if ($tpages > 1) {
                    $pages = [];
                    for ($i = 1; $i <= $tpages; $i++) {
                        $pages[] = "<a href=\"forums.php?action=viewtopic&amp;topicid={$topic['id']}&amp;page={$i}\">{$i}</a>";
                    }
                    $topicpages = " [" . implode(" ", $pages) . "]";
                }

                $forumname = "<a title=\"" . htmlspecialchars($topic["description"] ?? "", ENT_QUOTES | ENT_SUBSTITUTE) .
                    "\" href=\"/forums.php?action=viewforum&amp;forumid={$topic['forumid']}\">" .
                    htmlspecialchars($topic["forumname"] ?? "", ENT_QUOTES | ENT_SUBSTITUTE) . "</a>";

                $topicid   = (int)$topic["id"];
                $topic_uid = (int)$topic["userid"];
                $views     = (int)$topic["views"];
                $sticky    = $topic["sticky"] ?? 'no';
                $lastpost  = (int)($topic["lastpost"] ?? 0);

                $postRes = sql_query("
                    SELECT p.*, la.class AS la_class, la.username AS la_username,
                           ow.class AS owner_class, ow.username AS owner_username
                    FROM posts AS p
                    LEFT JOIN users AS la ON la.id = p.userid
                    LEFT JOIN users AS ow ON ow.id = {$topic_uid}
                    WHERE p.topicid = {$topicid}
                    ORDER BY p.id DESC
                    LIMIT 1
                ") or sqlerr(__FILE__, __LINE__);

                $last = mysqli_fetch_assoc($postRes) ?: [];
                $postid = (int)($last["id"] ?? 0);
                $userid = (int)($last["userid"] ?? 0);
                $added  = $last["added"] ?? "";

                if (!empty($last["la_username"])) {
                    $username = "<a href='userdetails.php?id={$userid}'>" .
                        get_user_class_color((int)($last["la_class"] ?? 0), $last["la_username"]) .
                        "</a>";
                } else {
                    $username = "id: {$userid}";
                }

                if (!empty($last["owner_username"])) {
                    $author = "<a href='userdetails.php?id={$topic_uid}'>" .
                        get_user_class_color((int)($last["owner_class"] ?? 0), $last["owner_username"]) .
                        "</a>";
                } else {
                    $author = "id: {$topic_uid}";
                }

                $subject = "<a title=\"" . htmlspecialchars($added, ENT_QUOTES | ENT_SUBSTITUTE) .
                    "\" href=\"forums.php?action=viewtopic&topicid={$topicid}&page=last#{$lastpost}\">" .
                    format_comment($topic["subject"]) . "</a>";

                $replies = max(0, $posts - 1);

                echo "<tr class=\"zebra\">
                        <td class=\"b\" align=\"left\">" . ($sticky === "yes" ? "<b>Важная</b>: " : "") . $subject . $topicpages . $polls_view . "</td>
                        <td class=\"b\" align=\"left\">{$forumname}</td>
                        <td class=\"b\" align=\"center\"><small>{$replies} / {$views}</small></td>
                        <td class=\"b\" align=\"center\">{$author}</td>
                        <td class=\"b\" align=\"right\">{$username} <small>" . htmlspecialchars($added, ENT_QUOTES | ENT_SUBSTITUTE) . "</small></td>
                      </tr>";
            }

            // Блок «Обновлённые темы в скрытых разделах за 7 дней»
            echo "<tr><td align=\"center\" colspan=\"5\" class=\"b\"><small>Обновлённых тем в скрытых разделах: ";

            $hiddenQ = "
                SELECT tp.id AS topid, tp.subject, tp.lastpost, tp.visible, tp.forumid, ft.minclassread
                FROM topics AS tp
                LEFT JOIN forums AS ft ON ft.id = tp.forumid
                WHERE ft.minclassread <= " . sqlesc($curuserclass) . "
                  AND (tp.visible = 'no' OR ft.visible = 'no')
                  AND tp.lastdate > " . sqlesc(get_date_time(gmtime() - 86400 * 7)) . "
                ORDER BY tp.lastdate DESC
                LIMIT 5
            ";
            $hiddenRes = sql_query($hiddenQ) or sqlerr(__FILE__, __LINE__);

            $first = true;
            $count = 0;
            while ($f = mysqli_fetch_assoc($hiddenRes)) {
                if (!$first) {
                    echo ", ";
                }
                $first = false;
                $subj = format_comment($f["subject"] ?? "");
                $topid = (int)$f["topid"];
                $lp = (int)($f["lastpost"] ?? 0);
                echo "<a href=\"forums.php?action=viewtopic&topicid={$topid}&page=last#{$lp}\"><b>{$subj}</b></a>";
                $count++;
            }

            if ($count === 0) {
                echo "нет.";
            }

            echo "</small></td></tr></table>";
            ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();

    // Пишем в кэш
    $memcached->set($CACHE_KEY, $content, $CACHE_TTL);
}

// Вывод блока
begin_frame($blocktitle);
echo $content;
end_frame();










////////////////////////////ФОРУМ//////////////////////////////////




stdfoot();







?>