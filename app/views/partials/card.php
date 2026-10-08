<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $n @var bool|null $eager */
$c = \App\Support\Repo::cover($n); ?>
<article class="card">
  <a class="card__media" href="<?= e(news_path($n)) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($c): echo img($c, '(min-width: 1000px) 380px, (min-width: 600px) 45vw, 100vw', ['max' => 800, 'eager' => !empty($eager), 'alt' => '']);
    else: ?><span class="card__ph"><svg viewBox="0 0 221 170" aria-hidden="true"><use href="/assets/img/sprite.svg#logo-mark"></use></svg></span><?php endif ?>
  </a>
  <div class="card__body">
    <p class="card__meta">
      <?php if ($n['cat_name']): ?><a class="chip" href="/category/<?= e($n['cat_slug']) ?>"><?= e($n['cat_name']) ?></a><?php endif ?>
      <time datetime="<?= e(date('c', strtotime((string) $n['published_at']))) ?>"><?= e(pub_date($n['published_at'])) ?></time>
    </p>
    <h3 class="card__title"><a href="<?= e(news_path($n)) ?>"><?= e($n['title']) ?></a></h3>
  </div>
</article>
