<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Request;
use App\Core\Settings;

/**
 * Оформление и бренд, настраиваемые из админки: логотип, фавикон и производные иконки, фирменный цвет, картинка для соцсетей.
 * Пока ничего не задано — используются встроенные файлы из /assets/img/.
 */
final class Brand
{
    public const DEFAULT_ACCENT = '#e3182b';
    private const DIR = '/uploads/brand';
    private static array $cache = [];

    // ------------------------------------------------------------------ медиа из настроек

    public static function media(string $key): ?array
    {
        $id = (int) Settings::get($key, 0);
        if ($id <= 0) {
            return null;
        }
        if (!array_key_exists($id, self::$cache)) {
            self::$cache[$id] = DB::one('SELECT * FROM media WHERE id = ?', [$id]);
        }
        return self::$cache[$id];
    }

    // ------------------------------------------------------------------ цвет

    public static function accent(): string
    {
        $c = strtolower(trim((string) Settings::get('brand_accent', '')));
        return preg_match('/^#[0-9a-f]{6}$/', $c) ? $c : self::DEFAULT_ACCENT;
    }

    public static function hasCustomAccent(): bool
    {
        return self::accent() !== self::DEFAULT_ACCENT;
    }

    /** @return int[] [r,g,b] */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($c[0]))), max(0, min(255, (int) round($c[1]))), max(0, min(255, (int) round($c[2]))));
    }

    /** Смешивает цвет с другим: $t — доля второго (0…1). */
    public static function mix(string $hex, string $with, float $t): string
    {
        $a = self::rgb($hex);
        $b = self::rgb($with);
        return self::hex([$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t]);
    }

    /** Относительная яркость цвета по WCAG. */
    private static function lum(string $hex): float
    {
        $l = 0.0;
        foreach ([[0.2126, 0], [0.7152, 1], [0.0722, 2]] as [$k, $i]) {
            $v = self::rgb($hex)[$i] / 255;
            $l += $k * ($v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4);
        }
        return $l;
    }

    /** Контраст двух цветов (WCAG): 1…21; для обычного текста нужно не меньше 4,5. */
    public static function ratio(string $a, string $b): float
    {
        [$x, $y] = [self::lum($a), self::lum($b)];
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** Цвет текста (белый или тёмный) для надписей НА этом фоне — выбирается лучший по контрасту. */
    public static function onColor(string $bg): string
    {
        return self::ratio('#ffffff', $bg) >= self::ratio('#15171c', $bg) ? '#ffffff' : '#15171c';
    }

    /** Оттенок акцента, пригодный для ТЕКСТА на фоне $bg (ссылки, рубрики): сдвигаем к чёрному/белому, пока контраст не станет ≥ $min. */
    public static function readable(string $hex, string $bg, float $min = 4.6, bool $towardDark = true): string
    {
        for ($t = 0.0; $t <= 1.0001; $t += 0.04) {
            $c = self::mix($hex, $towardDark ? '#000000' : '#ffffff', $t);
            if (self::ratio($c, $bg) >= $min) {
                return $c;
            }
        }
        return $towardDark ? '#000000' : '#ffffff';
    }

    /** Все производные от акцента: @return array<string,string> */
    public static function tones(?string $accent = null): array
    {
        $a = $accent ?? self::accent();
        $on = self::onColor($a);
        $hover = self::mix($a, $on === '#ffffff' ? '#000000' : '#ffffff', 0.18);
        return [
            'accent' => $a, 'on' => $on, 'hover' => $hover, 'on_hover' => self::onColor($hover),
            'soft' => self::mix($a, '#ffffff', 0.90), 'soft_dark' => self::mix($a, '#12141a', 0.78),
            'text_light' => self::readable($a, '#ffffff', 4.6, true),      // на белом/светло-сером
            'text_dark' => self::readable($a, '#1c1f26', 4.6, false),      // на тёмных поверхностях
        ];
    }

    /** Таблица стилей с фирменным цветом (отдаётся как /brand.css — inline-стили запрещены политикой CSP). */
    public static function css(): string
    {
        $t = self::tones();
        return "/* Фирменный цвет сайта: настраивается в админке → Настройки → Оформление и бренд.\n   Цвета текста на акценте и оттенки для ссылок/рубрик подбираются автоматически по контрасту WCAG для светлой и тёмной темы. */\n"
            . ":root{--red:{$t['accent']};--red-dark:{$t['hover']};--red-soft:{$t['soft']};--on-red:{$t['on']};--on-red-dark:{$t['on_hover']};--red-text:{$t['text_light']}}\n"
            . "@media (prefers-color-scheme:dark){:root:not([data-theme=\"light\"]){--red-soft:{$t['soft_dark']};--red-text:{$t['text_dark']}}}\n"
            . ":root[data-theme=\"dark\"]{--red-soft:{$t['soft_dark']};--red-text:{$t['text_dark']}}\n";
    }

    // ------------------------------------------------------------------ логотип

    /** Разметка знака в шапке/подвале: загруженный логотип или встроенный значок. */
    public static function mark(bool $footer = false): string
    {
        $m = ($footer ? self::media('site_logo_footer') : null) ?? self::media('site_logo');
        if ($m) {
            $alt = Settings::bool('logo_hide_name') ? Seo::siteName() : '';
            return img($m, '240px', ['class' => 'brand__logo', 'eager' => true, 'alt' => $alt, 'max' => 800]);
        }
        return '<svg class="brand__mark" viewBox="0 0 221 170" aria-hidden="true"><use href="/assets/img/sprite.svg#logo-mark"></use></svg>';
    }

    public static function showName(): bool
    {
        return !(self::media('site_logo') && Settings::bool('logo_hide_name'));
    }

    // ------------------------------------------------------------------ иконки

    private static function ver(): string
    {
        return (string) Settings::get('brand_icon_v', '1');
    }

    private static function generated(): bool
    {
        return self::media('site_favicon') !== null && is_file(PUBLIC_PATH . self::DIR . '/icon-512.png');
    }

    /** Адрес иконки PNG нужного размера (192/512/180/32) — загруженной или встроенной. */
    public static function iconUrl(int $size): string
    {
        if (self::generated()) {
            $name = $size === 180 ? 'apple-touch-icon.png' : ($size === 32 ? 'favicon-32.png' : "icon-$size.png");
            return self::DIR . '/' . $name . '?v=' . self::ver();
        }
        return $size === 180 ? '/assets/img/apple-touch-icon.png' : "/assets/img/icon-$size.png";
    }

    public static function maskableUrl(): string
    {
        return self::generated() ? self::DIR . '/icon-maskable-512.png?v=' . self::ver() : '/assets/img/icon-maskable-512.png';
    }

    /** <link rel="icon">, apple-touch-icon — для публичной части и админки. */
    public static function headLinks(): string
    {
        if (!self::generated()) {
            return '<link rel="icon" href="/favicon.ico" sizes="48x48">' . "\n"
                . '<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">' . "\n"
                . '<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">' . "\n";
        }
        $v = self::ver();
        return '<link rel="icon" href="' . self::DIR . '/favicon.ico?v=' . $v . '" sizes="48x48">' . "\n"
            . '<link rel="icon" type="image/png" sizes="32x32" href="' . self::DIR . '/favicon-32.png?v=' . $v . '">' . "\n"
            . '<link rel="apple-touch-icon" href="' . self::DIR . '/apple-touch-icon.png?v=' . $v . '">' . "\n";
    }

    /** Картинка по умолчанию для превью в соцсетях. @return array{url:string,w:int,h:int} */
    public static function ogDefault(): array
    {
        $m = self::media('og_image');
        if ($m && (int) $m['width'] > 0) {
            $w = min(1200, (int) $m['width']);
            return ['url' => Request::baseUrl() . media_url($m, 1200), 'w' => $w, 'h' => (int) round($w * (int) $m['height'] / (int) $m['width'])];
        }
        return ['url' => Request::baseUrl() . '/assets/img/og-default.jpg', 'w' => 1200, 'h' => 630];
    }

    // ------------------------------------------------------------------ генерация иконок из загруженного изображения

    /** Квадратное изображение → PNG нужного размера; $bg — подложка [r,g,b], $pad — доля поля вокруг. */
    private static function scale(\GdImage $sq, int $size, ?array $bg = null, float $pad = 0.0): \GdImage
    {
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, $bg ? (int) imagecolorallocate($out, $bg[0], $bg[1], $bg[2]) : (int) imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagealphablending($out, true);
        $in = (int) round($size * (1 - 2 * $pad));
        $off = (int) round(($size - $in) / 2);
        imagecopyresampled($out, $sq, $off, $off, 0, 0, $in, $in, imagesx($sq), imagesy($sq));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        return $out;
    }

    private static function png(\GdImage $im): string
    {
        ob_start();
        imagepng($im, null, 9);
        return (string) ob_get_clean();
    }

    /**
     * Создаёт favicon.ico, favicon-32.png, значок iOS (180), PWA-иконки 192/512 и maskable 512 из «Фавикон / иконка сайта».
     * Файлы кладутся в public/uploads/brand/, а favicon.ico — ещё и в корень сайта (если папка доступна для записи).
     */
    public static function rebuildIcons(): bool
    {
        $m = self::media('site_favicon');
        if (!$m || !Images::available()) {
            return false;
        }
        $src = PUBLIC_PATH . '/uploads/' . $m['path'] . '.' . $m['ext'];
        $raw = is_file($src) ? @file_get_contents($src) : false;
        $im = $raw !== false ? @imagecreatefromstring($raw) : false;
        if (!$im) {
            return false;
        }
        $dir = PUBLIC_PATH . self::DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        // квадрат по центру
        $w = imagesx($im);
        $h = imagesy($im);
        $s = min($w, $h);
        $sq = imagecreatetruecolor($s, $s);
        imagealphablending($sq, false);
        imagesavealpha($sq, true);
        imagefill($sq, 0, 0, (int) imagecolorallocatealpha($sq, 0, 0, 0, 127));
        imagecopy($sq, $im, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 2), $s, $s);

        $white = [255, 255, 255];
        $accent = self::rgb(self::accent());
        $files = [
            'favicon-32.png' => self::png(self::scale($sq, 32)),
            'apple-touch-icon.png' => self::png(self::scale($sq, 180, $white)),                // iOS не любит прозрачность
            'icon-192.png' => self::png(self::scale($sq, 192)),
            'icon-512.png' => self::png(self::scale($sq, 512)),
            'icon-maskable-512.png' => self::png(self::scale($sq, 512, $accent, 0.14)),         // «безопасная зона» для круглых масок Android
        ];
        $png48 = self::png(self::scale($sq, 48));
        $files['favicon.ico'] = pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 48, 48, 0, 0, 1, 32, strlen($png48), 22) . $png48; // ICO с PNG внутри

        foreach ($files as $name => $bin) {
            if (@file_put_contents($dir . '/' . $name, $bin, LOCK_EX) === false) {
                return false;
            }
        }
        // /favicon.ico в корне — его запрашивают поисковые системы и старые браузеры; оригинал сохраняем, чтобы вернуть при сбросе
        $root = PUBLIC_PATH . '/favicon.ico';
        $backup = STORAGE_PATH . '/favicon-default.ico';
        if (is_file($root) && !is_file($backup)) {
            @copy($root, $backup);
        }
        @file_put_contents($root, $files['favicon.ico'], LOCK_EX);
        Settings::set('brand_icon_v', (string) time());
        return true;
    }

    /** Фавикон убрали в настройках: удаляем сгенерированное и возвращаем исходный /favicon.ico. */
    public static function resetIcons(): void
    {
        foreach (glob(PUBLIC_PATH . self::DIR . '/*') ?: [] as $f) {
            @unlink($f);
        }
        $backup = STORAGE_PATH . '/favicon-default.ico';
        if (is_file($backup)) {
            @copy($backup, PUBLIC_PATH . '/favicon.ico');
        }
        Settings::set('brand_icon_v', (string) time());
    }
}
