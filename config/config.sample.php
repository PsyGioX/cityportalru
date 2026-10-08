<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Образец конфигурации. Обычно файл config.php создаёт мастер установки.
return [
    'app' => [
        'url' => 'https://example.ru',            // канонический адрес без слэша в конце
        'key' => 'ЗАМЕНИТЕ-на-64-случайных-hex-символа-php -r "echo bin2hex(random_bytes(32));"',
        'timezone' => 'Europe/Moscow',
        'debug' => false,                         // на боевом сайте всегда false
        'admin_path' => 'admin',                  // адрес админки
        'force_https' => true,
        'enforce_host' => true,                   // редирект www → основной домен
        'trusted_proxies' => [],                  // например ['127.0.0.1'] за nginx/Cloudflare
    ],
    'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'tk', 'user' => 'tk', 'pass' => '', 'socket' => ''],
];
