<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(string $action, string $entity = '', string|int $entityId = '', array|string $details = ''): void
    {
        try {
            $u = Auth::user();
            DB::insert('audit_log', [
                'user_id' => $u['id'] ?? null,
                'username' => $u['username'] ?? '',
                'action' => $action,
                'entity' => $entity,
                'entity_id' => (string) $entityId,
                'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
                'ip' => Request::ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::write('error', 'audit: ' . $e->getMessage());
        }
    }
}
