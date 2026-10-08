#!/usr/bin/env php
<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Резервная копия БД в storage/backups (хранятся последние 14). Cron: 0 3 * * * php /путь/bin/backup.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !App\Core\Config::loaded()) { exit(1); }
$file = STORAGE_PATH . '/backups/db-' . date('Y-m-d_His') . '.sql.gz';
$rows = App\Support\Backup::dump($file);
App\Support\Backup::prune(14);
echo "Готово: $file ($rows записей)\n";
