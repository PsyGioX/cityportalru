<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Settings;
use App\Support\Repo;
use App\Support\Seo;

final class HomeController extends FrontController
{
    public function index(): void
    {
        $count = max(6, (int) Settings::get('home_news_count', 12));
        $lead = Repo::news(1, 0, 'n.is_featured = 1', [], 'n.published_at DESC')[0] ?? null;
        $skip = $lead ? 'n.id <> ' . (int) $lead['id'] : '1=1';
        $lead ??= Repo::news(1)[0] ?? null;
        $skip = $lead ? 'n.id <> ' . (int) $lead['id'] : '1=1';
        $rest = Repo::news($count + 4, 0, $skip, [], 'n.published_at DESC');
        $side = array_slice($rest, 0, 4);
        $grid = array_slice($rest, 4);
        $site = Seo::siteName();
        $base = Request::baseUrl();
        $cover = $lead ? Repo::cover($lead) : null;
        $this->render('front/home', [
            'lead' => $lead, 'side' => $side, 'grid' => $grid,
            'events' => Repo::upcomingEvents(3),
            'blocks' => Repo::blocks('home'),
        ], [
            'title_full' => $site . ' — новости и афиша ' . city_of(),
            'og_title' => $site . ' — новости и афиша ' . city_of(),
            'description' => (string) Settings::get('site_description', ''),
            'path' => '/',
            'image' => $cover ? $base . media_url($cover) : null,
            'jsonld' => [Seo::organization(), Seo::website()],
        ] + ($cover ? [] : []), Repo::lastModified());
    }
}
