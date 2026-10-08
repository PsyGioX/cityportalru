<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

/** Простой файловый кэш (storage/cache). */
final class Cache
{
    private static function file(string $key): string
    {
        return STORAGE_PATH . '/cache/' . sha1($key) . '.cache';
    }

    public static function get(string $key, bool $allowStale = false): mixed
    {
        $f = self::file($key);
        if (!is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        if (!is_array($d) || !array_key_exists('v', $d)) {
            return null;
        }
        return ($allowStale || ($d['exp'] ?? 0) >= time()) ? $d['v'] : null;
    }

    public static function set(string $key, mixed $value, int $ttl = 300): void
    {
        $f = self::file($key);
        if (is_dir(dirname($f)) && is_writable(dirname($f))) {
            @file_put_contents($f, json_encode(['exp' => time() + $ttl, 'v' => $value], JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }

    public static function remember(string $key, int $ttl, callable $fn): mixed
    {
        $v = self::get($key);
        if ($v === null) {
            $v = $fn();
            if ($v !== null) {
                self::set($key, $v, $ttl);
            }
        }
        return $v;
    }

    public static function flush(): int
    {
        $n = 0;
        foreach (glob(STORAGE_PATH . '/cache/*.cache') ?: [] as $f) {
            $n += @unlink($f) ? 1 : 0;
        }
        return $n;
    }
}
