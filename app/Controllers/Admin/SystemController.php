<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Config;
use App\Core\DB;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Support\Backup;
use App\Support\Cron;
use App\Support\Http;
use App\Support\SeoFiles;

final class SystemController extends AdminController
{
    public function audit(): void
    {
        $this->need('audit');
        $q = Request::query('q');
        $w = $q !== '' ? 'WHERE action LIKE ? OR username LIKE ? OR details LIKE ?' : '';
        $params = $q !== '' ? array_fill(0, 3, '%' . addcslashes($q, '\\%_') . '%') : [];
        $p = new Paginator((int) DB::val("SELECT COUNT(*) FROM audit_log $w", $params), 50, Request::page());
        $this->view('audit', ['rows' => DB::all("SELECT * FROM audit_log $w ORDER BY id DESC LIMIT 50 OFFSET {$p->offset}", $params), 'p' => $p, 'q' => $q], 'Журнал действий');
    }

    public function index(): void
    {
        $this->need('system');
        $exposed = [];
        $base = Request::baseUrl();
        foreach (['/config/config.php', '/app/bootstrap.php', '/storage/logs/php-error.log', '/database/schema.sql', '/bin/cron.php', '/.git/config'] as $p) {
            [$code] = Http::request($base . $p, 'GET', null, [], 3);
            $exposed[$p] = $code;
        }
        $backups = [];
        foreach (glob(STORAGE_PATH . '/backups/*.sql.gz') ?: [] as $f) {
            $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);
        $dirs = ['config' => BASE_PATH . '/config', 'storage' => STORAGE_PATH, 'public/uploads' => PUBLIC_PATH . '/uploads', 'public (SEO-файлы)' => PUBLIC_PATH];
        $this->view('system', [
            'exposed' => $exposed, 'backups' => $backups,
            'info' => [
                'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')' . (\App\Support\Installer::phpStatus()[0] === 'ok' ? ' — поддерживается' : ' — ' . \App\Support\Installer::phpStatus()[1]),
                'MySQL / MariaDB' => (string) DB::val('SELECT VERSION()') . (($ds = \App\Support\Installer::dbStatus((string) DB::val('SELECT VERSION()')))[0] === 'ok' ? ' — поддерживается' : ' — ' . $ds[1]), 'Версия сайта' => APP_VERSION, 'Часовой пояс' => date_default_timezone_get(),
                'OPcache' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'включён' : 'выключен', 'display_errors' => ini_get('display_errors') ?: 'выключено',
                'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'Argon2id' => defined('PASSWORD_ARGON2ID') ? 'да' : 'нет (bcrypt)',
                'Режим отладки' => Config::get('app.debug') ? 'ВКЛЮЧЁН' : 'выключен', 'Размер кэша' => bytes_h((int) array_sum(array_map('filesize', glob(STORAGE_PATH . '/cache/*') ?: []))),
            ],
            'dirs' => array_map(fn($p) => is_writable($p), $dirs),
            'db' => DB::all("SELECT table_name AS t, table_rows AS r, ROUND((data_length + index_length)/1024) AS kb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC LIMIT 12"),
        ], 'Система и резервные копии');
    }

    public function act(array $a): void
    {
        $this->need('system');
        switch ($a['action']) {
            case 'backup':
                @set_time_limit(300);
                $file = STORAGE_PATH . '/backups/db-' . date('Y-m-d_His') . '.sql.gz';
                $rows = Backup::dump($file);
                Backup::prune(14);
                Audit::log('backup.create', 'system', basename($file), "$rows строк");
                $this->go('system', 'ok', 'Резервная копия создана: ' . basename($file) . ' (' . $rows . ' записей).');
            case 'cache':
                $n = Cache::flush();
                SeoFiles::touch();
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                }
                Audit::log('cache.flush', 'system');
                $this->go('system', 'ok', "Кэш очищен ($n файлов), SEO-файлы пересозданы.");
            default:
                $log = Cron::tick(true);
                $this->go('system', 'ok', 'Фоновые задачи выполнены. ' . ($log ? implode('; ', $log) : 'Изменений нет.'));
        }
    }

    public function download(array $a): void
    {
        $this->need('system');
        $name = (string) $a['file'];
        $path = STORAGE_PATH . '/backups/' . $name;
        if (!preg_match('/^[A-Za-z0-9_\-]+\.sql\.gz$/', $name) || !is_file($path)) {
            $this->fail(404, 'Файл не найден.');
        }
        Audit::log('backup.download', 'system', $name);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
