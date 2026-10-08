<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

use App\Support\Cron;
use App\Support\Repo;

final class App
{
    public static function run(): void
    {
        if (!Config::loaded()) {
            (new \App\Controllers\InstallController())->run();
            return;
        }
        $path = Request::path();
        $adminBase = '/' . admin_path();
        $isAdmin = $path === $adminBase || str_starts_with($path, $adminBase . '/');
        Security::apply($isAdmin);
        self::canonicalize($isAdmin);
        \App\Support\Migrator::run();   // на обновлённом сайте сам создаст недостающие таблицы
        if (!$isAdmin && in_array(Request::method(), ['GET', 'HEAD'], true) && \App\Support\Site::isDisabled($path)) {
            self::notFound(404);        // раздел отключён в «Меню сайта»
        }

        $router = new Router();
        Routes::register($router);
        if (!$router->dispatch(Request::method(), $path)) {
            self::fallback($path);
        }
        if (!$isAdmin) {
            Deferred::add(static fn() => Cron::tick());
        }
        Deferred::flush();
    }

    private static function canonicalize(bool $isAdmin): void
    {
        $method = Request::method();
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }
        $qs = Request::queryString() !== '' ? '?' . Request::queryString() : '';
        $base = Request::baseUrl();
        $target = null;
        if (Config::get('app.force_https', false) && !Request::isHttps()) {
            $target = preg_replace('#^http://#', 'https://', $base) . Request::path() . $qs;
        }
        if (Config::get('app.enforce_host', false) && Request::host() !== strtolower((string) parse_url($base, PHP_URL_HOST) . (parse_url($base, PHP_URL_PORT) ? ':' . parse_url($base, PHP_URL_PORT) : ''))) {
            $target = $base . Request::path() . $qs;
        }
        if ($target === null && Request::hasTrailingSlash()) {
            $target = Request::path() . $qs;
        }
        if ($target !== null) {
            Response::redirect($target, 301);
        }
    }

    /** Нет маршрута: редиректы из админки → страницы верхнего уровня → 404. */
    private static function fallback(string $path): void
    {
        if (!in_array(Request::method(), ['GET', 'HEAD'], true)) {
            // страницы и редиректы читаются только методом GET
            http_response_code(405);
            header('Allow: GET, HEAD');
            exit('Method Not Allowed');
        }
        $r = DB::one('SELECT * FROM redirects WHERE from_path = ? AND is_active = 1', [$path]);
        if ($r) {
            DB::exec('UPDATE redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?', [$r['id']]);
            if ((int) $r['code'] === 410) {
                self::notFound(410);
            }
            Response::redirect($r['to_url'], (int) $r['code']);
        }
        if (preg_match('#^/([a-z0-9][a-z0-9-]*)$#', $path, $m)) {
            $page = DB::one("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$m[1]]);
            if ($page) {
                (new \App\Controllers\Front\PagesController())->show($page);
                return;
            }
        }
        self::notFound(404);
    }

    public static function notFound(int $code = 404): never
    {
        $path = Request::path();
        if ($code === 404 && Request::method() === 'GET' && strlen($path) <= 255 && !preg_match('#\.(php\d?|env|git|sql|bak|ini|asp|jsp)$#i', $path)) {
            try {
                // Сохраняется только адрес страницы и счётчик — без referrer, IP и браузера.
                DB::exec(
                    'INSERT INTO not_found_log (path, hits, first_seen_at, last_seen_at) VALUES (?, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = NOW()',
                    [$path]
                );
            } catch (\Throwable) {
            }
        }
        View::page('front/404', [
            'seo' => \App\Support\Seo::page(['title' => $code === 410 ? 'Страница удалена' : 'Страница не найдена', 'noindex' => true]),
            'latest' => Repo::news(4),
            'code' => $code,
        ], 'front/layout', $code);
    }
}
