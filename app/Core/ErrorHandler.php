<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    private static bool $handled = false;

    public static function handle(\Throwable $e): void
    {
        Logger::write('error', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        if (self::$handled) {
            return;
        }
        self::$handled = true;
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        $debug = (bool) Config::get('app.debug', false);
        $db = $e instanceof \PDOException;
        if (!headers_sent()) {
            http_response_code($db ? 503 : 500);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
            header('Retry-After: 300');
        }
        if (ob_get_level()) {
            @ob_end_clean();
        }
        $msg = $debug ? '<pre style="white-space:pre-wrap;text-align:left">' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>' : '';
        $title = $db ? 'Сайт временно недоступен' : 'Что-то пошло не так';
        $text = $db ? 'Мы уже работаем над восстановлением. Попробуйте зайти через несколько минут.' : 'Мы записали ошибку и скоро её исправим. Попробуйте обновить страницу.';
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            . "<title>{$title}</title><body style=\"font:16px/1.5 system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;color:#15171c\">"
            . "<h1 style=\"color:#e3182b\">{$title}</h1><p>{$text}</p><p><a href=\"/\">На главную</a></p>{$msg}</body></html>";
        exit(1);
    }

    public static function shutdown(): void
    {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handle(new \ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line']));
        }
    }
}
