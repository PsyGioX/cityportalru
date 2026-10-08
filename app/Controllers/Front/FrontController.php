<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Support\Repo;
use App\Support\Seo;

abstract class FrontController
{
    /** Рендер публичной страницы. $lastMod включает 304-ответы для краулеров и браузеров. */
    protected function render(string $view, array $vars, array $seoOpts, ?int $lastMod = null, int $code = 200): never
    {
        // Страницы разделов (Главная, Новости, Афиша, Кино, Погода, Радио): тексты и SEO, заданные в админке
        $sec = $code === 200 ? \App\Support\Site::sectionForPath((string) ($seoOpts['path'] ?? '')) : [];
        if ($sec) {
            $vars['sec'] = $sec;
            $suffix = preg_match('/ — страница \d+$/u', (string) ($seoOpts['title'] ?? ''), $m) ? $m[0] : ''; // пагинация новостей
            if (($sec['seo_title'] ?? '') !== '') {
                foreach (['title', 'title_full', 'og_title'] as $k) {
                    if (isset($seoOpts[$k]) || $k === 'title') {
                        $seoOpts[$k] = $sec['seo_title'] . ($k === 'title' ? $suffix : '');
                    }
                }
            }
            if (($sec['seo_description'] ?? '') !== '') {
                $seoOpts['description'] = $sec['seo_description'];
            }
            if (isset($vars['head']) && is_array($vars['head'])) { // шапка списка новостей
                $vars['head']['h1'] = sec($sec, 'h1', (string) $vars['head']['h1']);
                $vars['head']['intro'] = sec($sec, 'intro', (string) ($vars['head']['intro'] ?? ''));
            }
            if ($lastMod !== null) {
                $lastMod = max($lastMod, (int) $sec['updated_ts']);
            }
        }
        if ($lastMod !== null && $code === 200) {
            Response::conditional(max($lastMod, Repo::lastModified(), (int) \App\Core\Settings::contentVersion()), Request::path() . '?' . Request::queryString());
        }
        $vars['seo'] = Seo::page($seoOpts);
        View::page($view, $vars, 'front/layout', $code);
    }
}
