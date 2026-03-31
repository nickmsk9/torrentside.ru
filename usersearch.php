<?php
declare(strict_types=1);

require "include/bittorrent.php";

gzip();
dbconn();
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'], "Отказано в доступе.");
}

function usersearch_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function usersearch_self_url(): string
{
    $self = (string)($_SERVER['PHP_SELF'] ?? 'usersearch.php');
    $self = explode('?', $self, 2)[0];
    return $self !== '' ? $self : 'usersearch.php';
}

function usersearch_get(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

function usersearch_has_wildcard(string $text): bool
{
    return strpbrk($text, '*?%_') !== false;
}

function usersearch_like_pattern(string $text, bool $wrapPlainWithPercents = false): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (usersearch_has_wildcard($text)) {
        return str_replace(['*', '?'], ['%', '_'], $text);
    }

    $escaped = sqlwildcardesc($text);
    return $wrapPlainWithPercents ? '%' . $escaped . '%' : $escaped;
}

function usersearch_add_token_filter(array &$conditions, string $column, string $input, bool $wrapPlainWithPercents = false): void
{
    $input = trim($input);
    if ($input === '') {
        return;
    }

    $include = [];
    $exclude = [];

    foreach (preg_split('/\s+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        if ($token === '') {
            continue;
        }

        if ($token[0] === '~') {
            $token = substr($token, 1);
            if ($token !== '') {
                $exclude[] = $token;
            }
            continue;
        }

        $include[] = $token;
    }

    if ($include !== []) {
        $parts = [];
        foreach ($include as $token) {
            if (!usersearch_has_wildcard($token) && !$wrapPlainWithPercents) {
                $parts[] = $column . " = " . sqlesc($token);
            } else {
                $parts[] = $column . " LIKE " . sqlesc(usersearch_like_pattern($token, $wrapPlainWithPercents));
            }
        }
        if ($parts !== []) {
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }
    }

    if ($exclude !== []) {
        $parts = [];
        foreach ($exclude as $token) {
            if (!usersearch_has_wildcard($token) && !$wrapPlainWithPercents) {
                $parts[] = $column . " = " . sqlesc($token);
            } else {
                $parts[] = $column . " LIKE " . sqlesc(usersearch_like_pattern($token, $wrapPlainWithPercents));
            }
        }
        if ($parts !== []) {
            $conditions[] = 'NOT (' . implode(' OR ', $parts) . ')';
        }
    }
}

