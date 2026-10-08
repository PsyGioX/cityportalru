#!/usr/bin/env php
<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Фоновые задачи: публикация по расписанию, IndexNow, снятие просроченных объявлений, очистка.
// Cron (каждые 5 минут):  */5 * * * * php /путь/к/сайту/bin/cron.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
if (!App\Core\Config::loaded()) { fwrite(STDERR, "Сайт не установлен\n"); exit(1); }
$log = App\Support\Cron::tick(true);
echo date('c'), ' ', $log ? implode('; ', $log) : 'ok', PHP_EOL;
