<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Роутер для встроенного сервера PHP: php -S 127.0.0.1:8080 tools/dev-router.php   (только для разработки)
$p = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$f = realpath(__DIR__ . '/../public' . $p);
if ($p !== '/' && $f && is_file($f) && str_starts_with($f, realpath(__DIR__ . '/../public')) && !str_ends_with($f, '.php')) {
    return false;
}
chdir(__DIR__ . '/../public');
require __DIR__ . '/../public/index.php';
