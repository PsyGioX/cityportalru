<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $events @var array $past @var int $pastTotal @var bool $archive @var \App\Core\Paginator $p */ use App\Controllers\Front\EventsController as EC; ?>
<div class="container">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => $archive ? [['Главная', '/'], ['Афиша', '/afisha'], ['Архив', null]] : [['Главная', '/'], ['Афиша', null]]]) ?>
  <header class="page-head"><h1><?= $archive ? 'Архив афиши' : e(sec($sec ?? [], 'h1', 'Афиша ' . city_of())) ?><?= $p->page > 1 ? ' <span class="muted">— страница ' . $p->page . '</span>' : '' ?></h1><p class="page-head__intro"><?= $archive ? 'Прошедшие события — от недавних к более ранним. <a href="/afisha">К предстоящим</a>' : e(sec($sec ?? [], 'intro', 'Концерты, праздники, выставки и мероприятия города и района.')) ?></p></header>
  <?php if ($events): ?>
    <ul class="event-list">
      <?php foreach ($events as $ev): $c = EC::cover($ev); ?>
      <li class="event-card">
        <time class="event__date" datetime="<?= e(date('c', strtotime((string) $ev['starts_at']))) ?>"><b><?= e(date('j', strtotime((string) $ev['starts_at']))) ?></b><span><?= e(date_ru($ev['starts_at'], 'F')) ?></span><small><?= e(date_ru($ev['starts_at'], 'D')) ?></small></time>
        <?php if ($c): ?><a class="event-card__img" href="/afisha/<?= e($ev['slug']) ?>" tabindex="-1" aria-hidden="true"><?= img($c, '160px', ['max' => 480, 'alt' => '']) ?></a><?php endif ?>
        <div class="event-card__body">
          <h2><a href="/afisha/<?= e($ev['slug']) ?>"><?= e($ev['title']) ?></a></h2>
          <p class="meta-line"><?= icon('clock') ?><?= e(date('H:i', strtotime((string) $ev['starts_at']))) ?><?php if ($ev['place']): ?> <?= icon('pin') ?><?= e($ev['place']) ?><?php endif ?><?php if ($ev['price']): ?> <?= icon('ticket') ?><?= e($ev['price']) ?><?php endif ?></p>
        </div>
      </li>
      <?php endforeach ?>
    </ul>
    <?= load_more_html($p, '/afisha', '.event-list') ?>
    <?= paginate_html($p, '/afisha') ?>
  <?php else: ?>
    <div class="empty empty--card"><?= icon('calendar') ?><p><b><?= $archive ? 'В архиве пока нет событий.' : 'В афише пока нет событий.' ?></b><br>Загляните позже или посмотрите <a href="/kino">расписание кинотеатра</a>.</p></div>
  <?php endif ?>
  <?php if ($past): ?>
    <section class="block"><h2 class="block-title">Прошедшие события</h2>
      <ul class="plain-list"><?php foreach ($past as $ev): ?><li><a href="/afisha/<?= e($ev['slug']) ?>"><?= e($ev['title']) ?></a> <span class="muted"><?= e(date_ru($ev['starts_at'], 'j F Y')) ?></span></li><?php endforeach ?></ul>
      <?php if ($pastTotal > count($past)): ?><p><a class="btn btn--ghost" href="/afisha?past=1">Весь архив (<?= (int) $pastTotal ?>)</a></p><?php endif ?>
    </section>
  <?php endif ?>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
