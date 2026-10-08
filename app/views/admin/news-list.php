<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var string $status @var string $q @var int $cat @var array $counts @var array $cats */
use App\Core\Auth;
$tabs = ['' => ['Все', $counts['al']], 'published' => ['Опубликованы', $counts['pub']], 'scheduled' => ['Запланированы', $counts['sch']], 'review' => ['На проверке', $counts['rv']], 'draft' => ['Черновики', $counts['dr']]]; ?>
<div class="toolbar">
  <a class="btn btn--primary" href="<?= e(admin_url('news/new')) ?>"><?= icon('plus') ?> Новая новость</a>
  <form class="toolbar__search" method="get"><input type="hidden" name="status" value="<?= e($status) ?>">
    <label class="sr-only" for="nq">Поиск по заголовку</label><input id="nq" name="q" value="<?= e($q) ?>" placeholder="Поиск по заголовку">
    <select name="cat" aria-label="Рубрика"><option value="">Все рубрики</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
    <button class="btn btn--ghost btn--sm" type="submit">Найти</button></form>
</div>
<nav class="tabs" aria-label="Статус"><?php foreach ($tabs as $k => [$l, $n]): ?><a href="<?= e(admin_url('news' . ($k ? '?status=' . $k : ''))) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <span><?= (int) $n ?></span></a><?php endforeach ?></nav>
<form method="post" action="<?= e(admin_url('news/bulk')) ?>" data-confirm-action>
  <?= csrf_field() ?>
  <div class="table-wrap"><table class="table table--hover">
    <thead><tr><?php if (Auth::can('news.publish')): ?><th class="chk"><input type="checkbox" data-check-all aria-label="Выбрать все"></th><?php endif ?><th>Заголовок</th><th>Рубрика</th><th>Статус</th><th>Дата</th><th class="r">Просм.</th><th class="r"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><?php if (Auth::can('news.publish')): ?><td class="chk"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Выбрать"></td><?php endif ?>
        <td><a class="strong" href="<?= e(admin_url('news/' . $r['id'])) ?>"><?= e($r['title']) ?></a>
          <div class="small muted"><?= e($r['display_name'] ?: '—') ?>
            <?php if (!$r['cover_media_id']): ?> · <span class="warn-text">нет обложки</span><?php endif ?>
            <?php if (!$r['seo_description']): ?> · SEO-описание создастся автоматически<?php endif ?></div></td>
        <td><?= e($r['cat_name'] ?: '—') ?></td>
        <td><?= \App\Core\View::partial('admin/_status', ['n' => $r]) ?></td>
        <td class="nowrap small"><?= e($r['published_at'] ? pub_date($r['published_at']) : '—') ?></td>
        <td class="r"><?= number_format((int) $r['views'], 0, ',', ' ') ?></td>
        <td class="r nowrap"><?php if ($r['status'] === 'published' && strtotime((string) $r['published_at']) <= time()): ?><a class="iconbtn" href="/news/<?= e($r['slug']) ?>" target="_blank" rel="noopener" aria-label="Открыть на сайте"><?= icon('external') ?></a><?php endif ?>
          <a class="iconbtn" href="<?= e(admin_url('news/' . $r['id'])) ?>" aria-label="Изменить"><?= icon('edit') ?></a></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">Ничего не найдено.</td></tr><?php endif ?>
    </tbody></table></div>
  <?php if (Auth::can('news.publish') && $rows): ?>
  <div class="bulk"><label class="sr-only" for="bulk-a">Действие</label><select id="bulk-a" name="action"><option value="">Действие с выбранными…</option><option value="publish">Опубликовать</option><option value="draft">В черновики</option><option value="delete">Удалить</option></select>
    <button class="btn btn--ghost btn--sm" type="submit">Применить</button></div>
  <?php endif ?>
</form>
<?= paginate_html($p, admin_url('news')) ?>
