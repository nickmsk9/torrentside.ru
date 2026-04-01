<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

require_once __DIR__ . '/routes.php';

function admincp_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admincp_table_exists(string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $result = sql_query("SHOW TABLES LIKE " . sqlesc($table));
    return $cache[$table] = ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
}

function admincp_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!admincp_table_exists($table)) {
        return $cache[$key] = false;
    }

    $result = sql_query("SHOW COLUMNS FROM `{$table}` LIKE " . sqlesc($column));
    return $cache[$key] = ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
}

function admincp_scalar_int(string $sql, int $default = 0): int
{
    $result = sql_query($sql);
    if (!($result instanceof mysqli_result)) {
        return $default;
    }

    $row = mysqli_fetch_row($result);
    return isset($row[0]) ? (int)$row[0] : $default;
}

function admincp_count_rows(string $table, string $where = '1=1'): int
{
    if (!admincp_table_exists($table)) {
        return 0;
    }

    return admincp_scalar_int("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
}

function admincp_num(int $value): string
{
    return number_format($value, 0, '.', ' ');
}

function admincp_role_label(int $class): string
{
    $label = function_exists('get_user_class_name') ? strip_tags((string)get_user_class_name($class)) : '';
    return $label !== '' ? $label : ('Класс ' . $class);
}

function admincp_status_rows(array $rows): string
{
    $html = '';
    foreach ($rows as $row) {
        $label = admincp_h((string)($row['label'] ?? ''));
        $value = admincp_h((string)($row['value'] ?? '0'));
        $note = trim((string)($row['note'] ?? ''));
        $html .= '<div class="admincp-kv-row">'
            . '<div class="admincp-kv-label">' . $label . '</div>'
            . '<div class="admincp-kv-value"><b>' . $value . '</b>';
        if ($note !== '') {
            $html .= '<span>' . admincp_h($note) . '</span>';
        }
        $html .= '</div></div>';
    }

    return $html;
}

$tools = admincp_tool_registry();
$groupedTools = [];
$toolTotals = [
    'total' => 0,
    'valid' => 0,
    'broken' => 0,
    'locked' => 0,
    'modules' => 0,
    'pages' => 0,
    'broken_items' => [],
];

foreach ($tools as $tool) {
    $title = trim((string)($tool['title'] ?? ''));
    $url = trim((string)($tool['url'] ?? ''));
    $category = trim((string)($tool['category'] ?? 'Прочее'));

    if ($title === '' || $url === '') {
        continue;
    }

    $status = admincp_validate_tool_link($tool);
    $isAccessible = get_user_class() >= (int)($tool['min_class'] ?? UC_MODERATOR);

    $tool['status'] = $status;
    $tool['accessible'] = $isAccessible;

    $groupedTools[$category][] = $tool;

    $toolTotals['total']++;
    if ($status['ok']) {
        $toolTotals['valid']++;
    } else {
        $toolTotals['broken']++;
        $toolTotals['broken_items'][] = $tool;
    }

    if (!$isAccessible) {
        $toolTotals['locked']++;
    }

    if (str_starts_with($url, 'admincp.php')) {
        $toolTotals['modules']++;
    } else {
        $toolTotals['pages']++;
    }
}

$usersCount = admincp_count_rows('users');
$activeUsersCount = admincp_column_exists('users', 'enabled') ? admincp_count_rows('users', "enabled = 'yes'") : $usersCount;
$disabledUsersCount = admincp_column_exists('users', 'enabled') ? admincp_count_rows('users', "enabled = 'no'") : 0;
$warnedUsersCount = admincp_column_exists('users', 'warned') ? admincp_count_rows('users', "warned = 'yes'") : 0;
$torrentsCount = admincp_count_rows('torrents');
$commentsCount = admincp_count_rows('comments');
$peersCount = admincp_count_rows('peers');
$messagesCount = admincp_count_rows('messages');
$shoutboxCount = admincp_count_rows('shoutbox');
$faqSectionsCount = admincp_table_exists('faq') && admincp_column_exists('faq', 'type')
    ? admincp_count_rows('faq', "type = 'categ'")
    : 0;
$faqItemsCount = admincp_table_exists('faq') && admincp_column_exists('faq', 'type')
    ? admincp_count_rows('faq', "type = 'item'")
    : 0;
$newsCount = admincp_count_rows('news');
$categoriesCount = admincp_count_rows('categories');
$tagsCount = admincp_count_rows('tags');
$pollsCount = admincp_count_rows('polls');
$pollAnswersCount = admincp_count_rows('pollanswers');
$bansCount = admincp_count_rows('bans');
$hackersCount = admincp_count_rows('hackers');
$sessionsCount = admincp_count_rows('sessions');
$historyCount = admincp_count_rows('visitor_history');
$bonusCount = admincp_count_rows('mybonus');
$lotoTicketsCount = admincp_count_rows('super_loto_tickets');

$topStats = [
    [
        'title' => 'Пользователи',
        'value' => admincp_num($usersCount),
        'note' => 'Активных ' . admincp_num($activeUsersCount) . ', предупреждений ' . admincp_num($warnedUsersCount),
    ],
    [
        'title' => 'Раздачи',
        'value' => admincp_num($torrentsCount),
        'note' => 'Комментариев ' . admincp_num($commentsCount) . ', пиров ' . admincp_num($peersCount),
    ],
    [
        'title' => 'Коммуникации',
        'value' => admincp_num($messagesCount),
        'note' => 'ЛС и служебные сообщения, чат ' . admincp_num($shoutboxCount),
    ],
    [
        'title' => 'Контент',
        'value' => admincp_num($newsCount + $faqItemsCount + $pollsCount),
        'note' => 'FAQ ' . admincp_num($faqItemsCount) . ', новости ' . admincp_num($newsCount) . ', опросы ' . admincp_num($pollsCount),
    ],
    [
        'title' => 'Безопасность',
        'value' => admincp_num($bansCount + $hackersCount),
        'note' => 'Баны ' . admincp_num($bansCount) . ', события hackers ' . admincp_num($hackersCount),
    ],
    [
        'title' => 'Инструменты',
        'value' => admincp_num($toolTotals['valid']),
        'note' => 'Рабочих ссылок ' . admincp_num($toolTotals['valid']) . ' из ' . admincp_num($toolTotals['total']),
    ],
];

$securityRows = [
    ['label' => 'Отключённые аккаунты', 'value' => admincp_num($disabledUsersCount), 'note' => 'Пользователи с enabled = no'],
    ['label' => 'Предупреждённые аккаунты', 'value' => admincp_num($warnedUsersCount), 'note' => 'Активные предупреждения'],
    ['label' => 'Баны', 'value' => admincp_num($bansCount), 'note' => 'Записи в бан-листе'],
    ['label' => 'Hackers', 'value' => admincp_num($hackersCount), 'note' => 'Срабатывания антихака'],
    ['label' => 'Активные сессии', 'value' => admincp_num($sessionsCount), 'note' => 'Записи в таблице sessions'],
    ['label' => 'История переходов', 'value' => admincp_num($historyCount), 'note' => 'Записи visitor_history'],
];

$contentRows = [
    ['label' => 'Категории', 'value' => admincp_num($categoriesCount), 'note' => 'Доступные разделы раздач'],
    ['label' => 'Теги', 'value' => admincp_num($tagsCount), 'note' => 'Служебные и пользовательские теги'],
    ['label' => 'FAQ', 'value' => admincp_num($faqItemsCount), 'note' => 'Элементов, секций ' . admincp_num($faqSectionsCount)],
    ['label' => 'Новости', 'value' => admincp_num($newsCount), 'note' => 'Новостные публикации'],
    ['label' => 'Опросы', 'value' => admincp_num($pollsCount), 'note' => 'Ответов ' . admincp_num($pollAnswersCount)],
    ['label' => 'Бонусы и лото', 'value' => admincp_num($bonusCount), 'note' => 'Позиции магазина, билетов ' . admincp_num($lotoTicketsCount)],
];

$systemRows = [
    ['label' => 'Внутренние модули', 'value' => admincp_num($toolTotals['modules']), 'note' => 'Маршрутизируются через admincp'],
    ['label' => 'Внешние страницы', 'value' => admincp_num($toolTotals['pages']), 'note' => 'Отдельные инструменты движка'],
    ['label' => 'Рабочие ссылки', 'value' => admincp_num($toolTotals['valid']), 'note' => 'Проверены по файловой структуре и роутеру'],
    ['label' => 'Битые ссылки', 'value' => admincp_num($toolTotals['broken']), 'note' => 'Сейчас должны быть сведены к нулю'],
    ['label' => 'Ограниченные по классу', 'value' => admincp_num($toolTotals['locked']), 'note' => 'Инструменты выше текущего класса'],
    ['label' => 'Ваш уровень', 'value' => admincp_role_label((int)get_user_class()), 'note' => 'Текущая роль в админке'],
];

ksort($groupedTools, SORT_NATURAL | SORT_FLAG_CASE);

global $admincpRoute;
$routeNotice = '';
if (is_array($admincpRoute ?? null) && !($admincpRoute['found'] ?? true) && trim((string)($admincpRoute['requested'] ?? '')) !== '') {
    $routeNotice = 'Модуль "' . trim((string)$admincpRoute['requested']) . '" не найден, открыт главный дашборд.';
}

begin_frame('Панель администратора');
?>
<style>
.admincp-dashboard{
  --admincp-bg:#f4f7fb;
  --admincp-card:#ffffff;
  --admincp-border:rgba(19,39,77,.12);
  --admincp-text:#1f2d3d;
  --admincp-muted:#66758c;
  --admincp-accent:#1f5fa8;
  --admincp-accent-soft:rgba(31,95,168,.08);
  --admincp-ok:#1f7a4f;
  --admincp-warn:#9a5a00;
  color:var(--admincp-text);
}
.admincp-dashboard *{box-sizing:border-box}
.admincp-hero{
  padding:18px 20px;
  border:1px solid var(--admincp-border);
  border-radius:18px;
  background:
    radial-gradient(circle at top right, rgba(31,95,168,.14), transparent 34%),
    linear-gradient(180deg, #ffffff 0%, var(--admincp-bg) 100%);
  margin-bottom:16px;
}
.admincp-hero h2{margin:0 0 8px;font-size:24px;line-height:1.15}
.admincp-hero p{margin:0;color:var(--admincp-muted);max-width:920px;line-height:1.55}
.admincp-pills{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.admincp-pill{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 11px;border-radius:999px;border:1px solid var(--admincp-border);
  background:rgba(255,255,255,.88);font-size:12px;font-weight:700;color:var(--admincp-text);
}
.admincp-pill.warn{color:var(--admincp-warn)}
.admincp-pill.ok{color:var(--admincp-ok)}
.admincp-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
  margin-bottom:16px;
}
.admincp-grid.admincp-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.admincp-card{
  border:1px solid var(--admincp-border);
  border-radius:18px;
  background:var(--admincp-card);
  padding:16px;
  box-shadow:0 8px 22px rgba(15,23,42,.04);
}
.admincp-stat-value{font-size:26px;font-weight:800;line-height:1.05;margin-bottom:6px}
.admincp-stat-title{font-size:13px;font-weight:700;letter-spacing:.01em}
.admincp-stat-note{margin-top:6px;color:var(--admincp-muted);font-size:12px;line-height:1.45}
.admincp-section-head{
  display:flex;justify-content:space-between;gap:12px;align-items:flex-end;
  margin-bottom:12px;
}
.admincp-section-head h3{margin:0;font-size:18px}
.admincp-section-head p{margin:0;color:var(--admincp-muted);font-size:12px;line-height:1.45}
.admincp-kv{display:grid;gap:10px}
.admincp-kv-row{
  display:grid;grid-template-columns:minmax(150px,210px) minmax(0,1fr);
  gap:10px;padding:10px 0;border-top:1px solid rgba(19,39,77,.08);
}
.admincp-kv-row:first-child{border-top:none;padding-top:0}
.admincp-kv-label{font-size:12px;color:var(--admincp-muted);font-weight:700}
.admincp-kv-value{display:flex;flex-direction:column;gap:4px}
.admincp-kv-value span{color:var(--admincp-muted);font-size:12px;line-height:1.45}
.admincp-actions{display:flex;flex-wrap:wrap;gap:10px}
.admincp-action-link{
  display:inline-flex;align-items:center;justify-content:center;min-height:40px;
  padding:10px 14px;border-radius:12px;border:1px solid var(--admincp-border);
  background:var(--admincp-accent-soft);text-decoration:none;font-weight:700;color:var(--admincp-accent);
}
.admincp-form{
  display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;align-items:center;
}
.admincp-form input,.admincp-form select{
  min-height:40px;padding:9px 12px;border:1px solid var(--admincp-border);border-radius:12px;background:#fff;color:var(--admincp-text);
}
.admincp-form button{
  min-height:40px;padding:10px 16px;border:none;border-radius:12px;background:var(--admincp-accent);color:#fff;font-weight:700;cursor:pointer;
}
.admincp-tools{
  display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;
}
.admincp-tool{
  display:flex;flex-direction:column;gap:9px;
  min-height:130px;padding:14px;border-radius:16px;border:1px solid var(--admincp-border);
  background:linear-gradient(180deg,#fff 0%, #f8fbff 100%);
  color:inherit;text-decoration:none;
}
.admincp-tool.is-locked{background:#fafbfc}
.admincp-tool.is-broken{background:#fff7f2;border-color:rgba(154,90,0,.2)}
.admincp-tool-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.admincp-tool-title{font-size:15px;font-weight:800;line-height:1.3}
.admincp-tool-desc{margin:0;color:var(--admincp-muted);font-size:12px;line-height:1.5}
.admincp-badges{display:flex;flex-wrap:wrap;gap:6px}
.admincp-badge{
  display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;
  border:1px solid rgba(19,39,77,.10);background:#fff;font-size:11px;font-weight:700;color:var(--admincp-text);
}
.admincp-badge.muted{color:var(--admincp-muted)}
.admincp-tool-meta{margin-top:auto;color:var(--admincp-muted);font-size:11px;line-height:1.45}
.admincp-notice{
  margin-bottom:14px;padding:12px 14px;border-radius:14px;border:1px solid rgba(154,90,0,.18);
  background:#fff8ef;color:#7a4a00;font-weight:700;
}
@media (max-width:1180px){
  .admincp-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:860px){
  .admincp-grid,.admincp-grid.admincp-grid-2,.admincp-tools{grid-template-columns:1fr}
  .admincp-form{grid-template-columns:1fr}
  .admincp-kv-row{grid-template-columns:1fr}
}
</style>
<div class="admincp-dashboard">
  <?php if ($routeNotice !== ''): ?>
    <div class="admincp-notice"><?=admincp_h($routeNotice)?></div>
  <?php endif; ?>

  <section class="admincp-hero">
    <h2>Глубокая панель управления трекером</h2>
    <p>В этой версии админки собраны только реальные и поддерживаемые инструменты. Старые битые ссылки, неверные `op`, несуществующие модули и устаревшие переходы из меню убраны, а внутренние модули теперь открываются через единый роутер с алиасами для старых адресов.</p>
    <div class="admincp-pills">
      <span class="admincp-pill ok">Рабочих ссылок: <?=admincp_h(admincp_num($toolTotals['valid']))?></span>
      <span class="admincp-pill">Внутренних модулей: <?=admincp_h(admincp_num($toolTotals['modules']))?></span>
      <span class="admincp-pill">Отдельных страниц: <?=admincp_h(admincp_num($toolTotals['pages']))?></span>
      <span class="admincp-pill <?=($toolTotals['broken'] > 0 ? 'warn' : 'ok')?>">Битых ссылок: <?=admincp_h(admincp_num($toolTotals['broken']))?></span>
    </div>
  </section>

  <section class="admincp-grid">
    <?php foreach ($topStats as $stat): ?>
      <div class="admincp-card">
        <div class="admincp-stat-title"><?=admincp_h($stat['title'])?></div>
        <div class="admincp-stat-value"><?=admincp_h($stat['value'])?></div>
        <div class="admincp-stat-note"><?=admincp_h($stat['note'])?></div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="admincp-grid admincp-grid-2">
    <div class="admincp-card">
      <div class="admincp-section-head">
        <div>
          <h3>Быстрые действия</h3>
          <p>Самые частые переходы по пользователям и обслуживанию.</p>
        </div>
      </div>
      <form class="admincp-form" method="get" action="users.php">
        <input type="text" name="search" placeholder="Ник, email или часть имени пользователя">
        <select name="class">
          <option value="-">Любой класс</option>
          <option value="0">Пользователь</option>
          <option value="1">Опытный пользователь</option>
          <option value="2">VIP</option>
          <option value="3">Заливающий</option>
          <option value="4">Модератор</option>
          <option value="5">Администратор</option>
          <option value="6">Сисоп</option>
        </select>
        <button type="submit">Искать</button>
      </form>
      <div class="admincp-actions" style="margin-top:12px">
        <a class="admincp-action-link" href="usersearch.php">Расширенный поиск</a>
        <a class="admincp-action-link" href="admincp.php?op=database">Операции с БД</a>
        <a class="admincp-action-link" href="config_editor.php">Конфигурация</a>
        <a class="admincp-action-link" href="log.php">Лог сайта</a>
      </div>
    </div>

    <div class="admincp-card">
      <div class="admincp-section-head">
        <div>
          <h3>Системный срез</h3>
          <p>Короткая сводка по доступам, ссылкам и инфраструктуре админки.</p>
        </div>
      </div>
      <div class="admincp-kv"><?=admincp_status_rows($systemRows)?></div>
    </div>
  </section>

  <section class="admincp-grid admincp-grid-2">
    <div class="admincp-card">
      <div class="admincp-section-head">
        <div>
          <h3>Безопасность и доступ</h3>
          <p>Пользователи, баны и служебные журналы безопасности.</p>
        </div>
      </div>
      <div class="admincp-kv"><?=admincp_status_rows($securityRows)?></div>
    </div>

    <div class="admincp-card">
      <div class="admincp-section-head">
        <div>
          <h3>Контент и сервисы</h3>
          <p>Основные сущности, которыми теперь можно управлять из полного набора инструментов.</p>
        </div>
      </div>
      <div class="admincp-kv"><?=admincp_status_rows($contentRows)?></div>
    </div>
  </section>

  <?php if ($toolTotals['broken'] > 0): ?>
    <section class="admincp-card" style="margin-bottom:16px">
      <div class="admincp-section-head">
        <div>
          <h3>Проблемные ссылки</h3>
          <p>Эти инструменты не прошли проверку и требуют отдельного внимания.</p>
        </div>
      </div>
      <div class="admincp-tools">
        <?php foreach ($toolTotals['broken_items'] as $tool): ?>
          <div class="admincp-tool is-broken">
            <div class="admincp-tool-top">
              <div class="admincp-tool-title"><?=admincp_h((string)$tool['title'])?></div>
              <span class="admincp-badge muted">не найдено</span>
            </div>
            <p class="admincp-tool-desc"><?=admincp_h((string)($tool['desc'] ?? ''))?></p>
            <div class="admincp-tool-meta"><?=admincp_h((string)$tool['url'])?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php foreach ($groupedTools as $category => $items): ?>
    <section class="admincp-card" style="margin-bottom:16px">
      <div class="admincp-section-head">
        <div>
          <h3><?=admincp_h((string)$category)?></h3>
          <p>Рабочие инструменты по этому направлению: <?=admincp_h(admincp_num(count($items)))?>.</p>
        </div>
      </div>
      <div class="admincp-tools">
        <?php foreach ($items as $tool): ?>
          <?php
            $minClass = (int)($tool['min_class'] ?? UC_MODERATOR);
            $badges = [];
            if (!empty($tool['badge'])) {
                $badges[] = (string)$tool['badge'];
            }
            $badges[] = admincp_role_label($minClass);
            $classes = 'admincp-tool';
            if (!$tool['accessible']) {
                $classes .= ' is-locked';
            }
            if (!($tool['status']['ok'] ?? false)) {
                $classes .= ' is-broken';
            }
            $statusText = !($tool['status']['ok'] ?? false)
                ? 'Ссылка требует исправления'
                : ($tool['accessible'] ? 'Открывается из админки' : 'Требуется доступ выше текущего класса');
          ?>
          <?php if (($tool['status']['ok'] ?? false) && $tool['accessible']): ?>
            <a class="<?=$classes?>" href="<?=admincp_h((string)$tool['url'])?>">
          <?php else: ?>
            <div class="<?=$classes?>">
          <?php endif; ?>
              <div class="admincp-tool-top">
                <div class="admincp-tool-title"><?=admincp_h((string)$tool['title'])?></div>
                <div class="admincp-badges">
                  <?php foreach ($badges as $badge): ?>
                    <span class="admincp-badge"><?=admincp_h((string)$badge)?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <p class="admincp-tool-desc"><?=admincp_h((string)($tool['desc'] ?? ''))?></p>
              <div class="admincp-tool-meta">
                <?=admincp_h($statusText)?>
                <br>
                <?=admincp_h((string)$tool['url'])?>
              </div>
          <?php if (($tool['status']['ok'] ?? false) && $tool['accessible']): ?>
            </a>
          <?php else: ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<?php
end_frame();