function usersearch_parse_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('/', '-', $value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

function usersearch_normalize_ip_mask(string $mask): ?string
{
    $mask = trim($mask);
    if ($mask === '') {
        return null;
    }

    if ($mask[0] === '/') {
        $cidr = (int)substr($mask, 1);
        if (!ctype_digit(substr($mask, 1)) || $cidr < 0 || $cidr > 32) {
            return null;
        }

        if ($cidr === 0) {
            return '0.0.0.0';
        }

        return long2ip((int)(0xFFFFFFFF << (32 - $cidr)));
    }

    return filter_var($mask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $mask : null;
}

function usersearch_add_numeric_range(array &$conditions, string $column, string $from, string $to, int $type, float $multiplier, string $label): void
{
    if ($from === '') {
        return;
    }

    if (!is_numeric($from) || (float)$from < 0) {
        stderr('Ошибка', 'Неверное значение для поля "' . $label . '".');
    }

    $fromValue = (float)$from * $multiplier;

    if ($type === 3) {
        if ($to === '' || !is_numeric($to) || (float)$to < (float)$from) {
            stderr('Ошибка', 'Для поля "' . $label . '" нужен корректный второй диапазон.');
        }

        $toValue = (float)$to * $multiplier;
        $conditions[] = sprintf('%s BETWEEN %s AND %s', $column, $fromValue, $toValue);
        return;
    }

    if ($type === 2) {
        $conditions[] = sprintf('%s < %s', $column, $fromValue);
        return;
    }

    if ($type === 1) {
        $conditions[] = sprintf('%s > %s', $column, $fromValue);
        return;
    }

    $delta = 0.004 * $multiplier;
    $conditions[] = sprintf('%s BETWEEN %s AND %s', $column, $fromValue - $delta, $fromValue + $delta);
}

function usersearch_add_date_range(array &$conditions, string $column, string $from, string $to, int $type, string $label): void
{
    if ($from === '') {
        return;
    }

    $fromDate = usersearch_parse_date($from);
    if ($fromDate === null) {
        stderr('Ошибка', 'Неверная дата в поле "' . $label . '".');
    }

    if ($type === 0) {
        $conditions[] = sprintf('%s >= %s AND %s < DATE_ADD(%s, INTERVAL 1 DAY)', $column, sqlesc($fromDate), $column, sqlesc($fromDate));
        return;
    }

    if ($type === 3) {
        $toDate = usersearch_parse_date($to);
        if ($toDate === null || strcmp($toDate, $fromDate) < 0) {
            stderr('Ошибка', 'Для поля "' . $label . '" нужен корректный второй диапазон.');
        }

        $conditions[] = sprintf('%s BETWEEN %s AND DATE_ADD(%s, INTERVAL 1 DAY)', $column, sqlesc($fromDate), sqlesc($toDate));
        return;
    }

    if ($type === 1) {
        $conditions[] = $column . ' < ' . sqlesc($fromDate);
        return;
    }

    $conditions[] = $column . ' >= ' . sqlesc($fromDate);
}

function usersearch_add_ratio_filter(array &$conditions, string $from, string $to, int $type): void
{
    $from = trim($from);
    if ($from === '') {
        return;
    }

    if ($from === '---') {
        $conditions[] = "u.uploaded = 0 AND u.downloaded = 0";
        return;
    }

    if (strtolower(substr($from, 0, 3)) === 'inf') {
        $conditions[] = "u.uploaded > 0 AND u.downloaded = 0";
        return;
    }

    if (!is_numeric($from) || (float)$from < 0) {
        stderr('Ошибка', 'Неверный рейтинг.');
    }

    $ratio = (float)$from;
    $ratioExpr = '(u.uploaded / NULLIF(u.downloaded, 0))';
    $conditions[] = 'u.downloaded > 0';

    if ($type === 3) {
        if ($to === '' || !is_numeric($to) || (float)$to < $ratio) {
            stderr('Ошибка', 'Нужен корректный второй рейтинг.');
        }
        $conditions[] = sprintf('%s BETWEEN %s AND %s', $ratioExpr, $ratio, (float)$to);
        return;
    }

    if ($type === 2) {
        $conditions[] = sprintf('%s < %s', $ratioExpr, $ratio);
        return;
    }

    if ($type === 1) {
        $conditions[] = sprintf('%s > %s', $ratioExpr, $ratio);
        return;
    }

    $conditions[] = sprintf('%s BETWEEN %s AND %s', $ratioExpr, $ratio - 0.004, $ratio + 0.004);
}

function usersearch_ratio_html(int|float $uploaded, int|float $downloaded): string
{
    $uploaded = (float)$uploaded;
    $downloaded = (float)$downloaded;

    if ($downloaded > 0) {
        $ratio = number_format($uploaded / $downloaded, 2);
        return '<span style="color:' . get_ratio_color($ratio) . '">' . $ratio . '</span>';
    }

    if ($uploaded > 0) {
        return 'Inf.';
    }

    return '---';
}

function usersearch_has_filters(array $filters): bool
{
    foreach ($filters as $value) {
        if (is_bool($value)) {
            if ($value) {
                return true;
            }
            continue;
        }

        if ((string)$value !== '' && (string)$value !== '0') {
            return true;
        }
    }

    return false;
}

function usersearch_format_datetime(?string $value, int $tzoffset): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '---';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return usersearch_h($value);
    }

    return usersearch_h(display_date_time($timestamp, $tzoffset));
}

$filters = [
    'n' => usersearch_get('n'),
    'r' => usersearch_get('r'),
    'r2' => usersearch_get('r2'),
    'rt' => max(0, min(3, (int)usersearch_get('rt'))),
    'st' => max(0, min(2, (int)usersearch_get('st'))),
    'em' => usersearch_get('em'),
    'ip' => usersearch_get('ip'),
    'as' => max(0, min(2, (int)usersearch_get('as'))),
    'co' => usersearch_get('co'),
    'ma' => usersearch_get('ma'),
    'c' => max(0, (int)usersearch_get('c')),
    'd' => usersearch_get('d'),
    'd2' => usersearch_get('d2'),
    'dt' => max(0, min(3, (int)usersearch_get('dt'))),
    'ul' => usersearch_get('ul'),
    'ul2' => usersearch_get('ul2'),
    'ult' => max(0, min(3, (int)usersearch_get('ult'))),
    'do' => max(0, min(2, (int)usersearch_get('do'))),
    'ls' => usersearch_get('ls'),
    'ls2' => usersearch_get('ls2'),
    'lst' => max(0, min(3, (int)usersearch_get('lst'))),
    'dl' => usersearch_get('dl'),
    'dl2' => usersearch_get('dl2'),
    'dlt' => max(0, min(3, (int)usersearch_get('dlt'))),
    'w' => max(0, min(2, (int)usersearch_get('w'))),
    'ac' => usersearch_get('ac') === '1',
    'dip' => usersearch_get('dip') === '1',
];

