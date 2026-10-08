<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $files @var array $issues @var array $checks @var string $base @var array $log @var int $total @var int $nf @var int $red */
$bad = array_sum(array_column($issues, 'count')); ?>
<div class="cols">
  <section class="card-a"><h2>Готовность к индексации</h2>
    <ul class="checks"><?php foreach ($checks as [$l, $ok, $hint]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'check' : 'alert') ?><div><?= e($l) ?><?php if (!$ok): ?><small><?= e($hint) ?></small><?php endif ?></div></li><?php endforeach ?></ul>
    <p class="small muted">Материалов в индексе: <?= $total ?> · редиректов: <?= $red ?> · <a href="<?= e(admin_url('seo/404')) ?>">адресов 404: <?= $nf ?></a></p></section>
  <section class="card-a"><h2>Автоматизация</h2>
    <p class="small">При каждом сохранении, публикации или удалении материала система сама обновляет sitemap и RSS, ставит редирект при смене адреса, выдаёт 410 для удалённого и уведомляет Яндекс и Bing через IndexNow. Для Google работает sitemap.xml (<a href="https://search.google.com/search-console" target="_blank" rel="noopener">отправьте его в Search Console</a> один раз).</p>
    <div class="actions">
      <form method="post" action="<?= e(admin_url('seo/regenerate')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn--primary"><?= icon('refresh') ?> Пересоздать SEO-файлы</button></form>
      <form method="post" action="<?= e(admin_url('seo/indexnow')) ?>" class="inline" data-confirm="Отправить в IndexNow все адреса сайта?"><?= csrf_field() ?><button class="btn btn--ghost"><?= icon('upload') ?> Отправить всё в IndexNow</button></form>
    </div></section>
</div>
<section class="card-a"><h2>SEO-файлы</h2>
  <div class="table-wrap"><table class="table"><thead><tr><th>Файл</th><th>Состояние</th><th>Размер</th><th>Обновлён</th></tr></thead><tbody>
  <?php foreach ($files as $f): ?><tr><td><a href="/<?= e($f['name']) ?>" target="_blank" rel="noopener"><code>/<?= e($f['name']) ?></code></a></td>
    <td><?= $f['exists'] ? '<span class="pill pill--ok">статический файл</span>' : '<span class="pill pill--info">отдаётся динамически</span>' ?></td><td><?= $f['exists'] ? e(bytes_h($f['size'])) : '—' ?></td><td class="small"><?= $f['mtime'] ? e(pub_date(date('Y-m-d H:i:s', $f['mtime']))) : '—' ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <p class="small muted">Адрес для Яндекс.Вебмастера и Search Console: <code><?= e($base) ?>/sitemap.xml</code>. Лента для Яндекс Новостей/Дзена: <code><?= e($base) ?>/rss-yandex.xml</code>. Google News: <code>/sitemap-google-news.xml</code>.</p></section>
<section class="card-a"><h2>Аудит материалов <?= $bad ? '<span class="pill pill--warn">' . $bad . '</span>' : '<span class="pill pill--ok">всё хорошо</span>' ?></h2>
  <div class="issues"><?php foreach ($issues as $i): if (!$i['count']) { continue; } ?>
    <details><summary><b><?= e($i['label']) ?></b> <span class="pill pill--warn"><?= (int) $i['count'] ?></span></summary>
      <p class="small muted"><?= e($i['hint']) ?></p><ul class="plain"><?php foreach ($i['rows'] as $r): ?><li><a href="<?= e(admin_url('news/' . $r['id'])) ?>"><?= e($r['title']) ?></a></li><?php endforeach ?></ul></details>
  <?php endforeach ?><?php if (!$bad): ?><p class="muted">Замечаний нет.</p><?php endif ?></div></section>
<section class="card-a"><h2>Последние отправки IndexNow</h2>
  <?php if ($log): ?><table class="table"><tbody><?php foreach ($log as $l): ?><tr><td class="small"><?= e(pub_date($l['created_at'])) ?></td><td><?= e($l['service']) ?></td><td class="small"><?= e(str_limit($l['url'], 70)) ?></td><td><span class="pill pill--<?= in_array((int) $l['http_code'], [200, 202], true) ? 'ok' : 'warn' ?>"><?= (int) $l['http_code'] ?></span></td></tr><?php endforeach ?></tbody></table>
  <?php else: ?><p class="muted">Отправок ещё не было. Они начнутся после публикации материала на рабочем домене (не localhost).</p><?php endif ?></section>
