<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public int $pages;
    public int $offset;

    public function __construct(public int $total, public int $perPage, public int $page)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = max(1, min($page, $this->pages));
        $this->offset = ($this->page - 1) * $perPage;
    }

    public function url(int $page, string $base): string
    {
        $q = $_GET;
        unset($q['page']);
        if ($page > 1) {
            $q['page'] = $page;
        }
        return $base . ($q ? '?' . http_build_query($q) : '');
    }

    /** Список номеров страниц с многоточиями: 1 … 4 5 [6] 7 8 … 20 */
    public function window(int $around = 2): array
    {
        $out = [];
        for ($i = 1; $i <= $this->pages; $i++) {
            if ($i === 1 || $i === $this->pages || abs($i - $this->page) <= $around) {
                $out[] = $i;
            } elseif (end($out) !== null) {
                if (end($out) !== 0) {
                    $out[] = 0;
                }
            }
        }
        return $out;
    }
}
