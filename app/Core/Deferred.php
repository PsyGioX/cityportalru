<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

/** Задачи, которые выполняются после отправки ответа пользователю (пинг поисковиков, уведомления). */
final class Deferred
{
    private static array $queue = [];

    public static function add(callable $fn): void
    {
        self::$queue[] = $fn;
    }

    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            @ob_end_flush();
            @flush();
        }
        ignore_user_abort(true);
        @set_time_limit(60);
        $q = self::$queue;
        self::$queue = [];
        foreach ($q as $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Logger::write('error', 'deferred: ' . $e->getMessage());
            }
        }
    }
}
