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

final class UsersController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('users');
    }

    public function index(): void
    {
        $this->view('users', ['rows' => DB::all('SELECT u.*, (SELECT COUNT(*) FROM news n WHERE n.author_id = u.id) AS news_cnt FROM users u ORDER BY u.is_active DESC, u.role, u.username')], 'Пользователи');
    }

    public function edit(array $a = []): void
    {
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        $u = $id ? DB::one('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($id && !$u) {
            $this->go('users', 'err', 'Пользователь не найден.');
        }
        $errors = [];
        $self = $u && (int) $u['id'] === (int) $this->user['id'];
        if (Request::isPost()) {
            $username = strtolower(Request::line('username', 40));
            $email = strtolower(Request::line('email', 190));
            $role = array_key_exists(Request::post('role'), Auth::ROLES) ? Request::post('role') : 'author';
            $active = Request::bool('is_active');
            $pw = (string) ($_POST['password'] ?? '');
            if (!preg_match('/^[a-z0-9_.\-]{3,40}$/', $username)) {
                $errors['username'] = 'Логин: 3–40 символов a–z, 0–9, . _ -';
            } elseif (DB::val('SELECT 1 FROM users WHERE username = ?' . ($u ? ' AND id <> ' . (int) $u['id'] : ''), [$username])) {
                $errors['username'] = 'Такой логин уже занят.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Некорректный e-mail.';
            } elseif (DB::val('SELECT 1 FROM users WHERE email = ?' . ($u ? ' AND id <> ' . (int) $u['id'] : ''), [$email])) {
                $errors['email'] = 'Такой e-mail уже используется.';
            }
            if (!$u || $pw !== '') {
                $pe = Auth::passwordErrors($pw, $username);
                if ($pe) {
                    $errors['password'] = implode(' ', $pe);
                }
            }
            if ($self && (!$active || $role !== 'admin')) {
                $errors['role'] = 'Нельзя понизить или отключить самого себя.';
            }
            if ($u && $u['role'] === 'admin' && ($role !== 'admin' || !$active) && (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$u['id']]) === 0) {
                $errors['role'] = 'Должен остаться хотя бы один активный администратор.';
            }
            if (!$errors) {
                $data = ['username' => $username, 'email' => $email, 'display_name' => Request::line('display_name', 120) ?: $username, 'role' => $role, 'is_active' => $active,
                    'must_change_password' => Request::bool('must_change_password'), 'updated_at' => date('Y-m-d H:i:s')];
                if ($pw !== '') {
                    $data['password_hash'] = Auth::hashPassword($pw);
                    $data['password_changed_at'] = date('Y-m-d H:i:s');
                }
                if ($u && Request::bool('reset_2fa')) {
                    $data += ['totp_enabled' => 0, 'totp_secret' => null, 'recovery_codes' => null, 'totp_last_step' => null];
                }
                if ($u) {
                    DB::update('users', $data, 'id = ?', [$id]);
                    if (!$active || $pw !== '') {
                        DB::delete('sessions', 'user_id = ?', [$id]);
                    }
                } else {
                    $id = DB::insert('users', $data + ['password_hash' => $data['password_hash'], 'created_at' => date('Y-m-d H:i:s')]);
                }
                Audit::log(($u ? 'update' : 'create') . '.user', 'user', $id, $username . ' (' . $role . ')' . ($pw !== '' ? ' пароль изменён' : ''));
                $this->go('users', 'ok', 'Пользователь сохранён.');
            }
            $u = array_merge($u ?? [], ['username' => $username, 'email' => $email, 'role' => $role, 'is_active' => $active, 'display_name' => Request::line('display_name', 120)]);
        }
        $u ??= ['username' => '', 'email' => '', 'display_name' => '', 'role' => 'author', 'is_active' => 1, 'must_change_password' => 1, 'totp_enabled' => 0];
        $this->view('user-edit', ['u' => $u, 'id' => $id, 'errors' => $errors, 'self' => $self,
            'sessions' => $id ? DB::all('SELECT ip, user_agent, last_activity FROM sessions WHERE user_id = ? ORDER BY last_activity DESC', [$id]) : []], $id ? 'Пользователь' : 'Новый пользователь');
    }
}
