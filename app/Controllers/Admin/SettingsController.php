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
use App\Core\Request;
use App\Core\Security;
use App\Core\Settings;
use App\Support\Brand;
use App\Support\Consent;
use App\Support\SeoFiles;
use App\Support\Weather;

final class SettingsController extends AdminController
{
    /** Группы настроек: ключ => [тип, подпись, подсказка]. Типы: text, textarea, checkbox, secret, select, date, media (id из медиатеки), color. */
    public static function groups(): array
    {
        return [
            'general' => ['Сайт и редакция', [
                'site_kind' => ['select', 'Тип проекта', 'Влияет на формулировки в описаниях и заголовках. Какие разделы нужны (афиша, кино, погода, радио), включайте в «Сайт → Меню сайта»', array_map(fn($k) => $k[0], SITE_KINDS)],
                'site_name' => ['text', 'Название издания'], 'site_tagline' => ['text', 'Слоган'], 'site_description' => ['textarea', 'Описание сайта (для главной и соцсетей)', '120–160 символов'],
                'footer_about' => ['textarea', 'Текст в подвале (о городе или издании)'], 'footer_note' => ['text', 'Строка после © в подвале', 'Например: «Независимое издание. 16+». Пусто — не показывать'],
                'home_news_title' => ['text', 'Главная: заголовок блока новостей', 'Например: Новости города / Последние материалы'], 'home_events_title' => ['text', 'Главная: заголовок блока событий'], 'home_fresh_title' => ['text', 'Главная: заголовок списка «Свежее»'], 'home_news_count' => ['text', 'Новостей в сетке на главной'], 'news_per_page' => ['text', 'Новостей на странице списка'],
                'org_name' => ['text', 'Издатель (для © в подвале)', 'Например: «Редакция Название» или ваше имя'],
                'city_name' => ['text', 'Город: название', 'Например: Кореновск. Используется в погоде, заголовках и описаниях страниц'],
                'city_name_in' => ['text', 'Город: «где?» (предложный падеж)', 'Например: в Кореновске. Пусто — подставится автоматически (для редких названий задайте вручную)'],
                'city_name_of' => ['text', 'Город: «чего?» (родительный падеж)', 'Например: Кореновска. Пусто — подставится автоматически'],
                'region_name' => ['text', 'Регион (необязательно)', 'Например: Краснодарский край — для адреса событий и описания сайта'],
                'contact_email' => ['text', 'E-mail редакции', 'Показывается на страницах «О редакции» и «Контакты». Форм обратной связи на сайте нет — данные читателей не собираются'],
                'contact_phone' => ['text', 'Телефон редакции (необязательно)'],
                'smi_reg' => ['text', 'Регистрация СМИ (необязательно)', 'Если издание зарегистрировано, укажите данные — они появятся в подвале'], 'age_mark' => ['text', 'Знак возрастной категории (необязательно)', '0+, 6+, 12+, 16+, 18+ — пусто, чтобы не показывать'],
            ]],
            'brand' => ['Оформление и бренд', [
                'site_logo' => ['media', 'Логотип сайта (шапка)', 'PNG или WebP с прозрачным фоном, высота от 100 px. Шапка сайта — цветная, поэтому лучше светлый или белый логотип. Пусто — встроенный знак'],
                'site_logo_footer' => ['media', 'Логотип для подвала (необязательно)', 'Подвал тёмный. Если не задан, используется основной логотип'],
                'logo_hide_name' => ['checkbox', 'Показывать только логотип, без названия сайта рядом', 'Включайте, если название уже есть на самой картинке логотипа'],
                'site_favicon' => ['media', 'Фавикон и иконка приложения', 'Квадратное изображение от 512×512 px (PNG). Из него создаются favicon.ico, значок для iPhone/Android и иконки для поисковиков'],
                'site_short_name' => ['text', 'Короткое название (под значком на экране телефона)', 'До 12 знаков. Пусто — первые 12 знаков названия сайта'],
                'brand_accent' => ['color', 'Фирменный цвет', 'Цвет шапки, кнопок и рубрик. Цвет надписей на нём (белый или тёмный) и оттенки для светлой и тёмной темы подбираются автоматически. Стандартный — #e3182b'],
                'og_image' => ['media', 'Картинка для соцсетей по умолчанию (1200×630)', 'Показывается в превью ссылок, если у материала нет своего фото'],
            ]],
            'cookies' => ['Cookie и согласие', [
                'cookie_banner' => ['checkbox', 'Показывать cookie-баннер', 'Выключено по умолчанию: пока на сайте нет аналитики и cookie для читателей, баннер не нужен. Включите, если подключаете счётчик (поле ниже). Журнал выбора читателей — «Сайт → Cookie и согласия»'],
                'ym_id' => ['text', 'Яндекс Метрика: номер счётчика', 'Только цифры. Счётчик подключается ПОСЛЕ согласия читателя («Принять все» или галочка «Аналитика»), вебвизор не используется. Пусто — аналитики нет, баннер только информирует. Требует включённого баннера'],
                'cookie_title' => ['text', 'Заголовок баннера', 'Пусто — «Мы используем cookie»'],
                'cookie_text' => ['textarea', 'Текст баннера', 'Пусто — стандартный текст. Не пишите «продолжая пользоваться сайтом, вы соглашаетесь»: молчаливое согласие не считается согласием. Изменение текста запрашивает согласие у читателей заново'],
                'cookie_policy_url' => ['text', 'Адрес политики cookie', 'По умолчанию /cookie-policy — страница создаётся черновиком в «Контент → Страницы»: проверьте текст и опубликуйте'],
                'privacy_policy_url' => ['text', 'Адрес политики обработки персональных данных', 'По умолчанию /privacy (статья 18.1 закона № 152-ФЗ: политика должна быть опубликована)'],
                'cookie_ttl_months' => ['text', 'Через сколько месяцев спрашивать согласие снова (1–24)', 'Рекомендуем 12'],
                'pd_operator_details' => ['textarea', 'Реквизиты оператора для политик', 'Полное наименование или ФИО, ИНН/ОГРН(ИП), адрес. Подставляется в политики вместо [[operator_details]]'],
            ]],
            'security' => ['Безопасность', [
                'require_2fa' => ['checkbox', 'Требовать двухфакторную аутентификацию у администраторов и редакторов', 'Рекомендуется, но не обязательно. Если включить, пока 2FA не настроена, доступен только раздел «Профиль»'],
            ]],
            'seo' => ['SEO и поисковики', [
                'noindex_site' => ['checkbox', 'Закрыть сайт от индексации', 'Режим разработки: robots.txt Disallow и meta noindex на всех страницах'],
                'yandex_verification' => ['text', 'Яндекс.Вебмастер: код подтверждения', 'Хэш из имени файла yandex_XXXX.html (файл и meta-тег создаются автоматически)'],
                'google_verification' => ['text', 'Google Search Console: код файла', 'Часть имени файла googleXXXX.html (после слова google)'], 'google_meta' => ['text', 'Google Search Console: meta-тег', 'Значение content из meta google-site-verification'],
                'bing_verification' => ['text', 'Bing Webmaster: код (msvalidate.01)'],
                'indexnow_enabled' => ['checkbox', 'IndexNow: мгновенно уведомлять Яндекс и Bing о новых материалах'], 'indexnow_key' => ['text', 'Ключ IndexNow', 'Создаётся автоматически; файл ключа публикуется в корне сайта'],
                'robots_block_ai' => ['checkbox', 'Запретить ИИ-ботам обучение на материалах', 'GPTBot, CCBot, Google-Extended, ClaudeBot и др. в robots.txt'],
                'robots_extra' => ['textarea', 'Дополнительные строки robots.txt'],
            ]],
            'integrations' => ['Радио, погода, кино', [
                'radio_name' => ['text', 'Название радио'], 'radio_url' => ['text', 'Адрес аудиопотока радио (https)', 'Поток подключается только когда читатель нажимает «слушать»'],
                'cinema_name' => ['text', 'Название кинотеатра', 'Например: Октябрь — для страницы «Кино»'],
                'cinema_widget_url' => ['text', 'Ссылка на расписание кинотеатра'], 'cinema_site_url' => ['text', 'Сайт кинотеатра'],
                'city_lat' => ['text', 'Погода: широта города', 'Например: 45.4647'], 'city_lon' => ['text', 'Погода: долгота города', 'Например: 39.4492'],
                'weather_days' => ['text', 'Погода: сколько дней прогноза показывать (1–14)', 'Бесплатный тариф WeatherAPI отдаёт 3 дня, платные — до 14'],
                'weather_api_key' => ['secret', 'Погода: API-ключ WeatherAPI.com', 'Ключ создаётся бесплатно на weatherapi.com (раздел My Account → API Key). Прогноз запрашивает сервер сайта, а не браузер читателя; без ключа погода не показывается.'],
            ]],
        ];
    }

