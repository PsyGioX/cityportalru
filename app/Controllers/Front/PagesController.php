<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Settings;
use App\Support\Repo;
use App\Support\Seo;
use App\Support\Site;
use App\Support\Weather;

final class PagesController extends FrontController
{
    /** Статическая страница из админки (в т.ч. юридические документы). */
    public function show(array $page): void
    {
        $this->render('front/page', ['page' => $page, 'body' => Site::fillPlaceholders((string) $page['body'])], [
            'title' => $page['seo_title'] ?: $page['title'], 'description' => $page['seo_description'] ?: excerpt($page['body'], 170),
            'path' => '/' . $page['slug'], 'noindex' => (bool) $page['noindex'],
            'breadcrumbs' => [['Главная', '/'], [$page['title'], null]],
        ], (int) strtotime((string) $page['updated_at']));
    }

    public function cinema(): void
    {
        $this->render('front/cinema', [
            'widget' => (string) Settings::get('cinema_widget_url', ''), 'site' => (string) Settings::get('cinema_site_url', ''),
        ], [
            'title' => 'Афиша кинотеатра «' . cinema_name() . '» ' . city_in(), 'description' => 'Расписание сеансов и билеты кинотеатра «' . cinema_name() . '» (' . city() . ') на сайте кинотеатра.',
            'path' => '/kino', 'breadcrumbs' => [['Главная', '/'], ['Кино', null]],
        ], 1);
    }

    public function radio(): void
    {
        $name = (string) Settings::get('radio_name', city() . ' FM');
        $this->render('front/radio', ['name' => $name], [
            'title' => 'Радио «' . $name . '» онлайн', 'description' => 'Слушайте радио «' . $name . '» онлайн в прямом эфире: музыка, новости и программы города ' . city_of() . '.',
            'path' => '/radio', 'breadcrumbs' => [['Главная', '/'], ['Радио', null]],
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'RadioStation', 'name' => $name, 'url' => \App\Core\Request::baseUrl() . '/radio', 'areaServed' => city()]],
        ], 1);
    }

    public function weather(): void
    {
        $w = Weather::get(true);
        $n = $w ? max(1, count($w['days'] ?? [])) : Weather::days();
        $this->render('front/weather', ['w' => $w, 'city' => city(), 'configured' => Weather::configured(), 'daysCount' => $n], [
            'title' => 'Погода ' . city_in(), 'description' => 'Прогноз погоды ' . city_in() . ' на ' . $n . ' ' . plural($n, 'день', 'дня', 'дней') . ': температура, осадки, ветер и давление. Данные обновляются каждые 30 минут.',
            'path' => '/pogoda', 'breadcrumbs' => [['Главная', '/'], ['Погода', null]],
        ]);
    }
}
