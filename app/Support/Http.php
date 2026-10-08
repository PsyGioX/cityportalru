<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

/** Исходящие HTTP-запросы с таймаутами. Только http(s). */
final class Http
{
    public static function get(string $url, int $timeout = 5): ?string
    {
        return self::request($url, 'GET', null, [], $timeout)[1];
    }

    /** @return array{0:int,1:?string} [http_code, body] */
    public static function request(string $url, string $method = 'GET', ?string $body = null, array $headers = [], int $timeout = 5): array
    {
        if (!preg_match('#^https?://#i', $url) || !function_exists('curl_init')) {
            return [0, null];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'TvoyKorenovsk/' . APP_VERSION,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res === false ? null : (string) $res];
    }
}
