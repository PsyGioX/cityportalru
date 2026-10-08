<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $name @var int $mediaId */
$m = $mediaId ? \App\Core\DB::one('SELECT * FROM media WHERE id = ?', [$mediaId]) : null; ?>
<div class="mediafield" data-media-field>
  <input type="hidden" name="<?= e($name) ?>" value="<?= $m ? (int) $m['id'] : '' ?>" id="f-<?= e($name) ?>">
  <div class="mediafield__preview"><?php if ($m): ?><img src="<?= e(media_url($m, 480)) ?>" alt=""><?php endif ?></div>
  <div class="mediafield__btns"><button type="button" class="btn btn--ghost btn--sm" data-pick="single"><?= icon('image') ?> Выбрать</button><button type="button" class="btn btn--ghost btn--sm" data-clear <?= $m ? '' : 'hidden' ?>>Убрать</button></div>
</div>
