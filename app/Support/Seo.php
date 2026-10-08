<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Settings;

/** Формирует мета-теги, Open Graph, Twitter Cards и JSON-LD для каждой страницы. */
final class Seo
{
    public static function siteName(): string
    {
        return (string) Settings::get('site_name', 'Новостной портал');
    }

    /**
     * @param array{title?:string,title_full?:string,description?:string,path?:string,canonical?:string,image?:string,image_alt?:string,
     *   image_w?:int,image_h?:int,type?:string,noindex?:bool,jsonld?:array,published?:string,modified?:string,section?:string,tags?:array,breadcrumbs?:array} $o
     */
    public static function page(array $o): array
    {
        $site = self::siteName();
        $title = $o['title_full'] ?? (isset($o['title']) && $o['title'] !== '' ? $o['title'] . ' — ' . $site : $site);
        $desc = trim(preg_replace('/\s+/u', ' ', (string) ($o['description'] ?? Settings::get('site_description', ''))) ?? '');
        $desc = str_limit($desc, 200);
        $path = $o['path'] ?? Request::path();
        $canonical = $o['canonical'] ?? (Request::baseUrl() . ($path === '/' ? '/' : $path));
        $noindex = !empty($o['noindex']) || Settings::bool('noindex_site');
        $def = Brand::ogDefault();
        $img = $o['image'] ?? $def['url'];
        if ($img !== '' && $img[0] === '/') {
            $img = Request::baseUrl() . $img;
        }
        $ld = $o['jsonld'] ?? [];
        if (!empty($o['breadcrumbs'])) {
            $ld[] = self::breadcrumbs($o['breadcrumbs']);
        }
        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'og' => [
                'type' => $o['type'] ?? 'website', 'title' => $o['og_title'] ?? ($o['title'] ?? $site), 'description' => $desc, 'url' => $canonical,
                'image' => $img, 'image_alt' => $o['image_alt'] ?? '', 'image_w' => $o['image_w'] ?? (isset($o['image']) ? 1200 : $def['w']), 'image_h' => $o['image_h'] ?? (isset($o['image']) ? 630 : $def['h']),
                'site_name' => $site, 'locale' => (string) Settings::get('site_locale', 'ru_RU'),
                'published' => $o['published'] ?? null, 'modified' => $o['modified'] ?? null, 'section' => $o['section'] ?? null, 'tags' => $o['tags'] ?? [],
            ],
            'jsonld' => $ld,
            'prev' => $o['prev'] ?? null,
            'next' => $o['next'] ?? null,
        ];
    }

    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $path]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name] + ($path !== null ? ['item' => Request::baseUrl() . $path] : []);
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public static function organization(): array
    {
        $base = Request::baseUrl();
        $same = array_values(array_filter(array_map(fn($l) => $l['url'], Repo::socialLinks())));
        return array_filter([
            '@context' => 'https://schema.org', '@type' => 'NewsMediaOrganization', '@id' => $base . '/#org',
            'name' => self::siteName(), 'url' => $base . '/',
            'logo' => ['@type' => 'ImageObject', 'url' => $base . strtok(Brand::iconUrl(512), '?'), 'width' => 512, 'height' => 512],
            'sameAs' => $same ?: null,
            'email' => Settings::get('contact_email', '') ?: null,
            'telephone' => Settings::get('contact_phone', '') ?: null,
            'foundingDate' => Settings::get('org_founding_year', '') ?: null,
            'areaServed' => ['@type' => 'City', 'name' => city()],
            'publishingPrinciples' => $base . '/principles',
        ]);
    }

    public static function website(): array
    {
        $base = Request::baseUrl();
        return [
            '@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base . '/', 'name' => self::siteName(),
            'inLanguage' => 'ru-RU', 'publisher' => ['@id' => $base . '/#org'],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/search?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
        ];
    }

    public static function article(array $n, ?array $cover, string $text, array $gallery = []): array
    {
        $base = Request::baseUrl();
        $url = $base . '/news/' . $n['slug'];
        $imgs = [];
        if ($cover) {
            $imgs[] = $base . media_url($cover);
        }
        foreach ($gallery as $g) {
            $imgs[] = $base . media_url($g);
        }
        if (!$imgs) {
            $imgs[] = $base . '/og/news/' . $n['id'] . '.jpg';
        }
        return array_filter([
            '@context' => 'https://schema.org', '@type' => 'NewsArticle', '@id' => $url . '#article',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'headline' => str_limit($n['title'], 110), 'description' => self::articleDescription($n),
            'image' => array_slice($imgs, 0, 6),
            'datePublished' => date('c', strtotime((string) $n['published_at'])), 'dateModified' => date('c', strtotime((string) $n['updated_at'])),
            'author' => ['@type' => 'Organization', 'name' => self::siteName(), 'url' => $base . '/'],
            'publisher' => ['@id' => $base . '/#org'],
            'articleSection' => $n['cat_name'] ?? null, 'inLanguage' => 'ru-RU',
            'wordCount' => count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'isBasedOn' => !empty($n['source_url']) ? $n['source_url'] : null,
        ]);
    }

    /** Описание: своё поле → лид → первые слова текста. */
    public static function articleDescription(array $n): string
    {
        $d = trim((string) ($n['seo_description'] ?? ''));
        if ($d === '') {
            $d = trim((string) ($n['excerpt'] ?? ''));
        }
        if ($d === '') {
            $d = excerpt((string) $n['body'], 170);
        }
        return str_limit($d, 200);
    }

    public static function articleTitle(array $n): string
    {
        $t = trim((string) ($n['seo_title'] ?? ''));
        return $t !== '' ? $t : $n['title'];
    }
}
