<?

if(!defined('IN_TRACKER') || !defined('IN_FORUM'))
  die('Hacking attempt! functions_forum.');

// ================= MEMCACHED HELPERS =================
// Возвращает значение из Memcached. Если ключа нет — возвращает false.
// Если значение реально было сохранено как false — вернётся именно false,
// но в наших кейсах мы не кладём туда false, так что это безопасно.
function mc_get(string $key) {
    global $memcached;
    if (!($memcached instanceof Memcached)) return false;
    $v = $memcached->get($key);
    $code = $memcached->getResultCode();
    if ($code === Memcached::RES_NOTFOUND) return false;
    return $v; // RES_SUCCESS/RES_BUFFERED => отдаём как есть (в т.ч. пустой массив/0)
}
function mc_set(string $key, $val, int $ttl = 300): bool {
    global $memcached;
    if (!($memcached instanceof Memcached)) return false;
    return $memcached->set($key, $val, $ttl);
}
function mc_del(string $key): void {
    global $memcached;
    if (!($memcached instanceof Memcached)) return;
    $memcached->delete($key);
}





// Инвалидация общего списка форумов для селекта
function invalidate_forum_jump_cache(): void {
    mc_del('forum:list:jump:v1');
}

// Инвалидация кэшей конкретного форума (списки тем, счётчики)
function invalidate_forum_lists(int $forumid): void {
    $forumid = (int)$forumid;
    mc_del("forums:{$forumid}:topic_count:v1");
    // Несколько типовых страниц:
    for ($p = 0; $p <= 5; $p++) {
        mc_del("forums:{$forumid}:topics:list:v1:{$p}:25"); // под твой perpage=25
        mc_del("forums:{$forumid}:topics:list:v1:{$p}:50"); // вдруг меняется
    }
    // если используешь другие ключи — добавь сюда
}

// Инвалидация кэша топика (после нового поста)
function invalidate_topic_caches(int $topicid): void {
    mc_del("topic:meta:{$topicid}");
    mc_del("topic:view:{$topicid}:page1");
    mc_del("topic:view:{$topicid}:last");
}



// ---------- КЭШ-УТИЛИТЫ (noop-safe) ----------
if (!function_exists('unsql_cache')) {
    /**
     * Совместимая заглушка старой функции инвалидирования SQL-кэша.
     * Просто удаляем ключ из Memcached, если он доступен.
     */
    function unsql_cache(string $key): void {
        if (isset($GLOBALS['memcached']) && $GLOBALS['memcached'] instanceof Memcached) {
            $GLOBALS['memcached']->delete($key);
        }
        // если Memcached не инициализирован — тихо выходим (noop)
    }
}

if (!function_exists('mc_del')) {
    /**
     * Единообразное удаление MEMCACHED-ключа.
     */
    function mc_del(string $key): void {
        if (isset($GLOBALS['memcached']) && $GLOBALS['memcached'] instanceof Memcached) {
            $GLOBALS['memcached']->delete($key);
        }
    }
}



// ====== КЭШ-БЛОКИ ФОРУМА (инвалидация HTML-блоков + точечных ключей) ======
function unlinks(): void {
    for ($x = 0; $x < 7; $x++) {
        unsql_cache("block-forum_{$x}");
        unsql_cache("block-forum_light_{$x}");
        unsql_cache("block-forum_lighthalf_{$x}");
    }
    unsql_cache("forums.main");

    // Дополнительно: сбросим частые мемкеш-ключи списков
    mc_del('forum:list:recent');
    mc_del('forum:list:hot');
}

// ====== ПОМЕТИТЬ «ПРОЧИТАНО» СВЕЖИЕ ТОПИКИ ======
function catch_up(): void {
    global $CURUSER, $Forum_Config;
    if (empty($CURUSER['id'])) return;
    $userid = (int)$CURUSER['id'];

    $dt = get_date_time(gmtime() - (int)$Forum_Config['readpost_expiry']);

    $res = sql_query(
        "SELECT t.id, t.lastpost
         FROM topics AS t
         LEFT JOIN posts AS p ON p.id = t.lastpost
         WHERE p.added > " . sqlesc($dt)
    ) or sqlerr(__FILE__, __LINE__);

    while ($arr = mysqli_fetch_assoc($res)) {
        $topicid = (int)$arr['id'];
        $postid  = (int)$arr['lastpost'];

        $r = sql_query(
            "SELECT id, lastpostread
             FROM readposts
             WHERE userid = " . sqlesc($userid) . " AND topicid = " . sqlesc($topicid) . " LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        if (mysqli_num_rows($r) === 0) {
            sql_query(
                "INSERT INTO readposts (userid, topicid, lastpostread)
                 VALUES (" . sqlesc($userid) . ", " . sqlesc($topicid) . ", " . sqlesc($postid) . ")"
            ) or sqlerr(__FILE__, __LINE__);
        } else {
            $a = mysqli_fetch_assoc($r);
            if ((int)$a['lastpostread'] < $postid) {
                sql_query(
                    "UPDATE readposts
                     SET lastpostread = " . sqlesc($postid) . "
                     WHERE id = " . sqlesc((int)$a['id']) . " LIMIT 1"
                ) or sqlerr(__FILE__, __LINE__);
            }
        }

        // Инвалидация потенциальных счетчиков «непрочитанного» для пользователя
        mc_del("user:{$userid}:unread:count");
        mc_del("user:{$userid}:unread:topics");
    }
}

// ====== УРОВНИ ДОСТУПА К ФОРУМУ (кеш) ======
function get_forum_access_levels($forumid) {
    $forumid = (int)$forumid;
    $ckey = "forum:access:{$forumid}";

    if (($cached = mc_get($ckey)) !== false) {
        return $cached;
    }

    $res = sql_query(
        "SELECT minclassread, minclasswrite, minclasscreate
         FROM forums
         WHERE id = " . sqlesc($forumid) . " LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) !== 1) {
        return false;
    }

    $arr = mysqli_fetch_assoc($res);
    $data = [
        'read'   => (int)$arr['minclassread'],
        'write'  => (int)$arr['minclasswrite'],
        'create' => (int)$arr['minclasscreate'],
    ];
    mc_set($ckey, $data, 600);
    return $data;
}

// ====== ID ФОРУМА ПО ID ТОПИКА (кеш) ======
function get_topic_forum($topicid) {
    $topicid = (int)$topicid;
    $ckey = "topic:forumid:{$topicid}";

    if (($cached = mc_get($ckey)) !== false) {
        return (int)$cached;
    }

    $res = sql_query(
        "SELECT forumid
         FROM topics
         WHERE id = " . sqlesc($topicid) . " LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) !== 1) {
        return false;
    }

    [$forumid] = mysqli_fetch_row($res);
    $forumid = (int)$forumid;
    mc_set($ckey, $forumid, 900);
    return $forumid;
}

