<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Cache;
use App\Core\DB;

/** Фоновые задачи. Запускаются из bin/cron.php (рекомендуется) или «псевдо-кроном» раз в 5 минут при заходах на сайт. */
final class Cron
{
    public static function tick(bool $force = false): array
    {
        $lock = STORAGE_PATH . '/cache/cron.lock';
        if (!$force && is_file($lock) && time() - filemtime($lock) < 300) {
            return [];
        }
        @touch($lock);
        $log = [];
        // 1. Запланированные публикации стали видимыми → обновить sitemap и уведомить поисковики
        $new = DB::all("SELECT id, slug FROM news WHERE status = 'published' AND published_at <= NOW() AND indexnow_at IS NULL");
        if ($new) {
            SeoFiles::writeAll();
            IndexNow::submit(array_map(fn($n) => '/news/' . $n['slug'], $new));
            DB::exec('UPDATE news SET indexnow_at = NOW() WHERE id IN (' . DB::in($new) . ')', array_column($new, 'id'));
            $log[] = 'Опубликовано по расписанию: ' . count($new);
        }
        // 2. Чистка служебных таблиц
        DB::exec('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        DB::exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
        DB::exec('DELETE FROM sessions WHERE last_activity < ?', [time() - 43200]);
        DB::exec('DELETE FROM indexnow_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        DB::exec("DELETE FROM not_found_log WHERE is_ignored = 0 AND hits < 3 AND last_seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        Consent::purgeOld();   // подтверждения согласий хранятся 3 года
        foreach (glob(STORAGE_PATH . '/og/*.jpg') ?: [] as $f) {
            if (time() - filemtime($f) > 90 * 86400) {
                @unlink($f);
            }
        }
        return $log;
    }
}
