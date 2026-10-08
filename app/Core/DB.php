<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Тонкая обёртка над PDO: только подготовленные запросы. */
final class DB
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        $dsn = !empty($cfg['socket'])
            ? 'mysql:unix_socket=' . $cfg['socket'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4'
            : 'mysql:host=' . ($cfg['host'] ?? 'localhost') . ';port=' . (int) ($cfg['port'] ?? 3306) . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) $cfg['user'], (string) ($cfg['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $offset = (new \DateTime('now'))->format('P');
        $pdo->exec("SET NAMES utf8mb4, time_zone = '{$offset}', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect((array) Config::get('db', []));
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function run(string $sql, array $params): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach (array_values($params) as $i => $v) {
            $type = match (true) {
                is_int($v) => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($i + 1, is_bool($v) ? (int) $v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::run($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function col(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new \InvalidArgumentException('Недопустимое имя поля: ' . $name);
        }
        return '`' . $name . '`';
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_map([self::class, 'ident'], array_keys($data)));
        $ph = implode(',', array_fill(0, count($data), '?'));
        self::run('INSERT INTO ' . self::ident($table) . " ($cols) VALUES ($ph)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => self::ident($c) . '=?', array_keys($data)));
        return self::run('UPDATE ' . self::ident($table) . " SET $set WHERE $where", [...array_values($data), ...$params])->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM ' . self::ident($table) . " WHERE $where", $params)->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Список «?,?,?» для IN (...) */
    public static function in(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }
}
