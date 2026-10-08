<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class RateLimit
{
    /** true — действие разрешено; false — лимит превышен. */
    public static function hit(string $bucket, int $max, int $windowSeconds): bool
    {
        $k = hash_hmac('sha256', $bucket, (string) Config::get('app.key', 'x'));
        $now = time();
        DB::exec(
            'INSERT INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE hits = IF(window_start < ?, 1, hits + 1), window_start = IF(window_start < ?, ?, window_start)',
            [$k, $now, $now - $windowSeconds, $now - $windowSeconds, $now]
        );
        return (int) DB::val('SELECT hits FROM rate_limits WHERE k = ?', [$k]) <= $max;
    }
}
