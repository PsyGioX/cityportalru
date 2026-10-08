<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Request;
use App\Core\Security;
use App\Support\SeoFiles;

/**
 * Меню сайта: разделы в шапке и подвале. Можно переименовать, переставить, создать свой пункт (страница или ссылка),
 * удалить и ОТКЛЮЧИТЬ. Отключённый раздел (Афиша, Кино, Погода, Радио…) исчезает из меню и перестаёт открываться (404).
 */
final class MenuController extends CrudController
{
    protected string $cap = 'settings';   // структура сайта: только администратор
    protected string $table = 'menu_items';
    protected string $route = 'menu';
    protected string $title = 'Меню сайта';
    protected string $singular = 'пункт меню';
    protected string $order = 'sort_order, id';
    protected int $perPage = 200;
    protected array $columns = ['title' => 'Название', 'url' => 'Адрес', 'kind' => 'Тип', 'is_active' => 'Статус'];

    public const KINDS = ['system' => 'Раздел сайта', 'page' => 'Страница', 'link' => 'Ссылка'];

    public function bulkActions(): array
    {
        return ['enable' => 'Включить', 'disable' => 'Отключить', 'delete' => 'Удалить (кроме разделов сайта)'];
    }

    public function index(): void
    {
        [$rows] = $this->list();
        $this->view('menu-list', ['rows' => $rows, 'c' => $this], $this->title);
    }

    public function edit(array $a = []): void
    {
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        $row = $id ? DB::one('SELECT * FROM menu_items WHERE id = ?', [$id]) : null;
        $this->fields = $this->buildFields($row);
        parent::edit($a);
    }

    private function buildFields(?array $row): array
    {
        $system = $row && $row['kind'] === 'system';
        $f = ['title' => ['label' => 'Название в меню', 'type' => 'text', 'required' => true, 'max' => 100]];
        if (!$system) {
            $pages = [];
            foreach (DB::all('SELECT slug, title FROM pages ORDER BY title') as $p) {
                $pages[$p['slug']] = $p['title'] . ' (/' . $p['slug'] . ')';
            }
            $f['kind'] = ['label' => 'Что открывает пункт', 'type' => 'select', 'options' => ['page' => 'Страницу сайта (из раздела «Страницы»)', 'link' => 'Произвольную ссылку'],
                'default' => $row['kind'] ?? 'page'];
            $f['page_slug'] = ['label' => 'Страница', 'type' => 'select', 'options' => $pages ?: ['' => '— страниц ещё нет, создайте в разделе «Страницы» —'],
                'default' => ($row && $row['kind'] === 'page') ? ltrim((string) $row['url'], '/') : (string) array_key_first($pages), 'help' => 'Если пункт отключить, страница перестанет открываться по своему адресу.'];
            $f['url'] = ['label' => 'Адрес ссылки', 'type' => 'text', 'max' => 500, 'help' => 'Путь на этом сайте (/contacts) или полный адрес https://… Внешние ссылки удобнее открывать в новой вкладке.'];
        }
        $f['sort_order'] = ['label' => 'Порядок (меньше — левее)', 'type' => 'number', 'default' => $this->nextOrder()];
        $f['in_header'] = ['label' => 'Показывать в шапке', 'type' => 'checkbox', 'default' => 1];
        $f['in_footer'] = ['label' => 'Показывать в подвале («Разделы»)', 'type' => 'checkbox', 'default' => 1];
        if (!$system) {
            $f['new_tab'] = ['label' => 'Открывать в новой вкладке', 'type' => 'checkbox', 'default' => 0];
        }
        $f['is_active'] = ['label' => 'Включён', 'type' => 'checkbox', 'default' => 1, 'help' => $system
            ? 'Адрес раздела: ' . $row['url'] . ' (не меняется). Если отключить — раздел пропадёт из меню и будет отвечать «страница не найдена».' : ''];
        return $f;
    }

    private function nextOrder(): int
    {
        return (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM menu_items');
    }

    protected function validate(array &$d, ?array $row, array &$errors): void
    {
        parent::validate($d, $row, $errors);
        $kind = $d['kind'] ?? ($row['kind'] ?? 'system');
        if ($kind === 'link' && Security::safeUrl((string) ($d['url'] ?? '')) === '') {
            $errors['url'] = 'Укажите путь (/страница) или полный адрес https://…';
        }
        if ($kind === 'page' && ($d['page_slug'] ?? '') === '') {
            $errors['page_slug'] = 'Выберите страницу.';
        }
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
        if (isset($data['kind'])) {
            $data['url'] = $data['kind'] === 'page' ? '/' . $data['page_slug'] : Security::safeUrl((string) $data['url']);
            if ($data['kind'] === 'page') {
                $data['new_tab'] = 0;
            }
        }
        unset($data['page_slug']);
    }

    protected function canDelete(array $row): bool
    {
        return $row['kind'] !== 'system'; // разделы сайта нельзя удалить — только отключить
    }

    public function cell(array $row, string $key): string
    {
        return match ($key) {
            'kind' => e(self::KINDS[$row['kind']] ?? $row['kind']),
            'is_active' => $row['is_active'] ? '<span class="pill pill--ok">включён</span>' : '<span class="pill pill--off">отключён</span>',
            default => parent::cell($row, $key),
        };
    }

    /** Включить/отключить пункт (и сам раздел) одним нажатием. */
    public function toggle(array $a): void
    {
        $id = (int) $a['id'];
        $row = DB::one('SELECT * FROM menu_items WHERE id = ?', [$id]);
        if (!$row) {
            $this->go('menu', 'err', 'Пункт не найден.');
        }
        $on = $row['is_active'] ? 0 : 1;
        DB::update('menu_items', ['is_active' => $on], 'id = ?', [$id]);
        Audit::log(($on ? 'enable' : 'disable') . '.menu', 'menu_items', $id, $row['title']);
        SeoFiles::touch();
        \App\Core\Cache::flush();
        $this->go('menu', 'ok', '«' . $row['title'] . '» ' . ($on ? 'включён.' : 'отключён: пункт скрыт, адрес ' . ($row['kind'] === 'link' ? '' : $row['url'] . ' ') . 'больше не открывается.'));
    }

    /** Сдвинуть пункт выше/ниже: порядок перенумеровывается 10, 20, 30… */
    public function move(array $a): void
    {
        $ids = DB::col('SELECT id FROM menu_items ORDER BY sort_order, id');
        $i = array_search((string) $a['id'], array_map('strval', $ids), true);
        $j = $a['dir'] === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        foreach ($ids as $n => $id) {
            DB::update('menu_items', ['sort_order' => ($n + 1) * 10], 'id = ?', [(int) $id]);
        }
        SeoFiles::touch();
        $this->go('menu', 'ok', 'Порядок изменён.');
    }
}
