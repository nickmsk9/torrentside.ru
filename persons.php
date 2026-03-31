<?php
declare(strict_types=1);

require_once 'include/bittorrent.php';
require_once 'languages/lang_russian/lang_pages.php';

dbconn();
loggedinorreturn();

/** @var mysqli $mysqli */
$mysqli = $GLOBALS['mysqli'];

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function capture_textbbcode(string $form, string $name, string $text = ''): string {
    ob_start();
    textbbcode($form, $name, $text);
    return ob_get_clean();
}

function persons_mem_instance(): ?Memcached {
    static $mem = null, $ready = false;
    if ($ready) {
        return $mem;
    }

    $ready = true;
    $mem = tracker_cache_instance();

    return $mem instanceof Memcached ? $mem : null;
}

function persons_mem_get(string $key) {
    $value = tracker_cache_get($key, $cacheHit);
    return $cacheHit ? $value : false;
}

function persons_mem_set(string $key, $value, int $ttl = 300): bool {
    return tracker_cache_set($key, $value, $ttl);
}

function persons_mem_del(string $key): void {
    tracker_cache_delete($key);
}

function persons_invalidate_cache(): void {
    foreach ([
        'countries:options:ru:v1',
        'pages_names_map_v2',
        'persons:names_map_v3',
        'persons:stats:v1',
        'persons:prof_counts:v1',
    ] as $key) {
        persons_mem_del($key);
    }
}

function persons_table_exists(mysqli $mysqli, string $table): bool {
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$table}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function persons_table_columns(mysqli $mysqli, string $table): array {
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $cols = [];
    $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $cols[(string)$row['Field']] = true;
        }
        $res->free();
    }

    return $cache[$key] = $cols;
}

function persons_has_column(mysqli $mysqli, string $table, string $column): bool {
    $cols = persons_table_columns($mysqli, $table);
    return isset($cols[$column]);
}

function persons_exec_ddl(mysqli $mysqli, string $sql): void {
    if (!$mysqli->query($sql)) {
        throw new RuntimeException('DDL failed: ' . $mysqli->error);
    }
}

