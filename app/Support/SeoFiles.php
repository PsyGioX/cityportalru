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
 * Генератор SEO-файлов: sitemap (индекс + страницы + новости + события + Google News),
 * robots.txt, RSS, лента для Яндекс Новостей/Дзена, llms.txt, manifest, security.txt, ключ IndexNow.
 * writeAll() кладёт статические копии в public/ (их отдаёт веб-сервер без PHP);
 * если папка недоступна для записи — те же файлы отдаются динамически через маршруты.
 */
final class SeoFiles
{
    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function d(?string $dt): string
    {
        return date('c', $dt ? (int) strtotime($dt) : time());
    }

    private static function base(): string
    {
        return Request::baseUrl();
    }

    public static function files(): array
    {
        $f = [
            'sitemap.xml' => [self::class, 'sitemapIndex'], 'sitemap-pages.xml' => [self::class, 'sitemapPages'],
            'sitemap-news.xml' => [self::class, 'sitemapNews'], 'sitemap-events.xml' => [self::class, 'sitemapEvents'],
            'sitemap-google-news.xml' => [self::class, 'sitemapGoogleNews'], 'robots.txt' => [self::class, 'robots'],
            'rss.xml' => [self::class, 'rss'], 'rss-yandex.xml' => [self::class, 'rssYandex'], 'llms.txt' => [self::class, 'llms'],
            'manifest.webmanifest' => [self::class, 'manifest'], 'opensearch.xml' => [self::class, 'openSearch'], '.well-known/security.txt' => [self::class, 'securityTxt'],
        ];
        if (IndexNow::key() !== '') {
            $f[IndexNow::key() . '.txt'] = fn() => IndexNow::key();
        }
        return $f;
    }

    public static function build(string $name): ?string
    {
        $f = self::files();
        return isset($f[$name]) ? (string) call_user_func($f[$name]) : null;
    }

    /** @return array<string,array{ok:bool,size:int}> */
    public static function writeAll(): array
    {
        $out = [];
        foreach (array_keys(self::files()) as $name) {
            $path = PUBLIC_PATH . '/' . $name;
            try {
                $body = (string) self::build($name);
                if (!is_dir(dirname($path))) {
                    @mkdir(dirname($path), 0755, true);
                }
                $ok = is_dir(dirname($path)) && is_writable(dirname($path)) && @file_put_contents($path, $body, LOCK_EX) !== false;
                $out[$name] = ['ok' => $ok, 'size' => $ok ? strlen($body) : 0];
            } catch (\Throwable $e) {
                $out[$name] = ['ok' => false, 'size' => 0];
            }
        }
        return $out;
    }

    /** Вызывается при любом изменении контента. */
    public static function touch(): void
    {
        Settings::touch();
        self::writeAll();
    }

    // ------------------------------------------------------------------ sitemap

