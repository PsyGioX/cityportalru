<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function status(int $code): void
    {
        http_response_code($code);
    }

    public static function redirect(string $to, int $code = 302): never
    {
        // защита от open redirect и разделения заголовков
        $to = str_replace(["\r", "\n", "\0"], '', $to);
        if (preg_match('#^(//|\\\\)#', $to)) {
            $to = '/';
        }
        header('Location: ' . $to, true, $code);
        exit;
    }

    public static function back(string $fallback = '/'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) parse_url($ref, PHP_URL_HOST);
        $to = ($host !== '' && $host === Request::baseUrlHost()) ? $ref : $fallback;
        self::redirect($to);
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    public static function send(string $body, string $contentType = 'text/html; charset=utf-8', int $code = 200, array $headers = []): never
    {
        http_response_code($code);
        header('Content-Type: ' . $contentType);
        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $body;
        exit;
    }

    /**
     * Условный GET: отдаёт 304, если страница не менялась. Поисковые роботы
     * экономят краулинговый бюджет, а вы — нагрузку на сервер.
     */
    public static function conditional(int $lastModified, string $etagSeed = '', int $maxAge = 0): void
    {
        if (Request::method() !== 'GET' || $lastModified <= 0) {
            return;
        }
        $etag = 'W/"' . substr(md5($lastModified . '|' . $etagSeed . '|' . APP_VERSION), 0, 20) . '"';
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=' . $maxAge . ', must-revalidate');
        $inm = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $ims = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        $match = $inm !== '' ? in_array($etag, array_map('trim', explode(',', $inm)), true)
            : ($ims !== '' && ($t = strtotime($ims)) !== false && $t >= $lastModified);
        if ($match) {
            http_response_code(304);
            exit;
        }
    }

    public static function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
