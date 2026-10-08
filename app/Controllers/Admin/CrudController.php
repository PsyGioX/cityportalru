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
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Support\Sanitizer;
use App\Support\SeoFiles;
use App\Support\Slug;

/**
 * Универсальный CRUD по описанию полей. Наследник задаёт таблицу, колонки списка и поля формы.
 * Типы полей: text, url, number, textarea, richtext, select, checkbox, datetime, media, mediapath, slug.
 */
abstract class CrudController extends AdminController
{
    protected string $table;
    protected string $route;           // часть URL: /admin/{route}
    protected string $title;           // «Рубрики»
    protected string $singular;        // «рубрика»
    protected string $cap = 'content';
    protected string $order = 'id DESC';
    protected int $perPage = 30;
    protected array $columns = [];     // key => label
    protected array $fields = [];      // name => [label, type, required?, help?, options?, default?, max?]

    public function __construct()
    {
        parent::__construct();
        $this->need($this->cap);
    }

    protected function list(array $params = []): array
    {
        $total = (int) DB::val("SELECT COUNT(*) FROM `{$this->table}`");
        $p = new Paginator($total, $this->perPage, Request::page());
        return [DB::all("SELECT * FROM `{$this->table}` ORDER BY {$this->order} LIMIT {$this->perPage} OFFSET {$p->offset}"), $p];
    }

    public function index(): void
    {
        [$rows, $p] = $this->list();
        $this->view('crud-list', ['rows' => $rows, 'p' => $p, 'c' => $this], $this->title);
    }

    public function edit(array $a = []): void
    {
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        $row = $id ? DB::one("SELECT * FROM `{$this->table}` WHERE id = ?", [$id]) : null;
        if ($id && !$row) {
            $this->go($this->route, 'err', 'Запись не найдена.');
        }
        $errors = [];
        if (Request::isPost()) {
            $data = $this->collect($row);
            $this->validate($data, $row, $errors);
            if (!$errors) {
                $this->beforeSave($data, $row);
                if ($row) {
                    DB::update($this->table, $data, 'id = ?', [$id]);
                } else {
                    $id = DB::insert($this->table, $data);
                }
                Audit::log(($row ? 'update' : 'create') . '.' . $this->table, $this->table, $id);
                $this->afterSave($id, $data);
                SeoFiles::touch();
                $this->go($this->route, 'ok', 'Сохранено.');
            }
            $row = array_merge($row ?? [], $data);
        }
        $this->view('crud-form', ['row' => $row ?? $this->defaults(), 'isNew' => !$id, 'errors' => $errors, 'c' => $this, 'id' => $id], ($id ? 'Редактирование: ' : 'Новая запись: ') . $this->singular);
    }

    public function delete(array $a): void
    {
        $id = (int) $a['id'];
        $row = DB::one("SELECT * FROM `{$this->table}` WHERE id = ?", [$id]);
        if ($row && $this->canDelete($row)) {
            DB::delete($this->table, 'id = ?', [$id]);
            $this->afterDelete($row);
            Audit::log('delete.' . $this->table, $this->table, $id, $row['title'] ?? $row['name'] ?? '');
            SeoFiles::touch();
            $this->go($this->route, 'ok', 'Удалено.');
        }
        $this->go($this->route, 'err', 'Удалить нельзя.');
    }

    /** Массовые действия над отмеченными записями: [код => подпись]. Набор зависит от полей таблицы. */
    public function bulkActions(): array
    {
        $a = [];
        if (isset($this->fields['is_active'])) {
            $a['enable'] = 'Включить';
            $a['disable'] = 'Отключить';
        }
        $st = $this->fields['status']['options'] ?? [];
        if (isset($st['published'], $st['draft'])) {
            $a['publish'] = 'Опубликовать';
            $a['draft'] = 'В черновики';
        }
        $a['delete'] = 'Удалить';
        return $a;
    }

