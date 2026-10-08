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
use App\Core\Security;
use App\Core\Settings;
use App\Support\IndexNow;
use App\Support\Repo;
use App\Support\SeoFiles;

final class SeoController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('seo');
    }

    private function issues(): array
    {
        $live = Repo::LIVE;
        $defs = [
            ['no_cover', 'Нет обложки', "n.cover_media_id IS NULL", 'Для превью в соцсетях подставится автокартинка, но своё фото повышает кликабельность.'],
            ['no_alt', 'У обложки нет alt-текста', "n.cover_media_id IS NOT NULL AND EXISTS (SELECT 1 FROM media m WHERE m.id = n.cover_media_id AND m.alt = '')", 'Alt нужен для доступности и поиска по картинкам.'],
            ['long_title', 'Заголовок длиннее 70 символов', "CHAR_LENGTH(COALESCE(NULLIF(n.seo_title,''), n.title)) > 70", 'Поисковики обрежут заголовок в выдаче. Задайте короткий SEO-заголовок.'],
            ['short_title', 'Заголовок короче 20 символов', "CHAR_LENGTH(COALESCE(NULLIF(n.seo_title,''), n.title)) < 20", 'Слишком короткие заголовки плохо ранжируются.'],
            ['thin', 'Мало текста (меньше 400 символов)', "CHAR_LENGTH(n.body_text) < 400", 'Тонкий контент хуже индексируется. Дополните материал.'],
            ['no_tags', 'Нет тегов', "NOT EXISTS (SELECT 1 FROM news_tags nt WHERE nt.news_id = n.id)", 'Теги создают страницы-подборки и внутренние ссылки.'],
            ['no_cat', 'Нет рубрики', "n.category_id IS NULL", 'Рубрика даёт хлебные крошки и страницу раздела.'],
            ['noindex', 'Закрыто от индексации (noindex)', "n.noindex = 1", 'Проверьте, что так и задумано.'],
            ['dup', 'Одинаковые заголовки', "n.title IN (SELECT title FROM news GROUP BY title HAVING COUNT(*) > 1)", 'Дубли заголовков мешают поисковикам различать страницы.'],
        ];
        $out = [];
        foreach ($defs as [$key, $label, $cond, $hint]) {
            $cnt = (int) DB::val("SELECT COUNT(*) FROM news n WHERE $live AND $cond");
            $out[] = ['key' => $key, 'label' => $label, 'hint' => $hint, 'count' => $cnt,
                'rows' => $cnt ? DB::all("SELECT n.id, n.title FROM news n WHERE $live AND $cond ORDER BY n.published_at DESC LIMIT 8") : []];
        }
        return $out;
    }

    public function index(): void
    {
        $files = [];
        foreach (array_keys(SeoFiles::files()) as $f) {
            $path = PUBLIC_PATH . '/' . $f;
            $files[] = ['name' => $f, 'exists' => is_file($path), 'size' => is_file($path) ? filesize($path) : 0, 'mtime' => is_file($path) ? filemtime($path) : 0];
        }
        $base = Request::baseUrl();
        $checks = [
            ['Сайт открыт для индексации', !Settings::bool('noindex_site'), 'Настройки → SEO: снимите «Закрыть сайт от индексации»'],
            ['Канонический адрес с https://', str_starts_with($base, 'https://'), 'Укажите https-адрес в config/config.php (app.url)'],
            ['Яндекс.Вебмастер подтверждён', Settings::get('yandex_verification', '') !== '', 'Настройки → SEO: код подтверждения'],
            ['Google Search Console подтверждён', Settings::get('google_meta', '') !== '' || Settings::get('google_verification', '') !== '', 'Настройки → SEO'],
            ['IndexNow включён и ключ создан', IndexNow::enabled() || (Settings::bool('indexnow_enabled') && IndexNow::key() !== ''), 'Настройки → SEO'],
            ['Все файлы sitemap/robots созданы', !array_filter($files, fn($f) => !$f['exists']), 'Нажмите «Пересоздать SEO-файлы»'],
        ];
        $this->view('seo', [
            'files' => $files, 'issues' => $this->issues(), 'checks' => $checks, 'base' => $base,
            'log' => DB::all('SELECT * FROM indexnow_log ORDER BY id DESC LIMIT 10'),
            'total' => (int) DB::val('SELECT COUNT(*) FROM news n WHERE ' . Repo::LIVE),
            'nf' => (int) DB::val('SELECT COUNT(*) FROM not_found_log WHERE is_ignored = 0'), 'red' => (int) DB::val('SELECT COUNT(*) FROM redirects'),
        ], 'SEO-центр');
    }

    public function regenerate(): void
    {
        \App\Core\Cache::flush();
        Settings::touch();
        $r = SeoFiles::writeAll();
        $bad = array_keys(array_filter($r, fn($x) => !$x['ok']));
        Audit::log('seo.regenerate', 'seo');
        $this->go('seo', $bad ? 'warn' : 'ok', $bad ? 'Не удалось записать файлы (будут отдаваться динамически): ' . implode(', ', $bad) : 'Все SEO-файлы пересозданы: ' . count($r) . '.');
    }

    public function indexnow(): void
    {
        if (!IndexNow::enabled()) {
            $this->go('seo', 'warn', 'IndexNow недоступен: он выключен, не создан ключ, либо сайт на локальном адресе / закрыт от индексации.');
        }
        $paths = ['/', '/news', '/afisha'];
        foreach (DB::col('SELECT slug FROM news n WHERE ' . Repo::LIVE . ' AND n.noindex = 0 ORDER BY published_at DESC LIMIT 2000') as $s) {
            $paths[] = '/news/' . $s;
        }
        foreach (DB::col("SELECT slug FROM pages WHERE status = 'published' AND noindex = 0") as $s) {
            $paths[] = '/' . $s;
        }
        $res = IndexNow::submit($paths);
        Audit::log('seo.indexnow', 'seo', '', count($paths) . ' URL');
        $this->go('seo', 'ok', 'Отправлено ' . count($paths) . ' адресов. Ответы: ' . implode(', ', array_map(fn($k, $v) => "$k — $v", array_keys($res), $res)) . ' (200/202 — принято).');
    }

    // ---------------------------------------------------------------- редиректы

    public function redirects(): void
    {
        $p = new Paginator((int) DB::val('SELECT COUNT(*) FROM redirects'), 40, Request::page());
        $this->view('seo-redirects', ['rows' => DB::all("SELECT * FROM redirects ORDER BY id DESC LIMIT 40 OFFSET {$p->offset}"), 'p' => $p, 'prefill' => Request::query('from')], 'Редиректы');
    }

    public function redirectSave(): void
    {
        $from = '/' . ltrim(Request::line('from_path', 255), '/');
        $from = rtrim($from, '/') ?: '/';
        $code = Request::int('code', 301);
        $to = Request::line('to_url', 500);
        if (!in_array($code, [301, 302, 307, 308, 410], true)) {
            $code = 301;
        }
        if ($code !== 410) {
            $to = str_starts_with($to, '/') ? $to : Security::safeUrl($to, false);
        } else {
            $to = '';
        }
        if ($from === '/' || str_starts_with($from, '/' . admin_path()) || ($code !== 410 && ($to === '' || $to === $from))) {
            $this->go('seo/redirects', 'err', 'Проверьте адреса: нельзя перенаправлять с главной/админки, пусто или на самого себя.');
        }
        DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url), code = VALUES(code), note = VALUES(note), is_active = 1',
            [$from, $to, $code, Request::line('note', 255)]);
        DB::delete('not_found_log', 'path = ?', [$from]);
        Audit::log('create.redirect', 'redirect', $from, "$code → $to");
        $this->go('seo/redirects', 'ok', 'Редирект сохранён.');
    }

    public function redirectDelete(array $a): void
    {
        DB::delete('redirects', 'id = ?', [(int) $a['id']]);
        Audit::log('delete.redirect', 'redirect', $a['id']);
        $this->go('seo/redirects', 'ok', 'Редирект удалён.');
    }

    // ---------------------------------------------------------------- 404

    public function notFound(): void
    {
        $p = new Paginator((int) DB::val('SELECT COUNT(*) FROM not_found_log WHERE is_ignored = 0'), 40, Request::page());
        $this->view('seo-404', ['rows' => DB::all("SELECT * FROM not_found_log WHERE is_ignored = 0 ORDER BY hits DESC, last_seen_at DESC LIMIT 40 OFFSET {$p->offset}"), 'p' => $p], 'Ошибки 404');
    }

    public function notFoundAct(array $a): void
    {
        $id = (int) $a['id'];
        $r = DB::one('SELECT * FROM not_found_log WHERE id = ?', [$id]);
        if ($r) {
            match ($a['action']) {
                'ignore' => DB::update('not_found_log', ['is_ignored' => 1], 'id = ?', [$id]),
                'delete' => DB::delete('not_found_log', 'id = ?', [$id]),
                'redirect' => Response::redirect(admin_url('seo/redirects?from=' . rawurlencode($r['path']))),
            };
        }
        $this->go('seo/404');
    }
}
