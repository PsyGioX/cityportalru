<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;
    public const IDLE = 1800;      // 30 минут бездействия
    public const ABSOLUTE = 43200; // 12 часов максимум

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        $https = Request::isHttps();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) self::ABSOLUTE);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '200');
        // Префикс __Host- запрещает перезапись cookie с поддоменов (только HTTPS)
        session_name($https ? '__Host-tksid' : 'tksid');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        session_set_save_handler(new DbSessionHandler(), true);
        session_start();
        self::$started = true;

        $ua = hash('sha256', Request::ua());
        $now = time();
        if (isset($_SESSION['_ua']) && !hash_equals($_SESSION['_ua'], $ua)) {
            self::destroy();
            self::start();
            return;
        }
        if (isset($_SESSION['_last']) && ($now - $_SESSION['_last'] > self::IDLE || $now - ($_SESSION['_born'] ?? $now) > self::ABSOLUTE)) {
            self::destroy();
            self::start();
            return;
        }
        $_SESSION['_ua'] = $ua;
        $_SESSION['_last'] = $now;
        $_SESSION['_born'] ??= $now;
        DbSessionHandler::$uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $name = $_COOKIE ? (Request::isHttps() ? '__Host-tksid' : 'tksid') : '';
        if ($name !== '' && !headers_sent()) {
            setcookie($name, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        self::$started = false;
    }

    public static function get(string $k, mixed $d = null): mixed
    {
        return $_SESSION[$k] ?? $d;
    }

    public static function set(string $k, mixed $v): void
    {
        $_SESSION[$k] = $v;
    }

    public static function forget(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function csrf(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(): bool
    {
        $t = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($t === '' || !hash_equals(self::csrf(), $t)) {
            return false;
        }
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && $origin !== 'null') {
            $oh = (string) parse_url($origin, PHP_URL_HOST);
            if ($oh !== '' && !in_array($oh, [Request::baseUrlHost(), preg_replace('/:\d+$/', '', Request::host())], true)) {
                return false;
            }
        }
        return true;
    }
}
