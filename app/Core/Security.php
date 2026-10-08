<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function apply(bool $admin = false): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=(), browsing-topics=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        // Единственный внешний источник — аудиопоток радио, и он подключается только по нажатию «слушать».
        $radio = '';
        try {
            $u = parse_url((string) Settings::get('radio_url', ''));
            if (!empty($u['host']) && preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/i', (string) $u['host']) && preg_match('/^https?$/', (string) ($u['scheme'] ?? ''))
                && (!isset($u['port']) || ((int) $u['port'] > 0 && (int) $u['port'] < 65536))) {
                $radio = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
            }
        } catch (\Throwable) {
        }
        if ($admin) {
            header('X-Frame-Options: DENY');
            header('Cross-Origin-Resource-Policy: same-origin');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            Response::noStore();
            $csp = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; "
                . "form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'";
        } else {
            header('X-Frame-Options: SAMEORIGIN');
            $csp = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; "
                . "media-src 'self' {$radio}; frame-src 'none'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
        }
        if (Request::isHttps()) {
            $csp .= '; upgrade-insecure-requests';
        }
        header('Content-Security-Policy: ' . $csp);
    }

    public static function key(): string
    {
        $k = (string) Config::get('app.key', '');
        if (strlen($k) < 32) {
            throw new \RuntimeException('Не задан app.key в config/config.php');
        }
        return $k;
    }

    public static function encrypt(string $plain): string
    {
        $key = hash('sha256', 'enc|' . self::key(), true);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'enc1:' . base64_encode($iv . $tag . (string) $ct);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'enc1:')) {
            return $value;
        }
        $raw = base64_decode(substr($value, 5), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $key = hash('sha256', 'enc|' . self::key(), true);
        $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $pt === false ? '' : $pt;
    }

    public static function randomToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Разрешает только http(s)-адреса и относительные пути. */
    public static function safeUrl(string $url, bool $allowRelative = true): string
    {
        $url = trim(preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '');
        if ($url === '') {
            return '';
        }
        if ($allowRelative && preg_match('#^/(?!/)#', $url)) {
            return $url;
        }
        return preg_match('#^https?://[^\s/$.?\#].[^\s]*$#iu', $url) ? $url : '';
    }
}
