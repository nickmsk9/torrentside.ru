<?php

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

require_once($rootpath . 'include/functions_global.php');

/**
 * Показывает посетителей страницы. Быстро и без лишних запросов.
 *
 * @param string      $id            добавка к query (как у вас)
 * @param int         $timeoutMin    тайм-аут онлайнов в минутах (по умолчанию 15)
 * @param bool        $notAdd        не добавлять/обновлять себя (только прочитать)
 * @param string      $urlOverride   явный URL (если нужен контроль)
 * @param int         $touchGapSec   троттлинг обновлений, сек (напр. 30)
 */
function visitorsHistorie(
    string $id = '',
    int $timeoutMin = 15,
    bool $notAdd = false,
    string $urlOverride = '',
    int $touchGapSec = 30
): bool {
    global $CURUSER, $mysqli, $memcached;

    if (!isset($CURUSER['id']) || (int)$CURUSER['id'] <= 0) {
        // Гостей не пишем — сразу читаем список (можно вообще отключить для гостей)
        $notAdd = true;
    }

    // ---------- Нормализация URL ----------
    $path = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $qs   = ($id !== '') ? $id : (string)($_SERVER['QUERY_STRING'] ?? '');
    $url  = $urlOverride !== '' ? $urlOverride : ($path . ($qs !== '' ? ('?' . $qs) : ''));
    // Храним «как есть» (экранируем только в HTML-выводе)
    if (strlen($url) > 200) {
        $url = substr($url, 0, 200);
    }

    $timeoutSec = max(60, $timeoutMin * 60);
    $now        = time();
    $uid        = (int)($CURUSER['id'] ?? 0);

    // Хэш URL для компактных ключей кэша
    $urlHash = md5($url, true);                // бинарный
    $urlHashHex = bin2hex($urlHash);           // для кэш-ключа
    $cacheKey = "vh:list:{$urlHashHex}:{$timeoutSec}";

    // ---------- Запись/обновление себя (upsert с троттлингом) ----------
    if (!$notAdd && $uid > 0) {
        $_SESSION['vh_last_touch'] ??= [];
        $last = (int)($_SESSION['vh_last_touch'][$url] ?? 0);

        if ($now - $last >= $touchGapSec) {
            // Вариант со столбцом url_hash (лучший):
            //   INSERT ... ON DUP KEY UPDATE time = IF(time < NOW() - INTERVAL ? SECOND, NOW(), time), uname=VALUES(uname)
            // Вариант без url_hash (оставляем url в уникальном ключе):
            $stmt = $mysqli->prepare(
                "INSERT INTO visitor_history (url, uid, uname, time)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   uname = VALUES(uname),
                   time  = IF(time < (NOW() - INTERVAL ? SECOND), NOW(), time)"
            );
            $uname = (string)($CURUSER['username'] ?? '');
            $stmt->bind_param('sisi', $url, $uid, $uname, $touchGapSec);
            $stmt->execute();
            $stmt->close();

            // быстрая чистка по текущему URL (просрочки)
            $stmt = $mysqli->prepare(
                "DELETE FROM visitor_history
                 WHERE url = ? AND time < (NOW() - INTERVAL ? SECOND)"
            );
            $stmt->bind_param('si', $url, $timeoutSec);
            $stmt->execute();
            $stmt->close();

            $_SESSION['vh_last_touch'][$url] = $now;

            // инвалидация короткого кэша
            if ($memcached instanceof Memcached) {
                $memcached->delete($cacheKey);
            }

            // редкая глобальная уборка (1% шанс) — чтобы таблица не пухла
            if (mt_rand(1, 100) === 1) {
                $mysqli->query(
                    "DELETE FROM visitor_history WHERE time < (NOW() - INTERVAL 1 DAY)"
                );
            }
        }
    }

    // ---------- Чтение списка (с кэшем) ----------
    $list = false;
    if ($memcached instanceof Memcached) {
        $list = $memcached->get($cacheKey);
    }

    if ($list === false) {
        // Сначала отфильтровали по URL и тайм-ауту, только потом JOIN
        $stmt = $mysqli->prepare(
            "SELECT v.uid, v.uname, u.class
             FROM visitor_history AS v
             LEFT JOIN users AS u ON u.id = v.uid
             WHERE v.url = ? AND v.time >= (NOW() - INTERVAL ? SECOND)
             ORDER BY v.time DESC"
        );
        $stmt->bind_param('si', $url, $timeoutSec);
        $stmt->execute();
        $res = $stmt->get_result();

        $list = [];
        while ($row = $res->fetch_assoc()) {
            $vu  = (int)$row['uid'];
            $un  = (string)$row['uname'];
            $cls = $row['class'] ?? null;

            // Ссылку формируем безопасно при выводе; здесь — готовим уже «цветное имя»
            $html = "<a href='userdetails.php?id={$vu}'>" . get_user_class_color($cls, $un) . "</a>";
            $list[] = $html;
        }
        $stmt->close();

        if ($memcached instanceof Memcached) {
            // короткий TTL, чтобы не было «залипания»
            $memcached->set($cacheKey, $list, 15);
        }
    }

    // — Если нужно гарантированно показывать «себя» сразу,
    //   можно добавить в голову списка (опционально):
    if (!$notAdd && $uid > 0) {
        $selfHtml = "<a href='userdetails.php?id={$uid}'>" .
            get_user_class_color(($CURUSER['class'] ?? null), ($CURUSER['username'] ?? '')) .
            "</a>";
        // если вдруг мы уже есть в списке — не дублируем
        if (!in_array($selfHtml, $list, true)) {
            array_unshift($list, $selfHtml);
        }
    }

    // Экспорт в глобалы (как и было у вас)
    $GLOBALS['VISITORS']     = $list;
    $GLOBALS['VIS_URL']      = $url;
    $GLOBALS['VIS_TIMEOUT']  = (int)round($timeoutSec / 60);

    return true;
}

function visitorsList(string $tpl, array $visitors): string {
    return str_replace('[VISITORS]', implode(', ', $visitors), $tpl);
}

////////////////////////////////////////////////////////////////////////////////////

function nicetime(string|int|\DateTimeInterface $input, bool $withTime = false, ?\DateTimeZone $tz = null, string $locale = 'ru_RU'): string
{
    // --- 0) Кэш дефолтной таймзоны (экономит аллокации/системные вызовы) ---
    static $DEF_TZ = null, $DEF_TZ_NAME = null;
    if ($DEF_TZ === null) {
        $DEF_TZ_NAME = date_default_timezone_get();
        $DEF_TZ = new \DateTimeZone($DEF_TZ_NAME);
    }

    // --- 1) Нормализация входа (устойчиво к ошибкам) ---
    try {
        if ($input instanceof \DateTimeInterface) {
            $dt = \DateTimeImmutable::createFromInterface($input);
        } elseif (is_int($input)) {
            // таймстемпы всегда считаем как «UTC @ts» и потом переводим в нужный TZ
            $dt = (new \DateTimeImmutable('@' . $input))->setTimezone($tz ?? $DEF_TZ);
        } else {
            // если строка без TZ — трактуем в указанной/дефолтной TZ
            $dt = new \DateTimeImmutable($input, $tz ?? $DEF_TZ);
        }
    } catch (\Throwable) {
        // безопасный фолбэк: вернём исходную строку как есть
        return is_string($input) ? $input : '';
    }

    if ($tz) {
        $dt = $dt->setTimezone($tz);
    } else {
        // приводим к дефолтной для консистентности вывода
        $dt = $dt->setTimezone($DEF_TZ);
        $tz = $DEF_TZ;
    }

    // --- 2) Intl (корректные склонения, ru генитив у месяца с "d MMMM y") ---
    if (class_exists(\IntlDateFormatter::class)) {
        static $fmt = []; // ключ: "$locale|$tzName|$withTime"
        $tzName = $tz->getName();
        $key = $locale . '|' . $tzName . '|' . (int)$withTime;

        if (!isset($fmt[$key])) {
            $pattern = $withTime ? "d MMMM y 'в' HH:mm:ss" : "d MMMM y";
            $idf = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $tzName);
            $idf->setPattern($pattern);
            $fmt[$key] = $idf;
        }

        $out = $fmt[$key]->format($dt);
        if ($out !== false && $out !== '') {
            return $out;
        }
        // иначе — мягко падаем на фолбэк ниже
    }

    // --- 3) Фолбэк без intl (минимум аллокаций) ---
    static $SEARCH = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    static $REPLACE = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    $fmt = $withTime ? 'j F Y \в H:i:s' : 'j F Y';
    return str_replace($SEARCH, $REPLACE, $dt->format($fmt));
}


//////////////////////////////////////////////////////////////////////////////////////////////


function karma(int|float $karma): string
{
    $k = (int)$karma;

    $color = match (true) {
        $k < 0   => '#FF0000', // красный
        $k === 0 => '#000000', // чёрный
        $k < 10  => '#000080', // синий (1..9)
        default  => '#008000', // зелёный (>=10)
    };

    $label = ($k > 0 ? '+' : '') . (string)$k;
    $labelEsc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Инлайн-стили оставил для совместимости. Лучше вынести в CSS-класс.
    return '<span style="color:' . $color . ';vertical-align:top;font-size:13px;"><b>' . $labelEsc . '</b></span>';
}

function pager($rpp, $count, $href, $opts = array())
{
    $rpp    = max(1, (int)$rpp);
    $count  = max(0, (int)$count);
    $pages  = (int)ceil($count / $rpp);
    $pagedefault = !empty($opts['lastpagedefault']) ? max((int)floor(($count - 1) / $rpp), 0) : 0;
    $page   = isset($_GET['page']) ? max(0, (int)$_GET['page']) : $pagedefault;
    $page   = min($page, max(0, $pages - 1)); // защита от выхода за пределы
    $mp     = max(0, $pages - 1);

    $prev = $next = '';

    // «Назад»
    if ($page >= 1) {
        $prev = '<td><a class="bubble-page" href="' . $href . 'page=' . ($page - 1) . '" title="Назад">«</a></td>' . "\n";
    }

    // «Вперёд»
    if ($page < $mp && $mp >= 0) {
        $next = '<td><a class="bubble-page" href="' . $href . 'page=' . ($page + 1) . '" title="Вперёд">»</a></td>' . "\n";
    }

    if ($count > 0 && $pages > 0) {
        $pagerarr     = [];
        $dotspace     = 3;
        $dotend       = $pages - $dotspace;
        $curdotend    = $page - $dotspace;
        $curdotstart  = $page + $dotspace;
        $dotted       = false;

        for ($i = 0; $i < $pages; $i++) {
            if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
                if (!$dotted) {
                    $pagerarr[] = "<td><span class=\"bubble-dot\">...</span></td>\n";
                    $dotted = true;
                }
                continue;
            }
            $dotted = false;

            $start = $i * $rpp + 1;
            $end   = min($start + $rpp - 1, $count);
            $text  = $i + 1;

            if ($i !== $page) {
                $pagerarr[] = '<td><a class="bubble-page" href="' . $href . 'page=' . $i . '" title="' . $start . ' - ' . $end . '">' . $text . '</a></td>' . "\n";
            } else {
                $pagerarr[] = '<td><span class="bubble-current">' . $text . '</span></td>' . "\n";
            }
        }

        $pagerstr   = implode('', $pagerarr);
        $table      = '<table class="pager-bubble" role="presentation"><tr>' . $prev . $pagerstr . $next . '</tr></table>' . "\n";

        // ВАЖНО: одинаковые обёртки сверху и снизу — ломают коллапс margin и дают симметрию
        $pagertop    = '<div class="pager-wrap pager-wrap--top" role="navigation" aria-label="Навигация по страницам">' . $table . '</div>' . "\n";
        $pagerbottom = '<div class="pager-wrap pager-wrap--bottom" role="navigation" aria-label="Навигация по страницам">'
                     . '<div class="bubble-info">Всего ' . $count . ' на ' . $pages . ' страницах по ' . $rpp . ' на каждой</div>'
                     . $table
                     . '</div>' . "\n";
    } else {
        // Нет страниц — ничего не рисуем
        $pagertop = $pagerbottom = '';
    }

    $start = $page * $rpp;
    return array($pagertop, $pagerbottom, "LIMIT $start,$rpp");
}



function pager2(int $rpp, int $count, string $href, string $postfix = '', array $opts = []): array {
    if ($rpp <= 0) $rpp = 20;

    $pages = (int)ceil($count / $rpp);
    $hasPages = $pages > 0;
    $pagedefault = (!empty($opts['lastpagedefault']) && $hasPages) ? max(0, $pages - 1) : 0;

    $page_get = $_GET['page'] ?? null;
    $page = (is_numeric($page_get) ? (int)$page_get : $pagedefault);
    if ($page < 0) $page = 0;
    if ($hasPages && $page > $pages - 1) $page = $pages - 1;
    if (!$hasPages) $page = 0;

    $start = $page * $rpp;
    $limit = "LIMIT $start, $rpp";

    if ($count <= 0) {
        $empty = '<nav class="pg-wrap pg-left pg-glass"><ul class="pg-list"><li><span class="pg-nav pg-disabled">—</span></li></ul></nav>';
        return [$empty, $empty, $limit];
    }

    $mk = function (int $p) use ($href, $postfix): string { return $href . 'page=' . $p . $postfix; };
    $on = function (int $p): string { return ' onclick="return pageswitcher(' . $p . ')"'; };
    $a  = function (string $label, string $url, string $cls = '', string $title = '', string $extra = ''): string {
        $t = $title !== '' ? ' title="'.htmlspecialchars($title, ENT_QUOTES).'"' : '';
        $c = $cls  !== '' ? ' class="'.$cls.'"' : '';
        return '<a href="'.htmlspecialchars($url, ENT_QUOTES).'"'.$c.$t.$extra.'>'.$label.'</a>';
    };
    $pill = function (int $p, int $page, int $rpp, int $count, callable $a, callable $mk, callable $on): string {
        $startN = $p * $rpp + 1;
        $endN   = min($startN + $rpp - 1, $count);
        $title  = $startN . '–' . $endN;
        if ($p === $page) return '<span class="pg-pill pg-current" aria-current="page">'.($p+1).'</span>';
        return $a((string)($p+1), $mk($p), 'pg-pill', $title, $on($p));
    };

    $mp = $pages - 1;
    $parts = [];

    // Prev
    $parts[] = ($page > 0)
        ? $a('Назад', $mk($page-1), 'pg-nav', 'Назад', $on($page-1))
        : '<span class="pg-nav pg-disabled">Назад</span>';

    // 1
    $parts[] = $pill(0, $page, $rpp, $count, $a, $mk, $on);

    // …
    if ($page > 2) $parts[] = '<span class="pg-ellipsis">…</span>';

    // k-1
    if ($page - 1 > 0 && $page - 1 < $mp) $parts[] = $pill($page-1, $page, $rpp, $count, $a, $mk, $on);

    // k
    if ($page !== 0 && $page !== $mp) $parts[] = $pill($page, $page, $rpp, $count, $a, $mk, $on);

    // k+1
    if ($page + 1 < $mp) $parts[] = $pill($page+1, $page, $rpp, $count, $a, $mk, $on);

    // …
    if ($page < $mp - 2) $parts[] = '<span class="pg-ellipsis">…</span>';

    // N
    if ($pages > 1) $parts[] = $pill($mp, $page, $rpp, $count, $a, $mk, $on);

    // Next
    $parts[] = ($page < $mp)
        ? $a('Вперёд', $mk($page+1), 'pg-nav', 'Вперёд', $on($page+1))
        : '<span class="pg-nav pg-disabled">Вперёд</span>';

    $bar = implode('', array_map(fn($x)=>'<li>'.$x.'</li>', $parts));
    $summary = '<span class="pg-summary">'.number_format($count).' • стр. '.number_format($page+1).' / '.number_format($pages).'</span>';

    // слева (top), внизу — слева стек + справа краткая сводка
    $pagertop =
        '<nav class="pg-wrap pg-left pg-glass" role="navigation" aria-label="Пагинация">'.
            '<ul class="pg-list">'.$bar.'</ul>'.
        '</nav>';

    $pagerbottom =
        '<nav class="pg-wrap pg-left pg-glass pg-bottom" role="navigation" aria-label="Пагинация">'.
            '<ul class="pg-list">'.$bar.'</ul>'.
            $summary.
        '</nav>';

    return [$pagertop, $pagerbottom, $limit];
}




