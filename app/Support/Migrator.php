<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Автоматическое обновление базы данных при обновлении кода: сайт, установленный до этой версии,
 * сам создаёт недостающие таблицы при первом запросе — вручную ничего импортировать не нужно.
 */
final class Migrator
{
    public const VERSION = 4;

    public const MENU_DDL = "CREATE TABLE IF NOT EXISTS `menu_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    public const BLOCKS_DDL = "CREATE TABLE IF NOT EXISTS `link_blocks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    public const SECTIONS_DDL = "CREATE TABLE IF NOT EXISTS `section_pages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /** Страницы разделов: ключ => [название, адрес]. Пустые поля = текст по умолчанию из шаблона. */
    public const SECTIONS = [
        'home' => ['Главная страница', '/'], 'news' => ['Новости', '/news'], 'afisha' => ['Афиша', '/afisha'],
        'kino' => ['Кино', '/kino'], 'pogoda' => ['Погода', '/pogoda'], 'radio' => ['Радио', '/radio'],
    ];

    /** Текст страницы «Кино» по умолчанию (раньше был зашит в шаблон: условия возврата и памятка о Пушкинской карте). */
    public const KINO_BODY = <<<'NOWDOC'
<h2>Возврат билетов</h2>
<p>Билеты, приобретённые онлайн, можно вернуть не позже чем за 1 час до начала сеанса.</p>
<h2>Профилактика нарушений с Пушкинской картой</h2>
<p>С целью предотвращения махинаций при приобретении билетов по программе «Пушкинская карта» контролёр при посещении вами кинотеатра имеет право попросить вас предъявить документ, удостоверяющий личность.</p>
<p>Основанием является пункт 8 «Правил реализации программы «Пушкинская карта»»: организации культуры в рамках реализации программы подтверждают личность гражданина, предъявившего билет, в том числе путём сравнения с основным документом, удостоверяющим личность гражданина Российской Федерации.</p>
<p>Законность требования контролёра предъявить документ, удостоверяющий личность, в целях сверки соответствия владельца билета данным владельца «Пушкинской карты» подтверждена пунктом 27 правил Программы.</p>
<p>Пункт 29 правил Программы: билет даёт право на посещение мероприятия только гражданину, купившему билет, и не может быть передан третьим лицам.</p>
NOWDOC;

    /** Блоки ссылок «по умолчанию»: [ключ, заголовок, где показывать, порядок]. */
    public const DEFAULT_BLOCKS = [
        ['gosuslugi', 'Госуслуги для граждан', 'home', 10],
        ['delivery', 'Доставка продуктов и еды (справочно)', 'home', 20],
        ['social', 'Мы в соцсетях', 'social', 30],
        ['useful', 'Полезные ссылки', 'footer', 40],
        ['orgs', 'Сайты организаций', 'footer', 50],
    ];

    /** Пункты меню «по умолчанию»: разделы сайта и страница «О редакции». */
    public const DEFAULT_MENU = [
        ['news', 'system', 'Новости', '/news', 10],
        ['afisha', 'system', 'Афиша', '/afisha', 20],
        ['kino', 'system', 'Кино', '/kino', 30],
        ['pogoda', 'system', 'Погода', '/pogoda', 40],
        ['radio', 'system', 'Радио', '/radio', 50],
        ['about', 'page', 'О редакции', '/about', 60],
    ];

    public static function run(): void
    {
        try {
            if ((int) Settings::get('schema_ver', 0) >= self::VERSION) {
                return;
            }
            DB::pdo()->exec(self::MENU_DDL);
            if ((int) DB::val('SELECT COUNT(*) FROM menu_items') === 0) {
                foreach (self::DEFAULT_MENU as [$key, $kind, $title, $url, $sort]) {
                    DB::exec('INSERT IGNORE INTO menu_items (sys_key, kind, title, url, sort_order) VALUES (?,?,?,?,?)', [$key, $kind, $title, $url, $sort]);
                }
            }
            DB::pdo()->exec(self::BLOCKS_DDL);
            if ((int) DB::val('SELECT COUNT(*) FROM link_blocks') === 0) {
                foreach (self::DEFAULT_BLOCKS as [$key, $title, $place, $sort]) {
                    DB::exec('INSERT IGNORE INTO link_blocks (group_key, title, placement, sort_order) VALUES (?,?,?,?)', [$key, $title, $place, $sort]);
                }
            }
            // ссылки с «чужими» ключами групп (добавленными вручную) не должны пропасть — для них создаётся блок
            foreach (DB::col('SELECT DISTINCT l.group_key FROM links l LEFT JOIN link_blocks b ON b.group_key = l.group_key WHERE b.id IS NULL') as $k) {
                DB::exec('INSERT IGNORE INTO link_blocks (group_key, title, placement, sort_order) VALUES (?,?,?,?)', [$k, $k, 'footer', 90]);
            }
            DB::pdo()->exec(self::SECTIONS_DDL);
            if ((int) DB::val('SELECT COUNT(*) FROM section_pages') === 0) {
                foreach (array_keys(self::SECTIONS) as $key) {
                    DB::exec('INSERT IGNORE INTO section_pages (sys_key, body, updated_at) VALUES (?, ?, NOW())', [$key, $key === 'kino' ? self::KINO_BODY : '']);
                }
            }
            DB::exec("INSERT IGNORE INTO settings (k, v) VALUES ('footer_note', 'Независимое издание: без рекламы, без cookie, без сбора данных о читателях.')");
            Settings::set('schema_ver', (string) self::VERSION);
            Settings::touch();
        } catch (\Throwable $e) {
            Logger::write('error', 'migrate: ' . $e->getMessage());
        }
    }
}
