<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var ?array $lead @var array $side @var array $grid @var array $events @var array $blocks */
$lc = $lead ? \App\Support\Repo::cover($lead) : null; ?>
<div class="container">
  <h1 class="sr-only"><?= e(sec($sec ?? [], 'h1', setting('site_name') . ' — новости и афиша ' . city_of())) ?></h1>

  <?php if ($lead): ?>
  <section class="hero" aria-label="Главное">
    <article class="lead">
      <a class="lead__media" href="<?= e(news_path($lead)) ?>" tabindex="-1" aria-hidden="true">
        <?php if ($lc): echo img($lc, '(min-width: 1000px) 760px, 100vw', ['eager' => true, 'alt' => '']);
        else: ?><span class="card__ph card__ph--lg"><svg viewBox="0 0 221 170" aria-hidden="true"><use href="/assets/img/sprite.svg#logo-mark"></use></svg></span><?php endif ?>
      </a>
      <div class="lead__body">
        <p class="card__meta">
          <?php if ($lead['cat_name']): ?><a class="chip" href="/category/<?= e($lead['cat_slug']) ?>"><?= e($lead['cat_name']) ?></a><?php endif ?>
          <time datetime="<?= e(date('c', strtotime((string) $lead['published_at']))) ?>"><?= e(pub_date($lead['published_at'])) ?></time>
        </p>
        <h2 class="lead__title"><a href="<?= e(news_path($lead)) ?>"><?= e($lead['title']) ?></a></h2>
        <?php if ($lead['excerpt']): ?><p class="lead__text"><?= e(str_limit((string) $lead['excerpt'], 230)) ?></p><?php endif ?>
      </div>
    </article>
    <aside class="fresh" aria-labelledby="fresh-h">
      <h2 class="block-title" id="fresh-h"><?= e(setting('home_fresh_title', 'Свежее')) ?></h2>
      <ol class="fresh__list">
        <?php foreach ($side as $n): ?>
          <li>
            <time datetime="<?= e(date('c', strtotime((string) $n['published_at']))) ?>"><?= e(pub_date($n['published_at'])) ?></time>
            <a href="<?= e(news_path($n)) ?>"><?= e($n['title']) ?></a>
          </li>
        <?php endforeach ?>
      </ol>
      <a class="more" href="/news">Все новости <?= icon('arrow-right') ?></a>
    </aside>
  </section>
  <?php else: ?>
    <p class="empty">Пока нет опубликованных новостей. Добавьте первую в админ-панели.</p>
  <?php endif ?>

  <?php if ($grid): ?>
  <section class="block" aria-labelledby="news-h">
    <div class="block-head"><h2 class="block-title" id="news-h"><?= e(setting('home_news_title', 'Новости города')) ?></h2><a class="more" href="/news">Все новости <?= icon('arrow-right') ?></a></div>
    <div class="cards">
      <?php foreach ($grid as $n) { echo \App\Core\View::partial('partials/card', ['n' => $n]); } ?>
    </div>
  </section>
  <?php endif ?>

  <?php if ($events): ?>
  <section class="block" aria-labelledby="ev-h">
    <div class="block-head"><h2 class="block-title" id="ev-h"><?= e(setting('home_events_title', 'Ближайшие события')) ?></h2><a class="more" href="/afisha">Вся афиша <?= icon('arrow-right') ?></a></div>
    <ul class="events">
      <?php foreach ($events as $e): ?>
        <li class="event">
          <time class="event__date" datetime="<?= e(date('c', strtotime((string) $e['starts_at']))) ?>"><b><?= e(date('j', strtotime((string) $e['starts_at']))) ?></b><span><?= e(date_ru($e['starts_at'], 'F')) ?></span></time>
          <div><h3><a href="/afisha/<?= e($e['slug']) ?>"><?= e($e['title']) ?></a></h3>
          <p><?= e(date('H:i', strtotime((string) $e['starts_at']))) ?><?= $e['place'] ? ' · ' . e($e['place']) : '' ?></p></div>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <?php foreach ($blocks as $b): $links = $b['links']; $hid = 'blk-' . $b['id']; ?>
  <section class="block" aria-labelledby="<?= e($hid) ?>">
    <h2 class="block-title" id="<?= e($hid) ?>"><?= e($b['title']) ?></h2>
    <?php if (!empty($b['subtitle'])): ?><p class="muted block-sub"><?= e($b['subtitle']) ?></p><?php endif ?>
    <ul class="tiles">
      <?php foreach ($links as $l): ?>
        <li><a class="tile" href="<?= e($l['url']) ?>"<?= $l['new_tab'] ? ' target="_blank"' : '' ?> rel="noopener">
          <?php if ($l['icon']): ?><span class="tile__icon"><img src="<?= e($l['icon']) ?>" alt="" width="56" height="56" loading="lazy"></span><?php endif ?>
          <span class="tile__text"><b><?= e($l['title']) ?></b><?php if ($l['description']): ?><small><?= e($l['description']) ?></small><?php endif ?></span>
        </a></li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endforeach ?>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
