<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    /** Ключи, которые хранятся в базе в зашифрованном виде. */
    public const SECRET = ['weather_api_key'];
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        foreach (DB::all('SELECT k, v FROM settings') as $r) {
            $out[$r['k']] = in_array($r['k'], self::SECRET, true) ? Security::decrypt((string) $r['v']) : (string) $r['v'];
        }
        return self::$cache = $out;
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        $all = self::load();
        return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function bool(string $key): bool
    {
        return (string) self::get($key, '0') === '1';
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function set(string $key, string $value): void
    {
        $stored = in_array($key, self::SECRET, true) && $value !== '' ? Security::encrypt($value) : $value;
        DB::exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $stored]);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Версия контента: любая правка на сайте сдвигает её — по ней работают 304-ответы и sitemap. */
    public static function contentVersion(): int
    {
        return (int) self::get('content_version', 0);
    }

    public static function touch(): void
    {
        self::set('content_version', (string) time());
    }
}
