<?php
if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

function ClassPermissions(): void
{
    if (get_user_class() < UC_SYSOP) {
        stderr('Ошибка', 'Доступ запрещен.');
    }

    class_permissions_ensure_schema();

    $action = (string)($_POST['cp_action'] ?? $_GET['cp_action'] ?? '');
    $editId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $baseClass = (int)($_POST['base_class'] ?? UC_USER);
        $modules = is_array($_POST['modules'] ?? null) ? array_map('strval', $_POST['modules']) : [];
        class_permissions_save_profile($editId, $name, $description, $baseClass, $modules);
        header('Location: admincp.php?op=class_permissions&saved=1');
        exit;
    }

    if ($action === 'delete' && $editId > 0) {
        class_permissions_delete_profile($editId);
        header('Location: admincp.php?op=class_permissions&deleted=1');
        exit;
    }

    $editing = $editId > 0 ? class_permissions_get_profile($editId) : null;
    $editingModules = $editing ? class_permissions_get_profile_permissions($editId) : [];
    $profiles = class_permissions_get_profiles();
    $groupedCatalog = class_permissions_grouped_catalog();

    begin_frame('Управление классами пользователей');
    echo '<div style="padding:8px 0">';
    echo '<p><b>Мод управления классами пользователей</b> позволяет создавать свои профили доступа и назначать им набор модулей без правки кода.</p>';
    if (!empty($_GET['saved'])) {
        echo '<div class="tab_success">Профиль класса сохранен.</div>';
    }
    if (!empty($_GET['deleted'])) {
        echo '<div class="tab_success">Профиль класса удален.</div>';
    }
    echo '</div>';

    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="4">Существующие профили</td></tr>';
    echo '<tr><td class="rowhead"><b>Название</b></td><td class="rowhead"><b>Базовый класс</b></td><td class="rowhead"><b>Модулей</b></td><td class="rowhead"><b>Действия</b></td></tr>';

    if ($profiles) {
        foreach ($profiles as $profile) {
            $pid = (int)$profile['id'];
            echo '<tr>';
            echo '<td class="lol"><b>' . htmlspecialchars((string)$profile['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><br><small>' . htmlspecialchars((string)$profile['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small></td>';
            echo '<td class="lol">' . htmlspecialchars(get_user_class_name((int)$profile['base_class']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td class="lol" align="center">' . (int)$profile['modules_count'] . '</td>';
            echo '<td class="lol"><a href="admincp.php?op=class_permissions&id=' . $pid . '"><b>Редактировать</b></a> | <a href="admincp.php?op=class_permissions&cp_action=delete&id=' . $pid . '" onclick="return confirm(\'Удалить профиль класса?\')"><b>Удалить</b></a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td class="lol" colspan="4">Пока нет ни одного пользовательского класса.</td></tr>';
    }
    echo '</table><br>';

    echo '<form method="post" action="admincp.php?op=class_permissions">';
    if ($editing) {
        echo '<input type="hidden" name="id" value="' . (int)$editing['id'] . '">';
    }
    echo '<input type="hidden" name="cp_action" value="save">';
    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="2">' . ($editing ? 'Редактирование профиля класса' : 'Добавление профиля класса') . '</td></tr>';
    echo '<tr><td class="rowhead" width="180">Название</td><td class="lol"><input type="text" name="name" size="60" value="' . htmlspecialchars((string)($editing['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td></tr>';
    echo '<tr><td class="rowhead">Описание</td><td class="lol"><textarea name="description" cols="60" rows="3">' . htmlspecialchars((string)($editing['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea></td></tr>';
    echo '<tr><td class="rowhead">Базовый класс</td><td class="lol"><select name="base_class">';
    for ($i = UC_USER; $i <= UC_SYSOP; $i++) {
        $selected = ((int)($editing['base_class'] ?? UC_USER) === $i) ? ' selected' : '';
        echo '<option value="' . $i . '"' . $selected . '>' . htmlspecialchars(get_user_class_name($i), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td class="rowhead" valign="top">Модули</td><td class="lol">';

    foreach ($groupedCatalog as $group => $items) {
        echo '<div style="margin-bottom:10px"><b>' . htmlspecialchars($group, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><br>';
        foreach ($items as $key => $label) {
            $checked = in_array($key, $editingModules, true) ? ' checked' : '';
            echo '<label style="display:inline-block;min-width:280px;margin:4px 10px 4px 0;"><input type="checkbox" name="modules[]" value="' . htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $checked . '> ' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</label>';
        }
        echo '</div>';
    }

    echo '</td></tr>';
    echo '<tr><td class="lol" colspan="2" align="center"><input type="submit" value="' . ($editing ? 'Сохранить изменения' : 'Добавить класс') . '"></td></tr>';
    echo '</table>';
    echo '</form>';
    end_frame();
}

switch ($op) {
    case 'class_permissions':
    case 'ClassPermissions':
        ClassPermissions();
        break;
}
?>
