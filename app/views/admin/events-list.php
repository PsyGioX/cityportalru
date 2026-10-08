<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var string $q */ ?>
<div class="toolbar"><a class="btn btn--primary" href="<?= e(admin_url('events/new')) ?>"><?= icon('plus') ?> Новое событие</a>
  <form class="toolbar__search" method="get"><label class="sr-only" for="eq">Поиск по названию</label><input id="eq" name="q" value="<?= e($q) ?>" placeholder="Поиск по названию"><button class="btn btn--ghost btn--sm" type="submit">Найти</button></form></div>
<form method="post" id="bulk-form" action="<?= e(admin_url('events/bulk')) ?>" data-confirm-action><?= csrf_field() ?></form>
<div class="table-wrap"><table class="table table--hover"><thead><tr><th class="chk"><input type="checkbox" data-check-all aria-label="Выбрать все"></th><th>Событие</th><th>Когда</th><th>Место</th><th>Статус</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($rows as $r): $past = strtotime((string) ($r['ends_at'] ?: $r['starts_at'])) < time(); ?>
  <tr><td class="chk"><input type="checkbox" form="bulk-form" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Выбрать"></td><td><a class="strong" href="<?= e(admin_url('events/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td><td class="nowrap"><?= e(date_ru($r['starts_at'], 'j F Y, H:i')) ?></td><td><?= e($r['place'] ?: '—') ?></td>
    <td><span class="pill pill--<?= $past ? 'muted' : ($r['status'] === 'published' ? 'ok' : 'warn') ?>"><?= $past ? 'Прошло' : ($r['status'] === 'published' ? 'Опубликовано' : 'Черновик') ?></span></td>
    <td class="r"><a class="iconbtn" href="<?= e(admin_url('events/' . $r['id'])) ?>" aria-label="Изменить"><?= icon('edit') ?></a>
      <form method="post" class="inline" action="<?= e(admin_url('events/' . $r['id'] . '/delete')) ?>" data-confirm="Удалить событие?"><?= csrf_field() ?><button class="iconbtn iconbtn--danger" aria-label="Удалить"><?= icon('trash') ?></button></form></td></tr>
<?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">Событий пока нет. Добавьте первое — оно появится в афише и в Яндекс/Google как «Событие».</td></tr><?php endif ?>
</tbody></table></div>
<?php if ($rows): ?>
<div class="bulkbar"><label class="sr-only" for="bulk-a">Действие с отмеченными</label>
  <select id="bulk-a" name="action" form="bulk-form"><option value="">Действие с отмеченными…</option><option value="publish">Опубликовать</option><option value="draft">В черновики</option><option value="delete">Удалить</option></select>
  <button class="btn btn--ghost btn--sm" type="submit" form="bulk-form">Применить</button></div>
<?php endif ?>
<?= paginate_html($p, admin_url('events')) ?>
