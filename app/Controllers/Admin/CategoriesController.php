<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

final class CategoriesController extends CrudController
{
    protected string $table = 'categories';
    protected string $route = 'categories';
    protected string $title = 'Рубрики';
    protected string $singular = 'рубрика';
    protected string $order = 'sort_order, name';
    protected array $columns = ['name' => 'Название', 'slug' => 'Адрес', 'sort_order' => 'Порядок', 'is_active' => 'Показывать'];
    protected array $fields = [
        'name' => ['label' => 'Название', 'type' => 'text', 'required' => true, 'max' => 120],
        'slug' => ['label' => 'Адрес (URL)', 'type' => 'slug', 'from' => 'name', 'help' => 'Оставьте пустым — создастся из названия. Страница: /category/адрес'],
        'description' => ['label' => 'Описание (выводится на странице рубрики)', 'type' => 'textarea', 'max' => 1000],
        'seo_title' => ['label' => 'SEO-заголовок', 'type' => 'text', 'max' => 190, 'help' => 'До 60–70 символов. Если пусто — «Название — новости Кореновска».'],
        'seo_description' => ['label' => 'SEO-описание', 'type' => 'textarea', 'max' => 320, 'help' => '120–160 символов.'],
        'sort_order' => ['label' => 'Порядок в меню', 'type' => 'number', 'default' => 0],
        'is_active' => ['label' => 'Показывать на сайте', 'type' => 'checkbox', 'default' => 1],
    ];

    protected function afterSave(int $id, array $data): void
    {
        \App\Core\Cache::flush();
    }
}
