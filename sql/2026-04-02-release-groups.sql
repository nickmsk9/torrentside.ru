CREATE TABLE IF NOT EXISTS torrent_bookmarks (
  user_id INT UNSIGNED NOT NULL,
  torrent_id INT UNSIGNED NOT NULL,
  added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, torrent_id),
  KEY idx_torrent_id (torrent_id),
  KEY idx_added (added)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  description TEXT NULL,
  image VARCHAR(255) NOT NULL DEFAULT '',
  creator_id INT UNSIGNED NOT NULL DEFAULT 0,
  group_type VARCHAR(64) NOT NULL DEFAULT 'Релиз-группы',
  category_name VARCHAR(64) NOT NULL DEFAULT 'Общие интересы',
  subcategory_name VARCHAR(64) NOT NULL DEFAULT 'Другое',
  access_mode ENUM('open','closed') NOT NULL DEFAULT 'closed',
  added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_release_group_name (name),
  KEY idx_creator_id (creator_id),
  KEY idx_access_mode (access_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_group_members (
  group_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('owner','manager','member') NOT NULL DEFAULT 'member',
  added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  KEY idx_user_id (user_id),
  KEY idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_group_bookmarks (
  user_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, group_id),
  KEY idx_group_id (group_id),
  KEY idx_added (added)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_group_wall (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  text TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_group_added (group_id, added),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE torrents
  ADD COLUMN IF NOT EXISTS release_group_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER owner,
  ADD KEY idx_release_group_id (release_group_id);
