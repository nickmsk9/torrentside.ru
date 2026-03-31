<?php

if (!function_exists('class_permissions_safe_query')) {
    function class_permissions_safe_query(string $query)
    {
        global $mysqli;

        if (!($mysqli instanceof mysqli)) {
            return null;
        }

        try {
            return $mysqli->query($query);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('class_permissions_table_exists')) {
    function class_permissions_table_exists(string $table): bool
    {
        static $cache = [];

        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $res = class_permissions_safe_query("SHOW TABLES LIKE " . sqlesc($table));
        return $cache[$table] = ($res instanceof mysqli_result && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('class_permissions_column_exists')) {
    function class_permissions_column_exists(string $table, string $column): bool
    {
        static $cache = [];

        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $column = preg_replace('/[^a-z0-9_]/i', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (!class_permissions_table_exists($table)) {
            return $cache[$cacheKey] = false;
        }

        $res = class_permissions_safe_query("SHOW COLUMNS FROM `{$table}` LIKE " . sqlesc($column));
        return $cache[$cacheKey] = ($res instanceof mysqli_result && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('class_permissions_default_class_catalog')) {
    function class_permissions_default_class_catalog(): array
    {
        return [
            UC_USER => [
                'constant_name' => 'UC_USER',
                'lang_key' => 'class_user',
                'fallback_name' => 'Пользователь',
                'display_name' => '',
                'display_color' => '#4682B4',
                'display_style' => 'color: #4682B4;',
                'sort_order' => 1,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_POWER_USER => [
                'constant_name' => 'UC_POWER_USER',
                'lang_key' => 'class_power_user',
                'fallback_name' => 'Продвинутый',
                'display_name' => '',
                'display_color' => '#FFD700',
                'display_style' => 'color: #FFD700; font-weight: bold;',
                'sort_order' => 2,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_VIP => [
                'constant_name' => 'UC_VIP',
                'lang_key' => 'class_vip',
                'fallback_name' => 'VIP',
                'display_name' => '',
                'display_color' => '#8A2BE2',
                'display_style' => 'color: #8A2BE2; font-weight: bold;',
                'sort_order' => 3,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_UPLOADER => [
                'constant_name' => 'UC_UPLOADER',
                'lang_key' => 'class_uploader',
                'fallback_name' => 'Аплоадер',
                'display_name' => '',
                'display_color' => '#FF8C00',
                'display_style' => 'background: linear-gradient(90deg, #FF8C00, #FF4500); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent; font-weight: bold;',
                'sort_order' => 4,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_MODERATOR => [
                'constant_name' => 'UC_MODERATOR',
                'lang_key' => 'class_moderator',
                'fallback_name' => 'Модератор',
                'display_name' => '',
                'display_color' => '#C71585',
                'display_style' => 'color: #C71585; font-weight: bold;',
                'sort_order' => 5,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_ADMINISTRATOR => [
                'constant_name' => 'UC_ADMINISTRATOR',
                'lang_key' => 'class_administrator',
                'fallback_name' => 'Администратор',
                'display_name' => '',
                'display_color' => '#32CD32',
                'display_style' => 'background: linear-gradient(90deg, #32CD32, #006400); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold;',
                'sort_order' => 6,
                'is_override_allowed' => 'yes',
                'notes' => '',
            ],
            UC_SYSOP => [
                'constant_name' => 'UC_SYSOP',
                'lang_key' => 'class_sysop',
                'fallback_name' => 'Системный оператор',
                'display_name' => '',
                'display_color' => '#00FFFF',
                'display_style' => 'background: linear-gradient(90deg, #00FFFF, #007FFF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold;',
                'sort_order' => 7,
                'is_override_allowed' => 'no',
                'notes' => '',
            ],
        ];
    }
}

if (!function_exists('class_permissions_default_class_meta')) {
    function class_permissions_default_class_meta(int $class): ?array
    {
        $defaults = class_permissions_default_class_catalog();
        if (!isset($defaults[$class])) {
            return null;
        }

        return ['base_class' => $class] + $defaults[$class];
    }
}

if (!function_exists('class_permissions_effective_class_name')) {
    function class_permissions_effective_class_name(array $meta): string
    {
        global $tracker_lang;

        $displayName = trim((string)($meta['display_name'] ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $langKey = trim((string)($meta['lang_key'] ?? ''));
        if ($langKey !== '' && isset($tracker_lang[$langKey]) && trim((string)$tracker_lang[$langKey]) !== '') {
            return (string)$tracker_lang[$langKey];
        }

        return trim((string)($meta['fallback_name'] ?? ''));
    }
}

if (!function_exists('class_permissions_class_style')) {
    function class_permissions_class_style(array $meta): string
    {
        $style = trim((string)($meta['display_style'] ?? ''));
        if ($style !== '') {
            return preg_replace('/\s+/', ' ', $style) ?? $style;
        }

        $color = trim((string)($meta['display_color'] ?? ''));
        if ($color !== '') {
            return 'color: ' . $color . ';';
        }

        return '';
    }
}

if (!function_exists('class_permissions_normalize_class_sort_orders')) {
    function class_permissions_normalize_class_sort_orders(): void
    {
        if (!class_permissions_table_exists('tracker_class_catalog')) {
            return;
        }

        $res = sql_query("SELECT base_class FROM tracker_class_catalog ORDER BY sort_order ASC, base_class ASC");
        $position = 1;
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $baseClass = (int)($row['base_class'] ?? 0);
            sql_query("UPDATE tracker_class_catalog SET sort_order = {$position} WHERE base_class = {$baseClass}");
            $position++;
        }
    }
}

if (!function_exists('class_permissions_normalize_trophy_sort_orders')) {
    function class_permissions_normalize_trophy_sort_orders(): void
    {
        if (!class_permissions_table_exists('rangclass') || !class_permissions_column_exists('rangclass', 'sort_order')) {
            return;
        }

        $res = sql_query("SELECT id FROM rangclass ORDER BY sort_order ASC, name ASC, id ASC");
        $position = 1;
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $trophyId = (int)($row['id'] ?? 0);
            sql_query("UPDATE rangclass SET sort_order = {$position} WHERE id = {$trophyId}");
            $position++;
        }
    }
}

if (!function_exists('class_permissions_invalidate_catalog_cache')) {
    function class_permissions_invalidate_catalog_cache(): void
    {
        if (function_exists('tracker_cache_bump_namespace')) {
            tracker_cache_bump_namespace('class_catalog');
        }
    }
}

if (!function_exists('class_permissions_invalidate_trophy_cache')) {
    function class_permissions_invalidate_trophy_cache(): void
    {
        if (function_exists('tracker_cache_bump_namespace')) {
            tracker_cache_bump_namespace('class_trophies');
        }
    }
}

if (!function_exists('class_permissions_invalidate_user_auth_cache')) {
    function class_permissions_invalidate_user_auth_cache(int ...$userIds): void
    {
        if (!function_exists('tracker_invalidate_user_auth_cache')) {
            return;
        }
        tracker_invalidate_user_auth_cache(...$userIds);
    }
}

if (!function_exists('class_permissions_core_class_exists')) {
    function class_permissions_core_class_exists(int $class): bool
    {
        return class_permissions_default_class_meta($class) !== null;
    }
}

if (!function_exists('class_permissions_ensure_schema')) {
    function class_permissions_ensure_schema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        sql_query("
            CREATE TABLE IF NOT EXISTS user_class_profiles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                description TEXT NOT NULL,
                base_class TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        sql_query("
            CREATE TABLE IF NOT EXISTS user_class_profile_permissions (
                profile_id INT UNSIGNED NOT NULL,
                module_key VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (profile_id, module_key),
                KEY idx_module (module_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        sql_query("
            CREATE TABLE IF NOT EXISTS tracker_class_catalog (
                base_class TINYINT UNSIGNED NOT NULL,
                constant_name VARCHAR(64) NOT NULL,
                lang_key VARCHAR(64) NOT NULL DEFAULT '',
                fallback_name VARCHAR(120) NOT NULL DEFAULT '',
                display_name VARCHAR(120) NOT NULL DEFAULT '',
                display_color VARCHAR(32) NOT NULL DEFAULT '',
                display_style VARCHAR(255) NOT NULL DEFAULT '',
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                is_override_allowed ENUM('yes','no') NOT NULL DEFAULT 'yes',
                notes TEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (base_class),
                KEY idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        sql_query("
            CREATE TABLE IF NOT EXISTS rangclass_history (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                rangclass_id INT UNSIGNED NOT NULL,
                previous_holder_id INT UNSIGNED NOT NULL DEFAULT 0,
                holder_user_id INT UNSIGNED NOT NULL DEFAULT 0,
                changed_by INT UNSIGNED NOT NULL DEFAULT 0,
                comment VARCHAR(255) NOT NULL DEFAULT '',
                changed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_rangclass_changed (rangclass_id, changed_at),
                KEY idx_holder_changed (holder_user_id, changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!class_permissions_column_exists('users', 'class_profile_id')) {
            sql_query("ALTER TABLE users ADD COLUMN class_profile_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER class");
            sql_query("ALTER TABLE users ADD KEY idx_class_profile_id (class_profile_id)");
        }

        if (class_permissions_column_exists('rangclass', 'id')) {
            if (!class_permissions_column_exists('rangclass', 'description')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN description TEXT NOT NULL AFTER rangpic");
            }
            if (!class_permissions_column_exists('rangclass', 'sort_order')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER description");
            }
            if (!class_permissions_column_exists('rangclass', 'is_transition')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN is_transition ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER sort_order");
            }
            if (!class_permissions_column_exists('rangclass', 'holder_user_id')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN holder_user_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_transition");
            }
            if (!class_permissions_column_exists('rangclass', 'holder_assigned_at')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN holder_assigned_at DATETIME DEFAULT NULL AFTER holder_user_id");
            }
            if (!class_permissions_column_exists('rangclass', 'holder_comment')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN holder_comment VARCHAR(255) NOT NULL DEFAULT '' AFTER holder_assigned_at");
            }
            if (!class_permissions_column_exists('rangclass', 'is_active')) {
                sql_query("ALTER TABLE rangclass ADD COLUMN is_active ENUM('yes','no') NOT NULL DEFAULT 'yes' AFTER holder_comment");
            }
            sql_query("UPDATE rangclass SET sort_order = id WHERE sort_order = 0");
        }

        $now = get_date_time();
        foreach (class_permissions_default_class_catalog() as $baseClass => $meta) {
            sql_query("
                INSERT IGNORE INTO tracker_class_catalog
                    (base_class, constant_name, lang_key, fallback_name, display_name, display_color, display_style, sort_order, is_override_allowed, notes, updated_at)
                VALUES (
                    {$baseClass},
                    " . sqlesc($meta['constant_name']) . ",
                    " . sqlesc($meta['lang_key']) . ",
                    " . sqlesc($meta['fallback_name']) . ",
                    " . sqlesc($meta['display_name']) . ",
                    " . sqlesc($meta['display_color']) . ",
                    " . sqlesc($meta['display_style']) . ",
                    " . (int)$meta['sort_order'] . ",
                    " . sqlesc($meta['is_override_allowed']) . ",
                    " . sqlesc($meta['notes']) . ",
                    " . sqlesc($now) . "
                )
            ");

            sql_query("
                UPDATE tracker_class_catalog
                SET
                    constant_name = IF(constant_name = '', " . sqlesc($meta['constant_name']) . ", constant_name),
                    lang_key = IF(lang_key = '', " . sqlesc($meta['lang_key']) . ", lang_key),
                    fallback_name = IF(fallback_name = '', " . sqlesc($meta['fallback_name']) . ", fallback_name),
                    sort_order = IF(sort_order = 0, " . (int)$meta['sort_order'] . ", sort_order)
                WHERE base_class = {$baseClass}
            ");
        }

        class_permissions_normalize_class_sort_orders();
        class_permissions_normalize_trophy_sort_orders();

        $ready = true;
    }
}

if (!function_exists('class_permissions_catalog')) {
    function class_permissions_catalog(): array
    {
        return [
            'torrent_add' => ['group' => 'Торренты', 'label' => 'Добавлять торренты'],
            'torrent_edit' => ['group' => 'Торренты', 'label' => 'Редактировать торренты'],
            'torrent_delete' => ['group' => 'Торренты', 'label' => 'Удалять торренты'],
            'comment_add' => ['group' => 'Торренты', 'label' => 'Добавлять комменты'],
            'users_edit' => ['group' => 'Пользователи', 'label' => 'Редактировать пользователей'],
            'users_warn' => ['group' => 'Пользователи', 'label' => 'Предупреждать пользователей'],
            'users_delete' => ['group' => 'Пользователи', 'label' => 'Удалять пользователей'],
            'users_add' => ['group' => 'Пользователи', 'label' => 'Добавлять пользователей'],
            'message_write' => ['group' => 'Сообщения', 'label' => 'Писать ЛС'],
            'message_mass' => ['group' => 'Сообщения', 'label' => 'Писать массовые рассылки'],
            'admin_access' => ['group' => 'Сообщения', 'label' => 'Доступ в админку'],
            'custom_module' => ['group' => 'Дополнительно', 'label' => 'Свободный модуль'],
        ];
    }
}

if (!function_exists('class_permissions_grouped_catalog')) {
    function class_permissions_grouped_catalog(): array
    {
        $grouped = [];
        foreach (class_permissions_catalog() as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta['label'];
        }
        return $grouped;
    }
}

if (!function_exists('class_permissions_fallback_map')) {
    function class_permissions_fallback_map(): array
    {
        return [
            'torrent_add' => UC_USER,
            'torrent_edit' => UC_MODERATOR,
            'torrent_delete' => UC_MODERATOR,
            'comment_add' => UC_USER,
            'users_edit' => UC_MODERATOR,
            'users_warn' => UC_MODERATOR,
            'users_delete' => UC_ADMINISTRATOR,
            'users_add' => UC_ADMINISTRATOR,
            'message_write' => UC_USER,
            'message_mass' => UC_MODERATOR,
            'admin_access' => UC_SYSOP,
            'custom_module' => UC_SYSOP,
        ];
    }
}

if (!function_exists('class_permissions_fallback_allows')) {
    function class_permissions_fallback_allows(string $moduleKey, int $class): bool
    {
        $map = class_permissions_fallback_map();
        $need = $map[$moduleKey] ?? UC_SYSOP;
        return $class >= $need;
    }
}

if (!function_exists('class_permissions_get_class_catalog')) {
    function class_permissions_get_class_catalog(): array
    {
        if (!class_permissions_table_exists('tracker_class_catalog')) {
            $rows = [];
            foreach (class_permissions_default_class_catalog() as $baseClass => $meta) {
                $rows[] = ['base_class' => $baseClass] + $meta;
            }
            return $rows;
        }

        $resolver = static function (): array {
            $res = sql_query("
                SELECT
                    base_class,
                    constant_name,
                    lang_key,
                    fallback_name,
                    display_name,
                    display_color,
                    display_style,
                    sort_order,
                    is_override_allowed,
                    notes
                FROM tracker_class_catalog
                ORDER BY sort_order ASC, base_class ASC
            ");

            $rows = [];
            while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
                $row['base_class'] = (int)($row['base_class'] ?? 0);
                $row['sort_order'] = (int)($row['sort_order'] ?? 0);
                $rows[] = $row;
            }
            return $rows;
        };

        if (function_exists('tracker_cache_ns_key') && function_exists('tracker_cache_remember')) {
            $cacheKey = tracker_cache_ns_key('class_catalog', 'catalog');
            $rows = tracker_cache_remember($cacheKey, 300, $resolver);
        } else {
            $rows = $resolver();
        }

        if (!is_array($rows) || !$rows) {
            $rows = [];
            foreach (class_permissions_default_class_catalog() as $baseClass => $meta) {
                $rows[] = ['base_class' => $baseClass] + $meta;
            }
        }

        return $rows;
    }
}

if (!function_exists('class_permissions_get_class_meta')) {
    function class_permissions_get_class_meta(int $class): ?array
    {
        foreach (class_permissions_get_class_catalog() as $meta) {
            if ((int)($meta['base_class'] ?? -1) === $class) {
                return $meta;
            }
        }

        return class_permissions_default_class_meta($class);
    }
}

if (!function_exists('class_permissions_get_selectable_classes')) {
    function class_permissions_get_selectable_classes(int $maxClass = PHP_INT_MAX, bool $overrideOnly = false): array
    {
        $rows = [];
        foreach (class_permissions_get_class_catalog() as $meta) {
            $baseClass = (int)($meta['base_class'] ?? 0);
            if ($baseClass > $maxClass) {
                continue;
            }
            if ($overrideOnly && (($meta['is_override_allowed'] ?? 'yes') !== 'yes')) {
                continue;
            }
            $rows[] = $meta;
        }

        return $rows;
    }
}

if (!function_exists('class_permissions_render_class_preview')) {
    function class_permissions_render_class_preview(int $class, string $text = 'Пример'): string
    {
        $safeText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return get_user_class_color($class, $safeText);
    }
}

if (!function_exists('class_permissions_save_class_meta')) {
    function class_permissions_save_class_meta(int $class, array $payload): void
    {
        class_permissions_ensure_schema();

        if (!class_permissions_core_class_exists($class)) {
            stderr('Ошибка', 'Этот системный класс не существует.');
            exit;
        }

        $defaults = class_permissions_default_class_meta($class) ?? [];

        $constantName = strtoupper(trim((string)($payload['constant_name'] ?? ($defaults['constant_name'] ?? ''))));
        $constantName = preg_replace('/[^A-Z0-9_]/', '', $constantName) ?? '';
        if ($constantName === '') {
            $constantName = (string)($defaults['constant_name'] ?? ('UC_CLASS_' . $class));
        }

        $langKey = trim((string)($payload['lang_key'] ?? ($defaults['lang_key'] ?? '')));
        $langKey = preg_replace('/[^a-z0-9_]/i', '', $langKey) ?? '';

        $fallbackName = trim((string)($payload['fallback_name'] ?? ($defaults['fallback_name'] ?? '')));
        if ($fallbackName === '') {
            $fallbackName = (string)($defaults['fallback_name'] ?? '');
        }

        $displayName = trim((string)($payload['display_name'] ?? ''));
        $displayColor = trim((string)($payload['display_color'] ?? ''));
        if ($displayColor !== '' && !preg_match('/^[#a-z0-9(),.%\s-]{1,32}$/i', $displayColor)) {
            stderr('Ошибка', 'Некорректный цвет класса.');
            exit;
        }

        $displayStyle = trim((string)($payload['display_style'] ?? ''));
        $displayStyle = preg_replace('/[\r\n\t]+/', ' ', $displayStyle) ?? $displayStyle;
        if (strlen($displayStyle) > 255) {
            $displayStyle = substr($displayStyle, 0, 255);
        }

        $sortOrder = max(1, min(999, (int)($payload['sort_order'] ?? ($defaults['sort_order'] ?? ($class + 1)))));
        $isOverrideAllowed = (($payload['is_override_allowed'] ?? ($defaults['is_override_allowed'] ?? 'yes')) === 'no') ? 'no' : 'yes';
        $notes = trim((string)($payload['notes'] ?? ''));

        sql_query("
            UPDATE tracker_class_catalog
            SET
                constant_name = " . sqlesc($constantName) . ",
                lang_key = " . sqlesc($langKey) . ",
                fallback_name = " . sqlesc($fallbackName) . ",
                display_name = " . sqlesc($displayName) . ",
                display_color = " . sqlesc($displayColor) . ",
                display_style = " . sqlesc($displayStyle) . ",
                sort_order = {$sortOrder},
                is_override_allowed = " . sqlesc($isOverrideAllowed) . ",
                notes = " . sqlesc($notes) . ",
                updated_at = " . sqlesc(get_date_time()) . "
            WHERE base_class = {$class}
        ");

        class_permissions_normalize_class_sort_orders();
        class_permissions_invalidate_catalog_cache();
    }
}

if (!function_exists('class_permissions_move_class')) {
    function class_permissions_move_class(int $class, string $direction, bool $swapAssignments = false): bool
    {
        class_permissions_ensure_schema();

        $direction = $direction === 'down' ? 'down' : 'up';
        $catalog = class_permissions_get_class_catalog();
        $index = null;

        foreach ($catalog as $idx => $meta) {
            if ((int)($meta['base_class'] ?? -1) === $class) {
                $index = $idx;
                break;
            }
        }

        if ($index === null) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($catalog[$targetIndex])) {
            return false;
        }

        $current = $catalog[$index];
        $target = $catalog[$targetIndex];
        $currentClass = (int)$current['base_class'];
        $targetClass = (int)$target['base_class'];
        $currentSort = (int)$current['sort_order'];
        $targetSort = (int)$target['sort_order'];

        sql_query("
            UPDATE tracker_class_catalog
            SET sort_order = CASE
                WHEN base_class = {$currentClass} THEN {$targetSort}
                WHEN base_class = {$targetClass} THEN {$currentSort}
                ELSE sort_order
            END
            WHERE base_class IN ({$currentClass}, {$targetClass})
        ");

        if ($swapAssignments) {
            sql_query("
                UPDATE users
                SET class = CASE
                    WHEN class = {$currentClass} THEN {$targetClass}
                    WHEN class = {$targetClass} THEN {$currentClass}
                    ELSE class
                END
                WHERE class IN ({$currentClass}, {$targetClass})
            ");

            sql_query("
                UPDATE users
                SET override_class = CASE
                    WHEN override_class = {$currentClass} THEN {$targetClass}
                    WHEN override_class = {$targetClass} THEN {$currentClass}
                    ELSE override_class
                END
                WHERE override_class IN ({$currentClass}, {$targetClass})
            ");

            sql_query("
                UPDATE user_class_profiles
                SET base_class = CASE
                    WHEN base_class = {$currentClass} THEN {$targetClass}
                    WHEN base_class = {$targetClass} THEN {$currentClass}
                    ELSE base_class
                END
                WHERE base_class IN ({$currentClass}, {$targetClass})
            ");

            $affected = [];
            $res = sql_query("SELECT id FROM users WHERE class IN ({$currentClass}, {$targetClass}) OR override_class IN ({$currentClass}, {$targetClass})");
            while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
                $affected[] = (int)($row['id'] ?? 0);
            }
            if ($affected) {
                class_permissions_invalidate_user_auth_cache(...$affected);
            }
        }

        class_permissions_normalize_class_sort_orders();
        class_permissions_invalidate_catalog_cache();

        return true;
    }
}

if (!function_exists('class_permissions_get_profiles')) {
    function class_permissions_get_profiles(): array
    {
        if (!class_permissions_table_exists('user_class_profiles') || !class_permissions_table_exists('user_class_profile_permissions')) {
            return [];
        }

        $res = sql_query("
            SELECT
                p.id,
                p.name,
                p.description,
                p.base_class,
                COUNT(pp.module_key) AS modules_count
            FROM user_class_profiles p
            LEFT JOIN user_class_profile_permissions pp ON pp.profile_id = p.id
            GROUP BY p.id
            ORDER BY p.base_class ASC, p.name ASC
        ");

        $rows = [];
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('class_permissions_get_profile')) {
    function class_permissions_get_profile(int $profileId): ?array
    {
        if (!class_permissions_table_exists('user_class_profiles')) {
            return null;
        }

        $profileId = (int)$profileId;
        if ($profileId <= 0) {
            return null;
        }

        $res = sql_query("SELECT id, name, description, base_class FROM user_class_profiles WHERE id = {$profileId} LIMIT 1");
        $row = $res instanceof mysqli_result ? mysqli_fetch_assoc($res) : null;
        return $row ?: null;
    }
}

if (!function_exists('class_permissions_get_profile_permissions')) {
    function class_permissions_get_profile_permissions(int $profileId): array
    {
        if (!class_permissions_table_exists('user_class_profile_permissions')) {
            return [];
        }

        $profileId = (int)$profileId;
        if ($profileId <= 0) {
            return [];
        }

        $res = sql_query("SELECT module_key FROM user_class_profile_permissions WHERE profile_id = {$profileId}");
        $out = [];
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $key = (string)($row['module_key'] ?? '');
            if ($key !== '') {
                $out[] = $key;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('class_permissions_save_profile')) {
    function class_permissions_save_profile(int $profileId, string $name, string $description, int $baseClass, array $modules): int
    {
        global $mysqli;

        class_permissions_ensure_schema();

        $name = trim($name);
        if ($name === '') {
            stderr('Ошибка', 'Название профиля обязательно.');
            exit;
        }

        if (!class_permissions_core_class_exists($baseClass)) {
            stderr('Ошибка', 'Выбран несуществующий базовый класс.');
            exit;
        }

        $catalogKeys = array_keys(class_permissions_catalog());
        $modules = array_values(array_intersect($catalogKeys, array_map('strval', $modules)));
        $now = get_date_time();

        if ($profileId > 0) {
            $stmt = $mysqli->prepare("
                UPDATE user_class_profiles
                SET name = ?, description = ?, base_class = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssisi', $name, $description, $baseClass, $now, $profileId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO user_class_profiles (name, description, base_class, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssiss', $name, $description, $baseClass, $now, $now);
            $stmt->execute();
            $profileId = (int)$stmt->insert_id;
            $stmt->close();
        }

        sql_query("DELETE FROM user_class_profile_permissions WHERE profile_id = {$profileId}");
        if ($modules) {
            $stmt = $mysqli->prepare("
                INSERT INTO user_class_profile_permissions (profile_id, module_key, created_at)
                VALUES (?, ?, ?)
            ");
            foreach ($modules as $moduleKey) {
                $stmt->bind_param('iss', $profileId, $moduleKey, $now);
                $stmt->execute();
            }
            $stmt->close();
        }

        return $profileId;
    }
}

if (!function_exists('class_permissions_delete_profile')) {
    function class_permissions_delete_profile(int $profileId): void
    {
        class_permissions_ensure_schema();
        $profileId = (int)$profileId;
        if ($profileId <= 0) {
            return;
        }

        sql_query("UPDATE users SET class_profile_id = 0 WHERE class_profile_id = {$profileId}");
        sql_query("DELETE FROM user_class_profile_permissions WHERE profile_id = {$profileId}");
        sql_query("DELETE FROM user_class_profiles WHERE id = {$profileId}");
    }
}

if (!function_exists('class_permissions_get_user_profile_id')) {
    function class_permissions_get_user_profile_id(?array $user = null): int
    {
        global $CURUSER;

        $user = $user ?? (is_array($CURUSER) ? $CURUSER : null);
        if (!is_array($user)) {
            return 0;
        }

        if (isset($user['class_profile_id'])) {
            return (int)$user['class_profile_id'];
        }

        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0) {
            return 0;
        }

        if (!class_permissions_column_exists('users', 'class_profile_id')) {
            return 0;
        }

        static $cache = [];
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }

        $res = sql_query("SELECT class_profile_id FROM users WHERE id = {$uid} LIMIT 1");
        $row = $res instanceof mysqli_result ? mysqli_fetch_assoc($res) : null;
        $cache[$uid] = (int)($row['class_profile_id'] ?? 0);
        return $cache[$uid];
    }
}

if (!function_exists('class_permissions_get_user_modules')) {
    function class_permissions_get_user_modules(?array $user = null): array
    {
        $profileId = class_permissions_get_user_profile_id($user);
        if ($profileId <= 0) {
            return [];
        }

        static $cache = [];
        if (!isset($cache[$profileId])) {
            $cache[$profileId] = class_permissions_get_profile_permissions($profileId);
        }
        return $cache[$profileId];
    }
}

if (!function_exists('user_has_module')) {
    function user_has_module(string $moduleKey, ?array $user = null): bool
    {
        global $CURUSER;

        $user = $user ?? (is_array($CURUSER) ? $CURUSER : null);
        $class = (int)($user['class'] ?? 0);
        if ($class >= UC_SYSOP) {
            return true;
        }

        $modules = class_permissions_get_user_modules($user);
        if ($modules) {
            return in_array($moduleKey, $modules, true);
        }

        return class_permissions_fallback_allows($moduleKey, $class);
    }
}

if (!function_exists('current_user_profile_name')) {
    function current_user_profile_name(?array $user = null): string
    {
        $profileId = class_permissions_get_user_profile_id($user);
        if ($profileId <= 0) {
            return '';
        }

        $profile = class_permissions_get_profile($profileId);
        return (string)($profile['name'] ?? '');
    }
}

if (!function_exists('class_permissions_get_trophies')) {
    function class_permissions_get_trophies(bool $includeInactive = true): array
    {
        if (!class_permissions_table_exists('rangclass')) {
            return [];
        }

        $resolver = static function () use ($includeInactive): array {
            $hasDescription = class_permissions_column_exists('rangclass', 'description');
            $hasSortOrder = class_permissions_column_exists('rangclass', 'sort_order');
            $hasTransition = class_permissions_column_exists('rangclass', 'is_transition');
            $hasHolder = class_permissions_column_exists('rangclass', 'holder_user_id');
            $hasHolderAssignedAt = class_permissions_column_exists('rangclass', 'holder_assigned_at');
            $hasHolderComment = class_permissions_column_exists('rangclass', 'holder_comment');
            $hasActive = class_permissions_column_exists('rangclass', 'is_active');

            $where = ($includeInactive || !$hasActive) ? '' : "WHERE r.is_active = 'yes'";
            $join = $hasHolder ? "LEFT JOIN users u ON u.id = r.holder_user_id" : '';
            $orderBy = $hasSortOrder ? 'r.sort_order ASC, r.name ASC, r.id ASC' : 'r.name ASC, r.id ASC';

            $res = sql_query("
                SELECT
                    r.id,
                    r.name,
                    r.rangpic,
                    " . ($hasDescription ? 'r.description' : "'' AS description") . ",
                    " . ($hasSortOrder ? 'r.sort_order' : 'r.id AS sort_order') . ",
                    " . ($hasTransition ? 'r.is_transition' : "'no' AS is_transition") . ",
                    " . ($hasHolder ? 'r.holder_user_id' : '0 AS holder_user_id') . ",
                    " . ($hasHolderAssignedAt ? 'r.holder_assigned_at' : 'NULL AS holder_assigned_at') . ",
                    " . ($hasHolderComment ? 'r.holder_comment' : "'' AS holder_comment") . ",
                    " . ($hasActive ? 'r.is_active' : "'yes' AS is_active") . ",
                    " . ($hasHolder ? 'u.username AS holder_username' : "'' AS holder_username") . ",
                    " . ($hasHolder ? 'u.class AS holder_class' : '0 AS holder_class') . "
                FROM rangclass r
                {$join}
                {$where}
                ORDER BY {$orderBy}
            ");

            $rows = [];
            while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
                $row['id'] = (int)($row['id'] ?? 0);
                $row['sort_order'] = (int)($row['sort_order'] ?? 0);
                $row['holder_user_id'] = (int)($row['holder_user_id'] ?? 0);
                $row['holder_class'] = (int)($row['holder_class'] ?? 0);
                $rows[] = $row;
            }
            return $rows;
        };

        if (function_exists('tracker_cache_ns_key') && function_exists('tracker_cache_remember')) {
            $cacheKey = tracker_cache_ns_key('class_trophies', $includeInactive ? 'all' : 'active');
            $rows = tracker_cache_remember($cacheKey, 180, $resolver);
        } else {
            $rows = $resolver();
        }

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('class_permissions_get_trophy')) {
    function class_permissions_get_trophy(int $trophyId): ?array
    {
        $trophyId = (int)$trophyId;
        if ($trophyId <= 0) {
            return null;
        }

        foreach (class_permissions_get_trophies(true) as $trophy) {
            if ((int)($trophy['id'] ?? 0) === $trophyId) {
                return $trophy;
            }
        }

        return null;
    }
}

if (!function_exists('class_permissions_get_trophy_history')) {
    function class_permissions_get_trophy_history(int $trophyId, int $limit = 15): array
    {
        if (!class_permissions_table_exists('rangclass_history')) {
            return [];
        }

        $trophyId = (int)$trophyId;
        if ($trophyId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $res = sql_query("
            SELECT
                h.id,
                h.rangclass_id,
                h.previous_holder_id,
                h.holder_user_id,
                h.changed_by,
                h.comment,
                h.changed_at,
                prev.username AS previous_holder_username,
                prev.class AS previous_holder_class,
                curr.username AS holder_username,
                curr.class AS holder_class,
                changer.username AS changed_by_username,
                changer.class AS changed_by_class
            FROM rangclass_history h
            LEFT JOIN users prev ON prev.id = h.previous_holder_id
            LEFT JOIN users curr ON curr.id = h.holder_user_id
            LEFT JOIN users changer ON changer.id = h.changed_by
            WHERE h.rangclass_id = {$trophyId}
            ORDER BY h.changed_at DESC, h.id DESC
            LIMIT {$limit}
        ");

        $rows = [];
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('class_permissions_save_trophy')) {
    function class_permissions_save_trophy(int $trophyId, array $payload): int
    {
        global $mysqli;

        class_permissions_ensure_schema();

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            stderr('Ошибка', 'Название ранга или кубка обязательно.');
            exit;
        }

        $rangpic = trim((string)($payload['rangpic'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $sortOrder = max(1, min(9999, (int)($payload['sort_order'] ?? 1)));
        $isTransition = (($payload['is_transition'] ?? 'no') === 'yes') ? 'yes' : 'no';
        $isActive = (($payload['is_active'] ?? 'yes') === 'no') ? 'no' : 'yes';

        if ($trophyId > 0) {
            $stmt = $mysqli->prepare("
                UPDATE rangclass
                SET
                    name = ?,
                    rangpic = ?,
                    description = ?,
                    sort_order = ?,
                    is_transition = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sssissi', $name, $rangpic, $description, $sortOrder, $isTransition, $isActive, $trophyId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO rangclass (name, rangpic, description, sort_order, is_transition, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssiss', $name, $rangpic, $description, $sortOrder, $isTransition, $isActive);
            $stmt->execute();
            $trophyId = (int)$stmt->insert_id;
            $stmt->close();
        }

        if ($isTransition === 'no') {
            sql_query("
                UPDATE rangclass
                SET holder_user_id = 0, holder_assigned_at = NULL, holder_comment = ''
                WHERE id = {$trophyId}
            ");
        }

        class_permissions_normalize_trophy_sort_orders();
        class_permissions_invalidate_trophy_cache();

        return $trophyId;
    }
}

if (!function_exists('class_permissions_record_trophy_history')) {
    function class_permissions_record_trophy_history(int $trophyId, int $previousHolderId, int $holderUserId, int $changedBy, string $comment = ''): void
    {
        class_permissions_ensure_schema();

        $comment = trim($comment);
        if (strlen($comment) > 255) {
            $comment = substr($comment, 0, 255);
        }

        sql_query("
            INSERT INTO rangclass_history
                (rangclass_id, previous_holder_id, holder_user_id, changed_by, comment, changed_at)
            VALUES (
                {$trophyId},
                " . max(0, $previousHolderId) . ",
                " . max(0, $holderUserId) . ",
                " . max(0, $changedBy) . ",
                " . sqlesc($comment) . ",
                " . sqlesc(get_date_time()) . "
            )
        ");
    }
}

if (!function_exists('class_permissions_release_transition_trophy')) {
    function class_permissions_release_transition_trophy(int $trophyId, int $holderUserId, int $changedBy, string $comment = ''): void
    {
        if ($trophyId <= 0 || $holderUserId <= 0) {
            return;
        }

        sql_query("UPDATE users SET rangclass = 0 WHERE id = {$holderUserId} AND rangclass = {$trophyId}");
        sql_query("
            UPDATE rangclass
            SET holder_user_id = 0, holder_assigned_at = NULL, holder_comment = " . sqlesc($comment) . "
            WHERE id = {$trophyId}
        ");

        class_permissions_record_trophy_history($trophyId, $holderUserId, 0, $changedBy, $comment);
        class_permissions_invalidate_user_auth_cache($holderUserId);
        class_permissions_invalidate_trophy_cache();
    }
}

if (!function_exists('class_permissions_assign_transition_trophy_holder')) {
    function class_permissions_assign_transition_trophy_holder(int $trophyId, int $userId, int $changedBy, string $comment = ''): void
    {
        class_permissions_ensure_schema();

        $trophy = class_permissions_get_trophy($trophyId);
        if (!$trophy || ($trophy['is_transition'] ?? 'no') !== 'yes') {
            stderr('Ошибка', 'Выбранный кубок не поддерживает переходящего владельца.');
            exit;
        }

        $userId = (int)$userId;
        if ($userId <= 0) {
            $currentHolderId = (int)($trophy['holder_user_id'] ?? 0);
            if ($currentHolderId > 0) {
                class_permissions_release_transition_trophy($trophyId, $currentHolderId, $changedBy, $comment);
            }
            return;
        }

        $userRes = sql_query("SELECT id FROM users WHERE id = {$userId} LIMIT 1");
        if (!($userRes instanceof mysqli_result) || mysqli_num_rows($userRes) === 0) {
            stderr('Ошибка', 'Пользователь для выдачи переходящего кубка не найден.');
            exit;
        }

        $previousHolderId = (int)($trophy['holder_user_id'] ?? 0);
        if ($previousHolderId > 0 && $previousHolderId !== $userId) {
            class_permissions_release_transition_trophy($trophyId, $previousHolderId, $changedBy, $comment);
        }

        $otherTrophies = sql_query("
            SELECT id
            FROM rangclass
            WHERE is_transition = 'yes'
              AND holder_user_id = {$userId}
              AND id <> {$trophyId}
        ");
        while ($otherTrophies instanceof mysqli_result && ($row = mysqli_fetch_assoc($otherTrophies))) {
            $otherId = (int)($row['id'] ?? 0);
            if ($otherId > 0) {
                class_permissions_release_transition_trophy($otherId, $userId, $changedBy, 'Снято из-за выдачи другого переходящего кубка.');
            }
        }

        sql_query("UPDATE users SET rangclass = {$trophyId} WHERE id = {$userId}");
        sql_query("
            UPDATE rangclass
            SET
                holder_user_id = {$userId},
                holder_assigned_at = " . sqlesc(get_date_time()) . ",
                holder_comment = " . sqlesc($comment) . "
            WHERE id = {$trophyId}
        ");

        class_permissions_record_trophy_history($trophyId, $previousHolderId, $userId, $changedBy, $comment);
        class_permissions_invalidate_user_auth_cache($userId, $previousHolderId);
        class_permissions_invalidate_trophy_cache();
    }
}

if (!function_exists('class_permissions_set_user_rank')) {
    function class_permissions_set_user_rank(int $userId, int $trophyId, int $changedBy, string $comment = ''): void
    {
        class_permissions_ensure_schema();

        $userId = (int)$userId;
        $trophyId = (int)$trophyId;
        if ($userId <= 0) {
            return;
        }

        $res = sql_query("SELECT rangclass FROM users WHERE id = {$userId} LIMIT 1");
        $currentUser = $res instanceof mysqli_result ? mysqli_fetch_assoc($res) : null;
        $currentTrophyId = (int)($currentUser['rangclass'] ?? 0);
        $currentTrophy = $currentTrophyId > 0 ? class_permissions_get_trophy($currentTrophyId) : null;

        if ($currentTrophy && ($currentTrophy['is_transition'] ?? 'no') === 'yes' && (int)($currentTrophy['holder_user_id'] ?? 0) === $userId && $currentTrophyId !== $trophyId) {
            class_permissions_release_transition_trophy($currentTrophyId, $userId, $changedBy, $comment);
        }

        if ($trophyId <= 0) {
            sql_query("UPDATE users SET rangclass = 0 WHERE id = {$userId}");
            class_permissions_invalidate_user_auth_cache($userId);
            class_permissions_invalidate_trophy_cache();
            return;
        }

        $trophy = class_permissions_get_trophy($trophyId);
        if (!$trophy) {
            stderr('Ошибка', 'Выбранный ранг или кубок не найден.');
            exit;
        }

        if (($trophy['is_transition'] ?? 'no') === 'yes') {
            class_permissions_assign_transition_trophy_holder($trophyId, $userId, $changedBy, $comment);
            return;
        }

        sql_query("UPDATE users SET rangclass = {$trophyId} WHERE id = {$userId}");
        class_permissions_invalidate_user_auth_cache($userId);
        class_permissions_invalidate_trophy_cache();
    }
}

if (!function_exists('class_permissions_delete_trophy')) {
    function class_permissions_delete_trophy(int $trophyId, int $changedBy = 0): void
    {
        class_permissions_ensure_schema();

        $trophyId = (int)$trophyId;
        if ($trophyId <= 0) {
            return;
        }

        $trophy = class_permissions_get_trophy($trophyId);
        if ($trophy) {
            $holderUserId = (int)($trophy['holder_user_id'] ?? 0);
            if ($holderUserId > 0) {
                class_permissions_release_transition_trophy($trophyId, $holderUserId, $changedBy, 'Кубок удален администратором.');
            }
        }

        $affected = [];
        $res = sql_query("SELECT id FROM users WHERE rangclass = {$trophyId}");
        while ($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))) {
            $affected[] = (int)($row['id'] ?? 0);
        }

        sql_query("UPDATE users SET rangclass = 0 WHERE rangclass = {$trophyId}");
        sql_query("DELETE FROM rangclass_history WHERE rangclass_id = {$trophyId}");
        sql_query("DELETE FROM rangclass WHERE id = {$trophyId}");

        if ($affected) {
            class_permissions_invalidate_user_auth_cache(...$affected);
        }
        class_permissions_invalidate_trophy_cache();
    }
}

if (!function_exists('class_permissions_move_trophy')) {
    function class_permissions_move_trophy(int $trophyId, string $direction): bool
    {
        class_permissions_ensure_schema();

        $direction = $direction === 'down' ? 'down' : 'up';
        $trophies = class_permissions_get_trophies(true);
        $index = null;

        foreach ($trophies as $idx => $trophy) {
            if ((int)($trophy['id'] ?? 0) === $trophyId) {
                $index = $idx;
                break;
            }
        }

        if ($index === null) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($trophies[$targetIndex])) {
            return false;
        }

        $current = $trophies[$index];
        $target = $trophies[$targetIndex];
        $currentId = (int)$current['id'];
        $targetId = (int)$target['id'];
        $currentSort = (int)$current['sort_order'];
        $targetSort = (int)$target['sort_order'];

        sql_query("
            UPDATE rangclass
            SET sort_order = CASE
                WHEN id = {$currentId} THEN {$targetSort}
                WHEN id = {$targetId} THEN {$currentSort}
                ELSE sort_order
            END
            WHERE id IN ({$currentId}, {$targetId})
        ");

        class_permissions_normalize_trophy_sort_orders();
        class_permissions_invalidate_trophy_cache();

        return true;
    }
}
