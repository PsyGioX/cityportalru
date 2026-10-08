<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\App;
use App\Core\DB;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;
use App\Support\Repo;
use App\Support\Sanitizer;
use App\Support\Seo;
use App\Support\Stats;

final class NewsController extends FrontController
{
    private function listing(string $where, array $params, array $head, string $basePath, string $q = ''): void
    {
        $per = max(6, (int) Settings::get('news_per_page', 12));
        $p = new Paginator(Repo::countNews($where, $params), $per, Request::page());
        $items = Repo::news($per, $p->offset, $where, $params, 'n.published_at DESC');
        if (Request::page() > $p->pages && $p->pages >= 1 && Request::page() > 1) {
            App::notFound();
        }
        $base = Request::baseUrl();
        $title = $head['title'] . ($p->page > 1 ? ' — страница ' . $p->page : '');
        $ld = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $head['title'], 'url' => $base . $basePath, 'isPartOf' => ['@id' => $base . '/#website'],
            'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn($n, $i) => ['@type' => 'ListItem', 'position' => $p->offset + $i + 1, 'url' => $base . '/news/' . $n['slug']], $items, array_keys($items))]];
        $this->render('front/news-list', [
            'items' => $items, 'p' => $p, 'base' => $basePath, 'head' => $head, 'q' => $q, 'categories' => Repo::categories(),
        ], [
            'title' => $title, 'description' => $head['description'], 'path' => $basePath, 'canonical' => $base . $basePath . ($p->page > 1 ? '?page=' . $p->page : ''),
            'noindex' => !empty($head['noindex']), 'jsonld' => [$ld], 'breadcrumbs' => $head['crumbs'],
            'prev' => $p->page > 1 ? $base . $p->url($p->page - 1, $basePath) : null, 'next' => $p->page < $p->pages ? $base . $p->url($p->page + 1, $basePath) : null,
        ], $items ? (int) strtotime((string) $items[0]['updated_at']) : null);
    }

    public function index(): void
    {
        $this->listing('1=1', [], [
            'title' => 'Новости ' . city_of(), 'h1' => 'Новости города', 'description' => 'Свежие новости ' . city_of() . ': общество, экономика, спорт, культура, погода и происшествия.',
            'crumbs' => [['Главная', '/'], ['Новости', null]],
        ], '/news');
    }

