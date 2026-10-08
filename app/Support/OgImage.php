<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

/** Автогенерация обложки 1200×630 для материалов без фото (превью в соцсетях и мессенджерах). */
final class OgImage
{
    public static function file(string $key, string $title, string $meta): ?string
    {
        $out = STORAGE_PATH . '/og/' . preg_replace('/[^a-z0-9_-]/i', '', $key) . '-' . substr(md5($title . $meta), 0, 8) . '.jpg';
        if (is_file($out)) {
            return $out;
        }
        $font = STORAGE_PATH . '/og/Montserrat-ExtraBold.ttf';
        if (!function_exists('imagettftext') || !is_file($font)) {
            return null;
        }
        $im = imagecreatetruecolor(1200, 630);
        $red = imagecolorallocate($im, 227, 24, 43);
        $white = imagecolorallocate($im, 255, 255, 255);
        $soft = imagecolorallocate($im, 255, 214, 218);
        $sun = imagecolorallocate($im, 255, 197, 31);
        imagefill($im, 0, 0, $red);
        imagefilledrectangle($im, 0, 0, 24, 630, $sun);
        $mark = PUBLIC_PATH . '/assets/img/og-mark.png';
        if (is_file($mark) && ($mk = @imagecreatefrompng($mark))) {
            imagecopyresampled($im, $mk, 80, 70, 0, 0, 104, 80, imagesx($mk), imagesy($mk));
        }
        imagettftext($im, 26, 0, 204, 125, $white, $font, (string) \App\Core\Settings::get('site_name', 'Твой Кореновск'));
        $size = mb_strlen($title) > 110 ? 40 : (mb_strlen($title) > 70 ? 48 : 58);
        $lines = self::wrap($title, $font, $size, 1040, 5);
        $y = 270;
        foreach ($lines as $l) {
            imagettftext($im, $size, 0, 80, $y, $white, $font, $l);
            $y += (int) ($size * 1.28);
        }
        imagettftext($im, 24, 0, 80, 580, $soft, $font, $meta);
        imagejpeg($im, $out, 88);
        return $out;
    }

    private static function wrap(string $text, string $font, int $size, int $maxW, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            $b = imagettfbbox($size, 0, $font, $try);
            if ($b && ($b[2] - $b[0]) > $maxW && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.;:') . '…';
        }
        return $lines;
    }
}
