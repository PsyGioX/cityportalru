<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public const ROLES = ['admin' => 'Администратор', 'editor' => 'Редактор', 'author' => 'Автор'];

    /** Матрица прав. admin = всё. */
    private const CAPS = [
        'editor' => ['news.publish', 'news.edit_all', 'content', 'moderate', 'seo', 'media', 'news.write'],
        'author' => ['media', 'news.write'],
    ];

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['uid'])) {
            return null;
        }
        $u = DB::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['uid']]);
        // смена пароля завершает все ранее выданные сессии
        if (!$u || ($u['password_changed_at'] && strtotime((string) $u['password_changed_at']) > (int) ($_SESSION['_born'] ?? 0) + 1)) {
            unset($_SESSION['uid']);
            DbSessionHandler::$uid = null;
            return null;
        }
        return self::$user = $u;
    }

    public static function id(): ?int
    {
        return ($u = self::user()) ? (int) $u['id'] : null;
    }

    public static function can(string $cap): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role'] === 'admin' || in_array($cap, self::CAPS[$u['role']] ?? [], true);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT, defined('PASSWORD_ARGON2ID') ? [] : ['cost' => 12]);
    }

    /** Требования к паролю (рекомендации NIST SP 800-63B: длина важнее «сложности»). */
    public static function passwordErrors(string $pw, string $username = ''): array
    {
        $e = [];
        if (mb_strlen($pw) < 10) {
            $e[] = 'Пароль должен быть не короче 10 символов.';
        }
        if (mb_strlen($pw) > 200) {
            $e[] = 'Пароль слишком длинный.';
        }
        $low = mb_strtolower($pw);
        $weak = ['password', 'qwerty', '123456', 'admin', 'korenovsk', 'кореновск', 'йцукен', 'letmein', 'welcome', 'iloveyou', '111111', '000000'];
        foreach ($weak as $w) {
            if (str_contains($low, $w)) {
                $e[] = 'Пароль слишком простой: он содержит распространённое слово или последовательность.';
                break;
            }
        }
        if ($username !== '' && str_contains($low, mb_strtolower($username))) {
            $e[] = 'Пароль не должен содержать логин.';
        }
        if (preg_match('/^(.)\1+$/u', $pw) || count(array_unique(mb_str_split($pw))) < 5) {
            $e[] = 'Используйте больше разных символов.';
        }
        return array_values(array_unique($e));
    }

    /**
     * Шаг 1: проверка логина и пароля с защитой от перебора.
     * @return array{ok:bool,error?:string,user?:array,locked?:bool}
     */
    public static function attempt(string $username, string $password): array
    {
        $ip = Request::ip();
        $uname = mb_strtolower(mb_substr($username, 0, 190));
        $window = date('Y-m-d H:i:s', time() - 900);
        $byUser = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND created_at > ?', [$uname, $window]);
        $byPair = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND success = 0 AND created_at > ?', [$uname, $ip, $window]);
        $byIp = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [$ip, $window]);
        // 5 ошибок с одного адреса на один логин / 20 на логин со всех адресов / 15 с одного адреса.
        // Порог «на логин со всех адресов» выше, чтобы посторонний не мог заблокировать владельца сайта.
        if ($byPair >= 5 || $byUser >= 20 || $byIp >= 15) {
            Audit::log('login.locked', 'user', $uname);
            return ['ok' => false, 'locked' => true, 'error' => 'Слишком много неудачных попыток. Подождите 15 минут и попробуйте снова.'];
        }
        $u = DB::one('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1', [$uname, $uname]);
        // одинаковое время ответа для существующего и несуществующего пользователя
        $dummy = defined('PASSWORD_ARGON2ID') ? '$argon2id$v=19$m=65536,t=4,p=1$c3NuL3dNZHpSZE5CcFhjeg$xSzlcpY8pWdbcdIpunmzNwvyFPvUvX/5l7XbeeSWXGA' : '$2y$12$wttA2cxna1c3VA9jUHIqPuT9Grix6Fee13rqIGHmbauhVrS.mbmp.';
        $hash = $u['password_hash'] ?? $dummy;
        $valid = password_verify($password, $hash) && $u;
        DB::insert('login_attempts', ['username' => $uname, 'ip' => $ip, 'success' => $valid ? 1 : 0, 'created_at' => date('Y-m-d H:i:s')]);
        if (!$valid) {
            return ['ok' => false, 'error' => 'Неверный логин или пароль.'];
        }
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        if (password_needs_rehash($u['password_hash'], $algo)) {
            DB::update('users', ['password_hash' => self::hashPassword($password)], 'id = ?', [$u['id']]);
        }
        return ['ok' => true, 'user' => $u];
    }

    /**
     * Общий ограничитель неудачных попыток (2FA, подтверждение паролем в профиле): $max ошибок за 15 минут.
     * Нужен, потому что счётчик сеанса обходится повторным входом, если пароль известен.
     */
    public static function throttled(string $bucket, int $max = 10): bool
    {
        return (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND created_at > ?', [$bucket, date('Y-m-d H:i:s', time() - 900)]) >= $max;
    }

    public static function recordFailure(string $bucket): void
    {
        DB::insert('login_attempts', ['username' => mb_substr($bucket, 0, 190), 'ip' => Request::ip(), 'success' => 0, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** Шаг 2: открывает сессию. Для пользователей с 2FA вызывается только после проверки кода. */
    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['_born'] = time();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
        DbSessionHandler::$uid = (int) $user['id'];
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => Request::ip()], 'id = ?', [$user['id']]);
        self::$loaded = false;
        self::$user = null;
        Audit::log('login', 'user', $user['id']);
    }

    public static function logout(): void
    {
        Audit::log('logout', 'user', self::id() ?? '');
        self::$loaded = false;
        self::$user = null;
        DbSessionHandler::$uid = null;
        Session::destroy();
    }

    public static function verifySecondFactor(array $user, string $code): bool
    {
        $code = trim($code);
        // резервный код: xxxx-xxxx
        if (preg_match('/^[a-z0-9]{4}-?[a-z0-9]{4}$/i', $code)) {
            $codes = json_decode((string) $user['recovery_codes'], true) ?: [];
            $norm = strtolower(str_replace('-', '', $code));
            foreach ($codes as $i => $hash) {
                if (password_verify($norm, $hash)) {
                    unset($codes[$i]);
                    DB::update('users', ['recovery_codes' => json_encode(array_values($codes))], 'id = ?', [$user['id']]);
                    Audit::log('2fa.recovery_used', 'user', $user['id']);
                    return true;
                }
            }
            return false;
        }
        $secret = Security::decrypt((string) $user['totp_secret']);
        $step = Totp::verify($secret, $code, $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null);
        if ($step === null) {
            return false;
        }
        DB::update('users', ['totp_last_step' => $step], 'id = ?', [$user['id']]);
        return true;
    }

    public static function newRecoveryCodes(): array
    {
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtolower(substr(bin2hex(random_bytes(4)), 0, 8));
            $plain[] = substr($c, 0, 4) . '-' . substr($c, 4);
            $hashes[] = password_hash($c, PASSWORD_BCRYPT, ['cost' => 10]);
        }
        return [$plain, $hashes];
    }
}
