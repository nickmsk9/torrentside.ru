<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

$links = [];
$linksFile = __DIR__ . '/links/all.php';
if (is_file($linksFile)) {
    $loaded = include $linksFile;
    if (is_array($loaded)) {
        $links = $loaded;
    }
}

$groupedLinks = [];
foreach ($links as $row) {
    $title = trim((string)($row['title'] ?? ''));
    $url = trim((string)($row['url'] ?? ''));
    if ($title === '' || $url === '') {
        continue;
    }

    $category = trim((string)($row['category'] ?? 'Прочее'));
    if ($category === '') {
        $category = 'Прочее';
    }

    $groupedLinks[$category][] = [
        'title' => $title,
        'url' => $url,
        'desc' => trim((string)($row['desc'] ?? '')),
        'badge' => trim((string)($row['badge'] ?? '')),
    ];
}

ksort($groupedLinks, SORT_NATURAL | SORT_FLAG_CASE);

begin_frame('Панель администратора');

if ($groupedLinks) {
    foreach ($groupedLinks as $category => $items) {
        print('<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">');
        print('<tr><td class="colhead" colspan="2">' . htmlspecialchars($category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>');

        foreach ($items as $item) {
            $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $url = htmlspecialchars($item['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $desc = htmlspecialchars($item['desc'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $badge = $item['badge'] !== ''
                ? ' <small>[' . htmlspecialchars($item['badge'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ']</small>'
                : '';

            print('<tr>');
            print('<td class="rowhead" width="260"><a href="' . $url . '"><b>' . $title . '</b></a>' . $badge . '</td>');
            print('<td class="lol">' . ($desc !== '' ? $desc : '&nbsp;') . '</td>');
            print('</tr>');
        }

        print('</table><br>');
    }
}

if (get_user_class() >= UC_ADMINISTRATOR) {
    $tools = [
        ['warned.php', 'Предупр. юзеры'],
        ['adduser.php', 'Добавить юзера'],
        ['recover.php', 'Восстан. юзера'],
        ['uploaders.php', 'Аплоадеры'],
        ['users.php', 'Список юзеров'],
        ['tags.php', 'Теги'],
        ['smilies.php', 'Смайлы'],
        ['delacctadmin.php', 'Удалить юзера'],
        ['stats.php', 'Статистика'],
        ['testip.php', 'Проверка IP'],
        ['ipcheck.php', 'Повторные IP'],
        ['findnotconnectable.php', 'Юзеры за NAT'],
    ];

    print('<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">');
    print('<tr><td class="colhead">Инструменты</td></tr>');
    print('<tr><td class="lol">');
    foreach ($tools as [$url, $title]) {
        print('<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><b>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b></a>&nbsp;&nbsp;&nbsp;');
    }
    print('</td></tr>');
    print('</table><br>');
}

if (get_user_class() >= UC_MODERATOR) {
    print('<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">');
    print('<tr><td class="colhead" colspan="2">Средства модератора</td></tr>');
    print('<tr><td class="rowhead" width="260">Быстрые разделы</td><td class="lol">');
    print('<a href="staff.php?act=users"><b>Пользователи с рейтингом ниже 0.20</b></a>&nbsp;&nbsp;&nbsp;');
    print('<a href="staff.php?act=banned"><b>Отключенные пользователи</b></a>&nbsp;&nbsp;&nbsp;');
    print('<a href="staff.php?act=last"><b>Новые пользователи</b></a>&nbsp;&nbsp;&nbsp;');
    print('<a href="log.php"><b>Лог сайта</b></a>');
    print('</td></tr>');
    print('<tr><td class="rowhead">Поиск пользователя</td><td class="lol">');
    print('<form method="get" action="users.php">');
    print('<input type="text" size="30" name="search"> ');
    print('<select name="class">');
    print('<option value="-">(Выберите)</option>');
    print('<option value="0">Пользователь</option>');
    print('<option value="1">Опытный пользователь</option>');
    print('<option value="2">VIP</option>');
    print('<option value="3">Заливающий</option>');
    print('<option value="4">Модератор</option>');
    print('<option value="5">Администратор</option>');
    print('<option value="6">Владелец</option>');
    print('</select> ');
    print('<input type="submit" value="Искать"> ');
    print('<a href="usersearch.php"><b>Административный поиск</b></a>');
    print('</form>');
    print('</td></tr>');
    print('</table><br>');
}

end_frame();