    private static function urlset(array $urls, bool $images = false, bool $news = false): string
    {
        $ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ($images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '')
            . ($news ? ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"' : '');
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset $ns>\n";
        foreach ($urls as $u) {
            $x .= "  <url>\n    <loc>" . self::x($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $x .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            foreach ($u['images'] ?? [] as $im) {
                $x .= '    <image:image><image:loc>' . self::x($im['loc']) . '</image:loc>' . (!empty($im['title']) ? '<image:title>' . self::x($im['title']) . '</image:title>' : '') . "</image:image>\n";
            }
            if (!empty($u['news'])) {
                $x .= '    <news:news><news:publication><news:name>' . self::x(Seo::siteName()) . '</news:name><news:language>ru</news:language></news:publication>'
                    . '<news:publication_date>' . $u['news']['date'] . '</news:publication_date><news:title>' . self::x($u['news']['title']) . "</news:title></news:news>\n";
            }
            $x .= "  </url>\n";
        }
        return $x . "</urlset>\n";
    }

    public static function sitemapIndex(): string
    {
        $b = self::base();
        $lm = date('c', max(Repo::lastModified(), 1));
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (['sitemap-pages.xml', 'sitemap-news.xml', 'sitemap-events.xml', 'sitemap-google-news.xml'] as $s) {
            $x .= "  <sitemap><loc>{$b}/{$s}</loc><lastmod>{$lm}</lastmod></sitemap>\n";
        }
        return $x . "</sitemapindex>\n";
    }

    public static function sitemapPages(): string
    {
        $b = self::base();
        $lastNews = self::d((string) DB::val('SELECT MAX(published_at) FROM news n WHERE ' . Repo::LIVE));
        $u = [['loc' => $b . '/', 'lastmod' => $lastNews]];
        foreach (['news' => $lastNews, 'afisha' => null, 'kino' => null, 'radio' => null, 'pogoda' => null] as $sec => $lm) {
            if (Site::enabled($sec)) {   // отключённые в «Меню сайта» разделы в sitemap не попадают
                $u[] = ['loc' => $b . '/' . $sec] + ($lm ? ['lastmod' => $lm] : []);
            }
        }
        foreach (DB::all('SELECT c.slug, MAX(n.published_at) lm FROM categories c JOIN news n ON n.category_id = c.id WHERE c.is_active = 1 AND ' . Repo::LIVE . ' GROUP BY c.id') as $c) {
            $u[] = ['loc' => "$b/category/{$c['slug']}", 'lastmod' => self::d($c['lm'])];
        }
        foreach (DB::all('SELECT t.slug, MAX(n.published_at) lm FROM tags t JOIN news_tags nt ON nt.tag_id = t.id JOIN news n ON n.id = nt.news_id WHERE ' . Repo::LIVE . ' GROUP BY t.id HAVING COUNT(*) >= 1') as $t) {
            $u[] = ['loc' => "$b/tag/{$t['slug']}", 'lastmod' => self::d($t['lm'])];
        }
        foreach (DB::all("SELECT slug, updated_at FROM pages WHERE status = 'published' AND noindex = 0") as $p) {
            if (Site::isDisabled('/' . $p['slug'])) {
                continue;
            }
            $u[] = ['loc' => "$b/{$p['slug']}", 'lastmod' => self::d($p['updated_at'])];
        }
        return self::urlset($u);
    }

    public static function sitemapNews(): string
    {
        $b = self::base();
        $u = [];
        $rows = DB::all('SELECT n.id, n.slug, n.title, n.updated_at, n.cover_media_id, m.path, m.ext FROM news n LEFT JOIN media m ON m.id = n.cover_media_id WHERE ' . Repo::LIVE . ' AND n.noindex = 0 AND (n.canonical_url IS NULL OR n.canonical_url = \'\') ORDER BY n.published_at DESC LIMIT 20000');
        foreach ($rows as $r) {
            $imgs = [];
            if ($r['path']) {
                $imgs[] = ['loc' => $b . '/uploads/' . $r['path'] . '.' . $r['ext'], 'title' => $r['title']];
            }
            foreach (DB::all('SELECT m.path, m.ext FROM news_media nm JOIN media m ON m.id = nm.media_id WHERE nm.news_id = ? ORDER BY nm.sort_order LIMIT 9', [$r['id']]) as $g) {
                $imgs[] = ['loc' => $b . '/uploads/' . $g['path'] . '.' . $g['ext']];
            }
            $u[] = ['loc' => $b . '/news/' . $r['slug'], 'lastmod' => self::d($r['updated_at']), 'images' => $imgs];
        }
        return self::urlset($u, true);
    }

    public static function sitemapEvents(): string
    {
        $b = self::base();
        $u = [];
        foreach (DB::all("SELECT slug, updated_at FROM events WHERE status = 'published' AND COALESCE(ends_at, starts_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)") as $e) {
            $u[] = ['loc' => "$b/afisha/{$e['slug']}", 'lastmod' => self::d($e['updated_at'])];
        }
        return self::urlset($u);
    }

    /** Google News принимает материалы не старше 2 суток. */
    public static function sitemapGoogleNews(): string
    {
        $b = self::base();
        $u = [];
        foreach (DB::all('SELECT n.slug, n.title, n.published_at FROM news n WHERE ' . Repo::LIVE . ' AND n.noindex = 0 AND n.published_at > DATE_SUB(NOW(), INTERVAL 2 DAY) ORDER BY n.published_at DESC LIMIT 1000') as $r) {
            $u[] = ['loc' => $b . '/news/' . $r['slug'], 'news' => ['date' => self::d($r['published_at']), 'title' => $r['title']]];
        }
        return self::urlset($u, false, true);
    }

    // ------------------------------------------------------------------ robots

    public static function robots(): string
    {
        $b = self::base();
        if (Settings::bool('noindex_site')) {
            return "# Сайт закрыт от индексации (режим разработки). Включить: Админка → Настройки → SEO.\nUser-agent: *\nDisallow: /\n";
        }
        // Нестандартный адрес админки в robots.txt не раскрываем: от индексации её защищают заголовок X-Robots-Tag и авторизация.
        $admin = admin_path() === 'admin' ? "Disallow: /admin/\n" : '';
        $rules = "{$admin}Disallow: /install\nDisallow: /search\nDisallow: /api/\n";
        $o = "# robots.txt — создан автоматически, правится в админке (Настройки → SEO)\n";
        $o .= "User-agent: *\nAllow: /\n{$rules}\n";
        // Яндекс читает только свою группу, поэтому правила дублируются; Clean-param убирает метки из индекса
        $o .= "User-agent: Yandex\nAllow: /\n{$rules}Clean-param: utm_source&utm_medium&utm_campaign&utm_term&utm_content&yclid&ysclid&fbclid&gclid&_openstat&from&etext /\n\n";
        if (Settings::bool('robots_block_ai')) {
            $o .= "# Запрет обучения ИИ-моделей на материалах сайта\n";
            foreach (['GPTBot', 'CCBot', 'Google-Extended', 'ClaudeBot', 'anthropic-ai', 'Bytespider', 'Applebot-Extended', 'Meta-ExternalAgent', 'cohere-ai', 'Diffbot', 'ImagesiftBot', 'Omgilibot'] as $bot) {
                $o .= "User-agent: {$bot}\nDisallow: /\n\n";
            }
        }
        $extra = trim((string) Settings::get('robots_extra', ''));
        if ($extra !== '') {
            $o .= $extra . "\n\n";
        }
        return $o . "Host: {$b}\nSitemap: {$b}/sitemap.xml\nSitemap: {$b}/sitemap-google-news.xml\n";
    }

    // ------------------------------------------------------------------ RSS

    private static function feedItems(int $n = 30): array
    {
        return Repo::news($n, 0, 'n.noindex = 0', [], 'n.published_at DESC');
    }

    public static function rss(): string
    {
        $b = self::base();
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">' . "\n<channel>\n";
        $x .= '<title>' . self::x(Seo::siteName()) . '</title><link>' . $b . '/</link><description>' . self::x((string) Settings::get('site_description', '')) . "</description>\n<language>ru</language>\n";
        $x .= '<atom:link href="' . $b . '/rss.xml" rel="self" type="application/rss+xml"/>' . "\n<lastBuildDate>" . date(DATE_RSS) . "</lastBuildDate>\n";
        foreach (self::feedItems() as $n) {
            $url = $b . '/news/' . $n['slug'];
            $x .= "<item>\n<title>" . self::x($n['title']) . "</title>\n<link>$url</link>\n<guid isPermaLink=\"true\">$url</guid>\n<pubDate>" . date(DATE_RSS, (int) strtotime($n['published_at'])) . "</pubDate>\n";
            if ($n['cat_name']) {
                $x .= '<category>' . self::x($n['cat_name']) . "</category>\n";
            }
            $x .= '<description>' . self::x(Seo::articleDescription($n)) . "</description>\n<content:encoded><![CDATA[" . str_replace(']]>', ']]]]><![CDATA[>', (string) $n['body']) . "]]></content:encoded>\n";
            if ($c = Repo::cover($n)) {
                $x .= '<media:content url="' . self::x($b . media_url($c)) . '" medium="image"/>' . "\n";
            }
            $x .= "</item>\n";
        }
        return $x . "</channel>\n</rss>\n";
    }

    /** Лента в формате Яндекс Новостей и Дзена (полный текст + жанр). */
    public static function rssYandex(): string
    {
        $b = self::base();
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:yandex="http://news.yandex.ru" xmlns:media="http://search.yahoo.com/mrss/" xmlns:turbo="http://turbo.yandex.ru">' . "\n<channel>\n";
        $x .= '<title>' . self::x(Seo::siteName()) . '</title><link>' . $b . '/</link><description>' . self::x((string) Settings::get('site_description', '')) . "</description>\n<language>ru</language>\n";
        $x .= '<yandex:logo>' . $b . strtok(Brand::iconUrl(192), '?') . '</yandex:logo><yandex:logo type="square">' . $b . strtok(Brand::iconUrl(512), '?') . "</yandex:logo>\n";
        foreach (self::feedItems() as $n) {
            $url = $b . '/news/' . $n['slug'];
            $x .= "<item>\n<title>" . self::x($n['title']) . "</title>\n<link>$url</link>\n<pdalink>$url</pdalink>\n<guid>$url</guid>\n<pubDate>" . date(DATE_RSS, (int) strtotime($n['published_at'])) . "</pubDate>\n";
            $x .= '<author>' . self::x(Seo::siteName()) . "</author>\n";
            if ($n['cat_name']) {
                $x .= '<category>' . self::x($n['cat_name']) . "</category>\n";
            }
            $x .= '<description>' . self::x(Seo::articleDescription($n)) . "</description>\n";
            if ($c = Repo::cover($n)) {
                $x .= '<enclosure url="' . self::x($b . media_url($c)) . '" type="image/' . ($c['ext'] === 'jpg' ? 'jpeg' : $c['ext']) . "\"/>\n";
            }
            $x .= '<yandex:genre>message</yandex:genre>' . "\n<yandex:full-text>" . self::x(Sanitizer::plain((string) $n['body'])) . "</yandex:full-text>\n</item>\n";
        }
        return $x . "</channel>\n</rss>\n";
    }

    // ------------------------------------------------------------------ прочее

    /** llms.txt — краткая карта сайта для ИИ-поиска и ассистентов. */
    public static function llms(): string
    {
        $b = self::base();
        $o = '# ' . Seo::siteName() . "\n\n> " . Settings::get('site_description', '') . "\n\n";
        $region = trim((string) Settings::get('region_name', ''));
        $o .= mb_strtoupper(mb_substr(site_kind_noun(), 0, 1)) . mb_substr(site_kind_noun(), 1) . ' ' . city_of() . ($region !== '' ? " ($region)" : '') . ". Материалы публикуются на русском языке.\n\n## Разделы\n\n";
        $radio = (string) Settings::get('radio_name', city() . ' FM');
        foreach ([['about', 'О редакции', '/about', 'принципы независимого издания'], ['news', 'Новости', '/news', 'лента новостей города и района'], ['afisha', 'Афиша', '/afisha', 'события и мероприятия'],
            ['kino', 'Кино', '/kino', 'расписание кинотеатра «' . cinema_name() . '»'], ['pogoda', 'Погода', '/pogoda', 'прогноз погоды ' . city_in()],
            ['radio', 'Радио', '/radio', 'прямой эфир «' . $radio . '»']] as [$key, $t, $p, $d]) {
            if ($key !== 'about' && !Site::enabled($key)) {
                continue;
            }
            $o .= "- [{$t}]({$b}{$p}): {$d}\n";
        }
        $o .= "\n## Последние новости\n\n";
        foreach (Repo::news(15, 0, '1=1', [], 'n.published_at DESC') as $n) {
            $o .= "- [{$n['title']}]({$b}/news/{$n['slug']}): " . str_limit(Seo::articleDescription($n), 140) . "\n";
        }
        return $o . "\n## Служебное\n\n- [Sitemap]({$b}/sitemap.xml)\n- [RSS]({$b}/rss.xml)\n";
    }

    /** Короткое название под значком на экране телефона: из настроек, иначе первые 12 знаков названия сайта. */
    private static function shortName(): string
    {
        $s = trim((string) Settings::get('site_short_name', ''));
        return $s !== '' ? $s : mb_substr(Seo::siteName(), 0, 12);
    }

    public static function manifest(): string
    {
        return json_encode([
            'name' => Seo::siteName(), 'short_name' => self::shortName(), 'description' => (string) Settings::get('site_description', ''), 'lang' => 'ru', 'dir' => 'ltr',
            'start_url' => '/?utm_source=pwa', 'scope' => '/', 'display' => 'standalone', 'orientation' => 'portrait-primary',
            'theme_color' => Brand::accent(), 'background_color' => '#ffffff', 'categories' => ['news'],
            'icons' => [
                ['src' => Brand::iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => Brand::iconUrl(512), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => Brand::maskableUrl(), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Описание поиска для браузеров (Яндекс Браузер, Firefox, Chrome): поиск по сайту из адресной строки. */
    public static function openSearch(): string
    {
        $b = self::base();
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"><ShortName>' . self::x(Seo::siteName())
            . '</ShortName><Description>Поиск по новостям ' . self::x(Seo::siteName()) . '</Description><InputEncoding>UTF-8</InputEncoding><Image width="32" height="32" type="image/png">' . $b
            . strtok(Brand::iconUrl(192), '?') . '</Image><Url type="text/html" method="get" template="' . $b . '/search?q={searchTerms}"/></OpenSearchDescription>' . "\n";
    }

    public static function securityTxt(): string
    {
        $mail = (string) Settings::get('contact_email', '');
        return "Contact: " . ($mail !== '' ? 'mailto:' . $mail : self::base() . '/kontakty') . "\nExpires: " . date('Y-m-d\TH:i:s\Z', strtotime('+1 year'))
            . "\nPreferred-Languages: ru, en\nCanonical: " . self::base() . "/.well-known/security.txt\n";
    }

    public static function contentTypes(): array
    {
        return ['xml' => 'application/xml; charset=utf-8', 'txt' => 'text/plain; charset=utf-8', 'webmanifest' => 'application/manifest+json; charset=utf-8'];
    }
}
