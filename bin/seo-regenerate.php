#!/usr/bin/env php
<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Пересоздаёт sitemap*.xml, robots.txt, rss*.xml, llms.txt, manifest, security.txt, ключ IndexNow.
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !App\Core\Config::loaded()) { exit(1); }
foreach (App\Support\SeoFiles::writeAll() as $f => $r) { printf("%-32s %s\n", $f, $r['ok'] ? $r['size'] . ' байт' : 'НЕ ЗАПИСАН'); }
