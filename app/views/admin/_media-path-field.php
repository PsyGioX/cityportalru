<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $name @var string $path @var string $class */
$path = preg_match('#^/(assets|uploads)/[A-Za-z0-9/_.\-]+$#', (string) $path) ? (string) $path : ''; ?>
<div class="mediafield mediafield--icon <?= e($class ?? '') ?>" data-media-path-field>
  <input type="hidden" name="<?= e($name) ?>" value="<?= e($path) ?>" id="f-<?= e($name) ?>">
  <div class="mediafield__preview"><?php if ($path): ?><img src="<?= e($path) ?>" alt=""><?php endif ?></div>
  <p class="hint" data-path-label><?= $path !== '' ? e($path) : 'Иконка не выбрана' ?></p>
  <div class="mediafield__btns"><button type="button" class="btn btn--ghost btn--sm" data-pick="single"><?= icon('image') ?> Выбрать из медиатеки</button><button type="button" class="btn btn--ghost btn--sm" data-clear <?= $path !== '' ? '' : 'hidden' ?>>Убрать</button></div>
</div>
