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
use App\Support\Images;

final class MediaController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('media');
    }

    private function query(int $perPage): array
    {
        $q = mb_substr(Request::query('q'), 0, 80);
        $w = '1=1';
        $params = [];
        if ($q !== '') {
            $w = '(alt LIKE ? OR path LIKE ? OR caption LIKE ?)';
            $params = array_fill(0, 3, '%' . addcslashes($q, '\\%_') . '%');
        }
        $p = new Paginator((int) DB::val("SELECT COUNT(*) FROM media WHERE $w", $params), $perPage, Request::page());
        return [DB::all("SELECT * FROM media WHERE $w ORDER BY id DESC LIMIT $perPage OFFSET {$p->offset}", $params), $p, $q];
    }

    public function index(): void
    {
        [$rows, $p, $q] = $this->query(36);
        $this->view('media', ['rows' => $rows, 'p' => $p, 'q' => $q], 'Медиа');
    }

    /** JSON для окна выбора изображения в редакторе. */
    public function listJson(): void
    {
        $requested = Request::page();
        [$rows, $p] = $this->query(24);
        if ($requested > $p->pages) {
            $rows = []; // Paginator «прижимает» номер к последней странице — не отдаём её второй раз (это и давало дубли)
        }
        Response::json(['items' => array_map(fn($m) => [
            'id' => (int) $m['id'], 'thumb' => media_url($m, 480), 'src' => media_url($m, 1200), 'full' => media_url($m), 'alt' => $m['alt'], 'w' => (int) $m['width'], 'h' => (int) $m['height'],
        ], $rows), 'page' => $p->page, 'pages' => $p->pages]);
    }

    public function upload(): void
    {
        $files = [];
        foreach ($_FILES['files'] ?? [] as $k => $list) {
            foreach ((array) $list as $i => $v) {
                $files[$i][$k] = $v;
            }
        }
        $out = [];
        $errors = [];
        foreach ($files as $f) {
            try {
                $alt = trim(preg_replace('/[-_]+/', ' ', pathinfo((string) ($f['name'] ?? ''), PATHINFO_FILENAME)) ?? '');
                $m = Images::store($f, (int) $this->user['id'], mb_substr($alt, 0, 150));
                $dup = !empty($m['duplicate']);
                if (!$dup) {
                    Audit::log('upload.media', 'media', $m['id'], $f['name'] ?? '');
                }
                $out[] = ['dup' => $dup, 'id' => (int) $m['id'], 'thumb' => media_url($m, 480), 'src' => media_url($m, 1200), 'full' => media_url($m), 'alt' => $m['alt'], 'w' => (int) $m['width'], 'h' => (int) $m['height']];
            } catch (\Throwable $e) {
                $errors[] = ($f['name'] ?? 'файл') . ': ' . $e->getMessage();
            }
        }
        if (Request::isAjax() || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            Response::json(['items' => $out, 'errors' => $errors], $out || !$errors ? 200 : 422);
        }
        $new = count(array_filter($out, fn($o) => empty($o['dup'])));
        $this->flash($errors ? 'err' : 'ok', $errors ? implode(' ', $errors) : 'Загружено новых файлов: ' . $new . (count($out) > $new ? ' (остальные уже были в медиатеке — копии не созданы)' : ''));
        Response::redirect(admin_url('media'));
    }

    public function update(array $a): void
    {
        DB::update('media', ['alt' => Request::line('alt', 255), 'caption' => Request::line('caption', 500), 'credit' => Request::line('credit', 190)], 'id = ?', [(int) $a['id']]);
        \App\Core\Settings::touch();
        if (Request::isAjax()) {
            Response::json(['ok' => true]);
        }
        $this->go('media', 'ok', 'Описание сохранено.');
    }

    /** Массовое удаление файлов. Файлы, которые где-то используются (новости, афиша, логотип…), не удаляются. */
    public function bulk(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', Request::postArray('ids')))));
        if (!$ids || count($ids) > 200 || Request::post('action') !== 'delete') {
            $this->go('media', 'warn', 'Отметьте файлы и выберите действие.');
        }
        $deleted = 0;
        $kept = 0;
        foreach ($ids as $id) {
            $own = DB::val('SELECT uploaded_by FROM media WHERE id = ?', [$id]);
            if (!\App\Core\Auth::can('content') && (int) $own !== (int) $this->user['id']) {
                $kept++;   // авторы удаляют только свои загрузки
                continue;
            }
            if (Images::usage($id) > 0) {
                $kept++;
                continue;
            }
            Images::delete($id);
            $deleted++;
        }
        Audit::log('bulk.media.delete', 'media', implode(',', $ids), "удалено $deleted, используются $kept");
        \App\Core\Settings::touch();
        $this->go('media', $kept ? 'warn' : 'ok', 'Удалено файлов: ' . $deleted . ($kept ? '. Не удалено, так как используются: ' . $kept . ' (отвяжите их от материалов и повторите).' : '.'));
    }

    public function delete(array $a): void
    {
        $id = (int) $a['id'];
        if (!\App\Core\Auth::can('content') && (int) DB::val('SELECT uploaded_by FROM media WHERE id = ?', [$id]) !== (int) $this->user['id']) {
            $this->go('media', 'err', 'Автор может удалять только свои файлы.');
        }
        $used = Images::usage($id);
        if ($used > 0 && !Request::bool('force')) {
            $this->go('media', 'warn', "Файл используется в материалах ($used). Удаление отменено — отвяжите его или повторите с подтверждением.");
        }
        Images::delete($id);
        Audit::log('delete.media', 'media', $id);
        $this->go('media', 'ok', 'Файл удалён.');
    }
}
