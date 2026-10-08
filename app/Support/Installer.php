<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Settings;
use PDO;

/**
 * Логика установки: подсказки по окружению, проверка БД, создание таблиц, профиль данных (пустой сайт /
 * данные города Кореновск / демо-материалы), настройки сайта и города, администратор, config.php.
 */
final class Installer
{
    /** Если config.php не удалось записать — сюда попадает его готовый текст для ручного копирования. */
    public static ?string $manualConfig = null;

    /** Профили данных: код => [название, описание]. */
    public const PROFILES = [
        'empty' => ['Чистый сайт', 'Только таблицы, меню, базовые страницы («О редакции», «Контакты», «Принципы») и ваши настройки. Подходит для любого города.'],
        'korenovsk' => ['Данные города Кореновск', 'Чистый сайт + справочные данные Кореновска: рубрики, страницы «О Кореновске» и «Экстренные службы», ссылки на администрации (database/korenovsk.sql).'],
        'demo' => ['Кореновск + демо-материалы', '«Данные города» + 11 статей, фото, афиша-ссылки и сервисы из прежнего сайта (database/seed.sql). Удобно, чтобы сразу увидеть, как всё выглядит.'],
    ];

    /** @return array{0:string,1:string} [уровень ok|warn|bad, пояснение] */
    public static function phpStatus(): array
    {
        $v = PHP_VERSION_ID;
        if ($v >= 80300) {
            return ['ok', ''];
        }
        if ($v >= 80200) {
            return ['warn', 'PHP 8.2 получает только исправления безопасности и лишь до 31.12.2026. Выберите PHP 8.3, 8.4 или 8.5 в панели хостинга'];
        }
        return ['bad', 'Эта ветка PHP снята с поддержки (PHP 8.1 — 31.12.2025): уязвимости в ней не исправляются. Переключитесь на PHP 8.3, 8.4 или 8.5'];
    }

    /** Оценка версии СУБД по состоянию на октябрь 2026. @return array{0:string,1:string} */
    public static function dbStatus(string $version): array
    {
        $maria = stripos($version, 'mariadb') !== false;
        if (!preg_match('/(\d+)\.(\d+)/', $version, $m)) {
            return ['ok', ''];
        }
        $n = (int) $m[1] * 100 + (int) $m[2];
        if ($maria) {
            if ($n < 1011) {
                return ['bad', 'MariaDB ниже 10.11 снята с поддержки (10.6 — 06.07.2026). Нужна 10.11 LTS, 11.4 LTS или 11.8 LTS'];
            }
            return ['ok', ''];
        }
        if ($n < 804) {
            return ['bad', 'MySQL 8.0 снят с поддержки 30.04.2026 (5.7 — в 2023). Нужен MySQL 8.4 LTS или новее'];
        }
        return ['ok', ''];
    }

    /**
     * Проверки окружения — только подсказки: ничего не блокирует установку.
     * Каждый пункт: [название, выполнено, что будет, если не выполнено]
     * @return array<int,array{0:string,1:bool,2:string}>
     */
    public static function requirements(): array
    {
        $w = fn(string $p) => is_dir($p) ? is_writable($p) : (is_dir(dirname($p)) && is_writable(dirname($p)));
        [$lvl, $why] = self::phpStatus();
        return [
            ['PHP ' . PHP_VERSION, $lvl === 'ok', $why],
            ['Расширение pdo_mysql', extension_loaded('pdo_mysql'), 'Без него сайт не сможет подключиться к MySQL — включите в панели хостинга'],
            ['Расширения mbstring, dom, openssl, fileinfo', extension_loaded('mbstring') && extension_loaded('dom') && extension_loaded('openssl') && extension_loaded('fileinfo'), 'Часть функций (очистка HTML, шифрование ключей, проверка файлов) может не работать'],
            ['Расширение gd', extension_loaded('gd'), 'Сайт работает, но загружать фото и создавать иконки из логотипа нельзя'],
            ['WebP в GD', function_exists('imagewebp'), 'Не страшно: фото будут нарезаться в JPEG/PNG'],
            ['Расширение curl', extension_loaded('curl'), 'Не будут работать погода (WeatherAPI) и уведомление поисковиков (IndexNow); сайт работает'],
            ['Расширение exif', function_exists('exif_read_data'), 'Фото с телефона могут не поворачиваться автоматически'],
            ['Запись в config/', $w(BASE_PATH . '/config'), 'Установщик покажет готовый config.php, его нужно будет сохранить вручную'],
            ['Запись в storage/ и public/uploads/', $w(STORAGE_PATH) && $w(PUBLIC_PATH . '/uploads'), 'Не будут работать кэш, журналы и загрузка фото'],
            ['Запись в public/', $w(PUBLIC_PATH), 'sitemap.xml, robots.txt и favicon.ico будут отдаваться динамически/не обновляться — это нормально'],
            ['HTTPS', \App\Core\Request::isHttps(), 'Сайт открыт по http: вход в админку и cookie не защищены. На боевом сайте включите HTTPS (бесплатный сертификат Let\'s Encrypt есть у большинства хостингов)'],
        ];
    }

