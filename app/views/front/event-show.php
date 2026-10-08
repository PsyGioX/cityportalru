<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $e @var ?array $cover @var bool $past */ ?>
<div class="container container--narrow">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => [['Главная', '/'], ['Афиша', '/afisha'], [str_limit($e['title'], 60), null]]]) ?>
  <article class="article">
    <header class="article__head"><h1><?= e($e['title']) ?></h1>
      <?php if ($past): ?><p class="notice notice--warn"><?= icon('alert') ?> Это событие уже прошло.</p><?php endif ?></header>
    <?php if ($cover): ?><figure class="article__cover"><?= img($cover, '(min-width: 800px) 760px, 100vw', ['eager' => true]) ?></figure><?php endif ?>
    <dl class="facts">
      <div><dt><?= icon('calendar') ?> Когда</dt><dd><time datetime="<?= e(date('c', strtotime((string) $e['starts_at']))) ?>"><?= e(date_ru($e['starts_at'], 'j F Y, H:i')) ?></time><?= $e['ends_at'] ? ' — ' . e(date_ru($e['ends_at'], 'j F, H:i')) : '' ?></dd></div>
      <?php if ($e['place'] || $e['address']): ?><div><dt><?= icon('pin') ?> Где</dt><dd><?= e(trim($e['place'] . ($e['address'] ? ', ' . $e['address'] : ''), ', ')) ?></dd></div><?php endif ?>
      <?php if ($e['price']): ?><div><dt><?= icon('ticket') ?> Стоимость</dt><dd><?= e($e['price']) ?></dd></div><?php endif ?>
    </dl>
    <?php if ($e['ticket_url'] && !$past): ?><p><a class="btn btn--primary" href="<?= e($e['ticket_url']) ?>" target="_blank" rel="noopener nofollow"><?= icon('ticket') ?> Купить билет</a></p><?php endif ?>
    <div class="prose"><?= $e['description'] ?></div>
  </article>
</div>
