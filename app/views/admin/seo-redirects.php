<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var string $prefill */ ?>
<section class="card-a"><h2>Добавить правило</h2>
  <form method="post" action="<?= e(admin_url('seo/redirects')) ?>" class="form form--inline"><?= csrf_field() ?>
    <label>Откуда (путь)<input name="from_path" value="<?= e($prefill) ?>" placeholder="/old-page" required></label>
    <label>Куда (путь или URL)<input name="to_url" placeholder="/news/new-page"></label>
    <label>Код<select name="code"><option value="301">301 — навсегда</option><option value="302">302 — временно</option><option value="307">307</option><option value="308">308</option><option value="410">410 — удалено</option></select></label>
    <label>Заметка<input name="note" maxlength="255"></label>
    <button class="btn btn--primary">Сохранить</button></form>
  <p class="hint">Редиректы со старых адресов статического сайта (<code>/pages_news/N.html</code>, <code>/news_all.html</code> и др.) уже встроены. Здесь — ваши дополнительные правила; при смене адреса новости правило создаётся автоматически.</p></section>
<div class="table-wrap"><table class="table table--hover"><thead><tr><th>Откуда</th><th>Куда</th><th>Код</th><th class="r">Переходов</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><code><?= e($r['from_path']) ?></code><div class="small muted"><?= e($r['note']) ?></div></td><td><code><?= e($r['to_url'] ?: '—') ?></code></td><td><span class="pill pill--<?= (int) $r['code'] === 410 ? 'warn' : 'info' ?>"><?= (int) $r['code'] ?></span></td><td class="r"><?= (int) $r['hits'] ?></td>
  <td class="r"><form method="post" class="inline" action="<?= e(admin_url('seo/redirects/' . $r['id'] . '/delete')) ?>" data-confirm="Удалить правило?"><?= csrf_field() ?><button class="iconbtn iconbtn--danger" aria-label="Удалить"><?= icon('trash') ?></button></form></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Правил пока нет.</td></tr><?php endif ?></tbody></table></div>
<?= paginate_html($p, admin_url('seo/redirects')) ?>
