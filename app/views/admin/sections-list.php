<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Controllers\Admin\SectionsController $c */
use App\Controllers\Admin\SectionsController as S;
use App\Support\Site; ?>
<p class="hint">Здесь меняются тексты встроенных страниц сайта: заголовок, вводный текст, дополнительный текст внизу и SEO. Пустое поле — стандартный текст. Обычные страницы («О редакции», «Контакты», свои) — в разделе <a href="<?= e(admin_url('pages')) ?>">«Страницы»</a>; включение и отключение разделов — в <a href="<?= e(admin_url('menu')) ?>">«Меню сайта»</a>.</p>
<div class="table-wrap"><table class="table table--hover">
  <thead><tr><th>Раздел</th><th>Адрес</th><th>Что изменено</th><th>Статус</th><th class="r">Действия</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): [$name, $url] = S::info((string) $r['sys_key']);
      $set = array_filter([$r['h1'] !== '' ? 'заголовок' : '', $r['intro'] !== '' ? 'вступление' : '', trim((string) $r['body']) !== '' ? 'текст внизу' : '', ($r['seo_title'] . $r['seo_description']) !== '' ? 'SEO' : '']);
      $off = $r['sys_key'] !== 'home' && !Site::enabled((string) $r['sys_key']); ?>
    <tr class="<?= $off ? 'is-off' : '' ?>">
      <td><a href="<?= e(admin_url('sections/' . (int) $r['id'])) ?>"><b><?= e($name) ?></b></a></td>
      <td><code><?= e($url) ?></code></td>
      <td class="small muted"><?= $set ? e(implode(', ', $set)) : 'стандартные тексты' ?></td>
      <td><?= $off ? '<span class="pill pill--off">раздел отключён</span>' : '<span class="pill pill--ok">на сайте</span>' ?></td>
      <td class="r"><a class="btn btn--ghost btn--sm" href="<?= e(admin_url('sections/' . (int) $r['id'])) ?>"><?= icon('edit') ?> Изменить</a>
        <?php if (!$off): ?><a class="iconbtn" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="Открыть на сайте"><?= icon('external') ?></a><?php endif ?></td></tr>
  <?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted">Данные появятся после первого обращения к сайту (создаются автоматически).</td></tr><?php endif ?>
  </tbody></table></div>
