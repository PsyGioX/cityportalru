<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $content @var string $title @var array $flash */ ?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — админ-панель</title><?= \App\Support\Brand::headLinks() ?><link rel="stylesheet" href="<?= asset('css/admin.css') ?>"><?php if (\App\Support\Brand::hasCustomAccent()): ?><link rel="stylesheet" href="/brand.css?v=<?= (int) \App\Core\Settings::contentVersion() ?>"><?php endif ?></head>
<body class="install"><main class="install__box install__box--sm">
<a class="brand-adm brand-adm--badge" href="/"><span class="brand-badge"><?= \App\Support\Brand::mark() ?></span><span><?= e(setting('site_name', 'Админ-панель')) ?></span></a>
<?php foreach ($flash as $f): ?><div class="alert alert--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
<?= $content ?>
</main></body></html>
