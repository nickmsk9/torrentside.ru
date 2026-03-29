<?php

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

        $hasColumn = sql_query("SHOW COLUMNS FROM users LIKE 'class_profile_id'");
        if (!$hasColumn || mysqli_num_rows($hasColumn) === 0) {
            sql_query("ALTER TABLE users ADD COLUMN class_profile_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER class");
            sql_query("ALTER TABLE users ADD KEY idx_class_profile_id (class_profile_id)");
        }

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

if (!function_exists('class_permissions_get_profiles')) {
    function class_permissions_get_profiles(): array
    {
        class_permissions_ensure_schema();

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
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('class_permissions_get_profile')) {
    function class_permissions_get_profile(int $profileId): ?array
    {
        class_permissions_ensure_schema();
        $profileId = (int)$profileId;
        if ($profileId <= 0) {
            return null;
        }

        $res = sql_query("SELECT id, name, description, base_class FROM user_class_profiles WHERE id = {$profileId} LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row ?: null;
    }
}

if (!function_exists('class_permissions_get_profile_permissions')) {
    function class_permissions_get_profile_permissions(int $profileId): array
    {
        class_permissions_ensure_schema();
        $profileId = (int)$profileId;
        if ($profileId <= 0) {
            return [];
        }

        $res = sql_query("SELECT module_key FROM user_class_profile_permissions WHERE profile_id = {$profileId}");
        $out = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
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
            stderr('Ошибка', 'Название класса обязательно.');
            exit;
        }

        $baseClass = max(UC_USER, min(UC_SYSOP, $baseClass));
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

        class_permissions_ensure_schema();
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

        static $cache = [];
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }

        $res = sql_query("SELECT class_profile_id FROM users WHERE id = {$uid} LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
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
