<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/** Резервная копия БД средствами PHP (не требует mysqldump). Пишет потоково в gzip. */
final class Backup
{
    public static function dump(string $file): int
    {
        $pdo = DB::pdo();
        $gz = gzopen($file, 'wb9');
        if (!$gz) {
            throw new \RuntimeException('Не удалось создать файл копии.');
        }
        gzwrite($gz, "-- Резервная копия " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $rows = 0;
        foreach (DB::col('SHOW TABLES') as $t) {
            $create = DB::one('SHOW CREATE TABLE `' . $t . '`');
            gzwrite($gz, "DROP TABLE IF EXISTS `$t`;\n" . array_values($create)[1] . ";\n\n");
            if ($t === 'sessions') {
                continue; // сессии в копию не попадают
            }
            $st = $pdo->query('SELECT * FROM `' . $t . '`');
            $batch = [];
            while ($r = $st->fetch(\PDO::FETCH_NUM)) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                $rows++;
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($file, 0600);
        return $rows;
    }

    public static function prune(int $keep = 14): void
    {
        $files = glob(STORAGE_PATH . '/backups/*.sql.gz') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f);
        }
    }
}
