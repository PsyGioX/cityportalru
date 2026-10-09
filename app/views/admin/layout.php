<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;

/** @var string $content @var string $title @var ?array $user @var array $flash */
$path = Request::path();
$cnt = ['rev' => (int) DB::val("SELECT COUNT(*) FROM news WHERE status='review'")];
$menu = [
    ['Контент', [
        ['', 'Обзор', 'grid', null], ['news', 'Новости', 'file', $cnt['rev'] ?: null], ['events', 'Афиша', 'calendar', null], ['pages', 'Страницы', 'folder', null], ['sections', 'Страницы разделов', 'file', null],
        ['categories', 'Рубрики', 'list', null], ['tags', 'Теги', 'tag', null], ['media', 'Медиа', 'image', null]]],
    ['Сайт', [['menu', 'Меню сайта', 'menu', null], ['blocks', 'Блоки на сайте', 'grid', null], ['links', 'Ссылки и сервисы', 'link', null], ['cookies', 'Cookie и согласия', 'shield', null]]],
    ['SEO', [['seo', 'SEO-центр', 'activity', null], ['seo/redirects', 'Редиректы', 'arrow-right', null], ['seo/404', 'Ошибки 404', 'alert', null]]],
    ['Система', [['settings', 'Настройки', 'sliders', null], ['users', 'Пользователи', 'users', null], ['audit', 'Журнал действий', 'clock', null], ['system', 'Система и бэкапы', 'shield', null]]],
];
$perm = ['settings' => 'settings', 'users' => 'users', 'audit' => 'audit', 'system' => 'system', 'seo' => 'seo', 'seo/redirects' => 'seo', 'seo/404' => 'seo',
    'menu' => 'settings', 'blocks' => 'settings', 'cookies' => 'settings', 'links' => 'content', 'categories' => 'content', 'tags' => 'content', 'pages' => 'content', 'events' => 'content'];
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">
<title><?= e($title) ?> — админ-панель</title><?= \App\Support\Brand::headLinks() ?>
<script src="/assets/js/init.js"></script><link rel="stylesheet" href="<?= asset('css/admin.css') ?>"><?php if (\App\Support\Brand::hasCustomAccent()): ?><link rel="stylesheet" href="/brand.css?v=<?= (int) \App\Core\Settings::contentVersion() ?>"><?php endif ?></head>
<body class="adm" data-admin="<?= e(admin_url()) ?>" data-csrf="<?= e(\App\Core\Session::csrf()) ?>">
<a class="skip-link" href="#content">К содержимому</a>
<div class="side-backdrop" data-close-side hidden></div>
<aside class="side" id="side">
  <a class="brand-adm" href="<?= e(admin_url()) ?>"><?= \App\Support\Brand::mark(true) ?><span>Админ-панель</span></a>
  <button type="button" class="side__close" data-close-side aria-label="Закрыть меню">&times;</button>
  <nav aria-label="Разделы">
    <?php foreach ($menu as [$group, $items]): ?>
      <p class="side__group"><?= e($group) ?></p>
      <ul>
        <?php foreach ($items as [$href, $label, $ico, $badge]):
            if (isset($perm[$href]) && !Auth::can($perm[$href])) { continue; }
            if ($href === 'news' ? false : false) {}
            $full = admin_url($href);
            $cur = $href === '' ? $path === $full : ($path === $full || str_starts_with($path, $full . '/'));
            if ($href === 'seo' && (str_starts_with($path, admin_url('seo/redirects')) || str_starts_with($path, admin_url('seo/404')))) { $cur = false; } ?>
          <li><a href="<?= e($full) ?>"<?= $cur ? ' aria-current="page"' : '' ?>><?= icon($ico) ?><span><?= e($label) ?></span><?php if ($badge): ?><b class="badge"><?= (int) $badge ?></b><?php endif ?></a></li>
        <?php endforeach ?>
      </ul>
    <?php endforeach ?>
  </nav>
</aside>
<div class="main">
  <header class="top">
    <button type="button" class="top__menu" data-toggle="side" aria-label="Меню" aria-expanded="false"><?= icon('menu') ?></button>
    <h1 class="top__title"><?= e($title) ?></h1>
    <button type="button" class="theme-toggle" aria-label="Тема оформления" title="Тема оформления"><?= icon('contrast', 'th-auto') ?><?= icon('sun', 'th-light') ?><?= icon('moon', 'th-dark') ?></button>
    <a class="btn btn--ghost btn--sm" href="/" target="_blank" rel="noopener"><?= icon('external') ?> <span class="hide-sm">Открыть сайт</span></a>
    <details class="usermenu"><summary><?= icon('user') ?><span class="hide-sm"><?= e($user['display_name'] ?: $user['username']) ?></span></summary>
      <div class="usermenu__box"><p class="small muted"><?= e(\App\Core\Auth::ROLES[$user['role']] ?? $user['role']) ?> · <?= e($user['username']) ?></p>
        <a href="<?= e(admin_url('profile')) ?>">Профиль и безопасность</a>
        <form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="linklike"><?= icon('log-out') ?> Выйти</button></form></div></details>
  </header>
  <main id="content" tabindex="-1">
    <?php foreach ($flash as $f): ?><div class="alert alert--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= asset('js/theme.js') ?>" defer></script>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body></html>
