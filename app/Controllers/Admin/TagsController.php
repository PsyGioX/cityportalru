<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

final class TagsController extends CrudController
{
    protected string $table = 'tags';
    protected string $route = 'tags';
    protected string $title = 'Теги';
    protected string $singular = 'тег';
    protected string $order = 'name';
    protected int $perPage = 60;
    protected array $columns = ['name' => 'Тег', 'slug' => 'Адрес'];
    protected array $fields = [
        'name' => ['label' => 'Название', 'type' => 'text', 'required' => true, 'max' => 100],
        'slug' => ['label' => 'Адрес (URL)', 'type' => 'slug', 'from' => 'name', 'help' => 'Страница: /tag/адрес'],
    ];
}
