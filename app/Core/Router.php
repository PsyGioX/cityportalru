<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:array|callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array|callable $handler): void
    {
        $parts = preg_split('/(\{\w+(?::[^}]+)?\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)(?::([^}]+))?\}$/', $part, $m)) {
                $re = match ($m[1]) {
                    'id' => '\d+',
                    'slug' => '[A-Za-z0-9._\-]+',
                    'rest' => '.+',
                    default => $m[2] ?? '[^/]+',
                };
                $regex .= '(?P<' . $m[1] . '>' . $re . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        $this->routes[] = [strtoupper($method), '#^' . $regex . '$#u', $handler];
    }

    public function get(string $p, array|callable $h): void
    {
        $this->add('GET', $p, $h);
    }

    public function post(string $p, array|callable $h): void
    {
        $this->add('POST', $p, $h);
    }

    public function any(string $p, array|callable $h): void
    {
        $this->add('GET', $p, $h);
        $this->add('POST', $p, $h);
    }

    /** @return bool true — маршрут найден и выполнен */
    public function dispatch(string $method, string $path): bool
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $pathMatched = false;
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (!preg_match($regex, $path, $mm)) {
                continue;
            }
            $pathMatched = true;
            if ($m !== $method) {
                continue;
            }
            $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            if (is_callable($handler) && !is_array($handler)) {
                $handler($params);
            } else {
                [$class, $fn] = $handler;
                (new $class())->$fn($params);
            }
            return true;
        }
        if ($pathMatched) {
            header('Allow: GET, POST, HEAD');
            http_response_code(405);
            echo 'Method Not Allowed';
            return true;
        }
        return false;
    }
}
