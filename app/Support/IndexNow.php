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
use App\Core\Settings;

/**
 * IndexNow: мгновенное уведомление Яндекса, Bing и др. об изменении страниц.
 * Google протокол IndexNow не поддерживает — для него работает sitemap.xml.
 */
final class IndexNow
{
    public static function enabled(): bool
    {
        return Settings::bool('indexnow_enabled') && Settings::get('indexnow_key', '') !== '' && !Request::isLocal() && !Settings::bool('noindex_site');
    }

    public static function key(): string
    {
        return (string) Settings::get('indexnow_key', '');
    }

    /** @param string[] $paths пути вида /news/slug */
    public static function submit(array $paths): array
    {
        if (!self::enabled() || !$paths) {
            return [];
        }
        $base = Request::baseUrl();
        $urls = array_values(array_unique(array_map(fn($p) => str_starts_with($p, 'http') ? $p : $base . $p, $paths)));
        $urls = array_slice($urls, 0, 9000);
        $payload = json_encode([
            'host' => Request::baseUrlHost(), 'key' => self::key(), 'keyLocation' => $base . '/' . self::key() . '.txt', 'urlList' => $urls,
        ], JSON_UNESCAPED_SLASHES);
        $res = [];
        foreach (['yandex.com' => 'https://yandex.com/indexnow', 'indexnow.org (Bing и др.)' => 'https://api.indexnow.org/indexnow'] as $name => $ep) {
            [$code] = Http::request($ep, 'POST', $payload, ['Content-Type: application/json; charset=utf-8'], 6);
            $res[$name] = $code;
            DB::insert('indexnow_log', ['url' => mb_substr($urls[0] . (count($urls) > 1 ? ' (+' . (count($urls) - 1) . ')' : ''), 0, 500), 'service' => $name, 'http_code' => $code, 'created_at' => date('Y-m-d H:i:s')]);
        }
        return $res;
    }
}
