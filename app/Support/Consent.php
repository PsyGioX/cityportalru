<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Core\Settings;

/**
 * Cookie-баннер и согласия (152-ФЗ). По умолчанию выключено: сайт не ставит читателям cookie и не собирает данные.
 * Включается в «Настройки → Cookie и согласие». Аналитика (Яндекс Метрика) грузится только после согласия читателя.
 */
final class Consent
{
    public const ACTIONS = ['accept_all' => 'Принял все', 'reject' => 'Только необходимые', 'custom' => 'Свой выбор', 'ack' => 'Ознакомлен', 'withdraw' => 'Отозвал согласие'];
    public const RETENTION_YEARS = 3;   // общий срок исковой давности: столько хранится подтверждение согласия

    public const CONSENTS_DDL = "CREATE TABLE IF NOT EXISTS `cookie_consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` CHAR(32) NOT NULL,
  `action` VARCHAR(12) NOT NULL,
  `analytics` TINYINT(1) NOT NULL DEFAULT 0,
  `policy_ver` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consent_cid` (`cid`),
  KEY `idx_consent_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    public static function enabled(): bool
    {
        return Settings::bool('cookie_banner');
    }

    /** Номер счётчика Яндекс Метрики (только цифры) или пустая строка. Без включённого баннера счётчик не подключается никогда. */
    public static function ymId(): string
    {
        $id = trim((string) Settings::get('ym_id', ''));
        return self::enabled() && preg_match('/^\d{4,12}$/', $id) ? $id : '';
    }

    public static function version(): int
    {
        return max(1, (int) Settings::get('consent_version', 1));
    }

    public static function ttlMonths(): int
    {
        return max(1, min(24, (int) Settings::get('cookie_ttl_months', 12)));
    }

    public static function bumpVersion(): void
    {
        Settings::set('consent_version', (string) (self::version() + 1));
    }

    /** Адрес документа: только относительный путь или https-адрес. */
    public static function url(string $key, string $default): string
    {
        $u = \App\Core\Security::safeUrl((string) Settings::get($key, $default));
        return $u !== '' ? $u : $default;
    }

    public static function title(): string
    {
        return trim((string) Settings::get('cookie_title', '')) ?: 'Мы используем cookie';
    }

    public static function text(): string
    {
        $t = trim((string) Settings::get('cookie_text', ''));
        if ($t !== '') {
            return $t;
        }
        return self::ymId() !== ''
            ? 'Для работы сайта нужны только технические данные. С вашего согласия мы дополнительно используем Яндекс Метрику, чтобы понимать, какие материалы читают. Отказ не ограничивает доступ к сайту, выбор можно изменить в любой момент.'
            : 'Сайт использует только технические данные, необходимые для работы (например, выбранная тема оформления). Рекламы и аналитики нет.';
    }

    /** Данные для баннера в шаблоне или null, если баннер выключен. */
    public static function banner(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        return [
            'title' => self::title(), 'text' => self::text(), 'ym' => self::ymId(), 'ver' => self::version(), 'ttl' => self::ttlMonths(),
            'cookie_url' => self::url('cookie_policy_url', '/cookie-policy'), 'privacy_url' => self::url('privacy_policy_url', '/privacy'),
        ];
    }

    /** Дополнительные источники для CSP — только если счётчик настроен. Список взят из документации Яндекса («Установка счётчика на сайт с CSP»). */
    public static function csp(): array
    {
        return self::ymId() !== '' ? ['script' => ' https://mc.yandex.ru https://yastatic.net', 'img' => ' https://mc.yandex.ru', 'connect' => ' https://mc.yandex.ru'] : ['script' => '', 'img' => '', 'connect' => ''];
    }

    /** Записывает подтверждение выбора читателя. Без IP и User-Agent: связь только через случайный идентификатор из браузера. */
    public static function record(string $cid, string $action, bool $analytics, int $ver): bool
    {
        if (!self::enabled() || $ver !== self::version() || !preg_match('/^[a-f0-9]{32}$/', $cid) || !isset(self::ACTIONS[$action])) {
            return false;
        }
        if (self::ymId() === '' && $analytics) {
            return false;
        }
        DB::insert('cookie_consents', ['cid' => $cid, 'action' => $action, 'analytics' => $analytics ? 1 : 0, 'policy_ver' => $ver, 'created_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    public static function purgeOld(): void
    {
        DB::exec('DELETE FROM cookie_consents WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . self::RETENTION_YEARS . ' YEAR)');
    }

    /** Блок для «Политики cookie»: подставляется вместо [[cookie_analytics]]. */
    public static function policyAnalyticsHtml(): string
    {
        if (self::ymId() === '') {
            return '<p>Аналитические и рекламные cookie на сайте <strong>не используются</strong>.</p>';
        }
        return '<h3>Аналитические cookie (только с вашего согласия)</h3>'
            . '<p>Мы используем сервис «Яндекс Метрика» (ООО «ЯНДЕКС») для статистики посещений: какие страницы открывают и как пользуются сайтом. Счётчик подключается только после того, как вы нажали «Принять все» или включили «Аналитику» в настройках. Запись действий на странице (вебвизор) не используется.</p>'
            . '<table><thead><tr><th>Cookie</th><th>Назначение</th><th>Срок</th></tr></thead><tbody>'
            . '<tr><td>_ym_uid</td><td>Идентификатор посетителя в Метрике</td><td>до 1 года</td></tr>'
            . '<tr><td>_ym_d</td><td>Дата первого визита</td><td>до 1 года</td></tr>'
            . '<tr><td>_ym_isad</td><td>Проверка, блокирует ли браузер рекламу и счётчики</td><td>2 дня</td></tr>'
            . '<tr><td>_ym_metrika_enabled</td><td>Проверка, что cookie разрешены</td><td>сессия</td></tr>'
            . '</tbody></table>'
            . '<p>Яндекс может использовать и другие технические cookie; актуальный перечень и сроки опубликованы в документации сервиса «Яндекс Метрика».</p>';
    }
}
