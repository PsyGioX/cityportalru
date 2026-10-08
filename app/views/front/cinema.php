<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $widget @var string $site */ ?>
<div class="container">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => [['Главная', '/'], ['Кино', null]]]) ?>
  <header class="page-head"><h1><?= e(sec($sec ?? [], 'h1', 'Кинотеатр «' . cinema_name() . '» — афиша и билеты')) ?></h1>
    <p class="page-head__intro"><?= e(sec($sec ?? [], 'intro', 'Расписание сеансов и покупка билетов — на сайте кинотеатра. Мы не встраиваем чужие виджеты, чтобы сторонние сервисы не следили за читателями.')) ?></p></header>
  <div class="cta-row">
    <?php if ($widget): ?><a class="btn btn--primary btn--lg" href="<?= e($widget) ?>" target="_blank" rel="noopener noreferrer"><?= icon('ticket') ?> Расписание и билеты</a><?php endif ?>
    <?php if ($site): ?><a class="btn btn--ghost btn--lg" href="<?= e($site) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Сайт кинотеатра</a><?php endif ?>
  </div>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
