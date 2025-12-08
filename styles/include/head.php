<?php
declare(strict_types=1);

if (!defined('UC_SYSOP')) {
    exit('Direct access denied.');
}

// Инициализация Smarty
require_once dirname(__DIR__, 2) . '/include/smarty_init.php';
global $smarty, $CURUSER, $tracker_lang, $DEFAULTBASEURL;

// ====================== Заголовок страницы ======================
$titleEscaped = htmlspecialchars($title ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
$baseUrl      = rtrim((string)($DEFAULTBASEURL ?? ''), '/');

// Верхнее меню (главная навигация)
$navItems = [
    ['href' => $baseUrl,         'label' => $tracker_lang['homepage']],
    ['href' => 'browse.php',     'label' => 'Скачать'],
    ['href' => 'upload.php',     'label' => 'Залить'],
    ['href' => 'persons.php',    'label' => '<span class="text-blue">Актёры</span>', 'raw' => true],
    ['href' => 'forums.php',      'label' => 'Форум'],
    ['href' => 'hit.php',        'label' => 'Топ 20'],
    ['href' => 'casino.php',     'label' => 'Казино'],
    ['href' => 'super_loto.php', 'label' => 'Лото'],
    ['href' => 'users.php',      'label' => 'Пользователи'],
    ['href' => 'rules.php',      'label' => $tracker_lang['rules']],
    ['href' => 'staff.php',      'label' => $tracker_lang['staff']],
];

// Передаём в Smarty head и логотип
if (isset($smarty)) {
    $smarty->assign('title', $titleEscaped);
    $smarty->assign('baseUrl', $baseUrl);
    $smarty->assign('navItems', $navItems);
    echo $smarty->fetch('partials/head_block.tpl');
    echo $smarty->fetch('partials/header_logo.tpl');
}
?>

<center>
<?php
// ====================== Внешние таблицы (HTML) ======================
$table_width = 'width="100%"';
?>
<table class="ccl" align="center" <?= $table_width ?> cellpadding="5">
<table class="mainouter" align="center" <?= $table_width ?> border="0" cellspacing="0" cellpadding="5">

<?php if ($blockhide !== 'left' && $blockhide !== 'all'): ?>
<td valign="top" width="190">

<style>
  /* Стили для приветствия */
  .greet {
    display:flex;
    align-items:baseline;
    gap:6px;
    white-space:nowrap;
    margin-bottom:6px
  }
  @media (max-width:480px){ .greet{white-space:normal;flex-wrap:wrap} }
</style>

<?php
// ====================== Блок "Меню пользователя" ======================
if (isset($smarty)) {
    if (!empty($CURUSER)) {
        $uid   = (int)($CURUSER['id'] ?? 0);
        $uname = (string)($CURUSER['username'] ?? '');
        $upl   = (int)($CURUSER['uploaded']   ?? 0);
        $dwn   = (int)($CURUSER['downloaded'] ?? 0);
        $bonus = (int)($CURUSER['bonus']      ?? 0);

        $uped   = mksize($upl);
        $downed = mksize($dwn);
        $ratio  = $dwn > 0 ? number_format($upl / $dwn, 3) : ($upl > 0 ? 'Inf.' : '---');

        // Аватар (дефолтный, если пусто)
        $avatar = !empty($CURUSER['avatar'])
            ? htmlspecialchars($CURUSER['avatar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : 'pic/default_avatar.png';

        $smarty->assign('userMenu', [
            'id'       => $uid,
            'username' => get_user_class_color($CURUSER['class'] ?? 0, $uname),
            'ratio'    => $ratio,
            'uploaded' => $uped,
            'download' => $downed,
            'bonus'    => $bonus,
            'avatar'   => $avatar,
            'loggedin' => true,
        ]);
    } else {
        $smarty->assign('userMenu', ['loggedin' => false]);
    }

    echo $smarty->fetch('partials/user_menu.tpl');
}
?>

<?php
// ====================== Ссылочное меню (сайдбар) ======================
$menuLinks = [
    ['href' => 'index.php',   'label' => $tracker_lang['homepage'] ?? 'Главная'],
    ['href' => 'browse.php',  'label' => $tracker_lang['browse']   ?? 'Скачать'],
    ['href' => 'log.php',     'label' => $tracker_lang['log']      ?? 'Лог'],
    ['href' => 'rules.php',   'label' => $tracker_lang['rules']    ?? 'Правила'],
    ['href' => 'faq.php',     'label' => $tracker_lang['faq']      ?? 'FAQ'],
    ['href' => 'topten.php',  'label' => $tracker_lang['topten']   ?? 'Топ'],
];

// Добавляем пункты для залогиненных
if (!empty($CURUSER)) {
    $menuLinks = array_merge($menuLinks, [
        ['href' => 'my.php',      'label' => $tracker_lang['my'] ?? 'Мой профиль'],
        ['href' => 'message.php', 'label' => 'Личные сообщения'],
        ['href' => 'vip.php',     'label' => $tracker_lang['donate'] ?? 'Поддержать'],
        ['href' => 'mybonus.php', 'label' => $tracker_lang['my_bonus'] ?? 'Мой бонус'],
        ['href' => 'invite.php',  'label' => $tracker_lang['invite'] ?? 'Инвайты'],
        ['href' => 'logout.php',  'label' => ($tracker_lang['logout'] ?? 'Выход') . '!'],
    ]);
} else {
    // Для гостей — регистрация/вход
    $menuLinks = array_merge($menuLinks, [
        ['href' => 'signup.php',  'label' => 'Регистрация'],
        ['href' => 'recover.php', 'label' => 'Напомнить пароль'],
        ['href' => 'takelogin.php','label'=> 'Войти'],
    ]);
}

// В шаблон
if (isset($smarty)) {
    $smarty->assign('menuLinks', $menuLinks);
    $smarty->assign('showUtorrent', true);
    echo $smarty->fetch('partials/user_nav.tpl');
}
?>

<?php
// ====================== Облако тегов ======================
require_once "include/cloud_func.php";
$tagsHtml = php_cloud(); // готовый HTML со ссылками
if (isset($smarty)) {
    $smarty->assign('tagsHtml', $tagsHtml);
    echo $smarty->fetch('partials/tag_cloud.tpl');
}
?>

<!-- Левая панель завершена -->
</td>

<!-- Основной контент -->
<td align="center" valign="top" class="outer" style="padding-top:5px; padding-bottom:5px;">
<?php endif; ?>

<?php
// ====================== Входящие сообщения ======================
$unread = 0;
$inboxUrl = '';
$newText  = '';

if (!empty($CURUSER['id'])) {
    $uid = (int)$CURUSER['id'];
    $res = sql_query(
        "SELECT COUNT(*) AS unread_cnt
         FROM messages
         WHERE receiver = " . sqlesc($uid) . " AND unread = 'yes'"
    ) or sqlerr(__FILE__, __LINE__);

    $row = mysqli_fetch_assoc($res) ?: ['unread_cnt' => 0];
    $unread = (int)$row['unread_cnt'];

    if ($unread > 0) {
        $inboxUrl = $baseUrl . '/message.php';
        $newText  = sprintf($tracker_lang['new_pms'] ?? 'Новых сообщений: %d', $unread);
    }
}

if (isset($smarty)) {
    $smarty->assign('inbox', [
        'unread'   => $unread,
        'url'      => $inboxUrl,
        'new_text' => $newText,
    ]);
    echo $smarty->fetch('partials/inbox_notice.tpl');
}
?>

<?php
// ====================== Смена класса ======================
$restoreClass = null;
if (!empty($CURUSER['override_class']) && $CURUSER['override_class'] != 255) {
    $restoreClass = [
        'url'   => $baseUrl . '/restoreclass.php',
        'label' => $tracker_lang['lower_class'] ?? 'Сменить класс',
    ];
}

if (isset($smarty)) {
    $smarty->assign('restoreClass', $restoreClass);
    echo $smarty->fetch('partials/restore_class.tpl');
}
?>