function persons_slugify(string $value): string {
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = strtr($value, $map);
    $value = preg_replace('~[^a-z0-9]+~', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'person';
}

function persons_sort_name(string $name): string {
    $name = trim(preg_replace('~\s+~u', ' ', $name) ?? $name);
    if ($name === '') {
        return '';
    }

    $parts = preg_split('~\s+~u', $name) ?: [];
    if (count($parts) >= 2) {
        $last = array_pop($parts);
        return trim($last . ' ' . implode(' ', $parts));
    }

    return $name;
}

function persons_parse_birthdate(?string $raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    foreach (['Y-m-d', 'd.m.Y', 'd-m-Y', 'd/m/Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime && $dt->format($format) === $raw) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function persons_display_birthdate(?string $birthdate, ?string $legacyDate): string {
    $birthdate = trim((string)$birthdate);
    if ($birthdate !== '' && $birthdate !== '0000-00-00') {
        $ts = strtotime($birthdate);
        if ($ts !== false) {
            return date('d.m.Y', $ts);
        }
    }

    return trim((string)$legacyDate);
}

function persons_aliases_from_input(string $input, string $personName): array {
    $parts = preg_split('~[\r\n,;]+~u', $input) ?: [];
    $aliases = [];
    foreach ($parts as $part) {
        $alias = trim($part);
        if ($alias !== '') {
            $aliases[mb_strtolower($alias, 'UTF-8')] = $alias;
        }
    }
    $aliases[mb_strtolower($personName, 'UTF-8')] = trim($personName);
    return array_values($aliases);
}

function persons_allowed_professions(): array {
    return [
        'actor' => 'Актер',
        'actress' => 'Актриса',
        'director' => 'Режиссер',
        'writer' => 'Сценарист',
        'producer' => 'Продюсер',
        'composer' => 'Композитор',
        'voice' => 'Озвучивание',
        'host' => 'Ведущий',
        'other' => 'Другое',
    ];
}

function persons_allowed_genders(): array {
    return [
        'unknown' => 'Пол персоны',
        'male' => 'Мужской',
        'female' => 'Женский',
        'other' => 'Другой',
    ];
}

function persons_ru_letters(): array {
    return preg_split('//u', 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЭЮЯ', -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function persons_country_options(mysqli $mysqli, int $selected = 0): string {
    $key = 'countries:options:ru:v1';
    $list = persons_mem_get($key);
    if (!is_array($list)) {
        $list = [];
        $res = sql_query('SELECT id, name FROM countries ORDER BY name');
        while ($row = mysqli_fetch_assoc($res)) {
            $list[(int)$row['id']] = (string)$row['name'];
        }
        persons_mem_set($key, $list, 3600);
    }

    $html = '<option value="0">Не выбрано</option>';
    foreach ($list as $id => $name) {
        $sel = $selected === (int)$id ? ' selected' : '';
        $html .= '<option value="' . (int)$id . '"' . $sel . '>' . h($name) . '</option>';
    }
    return $html;
}

function persons_country_name(mysqli $mysqli, int $countryId): string {
    if ($countryId <= 0) {
        return '';
    }

    $key = 'country:name:' . $countryId;
    $name = persons_mem_get($key);
    if (is_string($name)) {
        return $name;
    }

    $res = sql_query('SELECT name FROM countries WHERE id = ' . $countryId . ' LIMIT 1');
    $row = mysqli_fetch_assoc($res);
    $name = (string)($row['name'] ?? '');
    persons_mem_set($key, $name, 3600);
    return $name;
}

function persons_ensure_schema(mysqli $mysqli): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $flagArg = 'persons_schema_v4';
    $res = sql_query("SELECT value_u FROM avps WHERE arg = " . sqlesc($flagArg) . " LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row && (int)($row['value_u'] ?? 0) === 1) {
        return;
    }

    $neededColumns = [
        'slug' => "ALTER TABLE `pages` ADD COLUMN `slug` varchar(191) NOT NULL DEFAULT '' AFTER `name`",
        'sort_name' => "ALTER TABLE `pages` ADD COLUMN `sort_name` varchar(191) NOT NULL DEFAULT '' AFTER `slug`",
        'birthdate' => "ALTER TABLE `pages` ADD COLUMN `birthdate` date DEFAULT NULL AFTER `date`",
        'deathdate' => "ALTER TABLE `pages` ADD COLUMN `deathdate` date DEFAULT NULL AFTER `birthdate`",
        'gender' => "ALTER TABLE `pages` ADD COLUMN `gender` varchar(16) NOT NULL DEFAULT 'unknown' AFTER `deathdate`",
        'primary_profession' => "ALTER TABLE `pages` ADD COLUMN `primary_profession` varchar(64) NOT NULL DEFAULT 'actor' AFTER `gender`",
        'created_at' => "ALTER TABLE `pages` ADD COLUMN `created_at` datetime DEFAULT NULL AFTER `primary_profession`",
        'updated_at' => "ALTER TABLE `pages` ADD COLUMN `updated_at` datetime DEFAULT NULL AFTER `created_at`",
        'is_published' => "ALTER TABLE `pages` ADD COLUMN `is_published` varchar(3) NOT NULL DEFAULT 'yes' AFTER `updated_at`",
        'views' => "ALTER TABLE `pages` ADD COLUMN `views` int unsigned NOT NULL DEFAULT 0 AFTER `is_published`",
    ];

    foreach ($neededColumns as $column => $ddl) {
        if (!persons_has_column($mysqli, 'pages', $column)) {
            persons_exec_ddl($mysqli, $ddl);
        }
    }

    if (!persons_table_exists($mysqli, 'person_aliases')) {
        persons_exec_ddl($mysqli, "
            CREATE TABLE `person_aliases` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `person_id` int unsigned NOT NULL,
              `alias_name` varchar(191) NOT NULL,
              `norm_name` varchar(191) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_person_alias` (`person_id`,`norm_name`),
              KEY `idx_norm_name` (`norm_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!persons_table_exists($mysqli, 'person_professions')) {
        persons_exec_ddl($mysqli, "
            CREATE TABLE `person_professions` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `person_id` int unsigned NOT NULL,
              `profession` varchar(64) NOT NULL,
              `sort_order` tinyint unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_person_profession` (`person_id`,`profession`),
              KEY `idx_profession` (`profession`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!persons_table_exists($mysqli, 'torrent_persons')) {
        persons_exec_ddl($mysqli, "
            CREATE TABLE `torrent_persons` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `torrent_id` int unsigned NOT NULL,
              `person_id` int unsigned NOT NULL,
              `role` varchar(64) NOT NULL DEFAULT 'actor',
              `sort_order` smallint unsigned NOT NULL DEFAULT 0,
              `source` varchar(32) NOT NULL DEFAULT 'manual',
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_torrent_person_role` (`torrent_id`,`person_id`,`role`),
              KEY `idx_person_role` (`person_id`,`role`),
              KEY `idx_torrent` (`torrent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    sql_query("UPDATE pages SET slug = '' WHERE slug IS NULL");
    sql_query("UPDATE pages SET sort_name = '' WHERE sort_name IS NULL");
    sql_query("UPDATE pages SET created_at = NOW() WHERE created_at IS NULL");
    sql_query("UPDATE pages SET updated_at = created_at WHERE updated_at IS NULL");
    sql_query("UPDATE pages SET is_published = 'yes' WHERE is_published NOT IN ('yes','no')");
    sql_query("UPDATE pages SET primary_profession = 'actor' WHERE primary_profession = ''");
    sql_query("UPDATE pages SET gender = 'unknown' WHERE gender = ''");

    $res = sql_query("SELECT id, name, date, slug, sort_name, birthdate FROM pages");
    while ($page = mysqli_fetch_assoc($res)) {
        $updates = [];
        $id = (int)$page['id'];
        $slug = trim((string)$page['slug']);
        $sortName = trim((string)$page['sort_name']);
        $birthdate = trim((string)$page['birthdate']);
        $legacyDate = trim((string)$page['date']);

        if ($slug === '') {
            $updates[] = "slug = " . sqlesc(persons_slugify((string)$page['name']) . '-' . $id);
        }
        if ($sortName === '') {
            $updates[] = "sort_name = " . sqlesc(persons_sort_name((string)$page['name']));
        }
        if ($birthdate === '' && ($parsed = persons_parse_birthdate($legacyDate))) {
            $updates[] = "birthdate = " . sqlesc($parsed);
        }
        if ($updates) {
            sql_query("UPDATE pages SET " . implode(', ', $updates) . " WHERE id = " . $id . " LIMIT 1");
        }
    }

    sql_query("
        INSERT IGNORE INTO person_professions (person_id, profession, sort_order)
        SELECT id, primary_profession, 1
        FROM pages
        WHERE primary_profession <> ''
    ");

    sql_query("
        INSERT IGNORE INTO person_aliases (person_id, alias_name, norm_name)
        SELECT id, name, LOWER(name)
        FROM pages
        WHERE name <> ''
    ");

    sql_query("INSERT INTO avps (arg, value_u) VALUES (" . sqlesc($flagArg) . ", 1) ON DUPLICATE KEY UPDATE value_u = 1");
    persons_invalidate_cache();
}

function persons_fetch_professions(int $personId): array {
    $out = [];
    $res = sql_query('SELECT profession FROM person_professions WHERE person_id = ' . $personId . ' ORDER BY sort_order, profession');
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = (string)$row['profession'];
    }
    return $out;
}

function persons_fetch_aliases(int $personId): array {
    $out = [];
    $res = sql_query('SELECT alias_name FROM person_aliases WHERE person_id = ' . $personId . ' ORDER BY alias_name');
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = (string)$row['alias_name'];
    }
    return $out;
}

function persons_sync_profile_meta(int $personId, string $personName, array $professions, array $aliases): void {
    sql_query('DELETE FROM person_professions WHERE person_id = ' . $personId);
    $sort = 1;
    foreach ($professions as $profession) {
        sql_query(
            'INSERT INTO person_professions (person_id, profession, sort_order) VALUES (' .
            $personId . ', ' . sqlesc($profession) . ', ' . $sort . ')'
        );
        $sort++;
    }

    sql_query('DELETE FROM person_aliases WHERE person_id = ' . $personId);
    foreach ($aliases as $alias) {
        sql_query(
            'INSERT INTO person_aliases (person_id, alias_name, norm_name) VALUES (' .
            $personId . ', ' . sqlesc($alias) . ', ' . sqlesc(mb_strtolower($alias, 'UTF-8')) . ')'
        );
    }

    sql_query(
        'INSERT IGNORE INTO person_aliases (person_id, alias_name, norm_name) VALUES (' .
        $personId . ', ' . sqlesc($personName) . ', ' . sqlesc(mb_strtolower($personName, 'UTF-8')) . ')'
    );

    persons_invalidate_cache();
}

function persons_torrent_conditions(array $names): string {
    $parts = [];
    foreach ($names as $name) {
        $escaped = sqlwildcardesc($name);
        $parts[] = "(t.name LIKE '%{$escaped}%' OR t.descr LIKE '%{$escaped}%')";
    }
    return $parts ? implode(' OR ', $parts) : '1=0';
}

function persons_sync_torrent_links(int $personId, string $primaryRole, array $names): int {
    $names = array_values(array_unique(array_filter(array_map('trim', $names))));
    if (!$names) {
        return 0;
    }

    $where = persons_torrent_conditions($names);
    sql_query('DELETE FROM torrent_persons WHERE person_id = ' . $personId . " AND source = 'auto'");

    $res = sql_query("
        SELECT t.id
        FROM torrents t
        WHERE t.visible = 'yes'
          AND ({$where})
    ");

    $count = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $torrentId = (int)$row['id'];
        sql_query(
            'INSERT IGNORE INTO torrent_persons (torrent_id, person_id, role, sort_order, source) VALUES (' .
            $torrentId . ', ' . $personId . ', ' . sqlesc($primaryRole) . ", 1, 'auto')"
        );
        $count++;
    }

    return $count;
}

function persons_fetch_related_torrents(int $personId, array $aliases, string $mode = 'releases'): array {
    $rows = [];
    $order = $mode === 'top' ? 't.seeders DESC, t.id DESC' : 't.id DESC';
    $limit = $mode === 'top' ? 24 : 100;

    $res = sql_query("
        SELECT t.id, t.name, t.seeders, t.leechers, t.times_completed, t.added, tp.role
        FROM torrent_persons tp
        INNER JOIN torrents t ON t.id = tp.torrent_id
        WHERE tp.person_id = {$personId}
          AND t.visible = 'yes'
        ORDER BY {$order}
        LIMIT {$limit}
    ");

    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

    if ($rows) {
        return $rows;
    }

    $where = persons_torrent_conditions($aliases);
    $res = sql_query("
        SELECT t.id, t.name, t.seeders, t.leechers, t.times_completed, t.added, '' AS role
        FROM torrents t
        WHERE t.visible = 'yes'
          AND ({$where})
        ORDER BY {$order}
        LIMIT {$limit}
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

    return $rows;
}

function persons_render_card(array $row, string $placeholder): string {
    $id = (int)$row['id'];
    $name = h((string)$row['name']);
    $img = trim((string)($row['img'] ?? '')) !== '' ? h((string)$row['img']) : $placeholder;
    $date = h(persons_display_birthdate($row['birthdate'] ?? null, $row['date'] ?? null));
    $prof = trim((string)($row['profession_label'] ?? ''));
    $profHtml = $prof !== '' ? '<div class="person-card__meta">' . h($prof) . '</div>' : '';

    return "<a class=\"person-card\" href=\"persons.php?id={$id}\">"
        . "<img class=\"person-card__img\" src=\"{$img}\" alt=\"{$name}\" loading=\"lazy\">"
        . "<div class=\"person-card__name\">{$name}</div>"
        . $profHtml
        . ($date !== '' ? "<div class=\"person-card__date\">{$date}</div>" : '')
        . "</a>";
}

function persons_render_sidebar(array $filters, array $counts, array $professions, array $letters): string {
    $q = h($filters['q']);
    $profession = (string)$filters['profession'];
    $gender = (string)$filters['gender'];
    $bdayDay = (int)$filters['bday_day'];
    $bdayMonth = (int)$filters['bday_month'];
    $bdayYear = (int)$filters['bday_year'];
    $mode = (string)$filters['mode'];
    $letter = (string)$filters['letter'];

    $days = '<option value="0">День</option>';
    for ($i = 1; $i <= 31; $i++) {
        $sel = $bdayDay === $i ? ' selected' : '';
        $days .= '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }

    $months = '<option value="0">Месяц</option>';
    for ($i = 1; $i <= 12; $i++) {
        $sel = $bdayMonth === $i ? ' selected' : '';
        $months .= '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }

    $currentYear = (int)date('Y');
    $years = '<option value="0">Год</option>';
    for ($i = $currentYear; $i >= 1900; $i--) {
        $sel = $bdayYear === $i ? ' selected' : '';
        $years .= '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }

    $profOptions = '<option value="">Все</option>';
    foreach ($professions as $key => $label) {
        $sel = $profession === $key ? ' selected' : '';
        $profOptions .= '<option value="' . h($key) . '"' . $sel . '>' . h($label) . '</option>';
    }

    $genderOptions = '';
    foreach (persons_allowed_genders() as $key => $label) {
        $value = $key === 'unknown' ? '' : $key;
        $sel = $gender === $value ? ' selected' : '';
        $genderOptions .= '<option value="' . h($value) . '"' . $sel . '>' . h($label) . '</option>';
    }

    $base = [
        'q' => $filters['q'],
        'profession' => $filters['profession'],
        'gender' => $filters['gender'],
        'bday_day' => $filters['bday_day'],
        'bday_month' => $filters['bday_month'],
        'bday_year' => $filters['bday_year'],
    ];

    $makeUrl = static function(array $params) use ($base): string {
        $merged = array_merge($base, $params);
        foreach ($merged as $k => $v) {
            if ($v === '' || $v === 0 || $v === '0' || $v === null) {
                unset($merged[$k]);
            }
        }
        return 'persons.php?' . http_build_query($merged);
    };

    $selection = [
        'all' => ['Все персоны', (int)($counts['all'] ?? 0)],
        'birthday_today' => ['День рождения', (int)($counts['birthday_today'] ?? 0)],
        'new' => ['Новые персоны', (int)($counts['new'] ?? 0)],
        'updated' => ['Недавно измененные', (int)($counts['updated'] ?? 0)],
    ];

    ob_start();
    ?>
    <aside class="persons-sidebar">
      <div class="persons-panel">
        <div class="persons-panel__title">Поиск персон</div>
        <form id="persons-filter-form" method="get" action="persons.php">
          <div class="persons-field">
            <label>Имя</label>
            <input type="text" name="q" value="<?= $q ?>" placeholder="Имя Фамилия">
          </div>
          <div class="persons-field">
            <label>Категория</label>
            <select name="profession"><?= $profOptions ?></select>
          </div>
          <div class="persons-field">
            <label>Пол</label>
            <select name="gender"><?= $genderOptions ?></select>
          </div>
          <div class="persons-field">
            <label>Дата рождения</label>
            <div class="persons-bday">
              <select name="bday_day"><?= $days ?></select>
              <select name="bday_month"><?= $months ?></select>
              <select name="bday_year"><?= $years ?></select>
            </div>
          </div>
          <div class="persons-actions">
            <button type="submit">Поиск персон</button>
          </div>
        </form>
      </div>

      <div class="persons-panel">
        <div class="persons-panel__title">Выбор персон</div>
        <?php foreach ($selection as $key => [$label, $count]): ?>
          <a class="persons-link<?= $mode === $key ? ' is-active' : '' ?>" href="<?= h($makeUrl(['mode' => $key, 'letter' => ''])) ?>">
            <?= h($label) ?> <span><?= $count ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="persons-panel">
        <div class="persons-panel__title">Заглавная буква имени</div>
        <div class="persons-letters">
          <?php foreach ($letters as $char): ?>
            <a class="persons-letter<?= $letter === $char ? ' is-active' : '' ?>" href="<?= h($makeUrl(['letter' => $char, 'mode' => $mode])) ?>"><?= h($char) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="persons-panel">
        <div class="persons-panel__title">Информация</div>
        <div class="persons-info">
          Раздел персон теперь поддерживает поиск по алиасам, фильтрацию по профессии и полу, дни рождения, алфавит и отдельные связи персон с раздачами.
        </div>
      </div>
    </aside>
    <?php
    return (string)ob_get_clean();
}

function persons_render_torrents_table(array $rows): string {
    if (!$rows) {
        return '<div class="persons-empty">Пока нет связанных раздач.</div>';
    }

    $html = '<table class="persons-torrents" width="100%" cellpadding="6" cellspacing="0"><tr><th align="left">Раздача</th><th align="center">Сиды</th><th align="center">Личи</th><th align="center">Скач.</th><th align="right">Добавлено</th></tr>';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $name = h((string)$row['name']);
        $seeders = (int)$row['seeders'];
        $leechers = (int)$row['leechers'];
        $completed = (int)$row['times_completed'];
        $added = h((string)$row['added']);
        $html .= "<tr>"
            . "<td><a href=\"details.php?id={$id}\"><b>{$name}</b></a></td>"
            . "<td align=\"center\">{$seeders}</td>"
            . "<td align=\"center\">{$leechers}</td>"
            . "<td align=\"center\">{$completed}</td>"
            . "<td align=\"right\">{$added}</td>"
            . "</tr>";
    }
    $html .= '</table>';
    return $html;
}

function persons_fetch_mode_counts(): array {
    $statsKey = 'persons:stats:v2';
    $counts = persons_mem_get($statsKey);
    if (is_array($counts)) {
        return array_merge([
            'all' => 0,
            'birthday_today' => 0,
            'new' => 0,
            'updated' => 0,
        ], $counts);
    }

    $counts = [
        'all' => 0,
        'birthday_today' => 0,
        'new' => 0,
        'updated' => 0,
    ];
    $r = sql_query("
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN DAY(birthdate) = " . (int)date('j') . " AND MONTH(birthdate) = " . (int)date('n') . " THEN 1 ELSE 0 END) AS birthday_today,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_count,
            SUM(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS updated_count
        FROM pages
        WHERE is_published = 'yes'
    ");
    $row = mysqli_fetch_assoc($r) ?: [];
    $counts['all'] = (int)($row['total_count'] ?? 0);
    $counts['birthday_today'] = (int)($row['birthday_today'] ?? 0);
    $counts['new'] = (int)($row['new_count'] ?? 0);
    $counts['updated'] = (int)($row['updated_count'] ?? 0);
    persons_mem_set($statsKey, $counts, 300);
    return $counts;
}

function persons_render_tab(string $tab, array $person, string $country, array $professions, array $aliases): string {
    $photo = trim((string)$person['img']) !== '' ? h((string)$person['img']) : 'styles/images/nophoto.png';
    $title = h((string)$person['name']);
    $birth = h(persons_display_birthdate($person['birthdate'] ?? null, $person['date'] ?? null));
    $death = trim((string)($person['deathdate'] ?? '')) !== '' ? h(date('d.m.Y', strtotime((string)$person['deathdate']))) : '';
    $countryHtml = $country !== '' ? h($country) : 'Не указана';
    $professionLabels = persons_allowed_professions();
    $professionHtml = [];
    foreach ($professions as $profession) {
        $professionHtml[] = $professionLabels[$profession] ?? $profession;
    }
    $aliasesHtml = [];
    foreach ($aliases as $alias) {
        if (mb_strtolower($alias, 'UTF-8') !== mb_strtolower((string)$person['name'], 'UTF-8')) {
            $aliasesHtml[] = '<span class="persons-chip">' . h($alias) . '</span>';
        }
    }

    if ($tab === 'info') {
        $gallery = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($person['img' . $i])) {
                $src = h((string)$person['img' . $i]);
                $gallery[] = "<img src=\"{$src}\" alt=\"screen{$i}\" loading=\"lazy\">";
            }
        }

        $html = "<div class=\"person-view\">"
            . "<div class=\"person-view__head\">"
            . "<div class=\"person-view__media\"><img class=\"person-view__poster\" src=\"{$photo}\" alt=\"{$title}\" loading=\"lazy\"></div>"
            . "<div class=\"person-view__body\">"
            . "<h1 class=\"person-view__title\">{$title}</h1>"
            . "<div class=\"person-view__meta\"><span>Дата рождения: {$birth}</span><span>Страна: {$countryHtml}</span>"
            . ($death !== '' ? "<span>Дата смерти: {$death}</span>" : '')
            . "</div>"
            . "<div class=\"person-view__meta\"><span>Профессии: " . h(implode(', ', $professionHtml ?: ['Не указаны'])) . "</span></div>";
        if ($aliasesHtml) {
            $html .= "<div class=\"person-view__aliases\">" . implode('', $aliasesHtml) . "</div>";
        }
        $html .= "<div class=\"person-view__text\">" . format_comment((string)$person['content']) . "</div>"
            . "</div></div>";
        if ($gallery) {
            $html .= "<div class=\"person-view__gallery\">" . implode('', $gallery) . "</div>";
        }
        return $html . "</div>";
    }

    $rows = persons_fetch_related_torrents((int)$person['id'], $aliases, $tab);
    return persons_render_torrents_table($rows);
}

persons_ensure_schema($mysqli);

$id = (int)($_GET['id'] ?? 0);
$ajax = !empty($_GET['ajax']) && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$placeholder = 'styles/images/nophoto.png';
$professionOptions = persons_allowed_professions();
$letters = persons_ru_letters();

if (isset($_GET['add'])) {
    if (get_user_class() < UC_POWER_USER) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    stdhead('Добавить персону');
    $countryOptions = persons_country_options($mysqli, 0);
    $profHtml = '';
    foreach ($professionOptions as $key => $label) {
        $checked = $key === 'actor' ? ' checked' : '';
        $profHtml .= '<label class="persons-check"><input type="checkbox" name="professions[]" value="' . h($key) . '"' . $checked . '> ' . h($label) . '</label>';
    }

    echo <<<CSS
<style>
.persons-form-wrap{max-width:980px;margin:0 auto}
.persons-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.persons-form-grid--full{grid-column:1/-1}
.persons-field label{display:block;font-weight:700;margin-bottom:6px;color:inherit}
.persons-field input[type=text],.persons-field input[type=date],.persons-field select,.persons-field textarea{width:100%;padding:9px 10px;border:1px solid #c7c7c7;background:#fff;box-sizing:border-box;font:inherit;color:inherit}
.persons-field textarea{min-height:140px;resize:vertical;line-height:1.45}
.persons-checks{display:flex;flex-wrap:wrap;gap:10px 16px}
.persons-check{display:inline-flex;align-items:center;gap:6px;color:inherit}
</style>
CSS;

    begin_frame('Добавить персону');
    echo '<div class="persons-form-wrap"><form method="post" action="persons.php?saveadd">';
    echo '<div class="persons-form-grid">';
    echo '<div class="persons-field"><label>Имя и фамилия</label><input type="text" name="name" required></div>';
    echo '<div class="persons-field"><label>Слаг</label><input type="text" name="slug" placeholder="создастся автоматически"></div>';
    echo '<div class="persons-field"><label>Дата рождения</label><input type="date" name="birthdate"></div>';
    echo '<div class="persons-field"><label>Дата смерти</label><input type="date" name="deathdate"></div>';
    echo '<div class="persons-field"><label>Отображаемая дата / период</label><input type="text" name="date_label" placeholder="если нужен текст вместо даты"></div>';
    echo '<div class="persons-field"><label>Пол</label><select name="gender">';
    foreach (persons_allowed_genders() as $key => $label) {
        echo '<option value="' . h($key) . '">' . h($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="persons-field"><label>Страна</label><select name="country">' . $countryOptions . '</select></div>';
    echo '<div class="persons-field"><label>Основное фото</label><input type="text" name="img" placeholder="https://..."></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Профессии</label><div class="persons-checks">' . $profHtml . '</div></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Алиасы и варианты имени</label><textarea name="aliases" placeholder="Каждый алиас с новой строки или через запятую"></textarea></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Биография</label>' . capture_textbbcode('personadd', 'content') . '</div>';
    for ($i = 1; $i <= 4; $i++) {
        echo '<div class="persons-field"><label>Кадр ' . $i . '</label><input type="text" name="img' . $i . '" placeholder="https://..."></div>';
    }
    echo '</div><div style="margin-top:14px;text-align:center"><input type="submit" value="Сохранить"></div></form></div>';
    end_frame();
    stdfoot();
    exit;
}

if (isset($_GET['saveadd'])) {
    if (get_user_class() < UC_POWER_USER) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        stderr($tracker_lang['error'], 'Имя персоны не заполнено.');
    }

    $birthdate = persons_parse_birthdate((string)($_POST['birthdate'] ?? ''));
    $deathdate = persons_parse_birthdate((string)($_POST['deathdate'] ?? ''));
    $dateLabel = trim((string)($_POST['date_label'] ?? ''));
    if ($dateLabel === '' && $birthdate) {
        $dateLabel = date('d.m.Y', strtotime($birthdate));
    }
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $slug = $slugInput !== '' ? persons_slugify($slugInput) : persons_slugify($name);
    $country = (string)((int)($_POST['country'] ?? 0));
    $gender = (string)($_POST['gender'] ?? 'unknown');
    if (!isset(persons_allowed_genders()[$gender])) {
        $gender = 'unknown';
    }
    $professions = array_values(array_intersect(array_keys($professionOptions), (array)($_POST['professions'] ?? [])));
    if (!$professions) {
        $professions = ['actor'];
    }
    $primaryProfession = $professions[0];
    $aliases = persons_aliases_from_input((string)($_POST['aliases'] ?? ''), $name);
    $createdAt = get_date_time();
    $updatedAt = $createdAt;

    $img = (string)($_POST['img'] ?? '');
    $content = (string)($_POST['content'] ?? '');
    $sortName = persons_sort_name($name);
    $img1 = (string)($_POST['img1'] ?? '');
    $img2 = (string)($_POST['img2'] ?? '');
    $img3 = (string)($_POST['img3'] ?? '');
    $img4 = (string)($_POST['img4'] ?? '');
    sql_query("
        INSERT INTO pages (
            img, name, slug, sort_name, content, date, birthdate, deathdate, gender, primary_profession,
            created_at, updated_at, is_published, country, img1, img2, img3, img4
        ) VALUES (
            " . sqlesc($img) . ",
            " . sqlesc($name) . ",
            " . sqlesc($slug) . ",
            " . sqlesc($sortName) . ",
            " . sqlesc($content) . ",
            " . sqlesc($dateLabel) . ",
            " . ($birthdate ? sqlesc($birthdate) : 'NULL') . ",
            " . ($deathdate ? sqlesc($deathdate) : 'NULL') . ",
            " . sqlesc($gender) . ",
            " . sqlesc($primaryProfession) . ",
            " . sqlesc($createdAt) . ",
            " . sqlesc($updatedAt) . ",
            'yes',
            " . sqlesc($country) . ",
            " . sqlesc($img1) . ",
            " . sqlesc($img2) . ",
            " . sqlesc($img3) . ",
            " . sqlesc($img4) . "
        )
    ");
    $personId = (int)$mysqli->insert_id;
    persons_sync_profile_meta($personId, $name, $professions, $aliases);

    stderr($tracker_lang['success'], "Персона добавлена: <a href='persons.php?id={$personId}'>" . h($name) . '</a>', 'success');
    exit;
}

if (isset($_GET['edit']) && $id > 0) {
    if (get_user_class() < UC_ADMINISTRATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $res = sql_query('SELECT * FROM pages WHERE id = ' . $id . ' LIMIT 1');
    $person = mysqli_fetch_assoc($res);
    if (!$person) {
        stderr($tracker_lang['error'], $tracker_lang['no_page_with_this_id']);
    }

    $countryOptions = persons_country_options($mysqli, (int)$person['country']);
    $professions = persons_fetch_professions($id);
    if (!$professions) {
        $professions = [(string)$person['primary_profession']];
    }
    $aliases = persons_fetch_aliases($id);
    $birthdate = trim((string)($person['birthdate'] ?? ''));
    $deathdate = trim((string)($person['deathdate'] ?? ''));

    stdhead('Редактировать персону');
    echo <<<CSS
<style>
.persons-form-wrap{max-width:980px;margin:0 auto}
.persons-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.persons-form-grid--full{grid-column:1/-1}
.persons-field label{display:block;font-weight:700;margin-bottom:6px;color:inherit}
.persons-field input[type=text],.persons-field input[type=date],.persons-field select,.persons-field textarea{width:100%;padding:9px 10px;border:1px solid #c7c7c7;background:#fff;box-sizing:border-box;font:inherit;color:inherit}
.persons-field textarea{min-height:140px;resize:vertical;line-height:1.45}
.persons-checks{display:flex;flex-wrap:wrap;gap:10px 16px}
.persons-check{display:inline-flex;align-items:center;gap:6px;color:inherit}
</style>
CSS;
    begin_frame('Редактировать персону: ' . h((string)$person['name']));
    echo '<div class="persons-form-wrap"><form method="post" action="persons.php?saveedit&id=' . $id . '">';
    echo '<div class="persons-form-grid">';
    echo '<div class="persons-field"><label>Имя и фамилия</label><input type="text" name="name" value="' . h((string)$person['name']) . '" required></div>';
    echo '<div class="persons-field"><label>Слаг</label><input type="text" name="slug" value="' . h((string)$person['slug']) . '"></div>';
    echo '<div class="persons-field"><label>Дата рождения</label><input type="date" name="birthdate" value="' . h($birthdate) . '"></div>';
    echo '<div class="persons-field"><label>Дата смерти</label><input type="date" name="deathdate" value="' . h($deathdate) . '"></div>';
    echo '<div class="persons-field"><label>Отображаемая дата / период</label><input type="text" name="date_label" value="' . h((string)$person['date']) . '"></div>';
    echo '<div class="persons-field"><label>Пол</label><select name="gender">';
    foreach (persons_allowed_genders() as $key => $label) {
        $sel = ((string)$person['gender'] === $key) ? ' selected' : '';
        echo '<option value="' . h($key) . '"' . $sel . '>' . h($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="persons-field"><label>Страна</label><select name="country">' . $countryOptions . '</select></div>';
    echo '<div class="persons-field"><label>Основное фото</label><input type="text" name="img" value="' . h((string)$person['img']) . '"></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Профессии</label><div class="persons-checks">';
    foreach ($professionOptions as $key => $label) {
        $checked = in_array($key, $professions, true) ? ' checked' : '';
        echo '<label class="persons-check"><input type="checkbox" name="professions[]" value="' . h($key) . '"' . $checked . '> ' . h($label) . '</label>';
    }
    echo '</div></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Алиасы и варианты имени</label><textarea name="aliases">' . h(implode("\n", $aliases)) . '</textarea></div>';
    echo '<div class="persons-field persons-form-grid--full"><label>Биография</label>' . capture_textbbcode('personedit', 'content', (string)$person['content']) . '</div>';
    for ($i = 1; $i <= 4; $i++) {
        echo '<div class="persons-field"><label>Кадр ' . $i . '</label><input type="text" name="img' . $i . '" value="' . h((string)$person['img' . $i]) . '"></div>';
    }
    echo '</div><div style="margin-top:14px;text-align:center"><input type="submit" value="Сохранить"></div></form></div>';
    end_frame();
    stdfoot();
    exit;
}

if (isset($_GET['saveedit']) && $id > 0) {
    if (get_user_class() < UC_ADMINISTRATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        stderr($tracker_lang['error'], 'Имя персоны не заполнено.');
    }

    $birthdate = persons_parse_birthdate((string)($_POST['birthdate'] ?? ''));
    $deathdate = persons_parse_birthdate((string)($_POST['deathdate'] ?? ''));
    $dateLabel = trim((string)($_POST['date_label'] ?? ''));
    if ($dateLabel === '' && $birthdate) {
        $dateLabel = date('d.m.Y', strtotime($birthdate));
    }

    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $slug = $slugInput !== '' ? persons_slugify($slugInput) : persons_slugify($name);
    $country = (string)((int)($_POST['country'] ?? 0));
    $gender = (string)($_POST['gender'] ?? 'unknown');
    if (!isset(persons_allowed_genders()[$gender])) {
        $gender = 'unknown';
    }
    $professions = array_values(array_intersect(array_keys($professionOptions), (array)($_POST['professions'] ?? [])));
    if (!$professions) {
        $professions = ['actor'];
    }
    $primaryProfession = $professions[0];
    $aliases = persons_aliases_from_input((string)($_POST['aliases'] ?? ''), $name);
    $updatedAt = get_date_time();

    $img = (string)($_POST['img'] ?? '');
    $content = (string)($_POST['content'] ?? '');
    $sortName = persons_sort_name($name);
    $img1 = (string)($_POST['img1'] ?? '');
    $img2 = (string)($_POST['img2'] ?? '');
    $img3 = (string)($_POST['img3'] ?? '');
    $img4 = (string)($_POST['img4'] ?? '');
    sql_query("
        UPDATE pages SET
            img = " . sqlesc($img) . ",
            name = " . sqlesc($name) . ",
            slug = " . sqlesc($slug) . ",
            sort_name = " . sqlesc($sortName) . ",
            content = " . sqlesc($content) . ",
            date = " . sqlesc($dateLabel) . ",
            birthdate = " . ($birthdate ? sqlesc($birthdate) : 'NULL') . ",
            deathdate = " . ($deathdate ? sqlesc($deathdate) : 'NULL') . ",
            gender = " . sqlesc($gender) . ",
            primary_profession = " . sqlesc($primaryProfession) . ",
            updated_at = " . sqlesc($updatedAt) . ",
            country = " . sqlesc($country) . ",
            img1 = " . sqlesc($img1) . ",
            img2 = " . sqlesc($img2) . ",
            img3 = " . sqlesc($img3) . ",
            img4 = " . sqlesc($img4) . "
        WHERE id = {$id}
        LIMIT 1
    ");
    persons_sync_profile_meta($id, $name, $professions, $aliases);

    stderr($tracker_lang['success'], "Персона обновлена: <a href='persons.php?id={$id}'>" . h($name) . '</a>', 'success');
    exit;
}

if (isset($_GET['delete']) && $id > 0) {
    if (get_user_class() < UC_ADMINISTRATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    sql_query('DELETE FROM person_aliases WHERE person_id = ' . $id);
    sql_query('DELETE FROM person_professions WHERE person_id = ' . $id);
    sql_query('DELETE FROM torrent_persons WHERE person_id = ' . $id);
    sql_query('DELETE FROM pages WHERE id = ' . $id . ' LIMIT 1');
    persons_invalidate_cache();
    stderr($tracker_lang['success'], 'Персона удалена.', 'success');
    exit;
}

if (isset($_GET['sync_links']) && $id > 0) {
    if (get_user_class() < UC_ADMINISTRATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $res = sql_query('SELECT id, name, primary_profession FROM pages WHERE id = ' . $id . ' LIMIT 1');
    $person = mysqli_fetch_assoc($res);
    if (!$person) {
        stderr($tracker_lang['error'], $tracker_lang['no_page_with_this_id']);
    }

    $aliases = persons_fetch_aliases($id);
    if (!$aliases) {
        $aliases = [(string)$person['name']];
    }
    $count = persons_sync_torrent_links($id, (string)$person['primary_profession'], $aliases);
    stderr($tracker_lang['success'], 'Связи с раздачами обновлены. Найдено совпадений: ' . $count, 'success');
    exit;
}

if ($id > 0) {
    $tab = (string)($_GET['tab'] ?? 'info');
    if (!in_array($tab, ['info', 'releases', 'top'], true)) {
        $tab = 'info';
    }

    $res = sql_query('SELECT * FROM pages WHERE id = ' . $id . " AND is_published = 'yes' LIMIT 1");
    $person = mysqli_fetch_assoc($res);
    if (!$person) {
        stderr($tracker_lang['error'], $tracker_lang['no_page_with_this_id']);
    }

    $country = persons_country_name($mysqli, (int)$person['country']);
    $professions = persons_fetch_professions($id);
    if (!$professions) {
        $professions = [(string)$person['primary_profession']];
    }
    $aliases = persons_fetch_aliases($id);
    if (!$aliases) {
        $aliases = [(string)$person['name']];
    }

    if ($ajax && (($_GET['type'] ?? '') === 'tab')) {
        echo persons_render_tab($tab, $person, $country, $professions, $aliases);
        exit;
    }

    sql_query('UPDATE pages SET views = views + 1 WHERE id = ' . $id . ' LIMIT 1');

    $canEdit = get_user_class() >= UC_ADMINISTRATOR;
    $title = h((string)$person['name']);

    stdhead('Персона: ' . $title);
    echo <<<CSS
<style>
.persons-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:14px}
.persons-sidebar{display:flex;flex-direction:column;gap:12px}
.persons-panel{padding:10px;border:1px solid rgba(127,127,127,.28);background:transparent;color:inherit}
.persons-panel__title{margin-bottom:8px;padding:0 0 6px;border-bottom:1px solid rgba(127,127,127,.22);font-size:14px;font-weight:700;color:inherit}
.persons-info{font-size:12px;line-height:1.45;color:inherit;opacity:.88}
.persons-link{display:flex;justify-content:space-between;padding:4px 0;text-decoration:none;color:inherit}
.persons-link.is-active{font-weight:700;text-decoration:underline}
.persons-letters{display:grid;grid-template-columns:repeat(6,1fr);gap:4px}
.persons-letter{display:flex;align-items:center;justify-content:center;min-height:28px;border:1px solid rgba(127,127,127,.3);background:transparent;text-decoration:none;color:inherit;font-weight:700}
.persons-letter.is-active{font-weight:800;border-color:currentColor;background:rgba(127,127,127,.08)}
.persons-field{margin-bottom:10px}
.persons-field label{display:block;margin-bottom:5px;font-weight:700;color:inherit}
.persons-field input[type=text],.persons-field select{width:100%;padding:8px 10px;border:1px solid rgba(127,127,127,.35);background:#fff;box-sizing:border-box;font:inherit;color:inherit}
.persons-bday{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px}
.persons-actions button{width:100%;padding:9px 12px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer}
.persons-main{min-width:0}
.persons-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 10px}
.persons-heading__title{margin:0;font-size:22px;line-height:1.15;font-weight:800;color:inherit}
.persons-heading__sub{font-size:13px;color:inherit;opacity:.72}
.persons-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.person-card{display:block;overflow:hidden;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;text-decoration:none}
.person-card:hover{border-color:rgba(127,127,127,.45)}
.person-card__img{display:block;width:100%;aspect-ratio:3/4;object-fit:cover;background:rgba(127,127,127,.08)}
.person-card__name{padding:7px 8px 2px;font-size:14px;line-height:1.25;font-weight:700;color:inherit}
.person-card__meta,.person-card__date{padding:0 8px 6px;font-size:12px;color:inherit;opacity:.74}
.person-view{padding:14px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit}
.person-view__head{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px}
.person-view__poster{display:block;width:220px;height:300px;object-fit:cover;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.08)}
.person-view__title{margin:0 0 8px;font-size:32px;line-height:1.05;color:inherit}
.person-view__meta{display:flex;flex-wrap:wrap;gap:8px 16px;margin:0 0 10px;color:inherit;opacity:.82}
.person-view__aliases{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.persons-chip{display:inline-flex;align-items:center;padding:5px 9px;border:1px solid rgba(127,127,127,.3);background:transparent;font-size:12px;color:inherit}
.person-view__text{font-size:15px;line-height:1.6;color:inherit}
.person-view__gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:14px}
.person-view__gallery img{width:100%;height:128px;object-fit:cover;border:1px solid rgba(127,127,127,.25);background:rgba(127,127,127,.08)}
.persons-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;padding-bottom:6px;border-bottom:1px solid rgba(127,127,127,.18)}
.persons-tabs a{display:inline-flex;align-items:center;padding:0 0 6px;border:none;border-bottom:2px solid transparent;background:transparent;text-decoration:none;color:inherit;font:inherit;font-weight:700}
.persons-tabs a.is-active{border-bottom-color:currentColor}
.persons-tools{display:flex;gap:8px;flex-wrap:wrap}
.persons-tools a{display:inline-flex;align-items:center;padding:6px 10px;border:1px solid rgba(127,127,127,.28);background:transparent;text-decoration:none;color:inherit;font-weight:700}
.persons-empty{padding:16px;border:1px dashed rgba(127,127,127,.28);background:transparent;color:inherit;opacity:.8}
.persons-torrents{border-collapse:collapse}
.persons-torrents th,.persons-torrents td{border-bottom:1px solid rgba(127,127,127,.18)}
@media (max-width: 980px){
  .persons-layout{grid-template-columns:1fr}
  .person-view__head{grid-template-columns:1fr}
  .person-view__poster{width:100%;max-width:260px;height:auto;aspect-ratio:11/15}
}
</style>
CSS;

    $modeCounts = persons_fetch_mode_counts();
    begin_frame($title);
    echo '<div class="persons-layout">';
    echo persons_render_sidebar([
        'q' => '',
        'profession' => '',
        'gender' => '',
        'bday_day' => 0,
        'bday_month' => 0,
        'bday_year' => 0,
        'mode' => 'all',
        'letter' => '',
    ], $modeCounts, $professionOptions, $letters);
    echo '<div class="persons-main">';
    if ($canEdit) {
        echo '<div class="persons-tools">'
            . '<a href="persons.php?edit&id=' . $id . '">Редактировать</a>'
            . '<a href="persons.php?sync_links=1&id=' . $id . '">Обновить связи с раздачами</a>'
            . '<a href="persons.php?delete=1&id=' . $id . '" onclick="return confirm(\'Удалить персону?\')">Удалить</a>'
            . '</div>';
    }
    echo '<div class="persons-tabs" id="person-tabs" data-id="' . $id . '">';
    foreach (['info' => 'Информация', 'releases' => 'Раздачи персоны', 'top' => 'Топ раздач персоны'] as $tabKey => $tabLabel) {
        $class = $tab === $tabKey ? ' is-active' : '';
        echo '<a class="' . $class . '" href="persons.php?id=' . $id . '&tab=' . $tabKey . '" data-tab="' . $tabKey . '">' . h($tabLabel) . '</a>';
    }
    echo '</div><div id="person-tab">' . persons_render_tab($tab, $person, $country, $professions, $aliases) . '</div></div></div>';
    end_frame();
    ?>
    <script>
    (function(){
      const tabs = document.getElementById('person-tabs');
      const box = document.getElementById('person-tab');
      if (!tabs || !box) return;
      const id = tabs.dataset.id;
      tabs.addEventListener('click', async function(e){
        const a = e.target.closest('a[data-tab]');
        if (!a) return;
        e.preventDefault();
        const tab = a.dataset.tab;
        const url = 'persons.php?id=' + id + '&ajax=1&type=tab&tab=' + encodeURIComponent(tab);
        const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        box.innerHTML = await r.text();
        tabs.querySelectorAll('a').forEach(function(link){ link.classList.toggle('is-active', link === a); });
        history.replaceState(null, '', 'persons.php?id=' + id + '&tab=' + tab);
      });
    })();
    </script>
    <?php
    stdfoot();
    exit;
}

if ($ajax && (($_GET['type'] ?? '') === 'suggest')) {
    header('Content-Type: application/json; charset=UTF-8');
    $term = trim((string)($_GET['q'] ?? ''));
    if ($term === '') {
        echo json_encode([]);
        exit;
    }

    $like = '%' . $mysqli->real_escape_string($term) . '%';
    $res = sql_query("
        SELECT p.id, p.name, p.img
        FROM pages p
        LEFT JOIN person_aliases pa ON pa.person_id = p.id
        WHERE p.is_published = 'yes'
          AND (p.name LIKE '{$like}' OR pa.alias_name LIKE '{$like}')
        GROUP BY p.id, p.name, p.img
        ORDER BY p.sort_name ASC
        LIMIT 10
    ");

    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'img' => trim((string)$row['img']) !== '' ? (string)$row['img'] : $placeholder,
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'profession' => trim((string)($_GET['profession'] ?? '')),
    'gender' => trim((string)($_GET['gender'] ?? '')),
    'bday_day' => (int)($_GET['bday_day'] ?? 0),
    'bday_month' => (int)($_GET['bday_month'] ?? 0),
    'bday_year' => (int)($_GET['bday_year'] ?? 0),
    'mode' => trim((string)($_GET['mode'] ?? 'all')),
    'letter' => trim((string)($_GET['letter'] ?? '')),
];

if (!isset($professionOptions[$filters['profession']])) {
    $filters['profession'] = '';
}
if ($filters['gender'] !== '' && !isset(persons_allowed_genders()[$filters['gender']])) {
    $filters['gender'] = '';
}
if (!in_array($filters['mode'], ['birthday_today', 'new', 'updated', 'all'], true)) {
    $filters['mode'] = 'all';
}
if ($filters['letter'] !== '' && !in_array($filters['letter'], $letters, true)) {
    $filters['letter'] = '';
}

$where = ["p.is_published = 'yes'"];

if ($filters['q'] !== '') {
    $qEsc = $mysqli->real_escape_string($filters['q']);
    $where[] = "(p.name LIKE '%{$qEsc}%' OR EXISTS (SELECT 1 FROM person_aliases pa WHERE pa.person_id = p.id AND pa.alias_name LIKE '%{$qEsc}%'))";
}
if ($filters['profession'] !== '') {
    $where[] = "EXISTS (SELECT 1 FROM person_professions pp WHERE pp.person_id = p.id AND pp.profession = " . sqlesc($filters['profession']) . ")";
}
if ($filters['gender'] !== '') {
    $where[] = "p.gender = " . sqlesc($filters['gender']);
}
if ($filters['bday_day'] > 0) {
    $where[] = 'DAY(p.birthdate) = ' . $filters['bday_day'];
}
if ($filters['bday_month'] > 0) {
    $where[] = 'MONTH(p.birthdate) = ' . $filters['bday_month'];
}
if ($filters['bday_year'] > 0) {
    $where[] = 'YEAR(p.birthdate) = ' . $filters['bday_year'];
}
if ($filters['mode'] === 'birthday_today') {
    $where[] = 'DAY(p.birthdate) = ' . (int)date('j');
    $where[] = 'MONTH(p.birthdate) = ' . (int)date('n');
}
if ($filters['letter'] !== '') {
    $letterEsc = $mysqli->real_escape_string($filters['letter']);
    $where[] = "p.name LIKE '{$letterEsc}%'";
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM pages p ';
$countRes = sql_query('SELECT COUNT(*) AS c' . $fromSql . ' WHERE ' . $whereSql);
$countRow = mysqli_fetch_assoc($countRes);
$total = (int)($countRow['c'] ?? 0);

$queryBase = 'persons.php?' . http_build_query(array_filter($filters, static function($v) {
    return !($v === '' || $v === 0 || $v === '0');
}));

list($pagertop, $pagerbottom, $limit) = pager2(40, $total, $queryBase === 'persons.php?' ? 'persons.php?' : $queryBase . '&');

$orderBy = 'p.sort_name ASC, p.id DESC';
if ($filters['mode'] === 'new') {
    $orderBy = 'p.created_at DESC, p.id DESC';
} elseif ($filters['mode'] === 'updated') {
    $orderBy = 'p.updated_at DESC, p.id DESC';
}

$res = sql_query("
    SELECT
        p.id,
        p.name,
        p.img,
        p.date,
        p.birthdate,
        p.created_at,
        p.updated_at,
        p.sort_name,
        p.primary_profession,
        p.views
    {$fromSql}
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    {$limit}
");

$cards = [];
while ($row = mysqli_fetch_assoc($res)) {
    $professionLabel = $professionOptions[$row['primary_profession'] ?? ''] ?? '';
    $row['profession_label'] = $professionLabel;
    $cards[] = persons_render_card($row, $placeholder);
}

$counts = persons_fetch_mode_counts();

$pageTitle = 'Персоны';
if ($filters['mode'] === 'birthday_today') {
    $pageTitle = 'Сегодня день рождения у ' . $total . ' персон';
} elseif ($filters['mode'] === 'new') {
    $pageTitle = 'Новые персоны';
} elseif ($filters['mode'] === 'updated') {
    $pageTitle = 'Недавно измененные персоны';
} elseif ($filters['q'] !== '') {
    $pageTitle = 'Поиск персон: ' . h($filters['q']);
}

$contentHtml = '<div class="persons-heading"><div><h1 class="persons-heading__title">' . $pageTitle . '</h1><div class="persons-heading__sub">Найдено: ' . number_format($total) . '</div></div>';
if (get_user_class() >= UC_POWER_USER) {
    $contentHtml .= '<div class="persons-tools"><a href="persons.php?add">Добавить персону</a></div>';
}
$contentHtml .= '</div>';
$showPager = $total > 40;
$contentHtml .= $showPager && $pagertop ? '<div class="index">' . $pagertop . '</div>' : '';
if ($cards) {
    $contentHtml .= '<div id="persons-grid" class="persons-grid">' . implode('', $cards) . '</div>';
}
$contentHtml .= $showPager && $pagerbottom ? '<div class="index">' . $pagerbottom . '</div>' : '';
if (!$cards) {
    $contentHtml .= '<div class="persons-empty">По заданным фильтрам ничего не найдено.</div>';
}

if ($ajax) {
    echo $contentHtml;
    exit;
}

stdhead('Персоны');
echo <<<CSS
<style>
.persons-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:14px}
.persons-sidebar{display:flex;flex-direction:column;gap:12px}
.persons-panel{padding:10px;border:1px solid rgba(127,127,127,.28);background:transparent;color:inherit}
.persons-panel__title{margin-bottom:8px;padding:0 0 6px;border-bottom:1px solid rgba(127,127,127,.22);font-size:14px;font-weight:700;color:inherit}
.persons-info{font-size:12px;line-height:1.45;color:inherit;opacity:.88}
.persons-link{display:flex;justify-content:space-between;padding:4px 0;text-decoration:none;color:inherit}
.persons-link.is-active{font-weight:700;text-decoration:underline}
.persons-letters{display:grid;grid-template-columns:repeat(6,1fr);gap:4px}
.persons-letter{display:flex;align-items:center;justify-content:center;min-height:28px;border:1px solid rgba(127,127,127,.3);background:transparent;text-decoration:none;color:inherit;font-weight:700}
.persons-letter.is-active{font-weight:800;border-color:currentColor;background:rgba(127,127,127,.08)}
.persons-field{margin-bottom:10px}
.persons-field label{display:block;margin-bottom:5px;font-weight:700;color:inherit}
.persons-field input[type=text],.persons-field select{width:100%;padding:8px 10px;border:1px solid rgba(127,127,127,.35);background:#fff;box-sizing:border-box;font:inherit;color:inherit}
.persons-bday{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px}
.persons-actions button{width:100%;padding:9px 12px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer}
.persons-main{min-width:0}
.persons-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 10px}
.persons-heading__title{margin:0;font-size:22px;line-height:1.15;font-weight:800;color:inherit}
.persons-heading__sub{font-size:13px;color:inherit;opacity:.72}
.persons-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.person-card{display:block;overflow:hidden;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit;text-decoration:none}
.person-card:hover{border-color:rgba(127,127,127,.45)}
.person-card__img{display:block;width:100%;aspect-ratio:3/4;object-fit:cover;background:rgba(127,127,127,.08)}
.person-card__name{padding:7px 8px 2px;font-size:14px;line-height:1.25;font-weight:700;color:inherit}
.person-card__meta,.person-card__date{padding:0 8px 6px;font-size:12px;color:inherit;opacity:.74}
.persons-tools{display:flex;gap:8px;flex-wrap:wrap}
.persons-tools a{display:inline-flex;align-items:center;padding:6px 10px;border:1px solid rgba(127,127,127,.28);background:transparent;text-decoration:none;color:inherit;font-weight:700}
.persons-empty{padding:16px;border:1px dashed rgba(127,127,127,.28);background:transparent;color:inherit;opacity:.8}
@media (max-width: 980px){.persons-layout{grid-template-columns:1fr}}
</style>
CSS;

begin_frame('Персоны');
echo '<div class="persons-layout">';
echo persons_render_sidebar($filters, $counts, $professionOptions, $letters);
echo '<div class="persons-main"><div id="persons-list">' . $contentHtml . '</div></div>';
echo '</div>';
end_frame();
stdfoot();
