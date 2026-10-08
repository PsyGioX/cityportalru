<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var \App\Core\Paginator $p @var string $base */ if ($p->pages < 2) { return; } ?>
<nav class="pagination" aria-label="Страницы">
  <?php if ($p->page > 1): ?><a class="pagination__nav" rel="prev" href="<?= e($p->url($p->page - 1, $base)) ?>"><?= icon('chevron-left') ?><span>Назад</span></a><?php endif ?>
  <ul>
    <?php foreach ($p->window() as $i): ?>
      <?php if ($i === 0): ?><li class="gap" aria-hidden="true">…</li>
      <?php elseif ($i === $p->page): ?><li><span class="current" aria-current="page"><?= $i ?></span></li>
      <?php else: ?><li><a href="<?= e($p->url($i, $base)) ?>" aria-label="Страница <?= $i ?>"><?= $i ?></a></li><?php endif ?>
    <?php endforeach ?>
  </ul>
  <?php if ($p->page < $p->pages): ?><a class="pagination__nav" rel="next" href="<?= e($p->url($p->page + 1, $base)) ?>"><span>Вперёд</span><?= icon('chevron-right') ?></a><?php endif ?>
</nav>
