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
use App\Core\Security;
use App\Core\Session;
use App\Core\Totp;

final class ProfileController extends AdminController
{
    public function index(): void
    {
        $errors = [];
        $u = $this->user;
        if (Request::isPost()) {
            $cur = (string) ($_POST['current'] ?? '');
            $new = (string) ($_POST['password'] ?? '');
            $name = Request::line('display_name', 120);
            if ($name !== '' && $name !== $u['display_name'] && $new === '') {
                DB::update('users', ['display_name' => $name, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$u['id']]);
                $this->go('profile', 'ok', 'Профиль обновлён.');
            }
            if (Auth::throttled('pw:' . $u['id'], 10)) {
                $errors['current'] = 'Слишком много неверных попыток. Подождите 15 минут.';
            } elseif (!password_verify($cur, $u['password_hash'])) {
                Auth::recordFailure('pw:' . $u['id']);
                $errors['current'] = 'Текущий пароль указан неверно.';
            }
            $pe = Auth::passwordErrors($new, $u['username']);
            if ($pe) {
                $errors['password'] = implode(' ', $pe);
            }
            if ($new !== (string) ($_POST['password2'] ?? '')) {
                $errors['password2'] = 'Пароли не совпадают.';
            }
            if (!$errors) {
                DB::update('users', ['password_hash' => Auth::hashPassword($new), 'password_changed_at' => date('Y-m-d H:i:s'), 'must_change_password' => 0, 'display_name' => $name ?: $u['display_name']], 'id = ?', [$u['id']]);
                // завершаем все остальные сессии, текущую оставляем
                $cur = hash('sha256', session_id());
                DB::exec('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$u['id'], $cur]);
                $_SESSION['_born'] = time();
                Audit::log('password.change', 'user', $u['id']);
                $this->go('profile', 'ok', 'Пароль изменён. Остальные сеансы завершены.');
            }
        }
        $pending = null;
        if (!$u['totp_enabled']) {
            $pending = (string) Session::get('totp_pending', '');
            if ($pending === '') {
                Session::set('totp_pending', $pending = Totp::generateSecret());
            }
        }
        $codes = Session::get('new_recovery_codes');
        Session::forget('new_recovery_codes');
        $this->view('profile', [
            'u' => $u, 'errors' => $errors, 'pending' => $pending, 'codes' => $codes,
            'uri' => $pending ? Totp::uri($pending, $u['username'], (string) setting('site_name', 'Админ-панель')) : '',
            'sessions' => DB::all('SELECT id, ip, user_agent, last_activity, created_at FROM sessions WHERE user_id = ? ORDER BY last_activity DESC', [$u['id']]),
            'currentSid' => hash('sha256', session_id()), 'recoveryLeft' => count(json_decode((string) $u['recovery_codes'], true) ?: []),
        ], 'Профиль и безопасность');
    }

    public function twoFactor(array $a): void
    {
        $u = $this->user;
        if ($a['action'] === 'enable') {
            $secret = (string) Session::get('totp_pending', '');
            $step = $secret !== '' ? Totp::verify($secret, Request::line('code', 10)) : null;
            if ($step === null) {
                $this->go('profile', 'err', 'Код не подошёл. Проверьте, что время на телефоне точное, и попробуйте ещё раз.');
            }
            [$plain, $hashes] = Auth::newRecoveryCodes();
            DB::update('users', ['totp_secret' => Security::encrypt($secret), 'totp_enabled' => 1, 'totp_last_step' => $step, 'recovery_codes' => json_encode($hashes)], 'id = ?', [$u['id']]);
            Session::forget('totp_pending');
            Session::set('new_recovery_codes', $plain);
            Audit::log('2fa.enable', 'user', $u['id']);
            $this->go('profile', 'ok', '2FA включена. Сохраните резервные коды!');
        }
        if (Auth::throttled('pw:' . $u['id'], 10) || !password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) {
            Auth::recordFailure('pw:' . $u['id']);
            $this->go('profile', 'err', 'Для отключения 2FA введите текущий пароль (или подождите 15 минут после нескольких ошибок).');
        }
        DB::update('users', ['totp_enabled' => 0, 'totp_secret' => null, 'recovery_codes' => null, 'totp_last_step' => null], 'id = ?', [$u['id']]);
        Audit::log('2fa.disable', 'user', $u['id']);
        $this->go('profile', 'warn', '2FA отключена.');
    }

    public function revoke(array $a): void
    {
        $sid = preg_replace('/[^a-f0-9]/', '', (string) $a['id']);
        if ($sid !== hash('sha256', session_id())) {
            DB::delete('sessions', 'id = ? AND user_id = ?', [$sid, $this->user['id']]);
            Audit::log('session.revoke', 'user', $this->user['id']);
        }
        $this->go('profile', 'ok', 'Сеанс завершён.');
    }
}
