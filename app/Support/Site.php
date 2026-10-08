<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Settings;

/** Данные «обвязки» сайта (меню, подвал). Кэшируются и сбрасываются при любом изменении контента. */
final class Site
{
    public static function chrome(): array
    {
        return Cache::remember('chrome_' . Settings::contentVersion(), 600, static fn() => [
            'categories' => array_values(array_filter(Repo::categories(), fn($c) => $c['cnt'] > 0)),
            'social_blocks' => Repo::blocks('social'),
            'footer_blocks' => Repo::blocks('footer'),
            'pages' => Repo::footerPages(),
        ]);
    }

    /**
     * Меню сайта из таблицы menu_items (кэшируется до любой правки контента).
     * Если таблицы ещё нет (сайт только обновили) — берётся набор по умолчанию.
     * @return array{header:array,footer:array,off:string[]}
     */
    public static function menu(): array
    {
        return Cache::remember('menu_' . Settings::contentVersion(), 600, static function (): array {
            try {
                $rows = DB::all('SELECT * FROM menu_items ORDER BY sort_order, id');
                $pages = array_flip(DB::col("SELECT slug FROM pages WHERE status = 'published'"));
            } catch (\Throwable) {
                $rows = [];
                $pages = [];
                foreach (Migrator::DEFAULT_MENU as $i => [$key, $kind, $title, $url, $sort]) {
                    $rows[] = ['sys_key' => $key, 'kind' => $kind, 'title' => $title, 'url' => $url, 'is_active' => 1, 'in_header' => 1, 'in_footer' => 1, 'new_tab' => 0];
                }
                $pages = array_flip(['about']);
            }
            $out = ['header' => [], 'footer' => [], 'off' => []];
            foreach ($rows as $r) {
                $pageGone = $r['kind'] === 'page' && !isset($pages[ltrim((string) $r['url'], '/')]);
                if (!(int) $r['is_active']) {
                    if ($r['kind'] !== 'link') {
                        $out['off'][] = (string) $r['url']; // отключённый раздел или страница: адрес перестаёт открываться
                    }
                    continue;
                }
                if ($pageGone) {
                    continue; // страница удалена или стала черновиком — пункт не показываем
                }
                $item = ['href' => (string) $r['url'], 'label' => (string) $r['title'], 'new_tab' => (bool) $r['new_tab'], 'key' => (string) ($r['sys_key'] ?? '')];
                if ((int) $r['in_header']) {
                    $out['header'][] = $item;
                }
                if ((int) $r['in_footer']) {
                    $out['footer'][] = $item;
                }
            }
            return $out;
        });
    }

    /** Тексты страниц разделов, правятся в админке («Страницы разделов»). @return array<string,array<string,mixed>> */
    public static function sections(): array
    {
        return Cache::remember('sections_' . Settings::contentVersion(), 600, static function (): array {
            try {
                $out = [];
                foreach (DB::all('SELECT * FROM section_pages') as $r) {
                    $r['updated_ts'] = (int) strtotime((string) $r['updated_at']);
                    $out[$r['sys_key']] = $r;
                }
                return $out;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** Страница раздела по адресу (/, /news, /afisha, /kino, /pogoda, /radio) или пустой массив. */
    public static function sectionForPath(string $path): array
    {
        foreach (Migrator::SECTIONS as $key => [, $url]) {
            if ($url === $path) {
                return self::sections()[$key] ?? [];
            }
        }
        return [];
    }

    /** Раздел включён? Ключи: news, afisha, kino, pogoda, radio. Отключённый раздел не показывается и отдаёт 404. */
    public static function enabled(string $key): bool
    {
        $m = Migrator::DEFAULT_MENU;
        foreach ($m as [$k, , , $url]) {
            if ($k === $key) {
                return !in_array($url, self::menu()['off'], true);
            }
        }
        return true;
    }

    /** Адрес принадлежит отключённому разделу/странице? (/news отключён → и /news/любая-статья недоступна). */
    public static function isDisabled(string $path): bool
    {
        foreach (self::menu()['off'] as $u) {
            if ($u !== '' && $u[0] === '/' && ($path === $u || str_starts_with($path, $u . '/'))) {
                return true;
            }
        }
        return false;
    }

    /** Подстановки в страницах: [[contact_email]], [[contact_email_link]], [[contact_phone]], [[site_name]], [[domain]]… */
    public static function fillPlaceholders(string $html): string
    {
        $map = [];
        foreach (['contact_email', 'contact_phone', 'org_name', 'site_name'] as $k) {
            $v = (string) Settings::get($k, '');
            $map['[[' . $k . ']]'] = $v !== '' ? e($v) : '<mark class="todo">[укажите в «Настройки → Редакция»]</mark>';
        }
        $mail = (string) Settings::get('contact_email', '');
        $map['[[contact_email_link]]'] = $mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)
            ? '<a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '<mark class="todo">[укажите e-mail в «Настройки → Редакция»]</mark>';
        $map['[[city]]'] = e(city());
        $map['[[city_in]]'] = e(city_in());
        $map['[[city_of]]'] = e(city_of());
        $map['[[region]]'] = e((string) Settings::get('region_name', ''));
        $map['[[city_about]]'] = e((string) Settings::get('footer_about', '')) ?: e(city());
        $map['[[domain]]'] = e(\App\Core\Request::baseUrlHost());
        $map['[[site_url]]'] = e(\App\Core\Request::baseUrl());
        return strtr($html, $map);
    }
}