$conditions = [];

usersearch_add_token_filter($conditions, 'u.username', $filters['n']);
usersearch_add_token_filter($conditions, 'u.email', $filters['em']);
usersearch_add_token_filter($conditions, 'u.modcomment', $filters['co'], true);

if ($filters['c'] > 1) {
    $conditions[] = 'u.class = ' . max(0, $filters['c'] - 2);
}

if ($filters['ip'] !== '') {
    if (!filter_var($filters['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        stderr('Ошибка', 'Неверный IP-адрес.');
    }

    if ($filters['ma'] === '' || $filters['ma'] === '255.255.255.255') {
        $conditions[] = 'u.ip = ' . sqlesc($filters['ip']);
    } else {
        $mask = usersearch_normalize_ip_mask($filters['ma']);
        if ($mask === null) {
            stderr('Ошибка', 'Неверная маска подсети.');
        }
        $conditions[] = sprintf(
            "INET_ATON(u.ip) & INET_ATON(%s) = INET_ATON(%s) & INET_ATON(%s)",
            sqlesc($mask),
            sqlesc($filters['ip']),
            sqlesc($mask)
        );
    }
}

usersearch_add_ratio_filter($conditions, $filters['r'], $filters['r2'], $filters['rt']);
usersearch_add_numeric_range($conditions, 'u.uploaded', $filters['ul'], $filters['ul2'], $filters['ult'], 1073741824.0, 'Раздал');
usersearch_add_numeric_range($conditions, 'u.downloaded', $filters['dl'], $filters['dl2'], $filters['dlt'], 1073741824.0, 'Скачал');
usersearch_add_date_range($conditions, 'u.added', $filters['d'], $filters['d2'], $filters['dt'], 'Регистрация');
usersearch_add_date_range($conditions, 'u.last_access', $filters['ls'], $filters['ls2'], $filters['lst'], 'Последняя активность');

if ($filters['st'] === 1) {
    $conditions[] = "u.status = 'confirmed'";
} elseif ($filters['st'] === 2) {
    $conditions[] = "u.status = 'pending'";
}

if ($filters['as'] === 1) {
    $conditions[] = "u.enabled = 'yes'";
} elseif ($filters['as'] === 2) {
    $conditions[] = "u.enabled = 'no'";
}

if ($filters['do'] === 1) {
    $conditions[] = "u.donor = 'yes'";
} elseif ($filters['do'] === 2) {
    $conditions[] = "u.donor = 'no'";
}

if ($filters['w'] === 1) {
    $conditions[] = "u.warned = 'yes'";
} elseif ($filters['w'] === 2) {
    $conditions[] = "u.warned = 'no'";
}

if ($filters['dip']) {
    $conditions[] = "u.ip <> ''";
    $conditions[] = "EXISTS (SELECT 1 FROM users u2 WHERE u2.ip = u.ip AND u2.enabled = 'no')";
}

if ($filters['ac']) {
    $conditions[] = "EXISTS (SELECT 1 FROM peers p WHERE p.userid = u.id)";
}

$showResults = usersearch_has_filters($filters);
$count = 0;
$results = null;
$pagertop = '';
$pagerbottom = '';

if ($showResults) {
    $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $countRes = sql_query("SELECT COUNT(*) AS total FROM users u {$whereSql}") or sqlerr(__FILE__, __LINE__);
    $countRow = mysqli_fetch_assoc($countRes);
    $count = (int)($countRow['total'] ?? 0);

    if ($count > 0) {
        $queryArgs = [];
        foreach ($filters as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $queryArgs[$key] = '1';
                }
                continue;
            }

            if ((string)$value !== '' && (string)$value !== '0') {
                $queryArgs[$key] = (string)$value;
            }
        }

        $pagerHref = usersearch_self_url() . '?';
        if ($queryArgs !== []) {
            $pagerHref .= http_build_query($queryArgs) . '&';
        }

        [$pagertop, $pagerbottom, $limit] = pager(30, $count, $pagerHref);

        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.status,
                u.added,
                u.last_access,
                u.ip,
                u.class,
                u.uploaded,
                u.downloaded,
                u.donor,
                u.enabled,
                u.warned,
                COALESCE(pa.pul, 0) AS pul,
                COALESCE(pa.pdl, 0) AS pdl,
                COALESCE(ca.comments_count, 0) AS comments_count,
                EXISTS (
                    SELECT 1
                    FROM bans b
                    WHERE u.ip <> ''
                      AND INET_ATON(u.ip) BETWEEN b.first AND b.last
                ) AS ip_banned
            FROM users u
            LEFT JOIN (
                SELECT userid, SUM(uploaded) AS pul, SUM(downloaded) AS pdl
                FROM peers
                GROUP BY userid
            ) pa ON pa.userid = u.id
            LEFT JOIN (
                SELECT user AS userid, COUNT(*) AS comments_count
                FROM comments
                GROUP BY user
            ) ca ON ca.userid = u.id
            {$whereSql}
            ORDER BY u.last_access DESC, u.id DESC
            {$limit}
        ";

        $results = sql_query($sql) or sqlerr(__FILE__, __LINE__);
    }
}

