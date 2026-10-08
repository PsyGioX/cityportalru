<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function write(string $channel, string $message): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log';
        if (is_file($file) && filesize($file) > 5_000_000) {
            @rename($file, $file . '.1');
        }
        $line = date('Y-m-d H:i:s') . ' ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
