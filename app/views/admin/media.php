<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var string $q */ ?>
<?php if (!\App\Support\Images::available()): ?><div class="alert alert--warn">На сервере не включено расширение PHP <b>GD</b> — загрузка изображений временно недоступна. Включите <code>php-gd</code> в панели хостинга.</div><?php elseif (!\App\Support\Images::webpSupported()): ?><div class="alert alert--info">GD на сервере собран без WebP: изображения нарезаются в JPEG/PNG. Сайт работает полностью, файлы чуть тяжелее.</div><?php endif ?>
<div class="dropzone" id="dropzone" data-upload="<?= e(admin_url('media/upload')) ?>" tabindex="0" role="button" aria-label="Загрузить файлы">
  <?= icon('upload') ?><p><b>Перетащите изображения сюда</b> или нажмите, чтобы выбрать</p><p class="small muted">JPEG, PNG, WebP, GIF · до 12 МБ · создадутся WebP-версии для быстрой загрузки, метаданные (GPS) удалятся</p>
  <input type="file" id="dz-input" multiple accept="image/jpeg,image/png,image/webp,image/gif" hidden></div>
<ul class="upload-status" id="upload-status" aria-live="polite"></ul>
<form class="toolbar" method="get"><label class="sr-only" for="mq">Поиск</label><input id="mq" name="q" value="<?= e($q) ?>" placeholder="Поиск по описанию"><button class="btn btn--ghost btn--sm">Найти</button></form>
<form method="post" id="bulk-form" action="<?= e(admin_url('media/bulk')) ?>" data-confirm-action><?= csrf_field() ?></form>
<?php if ($rows): ?><p class="bulkbar"><label class="inline-check"><input type="checkbox" data-check-all> Выбрать все на странице</label></p><?php endif ?>
<ul class="media-grid">
<?php foreach ($rows as $m): ?>
  <li class="media-item"><input type="checkbox" class="media-item__chk" form="bulk-form" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="Выбрать файл <?= (int) $m['id'] ?>"><details><summary><img src="<?= e(media_url($m, 480)) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" width="240" height="180"><span class="media-item__meta"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> · <?= e(bytes_h((int) $m['size'])) ?><?= $m['alt'] === '' ? ' · <b class="warn-text">нет alt</b>' : '' ?></span></summary>
    <form method="post" action="<?= e(admin_url('media/' . $m['id'])) ?>" class="form form--sm"><?= csrf_field() ?>
      <label>Описание (alt)<input name="alt" value="<?= e($m['alt']) ?>" maxlength="255"></label>
      <label>Подпись<input name="caption" value="<?= e($m['caption']) ?>" maxlength="500"></label>
      <label>Автор фото<input name="credit" value="<?= e($m['credit']) ?>" maxlength="190"></label>
      <p class="small muted">ID <?= (int) $m['id'] ?> · <code><?= e(media_url($m)) ?></code></p>
      <div class="actions"><button class="btn btn--primary btn--sm">Сохранить</button></div></form>
    <form method="post" action="<?= e(admin_url('media/' . $m['id'] . '/delete')) ?>" class="inline" data-confirm="Удалить файл?"><?= csrf_field() ?><button class="btn btn--danger btn--sm"><?= icon('trash') ?> Удалить</button></form>
  </details></li>
<?php endforeach ?>
</ul>
<?php if (!$rows): ?><p class="muted">Файлов нет.</p><?php else: ?>
<div class="bulkbar"><select name="action" form="bulk-form" aria-label="Действие с отмеченными"><option value="">Действие с отмеченными…</option><option value="delete">Удалить (неиспользуемые)</option></select>
  <button class="btn btn--ghost btn--sm" type="submit" form="bulk-form">Применить</button></div><?php endif ?>
<?= paginate_html($p, admin_url('media')) ?>
