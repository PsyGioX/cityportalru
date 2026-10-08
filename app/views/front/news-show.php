<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $n @var ?array $cover @var array $gallery @var array $tags @var array $related @var array $latest @var int $minutes @var string $url  @var bool $preview @var array $crumbs */
$title = rawurlencode($n['title']); $u = rawurlencode($url); ?>
<div class="container">
  <?php if ($preview): ?><p class="preview-bar" role="status"><?= icon('eye') ?> Предпросмотр. Материал <?= $n['status'] === 'published' ? 'опубликован' : 'не опубликован' ?>, поисковики его не видят.</p><?php endif ?>
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
  <div class="article-layout">
    <article class="article">
      <header class="article__head">
        <p class="card__meta">
          <?php if ($n['cat_name']): ?><a class="chip" href="/category/<?= e($n['cat_slug']) ?>"><?= e($n['cat_name']) ?></a><?php endif ?>
        </p>
        <h1><?= e($n['title']) ?></h1>
        <ul class="meta">
          <li><?= icon('calendar') ?><time datetime="<?= e(date('c', strtotime((string) $n['published_at']))) ?>"><?= e(date_ru($n['published_at'], 'j F Y, H:i')) ?></time></li>
          <li><?= icon('clock') ?><?= $minutes ?> мин чтения</li>
          <li><?= icon('eye') ?><?= number_format((int) $n['views'], 0, ',', ' ') ?></li>
        </ul>
        <?php if (!empty($n['excerpt'])): ?><p class="article__lead"><?= e($n['excerpt']) ?></p><?php endif ?>
      </header>

      <?php if ($cover): ?>
      <figure class="article__cover">
        <?= img($cover, '(min-width: 1100px) 760px, 100vw', ['eager' => true]) ?>
        <?php if (!empty($cover['caption']) || !empty($cover['credit'])): ?><figcaption><?= e($cover['caption']) ?><?= $cover['credit'] ? ' <span>Фото: ' . e($cover['credit']) . '</span>' : '' ?></figcaption><?php endif ?>
      </figure>
      <?php endif ?>

      <div class="prose" data-attrib><?= $n['body'] ?></div>

      <?php if ($gallery): ?>
      <section class="gallery" data-gallery aria-label="Фотогалерея" aria-roledescription="карусель">
        <h2 class="gallery__title">Фото (<?= count($gallery) ?>)</h2>
        <div class="gallery__track" tabindex="0">
          <?php foreach ($gallery as $i => $g): ?>
            <figure class="gallery__slide" aria-label="Фото <?= $i + 1 ?> из <?= count($gallery) ?>">
              <button type="button" class="gallery__open" data-full="<?= e(media_url($g, 1600)) ?>" aria-label="Открыть фото <?= $i + 1 ?> на весь экран"><?= img($g, '(min-width: 1100px) 760px, 100vw', ['max' => 1200, 'alt' => $g['alt'] ?: $n['title'] . ' — фото ' . ($i + 1)]) ?></button>
              <?php if ($g['caption']): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif ?>
            </figure>
          <?php endforeach ?>
        </div>
        <?php if (count($gallery) > 1): ?>
        <div class="gallery__nav">
          <button type="button" data-dir="-1" aria-label="Предыдущее фото"><?= icon('chevron-left') ?></button>
          <span class="gallery__count" aria-live="polite">1 / <?= count($gallery) ?></span>
          <button type="button" data-dir="1" aria-label="Следующее фото"><?= icon('chevron-right') ?></button>
        </div>
        <?php endif ?>
      </section>
      <dialog class="lightbox" id="lightbox" aria-label="Просмотр фото"><button type="button" class="lightbox__close" aria-label="Закрыть"><?= icon('close') ?></button><img src="" alt=""></dialog>
      <?php endif ?>

      <?php if ($n['source_name'] || $n['source_url']): ?>
        <p class="source">Источник: <?php if ($n['source_url']): ?><a href="<?= e($n['source_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($n['source_name'] ?: $n['source_url']) ?></a><?php else: ?><?= e($n['source_name']) ?><?php endif ?></p>
      <?php endif ?>

      <?php if ($tags): ?>
      <ul class="tags" aria-label="Темы"><?php foreach ($tags as $t): ?><li><a href="/tag/<?= e($t['slug']) ?>">#<?= e($t['name']) ?></a></li><?php endforeach ?></ul>
      <?php endif ?>

      <section class="share" aria-labelledby="share-h">
        <h2 id="share-h">Поделиться</h2>
        <ul>
          <li><a href="https://vk.com/share.php?url=<?= $u ?>&amp;title=<?= $title ?>" target="_blank" rel="noopener noreferrer"><img src="/assets/img/social/vk.svg" alt="" width="22" height="22">ВКонтакте</a></li>
          <li><a href="https://t.me/share/url?url=<?= $u ?>&amp;text=<?= $title ?>" target="_blank" rel="noopener noreferrer"><img src="/assets/img/social/tg.svg" alt="" width="22" height="22">Telegram</a></li>
          <li><a href="https://wa.me/?text=<?= $title . '%20' . $u ?>" target="_blank" rel="noopener noreferrer"><img src="/assets/img/social/whatsapp.svg" alt="" width="22" height="22">WhatsApp</a></li>
          <li><button type="button" data-copy="<?= e($url) ?>"><?= icon('link') ?><span>Копировать ссылку</span></button></li>
          <li class="share__native" hidden><button type="button" data-share="<?= e($url) ?>" data-title="<?= e($n['title']) ?>"><?= icon('share') ?><span>Ещё…</span></button></li>
        </ul>
      </section>
    </article>

    <aside class="article-side">
      <?php if ($latest): ?>
      <section class="fresh" aria-labelledby="lt-h">
        <h2 class="block-title" id="lt-h">Читайте также</h2>
        <ol class="fresh__list"><?php foreach ($latest as $x): ?>
          <li><time datetime="<?= e(date('c', strtotime((string) $x['published_at']))) ?>"><?= e(pub_date($x['published_at'])) ?></time><a href="<?= e(news_path($x)) ?>"><?= e($x['title']) ?></a></li>
        <?php endforeach ?></ol>
      </section>
      <?php endif ?>
    </aside>
  </div>

  <?php if ($related): ?>
  <section class="block" aria-labelledby="rel-h">
    <h2 class="block-title" id="rel-h">Похожие новости</h2>
    <div class="cards"><?php foreach ($related as $x) { echo \App\Core\View::partial('partials/card', ['n' => $x]); } ?></div>
  </section>
  <?php endif ?>
</div>
