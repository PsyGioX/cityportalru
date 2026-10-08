<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController extends AdminController
{
    protected function requiresAuth(): bool
    {
        return false;
    }

    private function safeNext(): string
    {
        $n = Request::query('next') ?: Request::post('next');
        return ($n !== '' && preg_match('#^/[A-Za-z0-9/_\-?=&.%]*$#', $n) && !str_starts_with($n, '//') && str_starts_with($n, '/' . admin_path())) ? $n : admin_url();
    }

    public function login(): void
    {
        if (Auth::user()) {
            Response::redirect(admin_url());
        }
        $error = '';
        if (Request::isPost()) {
            $r = Auth::attempt(Request::line('username', 190), (string) ($_POST['password'] ?? ''));
            if ($r['ok']) {
                $u = $r['user'];
                if ($u['totp_enabled']) {
                    Session::regenerate();
                    Session::set('pending_uid', (int) $u['id']);
                    Session::set('pending_at', time());
                    Session::set('pending_next', $this->safeNext());
                    Response::redirect(admin_url('2fa'));
                }
                Auth::login($u);
                Response::redirect($this->safeNext());
            }
            $error = $r['error'] ?? 'Ошибка входа.';
            usleep(random_int(150000, 350000));
        }
        $this->page('login', ['error' => $error, 'next' => $this->safeNext()], 'Вход');
    }

    public function twoFactor(): void
    {
        $uid = (int) Session::get('pending_uid', 0);
        if (!$uid || time() - (int) Session::get('pending_at', 0) > 300) {
            Session::forget('pending_uid');
            Response::redirect(admin_url('login'));
        }
        $error = '';
        if (Request::isPost()) {
            $u = DB::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$uid]);
            if (Auth::throttled('2fa:' . $uid, 10)) { // не зависит от сеанса: повторный вход счётчик не сбрасывает
                Session::forget('pending_uid');
                Audit::log('2fa.locked', 'user', $uid);
                Session::flash('err', 'Слишком много неверных кодов. Подождите 15 минут.');
                Response::redirect(admin_url('login'));
            }
            $tries = (int) Session::get('2fa_tries', 0) + 1;
            Session::set('2fa_tries', $tries);
            if ($tries > 5) {
                Session::forget('pending_uid');
                Audit::log('2fa.locked', 'user', $uid);
                Response::redirect(admin_url('login'));
            }
            if ($u && Auth::verifySecondFactor($u, Request::line('code', 20))) {
                $next = (string) Session::get('pending_next', admin_url());
                Session::forget('2fa_tries');
                Auth::login($u);
                Response::redirect($next);
            }
            Auth::recordFailure('2fa:' . $uid);
            $error = 'Неверный код. Проверьте время на телефоне или используйте резервный код.';
        }
        $this->page('twofactor', ['error' => $error], 'Код подтверждения');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        Session::flash('ok', 'Вы вышли из системы.');
        Response::redirect(admin_url('login'));
    }

    private function page(string $view, array $vars, string $title): never
    {
        $vars['title'] = $title;
        $vars['flash'] = Session::pullFlash();
        View::page('admin/' . $view, $vars, 'admin/bare');
    }
}