    /** Значения формы по умолчанию. */
    public static function defaults(string $detectedUrl): array
    {
        return ['db_host' => 'localhost', 'db_port' => '3306', 'db_socket' => '', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
            'site_url' => $detectedUrl, 'admin_path' => 'admin', 'admin_user' => 'admin', 'admin_email' => '', 'org_name' => '', 'contact_email' => '',
            'profile' => 'korenovsk', 'kind' => 'city', 'site_name' => 'Твой Кореновск', 'city_name' => 'Кореновск', 'city_name_in' => 'в Кореновске', 'city_name_of' => 'Кореновска',
            'region_name' => 'Краснодарский край', 'city_lat' => '45.4686', 'city_lon' => '39.4519', 'timezone' => 'Europe/Moscow', 'weather_api_key' => ''];
    }

    /** @return string[] ошибки валидации */
    public static function validate(array $in): array
    {
        $e = [];
        foreach (['db_name' => 'название базы данных', 'db_user' => 'пользователя БД', 'admin_user' => 'логин администратора', 'site_name' => 'название сайта', 'city_name' => 'название города'] as $k => $l) {
            if (trim((string) ($in[$k] ?? '')) === '') {
                $e[] = 'Укажите ' . $l . '.';
            }
        }
        if (!preg_match('#^https?://[a-z0-9.\-]+(:\d+)?$#i', (string) ($in['site_url'] ?? ''))) {
            $e[] = 'Адрес сайта — в виде https://example.ru (без слэша в конце).';
        }
        if (!preg_match('/^[a-z0-9_.\-]{3,40}$/i', (string) ($in['admin_user'] ?? ''))) {
            $e[] = 'Логин: 3–40 символов, латинские буквы, цифры, точка, дефис, подчёркивание.';
        }
        if (!filter_var($in['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Укажите корректный e-mail администратора (на него ничего не отправляется — он нужен для учётной записи).';
        }
        if (!preg_match('/^[a-z0-9\-]{3,30}$/', (string) ($in['admin_path'] ?? 'admin'))) {
            $e[] = 'Адрес админки: 3–30 символов a–z, 0–9, дефис.';
        }
        if (in_array((string) ($in['admin_path'] ?? ''), \App\Support\Slug::RESERVED, true) && ($in['admin_path'] ?? '') !== 'admin') {
            $e[] = 'Этот адрес админки занят системой, выберите другой.';
        }
        if (!isset(SITE_KINDS[(string) ($in['kind'] ?? 'city')])) {
            $e[] = 'Выберите тип проекта.';
        }
        if (!isset(self::PROFILES[(string) ($in['profile'] ?? '')])) {
            $e[] = 'Выберите набор данных.';
        }
        foreach (['city_lat' => [-90, 90, 'Широта'], 'city_lon' => [-180, 180, 'Долгота']] as $k => [$min, $max, $l]) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v !== '' && (!is_numeric($v) || (float) $v < $min || (float) $v > $max)) {
                $e[] = $l . ' — число от ' . $min . ' до ' . $max . '.';
            }
        }
        if (($in['timezone'] ?? '') !== '' && !in_array($in['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $e[] = 'Неизвестный часовой пояс (пример: Europe/Moscow).';
        }
        $wk = trim((string) ($in['weather_api_key'] ?? ''));
        if ($wk !== '' && !preg_match('/^[A-Za-z0-9]{10,64}$/', $wk)) {
            $e[] = 'Ключ WeatherAPI — латинские буквы и цифры (скопируйте из личного кабинета weatherapi.com) либо оставьте пустым.';
        }
        if (($in['admin_pass'] ?? '') !== ($in['admin_pass2'] ?? '')) {
            $e[] = 'Пароли не совпадают.';
        }
        return array_merge($e, Auth::passwordErrors((string) ($in['admin_pass'] ?? ''), (string) ($in['admin_user'] ?? '')));
    }

    /**
     * Проверка подключения без установки: версия СУБД, права на создание таблиц, занятость базы.
     * @return array{0:bool,1:string} [успех, сообщение]
     */
    public static function testConnection(array $in): array
    {
        try {
            $pdo = DB::connect(['host' => $in['db_host'] ?: 'localhost', 'port' => (int) ($in['db_port'] ?: 3306), 'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass'] ?? '', 'socket' => $in['db_socket'] ?? '']);
        } catch (\Throwable $e) {
            return [false, 'Не удалось подключиться: ' . self::dbError($e)];
        }
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        try {
            $pdo->exec('CREATE TABLE `tk_install_probe` (`id` INT)');
            $pdo->exec('DROP TABLE `tk_install_probe`');
        } catch (\Throwable) {
            return [false, 'Подключение есть (' . $ver . '), но у пользователя нет прав создавать таблицы (CREATE/DROP). Выдайте права на эту базу в панели хостинга.'];
        }
        $has = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($has && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            return [false, 'Подключение есть, но в базе уже есть пользователи — сайт установлен. Для переустановки нужна пустая база.'];
        }
        [$lvl, $why] = self::dbStatus($ver);
        return [true, 'Подключение работает, права достаточные. СУБД: ' . $ver . ($lvl === 'ok' ? '.' : '. Внимание: ' . $why . '.')];
    }

    /** Сообщение об ошибке БД без лишних технических деталей (пароль в нём не появляется, но путь/хост могут). */
    private static function dbError(\Throwable $e): string
    {
        $m = $e->getMessage();
        return match (true) {
            str_contains($m, '1045') => 'неверное имя пользователя или пароль.',
            str_contains($m, '1049') => 'базы данных с таким именем нет — создайте её в панели хостинга.',
            str_contains($m, '2002'), str_contains($m, '2006') => 'сервер БД недоступен по этому хосту/порту (на хостингах часто нужен localhost, иногда — адрес из панели).',
            str_contains($m, '1044') => 'у пользователя нет доступа к этой базе — привяжите пользователя к базе в панели хостинга.',
            default => 'проверьте хост, порт, имя базы и пароль.',
        };
    }

    private static function runSql(PDO $pdo, string $file): int
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $n = 0;
        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
            if (trim($stmt) !== '') {
                $pdo->exec($stmt);
                $n++;
            }
        }
        return $n;
    }

    private static function put(string $k, string $v, bool $override = true): void
    {
        DB::exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ' . ($override ? 'VALUES(v)' : 'v'), [$k, $v]);
    }

    public static function perform(array $in): array
    {
        $log = [];
        $pdo = DB::connect(['host' => $in['db_host'] ?: 'localhost', 'port' => (int) ($in['db_port'] ?: 3306), 'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass'] ?? '', 'socket' => $in['db_socket'] ?? '']);
        $has = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($has && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            throw new \RuntimeException('В этой базе уже есть пользователи — сайт установлен. Для переустановки используйте пустую базу данных.');
        }
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        [$lvl, $why] = self::dbStatus($ver);
        $log[] = 'Подключение к БД установлено (' . $ver . ')' . ($lvl === 'ok' ? '' : ' — ВНИМАНИЕ: ' . $why);
        self::runSql($pdo, BASE_PATH . '/database/schema.sql');
        $log[] = 'Таблицы созданы';

        $url = rtrim((string) $in['site_url'], '/');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $local = $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.local') || str_ends_with($host, '.test');
        $tz = (string) (($in['timezone'] ?? '') ?: 'Europe/Moscow');
        $config = [
            'app' => [
                'url' => $url, 'key' => bin2hex(random_bytes(32)), 'timezone' => $tz, 'debug' => false,
                'admin_path' => (string) ($in['admin_path'] ?: 'admin'),
                'force_https' => str_starts_with($url, 'https://'), 'enforce_host' => !$local,
                'trusted_proxies' => [],
            ],
            'db' => ['host' => $in['db_host'] ?: 'localhost', 'port' => (int) ($in['db_port'] ?: 3306), 'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass'] ?? '', 'socket' => $in['db_socket'] ?? ''],
        ];
        Config::set($config);
        DB::setPdo($pdo);
        Settings::flush();

        Migrator::run();   // меню сайта по умолчанию (Новости, Афиша, Кино, Погода, Радио, О редакции)
        $log[] = 'Меню сайта создано';

        $profile = (string) ($in['profile'] ?? 'korenovsk');
        if ($profile === 'demo') {
            self::runSql($pdo, BASE_PATH . '/database/seed.sql');
            $log[] = 'Загружены статьи, рубрики и ссылки из старого сайта';
        }
        if ($profile === 'demo' || $profile === 'korenovsk') {
            self::runSql($pdo, BASE_PATH . '/database/korenovsk.sql');
            $log[] = 'Загружены данные города Кореновск';
        }
        Settings::flush();

        // Настройки, введённые в мастере, главнее профиля.
        $city = trim((string) $in['city_name']);
        $given = ['site_name' => trim((string) $in['site_name']), 'city_name' => $city, 'city_name_in' => trim((string) ($in['city_name_in'] ?? '')),
            'city_name_of' => trim((string) ($in['city_name_of'] ?? '')), 'region_name' => trim((string) ($in['region_name'] ?? '')),
            'city_lat' => trim((string) ($in['city_lat'] ?? '')), 'city_lon' => trim((string) ($in['city_lon'] ?? '')), 'city_tz' => $tz];
        foreach ($given as $k => $v) {
            if ($v !== '') {
                self::put($k, $v);
            }
        }
        if ($profile === 'empty') { // падежи не наследуем от Кореновска
            foreach (['city_name_in', 'city_name_of'] as $k) {
                if ($given[$k] === '') {
                    DB::exec('DELETE FROM settings WHERE k = ?', [$k]);
                }
            }
        }
        Settings::flush();
        $kind = isset(SITE_KINDS[(string) ($in['kind'] ?? '')]) ? (string) $in['kind'] : 'city';
        self::put('site_kind', $kind);
        Settings::flush();
        $of = city_of();
        $noun = mb_strtoupper(mb_substr(site_kind_noun(), 0, 1)) . mb_substr(site_kind_noun(), 1);
        // Пресеты типа проекта: ненужные разделы отключаются в меню (их можно включить в «Сайт → Меню сайта»)
        if ($kind !== 'city') {
            DB::exec("UPDATE menu_items SET is_active = 0 WHERE sys_key IN ('kino', 'radio'" . ($kind === 'newspaper' || $kind === 'media' ? ", 'pogoda'" : '') . ')');
            DB::exec("UPDATE menu_items SET title = 'События' WHERE sys_key = 'afisha'");
        }
        self::put('home_news_title', SITE_KINDS[$kind][2], false);
        foreach (['site_description' => $noun . ' ' . $of . ': новости, события и материалы редакции.',
            'site_tagline' => $noun . ' ' . $of, 'home_news_count' => '12', 'news_per_page' => '12', 'indexnow_enabled' => '1', 'weather_days' => '3',
            'radio_name' => $city . ' FM', 'site_locale' => 'ru_RU', 'footer_about' => ''] as $k => $v) {
            self::put($k, $v, false);
        }
        $wk = trim((string) ($in['weather_api_key'] ?? ''));
        if ($wk !== '') {
            Settings::set('weather_api_key', $wk);
            $log[] = 'Ключ WeatherAPI сохранён (зашифрован)';
        }
        $log[] = 'Страницы «О редакции», «Контакты», «Редакционные принципы»: ' . BasePages::install();

        foreach (DB::all('SELECT id, body FROM news') as $r) {
            DB::update('news', ['body' => Sanitizer::html((string) $r['body'])], 'id = ?', [$r['id']]);
        }
        $email = trim((string) ($in['contact_email'] ?? ''));
        foreach ([
            'indexnow_key' => bin2hex(random_bytes(16)), 'content_version' => (string) time(),
            'org_name' => trim((string) ($in['org_name'] ?? '')) ?: 'Редакция', 'contact_email' => $email,
            'noindex_site' => $local ? '1' : '0', 'require_2fa' => '0',
        ] as $k => $v) {
            self::put($k, $v);
        }
        Settings::flush();

        DB::insert('users', [
            'username' => strtolower((string) $in['admin_user']), 'email' => strtolower((string) $in['admin_email']), 'display_name' => 'Администратор',
            'password_hash' => Auth::hashPassword((string) $in['admin_pass']), 'role' => 'admin', 'is_active' => 1,
            'password_changed_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $log[] = 'Создан администратор';

        $php = "<?php\n// Создано установщиком " . date('Y-m-d H:i:s') . ". Файл содержит секреты — не публикуйте и не добавляйте в git.\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents(BASE_PATH . '/config/config.php', $php, LOCK_EX) === false) {
            self::$manualConfig = $php;
            $log[] = 'ВНИМАНИЕ: config/config.php записать не удалось — сохраните текст ниже в этот файл вручную (иначе сайт не заработает)';
        } else {
            @chmod(BASE_PATH . '/config/config.php', 0640);
            @file_put_contents(BASE_PATH . '/config/installed.lock', date('c'));
            $log[] = 'Файл конфигурации записан';
        }

        $files = SeoFiles::writeAll();
        $ok = count(array_filter($files, fn($f) => $f['ok']));
        $log[] = "SEO-файлы созданы: $ok из " . count($files);
        return $log;
    }
}
