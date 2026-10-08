<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

final class Slug
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
    ];

    /** Страницы верхнего уровня не должны пересекаться с этими адресами. */
    public const RESERVED = ['news', 'category', 'tag', 'search', 'afisha', 'kino', 'radio', 'pogoda', 'admin',
        'install', 'assets', 'uploads', 'go', 'og', 'api', 'rss', 'sitemap', 'robots', 'feed', 'index', 'login', 'wp-admin', 'wp-login'];

    public static function make(string $text, int $max = 100): string
    {
        $s = mb_strtolower(trim($text));
        $s = strtr($s, self::MAP);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if (strlen($s) > $max) {
            $s = substr($s, 0, $max);
            $s = preg_replace('/-[^-]*$/', '', $s) ?: $s;
        }
        return $s !== '' ? $s : 'item';
    }

    public static function unique(string $table, string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (
            DB::val('SELECT 1 FROM `' . $table . '` WHERE slug = ?' . ($ignoreId ? ' AND id <> ' . (int) $ignoreId : ''), [$slug])
            || ($table === 'pages' && in_array($slug, self::RESERVED, true))
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
