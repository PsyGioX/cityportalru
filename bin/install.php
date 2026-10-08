#!/usr/bin/env php
<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Установка из консоли. Пример:
//   PROFILE: empty | korenovsk | demo. KIND: city | newspaper | media | other. Город: SITE_NAME CITY_NAME CITY_NAME_IN CITY_NAME_OF REGION CITY_LAT CITY_LON TZ WEATHER_KEY
//   DB_NAME=tk DB_USER=tk DB_PASS=secret SITE_URL=https://example.ru ADMIN_USER=admin ADMIN_EMAIL=me@example.ru ADMIN_PASS='длинный-пароль-12+' php bin/install.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
use App\Support\Installer;
if (PHP_SAPI !== 'cli') { exit(1); }
if (App\Core\Config::loaded()) { fwrite(STDERR, "Уже установлено\n"); exit(1); }
$g = fn(string $k, string $d = '') => getenv($k) !== false ? (string) getenv($k) : $d;
$in = Installer::defaults($g('SITE_URL', 'http://localhost')) + ['admin_pass' => '', 'admin_pass2' => ''];
$map = ['db_host' => 'DB_HOST', 'db_port' => 'DB_PORT', 'db_socket' => 'DB_SOCKET', 'db_name' => 'DB_NAME', 'db_user' => 'DB_USER', 'db_pass' => 'DB_PASS', 'site_url' => 'SITE_URL',
    'admin_path' => 'ADMIN_PATH', 'admin_user' => 'ADMIN_USER', 'admin_email' => 'ADMIN_EMAIL', 'org_name' => 'ORG_NAME', 'contact_email' => 'CONTACT_EMAIL',
    'profile' => 'PROFILE', 'kind' => 'KIND', 'site_name' => 'SITE_NAME', 'city_name' => 'CITY_NAME', 'city_name_in' => 'CITY_NAME_IN', 'city_name_of' => 'CITY_NAME_OF', 'region_name' => 'REGION',
    'city_lat' => 'CITY_LAT', 'city_lon' => 'CITY_LON', 'timezone' => 'TZ', 'weather_api_key' => 'WEATHER_KEY'];
foreach ($map as $k => $env) { $in[$k] = $g($env, (string) $in[$k]); }
$in['admin_pass'] = $in['admin_pass2'] = $g('ADMIN_PASS');
if ($g('DEMO') === '1') { $in['profile'] = 'demo'; } // обратная совместимость
$err = Installer::validate($in);
if ($err) { fwrite(STDERR, implode("\n", $err) . "\n"); exit(1); }
foreach (Installer::perform($in) as $l) { echo '✓ ', $l, PHP_EOL; }
if (Installer::$manualConfig) { echo PHP_EOL, "Сохраните в config/config.php:\n", Installer::$manualConfig; }
