<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $path = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Нормализованный путь: с ведущим «/», без завершающего (кроме корня), без «//». */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $p = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $p = rawurldecode($p);
        if (!mb_check_encoding($p, 'UTF-8') || str_contains($p, "\0")) {
            $p = '/';
        }
        $p = preg_replace('#/{2,}#', '/', $p) ?: '/';
        if ($p !== '/') {
            $p = rtrim($p, '/');
        }
        return self::$path = ($p === '' ? '/' : $p);
    }

    public static function hasTrailingSlash(): bool
    {
        $p = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
        return strlen($p) > 1 && str_ends_with($p, '/');
    }

    public static function queryString(): string
    {
        return (string) ($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function ua(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function referer(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    }

    private static function trustedProxy(): bool
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        foreach ((array) Config::get('app.trusted_proxies', []) as $cidr) {
            if (self::cidrMatch($remote, (string) $cidr)) {
                return true;
            }
        }
        return false;
    }

    public static function cidrMatch(string $ip, string $cidr): bool
    {
        if ($cidr === '*') {
            return true;
        }
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $a = @inet_pton($ip);
        $b = @inet_pton((string) $net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bits = $bits === null ? strlen($a) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem) {
            $mask = (0xFF << (8 - $rem)) & 0xFF;
            return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
        }
        return true;
    }

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (self::trustedProxy()) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $h) {
                if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) {
                    return (string) $_SERVER[$h];
                }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $list = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
                foreach (array_reverse($list) as $cand) {
                    if (filter_var($cand, FILTER_VALIDATE_IP)) {
                        return $cand;
                    }
                }
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return self::trustedProxy() && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function host(): string
    {
        $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        return preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $h) ? $h : 'localhost';
    }

    /** Базовый URL сайта. Если задан app.url — используется он (защита от подмены заголовка Host). */
    public static function baseUrl(): string
    {
        $cfg = (string) Config::get('app.url', '');
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        return (self::isHttps() ? 'https' : 'http') . '://' . self::host();
    }

    public static function isLocal(): bool
    {
        $h = preg_replace('/:\d+$/', '', self::baseUrlHost());
        return $h === 'localhost' || $h === '127.0.0.1' || str_ends_with((string) $h, '.local') || str_ends_with((string) $h, '.test');
    }

    public static function baseUrlHost(): string
    {
        return (string) parse_url(self::baseUrl(), PHP_URL_HOST);
    }

    public static function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim(str_replace("\0", '', $v)) : $default;
    }

    /** Однострочное поле: убирает управляющие символы и обрезает. */
    public static function line(string $key, int $max = 255): string
    {
        $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', self::post($key)) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** Многострочный текст (textarea). */
    public static function text(string $key, int $max = 5000): string
    {
        $v = str_replace(["\r\n", "\r"], "\n", self::post($key));
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr($v, 0, $max);
    }

    public static function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function bool(string $key): int
    {
        return !empty($_POST[$key]) && $_POST[$key] !== '0' ? 1 : 0;
    }

    public static function int(string $key, int $default = 0, string $src = 'post'): int
    {
        $v = $src === 'get' ? ($_GET[$key] ?? null) : ($_POST[$key] ?? null);
        return is_string($v) && preg_match('/^-?\d{1,10}$/', trim($v)) ? (int) $v : $default;
    }

    public static function page(): int
    {
        return max(1, min(100000, self::int('page', 1, 'get')));
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public static function isBot(): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|yandex|baidu|bing|google|facebookexternalhit|telegram|whatsapp|vkshare|curl|wget|python|java\/|monitor|uptime|lighthouse|headless/i', self::ua());
    }
}