$self = usersearch_self_url();
$highlight = ' bgcolor="#BBAF9B"';
$tzoffset = (int)($CURUSER['tzoffset'] ?? 0);

stdhead("Административный поиск");
echo "<h1>Административный поиск</h1>\n";
echo '<p align="center">(<a href="' . usersearch_h($self) . '?h=1">Инструкция</a>)&nbsp;-&nbsp;(<a href="' . usersearch_h($self) . '">Сброс</a>)</p>';
echo '<p align="center">Быстрые выборки: '
    . '<a href="' . usersearch_h($self) . '?st=2">Неподтвержденные</a> | '
    . '<a href="' . usersearch_h($self) . '?as=2">Отключенные</a> | '
    . '<a href="' . usersearch_h($self) . '?w=1">С предупреждением</a> | '
    . '<a href="' . usersearch_h($self) . '?ac=1">Сейчас активны</a> | '
    . '<a href="' . usersearch_h($self) . '?do=1">Доноры</a>'
    . '</p>';

if (usersearch_get('h') !== '') {
    begin_frame('Как искать');
    echo '<ul>'
        . '<li>Пустые поля игнорируются, а поиск запускается только по выбранным фильтрам.</li>'
        . '<li>В имени, email и комментарии работают `*` и `?`, а префикс `~` исключает совпадения.</li>'
        . '<li>Для рейтинга доступны `Inf` и `---`, а для объёмов используются гигабайты.</li>'
        . '<li>`Только активные` берёт пользователей с живыми записями в `peers`, а `Забаненные IP` ищет совпадения с отключенными аккаунтами по IP.</li>'
        . '<li>Колонки `pR`, `pUL`, `pDL` показывают текущий прогресс по активным пирам.</li>'
        . '</ul>';
    end_frame();
}