    public function __construct()
    {
        parent::__construct();
        $this->need('settings');
    }

    public function index(): void
    {
        $errors = [];
        if (Request::isPost()) {
            $changed = [];
            foreach (self::groups() as [, $fields]) {
                foreach ($fields as $key => $def) {
                    $type = $def[0];
                    if ($type === 'checkbox') {
                        $val = Request::bool($key) ? '1' : '0';
                    } elseif ($type === 'secret') {
                        $val = (string) ($_POST[$key] ?? '');
                        if (Request::bool('clear_' . $key)) {
                            $val = '';
                        } elseif ($val === '') {
                            continue; // не меняем сохранённый секрет
                        }
                    } elseif ($type === 'media') {
                        $id = Request::int($key);
                        $val = $id > 0 && DB::val('SELECT 1 FROM media WHERE id = ?', [$id]) ? (string) $id : '';
                    } elseif ($type === 'select') {
                        $val = Request::line($key, 60);
                        $val = isset($def[3][$val]) ? $val : (string) array_key_first($def[3]);
                    } elseif ($type === 'textarea') {
                        $val = Request::text($key, 2000);
                    } else {
                        $val = Request::line($key, 500);
                    }
                    if ($key === 'ym_id' && $val !== '' && !Request::bool('cookie_banner')) {
                        $errors[$key] = 'Счётчик можно подключить только вместе с cookie-баннером: включите «Показывать cookie-баннер».';
                        continue;
                    }
                    if (!$this->check($key, $val, $errors)) {
                        continue;
                    }
                    if (Settings::get($key, '') !== $val && !in_array($key, Settings::SECRET, true)) {
                        $changed[] = $key;
                    }
                    Settings::set($key, $val);
                }
            }
            if (!$errors) {
                if (array_intersect(['site_favicon', 'brand_accent'], $changed)) {   // иконки зависят от картинки и цвета (maskable)
                    if ((string) Settings::get('site_favicon', '') === '') {
                        Brand::resetIcons();
                    } elseif (!Brand::rebuildIcons()) {
                        $this->flash('warn', 'Иконки не созданы: нужно расширение PHP GD и права на запись в public/uploads/. Остальные настройки сохранены.');
                    }
                }
                if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', (string) Settings::get('indexnow_key', ''))) {
                    Settings::set('indexnow_key', bin2hex(random_bytes(16)));
                }
                if (array_intersect(['ym_id', 'cookie_text', 'cookie_title'], $changed) && Settings::bool('cookie_banner')) {
                    Consent::bumpVersion();   // цель или текст изменились — прежние согласия недействительны, спросим заново
                }
                if (Settings::bool('cookie_banner')) {
                    foreach (['cookie_policy_url' => '/cookie-policy', 'privacy_policy_url' => '/privacy'] as $k => $def) {
                        $u = Consent::url($k, $def);
                        $slug = preg_match('#^/([a-z0-9][a-z0-9-]*)$#', $u, $m) ? $m[1] : null;
                        if ($slug !== null && !DB::val("SELECT 1 FROM pages WHERE slug = ? AND status = 'published'", [$slug])) {
                            $this->flash('warn', 'Страница ' . $u . ' не опубликована — ссылка в баннере ведёт на 404. Проверьте текст в «Контент → Страницы» и опубликуйте.');
                        }
                    }
                }
                Settings::touch();
                \App\Core\Cache::flush();
                SeoFiles::writeAll();
                Audit::log('update.settings', 'settings', '', implode(', ', $changed));
                $this->go('settings', 'ok', 'Настройки сохранены, SEO-файлы пересозданы.');
            }
        }
        $this->view('settings', ['groups' => self::groups(), 'errors' => $errors], 'Настройки');
    }

    private function check(string $key, string $val, array &$errors): bool
    {
        $bad = match ($key) {
            'contact_email' => $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL),
            'radio_url', 'cinema_widget_url', 'cinema_site_url' => $val !== '' && (Security::safeUrl($val, false) === ''
                || !preg_match('#^https?://[a-z0-9]([a-z0-9.\-]*[a-z0-9])?(:\d{1,5})?(/|$)#i', $val)),
            'city_lat' => $val !== '' && !is_numeric($val),
            'city_lon' => $val !== '' && !is_numeric($val),
            'site_name' => $val === '',
            'brand_accent' => $val !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val),
            'ym_id' => $val !== '' && !preg_match('/^\d{4,12}$/', $val),
            'cookie_ttl_months' => !ctype_digit($val) || (int) $val < 1 || (int) $val > 24,
            'cookie_policy_url', 'privacy_policy_url' => $val !== '' && Security::safeUrl($val) === '',
            'weather_days' => !ctype_digit($val) || (int) $val < 1 || (int) $val > 14,
            'yandex_verification', 'google_verification', 'bing_verification', 'indexnow_key' => $val !== '' && !preg_match('/^[A-Za-z0-9_\-]{4,64}$/', $val),
            'home_news_count', 'news_per_page' => !ctype_digit($val) || (int) $val < 1 || (int) $val > 200,
            default => false,
        };
        if ($bad) {
            $errors[$key] = 'Проверьте значение.';
            return false;
        }
        return true;
    }
}