    public function category(array $a): void
    {
        $c = DB::one('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$a['slug']]);
        if (!$c) {
            App::notFound();
        }
        $this->listing('n.category_id = ?', [$c['id']], [
            'title' => $c['seo_title'] ?: $c['name'] . ' — новости ' . city_of(), 'h1' => $c['name'], 'intro' => $c['description'], 'category' => $c,
            'description' => $c['seo_description'] ?: ($c['description'] ?: 'Новости рубрики «' . $c['name'] . '» ' . city_in() . '.'),
            'crumbs' => [['Главная', '/'], ['Новости', '/news'], [$c['name'], null]],
        ], '/category/' . $c['slug']);
    }

    public function tag(array $a): void
    {
        $t = DB::one('SELECT * FROM tags WHERE slug = ?', [$a['slug']]);
        if (!$t) {
            App::notFound();
        }
        $this->listing('n.id IN (SELECT news_id FROM news_tags WHERE tag_id = ?)', [$t['id']], [
            'title' => 'Новости по теме «' . $t['name'] . '»', 'h1' => '#' . $t['name'], 'description' => 'Все новости ' . city_of() . ' по теме «' . $t['name'] . '».',
            'crumbs' => [['Главная', '/'], ['Новости', '/news'], [$t['name'], null]],
        ], '/tag/' . $t['slug']);
    }

    public function search(): void
    {
        $q = mb_substr(Request::query('q'), 0, 100);
        $head = ['title' => $q !== '' ? 'Поиск: ' . $q : 'Поиск по сайту', 'h1' => $q !== '' ? 'Результаты поиска' : 'Поиск по новостям', 'description' => 'Поиск по новостям сайта.', 'noindex' => true,
            'crumbs' => [['Главная', '/'], ['Поиск', null]], 'search' => true];
        if (mb_strlen($q) < 2) {
            $this->render('front/news-list', ['items' => [], 'p' => new \App\Core\Paginator(0, 12, 1), 'base' => '/search', 'head' => $head, 'q' => $q, 'categories' => Repo::categories()],
                ['title' => $head['title'], 'noindex' => true, 'path' => '/search']);
        }
        // общий лимит на весь сайт: адреса посетителей не используются и не сохраняются
        if (!\App\Core\RateLimit::hit('search', 600, 60)) {
            http_response_code(429);
            header('Retry-After: 60');
            exit('Слишком много поисковых запросов. Подождите минуту.');
        }
        $like = '%' . addcslashes($q, '\\%_') . '%';
        $this->listing('(n.title LIKE ? OR n.body_text LIKE ?)', [$like, $like], $head, '/search', $q);
    }

    public function legacy(array $a): void
    {
        $n = DB::one('SELECT slug FROM news WHERE id = ?', [(int) $a['id']]);
        if (!$n) {
            App::notFound();
        }
        Response::redirect('/news/' . $n['slug'], 301);
    }

    public function show(array $a): void
    {
        $n = Repo::newsBySlug($a['slug']);
        if (!$n) {
            App::notFound();
        }
        Stats::view((int) $n['id']);
        $this->renderArticle($n, false);
    }

    /** Общий рендер статьи для сайта и предпросмотра в админке. */
    public function renderArticle(array $n, bool $preview): void
    {
        $cover = Repo::cover($n);
        $gallery = Repo::gallery((int) $n['id']);
        $tags = Repo::tags((int) $n['id']);
        $base = Request::baseUrl();
        $url = $base . '/news/' . $n['slug'];
        $text = Sanitizer::plain((string) $n['body']);
        $crumbs = [['Главная', '/'], ['Новости', '/news']];
        if ($n['cat_name']) {
            $crumbs[] = [$n['cat_name'], '/category/' . $n['cat_slug']];
        }
        $crumbs[] = [str_limit($n['title'], 60), null];
        $vars = [
            'n' => $n, 'cover' => $cover, 'gallery' => $gallery, 'tags' => $tags, 'related' => Repo::related($n, 4), 'latest' => Repo::news(5, 0, 'n.id <> ?', [$n['id']]),
            'minutes' => Sanitizer::readingMinutes($text), 'url' => $url, 'preview' => $preview, 'crumbs' => $crumbs,
        ];
        $seo = [
            'title' => Seo::articleTitle($n), 'og_title' => $n['title'], 'description' => Seo::articleDescription($n), 'path' => '/news/' . $n['slug'],
            'canonical' => $n['canonical_url'] ?: $url, 'type' => 'article', 'noindex' => $preview || !empty($n['noindex']),
            'image' => $cover ? $base . media_url($cover) : $base . '/og/news/' . $n['id'] . '.jpg',
            'image_w' => $cover ? (int) $cover['width'] : 1200, 'image_h' => $cover ? (int) $cover['height'] : 630, 'image_alt' => $cover['alt'] ?? $n['title'],
            'published' => date('c', strtotime((string) $n['published_at'])), 'modified' => date('c', strtotime((string) $n['updated_at'])),
            'section' => $n['cat_name'], 'tags' => array_column($tags, 'name'),
            'jsonld' => [Seo::article($n, $cover, $text, $gallery)], 'breadcrumbs' => $crumbs,
        ];
        if ($preview) {
            $vars['seo'] = Seo::page($seo);
            View::page('front/news-show', $vars);
        }
        $this->render('front/news-show', $vars, $seo, (int) strtotime((string) $n['updated_at']));
    }
}
