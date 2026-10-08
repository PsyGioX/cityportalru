<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/** Общие выборки для публичной части и генераторов SEO-файлов. */
final class Repo
{
    public const LIVE = "n.status = 'published' AND n.published_at <= NOW()";
    private const SEL = 'n.*, c.name AS cat_name, c.slug AS cat_slug, m.path AS m_path, m.ext AS m_ext, m.width AS m_width, m.height AS m_height, m.widths AS m_widths, m.alt AS m_alt, m.caption AS m_caption, m.credit AS m_credit';
    private const JOIN = 'FROM news n LEFT JOIN categories c ON c.id = n.category_id LEFT JOIN media m ON m.id = n.cover_media_id';

    public static function cover(array $row): ?array
    {
        if (empty($row['m_path'])) {
            return null;
        }
        return ['path' => $row['m_path'], 'ext' => $row['m_ext'], 'width' => $row['m_width'], 'height' => $row['m_height'],
            'widths' => $row['m_widths'], 'alt' => $row['m_alt'] !== '' ? $row['m_alt'] : $row['title'], 'caption' => $row['m_caption'] ?? '', 'credit' => $row['m_credit'] ?? ''];
    }

    public static function news(int $limit = 12, int $offset = 0, string $where = '1=1', array $params = [], string $order = 'n.is_pinned DESC, n.published_at DESC'): array
    {
        return DB::all('SELECT ' . self::SEL . ' ' . self::JOIN . ' WHERE ' . self::LIVE . " AND $where ORDER BY $order LIMIT $limit OFFSET $offset", $params);
    }

    public static function countNews(string $where = '1=1', array $params = []): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM news n WHERE ' . self::LIVE . " AND $where", $params);
    }

    public static function newsBySlug(string $slug): ?array
    {
        return DB::one('SELECT ' . self::SEL . ' ' . self::JOIN . ' WHERE n.slug = ? AND ' . self::LIVE, [$slug]);
    }

    public static function newsById(int $id): ?array
    {
        return DB::one('SELECT ' . self::SEL . ' ' . self::JOIN . ' WHERE n.id = ? AND ' . self::LIVE, [$id]);
    }

    public static function gallery(int $newsId): array
    {
        return DB::all('SELECT m.* FROM news_media nm JOIN media m ON m.id = nm.media_id WHERE nm.news_id = ? ORDER BY nm.sort_order, m.id', [$newsId]);
    }

    public static function tags(int $newsId): array
    {
        return DB::all('SELECT t.* FROM news_tags nt JOIN tags t ON t.id = nt.tag_id WHERE nt.news_id = ? ORDER BY t.name', [$newsId]);
    }

    public static function related(array $n, int $limit = 4): array
    {
        return self::news($limit, 0, 'n.id <> ? AND (n.category_id <=> ? OR n.id IN (SELECT nt2.news_id FROM news_tags nt2 WHERE nt2.tag_id IN (SELECT tag_id FROM news_tags WHERE news_id = ?)))',
            [$n['id'], $n['category_id'], $n['id']], 'n.published_at DESC');
    }

    public static function categories(): array
    {
        return DB::all('SELECT c.*, (SELECT COUNT(*) FROM news n WHERE n.category_id = c.id AND ' . self::LIVE . ') AS cnt FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name');
    }

    /** Активные ссылки одной группы; ссылки отключённого блока не возвращаются. */
    public static function links(string $group): array
    {
        try {
            return DB::all('SELECT l.* FROM links l JOIN link_blocks b ON b.group_key = l.group_key AND b.is_active = 1
                WHERE l.group_key = ? AND l.is_active = 1 ORDER BY l.sort_order, l.id', [$group]);
        } catch (\Throwable) { // таблица блоков ещё не создана (сайт только обновили)
            return DB::all('SELECT * FROM links WHERE group_key = ? AND is_active = 1 ORDER BY sort_order, id', [$group]);
        }
    }

    /**
     * Включённые блоки ссылок для места показа (home | footer | social) вместе с их активными ссылками.
     * Пустые блоки не возвращаются. Блоки и их порядок редактируются в админке («Сайт → Блоки на сайте»).
     * @return array<int,array<string,mixed>>
     */
    public static function blocks(string $placement): array
    {
        try {
            $blocks = DB::all('SELECT * FROM link_blocks WHERE is_active = 1 AND placement = ? ORDER BY sort_order, id', [$placement]);
        } catch (\Throwable) {
            $map = ['home' => ['gosuslugi' => 'Госуслуги для граждан', 'delivery' => 'Доставка продуктов и еды (справочно)'], 'footer' => ['useful' => 'Полезные ссылки', 'orgs' => 'Сайты организаций'], 'social' => ['social' => 'Мы в соцсетях']];
            $blocks = [];
            foreach ($map[$placement] ?? [] as $k => $t) {
                $blocks[] = ['id' => $k, 'group_key' => $k, 'title' => $t, 'subtitle' => ''];
            }
        }
        foreach ($blocks as $i => &$b) {
            $b['links'] = self::links((string) $b['group_key']);
            if (!$b['links']) {
                unset($blocks[$i]);
            }
        }
        unset($b);
        return array_values($blocks);
    }

    /** Все активные ссылки блоков-«соцсетей» (для разметки sameAs). */
    public static function socialLinks(): array
    {
        return array_merge(...array_map(fn($b) => $b['links'], self::blocks('social')) ?: [[]]);
    }

    public static function upcomingEvents(int $limit = 6): array
    {
        return DB::all("SELECT e.*, m.path AS m_path, m.ext AS m_ext, m.width AS m_width, m.height AS m_height, m.widths AS m_widths, m.alt AS m_alt
            FROM events e LEFT JOIN media m ON m.id = e.cover_media_id
            WHERE e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() ORDER BY e.starts_at LIMIT $limit");
    }

    public static function footerPages(): array
    {
        return DB::all("SELECT slug, title FROM pages WHERE status = 'published' AND show_in_footer = 1 ORDER BY sort_order, title");
    }

    /** Время последнего изменения публичного контента (для 304-ответов). */
    public static function lastModified(): int
    {
        $t = DB::val('SELECT MAX(GREATEST(n.updated_at, COALESCE(n.published_at, n.updated_at))) FROM news n WHERE ' . self::LIVE);
        return max($t ? (int) strtotime((string) $t) : 0, \App\Core\Settings::contentVersion());
    }
}