// ====== ОБНОВИТЬ ПОСЛЕДНЕЕ СООБЩЕНИЕ В ТОПИКЕ (кеш + инвалидация) ======
function update_topic_last_post($topicid, $added, $postold = false): void {
    $topicid = (int)$topicid;

    $res = sql_query(
        "SELECT id, added
         FROM posts
         WHERE topicid = " . sqlesc($topicid) . "
         ORDER BY id DESC
         LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);

    $arr    = mysqli_fetch_assoc($res) ?: null;
    $postid = $arr ? (int)$arr['id'] : 0;

    if (empty($added) && $arr) {
        $added = $arr['added'];
    }

    if ($postid > 0) {
        sql_query(
            "UPDATE topics
             SET lastpost = " . sqlesc($postid) . ",
                 lastdate = " . sqlesc($added) . "
             WHERE id = " . sqlesc($topicid) . "
             LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        // === КЕШ: сохраним мету топика и инвалидируем связанный список ===
        mc_set("topic:meta:{$topicid}", ['lastpost' => $postid, 'lastdate' => $added], 600);
        mc_del("topic:view:{$topicid}:page1");   // кэш первой страницы топика (если используешь)
        mc_del("topic:view:{$topicid}:last");    // кэш «последняя страница»

        // Вычислим форум и сбросим его списки
        $forumid = get_topic_forum($topicid);
        if ($forumid) {
            mc_del("forum:topics:list:{$forumid}:page1");
            mc_del("forum:topics:list:{$forumid}:latest");
            mc_del("forum:stats:{$forumid}");
        }

        // Если удалили «последний пост» и выбрали новый — синхронизируем readposts
        if (!empty($postold)) {
            sql_query(
                "UPDATE readposts
                 SET lastpostread = " . sqlesc($postid) . "
                 WHERE topicid = " . sqlesc($topicid)
            ) or sqlerr(__FILE__, __LINE__);

            // Инвалидация пользовательских счетчиков «непрочитанное»
            // (глобально — дешевле, чем точечно по всем юзерам)
            mc_del("unread:global:hint");
        }
    }
}

 // ====== ПОСЛЕДНИЙ postid ВО ФОРУМЕ (с кешем) ======
function get_forum_last_post($forumid)  {
    $forumid = (int)$forumid;

    // Ключ Memcached: последний postid по форуму
    $ckey = "forum:lastpost:{$forumid}";
    if (($cached = mc_get($ckey)) !== false) {
        return (int)$cached;
    }

    // Быстрее и короче, чем ORDER BY ... LIMIT 1
    $res = sql_query(
        "SELECT MAX(lastpost) AS lastpost
         FROM topics
         WHERE forumid = " . sqlesc($forumid)
    ) or sqlerr(__FILE__, __LINE__);

    $row = mysqli_fetch_assoc($res) ?: ['lastpost' => 0];
    $postid = (int)$row['lastpost'];

    // Кладём в кеш на 2 минуты (можно увеличить/уменьшить)
    mc_set($ckey, $postid, 120);

    return $postid;
}


// ====== БЫСТРЫЙ ПЕРЕХОД ПО ФОРУМАМ + «кто онлайн» (Memcached) ======
function insert_quick_jump_menu($currentforum = false, $users = false)  {
    global $CURUSER, $DEFAULTBASEURL, $memcached;

    // JS-обновлялка «кто онлайн в форуме»
    ?>
    <script>
    (function () {
      function forum_online() {
        jQuery.post("forums.php", {}, function (response) {
          jQuery("#forum_online").html(response);
        }, "html");
      }
      forum_online();
      setInterval(forum_online, 60000);
    })();
    </script>
    <?php

    echo "
    <div class=\"tcat_t\">
      <div class=\"tcat_r\">
        <div class=\"tcat_l\">
          <div class=\"tcat_tl\">
            <div class=\"tcat_simple\">
              <table cellspacing=\"0\" cellpadding=\"0\">
                <tr><td class=\"tcat_name\">Кто просматривает форум</td></tr>
              </table>
              <br class=\"tcat_clear\" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class=\"post_body\">
      <div class=\"f_dark\">
        <span id=\"forum_online\" style=\"display:block;text-align:center\">Загрузка кто смотрит форум</span>
      </div>
    </div>
    <div class=\"on\">
      <div class=\"tcat_b\"><div class=\"tcat_bl\"><div class=\"tcat_br\"></div></div></div>
    </div><br />";

    // ——— Быстрый переход ———
    $le  = "<form method=\"get\" action=\"forums.php\" name=\"jump\">\n";
    $le .= "  <input type=\"hidden\" name=\"action\" value=\"viewforum\" />\n";
    $le .= "  Быстрый переход: ";
    $le .= "  <select name=\"forumid\" onchange=\"if(this.value!=-1){ this.form.submit(); }\">\n";

    // ключ и чтение из Memcached
    $ckey    = "forum:list:jump:v1";
    $forums  = ($memcached instanceof Memcached) ? $memcached->get($ckey) : false;
    $hasMC   = ($memcached instanceof Memcached) && ($memcached->getResultCode() === Memcached::RES_SUCCESS);

    // ключ и чтение из Memcached
$ckey   = "forum:list:jump:v1";
$forums = mc_get($ckey);

// Если в кэше нет массива или он пустой — грузим из БД
if (!is_array($forums) || count($forums) === 0) {
    $res = sql_query("
        SELECT id, name, minclassread, minclasswrite
        FROM forums
        WHERE 1
        ORDER BY name
    ") or sqlerr(__FILE__, __LINE__);

    $forums = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $forums[] = [
            'id'            => (int)$row['id'],
            'name'          => (string)$row['name'],
            'minclassread'  => (int)$row['minclassread'],
            'minclasswrite' => (int)$row['minclasswrite'],
        ];
    }
    // кладём в кеш только если действительно что-то есть (чтобы не запинать пустоту)
    if (!empty($forums)) {
        mc_set($ckey, $forums, 600);
    }
}


    // Рендер опций по правам
    $userClass = function_exists('get_user_class') ? (int)get_user_class() : 0;
    $isGuest   = empty($CURUSER);

    foreach ($forums as $f) {
        if ($f['minclassread'] <= $userClass || ($f['minclassread'] === 0 && $isGuest)) {
            $selected = ((string)$currentforum === (string)$f['id']) ? " selected" : "";
            $name = htmlspecialchars($f['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $le .= "    <option value=\"{$f['id']}\"{$selected}>{$name}</option>\n";
        }
    }

    $le .= "  </select>\n";
    $le .= "  <input type=\"submit\" value=\"Вперед!\" />\n";
    $le .= "</form>\n";

    echo "<div class=\"smallfont\" style=\"white-space:nowrap\">{$le}</div><br />\n";
}


function insert_compose_frame(int $id, bool $newtopic = true, bool $quote = false): void
{
    global $CURUSER, $DEFAULTBASEURL, $memcached;

    // безопасная длина темы (без notice на неинициализированной глобали)
    $maxsubjectlength = (isset($GLOBALS['maxsubjectlength']) && (int)$GLOBALS['maxsubjectlength'] > 0)
        ? (int)$GLOBALS['maxsubjectlength']
        : 255;

    // ===== Заголовок/шапка: имя форума (для НОВОЙ темы) либо subject (для ответа) =====
    if ($newtopic) {
        $forumid = (int)$id;
        $ckey = "forum:name:{$forumid}";
        $forumname = mc_get($ckey);
        if ($forumname === false || $forumname === null || $forumname === '') {
            $res = sql_query("SELECT name FROM forums WHERE id = " . sqlesc($forumid) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $row = mysqli_fetch_assoc($res);
            $forumname = $row ? (string)$row['name'] : '';
            if ($forumname !== '') mc_set($ckey, $forumname, 600);
        }
        $forumname_safe = htmlspecialchars($forumname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($forumname_safe !== '') {
            echo "<p align='center'>Новое сообщение для форума <a href='?action=viewforum&amp;forumid={$forumid}'>{$forumname_safe}</a></p>\n";
        }
    } else {
        $topicid = (int)$id;
        $ckey = "topic:subject:{$topicid}";
        $subject = mc_get($ckey);
        if ($subject === false || $subject === null || $subject === '') {
            $res = sql_query("SELECT subject FROM topics WHERE id = " . sqlesc($topicid) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $row = mysqli_fetch_assoc($res);
            if (!$row) stderr("Ошибка форума", "Такой темы не найдено.");
            $subject = (string)$row['subject'];
            if ($subject !== '') mc_set($ckey, $subject, 600);
        }
        // если нужно — можно показать заголовок темы пользователю
        // echo "<p align='center'>Тема: <a href='?action=viewtopic&amp;topicid={$topicid}'>"
        //      . htmlspecialchars($subject, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') . "</a></p>";
    }

    // ===== Форма =====
    begin_frame("Сообщение", true);
    echo "<form name='comment' method='post' action='{$DEFAULTBASEURL}/forums.php?action=post'>\n";
    if ($newtopic) {
        echo "<input type='hidden' name='forumid' value='".(int)$id."'>\n";
    } else {
        echo "<input type='hidden' name='topicid' value='".(int)$id."'>\n";
    }

    begin_table();

    if ($newtopic) {
        echo "<tr><td class='rowhead'><b>Тема сообщения</b>:</td>
              <td align='left' style='padding:0'>
                <input type='text' size='100' maxlength='{$maxsubjectlength}' name='subject' style='border:0;height:19px'>
              </td></tr>\n";
    }

    // ===== Подготовка цитаты (если запрошена) =====
    $prefill = '';
    if ($quote) {
        $postid = isset($_GET['postid']) ? (int)$_GET['postid'] : 0;
        if ($postid <= 0) {
            header("Location: {$DEFAULTBASEURL}/forums.php");
            exit;
        }
        $res = sql_query(
            "SELECT p.body, u.username
               FROM posts p
               LEFT JOIN users u ON u.id = p.userid
              WHERE p.id = " . sqlesc($postid) . " LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        if (mysqli_num_rows($res) === 0) stderr("Ошибка", "Нет сообщения с таким id.");

        $q = mysqli_fetch_assoc($res);
        $qUser = htmlspecialchars((string)($q['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // В префилл кладём слегка экранированный текст (форматирование сделает парсер)
        $qBody = htmlspecialchars((string)$q['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $prefill = $qUser !== '' ? "[quote={$qUser}]{$qBody}[/quote]" : "[quote]{$qBody}[/quote]";
    }

    // ===== Редактор текста (ОБЯЗАТЕЛЬНО один, без вложенных форм) =====
    $editor = textbbcode("comment", "body", $prefill, 1);

    if ($newtopic) {
        echo "<tr><td colspan='2' align='center'><div>{$editor}</div></td></tr>";
    } else {
        echo "<tr><td class='colhead' align='center' colspan='2'>
                <a name='comments'></a><b>.::: Добавить сообщение к теме :::.</b>
              </td></tr>
              <tr><td colspan='2' width='100%' align='center'><div>{$editor}</div></td></tr>";
    }

    end_table();

    echo "<div style='text-align:center;margin-top:6px'>
            <input type='submit' name='post' title='CTRL+ENTER — разместить сообщение' class='btn' value='Разместить сообщение'>
          </div>";
    echo "</form>\n";
    end_frame();

    // ===== Последние 10 комментариев (для существующей темы) =====
    if (!$newtopic) {
        $topicid = (int)$id;
        // Берём флаг locked из topics, иначе $post['locked'] всегда пуст
        $postres = sql_query(
            "SELECT t.*,
                    tp.locked,
                    u.username, u.class, u.last_access, u.ip,
                    u.signature, u.avatar, u.title, u.enabled, u.warned, u.hiderating,
                    u.uploaded, u.downloaded, u.donor
               FROM posts AS t
          LEFT JOIN topics AS tp ON tp.id = t.topicid
          LEFT JOIN users  AS u  ON u.id = t.userid
              WHERE t.topicid = " . sqlesc($topicid) . "
           ORDER BY t.id DESC
              LIMIT 10"
        ) or sqlerr(__FILE__, __LINE__);

        begin_frame("<hr>Последние 10 комментариев, от последнего к первому.");

        while ($post = mysqli_fetch_assoc($postres)) {
            $postid      = (int)$post['id'];
            $posterid    = (int)$post['userid'];
            $added       = (string)$post['added'];
            $postername  = (string)($post['username'] ?? '');
            $posterclass = (int)($post['class'] ?? 0);
            $locked      = (string)($post['locked'] ?? 'no') === 'yes';

            // Автор
            if ($postername === '' && $posterid !== 0) {
                $by = "<b>id</b>: {$posterid}";
            } elseif ($posterid === 0 && $postername === '') {
                $by = "<i>Сообщение от</i> <span style='color:gray'>[<b>System</b>]</span>";
            } else {
                $title = (string)($post['title'] ?? '');
                if ($title === '') $title = get_user_class_name($posterclass);
                $uname = htmlspecialchars($postername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $utitle= htmlspecialchars($title,     ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $by = "<a href='{$DEFAULTBASEURL}/userdetails.php?id={$posterid}'><b>" .
                      get_user_class_color($posterclass, $uname) . "</b></a>" .
                      ($post['donor']   === "yes" ? " <img src=\"{$DEFAULTBASEURL}/pic/star.gif\" alt='Донор'>" : "") .
                      ($post['enabled'] === "no"  ? " <img src=\"{$DEFAULTBASEURL}/pic/disabled.gif\" alt='Этот аккаунт отключен' style='margin-left:2px'>" 
                         : ($post['warned'] === "yes" ? " <img src=\"{$DEFAULTBASEURL}/pic/warned.gif\" alt='Предупрежден'>" : "")) .
                      ($utitle !== '' ? " / " . get_user_class_color($posterclass, $utitle) : "");
            }

            // Онлайн + ratio (аккуратно с отсутствующими полями)
            $online = $online_text = '';
            $print_ratio = '';
            if ($posterid !== 0 && $postername !== '') {
                $last = strtotime($post['last_access'] ?? '1970-01-01 00:00:00');
                if ($last > (gmtime() - 600)) { $online = "online";  $online_text = "В сети"; }
                else                          { $online = "offline"; $online_text = "Не в сети"; }

                $uploaded   = (int)($post['uploaded'] ?? 0);
                $downloaded = (int)($post['downloaded'] ?? 0);
                if ($downloaded > 0)      $ratio = number_format($uploaded / $downloaded, 2);
                elseif ($uploaded > 0)    $ratio = "Infinity";
                else                       $ratio = "---";

                if (($post['hiderating'] ?? '') === "yes") {
                    $print_ratio = "<b>+100%</b>";
                } else {
                    $print_ratio =
                        "<img src=\"{$DEFAULTBASEURL}/pic/upl.gif\" alt=\"Залито\" width=\"12\" height=\"12\"> " . mksize($uploaded) .
                        " : <img src=\"{$DEFAULTBASEURL}/pic/down.gif\" alt=\"Скачано\" width=\"12\" height=\"12\"> " . mksize($downloaded) .
                        " : {$ratio}";
                }
            }

            // Аватар
            $avatar = '';
            if (!empty($post['avatar'])) {
                $avaSafe = htmlspecialchars($post['avatar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if (!empty($CURUSER['id']) && (int)$CURUSER['id'] === $posterid) {
                    $avatar = "<a href=\"{$DEFAULTBASEURL}/my.php\" title=\"Настройки профиля\">
                                 <img alt=\"Аватар\" width='100' height='100' src=\"{$DEFAULTBASEURL}/pic/avatar/{$avaSafe}\">
                               </a>";
                } else {
                    $avatar = "<img width='100' height='100' src=\"{$DEFAULTBASEURL}/pic/avatar/{$avaSafe}\" alt='avatar'>";
                }
            }

            // Шапка поста
            echo "<p class='sub'>#{$postid}" .
                 (get_user_class() > UC_UPLOADER
                    ? " <img src=\"{$DEFAULTBASEURL}/pic/button_{$online}.gif\" alt=\"{$online_text}\" title=\"{$online_text}\" style='position:relative;top:2px' height='14'>"
                    : ""
                 ) .
                 " {$by}" .
                 ($CURUSER['cansendpm'] ?? '' ? " :: <a href='{$DEFAULTBASEURL}/message.php?action=sendmessage&amp;receiver={$posterid}'><img src='{$DEFAULTBASEURL}/pic/button_pm.gif' alt='Отправить сообщение' border='0'></a>" : "") .
                 "</p>";

            begin_table(true);

            // Тело поста + подпись
            $bodyHtml = format_comment((string)$post['body']);
            $hasSignatureFlag = (string)($post['signature_on'] ?? '') === 'yes'; // корректное поле
            $signatureRaw  = (string)($post['signature'] ?? '');
            $signatureHtml = ($hasSignatureFlag && $signatureRaw !== '') ? "<p><hr>" . format_comment($signatureRaw) . "</p>" : "";

            echo "<tr valign='top'>
                    <td width='100' align='left' style='padding:0'>{$avatar}<br><div></div></td>
                    <td class='comment'>{$bodyHtml}{$signatureHtml}</td>
                  </tr>
                  <tr><td colspan='2' class='a' align='center'>".htmlspecialchars($added, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</td></tr>
                  <tr><td colspan='2' align='right'>";

            if ((((int)($CURUSER['id'] ?? 0) === $posterid) && !$locked) || get_user_class() >= UC_MODERATOR) {
                echo "[<a href='{$DEFAULTBASEURL}/forums.php?action=editpost&amp;postid={$postid}'><b>Редактировать</b></a>]";
            }
            if (get_user_class() >= UC_MODERATOR) {
                echo " - [<a href='{$DEFAULTBASEURL}/forums.php?action=deletepost&amp;postid={$postid}'><b>Удалить</b></a>]";
            }
            echo "</td></tr>";

            end_table();
        }

        end_frame();
    }

    // Хвост: быстрый переход
    insert_quick_jump_menu(false);
}
 
// ===================== CLEANUP ======================
function get_cleanup(bool $force = false): void {
    // Удаление просроченных отметок прочитанного и «битых» постов.
    // Запускается в 00:00 / 06:00 / 12:00 или принудительно через $force = true.
    if (!$force && !in_array(date('H'), ['00','06','12'], true)) {
        return;
    }

    $now = time();

    $res = sql_query("SELECT value_u FROM avps WHERE arg = 'fread_posts' LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_assoc($res) ?: ['value_u' => 0];
    $row_time = (int)$row['value_u'];

    // Следующий запуск — через 3 дня
    $next_ts = $now + 86400 * 3;

    if ($row_time >= $now) {
        return; // ещё рано
    }

    global $Forum_Config;

    $dt = get_date_time(gmtime() - (int)$Forum_Config['readpost_expiry']);

    // 1) Чистим readposts, где отмеченное последнее сообщение уже слишком старое
    $q1 = "DELETE rp
           FROM readposts rp
           LEFT JOIN posts p ON p.id = rp.lastpostread
           WHERE p.added < " . sqlesc($dt);
    sql_query($q1) or sqlerr(__FILE__, __LINE__);
    $deleted_readposts = mysqli_affected_rows($GLOBALS['__mysql_link'] ?? null);

    // 2) Чистим «висячие» посты: topicid указывает на несуществующую тему
    // (исправлено: прежний подзапрос ссылался на posts.* и был некорректным)
    $q2 = "DELETE t
           FROM posts t
           LEFT JOIN topics x ON x.id = t.topicid
           WHERE x.id IS NULL";
    sql_query($q2) or sqlerr(__FILE__, __LINE__);
    $deleted_posts = mysqli_affected_rows($GLOBALS['__mysql_link'] ?? null);

    // Лог в avps
    $numo = "Readposts: {$deleted_readposts}; Posts: {$deleted_posts}";

    if ($row_time === 0) {
        sql_query(
            "INSERT INTO avps (arg, value_u, value_s, value_i)
             VALUES ('fread_posts', " . sqlesc($next_ts) . ", " . sqlesc($numo) . ", " . sqlesc($deleted_readposts) . ")"
        ) or sqlerr(__FILE__, __LINE__);
    } else {
        sql_query(
            "UPDATE avps
             SET value_u = " . sqlesc($next_ts) . ",
                 value_s = " . sqlesc($numo) . ",
                 value_i = " . sqlesc($deleted_readposts) . "
             WHERE arg = 'fread_posts' LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);
    }

    // Инвалидация счётчиков (если такие ключи используешь)
    mc_del('unread:global:hint');
}

// ===================== META FORUM ===================
function meta_forum($iforum = false, $ipost = false): string {
    global $SITENAME, $DEFAULTBASEURL;

    // базовые URL
    $def_ico = rtrim($DEFAULTBASEURL, '/');
    $req_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $def_url = $def_ico . $req_uri;

    // Определим контекст по action
    $a = (string)($_GET['action'] ?? '');
    $topicid = null;

    if ($a === 'viewpost' && is_valid_id($_GET['id'] ?? 0)) {
        $ipost = (int)$_GET['id'];
    } elseif ($a === 'viewtopic' && is_valid_id($_GET['topicid'] ?? 0)) {
        $topicid = (int)$_GET['topicid'];
    } elseif ($a === 'viewforum' && is_valid_id($_GET['forumid'] ?? 0)) {
        $iforum = (int)$_GET['forumid'];
    }

    // Мету для гостей можно кешировать (страницы публичные)
    $is_guest = empty($_COOKIE['uid']);
    $cache_key = null;
    if ($is_guest) {
        if (!empty($iforum))  $cache_key = "meta:forum:{$iforum}";
        if (!empty($topicid)) $cache_key = "meta:topic:{$topicid}";
        if (!empty($ipost))   $cache_key  = "meta:post:{$ipost}";
        if ($cache_key) {
            $cached = mc_get($cache_key);
            if ($cached !== false && is_string($cached)) {
                return $cached;
            }
        }
    }

    $name = '';
    $descr = '';
    $name_orig = "портал файлов, muz-tracker, muz, музыка, mp3 скачать, Tesla, Tesla Tracker, Tesla Platinum, tesla tbdev, скачать бесплатно, скачать без регистрации, скачать торрент, скачать через торрент, загрузка файлов, tracker, zaycev";

    if ($is_guest) {
        if (!empty($iforum)) {
            // Форум: имя + описание + до 10 тем в описании
            $s  = sql_query("SELECT name, description FROM forums WHERE id = " . sqlesc($iforum) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $f  = mysqli_fetch_assoc($s) ?: ['name' => '', 'description' => ''];
            $name  = trim(($f['name'] ?? '') . ' ' . ($f['description'] ?? ''));
            $descr = (string)($f['description'] ?? '');

            $s2 = sql_query("SELECT subject FROM topics WHERE forumid = " . sqlesc($iforum) . " ORDER BY id DESC LIMIT 10") or sqlerr(__FILE__, __LINE__);
            while ($t = mysqli_fetch_assoc($s2)) {
                $descr .= ' ' . ($t['subject'] ?? '');
                if (strlen($descr) >= 1000) break;
            }

        } elseif (!empty($topicid)) {
            // Тема: subject + имя форума, и первые 3 поста в description
            $s  = sql_query(
                "SELECT subject, (SELECT name FROM forums WHERE forums.id = topics.forumid LIMIT 1) AS name
                 FROM topics WHERE id = " . sqlesc($topicid) . " LIMIT 1"
            ) or sqlerr(__FILE__, __LINE__);
            $t  = mysqli_fetch_assoc($s) ?: ['subject' => '', 'name' => ''];
            $name  = trim(($t['subject'] ?? '') . ' ' . ($t['name'] ?? ''));

            $descr = '';
            $s2 = sql_query("SELECT body FROM posts WHERE topicid = " . sqlesc($topicid) . " ORDER BY id ASC LIMIT 3") or sqlerr(__FILE__, __LINE__);
            while ($p = mysqli_fetch_assoc($s2)) {
                $descr .= (string)$p['body'];
                if (strlen($descr) >= 1000) break;
            }

        } elseif (!empty($ipost)) {
            // Конкретный пост: subject темы + body поста
            $s = sql_query(
                "SELECT p.body,
                        (SELECT subject FROM topics WHERE topics.id = p.topicid LIMIT 1) AS subject
                 FROM posts p
                 WHERE p.id = " . sqlesc($ipost) . " LIMIT 1"
            ) or sqlerr(__FILE__, __LINE__);
            $p = mysqli_fetch_assoc($s) ?: ['body' => '', 'subject' => ''];
            $name  = trim(($p['subject'] ?? '') . ' ' . ($p['body'] ?? ''));
            $descr = trim(($p['subject'] ?? '') . ' ' . ($p['body'] ?? ''));

        } else {
            // Главная/прочее для гостей
            $name  = "музыкальный портал музыки, портал файлов, muz-tracker, muz, музыка, mp3 скачать";
            $descr = "Музыкальный портал музыки. Скачать через торрент БЕСПЛАТНО и БЕЗ регистрации.";
        }
    }

    // Очистка описания
    $descr = (string)$descr;
    $descr = preg_replace("/\[((\s|.)+?)\]/is", "", $descr);     // BB-теги
    $descr = strip_tags($descr);
    // Убираем явные URL, чтобы не плодить ссылки в <meta>
    $descr = preg_replace("/(\A|[^=\]'\"a-zA-Z0-9])((?:https?|ftps?):\/\/[^()<>\s]+)/is", "", $descr);
    $descr = preg_replace('/[\r\n\t]+/u', ' ', $descr);
    $descr = trim(mb_substr($descr, 0, 255, 'UTF-8'));

    // Ключевые слова
    $keywords = strip_tags((string)$name);
    $keywords = preg_replace('/\s+/u', ',', $keywords);
    $keywords = preg_replace('/[\r\n\t]+/u', ',', (string)$keywords);
    $keywords = trim($keywords);

    // $array_file может не существовать — аккуратно
    if (!empty($GLOBALS['array_file']) && is_array($GLOBALS['array_file'])) {
        $name_orig .= ", " . trim(implode(",", $GLOBALS['array_file']));
    }

    $dexplode = $keywords . ", " . $name_orig;
    $dexplode = str_replace("_", " ", $dexplode);
    $dexplode = str_replace(["]","[","'","`","/"], "", $dexplode);
    $dexplode = preg_replace("/\(((\s|.)+?)\)/u", "", $dexplode);

    $keywords_array = array_filter(array_map('trim', explode(",", $dexplode)));
    $keywords_array = array_unique($keywords_array);
    sort($keywords_array, SORT_NATURAL | SORT_FLAG_CASE);

    $keywords_w = "";
    $strall = mb_strlen($dexplode, 'UTF-8');
    foreach ($keywords_array as $k) {
        if ($strall < 1000 && $k !== '' && mb_strlen($k, 'UTF-8') >= 3) {
            $keywords_w .= ($keywords_w !== "" ? ", " : "") . $k;
        } elseif ($strall >= 1000) {
            break;
        }
    }
    if ($keywords_w === "") $keywords_w = $keywords . ", " . $name_orig;
    $keywords_w = preg_replace("/,\s+/u", ", ", (string)$keywords_w);
    $keywords_w = trim($keywords_w);

    // Безопасное экранирование для meta
    $meta_keywords = htmlspecialchars($keywords_w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $meta_descr    = htmlspecialchars($descr,       ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Итоговый HTML
    $content  = "";
    $content .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"/>\n";
    $content .= "<meta name=\"google-site-verification\" content=\"4H80qcqfrkAEmHFgkNCwvzV1X3Cq6NTw3Kf0a7CR_Mg\"/>\n";
    $content .= "<meta name=\"msvalidate.01\" content=\"62F86B905E627D78A50502FC477ECECF\"/>\n";
    $content .= "<meta name=\"author\" content=\"7Max7\"/>\n";
    $content .= "<meta name=\"publisher-url\" content=\"{$def_url}\"/>\n";
    $content .= "<meta name=\"copyright\" content=\"Tesla Tracker TT (" . date('Y') . ") v.Platinum\"/>\n";
    $content .= "<meta name=\"generator\" content=\"PhpDesigner см. useragreement.php\"/>\n";

    if ($meta_keywords !== "") {
        $content .= "<meta name=\"keywords\" content=\"{$meta_keywords}\"/>\n";
    }
    if ($meta_descr !== "") {
        $content .= "<meta name=\"description\" content=\"{$meta_descr}\"/>\n";
    }

    $content .= "<meta name=\"robots\" content=\"index, follow\"/>\n";
    $content .= "<meta name=\"revisit-after\" content=\"15 days\"/>\n";
    $content .= "<meta name=\"rating\" content=\"general\"/>\n";
    $content .= "<link rel=\"shortcut icon\" href=\"{$def_ico}/pic/favicon.ico\" type=\"image/x-icon\"/>\n";
    $content .= "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"Последние торрент релизы\" href=\"{$def_ico}/rss.php\"/>\n";

    // Кеш для гостей
    if ($is_guest && $cache_key) {
        mc_set($cache_key, $content, 900); // 15 минут
    }

    return $content;
}
   
   
   
 function format_comment_light($text = null) {
    global $CURUSER, $BASEURL, $pic_base_url, $lang;

    $s = (string)$text;

    // лёгкая нормализация
    $s = str_replace(";)", ";-)", $s);

    // Сначала экранируем весь сырой HTML (без & → &amp; трогать не будем вручную)
    // ENT_SUBSTITUTE — чтобы сломанные юникод-байты не убивали вывод.
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Чистим потенциальные вставки <script> оставшиеся после экранирования (на всякий)
    // и двойные пробелы в сущностях, пришедшие из старых текстов.
    $s = preg_replace("~&lt;script[^&]*?&gt;.*?&lt;/script&gt;~is", "", $s);
    $s = preg_replace('~\s+~u', ' ', $s);

    // === Подмена домена (если нужно держать зеркала в унисон) ===
    $site = parse_url($BASEURL, PHP_URL_HOST);
    if ($site === "www.muz-tracker.net") {
        $s = str_replace("www.muz-trackers.ru", "www.muz-tracker.net", $s);
    } elseif ($site === "www.muz-trackers.ru") {
        $s = str_replace("www.muz-tracker.net", "www.muz-trackers.ru", $s);
    }
    unset($site);

    // ===== BB-коды (минимальный whitelist) =====
    // [b],[i],[u],[s],[h]
    $s = preg_replace("~\[b\]((?:.|\s)+?)\[/b\]~i", "<b>$1</b>", $s);
    $s = preg_replace("~\[i\]((?:.|\s)+?)\[/i\]~i", "<i>$1</i>", $s);
    $s = preg_replace("~\[u\]((?:.|\s)+?)\[/u\]~i", "<u>$1</u>", $s);
    $s = preg_replace("~\[s\]((?:.|\s)+?)\[/s\]~i", "<s>$1</s>", $s);
    $s = preg_replace("~\[h\]((?:.|\s)+?)\[/h\]~i", "<h3>$1</h3>", $s);

    // списки/разделители/переносы
    $s = preg_replace("~\[li\]~i", "<li>", $s);
    $s = preg_replace("~\[hr\]~i", "<hr>", $s);
    $s = preg_replace("~\[br\]~i", "<br />", $s);
    $s = preg_replace("~\[\*\]~", "<li>", $s);

    // [size], [font], [color] — оставляем текст, убираем оформление (как у тебя)
    $s = preg_replace("~\[size=\d+\](.*?)\[/size\]~is", "$1", $s);
    $s = preg_replace("~\[font=[a-zA-Z ,]+\]((?:.|\s)+?)\[/font\]~i", "$1", $s);
    $s = preg_replace("~\[color=#[0-9a-fA-F]{6}\]((?:.|\s)+?)\[/color\]~", "$1", $s);
    $s = preg_replace("~\[color=[a-zA-Z]+\]((?:.|\s)+?)\[/color\]~", "$1", $s);

    // [align]/старые теги выравнивания — убираем обёртку, оставляем текст
    $s = preg_replace("~\[(left|right|center|justify)\](.*?)\[/\\1\]~is", "$2", $s);
    $s = preg_replace("~\[align=(left|right|center|justify)\](.*?)\[/align\]~is", "$2", $s);

    // ===== URL/IMG с валидацией =====
    $safeUrl = static function ($u) {
        $u = html_entity_decode($u, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $parts = parse_url($u);
        if (!$parts) return null;
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http','https'], true)) return null;
        if (empty($parts['host'])) return null;
        // Сборка обратно — базово достаточно исходной строки
        return htmlspecialchars($u, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    // [url=...]текст[/url]
    $s = preg_replace_callback(
        "~\[url=(.+?)\]((?:.|\s)+?)\[/url\]~i",
        function ($m) use ($safeUrl) {
            $u = $safeUrl($m[1]);
            $t = $m[2];
            if (!$u) return $t; // если URL плохой — просто текст
            return '<a href="'.$u.'" rel="nofollow ugc noopener" target="_blank">'.$t.'</a>';
        },
        $s
    );

    // [url]...[/url]
    $s = preg_replace_callback(
        "~\[url\]((?:.|\s)+?)\[/url\]~i",
        function ($m) use ($safeUrl) {
            $u = $safeUrl(trim($m[1]));
            if (!$u) return $m[1];
            return '<a href="'.$u.'" rel="nofollow ugc noopener" target="_blank">'.$u.'</a>';
        },
        $s
    );

    // [img]...[/img] и [img=...]
    $imgCb = function ($u) use ($safeUrl) {
        $u = $safeUrl($u);
        if (!$u) return ''; // плохой URL — ничего не выводим
        // Разрешённые расширения картинок
        if (!preg_match('~\.(?:jpe?g|png|gif|webp)(?:\?.*)?$~i', html_entity_decode($u, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))) {
            return '';
        }
        return '<img src="'.$u.'" alt="" loading="lazy" referrerpolicy="no-referrer">';
    };
    $s = preg_replace_callback("~\[img\]([^\s'\"<>]+)\[/img\]~i", fn($m) => $imgCb($m[1]), $s);
    $s = preg_replace_callback("~\[img=([^\s'\"<>]+)\]~i", fn($m) => $imgCb($m[1]), $s);

    // дополнительная фильтрация простых слов (как у тебя)
    $s = str_ireplace(["javascript", "alert", "<body", "<html"], "", $s);

    // к quote/url автопарсеру твоему — после BB-обработки
    if (function_exists('format_quotes')) $s = format_quotes($s);
    if (function_exists('format_urls'))   $s = format_urls($s);

    // Перевод строк → <br>, затем «жёсткая» переноска длинных слов
    $s = nl2br($s);
    $s = wordwrap($s, 97, "\n", true);

    return $s;
}

// без изменений
function stderr_f($heading = '', $text = '') {
   stdhead_f();
   stdmsg_f($heading, $text, 'error');
   stdfoot_f();
   die;
}

function stdhead_f($title = null) {
    global $BASEURL, $CURUSER, $SITENAME;

    // Безопасные значения
    $siteTitle   = (string)$SITENAME;
    $pageTitle   = $title ? (string)$title . " - " . $siteTitle : "Форум - " . $siteTitle;

    // Блок «новые сообщения»
    $newmessage = '';
    if (!empty($CURUSER)) {
        $unread = (int)($CURUSER['unread'] ?? 0);
        if ($unread > 0) {
            $newmessage1 = $unread . " нов" . ($unread > 1 ? "ых" : "ое");
            $newmessage2 = " сообщен" . ($unread > 1 ? "ий" : "ие");
            $newmessage  = "<b><a href='{$BASEURL}/message.php?action=new'>У вас {$newmessage1} {$newmessage2}</a></b>";
        }
    }

    // Экранируем username и отметку времени
    $username     = !empty($CURUSER['username']) ? htmlspecialchars($CURUSER['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
    $userIdSafe   = !empty($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
    $forumAccess  = htmlspecialchars((string)($CURUSER['forum_access'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Frameset//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd\">
<html>
<head>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"js/style_forums.css\" />

<script src=\"js/jquery.js\" defer></script>
<script src=\"js/forums.js\" defer></script>
<script src=\"js/swfobject.js\" defer></script>
<script src=\"js/functions.js\" defer></script>
<script src=\"js/tooltips.js\" defer></script>

<title>{$pageTitle}</title>
" . meta_forum() . "
</head>
<body>

<table cellpadding=\"0\" cellspacing=\"0\" id=\"main\">
<tr>
<td class=\"main_col1\"><img src=\"/pic/forumicons/clear.gif\" alt=\"\" /></td>
<td class=\"main_col2\"><img src=\"/pic/forumicons/clear.gif\" alt=\"\" /></td>
<td class=\"main_col3\"><img src=\"/pic/forumicons/clear.gif\" alt=\"\" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign=\"top\">
<table cellpadding=\"0\" cellspacing=\"0\" id=\"header\">
<tr>
<td id=\"logo\">" . (defined('LOGO') ? LOGO : '') . "</td>

<td class=\"login\">
  <div id=\"login_box\"><span class=\"smallfont\">
    <div>Здравствуйте, " . (!empty($CURUSER)
        ? "<a href=\"{$BASEURL}/userdetails.php?id={$userIdSafe}\">{$username}</a>
           <div>Последнее обновление: <span class=\"time\">{$forumAccess}</span></div>
           <div>{$newmessage}</div>"
        : " для просмотра полной версии данных,
           <div>пожалуйста, <a href=\"{$BASEURL}/login.php\">авторизуйтесь</a>.
           <div>Права просмотра: Гость</div>
           </div>"
    ) . "</div>
  </span></div>
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
<td class=\"first\"><a href=\"{$BASEURL}/index.php\">Главная сайта</a></td>
<td class=\"shad\"><a href=\"{$BASEURL}/browse.php\">Торренты</a></td>
<td class=\"shad\"><a href=\"{$BASEURL}/forums.php\">Главная форума</a></td>"
. (!empty($CURUSER) ? "
<td class=\"shad\"><a href=\"{$BASEURL}/forums.php?action=search\">Поиск</a></td>
<td class=\"shad\"><a href=\"{$BASEURL}/forums.php?action=viewunread\">Непрочитанные комментарии</a></td>
<td class=\"shad\"><a title=\"Пометить все сообщения прочитанными\" href=\"{$BASEURL}/forums.php?action=catchup\">Все как прочитанное</a></td>" : "") .
"
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
<td class=\"content_col1\"><img src=\"/pic/forumicons/clear.gif\" alt=\"\" /></td>
<td class=\"content_col_left\">&nbsp;</td>
<td class=\"content_col5\"><img src=\"/pic/forumicons/clear.gif\" alt=\"\" /></td>
</tr>
<tr>
<td>&nbsp;</td>
<td valign=\"top\">
<br />";
}

function stdfoot_f($title = null) {
    global $use_gzip, $queries, $tstart, $querytime, $CURUSER;

    // Тайминги и защита от деления на ноль
    $total = max(1e-9, (float)(timer() - $tstart));
    $sql   = (float)$querytime;
    $php   = max(0.0, $total - $sql);

    $percentphp = number_format(($php / $total) * 100, 2);
    $percentsql = number_format(($sql / $total) * 100, 2);

    $seconds = number_format($total, 5, '.', '');
    $memory  = mksize((int)@memory_get_usage());

    // gzip заметка
    $gzip = (!empty($use_gzip) && $use_gzip === "yes" && !empty($CURUSER))
        ? " (gzip " . (string)ini_get('zlib.output_compression_level') . ")"
        : ".";

    $time_sql = sprintf("%0.4f", $sql);
    $time_php = sprintf("%0.4f", $php);

    $queries_cnt = (int)($queries ?? 0);

    echo "<br />
<td>&nbsp;</td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr></table></td>
<td>&nbsp;</td></tr>

<tr>
<td>&nbsp;</td>
<td align=\"center\">
  <div class=\"content_bl\"><div class=\"content_br\"><div class=\"content_b\"></div></div></div>

  <table cellpadding=\"0\" cellspacing=\"0\" id=\"footer\" align=\"center\">
  </table>

  <br />
  <div style=\"color:#FFFFFF;\" align=\"center\" class=\"smallfont\">
    <b>" . (defined('VERSION') ? VERSION : '') . (defined('TBVERSION') ? TBVERSION : '') . "</b><br />
    Страничка сформирована за <b>{$seconds}</b> секунд{$gzip}<br />
    <b>{$queries_cnt}</b> (queries) - <b>{$percentphp}%</b> ({$time_php} =&gt; php) - <b>{$percentsql}%</b> ({$time_sql} =&gt; sql)"
    . ((function_exists('get_user_class') && defined('UC_SYSOP') && get_user_class() == UC_SYSOP && !empty($memory))
        ? " - {$memory} (use memory)" : "")
  . "</div>
</td>
<td>&nbsp;</td>
</tr>
</table></body></html>";

    // Отладочный вывод запросов (только для SYSOP и если включен DEBUG_MODE)
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('get_user_class') && defined('UC_SYSOP')
        && get_user_class() == UC_SYSOP && !empty($_COOKIE['debug']) && $_COOKIE['debug'] === 'yes'
        && !empty($GLOBALS['query_stat']) && is_array($GLOBALS['query_stat'])) {

        foreach ($GLOBALS['query_stat'] as $key => $value) {
            $n = (int)$key + 1;
            $sec = (float)($value['seconds'] ?? 0);
            $sec_html = ($sec <= 0.0009)
                ? "<font color=\"white\" title=\"Сверхбыстрый запрос. Время исполнения отличное.\">{$sec}</font>"
                : (($sec >= 0.01)
                    ? "<font color=\"red\" title=\"Рекомендуется оптимизировать запрос. Время исполнения превышает норму.\">{$sec}</font>"
                    : "<font color=\"blue\" title=\"Запрос не нуждается в оптимизации. Время исполнения допустимое.\">{$sec}</font>");

            $cacheBadge = !empty($value['cache'])
                ? "<font color=\"white\" title=\"По этому запросу используется кеширование данных.\">{$value['cache']}</font>"
                : "";

            $queryText = htmlspecialchars((string)($value['query'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo "<div style=\"color:#FFFFFF;\">[<b>{$n}</b>] =&gt; {$cacheBadge} <b>{$sec_html}</b> [{$queryText}]</div>\n";
        }
        if (function_exists('debug')) {
            debug();
        }
    }
}

function stdmsg_f($heading = '', $text = '', $div = 'success', $htmlstrip = false) {
    // $div пока не используется, оставляю для совместимости
    $heading = (string)$heading;
    $text    = (string)$text;

    if ($htmlstrip) {
        // Мягкая зачистка: без htmlspecialchars, чтобы можно было передавать подготовленный HTML
        $heading = trim($heading);
        $text    = trim($text);
    }

    // Экран заголовка; тело считаем уже безопасным/подготовленным HTML
    $heading_safe = $heading !== '' ? "<b>" . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>" : "";

    echo "<br />
<div class=\"tcat_t\"><div class=\"tcat_r\"><div class=\"tcat_l\"><div class=\"tcat_tl\"><div class=\"tcat_simple\">
  <div align=\"center\"><a name=\"comments\"></a><b>.::: " . ($heading_safe !== "" ? "{$heading_safe}" : "") . " :::.</b></div>
  <br class=\"tcat_clear\" />
</div></div></div></div></div>

<div class=\"post_body\" id=\"collapseobj_forumbit_5\" align=\"center\">
  <table cellspacing=\"0\" cellpadding=\"0\" class=\"forums\"></table>
  <div align=\"center\" class=\"statist\">{$text}</div>
</div>
<div class=\"off\"><div class=\"tcat_b\"><div class=\"tcat_bl\"><div class=\"tcat_br\"></div></div></div></div><br />";
}

// ======== каркасные helpers как у тебя, но без варнингов ========
function begin_main_frame() {
    echo "<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">\n";
}
function end_main_frame() {
    echo "</td></tr></table>\n";
}
function begin_table($fullwidth = false, $padding = 5) {
    $width = $fullwidth ? " width=\"100%\"" : "";
    echo "<table class=\"main\"{$width} border=\"1\" cellspacing=\"0\" cellpadding=\"" . (int)$padding . "\">\n<tr><td>\n";
}
function end_table() {
    echo "</td></tr></table>\n";
}
function begin_frame($caption = "", $center = false, $padding = 10) {
    $caption = (string)$caption;
    if ($caption !== "") echo "<h2>" . htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
    $tdextra = $center ? " align=\"center\"" : "";
    echo "<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"" . (int)$padding . "\"><tr><td{$tdextra}>\n";
}
function attach_frame($padding = 10) {
    echo "</td></tr><tr><td style=\"border-top:0\" cellpadding=\"" . (int)$padding . "\">\n";
}
function end_frame() {
    echo "</td></tr></table>\n";
}
function insert_smilies_frame() {
    global $smilies, $DEFAULTBASEURL;
    begin_frame("Смайлы", true);
    begin_table(false, 5);
    echo "<tr><td class=\"colhead\">Написание</td><td class=\"colhead\">Смайл</td></tr>\n";
    if (!empty($smilies) && is_array($smilies)) {
        foreach ($smilies as $code => $url) {
            $codeSafe = htmlspecialchars((string)$code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $urlSafe  = htmlspecialchars((string)$url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo "<tr><td>{$codeSafe}</td><td><img src=\"{$DEFAULTBASEURL}/pic/smilies/{$urlSafe}\" alt=\"{$codeSafe}\"></td></tr>\n";
        }
    }
    end_table();
    end_frame();
}
   
   
   
?>