<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $items @var \App\Core\Paginator $p @var string $base @var array $head @var string $q @var array $categories */ ?>
<div class="container">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => $head['crumbs']]) ?>
  <header class="page-head">
    <h1><?= e($head['h1']) ?><?= $p->page > 1 ? ' <span class="muted">— страница ' . $p->page . '</span>' : '' ?></h1>
    <?php if (!empty($head['intro'])): ?><p class="page-head__intro"><?= e($head['intro']) ?></p><?php endif ?>
    <?php if (!empty($head['search'])): ?>
      <form class="search search--page" action="/search" method="get" role="search">
        <label class="sr-only" for="q-page">Что искать</label>
        <input id="q-page" type="search" name="q" value="<?= e($q) ?>" placeholder="Например: погода, футбол, афиша" minlength="2" maxlength="100" autofocus>
        <button type="submit" class="btn btn--primary">Найти</button>
      </form>
      <?php if ($q !== '' && mb_strlen($q) >= 2): ?><p class="muted">Найдено: <?= (int) $p->total ?> <?= plural((int) $p->total, 'материал', 'материала', 'материалов') ?> по запросу «<?= e($q) ?>»</p><?php endif ?>
    <?php endif ?>
  </header>

  <?php if ($items): ?>
    <div class="cards cards--list">
      <?php foreach ($items as $i => $n) { echo \App\Core\View::partial('partials/card', ['n' => $n, 'eager' => $i < 3]); } ?>
    </div>
    <?= load_more_html($p, $base, '.cards--list') ?>
    <?= paginate_html($p, $base) ?>
  <?php else: ?>
    <p class="empty"><?= !empty($head['search']) && $q !== '' ? 'Ничего не найдено. Попробуйте изменить запрос.' : 'В этом разделе пока нет материалов.' ?></p>
  <?php endif ?>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
