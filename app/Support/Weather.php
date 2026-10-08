<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Cache;
use App\Core\Settings;

/**
 * Погода из WeatherAPI.com (https://www.weatherapi.com/docs/) с кэшем на сервере.
 * Читатель браузером к WeatherAPI не обращается — запрос делает сервер сайта не чаще раза в 30 минут.
 */
final class Weather
{
    private const ENDPOINT = 'https://api.weatherapi.com/v1/forecast.json';
    private const CACHE_KEY = 'wx_v2';

    /** Коды условий WeatherAPI → [описание по-русски, значок из sprite.svg]. */
    private const CODES = [
        1000 => ['Ясно', 'sun'], 1003 => ['Переменная облачность', 'cloud-sun'], 1006 => ['Облачно', 'cloud'], 1009 => ['Пасмурно', 'cloud'],
        1030 => ['Дымка', 'fog'], 1135 => ['Туман', 'fog'], 1147 => ['Изморозь, туман', 'fog'],
        1063 => ['Местами дождь', 'rain'], 1150 => ['Местами морось', 'rain'], 1153 => ['Морось', 'rain'], 1072 => ['Местами ледяная морось', 'rain'],
        1168 => ['Ледяная морось', 'rain'], 1171 => ['Сильная ледяная морось', 'rain'], 1180 => ['Местами небольшой дождь', 'rain'], 1183 => ['Небольшой дождь', 'rain'],
        1186 => ['Временами дождь', 'rain'], 1189 => ['Дождь', 'rain'], 1192 => ['Временами сильный дождь', 'rain'], 1195 => ['Сильный дождь', 'rain'],
        1198 => ['Слабый ледяной дождь', 'rain'], 1201 => ['Ледяной дождь', 'rain'], 1240 => ['Небольшой ливень', 'rain'], 1243 => ['Ливень', 'rain'], 1246 => ['Сильный ливень', 'rain'],
        1066 => ['Местами снег', 'snow'], 1069 => ['Местами дождь со снегом', 'snow'], 1114 => ['Низовая метель', 'snow'], 1117 => ['Метель', 'snow'],
        1204 => ['Небольшой дождь со снегом', 'snow'], 1207 => ['Дождь со снегом', 'snow'], 1210 => ['Местами небольшой снег', 'snow'], 1213 => ['Небольшой снег', 'snow'],
        1216 => ['Местами снег', 'snow'], 1219 => ['Снег', 'snow'], 1222 => ['Местами сильный снег', 'snow'], 1225 => ['Сильный снег', 'snow'], 1237 => ['Ледяная крупа', 'snow'],
        1249 => ['Небольшой ливень со снегом', 'snow'], 1252 => ['Ливень со снегом', 'snow'], 1255 => ['Небольшой снегопад', 'snow'], 1258 => ['Снегопад', 'snow'],
        1261 => ['Слабый ледяной град', 'snow'], 1264 => ['Ледяной град', 'snow'],
        1087 => ['Возможна гроза', 'storm'], 1273 => ['Дождь с грозой', 'storm'], 1276 => ['Сильный дождь с грозой', 'storm'], 1279 => ['Снег с грозой', 'storm'], 1282 => ['Сильный снег с грозой', 'storm'],
    ];

    /** @return array{0:string,1:string} [описание, значок] */
    public static function describe(int $code, bool $isDay = true): array
    {
        $d = self::CODES[$code] ?? ['—', 'cloud'];
        if (!$isDay) {
            if ($code === 1000) {
                return ['Ясно', 'moon'];
            }
            if ($code === 1003) {
                return [$d[0], 'cloud'];
            }
        }
        return $d;
    }

    /** Ключ API задан в настройках? Без него погода не запрашивается и не показывается. */
    public static function configured(): bool
    {
        return (string) Settings::get('weather_api_key', '') !== '';
    }

    /** Сколько дней прогноза запрашивать (бесплатный тариф WeatherAPI даёт 3, платные — до 14). */
    public static function days(): int
    {
        return max(1, min(14, (int) Settings::get('weather_days', 3)));
    }

