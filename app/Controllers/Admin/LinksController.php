<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Paginator;
use App\Core\Request;

final class LinksController extends CrudController
{
    protected string $table = 'links';
    protected string $route = 'links';
    protected string $title = 'Ссылки и сервисы';
    protected string $singular = 'ссылка';
    protected string $order = 'group_key, sort_order, id';
    protected int $perPage = 60;
    protected array $columns = ['group_key' => 'Блок', 'title' => 'Название', 'url' => 'Адрес', 'sort_order' => 'Порядок', 'is_active' => 'Показывать'];

    /** Блоки для выбора: ключ => «Заголовок (где показан)». Создаются в разделе «Блоки на сайте». */
    public static function groups(): array
    {
        $o = [];
        foreach (DB::all('SELECT group_key, title, placement, is_active FROM link_blocks ORDER BY placement, sort_order, id') as $b) {
            $o[$b['group_key']] = $b['title'] . ' — ' . (BlocksController::PLACEMENTS[$b['placement']] ?? '') . ($b['is_active'] ? '' : ' [скрыт]');
        }
        return $o ?: ['gosuslugi' => 'Госуслуги'];
    }

    protected function list(array $params = []): array
    {
        $g = Request::query('group');
        if ($g === '' || !isset(self::groups()[$g])) {
            return parent::list($params);
        }
        $total = (int) DB::val('SELECT COUNT(*) FROM links WHERE group_key = ?', [$g]);
        $p = new Paginator($total, $this->perPage, Request::page());
        return [DB::all("SELECT * FROM links WHERE group_key = ? ORDER BY sort_order, id LIMIT {$this->perPage} OFFSET {$p->offset}", [$g]), $p];
    }

    protected function defaults(): array
    {
        $d = parent::defaults();
        $g = Request::query('group');
        $opts = self::groups();
        $d['group_key'] = isset($opts[$g]) ? $g : (string) array_key_first($opts);
        return $d;
    }

    public function __construct()
    {
        $this->fields = [
            'group_key' => ['label' => 'Блок', 'type' => 'select', 'options' => self::groups(), 'help' => 'Блоки (заголовки, место показа) настраиваются в разделе «Блоки на сайте».'],
            'title' => ['label' => 'Название', 'type' => 'text', 'required' => true],
            'description' => ['label' => 'Описание', 'type' => 'text'],
            'url' => ['label' => 'Ссылка', 'type' => 'url', 'required' => true, 'max' => 500, 'help' => 'Полный адрес с https://'],
            'icon' => ['label' => 'Иконка', 'type' => 'mediapath', 'help' => 'Выберите изображение из медиатеки или загрузите новое прямо в окне выбора. Старые иконки сервисов (/assets/img/…) продолжают работать.'],
            'sort_order' => ['label' => 'Порядок', 'type' => 'number', 'default' => 100],
            'new_tab' => ['label' => 'Открывать в новой вкладке', 'type' => 'checkbox', 'default' => 1],
            'is_active' => ['label' => 'Показывать', 'type' => 'checkbox', 'default' => 1],
        ];
        parent::__construct();
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
        if ($data['icon'] !== '' && !preg_match('#^/(assets|uploads)/[A-Za-z0-9/_.\-]+$#', $data['icon'])) {
            $data['icon'] = '';
        }
    }

    public function cell(array $row, string $key): string
    {
        return $key === 'group_key' ? e(self::groups()[$row['group_key']] ?? $row['group_key']) : parent::cell($row, $key);
    }
}
