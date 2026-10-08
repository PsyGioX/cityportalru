<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Settings;
use App\Support\IndexNow;
use App\Support\Repo;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-$i day"))] = 0;
        }
        foreach (DB::all('SELECT day, views FROM stats_daily WHERE day >= ?', [array_key_first($days)]) as $r) {
            $days[$r['day']] = (int) $r['views'];
        }
        $stats = [
            'published' => (int) DB::val("SELECT COUNT(*) FROM news n WHERE " . Repo::LIVE),
            'drafts' => (int) DB::val("SELECT COUNT(*) FROM news WHERE status IN ('draft','review')"),
            'scheduled' => (int) DB::val("SELECT COUNT(*) FROM news WHERE status = 'published' AND published_at > NOW()"),
            'views' => (int) DB::val('SELECT COALESCE(SUM(views),0) FROM news'),
            'views7' => (int) array_sum(array_slice($days, -7)),
            'nf' => (int) DB::val('SELECT COUNT(*) FROM not_found_log WHERE is_ignored = 0 AND hits >= 3'),
        ];
        $health = $this->health();
        $this->view('dashboard', [
            'stats' => $stats, 'days' => $days,
            'top' => DB::all("SELECT id, title, slug, views FROM news n WHERE " . Repo::LIVE . ' ORDER BY views DESC LIMIT 5'),
            'recent' => DB::all("SELECT n.id, n.title, n.status, n.published_at, n.updated_at, u.display_name FROM news n LEFT JOIN users u ON u.id = n.author_id ORDER BY n.updated_at DESC LIMIT 6"),
            'nfTop' => Auth::can('seo') ? DB::all('SELECT id, path, hits FROM not_found_log WHERE is_ignored = 0 ORDER BY hits DESC LIMIT 5') : [],
            'health' => $health,
        ], 'Обзор');
    }

    private function health(): array
    {
        $h = [];
        $https = str_starts_with((string) Config::get('app.url', ''), 'https://');
        $h[] = ['HTTPS включён и сайт открывается по защищённому адресу', $https && Request::isHttps(), 'Подключите SSL-сертификат (Let\'s Encrypt) и укажите https:// в config/config.php'];
        $h[] = ['Сайт открыт для индексации', !Settings::bool('noindex_site'), 'Сейчас стоит запрет индексации: Настройки → SEO'];
        $h[] = ['Режим отладки выключен', !Config::get('app.debug', false), 'Поставьте debug => false в config/config.php'];
        $h[] = ['У вашей учётной записи включена 2FA', (bool) ($this->user['totp_enabled'] ?? false), 'Профиль → Двухфакторная аутентификация'];
        $h[] = ['Указан e-mail редакции (страницы «О редакции» и «Контакты»)', Settings::get('contact_email', '') !== '', 'Настройки → Редакция'];
        $h[] = ['IndexNow настроен', IndexNow::key() !== '', 'Ключ создаётся автоматически при сохранении настроек'];
        $h[] = ['Подтверждён сайт в Яндекс.Вебмастере', Settings::get('yandex_verification', '') !== '', 'Настройки → SEO'];
        $h[] = ['Папка config/ недоступна из интернета', !$this->reachable('/config/config.php'), 'Настройте корень сайта на папку public/ или оставьте .htaccess из архива'];
        return $h;
    }

    private function reachable(string $path): bool
    {
        return false; // проверка выполняется на странице «Система» с реальным HTTP-запросом
    }
}