    /** Последняя ошибка API (для подсказки в «Настройках»), пустая строка — ошибок нет. */
    public static function lastError(): string
    {
        $e = Cache::get('wx_err', true);
        return is_string($e) ? $e : '';
    }

    public static function fresh(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    /** Свежие данные из кэша; при его отсутствии — запрос к API; при ошибке — устаревшие данные. */
    public static function get(bool $fetch = true): ?array
    {
        if (!self::configured()) {
            return null;
        }
        $d = Cache::get(self::CACHE_KEY);
        if ($d !== null || !$fetch) {
            return $d ?? Cache::get(self::CACHE_KEY, true);
        }
        if (Cache::get('wx_fail') !== null) {
            return Cache::get(self::CACHE_KEY, true);
        }
        $d = self::fetch();
        if ($d === null) {
            Cache::set('wx_fail', 1, 120); // не долбим API при сбое
            return Cache::get(self::CACHE_KEY, true);
        }
        Cache::set(self::CACHE_KEY, $d, 1800);
        return $d;
    }

    private static function fetch(): ?array
    {
        $key = (string) Settings::get('weather_api_key', '');
        $q = trim((string) Settings::get('city_lat', '45.4647')) . ',' . trim((string) Settings::get('city_lon', '39.4492'));
        $url = self::ENDPOINT . '?' . http_build_query(['key' => $key, 'q' => $q, 'days' => self::days(), 'lang' => 'ru', 'aqi' => 'no', 'alerts' => 'no']);
        [$http, $body] = Http::request($url, 'GET', null, [], 5);
        $j = $body !== null ? json_decode($body, true) : null;
        if (!is_array($j) || empty($j['current']) || !is_array($j['current'])) {
            $msg = is_array($j) && isset($j['error']['message']) ? (string) $j['error']['message'] : 'нет ответа от api.weatherapi.com (HTTP ' . $http . ')';
            Cache::set('wx_err', date('d.m.Y H:i') . ' — ' . mb_substr($msg, 0, 200), 7 * 86400);
            return null;
        }
        Cache::set('wx_err', '', 7 * 86400);

        $c = $j['current'];
        $out = [
            'updated' => time(),
            'temp' => (int) round((float) ($c['temp_c'] ?? 0)),
            'feels' => (int) round((float) ($c['feelslike_c'] ?? $c['temp_c'] ?? 0)),
            'humidity' => (int) ($c['humidity'] ?? 0),
            'pressure' => (int) round((float) ($c['pressure_mb'] ?? 0) * 0.750062),   // гПа → мм рт. ст.
            'wind' => round((float) ($c['wind_kph'] ?? 0) / 3.6, 1),                  // км/ч → м/с
            'wind_dir' => (int) ($c['wind_degree'] ?? 0),
            'code' => (int) ($c['condition']['code'] ?? 0),
            'is_day' => (int) ($c['is_day'] ?? 1),
            'uv' => round((float) ($c['uv'] ?? 0), 1),
            'days' => [],
        ];
        foreach (($j['forecast']['forecastday'] ?? []) as $fd) {
            $day = $fd['day'] ?? [];
            $out['days'][] = [
                'date' => (string) ($fd['date'] ?? ''),
                'max' => (int) round((float) ($day['maxtemp_c'] ?? 0)),
                'min' => (int) round((float) ($day['mintemp_c'] ?? 0)),
                'code' => (int) ($day['condition']['code'] ?? 0),
                'rain' => round((float) ($day['totalprecip_mm'] ?? 0), 1),
                'wind' => round((float) ($day['maxwind_kph'] ?? 0) / 3.6, 1),
                'chance' => max((int) ($day['daily_chance_of_rain'] ?? 0), (int) ($day['daily_chance_of_snow'] ?? 0)),
            ];
        }
        return $out;
    }

    public static function compass(int $deg): string
    {
        return ['северный', 'северо-восточный', 'восточный', 'юго-восточный', 'южный', 'юго-западный', 'западный', 'северо-западный'][(int) round($deg / 45) % 8];
    }

    public static function fmtTemp(int $t): string
    {
        return ($t > 0 ? '+' : ($t < 0 ? '−' : '')) . abs($t) . '°';
    }
}
