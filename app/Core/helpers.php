<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;

/** Экранирование для HTML (текст и атрибуты). */
function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

function abs_url(string $path = '/'): string
{
    return Request::baseUrl() . url($path);
}

function admin_path(): string
{
    return trim((string) Config::get('app.admin_path', 'admin'), '/') ?: 'admin';
}

function admin_url(string $path = ''): string
{
    return '/' . admin_path() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function asset(string $path): string
{
    $f = PUBLIC_PATH . '/assets/' . ltrim($path, '/');
    return '/assets/' . ltrim($path, '/') . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function setting(string $key, mixed $default = ''): mixed
{
    return Settings::get($key, $default);
}

function old(string $key, mixed $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : (string) $default;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrf()) . '">';
}

function icon(string $name, string $class = ''): string
{
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false"><use href="/assets/img/sprite.svg#' . e($name) . '"></use></svg>';
}

function date_ru(int|string|null $ts, string $format = 'j F Y'): string
{
    if ($ts === null || $ts === '') {
        return '';
    }
    $t = is_int($ts) ? $ts : strtotime($ts);
    if ($t === false) {
        return '';
    }
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $days = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
    $short = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    $map = ['F' => $months[(int) date('n', $t) - 1], 'l' => $days[(int) date('w', $t)], 'D' => $short[(int) date('w', $t)]];
    $format = preg_replace_callback('/(?<!\\\\)[FlD]/', static fn($m) => $map[$m[0]], $format) ?? $format;
    return date($format, $t);
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $d = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    return $d === 1 ? $one : (($d >= 2 && $d <= 4) ? $few : $many);
}

function bytes_h(int $b): string
{
    foreach (['Б', 'КБ', 'МБ', 'ГБ'] as $i => $u) {
        if ($b < 1024 || $i === 3) {
            return ($i ? number_format($b, 1, ',', ' ') : (string) $b) . ' ' . $u;
        }
        $b /= 1024;
    }
    return '';
}

function excerpt(?string $html, int $len = 160): string
{
    $t = \App\Support\Sanitizer::plain((string) $html);
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp !== false && $sp > $len * 0.6 ? $sp : $len), " ,.;:–—-") . '…';
}

/**
 * Размеры и расширение нарезанных вариантов изображения.
 * Формат поля widths: [480,800] (варианты .webp) или {"e":"jpg","w":[480,800]} (сервер без поддержки WebP).
 * @return array{0:int[],1:string}
 */
function media_variants(?array $m): array
{
    $j = json_decode((string) ($m['widths'] ?? '[]'), true);
    if (is_array($j) && isset($j['w'])) {
        $e = (string) ($j['e'] ?? 'webp');
        return [array_map('intval', (array) $j['w']), in_array($e, ['webp', 'jpg', 'png'], true) ? $e : 'webp'];
    }
    return [is_array($j) ? array_map('intval', $j) : [], 'webp'];
}

function media_url(?array $m, ?int $w = null): string
{
    if (!$m) {
        return '';
    }
    [$widths, $vext] = media_variants($m);
    if ($w !== null && $widths) {
        $best = null;
        foreach ($widths as $x) {
            if ($x >= $w) {
                $best = $x;
                break;
            }
        }
        $best ??= end($widths);
        return '/uploads/' . $m['path'] . '-' . $best . '.' . $vext;
    }
    return '/uploads/' . $m['path'] . '.' . $m['ext'];
}

/** <img> с srcset (WebP), размерами для защиты от смещения вёрстки и ленивой загрузкой. */
function img(?array $m, string $sizes = '100vw', array $o = []): string
{
    if (!$m) {
        return '';
    }
    [$widths, $vext] = media_variants($m);
    $max = $o['max'] ?? null;
    if ($max) {
        $f = array_values(array_filter($widths, fn($x) => $x <= $max)) ?: [min($widths ?: [$max])];
        $widths = $f;
    }
    $alt = $o['alt'] ?? $m['alt'] ?? '';
    $attrs = ' src="' . e($widths ? '/uploads/' . $m['path'] . '-' . ($o['src'] ?? end($widths)) . '.' . $vext : media_url($m)) . '"';
    if (count($widths) > 1) {
        $set = [];
        foreach ($widths as $x) {
            $set[] = '/uploads/' . $m['path'] . '-' . $x . '.' . $vext . ' ' . $x . 'w';
        }
        $attrs .= ' srcset="' . e(implode(', ', $set)) . '" sizes="' . e($sizes) . '"';
    }
    $attrs .= ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '" alt="' . e($alt) . '"';
    if (!empty($o['class'])) {
        $attrs .= ' class="' . e($o['class']) . '"';
    }
    $attrs .= !empty($o['eager']) ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"';
    return '<img' . $attrs . '>';
}

