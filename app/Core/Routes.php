<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

use App\Controllers\Admin as A;
use App\Controllers\Front as F;

final class Routes
{
    public static function register(Router $r): void
    {
        // ---------- публичная часть
        $r->get('/', [F\HomeController::class, 'index']);
        $r->get('/news', [F\NewsController::class, 'index']);
        $r->get('/news/{slug}', [F\NewsController::class, 'show']);
        $r->get('/category/{slug}', [F\NewsController::class, 'category']);
        $r->get('/tag/{slug}', [F\NewsController::class, 'tag']);
        $r->get('/search', [F\NewsController::class, 'search']);
        $r->get('/afisha', [F\EventsController::class, 'index']);
        $r->get('/afisha/{slug}', [F\EventsController::class, 'show']);
        $r->get('/kino', [F\PagesController::class, 'cinema']);
        $r->get('/radio', [F\PagesController::class, 'radio']);
        $r->get('/pogoda', [F\PagesController::class, 'weather']);
        $r->get('/og/news/{id}.jpg', [F\ServiceController::class, 'og']);
        $r->get('/api/weather', [F\ServiceController::class, 'weather']);
        $r->get('/brand.css', [F\ServiceController::class, 'brandCss']);
        // SEO-файлы (если статической копии нет — отдаём динамически)
        $r->get('/{file:[A-Za-z0-9_.\-]+\.(?:xml|txt|webmanifest)}', [F\ServiceController::class, 'seoFile']);
        $r->get('/.well-known/security.txt', [F\ServiceController::class, 'securityTxt']);
        $r->get('/{file:(?:yandex_[a-f0-9]+|google[a-f0-9]+)\.html}', [F\ServiceController::class, 'verification']);
        // старые адреса статического сайта → 301
        $r->get('/index.html', fn() => Response::redirect('/', 301));
        $r->get('/advertising.html', fn() => Response::redirect('/', 301));
        $r->get('/advertisements.html', fn() => Response::redirect('/', 301));
        $r->get('/news_all.html', fn() => Response::redirect('/news', 301));
        $r->get('/afisha.html', fn() => Response::redirect('/afisha', 301));
        $r->get('/tickets_in_cinema.html', fn() => Response::redirect('/kino', 301));
        $r->get('/pages_news/{id}.html', [F\NewsController::class, 'legacy']);

        // ---------- админ-панель
        $p = '/' . admin_path();
        $r->any($p . '/login', [A\AuthController::class, 'login']);
        $r->any($p . '/2fa', [A\AuthController::class, 'twoFactor']);
        $r->post($p . '/logout', [A\AuthController::class, 'logout']);
        $r->get($p, [A\DashboardController::class, 'index']);

        $r->get($p . '/news', [A\NewsController::class, 'index']);
        $r->post($p . '/news/bulk', [A\NewsController::class, 'bulk']);
        $r->any($p . '/news/new', [A\NewsController::class, 'edit']);
        $r->any($p . '/news/{id}', [A\NewsController::class, 'edit']);
        $r->post($p . '/news/{id}/delete', [A\NewsController::class, 'delete']);
        $r->get($p . '/news/{id}/preview', [A\NewsController::class, 'preview']);

        $r->get($p . '/events', [A\EventsController::class, 'index']);
        $r->post($p . '/events/bulk', [A\EventsController::class, 'bulk']);
        $r->any($p . '/events/new', [A\EventsController::class, 'edit']);
        $r->any($p . '/events/{id}', [A\EventsController::class, 'edit']);
        $r->post($p . '/events/{id}/delete', [A\EventsController::class, 'delete']);

        $r->get($p . '/sections', [A\SectionsController::class, 'index']);
        $r->any($p . '/sections/{id}', [A\SectionsController::class, 'edit']);
        $r->post($p . '/menu/{id}/toggle', [A\MenuController::class, 'toggle']);
        $r->post($p . '/menu/{id}/move/{dir:up|down}', [A\MenuController::class, 'move']);
        foreach (['categories' => A\CategoriesController::class, 'tags' => A\TagsController::class, 'pages' => A\PagesController::class,
            'links' => A\LinksController::class, 'menu' => A\MenuController::class, 'blocks' => A\BlocksController::class] as $res => $cls) {
            $r->get("$p/$res", [$cls, 'index']);
            $r->post("$p/$res/bulk", [$cls, 'bulk']);
            $r->any("$p/$res/new", [$cls, 'edit']);
            $r->any("$p/$res/{id}", [$cls, 'edit']);
            $r->post("$p/$res/{id}/delete", [$cls, 'delete']);
        }

        $r->get($p . '/media', [A\MediaController::class, 'index']);
        $r->post($p . '/media/upload', [A\MediaController::class, 'upload']);
        $r->post($p . '/media/bulk', [A\MediaController::class, 'bulk']);
        $r->get($p . '/media/list', [A\MediaController::class, 'listJson']);
        $r->post($p . '/media/{id}', [A\MediaController::class, 'update']);
        $r->post($p . '/media/{id}/delete', [A\MediaController::class, 'delete']);

        $r->get($p . '/seo', [A\SeoController::class, 'index']);
        $r->post($p . '/seo/regenerate', [A\SeoController::class, 'regenerate']);
        $r->post($p . '/seo/indexnow', [A\SeoController::class, 'indexnow']);
        $r->get($p . '/seo/redirects', [A\SeoController::class, 'redirects']);
        $r->post($p . '/seo/redirects', [A\SeoController::class, 'redirectSave']);
        $r->post($p . '/seo/redirects/{id}/delete', [A\SeoController::class, 'redirectDelete']);
        $r->get($p . '/seo/404', [A\SeoController::class, 'notFound']);
        $r->post($p . '/seo/404/{id}/{action:ignore|delete|redirect}', [A\SeoController::class, 'notFoundAct']);

        $r->any($p . '/settings', [A\SettingsController::class, 'index']);
        $r->get($p . '/users', [A\UsersController::class, 'index']);
        $r->any($p . '/users/new', [A\UsersController::class, 'edit']);
        $r->any($p . '/users/{id}', [A\UsersController::class, 'edit']);
        $r->any($p . '/profile', [A\ProfileController::class, 'index']);
        $r->post($p . '/profile/2fa/{action:enable|disable}', [A\ProfileController::class, 'twoFactor']);
        $r->post($p . '/profile/sessions/{id}/revoke', [A\ProfileController::class, 'revoke']);
        $r->get($p . '/audit', [A\SystemController::class, 'audit']);
        $r->get($p . '/system', [A\SystemController::class, 'index']);
        $r->post($p . '/system/{action:backup|cache|cleanup}', [A\SystemController::class, 'act']);
        $r->get($p . '/system/backup/{file}', [A\SystemController::class, 'download']);
    }
}