function check_images(string $html): string {
    // --- настройки ---
    $MAX_NETWORK_CHECKS = 10;     // максимум реальных сетевых проверок за вызов
    $TTL_OK   = 21600;            // 6 часов кэшируем «живо»
    $TTL_BAD  = 7200;             // 2 часа кэшируем «битое»
    $CONNECT_TIMEOUT = 2;         // секунды
    $TOTAL_TIMEOUT   = 4;         // секунды
    $FOLLOW_REDIRECTS = 3;

    // --- инициализация Memcached (persistent pool) ---
    static $mc = null;
    if ($mc === null && class_exists('Memcached')) {
        $mc = new Memcached('ts_memc_pool');
        if (empty($mc->getServerList())) {
            $mc->addServer('127.0.0.1', 11211);
        }
    }

    // --- быстрый поиск <img ... src="..."> (один regex-проход) ---
    // захватываем целиком тег (0) и значение src (1)
    static $re_img = null;
    if ($re_img === null) {
        $re_img = '/<img\b[^>]*\bsrc\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^"\'>\s]+))[^>]*>/i';
    }
    if (!preg_match_all($re_img, $html, $m, PREG_SET_ORDER)) {
        return $html; // картинок нет — выходим быстро
    }

    // --- данные окружения ---
    $server_host = $_SERVER['HTTP_HOST'] ?? '';
    $replacements = [];        // тег => замена
    $checked = 0;              // счётчик сетевых проверок за этот вызов
    $seenUrls = [];            // дедупликация по URL

    foreach ($m as $match) {
        $fullTag = $match[0];
        $src     = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]);

        if ($src === '') continue;

        // нормализация protocol-relative //cdn...
        if (strpos($src, '//') === 0) {
            // если сайт под https — используем https
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
            $src = $scheme . $src;
        }

        // относительный путь или data:/blob: — считаем локальным, не проверяем
        $lower = strtolower($src);
        if (!preg_match('#^https?://#i', $src) || str_starts_with($lower, 'data:') || str_starts_with($lower, 'blob:')) {
            continue;
        }

        // внешний хост?
        $host = parse_url($src, PHP_URL_HOST);
        if ($host === null || $host === '' || ($server_host !== '' && stripos($host, $server_host) !== false)) {
            // наш домен / поддомен — не проверяем
            continue;
        }

        // дедупликация в пределах одного вызова
        if (isset($seenUrls[$src])) {
            $isOk = $seenUrls[$src];
        } else {
            // сначала кэш
            $cache_key = 'img_status_' . md5($src);
            $cached = null;
            if ($mc instanceof Memcached) {
                $cached = $mc->get($cache_key);
                if ($mc->getResultCode() === Memcached::RES_SUCCESS) {
                    $isOk = (bool)$cached;
                    $seenUrls[$src] = $isOk;
                } else {
                    $isOk = null; // нет в кэше
                }
            } else {
                $isOk = null; // нет Memcached — пойдём в сеть (с лимитом)
            }

            // сеть только если: нет в кэше и не превышен лимит
            if ($isOk === null && $checked < $MAX_NETWORK_CHECKS) {
                $isOk = _ts_head_ok($src, $CONNECT_TIMEOUT, $TOTAL_TIMEOUT, $FOLLOW_REDIRECTS);
                $seenUrls[$src] = $isOk;
                $checked++;

                // записываем в кэш
                if ($mc instanceof Memcached) {
                    $mc->set($cache_key, $isOk ? 1 : 0, $isOk ? $TTL_OK : $TTL_BAD);
                }
            }

            // если лимит исчерпан и нет кэша — пропускаем (ничего не меняем)
            if ($isOk === null) {
                continue;
            }
        }

        // Подмена только если битое
        if (!$isOk) {
            // делаем компактный placeholder
            $safeUrl = htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $replacements[$fullTag] = "<div style='color:#c00;font-size:11px'>[недоступное изображение: {$safeUrl}]</div>";
        }
    }

    // Массовая замена одним strtr/str_replace (в зависимости от числа замен)
    if ($replacements) {
        // strtr быстрее, но требует уникальности ключей — у нас они уникальны (целиком <img ...>)
        $html = strtr($html, $replacements);
    }

    return $html;
}

/**
 * Быстрая HEAD-проверка через cURL; фолбэк на get_headers().
 * Возвращает true, если HTTP-код 2xx/3xx.
 */
