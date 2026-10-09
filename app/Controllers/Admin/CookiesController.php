<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Settings;
use App\Support\Consent;

/** «Сайт → Cookie и согласия»: состояние, чек-лист соответствия, журнал выбора читателей. */
final class CookiesController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('settings');
    }

    public function index(): void
    {
        $ver = Consent::version();
        $act = Request::query('a');
        $w = isset(Consent::ACTIONS[$act]) ? 'WHERE action = ?' : '';
        $params = $w ? [$act] : [];
        $p = new Paginator((int) DB::val("SELECT COUNT(*) FROM cookie_consents $w", $params), 50, Request::page());

        // последний выбор каждого читателя по текущей версии текста
        $latest = DB::all('SELECT c.analytics FROM cookie_consents c WHERE c.policy_ver = ? AND c.id = (SELECT MAX(id) FROM cookie_consents WHERE cid = c.cid AND policy_ver = c.policy_ver)', [$ver]);
        $stats = ['readers' => count($latest), 'analytics' => array_sum(array_column($latest, 'analytics')),
            'days30' => (int) DB::val('SELECT COUNT(*) FROM cookie_consents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')];

        $published = fn(string $key, string $def) => preg_match('#^/([a-z0-9][a-z0-9-]*)$#', Consent::url($key, $def), $m)
            && DB::val("SELECT 1 FROM pages WHERE slug = ? AND status = 'published'", [$m[1]]);
        $note = mb_strtolower((string) Settings::get('footer_note', ''));
        $checks = [
            ['Cookie-баннер включён', Consent::enabled(), 'Если на сайте нет аналитики и cookie для читателей, баннер не обязателен. Включите его в «Настройки → Cookie и согласие».'],
            ['Политика cookie опубликована', (bool) $published('cookie_policy_url', '/cookie-policy'), 'Проверьте текст страницы в «Контент → Страницы» и опубликуйте: баннер ссылается на неё.'],
            ['Политика обработки персональных данных опубликована', (bool) $published('privacy_policy_url', '/privacy'), 'Статья 18.1 закона № 152-ФЗ требует открытой политики. Проверьте текст и опубликуйте страницу.'],
            ['Реквизиты оператора заполнены', trim((string) Settings::get('pd_operator_details', '')) !== '', 'Укажите наименование или ФИО, ИНН и адрес в «Настройки → Cookie и согласие».'],
            ['Указан e-mail для обращений', filter_var((string) Settings::get('contact_email', ''), FILTER_VALIDATE_EMAIL) !== false, 'Читатель должен знать, куда написать об отзыве согласия: «Настройки → Сайт и редакция».'],
            ['Сайт работает по HTTPS', Request::isHttps(), 'Без HTTPS cookie и выбор читателя передаются открыто.'],
            ['Подвал не обещает «без cookie», пока включена аналитика', Consent::ymId() === '' || (!str_contains($note, 'без cookie') && !str_contains($note, 'без аналитики')), 'Измените «Строку после ©» в настройках: она противоречит подключённому счётчику.'],
        ];
        $this->view('cookies', ['stats' => $stats, 'checks' => $checks, 'rows' => DB::all("SELECT * FROM cookie_consents $w ORDER BY id DESC LIMIT 50 OFFSET {$p->offset}", $params),
            'p' => $p, 'act' => $act, 'ver' => $ver, 'ym' => Consent::ymId()], 'Cookie и согласия');
    }

    public function export(): void
    {
        Audit::log('export.consents', 'cookie_consents');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cookie-consents-' . date('Y-m-d') . '.csv"');
        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['id', 'идентификатор', 'действие', 'аналитика', 'версия текста', 'время'], ';');
        $st = DB::pdo()->query('SELECT id, cid, action, analytics, policy_ver, created_at FROM cookie_consents ORDER BY id');
        while ($r = $st->fetch(\PDO::FETCH_NUM)) {
            fputcsv($o, $r, ';');
        }
        fclose($o);
        exit;
    }

    /** Новая версия: у всех читателей баннер появится снова. */
    public function reset(): void
    {
        Consent::bumpVersion();
        Settings::touch();
        \App\Core\Cache::flush();
        Audit::log('reset.consents', 'cookie_consents', '', 'версия ' . Consent::version());
        $this->go('cookies', 'ok', 'Готово: при следующем визите читатели увидят баннер снова.');
    }
}
