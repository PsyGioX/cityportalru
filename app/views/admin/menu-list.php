<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Controllers\Admin\MenuController $c */
$n = count($rows); ?>
<div class="toolbar"><a class="btn btn--primary" href="<?= e(admin_url('menu/new')) ?>"><?= icon('plus') ?> Добавить пункт</a></div>
<p class="hint">Здесь — разделы в шапке и подвале сайта. <b>Отключённый</b> раздел скрывается из меню и перестаёт открываться (ответ «страница не найдена»), включить его можно в любой момент. Разделы сайта (Новости, Афиша, Кино, Погода, Радио) удалить нельзя — только отключить; свои страницы и ссылки удаляются.</p>
<form method="post" id="bulk-form" action="<?= e(admin_url('menu/bulk')) ?>" data-confirm-action><?= csrf_field() ?></form>
<div class="table-wrap"><table class="table table--hover">
  <thead><tr><th class="chk"><input type="checkbox" data-check-all aria-label="Выбрать все"></th><th>Порядок</th><th>Название</th><th>Адрес</th><th>Тип</th><th>Где показан</th><th>Статус</th><th class="r">Действия</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i => $row): $id = (int) $row['id']; ?>
    <tr class="<?= $row['is_active'] ? '' : 'is-off' ?>">
      <td class="chk"><input type="checkbox" form="bulk-form" name="ids[]" value="<?= $id ?>" aria-label="Выбрать «<?= e($row['title']) ?>»"></td>
      <td><span class="menu-order">
        <form method="post" action="<?= e(admin_url("menu/$id/move/up")) ?>" class="inline"><?= csrf_field() ?><button class="iconbtn" aria-label="Выше" <?= $i === 0 ? 'disabled' : '' ?>><?= icon('arrow-up') ?></button></form>
        <form method="post" action="<?= e(admin_url("menu/$id/move/down")) ?>" class="inline"><?= csrf_field() ?><button class="iconbtn" aria-label="Ниже" <?= $i === $n - 1 ? 'disabled' : '' ?>><?= icon('arrow-down') ?></button></form></span></td>
      <td><a href="<?= e(admin_url("menu/$id")) ?>"><b><?= e($row['title']) ?></b></a></td>
      <td><code><?= e($row['url']) ?></code></td>
      <td><?= $c->cell($row, 'kind') ?></td>
      <td class="small muted"><?= implode(', ', array_filter([$row['in_header'] ? 'шапка' : '', $row['in_footer'] ? 'подвал' : ''])) ?: '—' ?></td>
      <td><?= $c->cell($row, 'is_active') ?></td>
      <td class="r">
        <form method="post" action="<?= e(admin_url("menu/$id/toggle")) ?>" class="inline"><?= csrf_field() ?><button class="btn btn--ghost btn--sm"><?= $row['is_active'] ? 'Отключить' : 'Включить' ?></button></form>
        <a class="iconbtn" href="<?= e(admin_url("menu/$id")) ?>" aria-label="Изменить"><?= icon('edit') ?></a>
        <?php if ($row['kind'] !== 'system'): ?>
        <form method="post" action="<?= e(admin_url("menu/$id/delete")) ?>" class="inline" data-confirm="Удалить пункт меню «<?= e($row['title']) ?>»?"><?= csrf_field() ?><button class="iconbtn iconbtn--danger" aria-label="Удалить"><?= icon('trash') ?></button></form>
        <?php endif ?></td></tr>
  <?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Пунктов пока нет.</td></tr><?php endif ?>
  </tbody></table></div>
<?php if ($rows): ?>
<div class="bulkbar"><label class="sr-only" for="bulk-a">Действие с отмеченными</label>
  <select id="bulk-a" name="action" form="bulk-form"><option value="">Действие с отмеченными…</option><?php foreach ($c->bulkActions() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach ?></select>
  <button class="btn btn--ghost btn--sm" type="submit" form="bulk-form">Применить</button></div>
<?php endif ?>
