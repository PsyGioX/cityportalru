<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p */ ?>
<p class="muted">Адреса, по которым посетители и роботы получили «Страница не найдена». Частые — повод настроить редирект: так вы вернёте трафик и ссылки.</p>
<div class="table-wrap"><table class="table table--hover"><thead><tr><th>Адрес</th><th class="r">Раз</th><th>Последний раз</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><code><?= e($r['path']) ?></code></td><td class="r"><b><?= (int) $r['hits'] ?></b></td><td class="small nowrap"><?= e(pub_date($r['last_seen_at'])) ?></td>
  <td class="r nowrap"><?php foreach ([['redirect', 'Создать редирект', 'arrow-right', ''], ['ignore', 'Игнорировать', 'close', ''], ['delete', 'Удалить запись', 'trash', ' iconbtn--danger']] as [$a, $l, $i, $c]): ?>
    <form method="post" class="inline" action="<?= e(admin_url('seo/404/' . $r['id'] . '/' . $a)) ?>"><?= csrf_field() ?><button class="iconbtn<?= $c ?>" title="<?= e($l) ?>" aria-label="<?= e($l) ?>"><?= icon($i) ?></button></form><?php endforeach ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="4" class="muted">Ошибок 404 не зафиксировано.</td></tr><?php endif ?></tbody></table></div>
<?= paginate_html($p, admin_url('seo/404')) ?>
