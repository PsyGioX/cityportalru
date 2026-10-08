<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use RuntimeException;

/**
 * Безопасная загрузка изображений. Файл НЕ сохраняется как есть: он проверяется (тип, размеры),
 * перекодируется через GD (удаляются EXIF/GPS и любые вложенные данные) и режется на WebP-варианты.
 */
final class Images
{
    public const MAX_BYTES = 12 * 1024 * 1024;
    public const MAX_PIXELS = 24_000_000;
    public const MAX_WIDTH = 2000;
    public const WIDTHS = [480, 800, 1200, 1600];

    /** Загрузка изображений доступна, только если на сервере есть GD. WebP для неё не обязателен. */
    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    public static function webpSupported(): bool
    {
        return function_exists('imagewebp');
    }

    public static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл больше разрешённого размера (' . bytes_h(self::MAX_BYTES) . ').',
            UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью. Попробуйте ещё раз.',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
            default => 'Не удалось загрузить файл (код ' . $code . ').',
        };
    }

    /** @param array{name?:string,tmp_name:string,error:int,size:int} $file */
    public static function store(array $file, ?int $userId, string $alt = '', bool $trusted = false): array
    {
        if (!self::available()) {
            throw new RuntimeException('На сервере не установлено расширение PHP GD, поэтому загрузка изображений отключена. Включите php-gd в панели хостинга (остальные функции сайта работают).');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $tmp = (string) $file['tmp_name'];
        if (!$trusted && !is_uploaded_file($tmp)) {
            throw new RuntimeException('Недопустимый источник файла.');
        }
        if ((int) $file['size'] > self::MAX_BYTES || filesize($tmp) > self::MAX_BYTES) {
            throw new RuntimeException('Файл больше ' . bytes_h(self::MAX_BYTES) . '.');
        }
        $info = @getimagesize($tmp);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
        if (!$info || !isset($types[$info[2]])) {
            throw new RuntimeException('Разрешены только изображения JPEG, PNG, WebP и GIF.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($mime !== $types[$info[2]]) {
            throw new RuntimeException('Тип файла не совпадает с его содержимым.');
        }
        [$w, $h] = $info;
        // Тот же файл уже загружали — не создаём копию, возвращаем существующую запись.
        $hash = sha1_file($tmp) ?: null;
        if ($hash && ($dup = DB::one('SELECT * FROM media WHERE file_hash = ? LIMIT 1', [$hash])) && is_file(PUBLIC_PATH . '/uploads/' . $dup['path'] . '.' . $dup['ext'])) {
            return $dup + ['duplicate' => true];
        }
        if ($w < 16 || $h < 16 || $w * $h > self::MAX_PIXELS) {
            throw new RuntimeException('Недопустимые размеры изображения (от 16 пикселей до ' . number_format(self::MAX_PIXELS / 1e6, 0) . ' Мпикс).');
        }
        $need = (int) ($w * $h * 5.5) + 32 * 1024 * 1024; // запас на декодирование + копии
        $limit = ini_get('memory_limit');
        $bytes = $limit === '-1' ? PHP_INT_MAX : (int) $limit * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr((string) $limit, -1))] ?? 1);
        if ($bytes < $need) {
            @ini_set('memory_limit', (string) ceil($need / 1048576) . 'M');
            $now = ini_get('memory_limit');
            if ($now !== '-1' && (int) $now * 1048576 < $need) {
                throw new RuntimeException('Изображение слишком большое для обработки на этом сервере. Уменьшите его и повторите.');
            }
        }
        $raw = (string) file_get_contents($tmp);
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(8));
        $dir = PUBLIC_PATH . '/uploads/' . dirname($rel);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Папка uploads недоступна для записи.');
        }
        $base = PUBLIC_PATH . '/uploads/' . $rel;

        // Анимированный GIF сохраняем как есть (после проверки), остальное перекодируем.
        if ($info[2] === IMAGETYPE_GIF && substr_count($raw, "\x00\x21\xF9\x04") > 1) {
            if (preg_match('/<\?php|<\?=|<script/i', $raw)) {
                throw new RuntimeException('Файл отклонён проверкой безопасности.');
            }
            file_put_contents($base . '.gif', $raw);
            return self::insert($rel, 'gif', $w, $h, strlen($raw), [], $alt, $userId, $hash);
        }

        $im = @imagecreatefromstring($raw);
        if (!$im) {
            throw new RuntimeException('Не удалось прочитать изображение.');
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $ex = @exif_read_data($tmp);
            $rot = match ((int) ($ex['Orientation'] ?? 1)) {
                3 => 180, 6 => -90, 8 => 90, default => 0,
            };
            if ($rot) {
                $im = imagerotate($im, $rot, 0) ?: $im;
            }
        }
        $alpha = self::hasAlpha($raw, $info[2], $im);
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w > self::MAX_WIDTH) {
            $im = self::resize($im, self::MAX_WIDTH, (int) round($h * self::MAX_WIDTH / $w), $alpha);
            $w = imagesx($im);
            $h = imagesy($im);
        }
        if (!$alpha) {
            $flat = imagecreatetruecolor($w, $h);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
            $im = $flat;
        } else {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }
        $ext = $alpha ? 'png' : 'jpg';
        $alpha ? imagepng($im, $base . '.png', 8) : imagejpeg($im, $base . '.jpg', 85);
        if (!$alpha) {
            imageinterlace($im, true);
        }
        $widths = array_values(array_unique([...array_filter(self::WIDTHS, fn($x) => $x < $w), $w]));
        sort($widths);
        // Варианты для srcset: WebP, а если GD собран без WebP — JPEG (фото) или PNG (прозрачность)
        $vext = self::webpSupported() ? 'webp' : ($alpha ? 'png' : 'jpg');
        foreach ($widths as $x) {
            $target = $base . '-' . $x . '.' . $vext;
            if ($x === $w && $vext === $ext) {
                copy($base . '.' . $ext, $target);
                continue;
            }
            $v = $x === $w ? $im : self::resize($im, $x, max(1, (int) round($h * $x / $w)), $alpha);
            match ($vext) {
                'webp' => imagewebp($v, $target, 78),
                'png' => imagepng($v, $target, 8),
                default => imagejpeg($v, $target, 82),
            };
        }
        $size = array_sum(array_map('filesize', glob($base . '*') ?: []));
        return self::insert($rel, $ext, $w, $h, (int) $size, $vext === 'webp' ? $widths : ['e' => $vext, 'w' => $widths], $alt, $userId, $hash);
    }

    private static function insert(string $rel, string $ext, int $w, int $h, int $size, array $widths, string $alt, ?int $uid, ?string $hash = null): array
    {
        $id = DB::insert('media', [
            'path' => $rel, 'ext' => $ext, 'width' => $w, 'height' => $h, 'size' => $size, 'alt' => mb_substr($alt, 0, 255),
            'widths' => json_encode($widths), 'file_hash' => $hash, 'uploaded_by' => $uid, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return DB::one('SELECT * FROM media WHERE id = ?', [$id]) ?? [];
    }

    private static function hasAlpha(string $raw, int $type, \GdImage $im): bool
    {
        return match ($type) {
            IMAGETYPE_PNG => in_array(ord($raw[25] ?? "\0"), [4, 6], true) || str_contains(substr($raw, 0, 2048), 'tRNS'),
            IMAGETYPE_GIF => imagecolortransparent($im) >= 0,
            IMAGETYPE_WEBP => str_contains(substr($raw, 0, 64), 'ALPH') || (str_starts_with(substr($raw, 12, 4), 'VP8X') && (ord($raw[20] ?? "\0") & 0x10)),
            default => false,
        };
    }

    private static function resize(\GdImage $src, int $nw, int $nh, bool $alpha): \GdImage
    {
        $dst = imagecreatetruecolor($nw, $nh);
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, imagesx($src), imagesy($src));
        return $dst;
    }

    public static function delete(int $id): void
    {
        $m = DB::one('SELECT * FROM media WHERE id = ?', [$id]);
        if (!$m) {
            return;
        }
        $base = PUBLIC_PATH . '/uploads/' . $m['path'];
        if (!str_contains($m['path'], '..')) {
            foreach (array_merge(glob($base . '.*') ?: [], glob($base . '-*.*') ?: []) as $f) {
                @unlink($f);
            }
        }
        DB::delete('media', 'id = ?', [$id]);
    }

    public static function usage(int $id): int
    {
        return (int) DB::val(
            'SELECT (SELECT COUNT(*) FROM news WHERE cover_media_id = ?) + (SELECT COUNT(*) FROM news_media WHERE media_id = ?)
                  + (SELECT COUNT(*) FROM events WHERE cover_media_id = ?)
                  + (SELECT COUNT(*) FROM news WHERE body LIKE ?)
                  + (SELECT COUNT(*) FROM settings WHERE k IN (\'site_logo\', \'site_logo_footer\', \'site_favicon\', \'og_image\') AND v = ?)
                  + (SELECT COUNT(*) FROM links WHERE icon LIKE ?)',
            [$id, $id, $id, '%' . (string) DB::val('SELECT path FROM media WHERE id = ?', [$id]) . '%', (string) $id, '%' . (string) DB::val('SELECT path FROM media WHERE id = ?', [$id]) . '%']
        );
    }
}
