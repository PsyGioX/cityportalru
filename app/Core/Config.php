<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(): bool
    {
        $file = BASE_PATH . '/config/config.php';
        if (!is_file($file)) {
            return false;
        }
        $cfg = require $file;
        if (!is_array($cfg)) {
            return false;
        }
        self::$data = $cfg;
        return self::$loaded = true;
    }

    public static function loaded(): bool
    {
        return self::$loaded;
    }

    public static function set(array $data): void
    {
        self::$data = $data;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($v) || !array_key_exists($part, $v)) {
                return $default;
            }
            $v = $v[$part];
        }
        return $v;
    }
}
