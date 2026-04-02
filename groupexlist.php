<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
parked();

function gx_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($CURUSER['id'] ?? 0);
$userClass = (int)(get_user_class() ?? 0);
$canCreateGroups = $userId > 0 && $userClass >= UC_UPLOADER;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'create_group') {
    if (!$canCreateGroups) {
        stderr('Ошибка', 'Создавать релиз-группы могут только аплоадеры и выше.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $image = trim((string)($_POST['image'] ?? ''));
    $accessMode = (string)($_POST['access_mode'] ?? 'closed');
    $categoryName = trim((string)($_POST['category_name'] ?? 'Общие интересы'));
    $subcategoryName = trim((string)($_POST['subcategory_name'] ?? 'Другое'));

    if ($name === '' || mb_strlen($name, 'UTF-8') > 128) {
        stderr('Ошибка', 'Укажите название группы длиной до 128 символов.');
    }
    if (!in_array($accessMode, ['open', 'closed'], true)) {
        $accessMode = 'closed';
    }
    if ($categoryName === '') {
        $categoryName = 'Общие интересы';
    }
    if ($subcategoryName === '') {
        $subcategoryName = 'Другое';
    }

    sql_query(
        "INSERT INTO release_groups (name, description, image, creator_id, group_type, category_name, subcategory_name, access_mode, added)
         VALUES (" . sqlesc($name) . ", " . sqlesc($description) . ", " . sqlesc($image) . ", " . $userId . ", 'Релиз-группы', " . sqlesc($categoryName) . ", " . sqlesc($subcategoryName) . ", " . sqlesc($accessMode) . ", NOW())"
    ) or sqlerr(__FILE__, __LINE__);

    $groupId = (int)mysqli_insert_id($GLOBALS['mysqli']);
    sql_query("INSERT INTO release_group_members (group_id, user_id, role, added) VALUES (" . $groupId . ", " . $userId . ", 'owner', NOW())") or sqlerr(__FILE__, __LINE__);

    tracker_invalidate_release_group_membership_cache($userId);
    tracker_invalidate_release_group_cache($groupId);

    header('Location: groupex.php?id=' . $groupId, true, 302);
    exit;
}

$view = (string)($_GET['view'] ?? 'all');
if (!in_array($view, ['all', 'my', 'bookmarks'], true)) {
    $view = 'all';
}

if ($userId <= 0 && $view !== 'all') {
    $view = 'all';
}

$search = trim((string)($_GET['search'] ?? ''));
$creatorId = (int)($_GET['creator_id'] ?? 0);
$accessFilter = (string)($_GET['access_mode'] ?? '');
if (!in_array($accessFilter, ['', 'open', 'closed'], true)) {
    $accessFilter = '';
}
$categoryFilter = trim((string)($_GET['category_name'] ?? ''));

$joins = [
    "LEFT JOIN users AS u ON u.id = g.creator_id",
    "LEFT JOIN (
        SELECT group_id, COUNT(*) AS members_count
        FROM release_group_members
        GROUP BY group_id
    ) AS members ON members.group_id = g.id",
    "LEFT JOIN (
        SELECT release_group_id, COUNT(*) AS torrents_count
        FROM torrents
        WHERE release_group_id > 0
        GROUP BY release_group_id
    ) AS torrents_cnt ON torrents_cnt.release_group_id = g.id",
];
$where = [];

if ($search !== '') {
    $where[] = "g.name LIKE '%" . sqlwildcardesc($search) . "%'";
}
if ($creatorId > 0) {
    $where[] = "g.creator_id = " . $creatorId;
}
if ($accessFilter !== '') {
    $where[] = "g.access_mode = " . sqlesc($accessFilter);
}
if ($categoryFilter !== '') {
    $where[] = "g.category_name = " . sqlesc($categoryFilter);
}
if ($view === 'my' && $userId > 0) {
    $joins[] = "INNER JOIN release_group_members AS my_groups ON my_groups.group_id = g.id AND my_groups.user_id = " . $userId;
} elseif ($view === 'bookmarks' && $userId > 0) {
    $joins[] = "INNER JOIN release_group_bookmarks AS my_bookmarks ON my_bookmarks.group_id = g.id AND my_bookmarks.user_id = " . $userId;
}