function _ts_head_ok(string $url, int $conn_to, int $tot_to, int $max_redirects): ?bool {
    // cURL предпочтительнее (таймауты, редиректы, SSL проверка)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) return null;

        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => $max_redirects,
            CURLOPT_CONNECTTIMEOUT => $conn_to,
            CURLOPT_TIMEOUT        => $tot_to,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'TS-ImageCheck/1.0',
        ]);
        @curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code > 0) {
            return ($code >= 200 && $code < 400);
        }
        // если код не получили — пойдём в фолбэк
    }

    // Фолбэк: get_headers с коротким таймаутом
    $context = stream_context_create([
        'http' => [
            'method'  => 'HEAD',
            'timeout' => $tot_to,
            'follow_location' => 0,
            'user_agent' => 'TS-ImageCheck/1.0',
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $hdrs = @get_headers($url, 1, $context);
    if ($hdrs === false || !isset($hdrs[0])) {
        return false;
    }

    // Мог прийти «HTTP/1.1 301 Moved...»
    $line = is_array($hdrs[0]) ? end($hdrs[0]) : $hdrs[0];
    if (!is_string($line)) return false;

    if (preg_match('#\s(\d{3})\s#', $line, $m)) {
        $code = (int)$m[1];
        return ($code >= 200 && $code < 400);
    }

    return false;
}


// SHELL CHECK ///
function shell_check($file_path)
{
    global $shell_check;

    // Базовая валидация и проверка, что это действительно картинка
    if (!is_string($file_path) || $file_path === '' || !is_file($file_path)) {
        stderr("Произошла ошибка", "Неправильный файл!");
    }
    if (!@getimagesize($file_path)) {
        stderr("Произошла ошибка", "Неправильный файл!");
    }

    // Если глубокая проверка отключена — выходим как и раньше
    if (empty($shell_check)) {
        return;
    }

    $file_contents = @file_get_contents($file_path);
    if ($file_contents === false) {
        stderr("Произошла ошибка", "Не удалось прочитать файл.");
    }

    // Ищем опасные вызовы: include/require/exec/system/passthru/eval/и т.п.
    // \b — граница слова, чтобы не ловить подстроки; \s* — любые пробелы перед '('
    // флаг i — без учета регистра, s — точка матчит перевод строки (на случай минифицированного кода)
    $pattern = '/\b(?:include|include_once|require|require_once|file|fwrite|fopen|fread|exec|system|passthru|eval|copy)\s*\(/is';

    if (preg_match($pattern, $file_contents)) {
        stderr("Произошла ошибка", "Неправильный файл!");
    }
}
// SHELL CHECK ///



function strip_magic_quotes($arr) {
	foreach ($arr as $k => $v) {
		if (is_array($v)) {
			$arr[$k] = strip_magic_quotes($v);
			} else {
			$arr[$k] = stripslashes($v);
			}
	}
	return $arr;
}

function local_user() {
	return $_SERVER["SERVER_ADDR"] == $_SERVER["REMOTE_ADDR"];
}



function sql_query(string $query, bool $detect = true) {
    global $mysqli, $queries, $query_stat, $querytime;

    // --- настройки по умолчанию (можно вынести в конфиг) ---
    static $SLOW_QUERY_THRESHOLD = 0.250;   // сек, что считать «медленным»
    static $QUERY_STAT_MAX       = 200;     // сколько последних запросов хранить
    static $MAX_RETRIES          = 3;       // максимальное число повторов при 1213/1205
    static $RETRY_BASE_US        = 50000;   // базовая задержка между повторами (микросекунды)

    if (!isset($queries))    { $queries = 0; }
    if (!isset($querytime))  { $querytime = 0.0; }
    if (!isset($query_stat) || !is_array($query_stat)) { $query_stat = []; }

    // Бюджет безопасности
    if ($detect && function_exists('detect_sqlinjection')) {
        detect_sqlinjection($query);
    }

    // Страхуем соединение (иногда на долгих скриптах timeout)
    if (!$mysqli->ping()) {
        // по желанию: попытка переподключения, если у тебя есть вспомогательная функция
        // reconnect_mysqli($mysqli);
        // ещё раз ping
        $mysqli->ping();
    }

    $queries++;
    $attempt  = 0;
    $started  = timer();
    $result   = false;
    $errNo    = 0;
    $errMsg   = '';

    // Включим исключения локально, чтобы не городить if ($result === false)
    $prevReport = mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        do {
            $attempt++;
            $tick = timer();
            try {
                $result = $mysqli->query($query);
                $errNo  = 0;
                break; // успех — выходим из retry цикла
            } catch (\mysqli_sql_exception $e) {
                $errNo  = (int)$e->getCode();
                $errMsg = $e->getMessage();

                // DEADLOCK (1213) или LOCK WAIT TIMEOUT (1205) — пробуем повторить
                if (($errNo === 1213 || $errNo === 1205) && $attempt <= $MAX_RETRIES) {
                    // экспоненциальная задержка с джиттером
                    $sleep = $RETRY_BASE_US * (1 << ($attempt - 1));
                    $sleep += random_int(0, (int)($RETRY_BASE_US / 2));
                    usleep($sleep);
                    // идём на повтор
                    continue;
                }

                // Другие ошибки — пробрасываем
                throw $e;
            } finally {
                // учёт времени одного «попытки»-раунда (не общий)
                $querytime += (timer() - $tick);
            }
        } while ($attempt <= $MAX_RETRIES);

    } catch (\mysqli_sql_exception $fatal) {
        // Финальная обработка ошибки: форматируем как в исходнике, но безопасно
        $safeQuery = htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMsg   = htmlspecialchars($fatal->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code      = (int)$fatal->getCode();

        // полезно залогировать в файл/системный лог (без HTML)
        error_log(sprintf(
            "[SQL] Error %d after %d attempt(s): %s | Query: %s",
            $code, $attempt, $fatal->getMessage(), $query
        ));

        mysqli_report($prevReport); // вернуть прежний режим
        die("SQL Error [{$code}]: {$safeMsg}<br>Запрос: {$safeQuery}");
    }

    // Итоговое время (включая повторы)
    $total_time = timer() - $started;

    // --- SLOW LOG ---
    if ($total_time >= $SLOW_QUERY_THRESHOLD) {
        // Короткий лог для быстрой диагностики
        error_log(sprintf(
            "[SQL][SLOW %.3fs][try:%d] %s",
            $total_time, $attempt, preg_replace('/\s+/', ' ', $query)
        ));

        // Опционально: EXPLAIN только для SELECT
        if (stripos(ltrim($query), 'SELECT') === 0) {
            try {
                $explainRes = $mysqli->query("EXPLAIN " . $query);
                if ($explainRes instanceof mysqli_result) {
                    $rows = [];
                    while ($row = $explainRes->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $explainRes->free();
                    // Логируем план в одну строку JSON
                    error_log("[SQL][EXPLAIN] " . json_encode($rows, JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $ignored) {
                // план не обязателен
            }
        }
    }

    // --- СТАТИСТИКА (ограничиваем размеры, чтобы не течь по памяти) ---
    $query_stat[] = [
        'seconds' => substr(number_format($total_time, 6, '.', ''), 0, 8),
        'attempt' => $attempt,
        'query'   => $query,
    ];
    if (count($query_stat) > $QUERY_STAT_MAX) {
        // сдвигаем «кольцевым» образом
        $query_stat = array_slice($query_stat, -$QUERY_STAT_MAX, null, false);
    }

    mysqli_report($prevReport); // восстановить режим

    return $result;
}


function dbconn(bool $autoclean = false, bool $lightmode = false): void
{
    global $mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db, $mysqli_charset, $mysqli;

    // Уже есть живое соединение? — переиспользуем
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        try {
            if (@$mysqli->ping()) {
                userlogin($lightmode);
                if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' && $autoclean) {
                    register_shutdown_function('autoclean');
                }
                return;
            }
        } catch (Throwable $e) {
            // упадём в реконнект ниже
        }
    }

    // Настройки повторов подключения
    $MAX_RETRIES   = 3;
    $BASE_SLEEP_US = 150000; // 0.15s, экспоненциально растёт
    $lastErr       = null;

    // Создаём инстанс и задаём опции ДО real_connect
    $link = new mysqli();
    // Таймауты подключения/запроса (сек)
    @$link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    // При желании можно включить SSL, если у тебя есть пути к сертификатам:
    // @$link->ssl_set(NULL, NULL, '/path/to/ca.pem', NULL, NULL);

    // Для совместимости с твоим стилем — держим handle в $mysqli
    $mysqli = $link;

    // Попытки подключения с экспоненциальной задержкой
    for ($i = 0; $i < $MAX_RETRIES; $i++) {
        try {
            // Можно использовать persistent-подключение, добавив 'p:' к хосту:
            $host = $mysqli_host; // либо "p:$mysqli_host" — если решишь применять persistent
            if (@$mysqli->real_connect($host, $mysqli_user, $mysqli_pass, $mysqli_db)) {
                $lastErr = null;
                break;
            }
            $lastErr = "[{$mysqli->connect_errno}] {$mysqli->connect_error}";
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
        }

        // экспоненциальная задержка с лёгким джиттером
        $sleep = $BASE_SLEEP_US * (1 << $i) + random_int(0, (int)($BASE_SLEEP_US / 2));
        usleep($sleep);
    }

    if ($lastErr !== null) {
        $safe = htmlspecialchars($lastErr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        die("dbconn: mysqli_connect failed: {$safe}");
    }

    // Установка кодировки соединения
    if (!@$mysqli->set_charset($mysqli_charset)) {
        $safe = htmlspecialchars($mysqli->error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        die("dbconn: set_charset error: {$safe}");
    }

    // Базовая инициализация сессии MySQL (строгие режимы, таймзона, безопасные дефолты)
    // ВНИМАНИЕ: при необходимости можешь ослабить STRICT/ONLY_FULL_GROUP_BY
    $initSql = [];
    $initSql[] = "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'";
    // Часовой пояс – из PHP конфигурации (соответствует Europe/Amsterdam, если выставлен в php.ini)
    $initSql[] = "SET time_zone = @@session.time_zone"; // нейтрально (оставляем как у сессии PHP/сервера)
    // Безопасное поведение по умолчанию
    $initSql[] = "SET NAMES " . preg_replace('~[^a-z0-9_]+~i', '', (string)$mysqli_charset);

    foreach ($initSql as $sql) {
        if (!$mysqli->query($sql)) {
            $safe = htmlspecialchars($mysqli->error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            die("dbconn: init query failed: {$safe}<br>SQL: " . htmlspecialchars($sql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    }

    // Авторизация пользователя
    userlogin($lightmode);

    // Регистрируем автозачистку только на index.php (по твоей логике)
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' && $autoclean) {
        register_shutdown_function('autoclean');
    }

    // Корректное закрытие соединения по завершению запроса
    // (используем именованную функцию, чтобы не захватывать объект по значению в use())
    if (!function_exists('dbconn_shutdown_close')) {
        function dbconn_shutdown_close(): void {
            global $mysqli;
            if ($mysqli instanceof mysqli) {
                @$mysqli->close();
            }
        }
    }
    register_shutdown_function('dbconn_shutdown_close');
}


function userlogin(bool $lightmode = false): void {
    global $SITE_ONLINE, $default_language, $tracker_lang, $use_lang, $use_ipbans, $mysqli, $memcached;

    // Сброс текущего пользователя
    unset($GLOBALS['CURUSER']);

    /** ----------------------- IP & BAN ----------------------- */
    $ip  = getip();
    $nip = ip2long($ip);
    // Корректный UNSIGNED для IPv4; для IPv6 $nip будет false — такие баны просто пропускаем (если в БД нет IPv6)
    $nip_u = ($nip !== false) ? sprintf('%u', $nip) : null;

    if ($use_ipbans && !$lightmode && $nip_u !== null) {
        // Быстрое «есть ли бан на этот IP?»
        $res = sql_query("SELECT 1 FROM bans WHERE {$nip_u} >= first AND {$nip_u} <= last LIMIT 1");
        if ($res instanceof mysqli_result && $res->fetch_row()) {
            http_response_code(403);
            // Минимум HTML, безопасно
            echo "<!doctype html><meta charset='utf-8'><title>403 Forbidden</title><h1>403 Forbidden</h1><p>Доступ с этого IP запрещён.</p>";
            exit;
        }
    }

    /** ----------------------- COOKIE ----------------------- */
    if (!$SITE_ONLINE || empty($_COOKIE['uid']) || empty($_COOKIE['pass'])) {
        if ($use_lang) {
            include_once "languages/lang_{$default_language}/lang_main.php";
        }
        user_session();
        return;
    }

    // Жёсткая валидация значений cookie
    $id_raw   = $_COOKIE['uid'];
    $pass_raw = $_COOKIE['pass'];

    // uid — только цифры (int > 0), pass — HEX32
    if (!ctype_digit($id_raw) || !preg_match('/^[a-f0-9]{32}$/i', $pass_raw)) {
        http_response_code(400);
        exit('Ошибка: некорректные cookie.');
    }

    $id   = (int)$id_raw;
    $pass = strtolower($pass_raw); // cookieFromPasshash обычно MD5 → нормализуем регистр

    if ($id <= 0) {
        http_response_code(400);
        exit('Ошибка: некорректный ID.');
    }

    /** ----------------------- КЭШ СЕССИИ ----------------------- */
    $row = null;
    $cache_key = "user:session:{$id}:{$pass}";
    $have_memc = isset($memcached) && ($memcached instanceof Memcached);

    if ($have_memc) {
        $row = $memcached->get($cache_key);
        if ($memcached->getResultCode() !== Memcached::RES_SUCCESS) {
            $row = null;
        }
    }

    if (!$row) {
        // Минимальный селект по ключевым полям (всё равно нужен passhash)
        $res = sql_query("SELECT * FROM users WHERE id = {$id} AND enabled = 'yes' AND status = 'confirmed' LIMIT 1");
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc() ?: null;
            $res->free();
        }

        // Проверяем cookie против passhash
        if ($row && hash_equals($pass, strtolower(cookieFromPasshash($row['passhash'] ?? '')))) {
            if ($have_memc) {
                // Короткий TTL — чтобы не «залипала» сессия при банах/изменениях
                $memcached->set($cache_key, $row, 60);
            }
        } else {
            $row = null;
        }
    }

    // Не нашли — гость
    if (!$row) {
        if ($use_lang) {
            include_once "languages/lang_{$default_language}/lang_main.php";
        }
        user_session();
        return;
    }

    /** ----------------------- LAST ACCESS THROTTLE ----------------------- */
    // Обновляем IP и last_access не чаще, чем раз в 5 минут
    $access_key = "user:lastaccess:{$row['id']}";
    $should_update = true;

    if ($have_memc) {
        $should_update = ($memcached->get($access_key) !== true);
    }

    if ($should_update) {
        $escaped_ip = sqlesc($ip);
        $datetime   = sqlesc(get_date_time());
        sql_query("UPDATE users SET last_access = {$datetime}, ip = {$escaped_ip} WHERE id = {$row['id']}");

        if ($have_memc) {
            $memcached->set($access_key, true, 300); // 5 минут
        }
    }

    // Текущий IP в объект пользователя (не пишем в БД чаще нужного)
    $row['ip'] = $ip;

    /** ----------------------- КЛАССЫ ----------------------- */
    if (isset($row['override_class'], $row['class']) && (int)$row['override_class'] < (int)$row['class']) {
        $row['class'] = (int)$row['override_class'];
    }

    /** ----------------------- ГЛОБАЛЫ, ЯЗЫК, СЕССИЯ ----------------------- */
    $GLOBALS['CURUSER'] = $row;

    if ($use_lang) {
        $lang = $row['language'] ?? $default_language;
        // Защита от инъекции пути языка: оставляем только буквы, цифры, дефис и подчёркивание
        $lang = preg_replace('~[^a-z0-9_-]+~i', '', (string)$lang) ?: $default_language;
        @include_once "languages/lang_{$lang}/lang_main.php";
    }

    if (!$lightmode) {
        user_session();
    }
}






function cookieFromPasshash($p)
{
	return md5('gu&R'.$p.getip().'==');
}

function get_server_load() {
    global $tracker_lang;

    // Микрокэш на уровне процесса
    static $cache = null, $cache_ts = 0;
    $now = microtime(true);
    if ($cache !== null && ($now - $cache_ts) < 2.0) {
        return $cache;
    }

    // Windows обычно не поддерживает loadavg — возвращаем 0.0 (как было в исходнике)
    if (stripos(PHP_OS, 'WIN') === 0) {
        $cache = 0.0;
        $cache_ts = $now;
        return $cache;
    }

    // 1) Быстрее всего — встроенная функция PHP
    if (function_exists('sys_getloadavg')) {
        $arr = @sys_getloadavg();
        if (is_array($arr) && isset($arr[0]) && is_numeric($arr[0])) {
            $val = round((float)$arr[0], 3);
            $cache = $val;
            $cache_ts = $now;
            return $val;
        }
    }

    // 2) Linux /proc/loadavg
    $proc = '/proc/loadavg';
    if (is_readable($proc)) {
        $data = @file_get_contents($proc);
        if (is_string($data) && $data !== '') {
            // формат: "0.42 0.55 0.60 1/123 4567"
            $parts = preg_split('/\s+/', trim($data));
            if (!empty($parts[0])) {
                $num = (float)str_replace(',', '.', $parts[0]);
                $val = round($num, 3);
                $cache = $val;
                $cache_ts = $now;
                return $val;
            }
        }
    }

    // 3) Fallback: вывод `uptime` (macOS/BSD/Linux)
    // Примеры:
    //  - "load averages: 1.07 0.95 0.90" (BSD/macOS)
    //  - "load average: 0.42, 0.55, 0.60" (Linux в некоторых локалях)
    $uptime = @exec('uptime 2>/dev/null');
    if (is_string($uptime) && $uptime !== '') {
        // Уберём переносы и двойные пробелы
        $u = trim(preg_replace('/\s+/', ' ', $uptime));
        // Нормализуем запятые в десятичные точки для простоты
        $u = str_replace(',', '.', $u);

        // Универсальный regex: берём первую цифру с точкой после "load average" или "load averages"
        if (preg_match('/load averages?:\s*([0-9]+(?:\.[0-9]+)?)/i', $u, $m)) {
            $val = round((float)$m[1], 3);
            $cache = $val;
            $cache_ts = $now;
            return $val;
        }

        // Альтернативный формат на некоторых системах: последние три числа — это LA1,5,15
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s+[0-9]+(?:\.[0-9]+)?\s+[0-9]+(?:\.[0-9]+)?$/', $u, $m)) {
            $val = round((float)$m[1], 3);
            $cache = $val;
            $cache_ts = $now;
            return $val;
        }
    }

    // Если ничего не получилось
    $unknown = isset($tracker_lang['unknown']) ? $tracker_lang['unknown'] : 'unknown';
    $cache = $unknown;
    $cache_ts = $now;
    return $unknown;
}

function user_session(): void
{
    global $CURUSER, $use_sessions, $memcached, $mysqli;

    if (empty($use_sessions) || !$mysqli instanceof mysqli) {
        return;
    }

    // --- Memcached (init один раз) ---
    if (!$memcached instanceof Memcached) {
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);
    }

    // --- Входные данные ---
    $ip    = (string) getip(); // TBDev helper
    $url   = substr((string)($_SERVER['REQUEST_URI']     ?? ''), 0, 255);
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $now   = time();

    if (!defined('APP_PREFIX')) {
        define('APP_PREFIX', 'project1');
    }

    $isLogged = !empty($CURUSER) && isset($CURUSER['id']);

    // ВАЖНО: для guest -> 0 (а не -1), чтобы не ломать UNSIGNED
    $uid      = $isLogged ? (int)$CURUSER['id']       : 0;
    $username = $isLogged ? (string)$CURUSER['username'] : '';
    $class    = $isLogged ? (int)$CURUSER['class']    : 0;

    // Безопасные пределы для UNSIGNED полей (на случай мелкой разрядности)
    // Подстрой под свою схему, если нужно (например, SMALLINT/INT UNSIGNED)
    $uid   = max(0, $uid);
    $class = max(0, $class);

    // Усечём юзернейм под тип столбца (часто VARCHAR(64))
    $username = substr($username, 0, 64);

    // sid фиксированной длины
    $sid = hash('sha256', APP_PREFIX . '|' . $uid . '|' . $ip . '|' . $agent);

    // --- троттлинг: не чаще 60с ---
    $throttleKey = 'sess_t_' . $sid;
    $last = $memcached->get($throttleKey);
    if (is_int($last) && ($now - $last) < 60) {
        return;
    }
    $memcached->set($throttleKey, $now, 90);

    // --- UPSERT (нужен PK/UNIQUE на sessions.sid) ---
    $sql = "
        INSERT INTO sessions (sid, uid, username, class, ip, time, url, useragent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            uid = VALUES(uid),
            username = VALUES(username),
            class = VALUES(class),
            ip = VALUES(ip),
            time = VALUES(time),
            url = VALUES(url),
            useragent = VALUES(useragent)
    ";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) return;

    // Типы: s i s i s i s s
    $timeInt = (int)$now;
    $stmt->bind_param(
        'sisisiss',
        $sid,
        $uid,
        $username,
        $class,
        $ip,
        $timeInt,
        $url,
        $agent
    );
    $stmt->execute();
    $stmt->close();
}



function unesc($x) {
    return $x;
}

function gzip() { 
        global $use_gzip; 
} 
        if ($use_gzip) { 
                @ob_start('ob_gzhandler'); 
        } 

// IP Validation
function validip(string $ip): bool {
    if ($ip === '' || $ip !== long2ip(ip2long($ip))) {
        return false;
    }

    // reserved IANA IPv4 addresses
    $reserved_ips = [
        ['0.0.0.0','2.255.255.255'],
        ['10.0.0.0','10.255.255.255'],       // private
        ['127.0.0.0','127.255.255.255'],     // loopback
        ['169.254.0.0','169.254.255.255'],   // link-local
        ['172.16.0.0','172.31.255.255'],     // private
        ['192.0.2.0','192.0.2.255'],         // TEST-NET-1
        ['192.168.0.0','192.168.255.255'],   // private
        ['198.18.0.0','198.19.255.255'],     // benchmark
        ['224.0.0.0','239.255.255.255'],     // multicast
        ['240.0.0.0','255.255.255.255'],     // reserved/broadcast
    ];

    $ipl = ip2long($ip);
    foreach ($reserved_ips as $r) {
        if ($ipl >= ip2long($r[0]) && $ipl <= ip2long($r[1])) {
            return false;
        }
    }
    return true; // глобальный, «валидный» IP
}

// Определение локальных пиров (LAN, loopback, CGNAT, IPv6 link-local)
function is_local_peer(string $ip): bool {
    // IPv4 private + loopback + CGNAT (100.64.0.0/10)
    $local_ranges = [
        ['10.0.0.0','10.255.255.255'],
        ['127.0.0.0','127.255.255.255'],
        ['169.254.0.0','169.254.255.255'],
        ['172.16.0.0','172.31.255.255'],
        ['192.168.0.0','192.168.255.255'],
        ['100.64.0.0','100.127.255.255'], // Carrier-Grade NAT
    ];

    $ipl = @ip2long($ip);
    if ($ipl !== false) {
        foreach ($local_ranges as $r) {
            if ($ipl >= ip2long($r[0]) && $ipl <= ip2long($r[1])) {
                return true;
            }
        }
    }

    // IPv6 локальные (link-local fe80::/10, loopback ::1, unique-local fc00::/7)
    if (strpos($ip, ':') !== false) {
        if ($ip === '::1') return true;
        if (stripos($ip, 'fe80:') === 0) return true;
        $first16 = substr($ip, 0, 2);
        if (in_array($first16, ['fc','fd'], true)) return true;
    }

    return false;
}



function getip() {
	if (isset($_SERVER)) {
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && validip($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP']) && validip($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
	} else {
		if (getenv('HTTP_X_FORWARDED_FOR') && validip(getenv('HTTP_X_FORWARDED_FOR'))) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP') && validip(getenv('HTTP_CLIENT_IP'))) {
			$ip = getenv('HTTP_CLIENT_IP');
		} else {
			$ip = getenv('REMOTE_ADDR');
		 }
	}

	return $ip;
}

function autoclean(): void {
    global $autoclean_interval, $rootpath, $mysqli;

    // sane defaults
    $interval = max(60, (int)$autoclean_interval); // не чаще раза в минуту
    $now      = time();

    // Небольшой джиттер, чтобы фронты не ударили ровно в одну секунду
    // (не влияет на частоту — всего 0..2 секунды)
    $now += random_int(0, 2);

    // 1) Инициализация «маячка» если его нет
    //    (без блокировок — просто ensure row exists)
    $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime' LIMIT 1");
    if ($res instanceof mysqli_result && !$res->fetch_row()) {
        // INSERT IGNORE на случай гонки нескольких процессов
        sql_query("INSERT IGNORE INTO avps (arg, value_u) VALUES ('lastcleantime', {$now})");
        // ранний выход — до следующего интервала
        return;
    }
    if ($res instanceof mysqli_result) $res->free();

    // 2) Быстрая проверка «пора ли»
    $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime' LIMIT 1");
    $row = ($res instanceof mysqli_result) ? $res->fetch_row() : false;
    if ($res instanceof mysqli_result) $res->free();

    $last = $row ? (int)$row[0] : 0;
    if ($last + $interval > $now) {
        // ещё рано
        return;
    }

    // 3) Пытаемся захватить глобальную advisory-блокировку MySQL, чтобы избежать параллельного запуска
    //    Имя блокировки — неймспейс проекта + задача; timeout=0 → не ждём
    $lockName = 'tbdev:autoclean';
    $gotLock  = false;

    try {
        $lockRes = sql_query("SELECT GET_LOCK('" . $mysqli->real_escape_string($lockName) . "', 0)");
        if ($lockRes instanceof mysqli_result) {
            $gotLock = (bool)($lockRes->fetch_row()[0] ?? 0);
            $lockRes->free();
        }
        if (!$gotLock) {
            // кто-то уже чистит
            return;
        }

        // 4) Доп. защита: CAS-обновление по старому значению (если другой процесс успел до нас)
        $update = sql_query("UPDATE avps SET value_u = {$now} WHERE arg = 'lastcleantime' AND value_u = {$last}");
        if ($mysqli->affected_rows === 0) {
            // проиграли гонку — выходим
            return;
        }

        // 5) Запуск фактической очистки
        require_once rtrim($rootpath, "/\\") . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'cleanup.php';

        // Защитим от непойманных исключений внутри docleanup()
        try {
            docleanup();
        } catch (Throwable $e) {
            // Откатим «lastcleantime» назад на 10 сек, чтобы другой процесс мог перехватить и повторить
            $fallback = max(0, $now - 10);
            sql_query("UPDATE avps SET value_u = {$fallback} WHERE arg = 'lastcleantime' AND value_u = {$now}");
            error_log("[autoclean] docleanup() failed: " . $e->getMessage());
            // не пробрасываем исключение — система должна жить
        }

    } finally {
        // 6) Всегда отпускаем блокировку, если брали
        if ($gotLock) {
            try {
                sql_query("DO RELEASE_LOCK('" . $mysqli->real_escape_string($lockName) . "')");
            } catch (Throwable $ignore) {
                // no-op
            }
        }
    }
}


function mksize($bytes) {
	if ($bytes < 1000 * 1024)
		return number_format($bytes / 1024, 2) . " kB";
	elseif ($bytes < 1000 * 1048576)
		return number_format($bytes / 1048576, 2) . " MB";
	elseif ($bytes < 1000 * 1073741824)
		return number_format($bytes / 1073741824, 2) . " GB";
	else
		return number_format($bytes / 1099511627776, 2) . " TB";
}

function mksizeint($bytes) {
		$bytes = max(0, $bytes);
		if ($bytes < 1000)
				return floor($bytes) . " B";
		elseif ($bytes < 1000 * 1024)
				return floor($bytes / 1024) . " kB";
		elseif ($bytes < 1000 * 1048576)
				return floor($bytes / 1048576) . " MB";
		elseif ($bytes < 1000 * 1073741824)
				return floor($bytes / 1073741824) . " GB";
		else
				return floor($bytes / 1099511627776) . " TB";
}

function deadtime() {
	global $announce_interval;
	return time() - floor($announce_interval * 1.3);
}

function mkprettytime($s) {
    if ($s < 0)
	$s = 0;
    $t = array();
    foreach (array("60:sec","60:min","24:hour","0:day") as $x) {
		$y = explode(":", $x);
		if ($y[0] > 1) {
		    $v = $s % $y[0];
		    $s = floor($s / $y[0]);
		} else
		    $v = $s;
	$t[$y[1]] = $v;
    }

    if ($t["day"])
	return $t["day"] . "d " . sprintf("%02d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
    if ($t["hour"])
	return sprintf("%d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
	return sprintf("%d:%02d", $t["min"], $t["sec"]);
}

function mkglobal($vars) {
	if (!is_array($vars))
		$vars = explode(":", $vars);
	foreach ($vars as $v) {
		if (isset($_GET[$v]))
			$GLOBALS[$v] = unesc($_GET[$v]);
		elseif (isset($_POST[$v]))
			$GLOBALS[$v] = unesc($_POST[$v]);
		else
			return 0;
	}
	return 1;
}

function tr($x, $y, $noesc = 0, $prints = true, $width = "", $relation = '') {
	if ($noesc)
		$a = $y;
	else {
		$a = htmlspecialchars_uni($y);
		$a = str_replace("\n", "<br />\n", $a);
	}
	if ($prints) {
		$print = "<td class=\"lola\" width=\"" . $width . "\" class=\"heading\" valign=\"top\" align=\"right\">$x</td>";
		$colpan = "align=\"left\"";
	} else {
		$print = ""; // обязательно инициализируем переменную
		$colpan = "colspan=\"2\"";
	}

	print("<tr" . ($relation ? " relation=\"$relation\"" : "") . ">$print<td class=\"lol\" valign=\"top\" $colpan>$a</td></tr>\n");
}

function validfilename($name) {
	return preg_match('/^[^\0-\x1f:\\\\\/?*\xff#<>|]+$/si', $name);
}

function validemail(string $email): bool
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 254) return false;

    // Быстрая структура
    if (strpos($email, '@') === false) return false;
    [$local, $domain] = explode('@', $email, 2);
    if ($local === '' || $domain === '') return false;
    if (strlen($local) > 64) return false;                 // RFC лимит локальной части
    if ($domain[0] === '.' || substr($domain, -1) === '.') return false;

    // Поддержка кириллических доменов (IDN → ASCII) при наличии intl
    if (function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false) return false;
        $domain = $ascii;
    }

    $normalized = $local . '@' . $domain;

    // Базовая валидация
    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) return false;

    // Доп. здравый смысл
    if (preg_match('/\.{2,}/', $normalized)) return false; // две точки подряд
    if ($domain[0] === '-' || substr($domain, -1) === '-') return false;

    return true;
}

/**
 * Универсальная отправка почты (UTF-8, PHP 8.1 совместимо)
 * Режимы:
 *   - $smtptype = 'default'  => простой mail() с минимальными заголовками
 *   - $smtptype = 'advanced' => расширенные заголовки + опц. Windows SMTP ini_set
 *   - $smtptype = 'external' => внешний SMTP-клиент include/smtp/smtp.lib.php
 *
 * Параметры совместимы с прежней сигнатурой TBDev.
 */
function sent_mail(
    string $to,
    string $fromname,
    string $fromemail,
    string $subject,
    string $body,
    bool   $multiple = false,
    string $multiplemail = ''
): bool {
    global $SITENAME, $SITEEMAIL, $smtptype, $smtp, $smtp_host, $smtp_port,
           $smtp_from, $smtpaddress, $accountname, $accountpassword, $rootpath;

    // Безопасные дефолты
    $smtptype = $smtptype ?? 'default';
    $fromname  = (string)$fromname ?: (string)$SITENAME;
    $fromemail = (string)$fromemail ?: (string)$SITEEMAIL;

    // Определим корректный перевод строки
    $windows = false;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $eol = "\r\n";
        $windows = true;
    } else {
        $eol = "\n"; // RFC допускает LF, большинство MTA норм
    }

    // Кодируем заголовки в UTF-8 как положено
    if (function_exists('mb_encode_mimeheader')) {
        $enc_fromname = mb_encode_mimeheader($fromname, 'UTF-8', 'B', $eol);
        $enc_subject  = mb_encode_mimeheader($subject,  'UTF-8', 'B', $eol);
    } else {
        // запасной вариант (минимально приемлемо)
        $enc_fromname = '=?UTF-8?B?' . base64_encode($fromname) . '?=';
        $enc_subject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    // Сборка заголовков
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $mid = md5(($GLOBALS['REMOTE_ADDR'] ?? '') . $fromname . microtime(true));

    $headers  = "From: {$enc_fromname} <{$fromemail}>".$eol;
    $headers .= "Reply-To: {$enc_fromname} <{$fromemail}>".$eol;
    // Return-Path как заголовок не всегда уважается MTA, но оставим для совместимости
    $headers .= "Return-Path: {$fromemail}".$eol;
    $headers .= "Message-ID: <{$mid}@{$server_name}>".$eol;
    $headers .= "X-Mailer: PHP/".phpversion().$eol;
    $headers .= "MIME-Version: 1.0".$eol;
    $headers .= "Content-Type: text/plain; charset=UTF-8".$eol;
    $headers .= "Content-Transfer-Encoding: 8bit".$eol;
    if ($multiple && $multiplemail !== '') {
        // несколько адресов через запятую
        $headers .= "Bcc: {$multiplemail}".$eol;
    }

    // Приводим тело к LF (часть MTA лучше переваривает)
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    // ===== Режимы отправки =====
    if ($smtptype === 'default') {
        // Минимальный режим
        return @mail($to, $enc_subject, $body, $headers);

    } elseif ($smtptype === 'advanced') {
        // Опционально подкрутить ini на Windows
        if ($smtp === 'yes') {
            @ini_set('SMTP',       (string)$smtp_host);
            @ini_set('smtp_port',  (string)$smtp_port);
            if ($windows) {
                @ini_set('sendmail_from', (string)($smtp_from ?: $fromemail));
            }
        }

        $ok = @mail($to, $enc_subject, $body, $headers);

        if ($smtp === 'yes') {
            @ini_restore('SMTP');
            @ini_restore('smtp_port');
            if ($windows) @ini_restore('sendmail_from');
        }

        return $ok;

    } elseif ($smtptype === 'external') {
        // Внешний SMTP-клиент (оставлено для полной совместимости с вашей схемой)
        // Ожидаем include/smtp/smtp.lib.php и класс smtp с методами open/auth/from/to/subject/body/send/close
        $lib = rtrim($rootpath ?? '','/\\') . '/include/smtp/smtp.lib.php';
        if (!is_file($lib)) {
            // Библиотека недоступна
            return false;
        }
        require_once $lib;

        try {
            $mail = new smtp();
            // $mail->debug(true); // при необходимости
            $mail->open($smtp_host, (int)$smtp_port);

            if (!empty($accountname) && !empty($accountpassword)) {
                $mail->auth((string)$accountname, (string)$accountpassword);
            }

            $mail->from($fromemail ?: $SITEEMAIL);
            $mail->to($to);
            $mail->subject($subject); // внешняя либра сама должна кодировать/ставить заголовки
            $mail->body($body);

            $result = $mail->send();
            $mail->close();

            return (bool)$result;
        } catch (Throwable $e) {
            return false;
        }
    }

    // Неизвестный режим
    return false;
}


function sqlesc($value) {
	global $mysqli;

	// Если значение не числовое — экранируем и оборачиваем в кавычки
	if (!is_numeric($value)) {
		$value = "'" . $mysqli->real_escape_string($value) . "'";
	}

	return $value;
}

function sqlwildcardesc($x) {
	global $mysqli;
	return str_replace(
		array("%", "_"),
		array("\\%", "\\_"),
		mysqli_real_escape_string($mysqli, $x)
	);
}


function urlparse($m) {
	$t = $m[0];
	if (preg_match(',^\w+://,', $t))
		return "<a href=\"$t\">$t</a>";
	return "<a href=\"http://$t\">$t</a>";
}

function parsedescr($d, $html) {
	if (!$html) {
	  $d = htmlspecialchars_uni($d);
	  $d = str_replace("\n", "\n<br>", $d);
	}
	return $d;
}

function stdhead(string $title = "", $blockhide = null, bool $msgalert = true): void
{
    global $CURUSER, $SITE_ONLINE, $FUNDS, $SITENAME, $DEFAULTBASEURL, $ss_uri, $tracker_lang, $default_theme;

    // ===== доступность сайта =====
    if (empty($SITE_ONLINE)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: 600');
        }
        // Сообщение оставлено прежним (не ломаем поведение)
        die("Сайт временно недоступен. Пожалуйста, зайдите позже... Спасибо!<br />");
    }

    // ===== заголовки (отправляем один раз) =====
    if (!headers_sent()) {
        // Если где-то раньше поставили другую кодировку — не ломаем; но у нас везде UTF-8
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
    }

    // ===== заголовок страницы =====
    $suffix = (isset($_GET['werth']) ? ' (FTEDev)' : '');
    if ($title === "") {
        $title = $SITENAME . $suffix;
    } else {
        // используем htmlspecialchars_uni, если есть, иначе — стандартный htmlspecialchars
        $safeTitle = function_exists('htmlspecialchars_uni')
            ? htmlspecialchars_uni($title)
            : htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = $SITENAME . $suffix . " :: " . $safeTitle;
    }

    // ===== выбор темы (безопасный, с фолбэком) =====
    $candidate = $CURUSER['theme'] ?? $CURUSER['stylesheet'] ?? $default_theme ?? 'default';
    // только латиница/цифры/_/-
    $candidate = preg_replace('~[^a-zA-Z0-9_\-]~', '', (string)$candidate) ?: 'default';

    // Проверяем существование каталога темы один раз (кэшируем в static)
    static $themeExistsCache = [];
    if (!isset($themeExistsCache[$candidate])) {
        // для совместимости оставляем логику через styles/<theme>
        $tryThemeDir = __DIR__ . "/../styles/{$candidate}";
        if (!is_dir($tryThemeDir)) {
            $fallback = preg_replace('~[^a-zA-Z0-9_\-]~', '', (string)($default_theme ?? 'default')) ?: 'default';
            $candidate = $fallback;
        }
        $themeExistsCache[$candidate] = true; // сам факт, что мы финально определили кандидат
    }
    $ss_uri = $candidate;

    // ===== подключаем include-файлы из styles/include (кэшируем поиск пути) =====
    $incDir = (static function (): string {
        static $cached = null;
        if ($cached !== null) return $cached;

        // Варианты путей (сохраняем прежний приоритет)
        $a = __DIR__ . '/../styles/include';
        $b = __DIR__ . '/styles/include';
        $c = (!empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/styles/include';

        if (is_dir($a))      { $cached = $a; return $cached; }
        if (is_dir($b))      { $cached = $b; return $cached; }
        if ($c && is_dir($c)){ $cached = $c; return $cached; }

        // дефолт — $a (как было)
        $cached = $a;
        return $cached;
    })();

    // Подключения (require_once не задвоит, сохраняем прежний порядок)
    $main = $incDir . '/main.php';
    $head = $incDir . '/head.php';
    if (is_file($main)) require_once $main;
    if (is_file($head)) require_once $head;
}

function stdfoot($blockhide = null): void
{
    global $CURUSER, $ss_uri, $tracker_lang, $queries, $tstart, $query_stat, $querytime;

    /** ================= include-директория (кеш + защита) ================= */
    $incDir = (static function (): string {
        static $cached = null;
        if ($cached !== null) return $cached;

        $try = [__DIR__ . '/../styles/include', __DIR__ . '/styles/include'];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $try[] = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/styles/include';
        }
        foreach ($try as $p) {
            $rp = realpath($p);
            if ($rp && is_dir($rp)) {
                $cached = rtrim($rp, '/\\');
                return $cached;
            }
        }
        $cached = rtrim(__DIR__ . '/../styles/include', '/\\');
        return $cached;
    })();

    $main = $incDir . '/main.php';
    $foot = $incDir . '/foot.php';
    if (is_file($main)) require_once $main;
    if (is_file($foot)) require_once $foot;

    /** ================= debug: безопасный, аккуратный ================= */
    $isAjax   = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $ctype    = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $isHtmlCt = (stripos($ctype, 'text/html') !== false) || $ctype === '';

    $debugOn = !$isAjax && $isHtmlCt && ((defined('DEBUG_MODE') && DEBUG_MODE) || isset($_GET['werth']));
    if (!$debugOn) return;

    // Метрики
    $totalQueries = (int)($queries ?? 0);
    $totalTime    = isset($querytime) ? (float)$querytime : 0.0;
    if ($totalTime <= 0 && !empty($query_stat) && is_array($query_stat)) {
        foreach ($query_stat as $row) $totalTime += (float)($row['seconds'] ?? 0.0);
    }

    $pageTime = (isset($tstart) && is_numeric($tstart)) ? (microtime(true) - (float)$tstart) : null;
    $load     = function_exists('get_server_load') ? get_server_load() : '—';
    $memPeak  = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;

    $rows = (!empty($query_stat) && is_array($query_stat)) ? $query_stat : [];

    $MAX_ROWS = 300;
    $hiddenCount = max(0, count($rows) - $MAX_ROWS);

    // Рендер
    echo "\n<!-- DEBUG BLOCK START -->\n";
    echo '<style>
:root{
  --dbg-bg: var(--panel, #f7f8fb);
  --dbg-text: var(--text, #222);
  --dbg-muted: var(--muted, #6b7280);
  --dbg-border: color-mix(in oklab, var(--dbg-text) 12%, transparent);
  --dbg-shadow: 0 10px 30px rgba(0,0,0,.08);
  --dbg-pill-ok-bg:#e8f7ee;   --dbg-pill-ok-fg:#0f5132;
  --dbg-pill-warn-bg:#fff4e5; --dbg-pill-warn-fg:#7c3e12;
  --dbg-pill-err-bg:#fde2e1;  --dbg-pill-err-fg:#7f1d1d;
  --dbg-accent: var(--primary, #0ea5e9);
}
.theme-dark,[data-theme="dark"]{
  --dbg-bg: color-mix(in oklab, var(--panel, #0b1220) 92%, white 8%);
  --dbg-text:#e6eaf2; --dbg-muted:#9aa3b2; --dbg-border:rgba(148,163,184,.25);
  --dbg-shadow:0 10px 30px rgba(0,0,0,.35);
}
.debug-panel{
  font:12px/1.45 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
  background:var(--dbg-bg);
  color:var(--dbg-text);
  margin:20px 0 12px;            /* убрали auto, чтобы не сужалось */
  padding:12px 14px 6px;
  border:1px solid var(--dbg-border);
  border-radius:14px;
  box-shadow:var(--dbg-shadow);
  backdrop-filter:blur(6px);
  width:100%;                     /* тянем на ширину контейнера */
  max-width:none;                 /* снимаем ограничение 1200px */
  box-sizing:border-box;          /* учитываем padding в ширине */
}
.debug-panel a{color:var(--dbg-accent);text-decoration:none}
.debug-panel .kv{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px}
.debug-panel .kv span{
  background: color-mix(in oklab, var(--dbg-bg) 90%, black 10%);
  border:1px solid var(--dbg-border); color:var(--dbg-text);
  padding:6px 8px; border-radius:10px;
}
.debug-table{width:100%;border-collapse:collapse;margin-top:8px;overflow:hidden;border-radius:8px}
.debug-table th,.debug-table td{border-bottom:1px dashed var(--dbg-border);padding:6px 8px;text-align:left;vertical-align:top}
.debug-table th{font-weight:600;color:var(--dbg-muted)}
.badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:11px}
.badge.ok{background:var(--dbg-pill-ok-bg);color:var(--dbg-pill-ok-fg)}
.badge.warn{background:var(--dbg-pill-warn-bg);color:var(--dbg-pill-warn-fg)}
.badge.err{background:var(--dbg-pill-err-bg);color:var(--dbg-pill-err-fg)}
</style>';

    echo '<div class="debug-panel" role="complementary" aria-label="Отладочная информация">';
   

    // Один список всех запросов, с подсветкой по SLA
    echo '<table class="debug-table"><thead><tr><th>#</th><th>сек</th><th>запрос</th></tr></thead><tbody>';
    if ($rows) {
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ($i > $MAX_ROWS) break;
            $sec = (float)($row['seconds'] ?? 0.0);
            $q   = htmlspecialchars((string)($row['query'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cls = $sec > 0.100 ? 'err' : ($sec > 0.010 ? 'warn' : 'ok'); // >100 мс — красный; 10–100 — янтарный
            echo '<tr><td>',$i,'</td><td><span class="badge ',$cls,'">',number_format($sec,6,'.',''),'</span></td><td style="word-break:break-word">',$q,'</td></tr>';
        }
        if ($hiddenCount > 0) {
            echo '<tr><td colspan="3"><em>… скрыто ещё ', number_format($hiddenCount), ' строк</em></td></tr>';
        }
    } else {
        echo '<tr><td colspan="3">—</td></tr>';
    }
    echo '</tbody></table>';

    echo '</div>';
    echo "\n<!-- DEBUG BLOCK END -->\n";
}





function genbark($x,$y) {
	stdhead($y);
	print("<h2>" . htmlspecialchars_uni($y) . "</h2>\n");
	print("<p>" . htmlspecialchars_uni($x) . "</p>\n");
	stdfoot();
	exit();
}

function mksecret($length = 20) {
    $set = array("a","A","b","B","c","C","d","D","e","E","f","F","g","G","h","H","i","I","j","J","k","K","l","L","m","M","n","N","o","O","p","P","q","Q","r","R","s","S","t","T","u","U","v","V","w","W","x","X","y","Y","z","Z","1","2","3","4","5","6","7","8","9");

    // 🧩 Обязательно инициализируем переменную
    $str = '';

    for ($i = 1; $i <= $length; $i++) {
        $ch = rand(0, count($set) - 1);
        $str .= $set[$ch];
    }

    return $str;
}


function httperr(int $code = 404): void {
    // Определяем, какой серверный API используется
    $sapi_name = php_sapi_name();

    // Устанавливаем соответствующий HTTP-заголовок
    if ($sapi_name === 'cgi' || $sapi_name === 'cgi-fcgi') {
        header("Status: $code " . get_http_status_message($code));
    } else {
        header("HTTP/1.1 $code " . get_http_status_message($code));
    }

    exit;
}

function gmtime() {
	return strtotime(get_date_time());
}

function logincookie($id, $passhash, $updatedb = 1, $expires = 0x7fffffff) {
		setcookie("uid", $id, $expires, "/");
		setcookie("pass", cookieFromPasshash($passhash), $expires, "/");

	if ($updatedb)
		sql_query("UPDATE users SET last_login = NOW() WHERE id = $id");
}

function logoutcookie() {
	setcookie("uid", "", 0x7fffffff, "/");
	setcookie("pass", "", 0x7fffffff, "/");
}

function loggedinorreturn($nowarn = false) {
	global $CURUSER, $DEFAULTBASEURL;
	if (!$CURUSER) {
		header("Location: $DEFAULTBASEURL/login.php?returnto=" . urlencode(basename($_SERVER["REQUEST_URI"])).($nowarn ? "&nowarn=1" : ""));
		exit();
	}
}

function deletetorrent($id): bool
{
    global $torrent_dir, $mysqli; // $mysqli — ваш общий коннектор; замените под свой слой

    $id = (int)$id;
    if ($id <= 0) {
        return false;
    }

    // список таблиц и условий удаления
    // формат: 'table' => 'column = ?'  или анонимная функция, если условие сложнее
    $targets = [
        'snatched'  => 'torrent = ?',
        'checkcomm' => 'checkid = ? AND torrent = 1',
        'peers'     => 'torrent = ?',
        'files'     => 'torrent = ?',
        'comments'  => 'torrent = ?',
        'ratings'   => 'torrent = ?',
    ];

    // путь к .torrent
    $torrentPath = rtrim((string)$torrent_dir, "/\\") . DIRECTORY_SEPARATOR . $id . '.torrent';

    // включаем исключения для удобного try/catch
    $prevReport = mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli->begin_transaction();

        // удаляем вторичку
        foreach ($targets as $table => $where) {
            $sql  = "DELETE FROM `$table` WHERE $where";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }

        // удаляем сам торрент (последним)
        $stmt = $mysqli->prepare("DELETE FROM `torrents` WHERE `id` = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected < 1) {
            // ничего не удалили из torrents — считаем это ошибкой
            $mysqli->rollback();
            mysqli_report($prevReport);
            return false;
        }

        // фиксируем БД до работы с ФС
        $mysqli->commit();

        // удаляем файл; ошибки ФС не должны ломать БД
        if (is_file($torrentPath)) {
            // @ не используем: лучше вернуть false, если не удалось
            if (!@unlink($torrentPath)) {
                // опционально: залогировать предупреждение
                // error_log("deletetorrent: can't unlink $torrentPath");
            }
        }

        // опционально: инвалидация кэша, если используете memcache/memcached/apcu
        if (function_exists('mc_delete')) {
            @mc_delete("torrents:details:$id");
            @mc_delete("torrents:peers:$id");
            @mc_delete("torrents:comments:$id");
        }

        mysqli_report($prevReport);
        return true;

    } catch (\mysqli_sql_exception $e) {
        // на любой ошибке — откат
        if ($mysqli->errno) {
            $mysqli->rollback();
        }
        // можно залогировать: error_log("deletetorrent($id) failed: ".$e->getMessage());
        mysqli_report($prevReport);
        return false;
    }
}



function downloaderdata($res) {
	$rows = array();
	$ids = array();
	$peerdata = array();
	while ($row = mysql_fetch_assoc($res)) {
		$rows[] = $row;
		$id = $row["id"];
		$ids[] = $id;
		$peerdata[$id] = array(downloaders => 0, seeders => 0, comments => 0);
	}

	if (count($ids)) {
		$allids = implode(",", $ids);
		$res = sql_query("SELECT COUNT(*) AS c, torrent, seeder FROM peers WHERE torrent IN ($allids) GROUP BY torrent, seeder");
		while ($row = mysql_fetch_assoc($res)) {
			if ($row["seeder"] == "yes")
				$key = "seeders";
			else
				$key = "downloaders";
			$peerdata[$row["torrent"]][$key] = $row["c"];
		}
		$res = sql_query("SELECT COUNT(*) AS c, torrent FROM comments WHERE torrent IN ($allids) GROUP BY torrent");
		while ($row = mysql_fetch_assoc($res)) {
			$peerdata[$row["torrent"]]["comments"] = $row["c"];
		}
	}

	return array($rows, $peerdata);
}

function convertEncoding($str, $charsetFrom, $charsetTo) {
		if(function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($str, $charsetTo, $charsetFrom);
		}
		if(function_exists('iconv')) {
			return iconv($charsetFrom, $charsetTo, $str);
		}
}

// functions.php

if (!function_exists('e')) {
    /**
     * Безопасное экранирование HTML.
     * Принимает: string|int|float|bool|null|array|object
     * Всегда возвращает строку. Исключает deprecation от null в htmlspecialchars().
     *
     * @param mixed $value
     * @param bool  $double_encode — если false, уже-экранированные последовательности не будут переэкранированы
     */
    function e($value, bool $double_encode = true): string
    {
        // Приводим к строке «мягко» и предсказуемо
        if ($value === null) {
            $value = '';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $value = (string)$value;
        } elseif (is_array($value) || is_object($value)) {
            // На случай, если кто-то передал массив/объект в e()
            // JSON_INVALID_UTF8_SUBSTITUTE — подставит � на битых байтах
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($value === false) {
                $value = ''; // крайний случай, если json_encode вернул false
            }
        } else {
            // строка или что-то приводимое к строке
            $value = (string)$value;
        }

        // ENT_SUBSTITUTE — не даст упасть на битой UTF-8, подставит �
        // ENT_QUOTES — экранирует и одинарные, и двойные кавычки (для атрибутов это важно)
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
    }
}


function commenttable(array $rows, string $redaktor = "comment"): void
{
    global $CURUSER, $avatar_max_width, $DEFAULTBASEURL; // ← добавили $DEFAULTBASEURL

    // Безопасный эскейпер
    $esc = static function ($value, bool $double_encode = true): string {
        if ($value === null) {
            $value = '';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $value = (string)$value;
        } elseif (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $value = $json === false ? '' : $json;
        } else {
            $value = (string)$value;
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
    };

    // Нормализация URL аватара с дефолтом и базовым URL
    $avatarUrlNormalize = static function (string $raw) use ($DEFAULTBASEURL): string {
        $raw = trim($raw);
        if ($raw === '') {
            $raw = '/pic/default_avatar.gif';               // дефолтный
        }
        // Абсолютные/протокольные/inline — оставляем
        if (preg_match('~^(https?://|//|data:image/)~i', $raw)) {
            return $raw;
        }
        // Локальный путь: гарантируем ведущий слэш
        if ($raw[0] !== '/') {
            $raw = '/' . $raw;
        }
        $base = rtrim((string)($DEFAULTBASEURL ?? ''), '/');
        return $base !== '' ? ($base . $raw) : $raw;        // если базовый не задан — оставим корневой путь
    };

    // Фолбэк-картинка (абсолютный URL)
    $fallbackAvatarUrl = $avatarUrlNormalize('/pic/default_avatar.gif');

    // Ширина (если глобал не задан)
    $avatarW = (int)($avatar_max_width ?? 100);
    if ($avatarW <= 0) $avatarW = 100;

    foreach ($rows as $row) {
        // Нормализация числовых полей
        $cid = (int)($row['id'] ?? 0);
        $tid = (int)($row['torrentid'] ?? 0);
        $uid = (int)($row['user'] ?? 0);

        // Нормализация строк
        $usernameRaw = (string)($row['username'] ?? '[Аноним]');
        $avatarPath  = (string)($row['avatar'] ?? '');
        $avatarUrl   = $avatarUrlNormalize($avatarPath); // ← теперь всегда валидный URL c дефолтом
        $addedRaw    = (string)($row['added'] ?? '');
        $editedByRaw = (string)($row['editedbyname'] ?? '');
        $editedAtRaw = (string)($row['editedat'] ?? '');

        // Текст комментария (уже HTML от format_comment — НЕ экранируем)
        $comment_html = (string)format_comment((string)($row['text'] ?? ''));
        $comment_text = "<div id=\"comment_text{$cid}\">{$comment_html}</div>\n";

        if (!empty($row['editedby'])) {
            $editedById = (int)$row['editedby'];
            $comment_text .= "<p><span class='small'>Редактировал <a href='/userdetails.php?id={$editedById}'><b>"
                           . $esc($editedByRaw)
                           . "</b></a> в "
                           . $esc($editedAtRaw)
                           . "</span></p>\n";
        }
        ?>
        <div class="c">
        <div class="c1"><div class="c2"><div class="c3"><div class="c4">
        <div class="c5"><div class="c6"><div class="c7"><div class="c8">
        <div class="ci" align="left">
            <div class="c_tit">Автор:
                <a name="comm<?= $cid ?>" href="/userdetails.php?id=<?= $uid ?>" class="altlink_white">
                    <b><?php
                        // Готовый HTML из get_user_class_color — НЕ экранировать!
                        $classVal = (int)($row['class'] ?? 0);
                        echo get_user_class_color($classVal, $usernameRaw);
                    ?></b>
                </a>
            </div>

            <table width="100%" border="0" cellspacing="0" cellpadding="3">
                <tr valign="top">
                    <td style="padding:0;width:5%;" align="center">
                        <img
                            src="<?= $esc($avatarUrl) ?>"
                            width="<?= $avatarW ?>"
                            height="<?= $avatarW ?>" 
                            alt="avatar"
                            loading="lazy"
                            decoding="async"
                            style="object-fit:cover;border-radius:8px"
                            onerror="this.onerror=null;this.src='<?= $esc($fallbackAvatarUrl) ?>';"
                        >
                    </td>
                    <td width="100%" class="text"><?= $comment_text ?></td>
                </tr>
            </table><br>

            <table border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="sbg"><div class="ss1"><div class="rat st">
                            <?= $esc($addedRaw) ?>
                            <div class="cl"></div>
                        </div></div></div>
                    </td>
                    <td><div class="ss2"></div></td>
                </tr>
            </table>

            <div class="s"><div class="s1"><div class="s2">
                <div class="st">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td>
                                <span id="karma<?= $cid ?>">
                                <?php
                                $can_rate  = (int)($row['canrate'] ?? 0);
                                $karma_val = karma((int)($row['karma'] ?? 0));
                                if (!$CURUSER || $can_rate > 0 || $uid === (int)($CURUSER['id'] ?? 0)) {
                                    echo "<img src='/pic/minus-dis.png' title='Нельзя' alt=''> {$karma_val} <img src='/pic/plus-dis.png' title='Нельзя' alt=''>";
                                } else {
                                    echo "<img src='/pic/minus.png' class='karma-btn' data-id='{$cid}' data-type='comment' data-act='minus' style='cursor:pointer;' title='Минус' alt=''> ";
                                    echo $karma_val;
                                    echo " <img src='/pic/plus.png' class='karma-btn' data-id='{$cid}' data-type='comment' data-act='plus' style='cursor:pointer;' title='Плюс' alt=''>";
                                }
                                ?>
                                </span>
                            </td>
                            <td align="right" class="r">
                                <div class="author actions">
                                    <?php if ($CURUSER): ?>
                                        [<a href="javascript:;" class="comment-quote" data-id="<?= $cid ?>" data-tid="<?= $tid ?>">Цитата</a>]
                                        <?php if ((int)($CURUSER["id"] ?? 0) === $uid || get_user_class() >= UC_MODERATOR): ?>
                                            [<a href="javascript:;" class="comment-edit" data-id="<?= $cid ?>" data-tid="<?= $tid ?>">Изменить</a>]
                                        <?php endif; ?>
                                        <?php if (!empty($row["editedby"]) && get_user_class() >= UC_MODERATOR): ?>
                                            [<a href="javascript:;" class="comment-original" data-id="<?= $cid ?>" data-tid="<?= $tid ?>">Оригинал</a>]
                                        <?php endif; ?>
                                        <?php if (get_user_class() >= UC_MODERATOR): ?>
                                            [<a href="javascript:;" class="comment-delete" data-id="<?= $cid ?>" data-tid="<?= $tid ?>">Удалить</a>]
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i>[Аноним]</i>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div></div></div>

        </div></div></div></div></div></div></div></div></div><br>
        <?php
    }
}


function textbbcode(string $form, string $name, string $text = ''): void
{
    global $DEFAULTBASEURL;

    // ---------- эскейпы/нормализация ----------
    $h = static fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $j = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // нормализуем id (допустимые: буквы/цифры/_-:.)
    $textareaId = preg_replace('/[^A-Za-z0-9_\-:.]/', '_', $name) ?: 'bbcode_textarea';

    // ---------- подключаем ассеты один раз ----------
    static $assetsPrinted = false;
    if (!$assetsPrinted) {
        $assetsPrinted = true; ?>
        <!-- SCEditor CSS -->
        <link rel="stylesheet" href="<?= $h($DEFAULTBASEURL) ?>/js/sceditor/themes/default.css">
        <!-- SCEditor core + BBCode + язык (важно: ядро ДО формата) -->
        <script defer src="<?= $h($DEFAULTBASEURL) ?>/js/sceditor/minified/jquery.sceditor.min.js"></script>
        <script defer src="<?= $h($DEFAULTBASEURL) ?>/js/sceditor/minified/formats/bbcode.js"></script>
        <script defer src="<?= $h($DEFAULTBASEURL) ?>/js/sceditor/languages/ru.js"></script>
    <?php }

    // ---------- инициализация конкретно этого поля ----------
    ?>
    <script>
    (function (win, doc) {
        // безопасные значения из PHP
        var BASE = <?= $j($DEFAULTBASEURL) ?>;
        var TA_ID = <?= $j($textareaId) ?>;
        var FORM_NAME = <?= $j($form) ?>;

        function get$() {
            return win.jQuery || win.$ || null;
        }

        function init() {
            var $ = get$();
            if (!$) { console.error('SCEditor init: jQuery not found'); return; }
            if (!$.sceditor || !$.sceditor.BBCodeParser) {
                // подождём, если скрипты ещё грузятся (defer)
                return doc.readyState === 'complete'
                    ? console.error('SCEditor init: sceditor not loaded')
                    : setTimeout(init, 30);
            }

            // CSS.escape может отсутствовать в старых браузерах — делаем лёгкий фолбэк
            var cssEscape = win.CSS && CSS.escape ? CSS.escape : function (s) { return s.replace(/[^A-Za-z0-9_\-:.]/g, '\\$&'); };
            var ta = doc.querySelector('#' + cssEscape(TA_ID));
            if (!ta) { console.error('SCEditor init: textarea #' + TA_ID + ' not found'); return; }
            if (ta.dataset.sceditorInitialized === '1') return; // защита от двойной инициализации
            ta.dataset.sceditorInitialized = '1';

            try {
                $(ta).sceditor({
                    format: 'bbcode',
                    style: BASE + '/js/sceditor/themes/content/default.css',
                    emoticonsRoot: BASE + '/js/sceditor/',
                    width: '100%',
                    height: 250,
                    resizeEnabled: true,
                    locale: 'ru',
                    toolbarExclude: '' // при желании кастомизируй
                });
            } catch (e) {
                console.error('SCEditor init error:', e);
            }
        }

        // дружелюбно ждём DOM
        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }

        // Окно смайлов — безопасно кодируем параметры
        win.openSmilesWindow = function () {
            var url = 'moresmiles.php?form=' + encodeURIComponent(FORM_NAME)
                    + '&text=' + encodeURIComponent(TA_ID);
            win.open(url, 'smiles', 'height=500,width=500,resizable=yes,scrollbars=yes');
        };
    })(window, document);
    </script>

    <!-- Текстовая область -->
    <textarea
        name="<?= $h($name) ?>"
        id="<?= $h($textareaId) ?>"
        style="width:100%;height:250px;"
        placeholder="Введите текст в BBCode…"
        aria-label="BBCode редактор"
        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
        lang="ru" dir="ltr"
    ><?= $h($text) ?></textarea>
    <?php
}


// Очистка строки поиска: убираем спецсимволы, множественные пробелы и приведение к нижнему регистру
function searchfield(string $s): string {
    return preg_replace(
        ['/[^a-z0-9]/si', '/^\s+/s', '/\s+$/s', '/\s+/s'],
        [" ", "", "", " "],
        strtolower($s)
    );
}

// Получение списка жанров (категорий)
function genrelist(): array {
    $ret = [];
    $res = sql_query("SELECT id, name, image FROM categories ORDER BY name ASC") or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($res)) {
        $ret[] = $row;
    }
    return $ret;
}

// Получение списка тэгов по категории
function taggenrelist(int $cat): array {
    $ret = [];
    $res = sql_query("SELECT id, name, howmuch FROM tags WHERE category = " . (int)$cat . " ORDER BY name ASC") or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($res)) {
        $ret[] = $row;
    }
    return $ret;
}

// Генерация HTML-ссылок на тэги
function addtags(string $addtags, int $category): string {
    $tags = '';

    foreach (array_filter(array_map('trim', explode(",", $addtags))) as $tag) {
        $tag_esc = htmlspecialchars($tag);
        $tags .= "<a style=\"font-weight:normal;\" href=\"browse.php?tag={$tag_esc}&incldead=1&cat=$category\">$tag_esc</a>, ";
    }

    return $tags ? rtrim($tags, ', ') : "Нет тэгов";
}

// Цвет ссылки по количеству: красный — 0, зелёный — есть сиды/личеры
function linkcolor(int $num): string {
    return $num > 0 ? "green" : "red";
}

// Генерация изображения рейтинга
function ratingpic(float $num): ?string {
    global $pic_base_url, $tracker_lang;

    $r = round($num * 2) / 2;
    if ($r < 1 || $r > 5) return null;

    $rating_alt = $tracker_lang['rating'] . ": $num / 5";
    return "<img src=\"{$pic_base_url}{$r}.gif\" border=\"0\" alt=\"$rating_alt\" />";
}

// Добавление строки в модкомментарии пользователя
function writecomment(int $userid, string $comment): bool {
    $res = sql_query("SELECT modcomment FROM users WHERE id = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);

    $existing = $arr['modcomment'] ?? '';
    $date = date("d-m-Y");
    $new_comment = "$date - $comment" . ($existing ? "\n$existing" : '');
    $modcom = sqlesc($new_comment);

    sql_query("UPDATE users SET modcomment = $modcom WHERE id = " . (int)$userid) or sqlerr(__FILE__, __LINE__);
    return true;
}



function torrenttable(mysqli_result $res, string $variant = "index"): void
{
    global $pic_base_url, $CURUSER, $use_wait, $tracker_lang, $mc1;

    // ===== helpers =====
    $h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $now = time();

    // ===== wait time (once) =====
    $wait = 0;
    if (!empty($use_wait) && !empty($CURUSER) && (int)$CURUSER['class'] < UC_VIP) {
        $uploaded  = (float)($CURUSER['uploaded']   ?? 0);
        $downloaded= (float)($CURUSER['downloaded'] ?? 0);
        $gigs  = $uploaded / (1024 ** 3);
        $ratio = $downloaded > 0 ? $uploaded / $downloaded : 0.0;

        if ($ratio < 0.5  || $gigs < 5)    $wait = 48;
        elseif ($ratio < 0.65 || $gigs < 6.5) $wait = 24;
        elseif ($ratio < 0.8  || $gigs < 8)   $wait = 12;
        elseif ($ratio < 0.95 || $gigs < 9.5) $wait = 6;
        else $wait = 0;
    }

    // ===== sort links (cheap & strict) =====
    $getSort = (string)($_GET['sort'] ?? '');
    $getType = (string)($_GET['type'] ?? '');
    $dirFor = static function (int $col) use ($getSort, $getType): string {
        return ($getSort === (string)$col && $getType === 'desc') ? 'asc' : 'desc';
    };

    // keep other params (fast, URL-safe)
    $params = $_GET;
    unset($params['sort'], $params['type']);
    // чистим пустые и потенциально вредные значения
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
    }
    $oldlink = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    if ($oldlink !== '') $oldlink .= '&';

    // ===== header (less DOM, better a11y) =====
    // добавил <tbody>, заменил часть <font> на классы (см. CSS-подсказку ниже)
    echo '<tr>'
        . '<td class="colhead" align="center"><img src="pic/torrenttable/genre.gif" alt="Категория" loading="lazy" decoding="async"></td>'
        . '<td class="colhead" align="left"><a href="browse.php?' . $oldlink . 'sort=1&type=' . $h($dirFor(1)) . '" class="altlink_white"><img src="pic/torrenttable/release.gif" alt="Название" loading="lazy" decoding="async"></a></td>';

    if ($wait) {
        echo '<td class="colhead" align="center">' . $h($tracker_lang['wait']) . '</td>';
    }
    if ($variant === 'mytorrents') {
        echo '<td class="colhead" align="center">' . $h($tracker_lang['visible']) . '</td>';
    }

    echo '<td class="colhead" align="center"><a href="browse.php?' . $oldlink . 'sort=3&type=' . $h($dirFor(3)) . '" class="altlink_white"><img src="pic/torrenttable/comments.gif" alt="Комментарии" loading="lazy" decoding="async"></a></td>'
       . '<td class="colhead" align="center"><a href="browse.php?' . $oldlink . 'sort=8&type=' . $h($dirFor(8)) . '" class="altlink_white"><img src="pic/torrenttable/snatched.gif" alt="Скачиваний" loading="lazy" decoding="async"></a></td>'
       . '<td class="colhead" align="center">'
       . '<a href="browse.php?' . $oldlink . 'sort=7&type=' . $h($dirFor(7)) . '" class="altlink_white"><img src="pic/torrenttable/seeders.gif" alt="Сидеры" loading="lazy" decoding="async"></a>'
       . ' | '
       . '<a href="browse.php?' . $oldlink . 'sort=8&type=' . $h($dirFor(8)) . '" class="altlink_white"><img src="pic/torrenttable/leechers.gif" alt="Личеры" loading="lazy" decoding="async"></a>'
       . '</td>'
       . '<td class="colhead" align="center"><a href="browse.php?' . $oldlink . 'sort=9&type=' . $h($dirFor(9)) . '" class="altlink_white"><img src="pic/torrenttable/upped.gif" alt="Аплоадер" loading="lazy" decoding="async"></a></td>'
       . '</tr><tbody>';

    // ===== rows (cache per row; build once, echo once) =====
    while ($row = mysqli_fetch_assoc($res)) {
        // fast scalars & sanitization
        $id        = (int)$row['id'];
        $stickyYes = ($row['sticky'] ?? '') === 'yes';
        $visibleNo = ($row['visible'] ?? '') === 'no';
        $moddedNo  = ($row['modded']  ?? '') === 'no';

        $comments  = (int)($row['comments'] ?? 0);
        $snatched  = (int)($row['times_completed'] ?? 0);
        $seeders   = (int)($row['seeders'] ?? 0);
        $leechers  = (int)($row['leechers'] ?? 0);

        $catId     = (int)($row['category'] ?? 0);
        $catName   = isset($row['cat_name']) ? $h($row['cat_name']) : '';
        $catPic    = isset($row['cat_pic'])  ? $h($row['cat_pic'])  : '';

        $name      = $h($row['name'] ?? '');
        $tagsHtml  = addtags($row['tags'] ?? '', 0); // TBDev helper (возвращает HTML)
        $added     = $row['added'] ?? '';
        $addedTs   = is_numeric($added) ? (int)$added : (int)strtotime($added);

        $ownerId   = (int)($row['owner'] ?? 0);
        $class     = (int)($row['class'] ?? 0);
        $username  = $row['username'] ?? '';

        $cache_key = "torrent_table_row_v2_$id"; // v2 -> сбиваем старый формат

        if (isset($mc1)) {
            $html = $mc1->get($cache_key);
            if ($html !== false) {
                echo $html;
                continue;
            }
        }

        // prebuild parts
        $catCell = '<td align="center" rowspan="2" width="1%" style="padding:0">'
                 . ($catName !== ''
                    ? ('<a href="browse.php?cat=' . $catId . '">'
                        . ($catPic !== ''
                           ? '<img width="55" height="55" loading="lazy" decoding="async" src="' . $h($pic_base_url) . '/cats/' . $catPic . '" alt="' . $catName . '">'
                           : $catName)
                        . '</a>')
                    : '-')
                 . '</td>';

$stickyBadge = $stickyYes 
    ? '<span class="badge badge-sticky" style="color:red;"><b>Прилеплен:</b></span> ' 
    : '';

        // row 1: title
        $row1 = '<tr class="' . ($stickyYes ? 'highlight' : '') . '" style="background:#F5F8FA">'
              . $catCell
              . '<td class="lol" colspan="9" align="left">'
              . $stickyBadge
              . '<a href="details.php?id=' . $id . '"><b>' . $name . '</b></a>'
              . '</td></tr>';

        // row 2: meta
        $meta = '<td class="lol">'
              . ($moddedNo ? '<img src="pic/viewnfo.gif" title="Ожидает проверки модератором" alt="Проверка" loading="lazy" decoding="async"> ' : '')
              . '<img src="pic/edit_com.png" alt="" loading="lazy" decoding="async"> '
              . '<span class="muted">Тэги:</span> ' . $tagsHtml
              . '</td>';

        $waitCell = '';
        if ($wait) {
            $elapsedH = (int)floor(($now - $addedTs) / 3600);
            if ($elapsedH < $wait) {
                // плавный цвет ожидания (красный->розовый), совместим с прежней логикой
                $r = (int)floor(127 * ($wait - $elapsedH) / 48 + 128);
                $color = sprintf('%06X', ($r << 16)); // #RR0000
                $waitCell = '<td class="lol" align="center"><nobr><a href="faq.php#dl8"><span style="color:#' . $color . '">' . number_format($wait - $elapsedH) . ' h</span></a></nobr></td>';
            } else {
                $waitCell = '<td class="lol" align="center"><nobr>' . $h($tracker_lang['no']) . '</nobr></td>';
            }
        }

        $visibleCell = '';
        if ($variant === 'mytorrents') {
            $visibleCell = '<td class="lol" align="right">'
                         . ($visibleNo
                            ? '<span class="badge badge-no">' . $h($tracker_lang['no']) . '</span>'
                            : '<span class="badge badge-yes">' . $h($tracker_lang['yes']) . '</span>')
                         . '</td>';
        }

        $commentsCell = '<td width="5%" class="lol" align="right">' . ($comments ? '<b>' . $comments . '</b>' : '0') . '</td>';
        $snatchedCell = '<td width="5%" class="lol" align="center">' . $snatched . '</td>';
        $peersCell    = '<td width="10%" class="lol" align="center"><b>' . $seeders . '</b> | <b>' . $leechers . '</b></td>';

        $uploaderCell = '<td width="10%" class="lol" align="center">';
        if ($username !== '') {
            // get_user_class_color уже сам вернёт раскрашенный ник; экранируем текст
            $uploaderCell .= '<a href="userdetails.php?id=' . $ownerId . '"><b>' . get_user_class_color($class, $h($username)) . '</b></a>';
        } else {
            $uploaderCell .= '<i>(unknown)</i>';
        }
        $uploaderCell .= '</td>';

        $row2 = '<tr>' . $meta . $waitCell . $visibleCell . $commentsCell . $snatchedCell . $peersCell . $uploaderCell . '</tr>';

        $html = $row1 . $row2;

        if (isset($mc1)) {
            // динамика у seed/leech/comments быстрая — держим кэш коротким
            $mc1->set($cache_key, $html, 90);
        }

        echo $html;
    }

    echo '</tbody>';

    if (($variant === 'index' || $variant === 'bookmarks') && get_user_class() >= UC_MODERATOR) {
        echo '</form>';
    }
}


function hash_pad($hash) {
	return str_pad($hash, 20);
}



function hash_where($name, $hash) {
	$shhash = preg_replace('/ *$/s', "", $hash);
	return "($name = " . sqlesc($hash) . " OR $name = " . sqlesc($shhash) . ")";
}
// Функция генерации иконок пользователя
function get_user_icons(array $arr, bool $big = false): string {
    $icons = [
        'donor'        => 'star16.gif',
        'warned'       => $big ? 'warnedbig.gif' : 'warned.gif',
        'chatwarned'   => 'chatwarned.gif',
        'forumwarned'  => 'forumwarned.gif',
        'disabled'     => $big ? 'disabledbig.gif' : 'disabled.gif',
        'parked'       => 'parked.gif',
    ];
    $style = $big ? "style='margin-left:4pt;vertical-align:middle'" 
                  : "style='margin-left:2pt;vertical-align:middle'";

    $pics = '';

    // Донор
    if (($arr['donor'] ?? '') === 'yes') {
        $pics .= "<img height='15' src='pic/{$icons['donor']}' alt='Донор' $style>";
    }

    // Активность / предупреждён / отключён
    if (($arr['enabled'] ?? '') === 'yes') {
        $warn = $arr['warned'] ?? '';
        $isWarned = !empty($warn) && $warn !== 'no' && $warn !== '0' && $warn !== 0;
        if ($isWarned) {
            $pics .= "<img src='pic/{$icons['warned']}' alt='' title='Предупреждён' $style>";
        }
    } else {
        $pics .= "<img src='pic/{$icons['disabled']}' alt='Отключён' $style>";
    }

    // Бан в чате
    if (($arr['schoutboxpos'] ?? '') === 'no') {
        $pics .= "<img src='pic/{$icons['chatwarned']}' alt='Забанен в чате' $style>";
    }

    // Бан на форуме
    if (($arr['forumban'] ?? '') === 'no') {
        $pics .= "<img src='pic/{$icons['forumwarned']}' alt='Забанен на форуме' $style>";
    }

    // Припаркован
    if (($arr['parked'] ?? '') === 'yes') {
        $pics .= "<img src='pic/{$icons['parked']}' alt='Припаркован' $style>";
    }

    return $pics;
}


function parked() {
    global $CURUSER;

    // Проверка, что пользователь авторизован и массив содержит нужный ключ
    if (is_array($CURUSER) && ($CURUSER["parked"] ?? '') === "yes") {
        stderr($tracker_lang['error'], "Ваш аккаунт припаркован.");
    }
}


/// MOD TOT ///
function div ($first, $second) {
$div = (($first) - (($first) % ($second))) / ($second);
return $div;
}
function datetime ($datetime) {
global $pic_base_url;
$years = div($datetime, 60*60*24*365);
$months = div($datetime, 60*60*24*30);
$weeks = div($datetime, 60*60*24*7);
$days = div($datetime, 60*60*24);
$hours = div($datetime, 60*60);
$minuts = div($datetime, 60);
$seconds = $datetime;
if ($years >= 1) {
$date = "$years лет ";
$date .= $months - $years*12;
$date .= " месяцев";
if ($year % 4) {
if (($datetime % (60*60*24*366))==0) {
$date .="<img src=\"".$pic_base_url."tort_small.gif\" title=\"Ровно год\">";
}
elseif (($datetime % (60*60*24*366))==0 && $year>1) {
$date .="<img src=\"".$pic_base_url."tort_big.gif\" title=\"Годовщина\">";
}
}
else {
if (($datetime % (60*60*24*365))==0) {
$date .="<img src=\"".$pic_base_url."tort_small.gif\" title=\"Ровно год\">";
}
elseif (($datetime % (60*60*24*365))==0 && $year>1) {
$date .="<img src=\"".$pic_base_url."tort_big.gif\" title=\"Годовщина\">";
}
}
}
elseif ($months >= 1 && $years < 1) {
$date = "$months месяцев ";
$date .= $weeks - $months*4;
$date .= " недель";
}
elseif ($weeks >= 1 && $months < 1) {
$date = "$weeks недель ";
$date .= $days - $weeks*7;
$date .= " дней";
}
elseif ($days >= 1 && $weeks < 1) {
$date = "$days дней ";
$date .= $hours - $days*24;
$date .= " часов";
}
elseif ($hours >= 1 && $days < 1) {
$date = "$hours часов ";
$date .= $minuts - $hours*60;
$date .= " минут";
}
elseif ($minuts >= 1 && $hours < 1) {
$date = "$minuts минут ";
$date .= $seconds - $minuts*60;
$date .= " секунд";
}
elseif ($seconds >= 1 && $minuts < 1) {
$date = "$seconds секунд";
}
return $date;
}
/// MOD TOT ///

function mysqli_modified_rows(): int {
	global $mysqli;

	// Получаем строку с информацией о последнем запросе
	$info_str = $mysqli->info;

	// Получаем количество затронутых строк
	$a_rows = $mysqli->affected_rows;

	// Извлекаем число "Rows matched" через preg_match
	$matched = 0;
	if (preg_match('/Rows matched: (\d+)/', $info_str, $matches)) {
		$matched = (int)$matches[1];
	}

	// Возвращаем либо matched, либо affected
	return ($a_rows < 1) ? $matched : $a_rows;
}




/////////////////////////////ФОРУМ/////////////

function parse_referer($cache = false) {

global $refer_parse, $CURUSER, $parser_deny;

/// рефер данные
$referer = (isset($_SERVER["HTTP_REFERER"]) ? htmlentities(utf8_to_win($_SERVER["HTTP_REFERER"])):"");
if (!empty($referer))
$parse_site = parse_url($referer, PHP_URL_HOST);

/// собственные данные о сайте
$site_own = (($_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://").htmlspecialchars_uni($_SERVER['HTTP_HOST']);
if (!empty($site_own))
$parse_owner = parse_url($site_own, PHP_URL_HOST);
//$parse_owner = str_replace("www.","", $parse_owner);
/// сравниваем данные
if (!empty($refer_parse) && !empty($parse_site) && !stristr($parse_site,$parse_owner) && ($parse_owner<>$parse_site) && !in_array($parse_site, $parser_deny)){

$ip = getip();
$ref = ($referer);

$uid = $CURUSER["id"];
if (empty($uid))
$uid = 0;

sql_query("INSERT INTO referrers (parse_url, parse_ref, uid, ip, date, numb, lastdate) VALUES (".sqlesc($parse_site).", ".sqlesc($ref).", ".sqlesc($uid).", ".sqlesc($ip).", ".sqlesc(get_date_time()).", 1, '0000-00-00 00:00:00')");

if (!mysql_insert_id()){
sql_query("UPDATE referrers SET numb=numb+1, lastdate = ".sqlesc(get_date_time())." WHERE uid = ".sqlesc($uid)." AND ip = ".sqlesc($ip)." AND parse_url = ".sqlesc($parse_site)." AND parse_ref = ".sqlesc($ref)) or sqlerr(__FILE__,__LINE__);
}

if (date('i')>=30 && date('i')<=35)
unsql_cache("block-top_refer");
}


/// начало расписания по автоочистке таблицы
if (in_array(date('H'), array("01","07","19","21"))) {

$res = sql_query("SELECT value_u FROM avps WHERE arg = 'referrers'");
$row = mysql_fetch_array($res);
$row_time = $row["value_u"];

if ($row_time < time()){

$ferrers = array();

/// Обнуление Рефералов
sql_query("UPDATE IGNORE referrers SET uid = '0' WHERE referrers.uid <> '0' AND (SELECT COUNT(*) FROM users WHERE users.id = referrers.uid) < '1'") or sqlerr(__FILE__,__LINE__);

$ferrers[] = "Old_uid: ".mysql_modified_rows();


/// Выставление id из users таблицы для Рефералов
sql_query("UPDATE IGNORE referrers SET referrers.uid = (SELECT id FROM users WHERE users.ip = referrers.ip LIMIT 1) WHERE referrers.uid = '0' AND (SELECT COUNT(*) FROM users WHERE users.ip = referrers.ip) = '1'") or sqlerr(__FILE__,__LINE__);

$ferrers[] = "New_uid: ".mysql_modified_rows();

sql_query("DELETE FROM referrers WHERE parse_ref = '' OR parse_url = ''") or sqlerr(__FILE__,__LINE__);

$ferrers[] = "Del_uid: ".mysql_affected_rows();

if (empty($row_time))
sql_query("INSERT INTO avps (arg, value_u, value_i, value_s) VALUES ('referrers', ".sqlesc((time()+86400*1)).", '_', ".sqlesc(implode(", ", $ferrers)).")");
elseif (!empty($row_time))
sql_query("UPDATE avps SET value_u = ".sqlesc((time()+86400*1)).", value_i = '_', value_s = ".sqlesc(implode(", ", $ferrers))." WHERE arg = 'referrers'") or sqlerr(__FILE__,__LINE__);

}
}
/// конец расписания по автоочистке таблицы

}





function utf8_to_win(string $str, string $to = 'w'): string
{
    // Соответствие алиасов -> реально существующие кодировки iconv/mbstring
    static $encMap = [
        'w' => 'Windows-1251', // cp1251
        'i' => 'ISO-8859-5',
        'k' => 'CP866',
        'a' => 'KOI8-R',
        'd' => 'KOI8-R',       // исторический алиас
        'm' => 'MacCyrillic',
    ];

    $enc = $encMap[$to] ?? null;

    // 1) Быстрый путь: iconv / mb_convert_encoding
    if ($enc) {
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', $enc . '//IGNORE', $str);
            if ($r !== false) return $r;
        }
        if (function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($str, $enc, 'UTF-8');
            if ($r !== false) return $r;
        }
        // если обе функции недоступны/не справились — пойдём «ручным» путём ниже
    }

    // 2) Ручное маппирование (как у тебя). Работает только для тех таблиц, что заполнены.
    $outstr = '';
    $recode = [];

    // !!! ключи массивов — в кавычках
    $recode['k'] = [0x2500,0x2502,0x250c,0x2510,0x2514,0x2518,0x251c,0x2524,0x252c,0x2534,0x253c,0x2580,0x2584,0x2588,0x258c,0x2590,0x2591,0x2592,0x2593,0x2320,0x25a0,0x2219,0x221a,0x2248,0x2264,0x2265,0x00a0,0x2321,0x00b0,0x00b2,0x00b7,0x00f7,0x2550,0x2551,0x2552,0x0451,0x2553,0x2554,0x2555,0x2556,0x2557,0x2558,0x2559,0x255a,0x255b,0x255c,0x255d,0x255e,0x255f,0x2560,0x2561,0x0401,0x2562,0x2563,0x2564,0x2565,0x2566,0x2567,0x2568,0x2569,0x256a,0x256b,0x256c,0x00a9,0x044e,0x0430,0x0431,0x0446,0x0434,0x0435,0x0444,0x0433,0x0445,0x0438,0x0439,0x043a,0x043b,0x043c,0x043d,0x043e,0x043f,0x044f,0x0440,0x0441,0x0442,0x0443,0x0436,0x0432,0x044c,0x044b,0x0437,0x0448,0x044d,0x0449,0x0447,0x044a,0x042e,0x0410,0x0411,0x0426,0x0414,0x0415,0x0424,0x0413,0x0425,0x0418,0x0419,0x041a,0x041b,0x041c,0x041d,0x041e,0x041f,0x042f,0x0420,0x0421,0x0422,0x0423,0x0416,0x0412,0x042c,0x042b,0x0417,0x0428,0x042d,0x0429,0x0427,0x042a];

    $recode['w'] = [0x0402,0x0403,0x201A,0x0453,0x201E,0x2026,0x2020,0x2021,0x20AC,0x2030,0x0409,0x2039,0x040A,0x040C,0x040B,0x040F,0x0452,0x2018,0x2019,0x201C,0x201D,0x2022,0x2013,0x2014,0x0000,0x2122,0x0459,0x203A,0x045A,0x045C,0x045B,0x045F,0x00A0,0x040E,0x045E,0x0408,0x00A4,0x0490,0x00A6,0x00A7,0x0401,0x00A9,0x0404,0x00AB,0x00AC,0x00AD,0x00AE,0x0407,0x00B0,0x00B1,0x0406,0x0456,0x0491,0x00B5,0x00B6,0x00B7,0x0451,0x2116,0x0454,0x00BB,0x0458,0x0405,0x0455,0x0457,0x0410,0x0411,0x0412,0x0413,0x0414,0x0415,0x0416,0x0417,0x0418,0x0419,0x041A,0x041B,0x041C,0x041D,0x041E,0x041F,0x0420,0x0421,0x0422,0x0423,0x0424,0x0425,0x0426,0x0427,0x0428,0x0429,0x042A,0x042B,0x042C,0x042D,0x042E,0x042F,0x0430,0x0431,0x0432,0x0433,0x0434,0x0435,0x0436,0x0437,0x0438,0x0439,0x043A,0x043B,0x043C,0x043D,0x043E,0x043F,0x0440,0x0441,0x0442,0x0443,0x0444,0x0445,0x0446,0x0447,0x0448,0x0449,0x044A,0x044B,0x044C,0x044D,0x044E,0x044F];

    $recode['i'] = [0x0080,0x0081,0x0082,0x0083,0x0084,0x0085,0x0086,0x0087,0x0088,0x0089,0x008A,0x008B,0x008C,0x008D,0x008E,0x008F,0x0090,0x0091,0x0092,0x0093,0x0094,0x0095,0x0096,0x0097,0x0098,0x0099,0x009A,0x009B,0x009C,0x009D,0x009E,0x009F,0x00A0,0x0401,0x0402,0x0403,0x0404,0x0405,0x0406,0x0407,0x0408,0x0409,0x040A,0x040B,0x040C,0x00AD,0x040E,0x040F,0x0410,0x0411,0x0412,0x0413,0x0414,0x0415,0x0416,0x0417,0x0418,0x0419,0x041A,0x041B,0x041C,0x041D,0x041E,0x041F,0x0420,0x0421,0x0422,0x0423,0x0424,0x0425,0x0426,0x0427,0x0428,0x0429,0x042A,0x042B,0x042C,0x042D,0x042E,0x042F,0x0430,0x0431,0x0432,0x0433,0x0434,0x0435,0x0436,0x0437,0x0438,0x0439,0x043A,0x043B,0x043C,0x043D,0x043E,0x043F,0x0440,0x0441,0x0442,0x0443,0x0444,0x0445,0x0446,0x0447,0x0448,0x0449,0x044A,0x044B,0x044C,0x044D,0x044E,0x044F,0x2116,0x0451,0x0452,0x0453,0x0454,0x0455,0x0456,0x0457,0x0458,0x0459,0x045A,0x045B,0x045C,0x00A7,0x045E,0x045F];

    // $recode['a'] / ['m'] у тебя не заполнены; если потребуется ручной фолбэк — их нужно заполнить.
    // $recode['d'] = $recode['a']; — ок, если 'a' задана.

    if (!isset($recode[$to])) {
        return $str; // неизвестная цель — отдадим как есть
    }

    $and = 0x3F;
    $len = strlen($str);

    for ($i = 0; $i < $len; $i++) {
        $letter = 0x0;
        $octet = [];
        $octet[0] = ord($str[$i]);
        $octets = 1;
        $andfirst = 0x7F;

        if (($octet[0] >> 1) == 0x7E) { $octets = 6; $andfirst = 0x01; }
        elseif (($octet[0] >> 2) == 0x3E) { $octets = 5; $andfirst = 0x03; }
        elseif (($octet[0] >> 3) == 0x1E) { $octets = 4; $andfirst = 0x07; }
        elseif (($octet[0] >> 4) == 0x0E) { $octets = 3; $andfirst = 0x0F; }
        elseif (($octet[0] >> 5) == 0x06) { $octets = 2; $andfirst = 0x1F; }

        $octet[0] &= $andfirst;
        $octet[0] <<= (($octets - 1) * 6);
        $letter += $octet[0];

        for ($j = 1; $j < $octets; $j++) {
            if (++$i >= $len) break 2; // защита выхода за строку
            $octet[$j] = ord($str[$i]) & $and;
            $octet[$j] <<= (($octets - 1 - $j) * 6);
            $letter += $octet[$j];
        }

        if ($letter < 0x80) {
            $outstr .= chr($letter);
        } else {
            $pos = array_search($letter, $recode[$to], true);
            if ($pos !== false) {
                $outstr .= chr($pos + 128);
            }
            // иначе пропускаем символ (или поставить $outstr .= '?';)
        }
    }

    return $outstr;
}



function normaltime($input, bool $withTime = false, ?DateTimeZone $tz = null): string
{
    // 1) Нормализуем вход в DateTimeImmutable
    try {
        if ($input instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($input);
        } elseif (is_int($input)) {
            // timestamp
            $dt = (new DateTimeImmutable('@' . $input))->setTimezone($tz ?? new DateTimeZone(date_default_timezone_get()));
        } elseif (is_string($input) && $input !== '') {
            $dt = new DateTimeImmutable($input, $tz ?? null);
            if ($tz) $dt = $dt->setTimezone($tz);
        } else {
            return '';
        }
    } catch (Throwable $e) {
        // Непарсибельная строка
        return '';
    }

    // 2) Если есть intl — используем правильные русские формы месяцев (родительный падеж)
    if (class_exists(IntlDateFormatter::class)) {
        // В русской локали "d MMMM" даёт родительный падеж: "23 октября"
        $pattern = $withTime ? "d MMMM y 'в' HH:mm:ss" : "d MMMM y";
        $fmt = new IntlDateFormatter(
            'ru_RU',
            IntlDateFormatter::NONE,
            $withTime ? IntlDateFormatter::MEDIUM : IntlDateFormatter::NONE,
            $dt->getTimezone()->getName(),
            IntlDateFormatter::GREGORIAN,
            $pattern
        );

        $out = $fmt->format($dt);
        if ($out !== false) {
            // По умолчанию месяцы в нижнем регистре — можно оставить как есть.
            // Если хотите заглавную букву в начале (как в старой версии), раскомментируйте строку ниже:
            // $out = mb_strtoupper(mb_substr($out, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($out, 1, null, 'UTF-8');
            return $out;
        }
        // Если что-то пошло не так — упадём в фоллбэк ниже.
    }

    // 3) Фоллбэк без intl (поведение похоже на вашу оригинальную функцию)
    //    Сначала формируем англ. шаблон, потом заменяем месяцы на русские (родительный падеж, с маленькой буквы)
    $search  = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $replace = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];

    $format = $withTime ? 'j F Y \в H:i:s' : 'j F Y';
    $data   = $dt->format($format);

    // Меняем месяц на русское слово
    $data = str_replace($search, $replace, $data);

    // Если вы хотите заглавную первую букву (как раньше): раскомментируйте
    // $data = mb_strtoupper(mb_substr($data, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($data, 1, null, 'UTF-8');

    return $data;
}


/////////////////////////////ФОРУМ/////////////


?>