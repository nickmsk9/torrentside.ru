<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
dbconn();
loggedinorreturn();

global $tracker_lang;

/** права */
if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Permission denied');
}

/** helpers */
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/** IPv4 → uint32 (беззнаковое) или null */
function ip_to_uint32(string $ip): ?int {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    $long = ip2long($ip);
    if ($long === false) {
        return null;
    }
    // Преобразуем в беззнаковое на 32-бит/64-бит PHP
    return (int) sprintf('%u', $long);
}

/** получим IP из POST/GET */
$ip = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim((string)($_POST['ip'] ?? ''));
} else {
    $ip = trim((string)($_GET['ip'] ?? ''));
}

if ($ip !== '') {
    $nip = ip_to_uint32($ip);

    // Если ввели IPv6 — честно скажем, что схема не поддерживает
    if ($nip === null) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            stderr('Результат', 'Пока что проверка поддерживает только IPv4 (таблица <code>bans</code> хранит диапазоны в <code>UNSIGNED INT</code>).');
        }
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Некорректный IPv4 адрес.');
    }

    // Поиск диапазона: first..last — UNSIGNED INT
    $q = sprintf(
        'SELECT first, last, comment FROM bans WHERE %u BETWEEN first AND last',
        $nip
    );
    $res = sql_query($q) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) === 0) {
        stderr('Результат', 'IP адрес <b>' . h($ip) . '</b> не забанен.');
    } else {
        // Список пересекающихся диапазонов
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }

        // Таблица результатов — аккуратный вид
        $banstable = '<style>
            .ban-table{border-collapse:collapse;width:100%;max-width:720px}
            .ban-table th,.ban-table td{padding:8px 10px;border:1px solid #ddd}
            .ban-table th{background:#f7f7f7}
            .ban-head{display:flex;gap:8px;align-items:center}
            .ban-head img{vertical-align:middle}
        </style>';

        $banstable .= "<table class=\"ban-table\">";
        $banstable .= "<tr><th>Первый</th><th>Последний</th><th>Комментарий</th></tr>";
        foreach ($rows as $arr) {
            $first = long2ip((int)$arr['first']);
            $last  = long2ip((int)$arr['last']);
            $comment = h($arr['comment'] ?? '');
            $banstable .= "<tr><td>{$first}</td><td>{$last}</td><td>{$comment}</td></tr>";
        }
        $banstable .= "</table>";

        $head = "<div class=\"ban-head\"><img src=\"pic/smilies/excl.gif\" alt=\"!\">"
              . "IP адрес <b>" . h($ip) . "</b> забанен:</div>";

        stderr('Результат', $head . '<p>' . $banstable . '</p>');
    }
}

stdhead('Проверка IP');
begin_frame('Проверка IP адреса');
?>
<form method="post" action="testip.php" style="margin:0">
  <table border="1" cellspacing="0" cellpadding="5">
    <tr>
      <td class="rowhead">IP адрес</td>
      <td><input type="text" name="ip" value="<?= h($ip) ?>" size="22" maxlength="45" placeholder="Например: 192.168.1.10"></td>
    </tr>
    <tr>
      <td colspan="2" align="center">
        <input type="submit" class="btn" value="Проверить">
      </td>
    </tr>
  </table>
</form>
<?php
end_frame();
stdfoot();