    public function bulk(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', Request::postArray('ids')))));
        $action = Request::post('action');
        if (count($ids) > 500 || !$ids || !array_key_exists($action, $this->bulkActions())) {
            $this->go($this->route, 'warn', 'Отметьте записи и выберите действие.');
        }
        $rows = DB::all("SELECT * FROM `{$this->table}` WHERE id IN (" . DB::in($ids) . ')', $ids);
        $done = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            try {
                switch ($action) {
                    case 'delete':
                        if (!$this->canDelete($row)) {
                            $skipped++;
                            continue 2;
                        }
                        DB::delete($this->table, 'id = ?', [$row['id']]);
                        $this->afterDelete($row);
                        break;
                    case 'enable':
                    case 'disable':
                        DB::update($this->table, ['is_active' => $action === 'enable' ? 1 : 0], 'id = ?', [$row['id']]);
                        break;
                    default: // publish | draft
                        DB::update($this->table, ['status' => $action === 'publish' ? 'published' : 'draft'] + (array_key_exists('updated_at', $row) ? ['updated_at' => date('Y-m-d H:i:s')] : []), 'id = ?', [$row['id']]);
                }
                $done++;
            } catch (\Throwable) {
                $skipped++; // например, запись используется в другом месте и удалить её нельзя
            }
        }
        Audit::log('bulk.' . $this->table . '.' . $action, $this->table, implode(',', $ids));
        SeoFiles::touch();
        \App\Core\Cache::flush();
        $this->go($this->route, $skipped ? 'warn' : 'ok', 'Готово: обработано ' . $done . ($skipped ? ', пропущено ' . $skipped . ' (защищённые или используемые записи).' : '.'));
    }

    protected function canDelete(array $row): bool
    {
        return true;
    }

    protected function defaults(): array
    {
        $d = [];
        foreach ($this->fields as $k => $f) {
            $d[$k] = $f['default'] ?? '';
        }
        return $d;
    }

    protected function collect(?array $row): array
    {
        $d = [];
        foreach ($this->fields as $k => $f) {
            $type = $f['type'] ?? 'text';
            $max = (int) ($f['max'] ?? 255);
            $d[$k] = match ($type) {
                'checkbox' => Request::bool($k),
                'number' => Request::int($k, (int) ($f['default'] ?? 0)),
                'textarea' => Request::text($k, $max ?: 5000),
                'richtext' => Sanitizer::html(Request::post($k)),
                'media' => Request::int($k) ?: null,
                'mediapath' => Request::line($k, 255),
                'datetime' => $this->datetime($k),
                'select' => in_array(Request::post($k), array_map('strval', array_keys($f['options'] ?? [])), true) ? Request::post($k) : (string) ($f['default'] ?? array_key_first($f['options'] ?? [])),
                'slug' => Slug::make(Request::line($k, 120) ?: Request::line($f['from'] ?? 'title', 200), 100),
                'url' => \App\Core\Security::safeUrl(Request::line($k, 500)),
                default => Request::line($k, $max),
            };
        }
        return $d;
    }

    protected function validate(array &$d, ?array $row, array &$errors): void
    {
        foreach ($this->fields as $k => $f) {
            if (!empty($f['required']) && ($d[$k] === '' || $d[$k] === null)) {
                $errors[$k] = 'Заполните поле.';
            }
            if (($f['type'] ?? '') === 'slug' && $d[$k] !== '') {
                $dup = DB::val("SELECT id FROM `{$this->table}` WHERE slug = ?" . ($row ? ' AND id <> ' . (int) $row['id'] : ''), [$d[$k]]);
                if ($dup) {
                    $d[$k] = Slug::unique($this->table, $d[$k], $row ? (int) $row['id'] : null);
                }
            }
        }
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
    }

    protected function afterSave(int $id, array $data): void
    {
    }

    /** Вызывается после удаления записи (в том числе из массового действия): каскадные удаления и т. п. */
    protected function afterDelete(array $row): void
    {
    }

    public function cell(array $row, string $key): string
    {
        $v = $row[$key] ?? '';
        $f = $this->fields[$key] ?? [];
        return match ($f['type'] ?? '') {
            'checkbox' => $v ? '<span class="pill pill--ok">да</span>' : '<span class="pill pill--muted">нет</span>',
            'select' => e($f['options'][$v] ?? $v),
            default => e(str_limit((string) $v, 80)),
        };
    }

    public function route(): string
    {
        return $this->route;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function columns(): array
    {
        return $this->columns;
    }
}
