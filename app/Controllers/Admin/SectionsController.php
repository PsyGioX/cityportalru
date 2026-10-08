<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Support\Migrator;

/**
 * Страницы разделов: Главная, Новости, Афиша, Кино, Погода, Радио. Здесь меняются заголовок (H1), вводный текст,
 * дополнительный текст под содержимым и SEO. Пустое поле — используется текст по умолчанию. Добавлять и удалять
 * эти страницы нельзя (разделы включаются и отключаются в «Меню сайта»); обычные страницы — в «Страницах».
 */
final class SectionsController extends CrudController
{
    protected string $table = 'section_pages';
    protected string $route = 'sections';
    protected string $title = 'Страницы разделов';
    protected string $singular = 'страница раздела';
    protected string $order = 'id';
    protected int $perPage = 50;
    protected array $columns = ['sys_key' => 'Раздел', 'h1' => 'Заголовок'];

    public function __construct()
    {
        $this->fields = [
            'h1' => ['label' => 'Заголовок страницы (H1)', 'type' => 'text', 'max' => 190, 'help' => 'Пусто — стандартный заголовок раздела.'],
            'intro' => ['label' => 'Вводный текст под заголовком', 'type' => 'textarea', 'max' => 600, 'help' => 'Одно-два предложения. Пусто — стандартный текст (для «Погоды» и «Главной» вводного текста по умолчанию нет).'],
            'body' => ['label' => 'Дополнительный текст внизу страницы', 'type' => 'richtext', 'help' => 'Правила, условия, пояснения — что угодно. Подстановки: [[site_name]], [[city]], [[city_in]], [[city_of]], [[contact_email_link]]. Пусто — блок не показывается.'],
            'seo_title' => ['label' => 'SEO-заголовок', 'type' => 'text', 'max' => 190, 'help' => 'Пусто — формируется автоматически.'],
            'seo_description' => ['label' => 'SEO-описание', 'type' => 'textarea', 'max' => 320, 'help' => '120–160 символов. Пусто — формируется автоматически.'],
        ];
        parent::__construct();
    }

    public function index(): void
    {
        [$rows] = $this->list();
        $this->view('sections-list', ['rows' => $rows, 'c' => $this], $this->title);
    }

    public function edit(array $a = []): void
    {
        if (empty($a['id'])) {
            $this->go('sections', 'err', 'Страницы разделов не создаются: они уже есть в списке.');
        }
        $key = (string) DB::val('SELECT sys_key FROM section_pages WHERE id = ?', [(int) $a['id']]);
        $this->singular = 'страница раздела «' . self::info($key)[0] . '» (' . self::info($key)[1] . ')';
        parent::edit($a);
    }

    public function delete(array $a): void
    {
        $this->go('sections', 'err', 'Страницу раздела удалить нельзя. Чтобы убрать раздел с сайта, отключите его в «Меню сайта».');
    }

    public function bulk(): void
    {
        $this->go('sections', 'err', 'Массовые действия для страниц разделов недоступны.');
    }

    protected function beforeSave(array &$data, ?array $row): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    /** Название и адрес раздела по ключу. @return array{0:string,1:string} */
    public static function info(string $key): array
    {
        return Migrator::SECTIONS[$key] ?? [$key, '/'];
    }

    public function cell(array $row, string $key): string
    {
        return $key === 'sys_key' ? e(self::info((string) $row['sys_key'])[0]) : parent::cell($row, $key);
    }
}
