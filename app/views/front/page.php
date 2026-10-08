<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $page @var string $body */ ?>
<div class="container container--narrow">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => [['Главная', '/'], [$page['title'], null]]]) ?>
  <article class="article article--page">
    <header class="article__head"><h1><?= e($page['title']) ?></h1></header>
    <div class="prose"><?= $body ?></div>
  </article>
</div>
