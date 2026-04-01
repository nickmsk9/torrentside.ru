<?php
if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

if (!function_exists('class_permissions_admin_redirect')) {
    function class_permissions_admin_redirect(string $query = ''): void
    {
        $url = 'admincp.php?op=class_permissions';
        if ($query !== '') {
            $url .= '&' . ltrim($query, '&');
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('class_permissions_admin_trophy_holder_html')) {
    function class_permissions_admin_trophy_holder_html(array $trophy): string
    {
        $holderId = (int)($trophy['holder_user_id'] ?? 0);
        if ($holderId <= 0 || empty($trophy['holder_username'])) {
            return '-';
        }

        $holderName = htmlspecialchars((string)$trophy['holder_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $holderHtml = get_user_class_color((int)($trophy['holder_class'] ?? 0), $holderName);
        return '<a href="userdetails.php?id=' . $holderId . '">' . $holderHtml . '</a>';
    }
}

function ClassPermissions(): void
{
    global $CURUSER;

    if (get_user_class() < UC_SYSOP) {
        stderr('Ошибка', 'Доступ запрещен.');
    }

    class_permissions_ensure_schema();

    $action = (string)($_POST['cp_action'] ?? $_GET['cp_action'] ?? '');
    $profileId = (int)($_GET['profile_id'] ?? $_POST['profile_id'] ?? 0);
    $classId = isset($_GET['class_id']) || isset($_POST['class_id'])
        ? (int)($_GET['class_id'] ?? $_POST['class_id'])
        : UC_USER;
    $trophyId = (int)($_GET['trophy_id'] ?? $_POST['trophy_id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $baseClass = (int)($_POST['base_class'] ?? UC_USER);
            $modules = is_array($_POST['modules'] ?? null) ? array_map('strval', $_POST['modules']) : [];
            class_permissions_save_profile($profileId, $name, $description, $baseClass, $modules);
            class_permissions_admin_redirect('saved=profile');
        }

        if ($action === 'save_class_meta') {
            class_permissions_save_class_meta($classId, [
                'constant_name' => (string)($_POST['constant_name'] ?? ''),
                'lang_key' => (string)($_POST['lang_key'] ?? ''),
                'fallback_name' => (string)($_POST['fallback_name'] ?? ''),
                'display_name' => (string)($_POST['display_name'] ?? ''),
                'display_color' => (string)($_POST['display_color'] ?? ''),
                'display_style' => (string)($_POST['display_style'] ?? ''),
                'sort_order' => (int)($_POST['sort_order'] ?? 1),
                'is_override_allowed' => (string)($_POST['is_override_allowed'] ?? 'yes'),
                'notes' => (string)($_POST['notes'] ?? ''),
            ]);
            class_permissions_admin_redirect('saved=class&class_id=' . $classId);
        }

        if ($action === 'save_trophy') {
            $previousTrophy = $trophyId > 0 ? class_permissions_get_trophy($trophyId) : null;
            $holderUserId = (int)($_POST['holder_user_id'] ?? 0);
            $holderComment = trim((string)($_POST['holder_comment'] ?? ''));

            $savedId = class_permissions_save_trophy($trophyId, [
                'name' => (string)($_POST['name'] ?? ''),
                'rangpic' => (string)($_POST['rangpic'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'sort_order' => (int)($_POST['sort_order'] ?? 1),
                'is_transition' => (string)($_POST['is_transition'] ?? 'no'),
                'is_active' => (string)($_POST['is_active'] ?? 'yes'),
                'auto_enabled' => (string)($_POST['auto_enabled'] ?? 'no'),
                'auto_metric' => (string)($_POST['auto_metric'] ?? ''),
                'auto_period_days' => (int)($_POST['auto_period_days'] ?? 0),
                'auto_direction' => (string)($_POST['auto_direction'] ?? 'max'),
                'auto_min_value' => (int)($_POST['auto_min_value'] ?? 0),
                'auto_refresh_minutes' => (int)($_POST['auto_refresh_minutes'] ?? 10),
            ]);

            $savedTrophy = class_permissions_get_trophy($savedId);
            if ($savedTrophy && ($savedTrophy['is_transition'] ?? 'no') === 'yes') {
                if ($holderUserId > 0) {
                    class_permissions_assign_transition_trophy_holder($savedId, $holderUserId, (int)$CURUSER['id'], $holderComment);
                } else {
                    $previousHolderId = (int)($previousTrophy['holder_user_id'] ?? 0);
                    if ($previousHolderId > 0) {
                        class_permissions_release_transition_trophy(
                            $savedId,
                            $previousHolderId,
                            (int)$CURUSER['id'],
                            $holderComment !== '' ? $holderComment : 'Кубок освобожден администратором.'
                        );
                    } elseif ($holderComment !== '') {
                        sql_query("UPDATE rangclass SET holder_comment = " . sqlesc($holderComment) . " WHERE id = {$savedId}");
                        class_permissions_invalidate_trophy_cache();
                    }
                }
            } elseif ($previousTrophy && (int)($previousTrophy['holder_user_id'] ?? 0) > 0) {
                class_permissions_release_transition_trophy(
                    $savedId,
                    (int)$previousTrophy['holder_user_id'],
                    (int)$CURUSER['id'],
                    'Кубок переведен в обычный ранг.'
                );
            }

            class_permissions_admin_redirect('saved=trophy&trophy_id=' . $savedId);
        }
    }

    if ($action === 'delete_profile' && $profileId > 0) {
        class_permissions_delete_profile($profileId);
        class_permissions_admin_redirect('deleted=profile');
    }

    if ($action === 'move_class' && class_permissions_core_class_exists($classId)) {
        $direction = ((string)($_GET['direction'] ?? 'up')) === 'down' ? 'down' : 'up';
        $swapUsers = !empty($_GET['swap_users']);
        class_permissions_move_class($classId, $direction, $swapUsers);
        class_permissions_admin_redirect('moved=class&class_id=' . $classId);
    }

    if ($action === 'move_trophy' && $trophyId > 0) {
        $direction = ((string)($_GET['direction'] ?? 'up')) === 'down' ? 'down' : 'up';
        class_permissions_move_trophy($trophyId, $direction);
        class_permissions_admin_redirect('moved=trophy&trophy_id=' . $trophyId);
    }

    if ($action === 'delete_trophy' && $trophyId > 0) {
        class_permissions_delete_trophy($trophyId, (int)($CURUSER['id'] ?? 0));
        class_permissions_admin_redirect('deleted=trophy');
    }

    $editingProfile = $profileId > 0 ? class_permissions_get_profile($profileId) : null;
    $editingProfileModules = $editingProfile ? class_permissions_get_profile_permissions((int)$editingProfile['id']) : [];
    $editingClass = class_permissions_get_class_meta($classId);
    $editingTrophy = $trophyId > 0 ? class_permissions_get_trophy($trophyId) : null;

    $profiles = class_permissions_get_profiles();
    $groupedCatalog = class_permissions_grouped_catalog();
    $classCatalog = class_permissions_get_class_catalog();
    $baseClassOptions = class_permissions_get_selectable_classes(UC_SYSOP);
    $trophies = class_permissions_get_trophies(true);
    $metricCatalog = class_permissions_transition_metric_catalog();
    $trophyHistory = $editingTrophy ? class_permissions_get_trophy_history((int)$editingTrophy['id'], 12) : [];

    begin_frame('Управление классами, доступами и кубками');
    echo '<div style="padding:8px 0">';
    echo '<p><b>Модуль объединяет три вещи:</b> каталог системных классов, профили доступов и переходящие кубки/ранги. Числовая лестница прав ядра остается фиксированной, а здесь настраиваются названия, оформление, порядок и перенос пользователей между ступенями.</p>';
    echo '<p><small>Адаптация старого `classes.php` вынесена в базу: константа, ключ локализации, имя, цвет, порядок и возможность временного понижения теперь управляются из админки без правки файлов.</small></p>';

    $saved = (string)($_GET['saved'] ?? '');
    $deleted = (string)($_GET['deleted'] ?? '');
    $moved = (string)($_GET['moved'] ?? '');
    if ($saved === 'profile') {
        echo '<div class="tab_success">Профиль доступа сохранен.</div>';
    } elseif ($saved === 'class') {
        echo '<div class="tab_success">Параметры системного класса сохранены.</div>';
    } elseif ($saved === 'trophy') {
        echo '<div class="tab_success">Ранг или кубок сохранен.</div>';
    }
    if ($deleted === 'profile') {
        echo '<div class="tab_success">Профиль доступа удален.</div>';
    } elseif ($deleted === 'trophy') {
        echo '<div class="tab_success">Ранг или кубок удален.</div>';
    }
    if ($moved === 'class') {
        echo '<div class="tab_success">Порядок классов обновлен.</div>';
    } elseif ($moved === 'trophy') {
        echo '<div class="tab_success">Порядок рангов и кубков обновлен.</div>';
    }
    echo '</div>';

    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="7">Каталог системных классов</td></tr>';
    echo '<tr>';
    echo '<td class="rowhead" width="60"><b>Порядок</b></td>';
    echo '<td class="rowhead" width="90"><b>Уровень</b></td>';
    echo '<td class="rowhead"><b>Константа / ключ</b></td>';
    echo '<td class="rowhead"><b>Имя</b></td>';
    echo '<td class="rowhead"><b>Превью</b></td>';
    echo '<td class="rowhead" width="110"><b>Override</b></td>';
    echo '<td class="rowhead" width="240"><b>Действия</b></td>';
    echo '</tr>';

    foreach ($classCatalog as $meta) {
        $baseClass = (int)$meta['base_class'];
        $constantName = htmlspecialchars((string)($meta['constant_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $langKey = htmlspecialchars((string)($meta['lang_key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $effectiveName = htmlspecialchars(class_permissions_effective_class_name($meta), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $preview = class_permissions_render_class_preview($baseClass, $effectiveName);
        $overrideText = (($meta['is_override_allowed'] ?? 'yes') === 'yes') ? 'Разрешен' : 'Запрещен';

        echo '<tr>';
        echo '<td class="lol" align="center">' . (int)($meta['sort_order'] ?? 0) . '</td>';
        echo '<td class="lol" align="center">' . $baseClass . '</td>';
        echo '<td class="lol"><b>' . $constantName . '</b><br><small>' . $langKey . '</small></td>';
        echo '<td class="lol"><b>' . $effectiveName . '</b></td>';
        echo '<td class="lol">' . $preview . '</td>';
        echo '<td class="lol" align="center">' . $overrideText . '</td>';
        echo '<td class="lol">';
        echo '<a href="admincp.php?op=class_permissions&class_id=' . $baseClass . '"><b>Редактировать</b></a> | ';
        echo '<a href="admincp.php?op=class_permissions&cp_action=move_class&class_id=' . $baseClass . '&direction=up"><b>Вверх</b></a> | ';
        echo '<a href="admincp.php?op=class_permissions&cp_action=move_class&class_id=' . $baseClass . '&direction=down"><b>Вниз</b></a> | ';
        echo '<a href="admincp.php?op=class_permissions&cp_action=move_class&class_id=' . $baseClass . '&direction=up&swap_users=1" onclick="return confirm(\'Поднять класс и поменять местами пользователей/override/профили у соседних ступеней?\')"><b>Вверх + пользователи</b></a> | ';
        echo '<a href="admincp.php?op=class_permissions&cp_action=move_class&class_id=' . $baseClass . '&direction=down&swap_users=1" onclick="return confirm(\'Опустить класс и поменять местами пользователей/override/профили у соседних ступеней?\')"><b>Вниз + пользователи</b></a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</table><br>';

    if ($editingClass) {
        echo '<form method="post" action="admincp.php?op=class_permissions">';
        echo '<input type="hidden" name="cp_action" value="save_class_meta">';
        echo '<input type="hidden" name="class_id" value="' . (int)$editingClass['base_class'] . '">';
        echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
        echo '<tr><td class="colhead" colspan="2">Редактирование системного класса</td></tr>';
        echo '<tr><td class="rowhead" width="200">Системный уровень</td><td class="lol"><b>' . (int)$editingClass['base_class'] . '</b><br><small>Числовой уровень прав в ядре не меняется из админки.</small></td></tr>';
        echo '<tr><td class="rowhead">Константа</td><td class="lol"><input type="text" name="constant_name" size="40" value="' . htmlspecialchars((string)($editingClass['constant_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td></tr>';
        echo '<tr><td class="rowhead">Ключ локализации</td><td class="lol"><input type="text" name="lang_key" size="40" value="' . htmlspecialchars((string)($editingClass['lang_key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td></tr>';
        echo '<tr><td class="rowhead">Фолбэк-имя</td><td class="lol"><input type="text" name="fallback_name" size="60" value="' . htmlspecialchars((string)($editingClass['fallback_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><br><small>Используется, если в языке нет ключа или вы не задали кастомное имя.</small></td></tr>';
        echo '<tr><td class="rowhead">Кастомное имя</td><td class="lol"><input type="text" name="display_name" size="60" value="' . htmlspecialchars((string)($editingClass['display_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><br><small>Если заполнено, будет показано вместо языкового ключа.</small></td></tr>';
        echo '<tr><td class="rowhead">Цвет</td><td class="lol"><input type="text" name="display_color" size="20" value="' . htmlspecialchars((string)($editingClass['display_color'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><br><small>Например: `#FFD700`, `orange`, `rgb(255,0,0)`.</small></td></tr>';
        echo '<tr><td class="rowhead">Полный CSS-стиль</td><td class="lol"><textarea name="display_style" cols="70" rows="3">' . htmlspecialchars((string)($editingClass['display_style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea><br><small>Если заполнен, он имеет приоритет над полем цвета.</small></td></tr>';
        echo '<tr><td class="rowhead">Порядок</td><td class="lol"><input type="text" name="sort_order" size="10" value="' . (int)($editingClass['sort_order'] ?? 1) . '"><br><small>Обычно удобнее двигать кнопками “вверх/вниз”.</small></td></tr>';
        echo '<tr><td class="rowhead">Разрешить временное понижение до класса</td><td class="lol"><label><input type="radio" name="is_override_allowed" value="yes"' . ((($editingClass['is_override_allowed'] ?? 'yes') === 'yes') ? ' checked' : '') . '> Да</label> <label><input type="radio" name="is_override_allowed" value="no"' . ((($editingClass['is_override_allowed'] ?? 'yes') === 'no') ? ' checked' : '') . '> Нет</label></td></tr>';
        echo '<tr><td class="rowhead">Заметки</td><td class="lol"><textarea name="notes" cols="70" rows="3">' . htmlspecialchars((string)($editingClass['notes'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea></td></tr>';
        echo '<tr><td class="lol" colspan="2" align="center"><input type="submit" value="Сохранить настройки класса"></td></tr>';
        echo '</table>';
        echo '</form><br>';
    }

    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="4">Профили доступов</td></tr>';
    echo '<tr><td class="rowhead"><b>Название</b></td><td class="rowhead"><b>Базовый класс</b></td><td class="rowhead"><b>Модулей</b></td><td class="rowhead"><b>Действия</b></td></tr>';
    if ($profiles) {
        foreach ($profiles as $profile) {
            $pid = (int)$profile['id'];
            echo '<tr>';
            echo '<td class="lol"><b>' . htmlspecialchars((string)$profile['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><br><small>' . htmlspecialchars((string)$profile['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small></td>';
            echo '<td class="lol">' . htmlspecialchars(get_user_class_name((int)$profile['base_class']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td class="lol" align="center">' . (int)$profile['modules_count'] . '</td>';
            echo '<td class="lol"><a href="admincp.php?op=class_permissions&profile_id=' . $pid . '"><b>Редактировать</b></a> | <a href="admincp.php?op=class_permissions&cp_action=delete_profile&profile_id=' . $pid . '" onclick="return confirm(\'Удалить профиль доступа?\')"><b>Удалить</b></a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td class="lol" colspan="4">Пока нет ни одного профиля доступа.</td></tr>';
    }
    echo '</table><br>';

    echo '<form method="post" action="admincp.php?op=class_permissions">';
    if ($editingProfile) {
        echo '<input type="hidden" name="profile_id" value="' . (int)$editingProfile['id'] . '">';
    }
    echo '<input type="hidden" name="cp_action" value="save_profile">';
    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="2">' . ($editingProfile ? 'Редактирование профиля доступа' : 'Добавление профиля доступа') . '</td></tr>';
    echo '<tr><td class="rowhead" width="200">Название</td><td class="lol"><input type="text" name="name" size="60" value="' . htmlspecialchars((string)($editingProfile['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td></tr>';
    echo '<tr><td class="rowhead">Описание</td><td class="lol"><textarea name="description" cols="60" rows="3">' . htmlspecialchars((string)($editingProfile['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea></td></tr>';
    echo '<tr><td class="rowhead">Базовый класс</td><td class="lol"><select name="base_class">';
    foreach ($baseClassOptions as $classMeta) {
        $baseClass = (int)$classMeta['base_class'];
        $selected = ((int)($editingProfile['base_class'] ?? UC_USER) === $baseClass) ? ' selected' : '';
        echo '<option value="' . $baseClass . '"' . $selected . '>' . htmlspecialchars(get_user_class_name($baseClass), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td class="rowhead" valign="top">Модули</td><td class="lol">';
    foreach ($groupedCatalog as $group => $items) {
        echo '<div style="margin-bottom:10px"><b>' . htmlspecialchars($group, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b><br>';
        foreach ($items as $key => $label) {
            $checked = in_array($key, $editingProfileModules, true) ? ' checked' : '';
            echo '<label style="display:inline-block;min-width:280px;margin:4px 10px 4px 0;"><input type="checkbox" name="modules[]" value="' . htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $checked . '> ' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</label>';
        }
        echo '</div>';
    }
    echo '</td></tr>';
    echo '<tr><td class="lol" colspan="2" align="center"><input type="submit" value="' . ($editingProfile ? 'Сохранить профиль' : 'Добавить профиль') . '"></td></tr>';
    echo '</table>';
    echo '</form><br>';

    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="7">Ранги и переходящие кубки</td></tr>';
    echo '<tr>';
    echo '<td class="rowhead" width="60"><b>Порядок</b></td>';
    echo '<td class="rowhead"><b>Название</b></td>';
    echo '<td class="rowhead" width="140"><b>Картинка</b></td>';
    echo '<td class="rowhead" width="160"><b>Тип</b></td>';
    echo '<td class="rowhead"><b>Текущий владелец</b></td>';
    echo '<td class="rowhead" width="100"><b>Статус</b></td>';
    echo '<td class="rowhead" width="200"><b>Действия</b></td>';
    echo '</tr>';

    if ($trophies) {
        foreach ($trophies as $trophy) {
            $tid = (int)$trophy['id'];
            $pic = trim((string)($trophy['rangpic'] ?? ''));
            $picHtml = $pic !== ''
                ? '<img src="/pic/' . htmlspecialchars($pic, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" style="max-width:120px;max-height:40px">'
                : '<small>Нет картинки</small>';
            $type = (($trophy['is_transition'] ?? 'no') === 'yes') ? 'Переходящий кубок' : 'Обычный ранг';
            if (($trophy['is_transition'] ?? 'no') === 'yes' && ($trophy['auto_enabled'] ?? 'no') === 'yes') {
                $type .= '<br><small>Авто: ' . htmlspecialchars(class_permissions_transition_metric_label((string)($trophy['auto_metric'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>';
            }
            $status = (($trophy['is_active'] ?? 'yes') === 'yes') ? 'Активен' : 'Скрыт';

            echo '<tr>';
            echo '<td class="lol" align="center">' . (int)($trophy['sort_order'] ?? 0) . '</td>';
            echo '<td class="lol"><b>' . htmlspecialchars((string)$trophy['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
            if (!empty($trophy['description'])) {
                echo '<br><small>' . htmlspecialchars((string)$trophy['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>';
            }
            echo '</td>';
            echo '<td class="lol" align="center">' . $picHtml . '</td>';
            echo '<td class="lol">' . $type . '</td>';
            echo '<td class="lol">' . class_permissions_admin_trophy_holder_html($trophy);
            if (!empty($trophy['holder_assigned_at'])) {
                echo '<br><small>С ' . htmlspecialchars((string)$trophy['holder_assigned_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>';
            }
            echo '</td>';
            echo '<td class="lol" align="center">' . $status . '</td>';
            echo '<td class="lol">';
            echo '<a href="admincp.php?op=class_permissions&trophy_id=' . $tid . '"><b>Редактировать</b></a> | ';
            echo '<a href="admincp.php?op=class_permissions&cp_action=move_trophy&trophy_id=' . $tid . '&direction=up"><b>Вверх</b></a> | ';
            echo '<a href="admincp.php?op=class_permissions&cp_action=move_trophy&trophy_id=' . $tid . '&direction=down"><b>Вниз</b></a> | ';
            echo '<a href="admincp.php?op=class_permissions&cp_action=delete_trophy&trophy_id=' . $tid . '" onclick="return confirm(\'Удалить ранг или кубок? У пользователей он тоже будет снят.\')"><b>Удалить</b></a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td class="lol" colspan="7">Пока нет ни одного ранга или кубка.</td></tr>';
    }
    echo '</table><br>';

    echo '<form method="post" action="admincp.php?op=class_permissions">';
    if ($editingTrophy) {
        echo '<input type="hidden" name="trophy_id" value="' . (int)$editingTrophy['id'] . '">';
    }
    echo '<input type="hidden" name="cp_action" value="save_trophy">';
    echo '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="2">' . ($editingTrophy ? 'Редактирование ранга / кубка' : 'Добавление ранга / кубка') . '</td></tr>';
    echo '<tr><td class="rowhead" width="200">Название</td><td class="lol"><input type="text" name="name" size="60" value="' . htmlspecialchars((string)($editingTrophy['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td></tr>';
    echo '<tr><td class="rowhead">Файл картинки</td><td class="lol"><input type="text" name="rangpic" size="40" value="' . htmlspecialchars((string)($editingTrophy['rangpic'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><br><small>Относительно каталога `/pic`.</small></td></tr>';
    echo '<tr><td class="rowhead">Описание</td><td class="lol"><textarea name="description" cols="70" rows="3">' . htmlspecialchars((string)($editingTrophy['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea></td></tr>';
    echo '<tr><td class="rowhead">Порядок</td><td class="lol"><input type="text" name="sort_order" size="10" value="' . (int)($editingTrophy['sort_order'] ?? (count($trophies) + 1)) . '"></td></tr>';
    echo '<tr><td class="rowhead">Тип</td><td class="lol"><label><input type="radio" name="is_transition" value="no"' . ((($editingTrophy['is_transition'] ?? 'no') === 'no') ? ' checked' : '') . '> Обычный ранг</label> <label><input type="radio" name="is_transition" value="yes"' . ((($editingTrophy['is_transition'] ?? 'no') === 'yes') ? ' checked' : '') . '> Переходящий кубок</label></td></tr>';
    echo '<tr><td class="rowhead">Статус</td><td class="lol"><label><input type="radio" name="is_active" value="yes"' . ((($editingTrophy['is_active'] ?? 'yes') === 'yes') ? ' checked' : '') . '> Активен</label> <label><input type="radio" name="is_active" value="no"' . ((($editingTrophy['is_active'] ?? 'yes') === 'no') ? ' checked' : '') . '> Скрыт</label></td></tr>';
    echo '<tr><td class="rowhead">Автовыдача</td><td class="lol"><label><input type="radio" name="auto_enabled" value="yes"' . ((($editingTrophy['auto_enabled'] ?? 'no') === 'yes') ? ' checked' : '') . '> Включена</label> <label><input type="radio" name="auto_enabled" value="no"' . ((($editingTrophy['auto_enabled'] ?? 'no') === 'no') ? ' checked' : '') . '> Выключена</label><br><small>Для переходящих кубков система сможет сама пересчитывать владельца по выбранной метрике.</small></td></tr>';
    echo '<tr><td class="rowhead">Метрика</td><td class="lol"><select name="auto_metric"><option value="">Без авто-логики</option>';
    foreach ($metricCatalog as $metricKey => $metricMeta) {
        $selected = ((string)($editingTrophy['auto_metric'] ?? '') === (string)$metricKey) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars((string)$metricKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars((string)$metricMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td class="rowhead">Период в днях</td><td class="lol"><input type="text" name="auto_period_days" size="10" value="' . (int)($editingTrophy['auto_period_days'] ?? 0) . '"><br><small>`0` означает считать за всё время.</small></td></tr>';
    echo '<tr><td class="rowhead">Мин. значение</td><td class="lol"><input type="text" name="auto_min_value" size="10" value="' . (int)($editingTrophy['auto_min_value'] ?? 0) . '"><br><small>Если лидер не набирает минимум, кубок остается без владельца.</small></td></tr>';
    echo '<tr><td class="rowhead">Обновлять раз в минут</td><td class="lol"><input type="text" name="auto_refresh_minutes" size="10" value="' . (int)($editingTrophy['auto_refresh_minutes'] ?? 10) . '"><br><small>Пересчет произойдет лениво при открытии страниц.</small></td></tr>';
    echo '<tr><td class="rowhead">ID владельца кубка</td><td class="lol"><input type="text" name="holder_user_id" size="12" value="' . (int)($editingTrophy['holder_user_id'] ?? 0) . '"><br><small>Для обычного ранга оставьте `0`. Для переходящего кубка можно сразу указать нового владельца.</small></td></tr>';
    echo '<tr><td class="rowhead">Комментарий к выдаче</td><td class="lol"><input type="text" name="holder_comment" size="80" value="' . htmlspecialchars((string)($editingTrophy['holder_comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><br><small>Попадает в историю переходящего кубка.</small></td></tr>';
    echo '<tr><td class="lol" colspan="2" align="center"><input type="submit" value="' . ($editingTrophy ? 'Сохранить ранг / кубок' : 'Добавить ранг / кубок') . '"></td></tr>';
    echo '</table>';
    echo '</form>';

    if ($editingTrophy) {
        echo '<br><table width="100%" border="1" cellspacing="0" cellpadding="5">';
        echo '<tr><td class="colhead" colspan="4">История владельцев и изменений</td></tr>';
        echo '<tr><td class="rowhead"><b>Когда</b></td><td class="rowhead"><b>Было</b></td><td class="rowhead"><b>Стало</b></td><td class="rowhead"><b>Комментарий</b></td></tr>';
        if ($trophyHistory) {
            foreach ($trophyHistory as $historyRow) {
                $before = '-';
                if (!empty($historyRow['previous_holder_username'])) {
                    $name = htmlspecialchars((string)$historyRow['previous_holder_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $before = '<a href="userdetails.php?id=' . (int)$historyRow['previous_holder_id'] . '">' . get_user_class_color((int)($historyRow['previous_holder_class'] ?? 0), $name) . '</a>';
                }

                $after = '-';
                if (!empty($historyRow['holder_username'])) {
                    $name = htmlspecialchars((string)$historyRow['holder_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $after = '<a href="userdetails.php?id=' . (int)$historyRow['holder_user_id'] . '">' . get_user_class_color((int)($historyRow['holder_class'] ?? 0), $name) . '</a>';
                }

                $comment = htmlspecialchars((string)($historyRow['comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if (!empty($historyRow['changed_by_username'])) {
                    $changer = htmlspecialchars((string)$historyRow['changed_by_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $comment .= ($comment !== '' ? '<br>' : '') . '<small>Изменил: <a href="userdetails.php?id=' . (int)$historyRow['changed_by'] . '">' . get_user_class_color((int)($historyRow['changed_by_class'] ?? 0), $changer) . '</a></small>';
                }

                echo '<tr>';
                echo '<td class="lol">' . htmlspecialchars((string)$historyRow['changed_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                echo '<td class="lol">' . $before . '</td>';
                echo '<td class="lol">' . $after . '</td>';
                echo '<td class="lol">' . ($comment !== '' ? $comment : '-') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td class="lol" colspan="4">История пока пуста.</td></tr>';
        }
        echo '</table>';
    }

    end_frame();
}

switch ($op) {
    case 'class_permissions':
    case 'ClassPermissions':
        ClassPermissions();
        break;
}
?>
