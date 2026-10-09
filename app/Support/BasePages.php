<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/** Базовые страницы издания: database/pages/*.html → таблица pages. Остаются редактируемыми в админке. */
final class BasePages
{
    public const PAGES = [
        // slug => [заголовок, показывать в подвале, порядок, SEO-описание]
        'about' => ['О редакции', 1, 10, 'Независимое городское издание {city_of}: новости, афиша, погода и радио — без рекламы и без сбора данных о читателях.'],
        'kontakty' => ['Контакты', 1, 20, 'Как связаться с редакцией: почта, соцсети, как сообщить новость или ошибку.'],
        'principles' => ['Редакционные принципы', 1, 30, 'Принципы работы редакции: независимость, проверка фактов, исправления, приватность читателей.'],
        // юридические документы создаются черновиками: их нужно проверить и опубликовать (5-й элемент — статус)
        'cookie-policy' => ['Политика в отношении cookie', 1, 90, 'Какие cookie и технические данные использует сайт, как дать или отозвать согласие.', 'draft'],
        'privacy' => ['Политика обработки персональных данных', 1, 95, 'Как редакция обрабатывает персональные данные, права субъектов данных и порядок обращения.', 'draft'],
    ];

    public static function install(bool $overwrite = false, ?array $only = null): int
    {
        $n = 0;
        foreach (self::PAGES as $slug => $def) {
            [$title, $footer, $sort, $desc] = $def;
            $status = $def[4] ?? 'published';
            if ($only !== null ? !in_array($slug, $only, true) : in_array($slug, ['cookie-policy', 'privacy'], true)) {
                continue;   // при установке — только базовые страницы; политики добавляет Migrator
            }
            $file = BASE_PATH . '/database/pages/' . $slug . '.html';
            if (!is_file($file)) {
                continue;
            }
            $exists = DB::val('SELECT id FROM pages WHERE slug = ?', [$slug]);
            if ($exists && !$overwrite) {
                continue;
            }
            $data = ['title' => $title, 'body' => Sanitizer::html((string) file_get_contents($file)), 'seo_description' => str_replace('{city_of}', city_of(), $desc), 'show_in_footer' => $footer,
                'sort_order' => $sort, 'status' => $status, 'noindex' => 0, 'updated_at' => date('Y-m-d H:i:s')];
            $exists ? DB::update('pages', $data, 'id = ?', [$exists]) : DB::insert('pages', $data + ['slug' => $slug]);
            $n++;
        }
        return $n;
    }
}
