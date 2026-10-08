<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var \App\Controllers\Admin\CrudController $c */ $r = $c->route(); $acts = $c->bulkActions(); ?>
<div class="toolbar"><a class="btn btn--primary" href="<?= e(admin_url($r . '/new')) ?>"><?= icon('plus') ?> Добавить</a><?php if ($r === 'pages'): ?><a class="btn btn--ghost" href="<?= e(admin_url('sections')) ?>">Страницы разделов (Главная, Афиша, Кино…)</a><?php endif ?></div>
<form method="post" id="bulk-form" action="<?= e(admin_url($r . '/bulk')) ?>" data-confirm-action><?= csrf_field() ?></form>
<div class="table-wrap"><table class="table table--hover">
  <thead><tr><th class="chk"><input type="checkbox" data-check-all aria-label="Выбрать все"></th><?php foreach ($c->columns() as $label): ?><th><?= e($label) ?></th><?php endforeach ?><th class="r">Действия</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr><td class="chk"><input type="checkbox" form="bulk-form" name="ids[]" value="<?= (int) $row['id'] ?>" aria-label="Выбрать"></td><?php $first = true; foreach (array_keys($c->columns()) as $k): ?>
      <td><?= $first ? '<a href="' . e(admin_url($r . '/' . $row['id'])) . '">' . $c->cell($row, $k) . '</a>' : $c->cell($row, $k) ?></td><?php $first = false; endforeach ?>
      <td class="r"><a class="iconbtn" href="<?= e(admin_url($r . '/' . $row['id'])) ?>" aria-label="Изменить"><?= icon('edit') ?></a>
        <form method="post" action="<?= e(admin_url($r . '/' . $row['id'] . '/delete')) ?>" class="inline" data-confirm="Удалить запись безвозвратно?"><?= csrf_field() ?><button class="iconbtn iconbtn--danger" aria-label="Удалить"><?= icon('trash') ?></button></form></td></tr>
  <?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($c->columns()) + 2 ?>" class="muted">Записей пока нет.</td></tr><?php endif ?>
  </tbody></table></div>
<?php if ($rows): ?>
<div class="bulkbar"><label class="sr-only" for="bulk-a">Действие с отмеченными</label>
  <select id="bulk-a" name="action" form="bulk-form"><option value="">Действие с отмеченными…</option><?php foreach ($acts as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach ?></select>
  <button class="btn btn--ghost btn--sm" type="submit" form="bulk-form">Применить</button></div>
<?php endif ?>
<?= paginate_html($p, admin_url($r)) ?>
