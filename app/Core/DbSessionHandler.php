<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

/** Сессии в БД: можно показать «активные устройства» и завершить любую сессию. ID хранится как SHA-256. */
final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public static ?int $uid = null;

    private static function key(string $id): string
    {
        return hash('sha256', $id);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $v = DB::val('SELECT payload FROM sessions WHERE id = ?', [self::key($id)]);
        return $v === null ? '' : (string) $v;
    }

    public function write(string $id, string $data): bool
    {
        DB::exec(
            'INSERT INTO sessions (id, user_id, payload, ip, user_agent, created_at, last_activity) VALUES (?,?,?,?,?,NOW(),?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), user_id = VALUES(user_id), last_activity = VALUES(last_activity), ip = VALUES(ip)',
            [self::key($id), self::$uid, $data, Request::ip(), Request::ua(), time()]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        DB::exec('DELETE FROM sessions WHERE id = ?', [self::key($id)]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return DB::exec('DELETE FROM sessions WHERE last_activity < ?', [time() - $max_lifetime]);
    }

    public function validateId(string $id): bool
    {
        return DB::val('SELECT 1 FROM sessions WHERE id = ?', [self::key($id)]) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
