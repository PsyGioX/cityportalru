<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\Slug;

final class PagesController extends CrudController
{
    protected string $table = 'pages';
    protected string $route = 'pages';
    protected string $title = 'Страницы';
    protected string $singular = 'страница';
    protected string $order = 'sort_order, title';
    protected array $columns = ['title' => 'Заголовок', 'slug' => 'Адрес', 'status' => 'Статус', 'show_in_footer' => 'В подвале'];
    protected array $fields = [
        'title' => ['label' => 'Заголовок', 'type' => 'text', 'required' => true],
        'slug' => ['label' => 'Адрес (URL)', 'type' => 'slug', 'help' => 'Страница откроется по адресу /адрес. Нельзя использовать служебные: news, afisha, admin и т.п.'],
        'body' => ['label' => 'Текст', 'type' => 'richtext', 'required' => true, 'help' => 'Доступны подстановки из «Настроек → Редакция»: [[site_name]], [[contact_email]], [[contact_email_link]] (ссылка mailto), [[contact_phone]], [[domain]], [[city]], [[city_in]] («в Кореновске»), [[city_of]] («Кореновска»), [[region]], [[city_about]].'],
        'seo_title' => ['label' => 'SEO-заголовок', 'type' => 'text', 'max' => 190],
        'seo_description' => ['label' => 'SEO-описание', 'type' => 'textarea', 'max' => 320],
        'status' => ['label' => 'Статус', 'type' => 'select', 'options' => ['published' => 'Опубликована', 'draft' => 'Черновик'], 'default' => 'published'],
        'show_in_footer' => ['label' => 'Ссылка в подвале сайта', 'type' => 'checkbox', 'default' => 0],
        'noindex' => ['label' => 'Закрыть от индексации (noindex)', 'type' => 'checkbox', 'default' => 0],
        'sort_order' => ['label' => 'Порядок в подвале', 'type' => 'number', 'default' => 100],
    ];

    protected function validate(array &$d, ?array $row, array &$errors): void
    {
        $d['slug'] = Slug::make($d['slug'] ?: $d['title']);
        if (in_array($d['slug'], Slug::RESERVED, true)) {
            $errors['slug'] = 'Этот адрес зарезервирован системой.';
        }
        parent::validate($d, $row, $errors);
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($row && $row['slug'] !== $data['slug'] && $row['status'] === 'published') {
            \App\Core\DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?,?,301,?,NOW()) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url)', ['/' . $row['slug'], '/' . $data['slug'], 'Авто: смена адреса страницы']);
        }
    }

    protected function canDelete(array $row): bool
    {
        return !in_array($row['slug'], ['about', 'kontakty', 'principles'], true) || \App\Core\Auth::isAdmin();
    }
}