function news_path(array $n): string
{
    return '/news/' . $n['slug'];
}

function paginate_html(\App\Core\Paginator $p, string $base): string
{
    return \App\Core\View::partial('partials/pagination', ['p' => $p, 'base' => $base]);
}

/** Кнопка «Показать ещё»: без JS это обычная ссылка на следующую страницу; скрипт подгружает её вниз списка. */
function load_more_html(\App\Core\Paginator $p, string $base, string $target, string $label = 'Показать ещё'): string
{
    if ($p->page >= $p->pages) {
        return '';
    }
    return '<p class="load-more"><a class="btn btn--ghost" href="' . e($p->url($p->page + 1, $base)) . '" data-more="' . e($target) . '">' . e($label) . '</a></p>';
}

function active(string $prefix, string $path): string
{
    $path = Request::path();
    return ($prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, $prefix . '/'))) ? ' aria-current="page"' : '';
}

function json_ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}

function str_limit(string $s, int $n): string
{
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}

/** Дата публикации: «27 октября, 11:00», для прошлых лет — с годом. */
function pub_date(?string $dt, bool $withTime = true): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    $sameYear = date('Y', $t) === date('Y');
    return date_ru($t, $sameYear ? ('j F' . ($withTime ? ', H:i' : '')) : 'j F Y');
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field__error" id="err-' . e($key) . '" role="alert">' . e($errors[$key]) . '</p>' : '';
}

function aria_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

/** Название города из настроек (именительный падеж): «Кореновск». */
function city(): string
{
    return trim((string) Settings::get('city_name', '')) ?: 'Кореновск';
}

/** «в Кореновске»: берётся из настроек; если не задано — простая подстановка окончания (для редких названий задайте вручную). */
function city_in(): string
{
    $v = trim((string) Settings::get('city_name_in', ''));
    if ($v !== '') {
        return $v;
    }
    $c = city();
    $last = mb_substr($c, -1);
    return match (true) {
        in_array($last, ['а', 'я'], true) => mb_substr($c, 0, -1) . 'е',
        in_array($last, ['ь', 'й'], true) => mb_substr($c, 0, -1) . ($last === 'ь' ? 'и' : 'е'),
        in_array($last, ['и', 'о', 'е', 'у', 'ы', 'э', 'ю'], true) => $c,   // Сочи, Токио — не склоняются
        default => $c . 'е',
    };
}

/** «Кореновска» (родительный падеж). */
function city_of(): string
{
    $v = trim((string) Settings::get('city_name_of', ''));
    if ($v !== '') {
        return $v;
    }
    $c = city();
    $last = mb_substr($c, -1);
    return match (true) {
        $last === 'а' => mb_substr($c, 0, -1) . 'ы',
        $last === 'я' => mb_substr($c, 0, -1) . 'и',
        in_array($last, ['ь', 'й'], true) => mb_substr($c, 0, -1) . 'я',
        in_array($last, ['и', 'о', 'е', 'у', 'ы', 'э', 'ю'], true) => $c,
        default => $c . 'а',
    };
}

/** Название кинотеатра для страницы «Кино». */
function cinema_name(): string
{
    return trim((string) Settings::get('cinema_name', '')) ?: 'Октябрь';
}

/** Типы проекта: код => [название в админке, «кто мы» в тексте, подпись главного блока новостей]. */
const SITE_KINDS = [
    'city' => ['Городской портал', 'городское издание', 'Новости города'],
    'newspaper' => ['Газета', 'газета', 'Последние материалы'],
    'media' => ['Интернет-СМИ / медиа', 'интернет-издание', 'Последние новости'],
    'other' => ['Другой проект', 'издание', 'Новости'],
];

/** Тип проекта из настроек (city | newspaper | media | other). */
function site_kind(): string
{
    $k = (string) Settings::get('site_kind', 'city');
    return isset(SITE_KINDS[$k]) ? $k : 'city';
}

/** «городское издание» / «газета» / «интернет-издание» — для текстов описаний. */
function site_kind_noun(): string
{
    return SITE_KINDS[site_kind()][1];
}

/** Текст раздела из админки, а если поле пустое — значение по умолчанию из шаблона. */
function sec(array $sec, string $key, string $default): string
{
    $v = trim((string) ($sec[$key] ?? ''));
    return $v !== '' ? $v : $default;
}
