<?php

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

function get_user(string $field, int $user_id = 0): ?string {
    global $mysqli;

    $user_id = (int)$user_id;
    $field = preg_replace('/[^a-z0-9_]/i', '', $field); // Защита от SQL-инъекций через поле

    $sql = "SELECT `$field` FROM users WHERE id = $user_id LIMIT 1";
    $result = mysqli_query($mysqli, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row[$field];
    }

    return null;
}

function hacker(string $event = ''): void {
    global $hacker_ban_time, $mysqli;

    // --- безопасные шорткаты окружения ---
    $ip         = (string) (function_exists('getip') ? getip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    $userAgent  = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $referer    = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    // --- сбор полезного контекста (оставляем формат максимально близким) ---
    // В оригинале: serialize($_GET) . '||' . serialize($_POST) . '||' . $event . '||' . $referer . '||' . $requestUri
    $eventData = serialize($_GET) . '||' . serialize($_POST) . '||' . $event . '||' . $referer . '||' . $requestUri;

    // — Ограничим размер payload'а, чтобы не раздувать строковые столбцы (напр., TEXT/VARCHAR)
    // Обрезаем по байтам, безопасно для UTF-8
    $eventData  = substr($eventData, 0, 4000);
    $userAgent  = substr($userAgent, 0, 255);

    // --- определяем, сможем ли записать IP как IPv4 (историческая схема int) ---
    $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $firstIp = $isIpv4 ? sprintf('%u', ip2long($ip)) : '0'; // как строка, чтобы не потерять беззнаковость на 32-битах

    // --- гарантируем валидное подключение ---
    if (!($mysqli instanceof mysqli)) {
        // Если по какой-то причине нет mysqli — просто выходим, чтобы не уронить страницу.
        return;
    }

    // === INSERT INTO hackers ===
    // hackers (ip, system, event)
    // ip — обычно VARCHAR; system — UA; event — длинная строка с контекстом
    $stmt1 = $mysqli->prepare('INSERT INTO hackers (ip, system, event) VALUES (?, ?, ?)');
    if ($stmt1) {
        $stmt1->bind_param('sss', $ip, $userAgent, $eventData);
        if (!$stmt1->execute()) {
            // мягкая диагностика (оставляем совместимость: не кидаем исключение)
            if (function_exists('sqlerr')) {
                @sqlerr(__FILE__, __LINE__);
            }
        }
        $stmt1->close();
    } else {
        if (function_exists('sqlerr')) {
            @sqlerr(__FILE__, __LINE__);
        }
    }

    // === INSERT INTO bans ===
    // bans (added, addedby, first, last, comment, until)
    // исторически first/last — INT для диапазона IPv4; для IPv6 запишем 0 и оставим IP в комменте
    $banMinutes = (int) $hacker_ban_time;
    if ($banMinutes <= 0) {
        $banMinutes = 60; // дефолт на час, если константа не задана
    }

    $comment = 'Temporal hacker ban';
    if (!$isIpv4) {
        // чтобы не потерять IPv6, запишем его в комментарий
        $comment .= ' (IP: ' . $ip . ')';
    }
    $comment = substr($comment, 0, 255);

    // Вставляем одинаковые first/last (бан по одному адресу)
    // Важно: используем iis (int, int, string) + интервал
    $stmt2 = $mysqli->prepare(
        'INSERT INTO bans (added, addedby, first, last, comment, until)
         VALUES (NOW(), 0, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    if ($stmt2) {
        // Приводим $firstIp к int безопасно: используем 0, если не числовое
        $first = ctype_digit((string)$firstIp) ? (int)$firstIp : 0;
        $last  = $first;
        $stmt2->bind_param('iisi', $first, $last, $comment, $banMinutes);
        if (!$stmt2->execute()) {
            if (function_exists('sqlerr')) {
                @sqlerr(__FILE__, __LINE__);
            }
        }
        $stmt2->close();
    } else {
        if (function_exists('sqlerr')) {
            @sqlerr(__FILE__, __LINE__);
        }
    }

    // при желании — дополнительный файловый лог (off by default)
    // @error_log("Hacker event from {$ip}: {$event}", 3, __DIR__ . '/logs/hacker.log');
}



function get_ratio($ratio) {
  switch ($ratio)
  {
    case 10: return "blabla";
    case 20: return "blabla";
    case 30: return "blabla";
    case 40: return "blabla";
    case 50: return "blabla";
    case 60: return "blabla";
    case 70: return "blabla";
    case 80: return "blabla";
    case 90: return "blabla";
    case 100: return "blabla";
  }
  return "blabla";

}  

function get_user_class_color($class, $username): string
{
    global $tracker_lang;

    switch ($class) {
        case UC_SYSOP:
            return "<span style=\"background: linear-gradient(90deg, #00FFFF, #007FFF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold;\" title=\"{$tracker_lang['class_sysop']}\">$username</span>";

        case UC_ADMINISTRATOR:
            return "<span style=\"background: linear-gradient(90deg, #32CD32, #006400); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold;\" title=\"{$tracker_lang['class_administrator']}\">$username</span>";

        case UC_MODERATOR:
            return "<span style=\"color: #C71585; font-weight: bold;\" title=\"{$tracker_lang['class_moderator']}\">$username</span>"; // розовато-бордовый

        case UC_UPLOADER:
    return "<span style=\"background: linear-gradient(90deg, #FF8C00, #FF4500);
                          -webkit-background-clip: text;
                          background-clip: text;
                          -webkit-text-fill-color: transparent;
                          color: transparent;
                          font-weight: bold;\" 
                 title=\"{$tracker_lang['class_uploader']}\">$username</span>"; // оранжевый градиент

        case UC_VIP:
            return "<span style=\"color: #8A2BE2; font-weight: bold;\" title=\"{$tracker_lang['class_vip']}\">$username</span>"; // фиолетовый VIP

        case UC_POWER_USER:
            return "<span style=\"color: #FFD700; font-weight: bold;\" title=\"{$tracker_lang['class_power_user']}\">$username</span>"; // золотой

        case UC_USER:
            return "<span style=\"color: #4682B4;\" title=\"{$tracker_lang['class_user']}\">$username</span>"; // стальной синий

        default:
            return "<span style=\"color: #000\" title=\"Пользователь\">$username</span>";
    }
}



function display_date_time(int $timestamp = 0, int $tzoffset = 0): string {
    if ($timestamp <= 0) {
        $timestamp = time();
    }
    return date("Y-m-d H:i:s", $timestamp + ($tzoffset * 60));
}

function cut_text(string $txt, int $car): string {
    if (mb_strlen($txt, 'UTF-8') > $car) {
        return mb_substr($txt, 0, $car, 'UTF-8') . "...";
    }
    return $txt;
}




function get_row_count(string $table, string $suffix = ""): int
{
    global $mysqli;

    // Удаляем лишние пробелы и добавляем пробел перед суффиксом, если он есть
    $suffix = trim($suffix);
    if ($suffix !== "") {
        $suffix = " $suffix";
    }

    // Экранируем имя таблицы для избежания SQL-инъекций (если не жёстко задано)
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);

    // Выполняем запрос и обрабатываем ошибки
    $result = sql_query("SELECT COUNT(*) FROM `$table`$suffix") or die($mysqli->error);

    $row = mysqli_fetch_row($result);
    if (!$row) {
        die($mysqli->error);
    }

    return (int)$row[0];
}


function stdmsg(string $heading = '', string $text = '', string $div = 'success', bool $htmlstrip = false): void {
    if ($htmlstrip) {
        $heading = htmlspecialchars(trim($heading), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    echo '<table class="main" width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td class="embedded">';
    echo '<div class="' . $div . '">' . ($heading ? "<b>$heading</b><br />" : '') . $text . '</div>';
    echo '</td></tr></table>';
}


function stderr(string $heading = '', string $text = '', string $div = 'error'): void {
    stdhead();                        // Выводим заголовок
    stdmsg($heading, $text, $div);   // Сообщение
    stdfoot();                       // Подвал
    exit;                            // Завершаем выполнение
}


function newerr($heading = '', $text = '', $head = true, $foot = true, $die = true, $div = 'error', $htmlstrip = true) {
	if ($head)
		stdhead($heading);

	newmsg($heading, $text, $div, $htmlstrip);

	if ($foot)
		stdfoot();

	if ($die)
		die;
}

function sqlerr($file = '', $line = '') {
    global $queries;

    // Получаем ошибку MySQLi
    $error = isset($GLOBALS['mysqli']) ? mysqli_error($GLOBALS['mysqli']) : 'Неизвестная ошибка соединения с MySQL';

    print("<table border=\"0\" bgcolor=\"blue\" align=\"left\" cellspacing=\"0\" cellpadding=\"10\" style=\"background: blue\">" .
        "<tr><td class=\"embedded\"><font color=\"white\"><h1>Ошибка в SQL</h1>\n" .
        "<b>Ответ от сервера MySQL: " . htmlspecialchars_uni($error) .
        ($file != '' && $line != '' ? "<p>в $file, линия $line</p>" : "") .
        "<p>Запрос номер $queries.</p></b></font></td></tr></table>");
    die;
}


// Returns the current time in GMT in MySQL compatible format.
function get_date_time($timestamp = 0) {
	if ($timestamp)
		return date("Y-m-d H:i:s", $timestamp);
	else
		return date("Y-m-d H:i:s");
}

function encodehtml($s, $linebreaks = true) {
	$s = str_replace("<", "&lt;", str_replace("&", "&amp;", $s));
	if ($linebreaks)
		$s = nl2br($s);
	return $s;
}

function get_dt_num() {
	return date("YmdHis");
}

function format_urls(string $s): string
{
    if ($s === '') return '';

    // Ищем «голые» ссылки, но не после '=' или кавычек (чтобы не лезть в href="...").
    // Юникод-флаг 'u' включает поддержку кириллицы в доменах/пути.
    $re = '~(?<![=\'"])\b((?:(?:https?|ftps?|irc)://|www\.)[^\s<>\[\]()]+' .
          // не захватываем завершающую пунктуацию/скобки — отдадим их в lookahead
          ')(?=([.,;:!?)]?)(?:\s|$|[<>\[\])]))~iu';

    return preg_replace_callback($re, static function(array $m): string {
        $url   = $m[1];
        $trail = $m[2] ?? '';

        // Если начинается с www., добавим схему
        $href = (stripos($url, 'www.') === 0) ? 'http://' . $url : $url;

        // Иногда закрывающая ')' принадлежит тексту, а не URL — попробуем сбалансировать
        if ($trail === ')' && substr_count($url, '(') < substr_count($url, ')')) {
            // скобок в URL больше закрывающих — вернём ')' в текст
            $url  .= ')';
            $href .= ')';
            $trail = '';
        }

        // Экранируем для безопасной вставки в HTML
        $safeText = htmlspecialchars($url,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a target="_blank" rel="nofollow noopener noreferrer ugc" href="' .
               $safeHref . '">' . $safeText . '</a>' . $trail;
    }, $s);
}


function _strlastpos(string $haystack, string $needle, int $offset = 0): int|false
{
    if ($needle === '') {
        return false; // в PHP тоже needle не может быть пустым
    }

    // Используем встроенную функцию, если она есть
    if (function_exists('strrpos')) {
        return strrpos($haystack, $needle, $offset);
    }

    // Фолбэк через strrpos-эмуляцию
    $lastPos = false;
    $pos = $offset;

    while (($pos = strpos($haystack, $needle, $pos)) !== false) {
        $lastPos = $pos;
        $pos++; // продолжаем искать дальше
    }

    return $lastPos;
}

function format_quotes(string $s): string
{
    if ($s === '') return $s;

    // Найдём все теги [quote], [quote=...], [/quote] с позициями
    // m[0][0] = полный тег, m[0][1] = offset; m[1] — слеш (/) у закрывающего; m[2] — автор (если есть)
    $pattern = '/\[(\/)?quote(?:=([^\]\r\n]+))?\]/i';
    if (!preg_match_all($pattern, $s, $m, PREG_OFFSET_CAPTURE)) {
        // Тегов цитат нет — сразу отдаём строку
        return $s;
    }

    $cursor = 0;
    $out = '';
    $stack = [];

    // Хелперы
    $openTag = static function (?string $author): string {
        $legend = 'Цитата';
        if ($author !== null && $author !== '') {
            // уберём возможные кавычки вокруг автора: [quote="Иван"] или [quote='Иван']
            $a = trim($author);
            if (($a[0] ?? '') === '"' && substr($a, -1) === '"')  $a = substr($a, 1, -1);
            if (($a[0] ?? '') === "'" && substr($a, -1) === "'") $a = substr($a, 1, -1);
            $legend = htmlspecialchars($a, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' писал';
        }
        // Можно добавить class="quote" под вашу верстку
        return '<fieldset><legend><span class="editorinput">'.$legend.'</span></legend>';
    };

    $closeTag = static fn(): string => '</fieldset>';

    // Пройдёмся по всем найденным тегам
    $count = count($m[0]);
    for ($i = 0; $i < $count; $i++) {
        [$tag, $pos]     = $m[0][$i];
        [$isClose, ]     = $m[1][$i]; // '/' или null
        [$authorRaw, ]   = $m[2][$i]; // автор или null

        // 1) Добавим обычный текст до текущего тега
        if ($pos > $cursor) {
            $out .= substr($s, $cursor, $pos - $cursor);
        }

        // 2) Обработка тега
        if ($isClose === '/') {
            // Закрывающий тег [/quote]
            if (empty($stack)) {
                // Закрытие без открытия — считаем некорректным, возвращаем исходный текст
                return $s;
            }
            array_pop($stack);
            $out .= $closeTag();
        } else {
            // Открывающий тег [quote] или [quote=...]
            $stack[] = true;
            // $authorRaw может быть null (нет захвата), приводим к строке или null
            $author = ($authorRaw !== null) ? (string)$authorRaw : null;
            $out .= $openTag($author);
        }

        // 3) Сдвигаем курсор за текущий тег
        $cursor = $pos + strlen($tag);
    }

    // Добавим «хвост» строки после последнего тега
    if ($cursor < strlen($s)) {
        $out .= substr($s, $cursor);
    }

    // Если остались незакрытые теги — считаем ввод некорректным
    if (!empty($stack)) {
        return $s;
    }

    return $out;
}

// Format quote
function encode_quote(string $text): string
{
    // Один проход не раскроет вложенные цитаты — делаем цикл до стабилизации
    $pattern = '~\[quote(?:=([^\]\r\n]+))?\](.*?)\[/quote\]~is';

    $render = static function(array $m): string {
        $author = isset($m[1]) ? trim((string)$m[1]) : '';
        $body   = (string)$m[2];

        // Шапка: "Цитата" или "Имя написал(а):"
        $title = $author !== ''
            ? '<span style="font-weight:600;">'.htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span> написал(а):'
            : 'Цитата';

        // Контейнер без аватара, округлённый, прозрачный фон
        return ''
        .'<div class="bb-quote" style="margin:8px 0;padding:12px 14px;'
            .'border:1px solid #e3e3e3;border-radius:12px;'
            .'background:rgba(0,0,0,.03);">'
            .'<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">'
                .'<div style="font-size:13px;color:#666;">'.$title.'</div>'
            .'</div>'
            // Текст: переносы строк, перенос длинных слов, высота строки
            .'<div style="margin-top:6px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;">'
                .$body
            .'</div>'
        .'</div>';
    };

    // Повторяем, пока есть замены (обрабатывает вложенность)
    $prev = null;
    while ($prev !== $text) {
        $prev = $text;
        $text = preg_replace_callback($pattern, $render, $text);
    }

    return $text;
}

// Format quote from
function encode_quote_from(string $text): string
{
    $pattern = '~\[quote=([^\]\r\n]+)\](.*?)\[/quote\]~is';

    $render = static function(array $m): string {
        $author = trim((string)$m[1]);
        $body   = (string)$m[2];

        $title = htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' писал(а):';

        return ''
        .'<div class="bb-quote" style="margin:8px 0;padding:12px 14px;'
            .'border:1px solid #e3e3e3;border-radius:12px;'
            .'background:rgba(0,0,0,.03);">'
            .'<div style="font-size:13px;color:#666;font-weight:600;">'.$title.'</div>'
            .'<div style="margin-top:6px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;">'
                .$body
            .'</div>'
        .'</div>';
    };

    // Поддержка вложенных цитат — повторяем пока есть срабатывания
    $prev = null;
    while ($prev !== $text) {
        $prev = $text;
        $text = preg_replace_callback($pattern, $render, $text);
    }

    return $text;
}


// Format code
function encode_code($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
	."<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
	."<tr bgcolor=\"E5EFFF\"><td colspan=\"2\"><font class=\"block-title\">Код</font></td></tr>"
	."<tr class=\"bgcolor1\"><td align=\"right\" class=\"code\" style=\"width: 5px; border-right: none\">{ZEILEN}</td><td class=\"code\">";
	$end_html = "</td></tr></table></div></div>";
	$match_count = preg_match_all("#\[code\](.*?)\[/code\]#si", $text, $matches);
    for ($mout = 0; $mout < $match_count; ++$mout) {
      $before_replace = $matches[1][$mout];
      $after_replace = $matches[1][$mout];
      $after_replace = trim ($after_replace);
      $zeilen_array = explode ("<br />", $after_replace);
      $j = 1;
      $zeilen = "";
      foreach ($zeilen_array as $str) {
        $zeilen .= "".$j."<br />";
        ++$j;
      }
      $after_replace = str_replace ("", "", $after_replace);
      $after_replace = str_replace ("&amp;", "&", $after_replace);
      $after_replace = str_replace ("", "&nbsp; ", $after_replace);
      $after_replace = str_replace ("", " &nbsp;", $after_replace);
      $after_replace = str_replace ("", "&nbsp; &nbsp;", $after_replace);
      $after_replace = preg_replace ("/^ {1}/m", "&nbsp;", $after_replace);
      $str_to_match = "[code]".$before_replace."[/code]";
      $replace = str_replace ("{ZEILEN}", $zeilen, $start_html);
      $replace .= $after_replace;
      $replace .= $end_html;
      $text = str_replace ($str_to_match, $replace, $text);
    }

    $text = str_replace ("[code]", $start_html, $text);
    $text = str_replace ("[/code]", $end_html, $text);
    return $text;
}

function encode_php($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
	."<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
	."<tr bgcolor=\"F3E8FF\"><td colspan=\"2\"><font class=\"block-title\">PHP - Код</font></td></tr>"
	."<tr class=\"bgcolor1\"><td align=\"right\" class=\"code\" style=\"width: 5px; border-right: none\">{ZEILEN}</td><td>";
	$end_html = "</td></tr></table></div></div>";
	$match_count = preg_match_all("#\[php\](.*?)\[/php\]#si", $text, $matches);
    for ($mout = 0; $mout < $match_count; ++$mout) {
        $before_replace = $matches[1][$mout];
        $after_replace = $matches[1][$mout];
        $after_replace = trim ($after_replace);
		$after_replace = str_replace("&lt;", "<", $after_replace);
		$after_replace = str_replace("&gt;", ">", $after_replace);
		$after_replace = str_replace("&quot;", '"', $after_replace);
		$after_replace = preg_replace("/<br.*/i", "", $after_replace);
		$after_replace = (substr($after_replace, 0, 5 ) != "<?php") ? "<?php\n".$after_replace."" : "".$after_replace."";
		$after_replace = (substr($after_replace, -2 ) != "?>") ? "".$after_replace."\n?>" : "".$after_replace."";
        ob_start ();
        highlight_string ($after_replace);
        $after_replace = ob_get_contents ();
        ob_end_clean ();
		$zeilen_array = explode("<br />", $after_replace);
        $j = 1;
        $zeilen = "";
      foreach ($zeilen_array as $str) {
        $zeilen .= "".$j."<br />";
        ++$j;
      }
		$after_replace = str_replace("\n", "", $after_replace);
		$after_replace = str_replace("&amp;", "&", $after_replace);
		$after_replace = str_replace("  ", "&nbsp; ", $after_replace);
		$after_replace = str_replace("  ", " &nbsp;", $after_replace);
		$after_replace = str_replace("\t", "&nbsp; &nbsp;", $after_replace);
		$after_replace = preg_replace("/^ {1}/m", "&nbsp;", $after_replace);
		$str_to_match = "[php]".$before_replace."[/php]";
		$replace = str_replace("{ZEILEN}", $zeilen, $start_html);
      $replace .= $after_replace;
      $replace .= $end_html;
      $text = str_replace ($str_to_match, $replace, $text);
    }
	$text = str_replace("[php]", $start_html, $text);
	$text = str_replace("[/php]", $end_html, $text);
    return $text;
}

function format_comment(string $text, bool $strip_html = true): string {
    global $smilies, $privatesmilies, $mysqli, $memcached, $nummatch;

    if (!isset($nummatch)) $nummatch = 0;
    if (!is_array($smilies))        $smilies = [];
    if (!is_array($privatesmilies)) $privatesmilies = [];

    // Безопасная экранизация до парсинга BB
    $s = $strip_html
        ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : $text;

    // Нормализация парочки смайлов до кодов
    $s = str_replace(';)', ':wink:', $s);

    /* ===== Подсветка имён персон ДО BBCode =====
       Превращаем совпадения в [url=persons.php?id=ID]Имя[/url],
       пропуская содержимое [url], [img], [code].
    ================================================================= */
    try {
        // 1) Получаем кэш имён
        $pages = [];
        $cacheKey = 'pages_names_map_v2'; // name(lower) => id
        $haveMemc = class_exists('Memcached');

        if ($haveMemc) {
            if (!isset($memcached) || !($memcached instanceof Memcached)) {
                $memcached = new Memcached();
                $memcached->addServer('127.0.0.1', 11211);
            }
            $pages = $memcached->get($cacheKey);
            if ($memcached->getResultCode() !== Memcached::RES_SUCCESS || !is_array($pages)) {
                $pages = [];
                if ($res = mysqli_query($mysqli, "SELECT id, name FROM pages")) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $name = trim((string)$row['name']);
                        if ($name === '') continue;
                        // Храним карту: нижний регистр => id
                        $pages[mb_strtolower($name, 'UTF-8')] = (int)$row['id'];
                    }
                }
                // TTL 5 минут
                $memcached->set($cacheKey, $pages, 300);
            }
        } else {
            if ($res = mysqli_query($mysqli, "SELECT id, name FROM pages")) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $name = trim((string)$row['name']);
                    if ($name === '') continue;
                    $pages[mb_strtolower($name, 'UTF-8')] = (int)$row['id'];
                }
            }
        }

        if ($pages) {
            // 2) Строим регэксп-альтернативу по именам (длинные вперёд)
            $names = array_keys($pages);
            usort($names, fn($a,$b) => mb_strlen($b,'UTF-8') <=> mb_strlen($a,'UTF-8'));
            $escaped = array_map(fn($n) => preg_quote($n, '~'), $names);
            // Границы: не буква/цифра/подчёркивание слева/справа (Unicode)
            $namesRx = '~(?<![\p{L}\p{N}_])(' . implode('|', $escaped) . ')(?![\p{L}\p{N}_])~iu';

            // 3) Защищаем [url], [img], [code] блоки — не заменяем внутри
            $blockRx = '~\[(url|img|code)(?:=[^\]]*)?\]((?:(?!\[/\1\]).)*?)\[/\1\]~is';
            $out = '';
            $last = 0;

            if (preg_match_all($blockRx, $s, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $i => $full) {
                    [$blockStr, $pos] = $full;

                    // неблоковая часть до защищённого блока
                    $chunk = substr($s, $last, $pos - $last);
                    if ($chunk !== '') {
                        $chunk = preg_replace_callback($namesRx, function($mm) use ($pages) {
                            $hit = (string)$mm[1];                         // оригинальный регистр
                            $key = mb_strtolower($hit, 'UTF-8');           // ключ
                            $id  = $pages[$key] ?? null;
                            if (!$id) return $hit;
                            // Заворачиваем в BBCode-ссылку, чтобы ниже парсер сделал <a>
                            return '[url=persons.php?id='.$id.']'.$hit.'[/url]';
                        }, $chunk);
                        if ($chunk === null) $chunk = substr($s, $last, $pos - $last); // на случай pcre.backtrack-limit
                    }

                    $out  .= $chunk;
                    $out  .= $blockStr; // сам блок оставляем без изменений
                    $last = $pos + strlen($blockStr);
                }
                // хвост после последнего блока
                $tail = substr($s, $last);
                if ($tail !== '') {
                    $tail = preg_replace_callback($namesRx, function($mm) use ($pages) {
                        $hit = (string)$mm[1];
                        $key = mb_strtolower($hit, 'UTF-8');
                        $id  = $pages[$key] ?? null;
                        if (!$id) return $hit;
                        return '[url=persons.php?id='.$id.']'.$hit.'[/url]';
                    }, $tail);
                    if ($tail === null) $tail = substr($s, $last);
                }
                $s = $out . $tail;
            } else {
                // Нет защищённых блоков — можно заменить всё разом
                $s2 = preg_replace_callback($namesRx, function($mm) use ($pages) {
                    $hit = (string)$mm[1];
                    $key = mb_strtolower($hit, 'UTF-8');
                    $id  = $pages[$key] ?? null;
                    if (!$id) return $hit;
                    return '[url=persons.php?id='.$id.']'.$hit.'[/url]';
                }, $s);
                if ($s2 !== null) $s = $s2;
            }
        }
    } catch (\Throwable $e) {
        // Тихо игнорим, чтобы не ломать вывод при редких ошибках PCRE/мемкеша
    }

    /* ===== BB-коды (static массивы). БЕЗ [size] здесь! ===== */
    static $bb_find = null, $bb_repl = null;
    if ($bb_find === null) {
        $bb_find = [
            // IMG
            '#\[img\](https?://[^\s\[]+)\[/img\]#iu',
            '#\[img=([a-zA-Z]+)\](https?://[^\s\[]+)\[/img\]#iu',
            '#\[img\ alt=([a-zA-Zа-яА-Я0-9_\-\. ]+)\](https?://[^\s\[]+)\[/img\]#iu',
            '#\[img=([a-zA-Z]+)\s+alt=([a-zA-Zа-яА-Я0-9_\-\. ]+)\](https?://[^\s\[]+)\[/img\]#iu',
            // URL
            '#\[url\](https?://[^\s\[]+)\[/url\]#iu',
            '#\[url\]((?:www|ftp)\.[^\s\[]+)\[/url\]#iu',
            '#\[url=(https?://[^\s\]]+)\](.*?)\[/url\]#isu',
            '#\[url=((?:www|ftp)\.[^\s\]]+)\](.*?)\[/url\]#isu',
            '/\[url=([^()<>\s]+?)\]((?:.|\s)+?)\[\/url\]/iu',
            '/\[url\]([^()<>\s]+?)\[\/url\]/iu',
            // MAIL
            '#\[mail\](\S+?)\[/mail\]#iu',
            '#\[mail\s*=\s*([\.\w\-]+\@[\.\w\-]+\.[\w\-]+)\s*\](.*?)\[\/mail\]#iu',
            // Оформление
            '#\[color=(\#[0-9A-Fa-f]{6}|[a-z]+)\](.*?)\[/color\]#isu',
            '#\[(?:font|family)=([A-Za-z ]+)\](.*?)\[/\(?:font|family\)\]#isu', // исправлено ниже — см. repl
            '#\[(left|right|center|justify)\](.*?)\[/\\1\]#isu',
            '#\[b\](.*?)\[/b\]#isu',
            '#\[i\](.*?)\[/i\]#isu',
            '#\[u\](.*?)\[/u\]#isu',
            '#\[s\](.*?)\[/s\]#isu',
            '#\[li\]#i',
            '#\[hr\]#i',
        ];
        // Исправление паттерна font/family (опечатка в слэше закрывающего тега)
        $bb_find[13] = '#\[(?:font|family)=([A-Za-z ]+)\](.*?)\[/(?:font|family)\]#isu';

        $bb_repl = [
            '<img class="linked-image" src="\\1" alt="\\1" title="\\1">',
            '<img class="linked-image" src="\\2" style="float:\\1" alt="\\2" title="\\2">',
            '<img class="linked-image" src="\\2" alt="\\1" title="\\1">',
            '<img class="linked-image" src="\\3" style="float:\\1" alt="\\2" title="\\2">',
            '<a href="\\1" rel="nofollow ugc noopener" target="_blank">\\1</a>',
            '<a href="http://\\1" rel="nofollow ugc noopener" target="_blank">\\1</a>',
            '<a href="\\1" rel="nofollow ugc noopener" target="_blank">\\2</a>',
            '<a href="http://\\1" rel="nofollow ugc noopener" target="_blank">\\2</a>',
            '<a href="\\1" rel="nofollow ugc noopener" target="_blank">\\2</a>',
            '<a href="\\1" rel="nofollow ugc noopener" target="_blank">\\1</a>',
            '<a href="mailto:\\1">\\1</a>',
            '<a href="mailto:\\1">\\2</a>',
            '<span style="color:\\1">\\2</span>',
            '<span style="font-family:\\1">\\2</span>',
            '<div style="text-align:\\1">\\2</div>',
            '<b>\\1</b>',
            '<i>\\1</i>',
            '<u>\\1</u>',
            '<s>\\1</s>',
            '<li>',
            '<hr>',
        ];
    }

    // [size] делаем отдельно callback’ом, чтобы ограничить диапазон (10..32px)
    $s = preg_replace_callback('#\[size=([0-9]{1,3})\](.*?)\[/size\]#isu', function($m){
        $px = max(10, min(32, (int)$m[1]));
        return '<span style="font-size:'.$px.'px">'.$m[2].'</span>';
    }, $s);

    // Прочие BB одним проходом
    $s = preg_replace($bb_find, $bb_repl, $s);

    // Цитаты/код-блоки
    if (preg_match('#\[quote\](.*?)\[/quote\]#si', $s))        $s = encode_quote($s);
    if (preg_match('#\[quote=(.+?)\](.*?)\[/quote\]#si', $s))  $s = encode_quote_from($s);
    if (preg_match('#\[code\](.*?)\[/code\]#si', $s))          $s = encode_code($s);
    if (preg_match('#\[php\](.*?)\[/php\]#si', $s))            $s = encode_php($s);

    // Спойлеры (до 100 на сообщение)
    $limit = 0;
    while ($limit < 100 && preg_match('/\[spoiler=\s*((?:.|\s)+?)\s*\]((?:.|\s)+?)\[\/spoiler\]/iu', $s)) {
        $id = 'sp'.(++$nummatch);
        $s  = preg_replace('/\[spoiler=\s*((?:.|\s)+?)\s*\]((?:.|\s)+?)\[\/spoiler\]/iu',
            "<div class='spoiler' style='border:1px solid #E0E0E0;padding:3px'>
               <div class='clickable' style='padding-bottom:3px' onclick=\"show_hide('{$id}')\" title='Показать/Скрыть спойлер'>
                 <img id='pic{$id}' src='pic/plus.gif' alt='+'> \\1
               </div>
               <div id='{$id}' style='display:none;border:1px dashed #E0E0E0;padding:2px'>\\2</div>
             </div>", $s, 1);
        $limit++;
    }
    while ($limit < 100 && preg_match('/\[spoiler\]\s*((?:.|\s)+?)\s*\[\/spoiler\]\s*/iu', $s)) {
        $id = 'sp'.(++$nummatch);
        $s  = preg_replace('/\[spoiler\]\s*((?:.|\s)+?)\s*\[\/spoiler\]\s*/iu',
            "<div class='spoiler' style='border:1px solid #E0E0E0;padding:3px'>
               <div class='clickable' style='padding-bottom:3px' onclick=\"show_hide('{$id}')\" title='Показать/Скрыть спойлер'>
                 <img id='pic{$id}' src='pic/plus.gif' alt='+'> Скрытый текст
               </div>
               <div id='{$id}' style='display:none;border:1px dashed #E0E0E0;padding:2px'>\\1</div>
             </div>", $s, 1);
        $limit++;
    }

    // Автоссылки (если есть ваш helper)
    if (function_exists('format_urls')) {
        $s = format_urls($s);
    }

    // Перевод \n → <br> после основных замен
    $s = nl2br($s, false);

    /* ===== смайлы только в текстовых узлах ===== */
    $smileyMap = [];
    foreach ([$smilies, $privatesmilies] as $set) {
        foreach ($set as $code => $file) {
            if ($code === '' || $file === '') continue;
            $smileyMap[$code] =
                '<img class="smiley" src="/pic/smilies/' . rawurlencode($file) . '" alt="' .
                htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        }
    }
    if ($smileyMap) {
        $parts = preg_split('/(<[^>]+>)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts !== false) {
            $out = '';
            foreach ($parts as $part) {
                if ($part === '') continue;
                $out .= ($part[0] === '<') ? $part : strtr($part, $smileyMap);
            }
            $s = $out;
        } else {
            $s = strtr($s, $smileyMap); // fallback
        }
    }

    return function_exists('check_images') ? check_images($s) : $s;
}


// Возвращает класс пользователя или null, если $CURUSER не определён
function get_user_class(): ?int {
    global $CURUSER;

    // Проверка: массив определён и содержит нужный ключ
    return isset($CURUSER['class']) ? (int)$CURUSER['class'] : null;
}


function get_user_class_name($class) {
  global $tracker_lang;
  switch ($class) {
    case UC_USER: return $tracker_lang['class_user'];

    case UC_POWER_USER: return $tracker_lang['class_power_user'];

    case UC_VIP: return $tracker_lang['class_vip'];

    case UC_UPLOADER: return $tracker_lang['class_uploader'];

    case UC_MODERATOR: return $tracker_lang['class_moderator'];

    case UC_ADMINISTRATOR: return $tracker_lang['class_administrator'];

    case UC_SYSOP: return $tracker_lang['class_sysop'];
  }
  return "";
}

function is_valid_user_class($class) {
  return is_numeric($class) && floor($class) == $class && $class >= UC_USER && $class <= UC_SYSOP;
}

//----------------------------------
//---- Security function v0.1 by xam
//----------------------------------
function int_check($value,$stdhead = false, $stdfood = true, $die = true, $log = true) {
	global $CURUSER;
	$msg = "Invalid ID Attempt: Username: ".$CURUSER["username"]." - UserID: ".$CURUSER["id"]." - UserIP : ".getip();
	if ( is_array($value) ) {
        foreach ($value as $val) int_check ($val);
    } else {
	    if (!is_valid_id($value)) {
		    if ($stdhead) {
			    if ($log)
		    		write_log($msg);
		    	stderr("ERROR","Invalid ID! For security reason, we have been logged this action.");
	    }else {
			    Print ("<h2>Error</h2><table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text>");
				Print ("Invalid ID! For security reason, we have been logged this action.</td></tr></table>");
				if ($log)
					write_log($msg);
	    }
			
		    if ($stdfood)
		    	stdfoot();
		    if ($die)
		    	die;
	    }
	    else
	    	return true;
    }
}
//----------------------------------
//---- Security function v0.1 by xam
//----------------------------------

function is_valid_id($id) {
  return is_numeric($id) && ($id > 0) && (floor($id) == $id);
}

function sql_timestamp_to_unix_timestamp($s) {
  return mktime(substr($s, 11, 2), substr($s, 14, 2), substr($s, 17, 2), substr($s, 5, 2), substr($s, 8, 2), substr($s, 0, 4));
}

  function get_ratio_color($ratio) {
    if ($ratio < 0.1) return "#ff0000";
    if ($ratio < 0.2) return "#ee0000";
    if ($ratio < 0.3) return "#dd0000";
    if ($ratio < 0.4) return "#cc0000";
    if ($ratio < 0.5) return "#bb0000";
    if ($ratio < 0.6) return "#aa0000";
    if ($ratio < 0.7) return "#990000";
    if ($ratio < 0.8) return "#880000";
    if ($ratio < 0.9) return "#770000";
    if ($ratio < 1) return "#660000";
    return "#000000";
  }

  function get_slr_color($ratio) {
    if ($ratio < 0.025) return "#ff0000";
    if ($ratio < 0.05) return "#ee0000";
    if ($ratio < 0.075) return "#dd0000";
    if ($ratio < 0.1) return "#cc0000";
    if ($ratio < 0.125) return "#bb0000";
    if ($ratio < 0.15) return "#aa0000";
    if ($ratio < 0.175) return "#990000";
    if ($ratio < 0.2) return "#880000";
    if ($ratio < 0.225) return "#770000";
    if ($ratio < 0.25) return "#660000";
    if ($ratio < 0.275) return "#550000";
    if ($ratio < 0.3) return "#440000";
    if ($ratio < 0.325) return "#330000";
    if ($ratio < 0.35) return "#220000";
    if ($ratio < 0.375) return "#110000";
    return "#000000";
  }

function write_log($text, $color = "transparent", $type = "tracker") {
  $type = sqlesc($type);
  $color = sqlesc($color);
  $text = sqlesc($text);
  $added = sqlesc(get_date_time());
  sql_query("INSERT INTO sitelog (added, color, txt, type) VALUES($added, $color, $text, $type)");
}



function get_elapsed_time($ts) {
  $mins = floor((time() - $ts) / 60);
  $hours = floor($mins / 60);
  $mins -= $hours * 60;
  $days = floor($hours / 24);
  $hours -= $days * 24;
  $weeks = floor($days / 7);
  $days -= $weeks * 7;
  $t = "";
  if ($weeks > 0)
    return "$weeks недел" . ($weeks > 1 ? "и" : "я");
  if ($days > 0)
    return "$days д" . ($days > 1 ? "ней" : "ень");
  if ($hours > 0)
    return "$hours час" . ($hours > 1 ? "ов" : "");
  if ($mins > 0)
    return "$mins минут" . ($mins > 1 ? "" : "а");
  return "< 1 минуты";
}

function decode_unicode_url(string $str): string {
    $res = '';
    $length = strlen($str);
    $i = 0;

    while ($i < $length) {
        // Если %uXXXX — юникод
        if ($i + 5 < $length && $str[$i] === '%' && $str[$i + 1] === 'u') {
            $hex = substr($str, $i + 2, 4);
            $code = hexdec($hex);
            $i += 6;

            // Преобразуем Unicode в UTF-8
            if ($code < 0x80) {
                $res .= chr($code);
            } elseif ($code < 0x800) {
                $res .= chr(0xC0 | ($code >> 6));
                $res .= chr(0x80 | ($code & 0x3F));
            } else {
                $res .= chr(0xE0 | ($code >> 12));
                $res .= chr(0x80 | (($code >> 6) & 0x3F));
                $res .= chr(0x80 | ($code & 0x3F));
            }
        }
        // Если обычное %XX
        elseif ($str[$i] === '%' && $i + 2 < $length && ctype_xdigit($str[$i + 1] . $str[$i + 2])) {
            $res .= chr(hexdec(substr($str, $i + 1, 2)));
            $i += 3;
        }
        // Просто символ
        else {
            $res .= $str[$i];
            $i++;
        }
    }

    return $res;
}


function convert_text(string $s): string
{
    if ($s === '') {
        return '';
    }

    // --- Быстрые пути через системные расширения ---
    // iconv — обычно самый надёжный
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'CP1251//TRANSLIT//IGNORE', $s);
        if ($r !== false) {
            return $r;
        }
    }
    // mbstring — второй вариант
    if (function_exists('mb_convert_encoding')) {
        try {
            return mb_convert_encoding($s, 'CP1251', 'UTF-8');
        } catch (\Throwable $e) {
            // игнор — упадём в ручной фолбэк
        }
    }

    // --- Ручной фолбэк только для стандартной кириллицы ---
    // Идея: заменить пары байтов UTF-8, соответствующие кириллице, на однобайтовый CP1251.
    // Диапазоны:
    //   U+0410..U+044F (А..я)  → 0xC0..0xFF  (смещение -0x350 / -848)
    //   U+0401 (Ё)             → 0xA8
    //   U+0451 (ё)             → 0xB8
    //
    // Матчем только двухбайтовые последовательности [\xD0-\xD1][\x80-\xBF] — это кириллица в UTF-8.
    $out = preg_replace_callback(
        '/([\xD0-\xD1])([\x80-\xBF])/',
        static function (array $m): string {
            $b1 = ord($m[1]);           // 0xD0 или 0xD1
            $b2 = ord($m[2]);           // 0x80..0xBF
            $codepoint = (($b1 & 0x1F) << 6) | ($b2 & 0x3F);

            // Быстрые спец-случаи Ё/ё
            if ($codepoint === 0x0401) { // Ё
                return chr(0xA8);
            }
            if ($codepoint === 0x0451) { // ё
                return chr(0xB8);
            }

            // Базовый диапазон А..я
            if ($codepoint >= 0x0410 && $codepoint <= 0x044F) {
                return chr($codepoint - 0x350); // 0x0410→0xC0, …, 0x044F→0xFF
            }

            // Если это всё же не стандартная кириллица — вернём исходную пару байтов как есть,
            // чтобы не «ломать» текст (это безопаснее, чем вставлять HTML-сущности).
            return $m[0];
        },
        $s
    );

    // Остальные (непойманные) байты копируются как есть — поведение максимально близко к «без потерь».
    return $out;
} 




/**
 * Рендер BBCode-редактора (совместимо с PHP 8.1)
 *
 * @param string $form    — имя HTML-формы (form.name), в которой находится textarea
 * @param string $name    — name/field textarea
 * @param string $content — начальное содержимое
 */
function textbbcode2(string $form, string $name, string $content = ''): void
{
    // Безопасные значения и удобные флаги
    $scriptFile = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptBase = basename($scriptFile);

    // Кол-во строк по месту использования
    if (preg_match('/upload/i', $scriptFile)) {
        $col = 18;
    } elseif (preg_match('/edit/i', $scriptFile)) {
        $col = 38;
    } else {
        $col = 11;
    }

    // Атрибуты disabled для пары кнопок
    $isEditOrUploadNext = ($scriptBase === 'edit.php' || $scriptBase === 'uploadnext.php');

    // Кнопка «Цитировать выделение» отключена на edit.php и uploadnext.php
    $disabQuoteSelected = $isEditOrUploadNext ? ' disabled="disabled"' : '';

    // Кнопка «Скрытый» включена только на edit.php и uploadnext.php
    $disabHidden = $isEditOrUploadNext ? '' : ' disabled="disabled"';

    // Экранируем значения для подстановки в HTML
    $formEsc   = htmlspecialchars($form, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $nameEsc   = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $contentEsc= htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // NB: используем обычные теги <?php/<?= (<?= всегда включён с PHP 5.4+)
    ?>
    <script type="text/javascript" src="js/bbcode2.js"></script>

    <style>
      .editbutton { cursor: pointer; padding: 2px 1px 0 5px; }
      /* ресайзер */
      div.grippie {
        background:#EEE url("/pic/grippie.png") no-repeat center 2px;
        border: 0 1px 1px 1px solid #DDD;
        cursor:s-resize;
        height:9px;
        overflow:hidden;
      }
      /* мелкий фикс таблицы */
      table.bbcode-wrap { margin: 0 auto; }
    </style>

    <table cellpadding="0" cellspacing="0" class="bbcode-wrap">
      <tr>
        <td class="b">
          <div>
            <div style="text-align:center">
              <select name="fontFace" class="editbutton">
                <option style="font-family: Verdana" value="-1" selected="selected">Шрифт:</option>
                <option style="font-family: Courier" value="Courier">&nbsp;Courier</option>
                <option style="font-family: Courier New" value="Courier New">&nbsp;Courier New</option>
                <option style="font-family: monospace" value="monospace">&nbsp;monospace</option>
                <option style="font-family: Fixedsys" value="Fixedsys">&nbsp;Fixedsys</option>
                <option style="font-family: Arial" value="Arial">&nbsp;Arial</option>
                <option style="font-family: Comic Sans MS" value="Comic Sans MS">&nbsp;Comic Sans</option>
                <option style="font-family: Georgia" value="Georgia">&nbsp;Georgia</option>
                <option style="font-family: Tahoma" value="Tahoma">&nbsp;Tahoma</option>
                <option style="font-family: Times New Roman" value="Times New Roman">&nbsp;Times</option>
                <option style="font-family: serif" value="serif">&nbsp;serif</option>
                <option style="font-family: sans-serif" value="sans-serif">&nbsp;sans-serif</option>
                <option style="font-family: cursive" value="cursive">&nbsp;cursive</option>
                <option style="font-family: fantasy" value="fantasy">&nbsp;fantasy</option>
                <option style="font-family: Book Antiqua" value="Book Antiqua">&nbsp;Antiqua</option>
                <option style="font-family: Century Gothic" value="Century Gothic">&nbsp;Century Gothic</option>
                <option style="font-family: Franklin Gothic Medium" value="Franklin Gothic Medium">&nbsp;Franklin</option>
                <option style="font-family: Garamond" value="Garamond">&nbsp;Garamond</option>
                <option style="font-family: Impact" value="Impact">&nbsp;Impact</option>
                <option style="font-family: Lucida Console" value="Lucida Console">&nbsp;Lucida</option>
                <option style="font-family: Palatino Linotype" value="Palatino Linotype">&nbsp;Palatino</option>
                <option style="font-family: Trebuchet MS" value="Trebuchet MS">&nbsp;Trebuchet</option>
              </select>
              &nbsp;
              <select name="codeColor" class="editbutton">
                <option style="color:black; background:#fff;" value="black" selected="selected">Цвет шрифта:</option>
                <option style="color:black" value="Black">&nbsp;Черный</option>
                <option style="color:sienna" value="Sienna">&nbsp;Охра</option>
                <option style="color:beige" value="Beige">&nbsp;Бежевый</option>
                <option style="color:darkolivegreen" value="DarkOliveGreen">&nbsp;Олив. Зеленый</option>
                <option style="color:darkgreen" value="DarkGreen">&nbsp;Т. Зеленый</option>
                <option style="color:cornflowerblue" value="Cornflower">&nbsp;Васильковый</option>
                <option style="color:darkslateblue" value="DarkSlateBlue">&nbsp;Гриф.-синий</option>
                <option style="color:navy" value="Navy">&nbsp;Темно-синий</option>
                <option style="color:midnightblue" value="MidnightBlue">&nbsp;Полу.-синий</option>
                <option style="color:indigo" value="Indigo">&nbsp;Индиго</option>
                <option style="color:darkslategray" value="DarkSlateGray">&nbsp;Синевато-серый</option>
                <option style="color:darkred" value="DarkRed">&nbsp;Т. Красный</option>
                <option style="color:darkorange" value="DarkOrange">&nbsp;Т. Оранжевый</option>
                <option style="color:olive" value="Olive">&nbsp;Оливковый</option>
                <option style="color:green" value="Green">&nbsp;Зеленый</option>
                <option style="color:darkcyan" value="DarkCyan">&nbsp;Темный циан</option>
                <option style="color:cadetblue" value="CadetBlue">&nbsp;Серо-синий</option>
                <option style="color:aquamarine" value="Aquamarine">&nbsp;Аквамарин</option>
                <option style="color:teal" value="Teal">&nbsp;Морской волны</option>
                <option style="color:blue" value="Blue">&nbsp;Голубой</option>
                <option style="color:slategray" value="SlateGray">&nbsp;Синевато-серый</option>
                <option style="color:dimgray" value="DimGray">&nbsp;Тускло-серый</option>
                <option style="color:red" value="Red">&nbsp;Красный</option>
                <option style="color:chocolate" value="Chocolate">&nbsp;Шоколадный</option>
                <option style="color:firebrick" value="Firebrick">&nbsp;Кирпичный</option>
                <option style="color:saddlebrown" value="SaddleBrown">&nbsp;Кож.коричневый</option>
                <option style="color:yellowgreen" value="YellowGreen">&nbsp;Желт-Зеленый</option>
                <option style="color:seagreen" value="SeaGreen">&nbsp;Океан. Зеленый</option>
                <option style="color:mediumturquoise" value="MediumTurquoise">&nbsp;Бирюзовый</option>
                <option style="color:royalblue" value="RoyalBlue">&nbsp;Голубой Корол.</option>
                <option style="color:purple" value="Purple">&nbsp;Липовый</option>
                <option style="color:gray" value="Gray">&nbsp;Серый</option>
                <option style="color:magenta" value="Magenta">&nbsp;Пурпурный</option>
                <option style="color:orange" value="Orange">&nbsp;Оранжевый</option>
                <option style="color:yellow" value="Yellow">&nbsp;Желтый</option>
                <option style="color:gold" value="Gold">&nbsp;Золотой</option>
                <option style="color:goldenrod" value="Goldenrod">&nbsp;Золотистый</option>
                <option style="color:lime" value="Lime">&nbsp;Лимонный</option>
                <option style="color:cyan" value="Cyan">&nbsp;Зел.-голубой</option>
                <option style="color:deepskyblue" value="DeepSkyBlue">&nbsp;Т.Неб.-голубой</option>
                <option style="color:darkorchid" value="DarkOrchid">&nbsp;Орхидея</option>
                <option style="color:silver" value="Silver">&nbsp;Серебристый</option>
                <option style="color:pink" value="Pink">&nbsp;Розовый</option>
                <option style="color:wheat" value="Wheat">&nbsp;Wheat</option>
                <option style="color:lemonchiffon" value="LemonChiffon">&nbsp;Лимонный</option>
                <option style="color:palegreen" value="PaleGreen">&nbsp;Бл. Зеленый</option>
                <option style="color:paleturquoise" value="PaleTurquoise">&nbsp;Бл. Бирюзовый</option>
                <option style="color:lightblue" value="LightBlue">&nbsp;Св. Голубой</option>
                <option style="color:plum" value="Plum">&nbsp;Св. Розовый</option>
                <option style="color:white" value="White">&nbsp;Белый</option>
              </select>
              &nbsp;

              <select name="codeSize" class="editbutton">
                <option value="12" selected="selected">Размер шрифта:</option>
                <option value="9" class="em">Маленький</option>
                <option value="10">&nbsp;size=10</option>
                <option value="11">&nbsp;size=11</option>
                <option value="12" class="em" disabled="disabled">Обычный</option>
                <option value="14">&nbsp;size=14</option>
                <option value="16">&nbsp;size=16</option>
                <option value="18" class="em">Большой</option>
                <option value="20">&nbsp;size=20</option>
                <option value="22">&nbsp;size=22</option>
                <option value="24" class="em">Огромный</option>
              </select>
              &nbsp;

              <select name="codeAlign" class="editbutton">
                <option value="" selected="selected">Выравнивание:</option>
                <option value="left">&nbsp;По левому краю</option>
                <option value="right">&nbsp;По правому краю</option>
                <option value="center">&nbsp;По центру</option>
                <option value="justify">&nbsp;По ширине</option>
              </select>
            </div>

            <div style="text-align:center; margin-top:6px;">
              <input class="btn" type="button" value="&#8212;" name="codeHR" title="Горизонтальная линия (Ctrl+8)" style="font-weight:bold; width:26px;" />
              <input class="btn" type="button" value="&para;" name="codeBR" title="Новая строка" style="width:26px;" />
              <input class="btn" type="button" value="Спойлер" name="codeSpoiler" title="Спойлер (Ctrl+S)" style="width:70px;" />

              <input class="btn" type="button" value=" B " name="codeB" title="Жирный текст (Ctrl+B)" style="font-weight:bold; width:30px;" />
              <input class="btn" type="button" value=" i " name="codeI" title="Наклонный текст (Ctrl+I)" style="width:30px; font-style:italic;" />
              <input class="btn" type="button" value=" u " name="codeU" title="Подчеркнутый текст (Ctrl+U)" style="width:30px; text-decoration:underline;" />
              <input class="btn" type="button" value=" s " name="codeS" title="Перечеркнутый текст" style="width:30px; text-decoration:line-through;" />

              <input class="btn" type="button" value=" BB " name="codeBB" title="Чистый bb код (Неотформатированный) (Ctrl+N)" style="font-weight:bold; width:30px;" />
              <input class="btn" type="button" value=" PRE " name="codePRE" title="Преформатный текст (Ctrl+P)" style="width:40px;" />
              <input class="btn" type="button" value=" HTEXT " name="codeHT" title="Скрытие текста при наведение показ (Ctrl+H)" style="width:60px;" />
              <input class="btn" type="button" value=" Marquee " name="codeMG" title="Бегающая строка (Ctrl+M)" style="width:70px;" />
              <input class="btn" type="button" value="Цитата" name="codeQuote" title="Цитирование (Ctrl+Q)" style="width:60px;" />
              <input class="btn" type="button" value="Img" name="codeImg" title="Картинка (Ctrl+R)" style="width:40px;" />

              <input class="btn" type="button"<?= $disabQuoteSelected; ?> value="Цитировать выделение" name="quoteselected" title="Цитировать выделенный текст" style="width:165px;"
                     onmouseout="bbcode.refreshSelection(false);" onmouseover="bbcode.refreshSelection(true);" onclick="bbcode.onclickQuoteSel();" />
              &nbsp;

              <input class="btn" type="button" value="Скрытый"<?= $disabHidden; ?> name="codeHIDE" title="Скрытый Текст, пока не прокомментируешь раздачу" style="width:70px;" />
              <input class="btn" type="button" value="URL" name="codeUr" title="URL ссылка" style="width:40px; text-decoration:underline;" />
              <input class="btn" type="button" value="PHP" name="codeCode" title="PHP код (Ctrl+K)" style="width:46px;" />
              <input class="btn" type="button" value="Flash" name="codeFlash" title="Flash анимания (Ctrl+F)" style="width:50px;" />
              <input class="btn" type="button" value="&#8226;" name="codeOpt" title="Маркированый список (Ctrl+0)" style="width:30px;" />
              <input class="btn" type="button" value="Рамка I" name="codeLG1" title="Рамка вокруг текста (Ctrl+1)" style="width:65px;" />
              <input class="btn" type="button" value="Рамка II" name="codeLG2" title="Рамка вокруг текста с цитатой (Ctrl+2)" style="width:65px;" />
              <input class="btn" type="button" value="highlight" name="codeHIG" title="Подсветка синтаксиса" style="width:60px;" />

              <input class="btn" type="button" value=" Смайлы " name="Smailes" title="Смайлы (окно всех смайлов)" style="width:60px;"
                     onclick="window.open('moresmiles.php?form=<?= $formEsc; ?>&text=<?= $nameEsc; ?>','',
                              'height=500,width=450,resizable=no,scrollbars=yes'); return false;" />
            </div>

            <?php if ($scriptBase !== 'forums.php'): ?>
              <script type="text/javascript">
                (function($){
                  var textarea, staticOffset, iLastMousePos = 0, iMin = 32;
                  $.fn.TextAreaResizer = function(){
                    return this.each(function(){
                      textarea = $(this).addClass('processed'), staticOffset = null;
                      $(this).wrap('<div class="resizable-textarea"><span></span></div>')
                        .parent()
                        .append($('<div class="grippie"></div>').on("mousedown",{el:this}, startDrag));
                      var grippie = $('div.grippie', $(this).parent())[0];
                      if (grippie) { grippie.style.marginRight = (grippie.offsetWidth - $(this)[0].offsetWidth) + 'px'; }
                    });
                  };
                  function startDrag(e){
                    textarea = $(e.data.el);
                    textarea.blur();
                    iLastMousePos = mousePosition(e).y;
                    staticOffset = textarea.height() - iLastMousePos;
                    textarea.css('opacity', 0.25);
                    $(document).on('mousemove', performDrag).on('mouseup', endDrag);
                    return false;
                  }
                  function performDrag(e){
                    var iThisMousePos = mousePosition(e).y;
                    var iMousePos = staticOffset + iThisMousePos;
                    if (iLastMousePos >= iThisMousePos) { iMousePos -= 5; }
                    iLastMousePos = iThisMousePos;
                    iMousePos = Math.max(iMin, iMousePos);
                    textarea.height(iMousePos + 'px');
                    if (iMousePos < iMin) endDrag(e);
                    return false;
                  }
                  function endDrag(){
                    $(document).off('mousemove', performDrag).off('mouseup', endDrag);
                    textarea.css('opacity', 1).focus();
                    textarea = null; staticOffset = null; iLastMousePos = 0;
                  }
                  function mousePosition(e){
                    return {
                      x: e.clientX + document.documentElement.scrollLeft,
                      y: e.clientY + document.documentElement.scrollTop
                    };
                  }
                  $(function(){ $('textarea.resizable:not(.processed)').TextAreaResizer(); });
                })(jQuery);
              </script>
            <?php endif; ?>

            <textarea class="resizable" id="area" name="<?= $nameEsc; ?>" style="width:100%;"
                      rows="<?= (int)$col; ?>"
                      onfocus="storeCaret(this);" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);"><?= $contentEsc; ?></textarea>

            <script type="text/javascript">
              var bbcode = new BBCode(document['<?= $formEsc; ?>']['<?= $nameEsc; ?>']);
              var ctrl = "ctrl";
              bbcode.addTag("codeB", "b", null, "B", ctrl);
              bbcode.addTag("codeBB", "bb", null, "N", ctrl);
              bbcode.addTag("codePRE", "pre", null, "P", ctrl);
              bbcode.addTag("codeHT", "hideback", null, "H", ctrl);
              bbcode.addTag("codeMG", "marquee", null, "M", ctrl);
              bbcode.addTag("codeLG1", "legend", null, "1", ctrl);
              bbcode.addTag("codeLG2", function(e){ var v=e.value; e.selectedIndex=0; return "legend=Заголовок"; }, "/legend", "2", ctrl);
              bbcode.addTag("codeHIDE", "hide", null, "", ctrl);
              bbcode.addTag("codeHIG", "highlight", null, "", ctrl);
              bbcode.addTag("codeI", "i", null, "I", ctrl);
              bbcode.addTag("codeU", "u", null, "U", ctrl);
              bbcode.addTag("codeS", "s", null, "", ctrl);
              bbcode.addTag("codeQuote", "quote", null, "Q", ctrl);
              bbcode.addTag("codeImg", "img", null, "R", ctrl);
              bbcode.addTag("codeUr", "url=введите ссылку", "/url", "", ctrl);
              bbcode.addTag("codeCode", "php", null, "K", ctrl);
              bbcode.addTag("codeFlash", "flash", null, "F", ctrl);
              bbcode.addTag("codeOpt", "li", "", "0", ctrl);
              bbcode.addTag("codeHR","hr", "", "8", ctrl);
              bbcode.addTag("codeBR","br", "", "", ctrl);
              bbcode.addTag("codeSpoiler", "spoiler", null, "S", ctrl);
              bbcode.addTag("fontFace", function(e){ var v=e.value; e.selectedIndex=0; return "font="+v+""; }, "/font");
              bbcode.addTag("codeColor", function(e){ var v=e.value; e.selectedIndex=0; return "color="+v; }, "/color");
              bbcode.addTag("codeSize", function(e){ var v=e.value; e.selectedIndex=0; return "size="+v; }, "/size");
              bbcode.addTag("codeAlign", function(e){ var v=e.value; e.selectedIndex=0; return "align="+v; }, "/align");
            </script>
          </div>
        </td>
      </tr>
    </table>
    <?php
}


?>