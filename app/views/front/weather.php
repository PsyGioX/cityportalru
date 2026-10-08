<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var ?array $w @var string $city @var bool $configured @var int $daysCount */ use App\Support\Weather; ?>
<div class="container container--narrow">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => [['Главная', '/'], ['Погода', null]]]) ?>
  <header class="page-head"><h1><?= e(sec($sec ?? [], 'h1', 'Погода ' . city_in())) ?></h1><?php if (trim((string) ($sec['intro'] ?? '')) !== ''): ?><p class="page-head__intro"><?= e($sec['intro']) ?></p><?php endif ?></header>
  <?php if (!$w): ?>
    <div class="empty empty--card"><?= icon('cloud') ?><p><b>Данные о погоде временно недоступны.</b><br>
      <?= $configured ? 'Попробуйте обновить страницу через несколько минут.' : 'Администратору: укажите ключ WeatherAPI.com в «Настройки → Радио, погода, кино».' ?></p></div>
  <?php else: [$desc, $ico] = Weather::describe($w['code'], (bool) ($w['is_day'] ?? 1)); ?>
    <section class="wx-now" aria-label="Сейчас">
      <?= icon($ico, 'wx-now__icon') ?>
      <div><p class="wx-now__temp"><?= e(Weather::fmtTemp($w['temp'])) ?></p><p class="wx-now__desc"><?= e($desc) ?>, ощущается как <?= e(Weather::fmtTemp($w['feels'])) ?></p></div>
      <ul class="wx-now__facts">
        <li><?= icon('wind') ?><span><b><?= str_replace('.', ',', (string) $w['wind']) ?> м/с</b><small><?= e(Weather::compass($w['wind_dir'])) ?></small></span></li>
        <li><?= icon('droplet') ?><span><b><?= (int) $w['humidity'] ?>%</b><small>влажность</small></span></li>
        <li><?= icon('gauge') ?><span><b><?= (int) $w['pressure'] ?> мм</b><small>давление</small></span></li>
      </ul>
    </section>
    <?php if ($w['days']): ?>
    <h2 class="block-title">Прогноз на <?= count($w['days']) ?> <?= plural(count($w['days']), 'день', 'дня', 'дней') ?></h2>
    <ul class="wx-days">
      <?php foreach ($w['days'] as $i => $d): [$dd, $di] = Weather::describe($d['code']); ?>
        <li><span class="wx-days__d"><?= $i === 0 ? 'Сегодня' : e(date_ru($d['date'], 'D, j F')) ?></span><?= icon($di) ?><span class="wx-days__t"><b><?= e(Weather::fmtTemp($d['max'])) ?></b> <span><?= e(Weather::fmtTemp($d['min'])) ?></span></span><span class="wx-days__desc"><?= e($dd) ?><?= $d['rain'] > 0 ? ', ' . str_replace('.', ',', (string) $d['rain']) . ' мм' : '' ?><?= !empty($d['chance']) ? ' · вероятность ' . (int) $d['chance'] . '%' : '' ?></span></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
    <p class="muted">Обновлено в <?= e(date('H:i', (int) $w['updated'])) ?>. Powered by <a href="https://www.weatherapi.com/" target="_blank" rel="noopener" title="Free Weather API">WeatherAPI.com</a>.</p>
  <?php endif ?>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
