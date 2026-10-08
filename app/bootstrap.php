<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

/**
 * Загрузчик приложения: константы, автозагрузка, обработка ошибок.
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('APP_VERSION', '1.3.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Нужен PHP 8.1 или новее (лучше 8.4/8.5). Сейчас: ' . PHP_VERSION);
}

mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
ini_set('expose_php', '0');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    App\Core\Logger::write('php', "[$no] $str in $file:$line");
    return true; // предупреждения не ломают страницу, но пишутся в журнал
});
set_exception_handler([App\Core\ErrorHandler::class, 'handle']);
register_shutdown_function([App\Core\ErrorHandler::class, 'shutdown']);

$configLoaded = App\Core\Config::load();
date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Europe/Moscow'));
