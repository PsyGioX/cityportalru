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
use App\Core\Deferred;
use App\Core\Paginator;
use App\Core\Request;
use App\Support\IndexNow;
use App\Support\Sanitizer;
use App\Support\SeoFiles;
use App\Support\Slug;

final class EventsController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('content');
    }

    public function index(): void
    {
        $q = mb_substr(Request::query('q'), 0, 100);
        $w = '1=1';
        $params = [];
        if ($q !== '') {
            $w = 'title LIKE ?';
            $params[] = '%' . addcslashes($q, '\\%_') . '%';
        }
        $p = new Paginator((int) DB::val("SELECT COUNT(*) FROM events WHERE $w", $params), 30, Request::page());
        $rows = DB::all("SELECT * FROM events WHERE $w ORDER BY starts_at DESC, id DESC LIMIT 30 OFFSET {$p->offset}", $params);
        $this->view('events-list', ['rows' => $rows, 'p' => $p, 'q' => $q], 'Афиша');
    }

    public function edit(array $a = []): void
    {
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        $e = $id ? DB::one('SELECT * FROM events WHERE id = ?', [$id]) : null;
        if ($id && !$e) {
            $this->go('events', 'err', 'Событие не найдено.');
        }
        $errors = [];
        if (Request::isPost()) {
            $title = Request::line('title', 255);
            $starts = $this->datetime('starts_at');
            $ends = $this->datetime('ends_at');
            $data = [
                'title' => $title, 'description' => Sanitizer::html(Request::post('description')), 'starts_at' => $starts, 'ends_at' => $ends,
                'place' => Request::line('place', 190), 'address' => Request::line('address', 255), 'price' => Request::line('price', 100),
                'ticket_url' => \App\Core\Security::safeUrl(Request::line('ticket_url', 500), false), 'cover_media_id' => Request::int('cover_media_id') ?: null,
                'status' => Request::post('status') === 'published' ? 'published' : 'draft', 'seo_title' => Request::line('seo_title', 190) ?: null,
                'seo_description' => Request::line('seo_description', 320) ?: null,
            ];
            if (mb_strlen($title) < 3) {
                $errors['title'] = 'Введите название.';
            }
            if (!$starts) {
                $errors['starts_at'] = 'Укажите дату и время начала.';
            }
            if ($ends && $starts && $ends < $starts) {
                $errors['ends_at'] = 'Окончание раньше начала.';
            }
            if (!$errors) {
                $data['slug'] = Slug::unique('events', Slug::make(Request::line('slug', 120) ?: $title . '-' . date('d-m-Y', strtotime((string) $starts))), $id ?: null);
                $data['updated_at'] = date('Y-m-d H:i:s');
                if ($e) {
                    DB::update('events', $data, 'id = ?', [$id]);
                } else {
                    $id = DB::insert('events', $data + ['created_at' => date('Y-m-d H:i:s')]);
                }
                Audit::log(($e ? 'update' : 'create') . '.event', 'event', $id, $title);
                SeoFiles::touch();
                if ($data['status'] === 'published') {
                    Deferred::add(fn() => IndexNow::submit(['/afisha/' . $data['slug'], '/afisha']));
                }
                $this->go('events', 'ok', 'Событие сохранено.');
            }
            $e = array_merge($e ?? [], $data);
        }
        $e ??= ['title' => '', 'slug' => '', 'description' => '', 'starts_at' => '', 'ends_at' => '', 'place' => '', 'address' => '', 'price' => '', 'ticket_url' => '', 'cover_media_id' => null, 'status' => 'published', 'seo_title' => '', 'seo_description' => ''];
        $this->view('event-edit', ['e' => $e, 'id' => $id, 'errors' => $errors], $id ? 'Редактирование события' : 'Новое событие');
    }

    /** Массовые действия над афишей: опубликовать, в черновики, удалить. */
    public function bulk(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', Request::postArray('ids')))));
        $action = Request::post('action');
        if (!$ids || count($ids) > 500 || !in_array($action, ['publish', 'draft', 'delete'], true)) {
            $this->go('events', 'warn', 'Отметьте события и выберите действие.');
        }
        $rows = DB::all('SELECT * FROM events WHERE id IN (' . DB::in($ids) . ')', $ids);
        $paths = [];
        foreach ($rows as $e) {
            if ($action === 'delete') {
                DB::delete('events', 'id = ?', [$e['id']]);
                DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?, \'\', 410, ?, NOW()) ON DUPLICATE KEY UPDATE code = 410', ['/afisha/' . $e['slug'], 'Авто: событие удалено']);
            } else {
                DB::update('events', ['status' => $action === 'publish' ? 'published' : 'draft', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$e['id']]);
                if ($action === 'publish') {
                    $paths[] = '/afisha/' . $e['slug'];
                }
            }
        }
        Audit::log('bulk.events.' . $action, 'event', implode(',', $ids));
        SeoFiles::touch();
        if ($paths) {
            Deferred::add(fn() => IndexNow::submit([...$paths, '/afisha']));
        }
        $this->go('events', 'ok', 'Готово: обработано ' . count($rows) . '.');
    }

    public function delete(array $a): void
    {
        $e = DB::one('SELECT * FROM events WHERE id = ?', [(int) $a['id']]);
        if ($e) {
            DB::delete('events', 'id = ?', [$e['id']]);
            DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?, \'\', 410, ?, NOW()) ON DUPLICATE KEY UPDATE code = 410', ['/afisha/' . $e['slug'], 'Авто: событие удалено']);
            Audit::log('delete.event', 'event', $e['id'], $e['title']);
            SeoFiles::touch();
        }
        $this->go('events', 'ok', 'Событие удалено.');
    }
}