echo '<form method="get" action="' . usersearch_h($self) . '">';
echo '<table border="1" cellspacing="0" cellpadding="5">';
echo '<tr>';
echo '<td valign="middle" class="rowhead">Имя:</td>';
echo '<td' . ($filters['n'] !== '' ? $highlight : '') . '><input name="n" type="text" value="' . usersearch_h($filters['n']) . '" size="35"></td>';
echo '<td valign="middle" class="rowhead">Рейтинг:</td>';
echo '<td' . ($filters['r'] !== '' ? $highlight : '') . '><select name="rt">';
foreach (['равен', 'выше', 'ниже', 'между'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['rt'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select> <input name="r" type="text" value="' . usersearch_h($filters['r']) . '" size="5" maxlength="8"> <input name="r2" type="text" value="' . usersearch_h($filters['r2']) . '" size="5" maxlength="8"></td>';
echo '<td valign="middle" class="rowhead">Статус:</td>';
echo '<td' . ($filters['st'] !== 0 ? $highlight : '') . '><select name="st">';
foreach (['(Любой)', 'Подтвержден', 'Не подтвержден'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['st'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select></td>';
echo '</tr>';

echo '<tr>';
echo '<td valign="middle" class="rowhead">Email:</td>';
echo '<td' . ($filters['em'] !== '' ? $highlight : '') . '><input name="em" type="text" value="' . usersearch_h($filters['em']) . '" size="35"></td>';
echo '<td valign="middle" class="rowhead">IP:</td>';
echo '<td' . ($filters['ip'] !== '' ? $highlight : '') . '><input name="ip" type="text" value="' . usersearch_h($filters['ip']) . '" maxlength="17"></td>';
echo '<td valign="middle" class="rowhead">Отключен:</td>';
echo '<td' . ($filters['as'] !== 0 ? $highlight : '') . '><select name="as">';
foreach (['(Любой)', 'Нет', 'Да'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['as'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select></td>';
echo '</tr>';

echo '<tr>';
echo '<td valign="middle" class="rowhead">Комментарий:</td>';
echo '<td' . ($filters['co'] !== '' ? $highlight : '') . '><input name="co" type="text" value="' . usersearch_h($filters['co']) . '" size="35"></td>';
echo '<td valign="middle" class="rowhead">Маска:</td>';
echo '<td' . ($filters['ma'] !== '' ? $highlight : '') . '><input name="ma" type="text" value="' . usersearch_h($filters['ma']) . '" maxlength="17"></td>';
echo '<td valign="middle" class="rowhead">Класс:</td>';
echo '<td' . ($filters['c'] > 1 ? $highlight : '') . '><select name="c"><option value="1">(Любой)</option>';
for ($i = 2;; ++$i) {
    $className = get_user_class_name($i - 2);
    if (!$className) {
        break;
    }
    echo '<option value="' . $i . '"' . ($filters['c'] === $i ? ' selected' : '') . '>' . usersearch_h($className) . '</option>';
}
echo '</select></td>';
echo '</tr>';

echo '<tr>';
echo '<td valign="middle" class="rowhead">Регистрация:</td>';
echo '<td' . ($filters['d'] !== '' ? $highlight : '') . '><select name="dt">';
foreach (['в', 'раньше', 'после', 'между'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['dt'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select> <input name="d" type="text" value="' . usersearch_h($filters['d']) . '" size="12" maxlength="10"> <input name="d2" type="text" value="' . usersearch_h($filters['d2']) . '" size="12" maxlength="10"></td>';
echo '<td valign="middle" class="rowhead">Раздал:</td>';
echo '<td' . ($filters['ul'] !== '' ? $highlight : '') . '><select name="ult">';
foreach (['ровно', 'больше', 'меньше', 'между'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['ult'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select> <input name="ul" type="text" size="8" maxlength="7" value="' . usersearch_h($filters['ul']) . '"> <input name="ul2" type="text" size="8" maxlength="7" value="' . usersearch_h($filters['ul2']) . '"></td>';
echo '<td valign="middle" class="rowhead">Донор:</td>';
echo '<td' . ($filters['do'] !== 0 ? $highlight : '') . '><select name="do">';
foreach (['(Любой)', 'Да', 'Нет'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['do'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select></td>';
echo '</tr>';

echo '<tr>';
echo '<td valign="middle" class="rowhead">Последняя активность:</td>';
echo '<td' . ($filters['ls'] !== '' ? $highlight : '') . '><select name="lst">';
foreach (['в', 'раньше', 'после', 'между'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['lst'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select> <input name="ls" type="text" value="' . usersearch_h($filters['ls']) . '" size="12" maxlength="10"> <input name="ls2" type="text" value="' . usersearch_h($filters['ls2']) . '" size="12" maxlength="10"></td>';
echo '<td valign="middle" class="rowhead">Скачал:</td>';
echo '<td' . ($filters['dl'] !== '' ? $highlight : '') . '><select name="dlt">';
foreach (['ровно', 'больше', 'меньше', 'между'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['dlt'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select> <input name="dl" type="text" size="8" maxlength="7" value="' . usersearch_h($filters['dl']) . '"> <input name="dl2" type="text" size="8" maxlength="7" value="' . usersearch_h($filters['dl2']) . '"></td>';
echo '<td valign="middle" class="rowhead">Предупрежден:</td>';
echo '<td' . ($filters['w'] !== 0 ? $highlight : '') . '><select name="w">';
foreach (['(Любой)', 'Да', 'Нет'] as $index => $label) {
    echo '<option value="' . $index . '"' . ($filters['w'] === $index ? ' selected' : '') . '>' . $label . '</option>';
}
echo '</select></td>';
echo '</tr>';

echo '<tr>';
echo '<td class="rowhead"></td><td></td>';
echo '<td valign="middle" class="rowhead">Только активные:</td>';
echo '<td' . ($filters['ac'] ? $highlight : '') . '><input name="ac" type="checkbox" value="1"' . ($filters['ac'] ? ' checked' : '') . '></td>';
echo '<td valign="middle" class="rowhead">Забаненные IP:</td>';
echo '<td' . ($filters['dip'] ? $highlight : '') . '><input name="dip" type="checkbox" value="1"' . ($filters['dip'] ? ' checked' : '') . '></td>';
echo '</tr>';

echo '<tr><td colspan="6" align="center"><input name="submit" type="submit" class="btn" value="Искать"></td></tr>';
echo '</table>';
echo '<br><br>';
echo '</form>';

if ($showResults) {
    if ($count === 0 || !$results) {
        stdmsg('Внимание', 'Пользователь не был найден.');
    } else {
        begin_frame('Результаты поиска [' . $count . ']');
        if ($count > 30) {
            echo $pagertop;
        }

        echo '<table border="1" cellspacing="0" cellpadding="5" width="100%">';
        echo '<tr>'
            . '<td class="colhead" align="left">Пользователь</td>'
            . '<td class="colhead" align="left">Рейтинг</td>'
            . '<td class="colhead" align="left">IP</td>'
            . '<td class="colhead" align="left">Email</td>'
            . '<td class="colhead" align="left">Регистрация</td>'
            . '<td class="colhead" align="left">Последняя активность</td>'
            . '<td class="colhead" align="left">Статус</td>'
            . '<td class="colhead" align="left">Включен</td>'
            . '<td class="colhead">pR</td>'
            . '<td class="colhead">pUL</td>'
            . '<td class="colhead">pDL</td>'
            . '<td class="colhead">История</td>'
            . '</tr>';

        while ($user = mysqli_fetch_assoc($results)) {
            $userId = (int)$user['id'];
            $username = get_user_class_color((int)$user['class'], usersearch_h($user['username']));
            $userLink = '<a href="userdetails.php?id=' . $userId . '"><b>' . $username . '</b></a>' . get_user_icons($user);

            $commentCount = (int)$user['comments_count'];
            $commentLink = $commentCount > 0
                ? '<a href="userhistory.php?action=viewcomments&id=' . $userId . '">' . $commentCount . '</a>'
                : '0';

            $ip = trim((string)$user['ip']);
            if ($ip === '') {
                $ipHtml = '---';
            } elseif ((int)$user['ip_banned'] === 1) {
                $ipHtml = '<a href="testip.php?ip=' . rawurlencode($ip) . '"><font color="#FF0000"><b>' . usersearch_h($ip) . '</b></font></a>';
            } else {
                $ipHtml = usersearch_h($ip);
            }

            echo '<tr>';
            echo '<td>'
                . $userLink
                . '<div style="font-size:11px;padding-top:3px;">'
                . '<a href="message.php?action=sendmessage&amp;receiver=' . $userId . '">ЛС</a> | '
                . '<a href="userhistory.php?action=viewcomments&amp;id=' . $userId . '">Комментарии</a>'
                . '</div>'
                . '</td>';
            echo '<td>' . usersearch_ratio_html((int)$user['uploaded'], (int)$user['downloaded']) . '</td>';
            echo '<td>' . $ipHtml . '</td>';
            echo '<td>' . usersearch_h($user['email']) . '</td>';
            echo '<td><div align="center">' . usersearch_format_datetime((string)$user['added'], $tzoffset) . '</div></td>';
            echo '<td><div align="center">' . usersearch_format_datetime((string)$user['last_access'], $tzoffset) . '</div></td>';
            echo '<td><div align="center">' . usersearch_h($user['status'] === 'confirmed' ? 'Подтвержден' : 'Не подтвержден') . '</div></td>';
            echo '<td><div align="center">' . usersearch_h($user['enabled'] === 'yes' ? 'Да' : 'Нет') . '</div></td>';
            echo '<td><div align="center">' . usersearch_ratio_html((int)$user['pul'], (int)$user['pdl']) . '</div></td>';
            echo '<td><div align="right">' . usersearch_h(mksize((int)$user['pul'])) . '</div></td>';
            echo '<td><div align="right">' . usersearch_h(mksize((int)$user['pdl'])) . '</div></td>';
            echo '<td><div align="center">' . $commentLink . '</div></td>';
            echo '</tr>';
        }

        echo '</table>';

        if ($count > 30) {
            echo $pagerbottom;
        }
        end_frame();
    }
}

stdfoot();
