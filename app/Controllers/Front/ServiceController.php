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
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Support\OgImage;
use App\Support\Repo;
use App\Support\SeoFiles;
use App\Support\Weather;

final class ServiceController
{
    /** Автогенерируемая обложка для превью в соцсетях, если у новости нет фото. */
    public function og(array $a): void
    {
        $n = Repo::newsById((int) $a['id']);
        if (!$n) {
            App::notFound();
        }
        $f = OgImage::file('n' . $n['id'], $n['title'], date_ru($n['published_at'], 'j F Y') . ($n['cat_name'] ? ' · ' . $n['cat_name'] : ''));
        if (!$f) {
            Response::redirect('/assets/img/og-default.jpg', 302);
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=2592000, immutable');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }

    public function weather(): void
    {
        $w = Weather::get(true);
        if (!$w) {
            Response::json(['ok' => false]);
        }
        [$desc, $icon] = Weather::describe($w['code'], (bool) ($w['is_day'] ?? 1));
        header('Cache-Control: public, max-age=300');
        Response::json(['ok' => true, 'temp' => Weather::fmtTemp($w['temp']), 'desc' => $desc, 'icon' => $icon]);
    }

    /** Фирменный цвет из настроек (inline-стили запрещены CSP, поэтому отдельный файл стилей). */
    public function brandCss(): void
    {
        Response::conditional(max(1, Settings::contentVersion()), 'brand', 3600);
        Response::send(\App\Support\Brand::css(), 'text/css; charset=utf-8');
    }

    public function seoFile(array $a): void
    {
        $name = $a['file'];
        $body = SeoFiles::build($name);
        if ($body === null) {
            App::notFound();
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        Response::conditional(Repo::lastModified() ?: time(), $name, 300);
        Response::send($body, SeoFiles::contentTypes()[$ext] ?? 'text/plain; charset=utf-8');
    }

    public function securityTxt(): void
    {
        Response::send((string) SeoFiles::build('.well-known/security.txt'), 'text/plain; charset=utf-8');
    }

    /** Подтверждение прав на сайт для Яндекс.Вебмастера и Google Search Console без загрузки файлов по FTP. */
    public function verification(array $a): void
    {
        $f = $a['file'];
        $y = (string) Settings::get('yandex_verification', '');
        $g = (string) Settings::get('google_verification', '');
        if ($y !== '' && $f === 'yandex_' . $y . '.html') {
            Response::send('<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>Verification: ' . e($y) . '</body></html>');
        }
        if ($g !== '' && $f === 'google' . $g . '.html') {
            Response::send('google-site-verification: google' . $g . '.html', 'text/html; charset=utf-8');
        }
        App::notFound();
    }
}
