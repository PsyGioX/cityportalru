<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows @var \App\Core\Paginator $p @var string $q */ ?>
<form class="toolbar" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Поиск: пользователь, действие, детали" aria-label="Поиск"><button class="btn btn--ghost btn--sm">Найти</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Объект</th><th>Детали</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="nowrap small"><?= e(date_ru($r['created_at'], 'j M H:i:s')) ?></td><td><?= e($r['username'] ?: '—') ?></td><td><code><?= e($r['action']) ?></code></td><td class="small"><?= e($r['entity'] . ($r['entity_id'] !== '' ? ' #' . $r['entity_id'] : '')) ?></td><td class="small"><?= e(str_limit((string) $r['details'], 90)) ?></td><td class="small"><?= e($r['ip']) ?></td></tr><?php endforeach ?>
</tbody></table></div><?= paginate_html($p, admin_url('audit')) ?>
