<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
parked();

function gx_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$groupId = (int)($_GET['id'] ?? 0);
if ($groupId <= 0) {
    stderr('Ошибка', 'Группа не найдена.');
}

$userId = (int)($CURUSER['id'] ?? 0);
$userClass = (int)(get_user_class() ?? 0);

$group = tracker_get_release_group($groupId);
if (!$group) {
    stderr('Ошибка', 'Группа не найдена.');
}

$userRole = $userId > 0 ? tracker_release_group_role($groupId, $userId) : null;
$isMember = $userRole !== null;
$canManage = $userId > 0 && tracker_can_manage_release_group($groupId, $userId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'join_group') {
        if ($userId <= 0 || $userClass < UC_UPLOADER) {
            stderr('Ошибка', 'Вступать в релиз-группы могут только аплоадеры и выше.');
        }
        if ($group['access_mode'] !== 'open') {
            stderr('Ошибка', 'Это закрытая группа. Прием участников осуществляется только руководством.');
        }

        sql_query("INSERT IGNORE INTO release_group_members (group_id, user_id, role, added) VALUES (" . $groupId . ", " . $userId . ", 'member', NOW())") or sqlerr(__FILE__, __LINE__);
        tracker_invalidate_release_group_membership_cache($userId);
        tracker_invalidate_release_group_cache($groupId);
        header('Location: groupex.php?id=' . $groupId, true, 302);
        exit;
    }

    if ($action === 'leave_group') {
        if ($userId <= 0 || !$isMember) {
            stderr('Ошибка', 'Вы не состоите в этой группе.');
        }
        if ($userRole === 'owner') {
            stderr('Ошибка', 'Создатель группы не может покинуть её без передачи руководства.');
        }

        sql_query("DELETE FROM release_group_members WHERE group_id = " . $groupId . " AND user_id = " . $userId . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        tracker_invalidate_release_group_membership_cache($userId);
        tracker_invalidate_release_group_cache($groupId);
        header('Location: groupex.php?id=' . $groupId, true, 302);
        exit;
    }

    if ($action === 'update_group') {
        if (!$canManage) {
            stderr('Ошибка', 'Недостаточно прав для управления группой.');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $accessMode = (string)($_POST['access_mode'] ?? 'closed');
        $categoryName = trim((string)($_POST['category_name'] ?? 'Общие интересы'));
        $subcategoryName = trim((string)($_POST['subcategory_name'] ?? 'Другое'));

        if ($name === '') {
            stderr('Ошибка', 'Название группы не может быть пустым.');
        }
        if (!in_array($accessMode, ['open', 'closed'], true)) {
            $accessMode = 'closed';
        }

        sql_query(
            "UPDATE release_groups
             SET name = " . sqlesc($name) . ",
                 description = " . sqlesc($description) . ",
                 image = " . sqlesc($image) . ",
                 access_mode = " . sqlesc($accessMode) . ",
                 category_name = " . sqlesc($categoryName !== '' ? $categoryName : 'Общие интересы') . ",
                 subcategory_name = " . sqlesc($subcategoryName !== '' ? $subcategoryName : 'Другое') . "
             WHERE id = " . $groupId . " LIMIT 1"
        ) or sqlerr(__FILE__, __LINE__);

        tracker_invalidate_release_group_cache($groupId);
        header('Location: groupex.php?id=' . $groupId . '#admin', true, 302);
        exit;
    }

    if ($action === 'add_member') {
        if (!$canManage) {
            stderr('Ошибка', 'Недостаточно прав для управления группой.');
        }

        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetRole = (string)($_POST['target_role'] ?? 'member');
        if (!in_array($targetRole, ['manager', 'member'], true)) {
            $targetRole = 'member';
        }
        if ($targetUserId <= 0) {
            stderr('Ошибка', 'Укажите пользователя.');
        }

        $userRes = sql_query("SELECT id, class FROM users WHERE id = " . $targetUserId . " LIMIT 1");
        $targetUser = $userRes ? mysqli_fetch_assoc($userRes) : null;
        if (!$targetUser) {
            stderr('Ошибка', 'Пользователь не найден.');
        }
        if ((int)($targetUser['class'] ?? 0) < UC_UPLOADER) {
            stderr('Ошибка', 'Добавлять в релиз-группы можно только аплоадеров и выше.');
        }

        sql_query("INSERT INTO release_group_members (group_id, user_id, role, added)
                   VALUES (" . $groupId . ", " . $targetUserId . ", " . sqlesc($targetRole) . ", NOW())
                   ON DUPLICATE KEY UPDATE role = VALUES(role)") or sqlerr(__FILE__, __LINE__);
        tracker_invalidate_release_group_membership_cache($targetUserId);
        tracker_invalidate_release_group_cache($groupId);
        header('Location: groupex.php?id=' . $groupId . '#members', true, 302);
        exit;
    }

    if ($action === 'remove_member') {
        if (!$canManage) {
            stderr('Ошибка', 'Недостаточно прав для управления группой.');
        }

        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetRole = tracker_release_group_role($groupId, $targetUserId);
        if ($targetUserId <= 0 || $targetRole === null) {
            stderr('Ошибка', 'Участник не найден.');
        }
        if ($targetRole === 'owner') {
            stderr('Ошибка', 'Нельзя удалить владельца группы.');
        }

        sql_query("DELETE FROM release_group_members WHERE group_id = " . $groupId . " AND user_id = " . $targetUserId . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        tracker_invalidate_release_group_membership_cache($targetUserId);
        tracker_invalidate_release_group_cache($groupId);
        header('Location: groupex.php?id=' . $groupId . '#members', true, 302);
        exit;
    }

    if ($action === 'add_wall') {
        if ($userId <= 0 || (!$isMember && !$canManage)) {
            stderr('Ошибка', 'Писать на стене могут только участники группы.');
        }

        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') {
            stderr('Ошибка', 'Введите текст сообщения.');
        }

        sql_query("INSERT INTO release_group_wall (group_id, user_id, added, text) VALUES (" . $groupId . ", " . $userId . ", NOW(), " . sqlesc($text) . ")") or sqlerr(__FILE__, __LINE__);
        header('Location: groupex.php?id=' . $groupId . '#wall', true, 302);
        exit;
    }

    if ($action === 'delete_wall') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $postRes = sql_query("SELECT user_id FROM release_group_wall WHERE id = " . $postId . " AND group_id = " . $groupId . " LIMIT 1");
        $postRow = $postRes ? mysqli_fetch_assoc($postRes) : null;
        if (!$postRow) {
            stderr('Ошибка', 'Сообщение не найдено.');
        }

        if (!$canManage && (int)($postRow['user_id'] ?? 0) !== $userId) {
            stderr('Ошибка', 'Недостаточно прав для удаления сообщения.');
        }

        sql_query("DELETE FROM release_group_wall WHERE id = " . $postId . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        header('Location: groupex.php?id=' . $groupId . '#wall', true, 302);
        exit;
    }
}

$group = tracker_get_release_group($groupId);
$userRole = $userId > 0 ? tracker_release_group_role($groupId, $userId) : null;
$isMember = $userRole !== null;
$canManage = $userId > 0 && tracker_can_manage_release_group($groupId, $userId);
$bookmarked = $userId > 0 ? tracker_user_has_release_group_bookmark($userId, $groupId) : false;

$members = [];
$membersRes = sql_query("
    SELECT gm.user_id, gm.role, gm.added, u.username, u.class
    FROM release_group_members AS gm
    INNER JOIN users AS u ON u.id = gm.user_id
    WHERE gm.group_id = " . $groupId . "
    ORDER BY CASE gm.role WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END, u.username ASC
") or sqlerr(__FILE__, __LINE__);
while ($membersRes && ($row = mysqli_fetch_assoc($membersRes))) {
    $members[] = $row;
}

$torrents = [];
$torrentRes = sql_query("
    SELECT id, name, added, seeders, leechers, times_completed
    FROM torrents
    WHERE release_group_id = " . $groupId . "
    ORDER BY added DESC, id DESC
    LIMIT 100
") or sqlerr(__FILE__, __LINE__);
while ($torrentRes && ($row = mysqli_fetch_assoc($torrentRes))) {
    $torrents[] = $row;
}

$wallPosts = [];
$wallRes = sql_query("
    SELECT w.id, w.user_id, w.added, w.text, u.username, u.class
    FROM release_group_wall AS w
    INNER JOIN users AS u ON u.id = w.user_id
    WHERE w.group_id = " . $groupId . "
    ORDER BY w.id DESC
    LIMIT 50
") or sqlerr(__FILE__, __LINE__);
while ($wallRes && ($row = mysqli_fetch_assoc($wallRes))) {
    $wallPosts[] = $row;
}

$leaders = array_values(array_filter($members, static fn(array $member): bool => in_array((string)($member['role'] ?? ''), ['owner', 'manager'], true)));
$statusLabel = match ($userRole) {
    'owner' => 'Вы владелец группы',
    'manager' => 'Вы состоите в руководстве группы',
    'member' => 'Вы участник группы',
    default => 'Вы не состоите в этой группе',
};
$accessText = $group['access_mode'] === 'open'
    ? 'Это открытая группа. Вступление доступно аплоадерам и выше.'
    : 'Это закрытая группа. Прием участников осуществляется только Руководством группы';

stdhead('Группа ' . gx_h((string)$group['name']));
begin_frame('Название: ' . gx_h((string)$group['name']));
?>
<style>
.gx-view{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px}
.gx-box{border:1px solid #d8e1ea;border-radius:14px;background:#fff;padding:14px;margin-bottom:12px}
.gx-topmeta td{padding:6px 8px;border-bottom:1px solid #eef2f6}
.gx-topmeta tr:last-child td{border-bottom:none}
.gx-side a,.gx-side span{display:block;padding:8px 10px;border-radius:10px;text-decoration:none}
.gx-side .current{background:#eef5ff;color:#1d4f91;font-weight:700}
.gx-wall-item{border:1px solid #e1e8ef;border-radius:12px;padding:12px;margin-bottom:10px;background:#fbfdff}
@media (max-width: 920px){.gx-view{grid-template-columns:1fr}}
</style>

<div class="gx-view">
  <div>
    <div class="gx-box">
      <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
        <?= tracker_release_group_badge_html($group) ?>
      </div>
      <table class="gx-topmeta" width="100%" cellspacing="0" cellpadding="0">
        <tr><td width="180"><b>Название:</b></td><td><?= gx_h((string)$group['name']) ?></td></tr>
        <tr><td><b>Тип:</b></td><td><?= gx_h((string)$group['group_type']) ?></td></tr>
        <tr><td><b>Категория:</b></td><td><?= gx_h((string)$group['category_name']) ?></td></tr>
        <tr><td><b>Подкатегория:</b></td><td><?= gx_h((string)$group['subcategory_name']) ?></td></tr>
        <tr><td><b>Время создания:</b></td><td><?= gx_h((string)$group['added']) ?></td></tr>
      </table>
    </div>

    <div class="gx-box" id="torrents">
      <div style="font-weight:700;margin-bottom:10px;">Список раздач</div>
      <?php if (!$torrents): ?>
        <div>Пока у группы нет привязанных раздач.</div>
      <?php else: ?>
        <table width="100%" border="1" cellspacing="0" cellpadding="5">
          <tr>
            <td class="colhead">Раздача</td>
            <td class="colhead" align="center">Сиды</td>
            <td class="colhead" align="center">Личи</td>
            <td class="colhead" align="center">Скачали</td>
            <td class="colhead" align="center">Добавлена</td>
          </tr>
          <?php foreach ($torrents as $torrent): ?>
            <tr>
              <td class="lol"><a href="details.php?id=<?= (int)$torrent['id'] ?>"><?= gx_h((string)$torrent['name']) ?></a></td>
              <td class="lol" align="center"><?= (int)$torrent['seeders'] ?></td>
              <td class="lol" align="center"><?= (int)$torrent['leechers'] ?></td>
              <td class="lol" align="center"><?= (int)$torrent['times_completed'] ?></td>
              <td class="lol" align="center"><?= gx_h((string)$torrent['added']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="gx-box" id="members">
      <div style="font-weight:700;margin-bottom:10px;">Участники</div>
      <table width="100%" border="1" cellspacing="0" cellpadding="5">
        <tr>
          <td class="colhead">Пользователь</td>
          <td class="colhead" align="center">Роль</td>
          <td class="colhead" align="center">Вступил</td>
          <?php if ($canManage): ?><td class="colhead" align="center">Действие</td><?php endif; ?>
        </tr>
        <?php foreach ($members as $member): ?>
          <tr>
            <td class="lol"><a href="userdetails.php?id=<?= (int)$member['user_id'] ?>"><?= get_user_class_color((int)$member['class'], (string)$member['username']) ?></a></td>
            <td class="lol" align="center"><?= gx_h((string)$member['role']) ?></td>
            <td class="lol" align="center"><?= gx_h((string)$member['added']) ?></td>
            <?php if ($canManage): ?>
              <td class="lol" align="center">
                <?php if ((string)$member['role'] !== 'owner'): ?>
                  <form method="post" action="groupex.php?id=<?= $groupId ?>" style="margin:0;">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="target_user_id" value="<?= (int)$member['user_id'] ?>">
                    <button type="submit" class="btn">Удалить</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="gx-box" id="wall">
      <div style="font-weight:700;margin-bottom:10px;">Стена группы</div>
      <?php if ($userId > 0 && ($isMember || $canManage)): ?>
        <form method="post" action="groupex.php?id=<?= $groupId ?>" style="margin-bottom:14px;">
          <input type="hidden" name="action" value="add_wall">
          <textarea name="text" rows="4" style="width:100%" placeholder="Написать сообщение группе"></textarea>
          <div style="margin-top:8px;"><button type="submit" class="btn">Отправить</button></div>
        </form>
      <?php endif; ?>

      <?php if (!$wallPosts): ?>
        <div>На стене пока нет сообщений.</div>
      <?php else: ?>
        <?php foreach ($wallPosts as $post): ?>
          <div class="gx-wall-item">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
              <div>
                <a href="userdetails.php?id=<?= (int)$post['user_id'] ?>"><?= get_user_class_color((int)$post['class'], (string)$post['username']) ?></a>
                <div style="font-size:12px;color:#6b7d92;"><?= gx_h((string)$post['added']) ?></div>
              </div>
              <?php if ($canManage || (int)$post['user_id'] === $userId): ?>
                <form method="post" action="groupex.php?id=<?= $groupId ?>" style="margin:0;">
                  <input type="hidden" name="action" value="delete_wall">
                  <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                  <button type="submit" class="btn">Удалить</button>
                </form>
              <?php endif; ?>
            </div>
            <div style="margin-top:8px;line-height:1.5;"><?= format_comment((string)$post['text']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="gx-side">
    <div class="gx-box">
      <div style="font-weight:700;margin-bottom:8px;">Меню</div>
      <a href="#torrents" class="current">Список раздач</a>
      <a href="#members">Участники</a>
      <?php if ($userId > 0 && !$isMember && $group['access_mode'] === 'open' && $userClass >= UC_UPLOADER): ?>
        <form method="post" action="groupex.php?id=<?= $groupId ?>" style="margin:8px 0 0 0;">
          <input type="hidden" name="action" value="join_group">
          <button type="submit" class="btn" style="width:100%;">Вступить в группу</button>
        </form>
      <?php elseif ($userId > 0 && $isMember && $userRole !== 'owner'): ?>
        <form method="post" action="groupex.php?id=<?= $groupId ?>" style="margin:8px 0 0 0;">
          <input type="hidden" name="action" value="leave_group">
          <button type="submit" class="btn" style="width:100%;">Покинуть группу</button>
        </form>
      <?php else: ?>
        <span>Вступить в группу</span>
      <?php endif; ?>
      <?php if ($userId > 0): ?>
        <form method="post" action="bookmark.php" style="margin:8px 0 0 0;">
          <input type="hidden" name="type" value="release_group">
          <input type="hidden" name="entity_id" value="<?= $groupId ?>">
          <input type="hidden" name="returnto" value="<?= gx_h('groupex.php?id=' . $groupId) ?>">
          <button type="submit" class="btn" style="width:100%;"><?= $bookmarked ? 'Убрать из закладок' : 'Добавить в закладки' ?></button>
        </form>
      <?php endif; ?>
    </div>

    <div class="gx-box">
      <div style="font-weight:700;margin-bottom:8px;">Доступ к группе</div>
      <div><?= gx_h($accessText) ?></div>
    </div>

    <div class="gx-box">
      <div style="font-weight:700;margin-bottom:8px;">Ваш статус в группе</div>
      <div><?= gx_h($statusLabel) ?></div>
    </div>

    <div class="gx-box">
      <div style="font-weight:700;margin-bottom:8px;">Руководство</div>
      <?php if (!$leaders): ?>
        <div>Руководство пока не назначено.</div>
      <?php else: ?>
        <?php foreach ($leaders as $leader): ?>
          <div style="margin-bottom:8px;">
            <a href="userdetails.php?id=<?= (int)$leader['user_id'] ?>"><?= get_user_class_color((int)$leader['class'], (string)$leader['username']) ?></a>
            <div style="font-size:12px;color:#6b7d92;"><?= gx_h((string)$leader['role']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
      <div class="gx-box" id="admin">
        <div style="font-weight:700;margin-bottom:8px;">Админка группы</div>
        <form method="post" action="groupex.php?id=<?= $groupId ?>">
          <input type="hidden" name="action" value="update_group">
          <div style="margin-bottom:8px;"><label>Название</label><br><input type="text" name="name" value="<?= gx_h((string)$group['name']) ?>" style="width:100%"></div>
          <div style="margin-bottom:8px;"><label>Картинка</label><br><input type="url" name="image" value="<?= gx_h((string)$group['image']) ?>" style="width:100%"></div>
          <div style="margin-bottom:8px;"><label>Категория</label><br><input type="text" name="category_name" value="<?= gx_h((string)$group['category_name']) ?>" style="width:100%"></div>
          <div style="margin-bottom:8px;"><label>Подкатегория</label><br><input type="text" name="subcategory_name" value="<?= gx_h((string)$group['subcategory_name']) ?>" style="width:100%"></div>
          <div style="margin-bottom:8px;"><label>Доступ</label><br>
            <select name="access_mode" style="width:100%">
              <option value="closed"<?= $group['access_mode'] === 'closed' ? ' selected' : '' ?>>Закрытая</option>
              <option value="open"<?= $group['access_mode'] === 'open' ? ' selected' : '' ?>>Открытая</option>
            </select>
          </div>
          <div style="margin-bottom:8px;"><label>Описание</label><br><textarea name="description" rows="5" style="width:100%"><?= gx_h((string)$group['description']) ?></textarea></div>
          <button type="submit" class="btn">Сохранить</button>
        </form>

        <hr style="margin:14px 0;">

        <form method="post" action="groupex.php?id=<?= $groupId ?>">
          <input type="hidden" name="action" value="add_member">
          <div style="margin-bottom:8px;"><label>ID пользователя</label><br><input type="number" name="target_user_id" style="width:100%"></div>
          <div style="margin-bottom:8px;"><label>Роль</label><br>
            <select name="target_role" style="width:100%">
              <option value="member">Участник</option>
              <option value="manager">Руководство</option>
            </select>
          </div>
          <button type="submit" class="btn">Добавить участника</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
end_frame();
stdfoot();