$sql = "
    SELECT
        g.*,
        u.username AS creator_name,
        COALESCE(members.members_count, 0) AS members_count,
        COALESCE(torrents_cnt.torrents_count, 0) AS torrents_count
    FROM release_groups AS g
    " . implode("\n", $joins) . "
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY g.added DESC, g.id DESC
";

$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
$groups = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $groups[] = $row;
}

$pageTitle = match ($view) {
    'my' => 'Список групп :: Мои группы',
    'bookmarks' => 'Список групп :: Закладки',
    default => 'Список групп :: Мои группы',
};

stdhead('Релиз-группы');
begin_frame($pageTitle);
?>
<style>
.gx-shell{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px}
.gx-card{border:1px solid #d8e1ea;border-radius:14px;background:#fff;padding:14px}
.gx-menu a,.gx-menu span{display:block;padding:8px 10px;border-radius:10px;text-decoration:none}
.gx-menu .active{background:#eef5ff;color:#1d4f91;font-weight:700}
.gx-list{display:grid;gap:12px}
.gx-item{border:1px solid #d8e1ea;border-radius:14px;background:#fbfdff;padding:14px}
.gx-meta{display:flex;flex-wrap:wrap;gap:10px;color:#6b7d92;font-size:12px;margin-top:6px}
.gx-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.gx-actions form{margin:0}
@media (max-width: 920px){.gx-shell{grid-template-columns:1fr}}
</style>

<div class="gx-shell">
  <div>
    <div class="gx-card" style="margin-bottom:12px;">
      <form method="get" action="groupexlist.php">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div>
            <label>Поиск группы</label><br>
            <input type="text" name="search" value="<?= gx_h($search) ?>" style="width:100%">
          </div>
          <div>
            <label>Ид создателя</label><br>
            <input type="number" name="creator_id" value="<?= $creatorId > 0 ? $creatorId : '' ?>" style="width:100%">
          </div>
          <div>
            <label>Выберите тип группы</label><br>
            <select name="group_type" style="width:100%">
              <option value="release" selected>Релиз-группы</option>
            </select>
          </div>
          <div>
            <label>Выберите из списка категорию</label><br>
            <select name="category_name" style="width:100%">
              <option value="">Не выбрана категория</option>
              <option value="Общие интересы"<?= $categoryFilter === 'Общие интересы' ? ' selected' : '' ?>>Общие интересы</option>
            </select>
          </div>
          <div>
            <label>Доступ к группе</label><br>
            <select name="access_mode" style="width:100%">
              <option value="">Все</option>
              <option value="open"<?= $accessFilter === 'open' ? ' selected' : '' ?>>Открытая</option>
              <option value="closed"<?= $accessFilter === 'closed' ? ' selected' : '' ?>>Закрытая</option>
            </select>
          </div>
          <div>
            <label>Сортировать по добавлению</label><br>
            <input type="text" value="Сначала новые" readonly style="width:100%;background:#f8fafc;">
          </div>
        </div>
        <div style="margin-top:10px;">
          <button type="submit" class="btn">Найти</button>
        </div>
      </form>
    </div>

    <div class="gx-card">
      <div style="font-weight:700;margin-bottom:10px;">Информация</div>
      <div style="color:#6b7d92;margin-bottom:12px;">Здесь отображены все группы, которые существуют. Для поиска определенных групп можете воспользоваться формой поиска, размещенной выше.</div>

      <div class="gx-list">
        <?php if (!$groups): ?>
          <div class="gx-item">Пока релиз-групп нет.</div>
        <?php else: ?>
          <?php foreach ($groups as $group): ?>
            <?php
              $groupId = (int)$group['id'];
              $bookmarked = $userId > 0 ? tracker_user_has_release_group_bookmark($userId, $groupId) : false;
              $image = trim((string)($group['image'] ?? ''));
            ?>
            <div class="gx-item">
              <div style="display:flex;gap:12px;align-items:flex-start;">
                <div style="min-width:72px;">
                  <?= tracker_release_group_badge_html($group, false) ?>
                </div>
                <div style="min-width:0;flex:1 1 auto;">
                  <div style="font-size:16px;font-weight:700;"><a href="groupex.php?id=<?= $groupId ?>"><?= gx_h($group['name']) ?></a></div>
                  <div class="gx-meta">
                    <span>Тип: <?= gx_h($group['group_type']) ?></span>
                    <span>Категория: <?= gx_h($group['category_name']) ?></span>
                    <span>Создатель: <?= (int)$group['creator_id'] ?></span>
                    <span>Участники: <?= (int)$group['members_count'] ?></span>
                    <span>Раздачи: <?= (int)$group['torrents_count'] ?></span>
                  </div>
                  <?php if (trim((string)$group['description']) !== ''): ?>
                    <div style="margin-top:8px;color:#4b5e74;"><?= nl2br(gx_h((string)$group['description'])) ?></div>
                  <?php endif; ?>
                  <div class="gx-actions">
                    <a class="btn" href="groupex.php?id=<?= $groupId ?>">Просмотреть группу</a>
                    <?php if ($userId > 0): ?>
                      <form method="post" action="bookmark.php">
                        <input type="hidden" name="type" value="release_group">
                        <input type="hidden" name="entity_id" value="<?= $groupId ?>">
                        <input type="hidden" name="returnto" value="<?= gx_h('groupexlist.php?view=' . $view) ?>">
                        <button type="submit" class="btn"><?= $bookmarked ? 'Убрать из закладок' : 'Добавить в закладки' ?></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="gx-card gx-menu" style="margin-bottom:12px;">
      <div style="font-weight:700;margin-bottom:8px;">Меню</div>
      <a href="groupexlist.php?view=all" class="<?= $view === 'all' ? 'active' : '' ?>">Список групп</a>
      <a href="groupexlist.php?view=my" class="<?= $view === 'my' ? 'active' : '' ?>">Мои группы</a>
      <a href="groupexlist.php?view=bookmarks" class="<?= $view === 'bookmarks' ? 'active' : '' ?>">Закладки</a>
    </div>

    <div class="gx-card">
      <div style="font-weight:700;margin-bottom:8px;">Создание новой группы</div>
      <?php if (!$canCreateGroups): ?>
        <div style="color:#6b7d92;">Создавать релиз-группы могут пользователи со статусом Аплоадер и выше.</div>
      <?php else: ?>
        <form method="post" action="groupexlist.php">
          <input type="hidden" name="action" value="create_group">
          <div style="margin-bottom:8px;">
            <label>Название</label><br>
            <input type="text" name="name" maxlength="128" style="width:100%" required>
          </div>
          <div style="margin-bottom:8px;">
            <label>Картинка</label><br>
            <input type="url" name="image" style="width:100%" placeholder="https://...">
          </div>
          <div style="margin-bottom:8px;">
            <label>Доступ к группе</label><br>
            <select name="access_mode" style="width:100%">
              <option value="closed" selected>Закрытая</option>
              <option value="open">Открытая</option>
            </select>
          </div>
          <div style="margin-bottom:8px;">
            <label>Категория</label><br>
            <input type="text" name="category_name" value="Общие интересы" style="width:100%">
          </div>
          <div style="margin-bottom:8px;">
            <label>Подкатегория</label><br>
            <input type="text" name="subcategory_name" value="Другое" style="width:100%">
          </div>
          <div style="margin-bottom:8px;">
            <label>Описание</label><br>
            <textarea name="description" rows="5" style="width:100%"></textarea>
          </div>
          <button type="submit" class="btn">Создать</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
end_frame();
stdfoot();
