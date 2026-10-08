<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class AdminController
{
    protected ?array $user = null;

    public function __construct()
    {
        Session::start();
        if (Request::isPost() && !Session::verifyCsrf()) {
            $this->fail(419, 'Сессия устарела или запрос отправлен с другого сайта. Обновите страницу и повторите действие.');
        }
        if ($this->requiresAuth()) {
            $this->user = Auth::user();
            if (!$this->user) {
                Response::redirect(admin_url('login') . (Request::method() === 'GET' && Request::path() !== '/' . admin_path() ? '?next=' . rawurlencode(Request::path()) : ''));
            }
            if (\App\Core\Settings::bool('require_2fa') && !$this->user['totp_enabled'] && in_array($this->user['role'], ['admin', 'editor'], true)
                && !str_starts_with(Request::path(), admin_url('profile')) && Request::path() !== admin_url('logout')) {
                Session::flash('warn', 'Для администраторов и редакторов обязательна двухфакторная аутентификация. Включите её, чтобы продолжить работу.');
                Response::redirect(admin_url('profile'));
            }
            if ($this->user['must_change_password'] && !str_starts_with(Request::path(), admin_url('profile'))) {
                Session::flash('warn', 'Администратор требует сменить пароль.');
                Response::redirect(admin_url('profile'));
            }
        }
    }

    protected function requiresAuth(): bool
    {
        return true;
    }

    protected function fail(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/admin.css"><body class="install"><main class="install__box"><h1>'
            . ($code === 403 ? 'Доступ запрещён' : 'Ошибка ' . $code) . '</h1><p>' . e($message) . '</p><p><a class="btn" href="' . e(admin_url()) . '">В админ-панель</a></p></main></body>';
        exit;
    }

    protected function need(string $cap): void
    {
        if (!Auth::can($cap)) {
            $this->fail(403, 'У вашей роли нет прав на это действие.');
        }
    }

    protected function view(string $view, array $vars = [], string $title = ''): never
    {
        $vars['title'] = $title;
        $vars['user'] = $this->user;
        $vars['flash'] = Session::pullFlash();
        View::page('admin/' . $view, $vars, 'admin/layout');
    }

    protected function flash(string $type, string $msg): void
    {
        Session::flash($type, $msg);
    }

    protected function go(string $path = '', ?string $type = null, ?string $msg = null): never
    {
        if ($type) {
            $this->flash($type, (string) $msg);
        }
        Response::redirect(admin_url($path));
    }

    protected function datetime(string $key, ?string $default = null): ?string
    {
        $v = Request::post($key);
        if ($v === '') {
            return $default;
        }
        $t = strtotime(str_replace('T', ' ', $v));
        return $t ? date('Y-m-d H:i:s', $t) : $default;
    }
}
