<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Support\Slug;

/**
 * Блоки ссылок: «Госуслуги для граждан», «Доставка», «Мы в соцсетях», «Полезные ссылки» и любые свои.
 * Блок можно переименовать, создать, переставить, отключить (ссылки сохраняются) и удалить (вместе со ссылками).
 * Содержимое блоков — раздел «Ссылки и сервисы».
 */
final class BlocksController extends CrudController
{
    public const PLACEMENTS = [
        'home' => 'Главная страница (плитки)',
        'footer' => 'Подвал (список ссылок)',
        'social' => 'Подвал (соцсети, с иконками)',
    ];

    protected string $cap = 'settings';   // структура сайта: только администратор
    protected string $table = 'link_blocks';
    protected string $route = 'blocks';
    protected string $title = 'Блоки на сайте';
    protected string $singular = 'блок';
    protected string $order = 'placement, sort_order, id';
    protected int $perPage = 100;
    protected array $columns = ['title' => 'Заголовок', 'placement' => 'Где показан', 'cnt' => 'Ссылки', 'is_active' => 'Показывать'];

    public function __construct()
    {
        $this->fields = [
            'title' => ['label' => 'Заголовок блока', 'type' => 'text', 'required' => true, 'max' => 150],
            'subtitle' => ['label' => 'Подзаголовок (необязательно)', 'type' => 'text', 'max' => 255, 'help' => 'Мелким шрифтом под заголовком — только для блоков на главной.'],
            'placement' => ['label' => 'Где показывать', 'type' => 'select', 'options' => self::PLACEMENTS, 'default' => 'home'],
            'sort_order' => ['label' => 'Порядок (меньше — выше)', 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => 'Показывать на сайте', 'type' => 'checkbox', 'default' => 1, 'help' => 'Если снять — блок скрыт, но все его ссылки сохраняются.'],
        ];
        parent::__construct();
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
        if (!$row) { // технический ключ создаётся один раз из заголовка и дальше не меняется
            $base = 'b-' . (Slug::make((string) $data['title'], 30) ?: 'block');
            $key = substr($base, 0, 36);
            for ($i = 2; DB::val('SELECT 1 FROM link_blocks WHERE group_key = ?', [$key]); $i++) {
                $key = substr($base, 0, 34) . '-' . $i;
            }
            $data['group_key'] = $key;
        }
    }

    protected function afterSave(int $id, array $data): void
    {
        \App\Core\Cache::flush();
    }

    protected function afterDelete(array $row): void
    {
        DB::delete('links', 'group_key = ?', [$row['group_key']]); // ссылки удалённого блока никому не нужны
    }

    public function cell(array $row, string $key): string
    {
        if ($key === 'cnt') {
            $n = (int) DB::val('SELECT COUNT(*) FROM links WHERE group_key = ?', [$row['group_key']]);
            return '<a href="' . e(admin_url('links?group=' . rawurlencode((string) $row['group_key']))) . '">' . $n . ' ' . plural($n, 'ссылка', 'ссылки', 'ссылок') . '</a>';
        }
        if ($key === 'placement') {
            return e(self::PLACEMENTS[$row['placement']] ?? $row['placement']);
        }
        return parent::cell($row, $key);
    }
}
