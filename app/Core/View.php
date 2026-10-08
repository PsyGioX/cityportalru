<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static array $shared = [];

    public static function share(string $k, mixed $v): void
    {
        self::$shared[$k] = $v;
    }

    public static function shared(string $k, mixed $d = null): mixed
    {
        return self::$shared[$k] ?? $d;
    }

    /** Подключает шаблон и возвращает HTML. */
    public static function partial(string $name, array $vars = []): string
    {
        $file = APP_PATH . '/views/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Шаблон не найден: ' . $name);
        }
        $vars = array_merge(self::$shared, $vars);
        return (static function () use ($file, $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            try {
                require $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })();
    }

    /** Рендерит шаблон внутри макета. */
    public static function render(string $view, array $vars = [], string $layout = 'front/layout'): string
    {
        $vars['content'] = self::partial($view, $vars);
        return self::partial($layout, $vars);
    }

    public static function page(string $view, array $vars = [], string $layout = 'front/layout', int $code = 200): never
    {
        $html = self::render($view, $vars, $layout);
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        Deferred::flush();
        exit;
    }
}
