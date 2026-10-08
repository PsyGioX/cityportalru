-- CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
-- Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
-- Project: https://github.com/PsyGioX/cityportalru

-- «Твой Кореновск» — схема базы данных. В БД нет таблиц с данными читателей: только контент и учётные записи редакции.
-- MySQL 5.7+ / 8.x / MariaDB 10.3+, кодировка utf8mb4.
-- Можно импортировать через phpMyAdmin (вкладка «Импорт») ИЛИ выполнить установщиком /install.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor','author') NOT NULL DEFAULT 'author',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `totp_secret` VARCHAR(255) NULL,
  `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `totp_last_step` BIGINT NULL,
  `recovery_codes` TEXT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `password_changed_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Идентификатор сессии хранится в виде SHA-256, чтобы утечка таблицы не позволяла угнать сессию.
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` CHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `payload` MEDIUMTEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(190) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_la_ip` (`ip`,`created_at`),
  KEY `idx_la_user` (`username`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `k` CHAR(64) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(60) NOT NULL DEFAULT '',
  `action` VARCHAR(60) NOT NULL,
  `entity` VARCHAR(40) NOT NULL DEFAULT '',
  `entity_id` VARCHAR(40) NOT NULL DEFAULT '',
  `details` TEXT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `seo_title` VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(190) NOT NULL,
  `ext` VARCHAR(8) NOT NULL,
  `width` INT UNSIGNED NOT NULL DEFAULT 0,
  `height` INT UNSIGNED NOT NULL DEFAULT 0,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `alt` VARCHAR(255) NOT NULL DEFAULT '',
  `caption` VARCHAR(500) NOT NULL DEFAULT '',
  `credit` VARCHAR(190) NOT NULL DEFAULT '',
  `widths` VARCHAR(120) NOT NULL DEFAULT '[]',
  `file_hash` CHAR(40) NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_created` (`created_at`),
  KEY `idx_media_hash` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `author_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `excerpt` TEXT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `body_text` MEDIUMTEXT NULL,
  `cover_media_id` INT UNSIGNED NULL,
  `status` ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `seo_title` VARCHAR(190) NULL,
  `seo_description` VARCHAR(320) NULL,
  `canonical_url` VARCHAR(500) NULL,
  `noindex` TINYINT(1) NOT NULL DEFAULT 0,
  `source_name` VARCHAR(190) NULL,
  `source_url` VARCHAR(500) NULL,
  `indexnow_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_news_slug` (`slug`),
  KEY `idx_news_status_pub` (`status`,`published_at`),
  KEY `idx_news_cat_pub` (`category_id`,`published_at`),
  KEY `idx_news_featured` (`is_featured`),
  CONSTRAINT `fk_news_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_news_cover` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news_media` (
  `news_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`news_id`,`media_id`),
  CONSTRAINT `fk_nm_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nm_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_slug` (`slug`),
  UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news_tags` (
  `news_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`news_id`,`tag_id`),
  KEY `idx_nt_tag` (`tag_id`),
  CONSTRAINT `fk_nt_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `description` MEDIUMTEXT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NULL,
  `place` VARCHAR(190) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `price` VARCHAR(100) NOT NULL DEFAULT '',
  `ticket_url` VARCHAR(500) NOT NULL DEFAULT '',
  `cover_media_id` INT UNSIGNED NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `seo_title` VARCHAR(190) NULL,
  `seo_description` VARCHAR(320) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_events_status_start` (`status`,`starts_at`),
  CONSTRAINT `fk_events_cover` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_key` VARCHAR(30) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `url` VARCHAR(500) NOT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `new_tab` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_links_group` (`group_key`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `seo_title` VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `noindex` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'published',
  `show_in_footer` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_path` VARCHAR(255) NOT NULL,
  `to_url` VARCHAR(500) NOT NULL,
  `code` SMALLINT NOT NULL DEFAULT 301,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `last_hit_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirects_from` (`from_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `not_found_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(255) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `is_ignored` TINYINT(1) NOT NULL DEFAULT 0,
  `first_seen_at` DATETIME NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nf_path` (`path`),
  KEY `idx_nf_hits` (`hits`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL,
  `v` MEDIUMTEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stats_daily` (
  `day` DATE NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `indexnow_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(500) NOT NULL,
  `service` VARCHAR(60) NOT NULL,
  `http_code` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inlog_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- Меню сайта (разделы в шапке и подвале): включение/отключение, порядок, свои ссылки
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sys_key` VARCHAR(30) NULL,
  `kind` ENUM('system','page','link') NOT NULL DEFAULT 'link',
  `title` VARCHAR(100) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `in_header` TINYINT(1) NOT NULL DEFAULT 1,
  `in_footer` TINYINT(1) NOT NULL DEFAULT 1,
  `new_tab` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menu_sys` (`sys_key`),
  KEY `idx_menu_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Блоки ссылок: заголовки, место показа (главная / подвал / соцсети), порядок, отключение
CREATE TABLE IF NOT EXISTS `link_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_key` VARCHAR(40) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `placement` ENUM('home','footer','social') NOT NULL DEFAULT 'home',
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block_key` (`group_key`),
  KEY `idx_block_sort` (`placement`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Редактируемые страницы разделов (Главная, Новости, Афиша, Кино, Погода, Радио)
CREATE TABLE IF NOT EXISTS `section_pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sys_key` VARCHAR(20) NOT NULL,
  `h1` VARCHAR(190) NOT NULL DEFAULT '',
  `intro` VARCHAR(600) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NULL,
  `seo_title` VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_section_key` (`sys_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
