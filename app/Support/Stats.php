<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Request;

/**
 * Общий счётчик просмотров. Не связан с посетителем: не хранит IP, не ставит cookie, не строит профилей.
 * Боты (по заголовку User-Agent, который нигде не сохраняется) не считаются.
 */
final class Stats
{
    public static function view(int $newsId): void
    {
        if (Request::isBot() || Request::method() !== 'GET') {
            return;
        }
        DB::exec('UPDATE news SET views = views + 1 WHERE id = ?', [$newsId]);
        DB::exec('INSERT INTO stats_daily (day, views) VALUES (CURDATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1');
    }
}
