#!/usr/bin/env php
<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
// Сброс пароля из консоли:  php bin/reset-password.php логин
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
use App\Core\{Auth, DB};
if (PHP_SAPI !== 'cli' || !App\Core\Config::loaded()) { exit(1); }
$login = $argv[1] ?? '';
$u = DB::one('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login]);
if (!$u) { fwrite(STDERR, "Пользователь не найден\n"); exit(1); }
fwrite(STDOUT, 'Новый пароль (от 12 символов): ');
system('stty -echo 2>/dev/null'); $pw = trim((string) fgets(STDIN)); system('stty echo 2>/dev/null'); echo PHP_EOL;
$err = Auth::passwordErrors($pw, $u['username']);
if ($err) { fwrite(STDERR, implode("\n", $err) . "\n"); exit(1); }
DB::update('users', ['password_hash' => Auth::hashPassword($pw), 'password_changed_at' => date('Y-m-d H:i:s'), 'must_change_password' => 1, 'is_active' => 1], 'id = ?', [$u['id']]);
DB::delete('sessions', 'user_id = ?', [$u['id']]);
DB::delete('login_attempts', '1=1');
echo "Пароль изменён, сеансы завершены. При входе потребуется сменить пароль.\n";
