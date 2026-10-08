<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Support\Installer;

final class InstallController
{
    public function run(): void
    {
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
        header('X-Frame-Options: DENY');
        session_name('tkinstall');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => Request::isHttps()]);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

        $detected = (Request::isHttps() ? 'https' : 'http') . '://' . Request::host();
        $in = Installer::defaults($detected);
        $errors = [];
        $notice = null;
        $done = null;
        if (Request::isPost()) {
            foreach (array_keys($in) as $k) {
                $in[$k] = in_array($k, ['db_pass', 'weather_api_key'], true) ? trim((string) ($_POST[$k] ?? '')) : Request::post($k);
            }
            $in['admin_pass'] = (string) ($_POST['admin_pass'] ?? '');
            $in['admin_pass2'] = (string) ($_POST['admin_pass2'] ?? '');
            if (!hash_equals($_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
                $errors[] = 'Сессия устарела, обновите страницу.';
            } elseif (Request::post('do') === 'test') { // «Проверить подключение» — без установки
                [$okDb, $msg] = Installer::testConnection($in);
                $notice = [$okDb ? 'ok' : 'err', $msg];
            } else {
                $errors = Installer::validate($in);
                if (!$errors) {
                    try {
                        $done = Installer::perform($in);
                        session_destroy();
                    } catch (\Throwable $e) {
                        $errors[] = 'Ошибка установки: ' . $e->getMessage();
                    }
                }
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        extract(['req' => Installer::requirements(), 'in' => $in, 'errors' => $errors, 'notice' => $notice, 'done' => $done, 'manual' => Installer::$manualConfig, 'csrf' => $_SESSION['csrf'] ?? '']);
        require APP_PATH . '/views/install.php';
        exit;
    }
}
