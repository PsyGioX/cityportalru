<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $crumbs */ if (!$crumbs) { return; } ?>
<nav class="crumbs" aria-label="Навигационная цепочка">
  <ol>
    <?php foreach ($crumbs as $i => [$name, $href]): ?>
      <li><?php if ($href !== null): ?><a href="<?= e($href) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif ?></li>
    <?php endforeach ?>
  </ol>
</nav>
