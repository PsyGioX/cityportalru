<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\App;
use App\Core\DB;
use App\Core\Paginator;
use App\Core\Request;
use App\Support\Repo;
use App\Support\Seo;

final class EventsController extends FrontController
{
    private const SEL = 'e.*, m.path AS m_path, m.ext AS m_ext, m.width AS m_width, m.height AS m_height, m.widths AS m_widths, m.alt AS m_alt';

    public static function cover(array $e): ?array
    {
        return empty($e['m_path']) ? null : ['path' => $e['m_path'], 'ext' => $e['m_ext'], 'width' => $e['m_width'], 'height' => $e['m_height'], 'widths' => $e['m_widths'], 'alt' => $e['m_alt'] ?: $e['title']];
    }

    private const PER_PAGE = 12;

    public function index(): void
    {
        $archive = Request::int('past', 0, 'get') === 1;
        $cmp = $archive ? '<' : '>=';
        $total = (int) DB::val("SELECT COUNT(*) FROM events e WHERE e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) $cmp NOW()");
        $p = new Paginator($total, self::PER_PAGE, Request::page());
        if (Request::page() > $p->pages && Request::page() > 1) {
            App::notFound();
        }
        $events = DB::all("SELECT " . self::SEL . " FROM events e LEFT JOIN media m ON m.id = e.cover_media_id WHERE e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) $cmp NOW() ORDER BY e.starts_at " . ($archive ? 'DESC' : 'ASC') . " LIMIT " . self::PER_PAGE . " OFFSET {$p->offset}");
        // на первой странице предстоящих — короткий список недавних событий со ссылкой на архив
        $past = [];
        $pastTotal = 0;
        if (!$archive && $p->page === 1) {
            $past = DB::all("SELECT " . self::SEL . " FROM events e LEFT JOIN media m ON m.id = e.cover_media_id WHERE e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) < NOW() ORDER BY e.starts_at DESC LIMIT 6");
            $pastTotal = (int) DB::val("SELECT COUNT(*) FROM events e WHERE e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) < NOW()");
        }
        $base = Request::baseUrl();
        $qs = [];
        if ($archive) {
            $qs['past'] = 1;
        }
        if ($p->page > 1) {
            $qs['page'] = $p->page;
        }
        $title = ($archive ? 'Архив афиши ' : 'Афиша ') . city_of() . ($p->page > 1 ? ' — страница ' . $p->page : '');
        $offset = $p->offset;
        $ld = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => array_map(fn($e, $i) => ['@type' => 'ListItem', 'position' => $offset + $i + 1, 'url' => $base . '/afisha/' . $e['slug']], $events, array_keys($events))];
        $this->render('front/events', ['events' => $events, 'past' => $past, 'pastTotal' => $pastTotal, 'archive' => $archive, 'p' => $p], [
            'title' => $title, 'description' => 'Афиша событий ' . city_of() . ': концерты, праздники, выставки, спектакли и мероприятия для всей семьи.',
            'path' => '/afisha', 'canonical' => $base . '/afisha' . ($qs ? '?' . http_build_query($qs) : ''),
            'jsonld' => $events ? [$ld] : [], 'breadcrumbs' => $archive ? [['Главная', '/'], ['Афиша', '/afisha'], ['Архив', null]] : [['Главная', '/'], ['Афиша', null]],
            'prev' => $p->page > 1 ? $base . $p->url($p->page - 1, '/afisha') : null, 'next' => $p->page < $p->pages ? $base . $p->url($p->page + 1, '/afisha') : null,
        ], DB::val("SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM events") ? (int) DB::val("SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM events") : 1);
    }

    public function show(array $a): void
    {
        $e = DB::one("SELECT " . self::SEL . " FROM events e LEFT JOIN media m ON m.id = e.cover_media_id WHERE e.slug = ? AND e.status = 'published'", [$a['slug']]);
        if (!$e) {
            App::notFound();
        }
        $base = Request::baseUrl();
        $cover = self::cover($e);
        $past = strtotime((string) ($e['ends_at'] ?: $e['starts_at'])) < time();
        $desc = $e['seo_description'] ?: excerpt((string) $e['description'], 170);
        $ld = array_filter([
            '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $e['title'], 'description' => $desc,
            'startDate' => date('c', strtotime((string) $e['starts_at'])), 'endDate' => $e['ends_at'] ? date('c', strtotime((string) $e['ends_at'])) : null,
            'eventStatus' => 'https://schema.org/EventScheduled', 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => ['@type' => 'Place', 'name' => $e['place'] ?: city(), 'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $e['address'], 'addressLocality' => city(), 'addressRegion' => (string) \App\Core\Settings::get('region_name', ''), 'addressCountry' => 'RU'])],
            'image' => $cover ? [$base . media_url($cover)] : null,
            'offers' => $e['ticket_url'] ? ['@type' => 'Offer', 'url' => $e['ticket_url'], 'price' => preg_match('/\d+/', $e['price'], $mm) ? $mm[0] : '0', 'priceCurrency' => 'RUB', 'availability' => 'https://schema.org/InStock'] : null,
            'organizer' => ['@id' => $base . '/#org'],
        ]);
        $this->render('front/event-show', ['e' => $e, 'cover' => $cover, 'past' => $past], [
            'title' => $e['seo_title'] ?: $e['title'] . ' — афиша ' . city_of(), 'og_title' => $e['title'], 'description' => $desc, 'path' => '/afisha/' . $e['slug'],
            'image' => $cover ? $base . media_url($cover) : null, 'jsonld' => [$ld],
            'breadcrumbs' => [['Главная', '/'], ['Афиша', '/afisha'], [str_limit($e['title'], 60), null]],
        ], (int) strtotime((string) $e['updated_at']));
    }
}
