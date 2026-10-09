<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
use App\Support\Consent;
/** @var array $stats @var array $checks @var array $rows @var \App\Core\Paginator $p @var string $act @var int $ver @var string $ym */ ?>
<div class="stats">
  <div class="stat"><b><?= (int) $stats['readers'] ?></b><span>читателей сделали выбор (версия текста <?= (int) $ver ?>)</span></div>
  <div class="stat"><b><?= (int) $stats['analytics'] ?></b><span>из них разрешили аналитику</span></div>
  <div class="stat"><b><?= (int) $stats['days30'] ?></b><span>записей за 30 дней</span></div>
  <a class="stat" href="<?= e(admin_url('settings#g-cookies')) ?>"><b><?= Consent::enabled() ? 'Вкл' : 'Выкл' ?></b><span>баннер<?= $ym ? ' · Метрика ' . e($ym) : '' ?> — настройки</span></a>
</div>
<div class="cols">
  <section class="card-a">
    <h2>Чек-лист</h2>
    <ul class="checks"><?php foreach ($checks as [$label, $ok, $hint]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'check' : 'alert') ?><div><?= e($label) ?><?php if (!$ok): ?><small><?= e($hint) ?></small><?php endif ?></div></li><?php endforeach ?></ul>
    <p class="small muted">Вручную: подайте уведомление в Роскомнадзор, если оно для вас обязательно, и убедитесь, что персональные данные россиян хранятся на серверах в РФ. Подробнее — <code>docs/COOKIES.md</code>.</p>
  </section>
  <section class="card-a">
    <h2>Действия</h2>
    <p>Журнал хранит случайный идентификатор браузера, выбор, версию текста и время. IP-адрес и браузер не сохраняются. Записи старше <?= (int) Consent::RETENTION_YEARS ?> лет удаляются сами.</p>
    <p><a class="btn btn--ghost btn--sm" href="<?= e(admin_url('cookies/export')) ?>">Скачать журнал (CSV)</a></p>
    <form method="post" action="<?= e(admin_url('cookies/reset')) ?>" data-confirm="Показать баннер всем читателям заново?"><?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm">Запросить согласие заново</button>
      <p class="small muted">Нужно, если изменились цели обработки. Смена номера счётчика или текста баннера делает это автоматически.</p>
    </form>
  </section>
</div>
<form class="toolbar" method="get"><select name="a" aria-label="Действие"><option value="">Все действия</option><?php foreach (Consent::ACTIONS as $k => $l): ?><option value="<?= e($k) ?>" <?= $act === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select><button class="btn btn--ghost btn--sm">Показать</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Когда</th><th>Идентификатор</th><th>Выбор</th><th>Аналитика</th><th>Версия</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="nowrap small"><?= e(date_ru($r['created_at'], 'j M Y H:i')) ?></td><td><code><?= e(substr($r['cid'], 0, 8)) ?>…</code></td><td><?= e(Consent::ACTIONS[$r['action']] ?? $r['action']) ?></td><td><?= $r['analytics'] ? 'да' : 'нет' ?></td><td><?= (int) $r['policy_ver'] ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Записей пока нет.</td></tr><?php endif ?>
</tbody></table></div><?= paginate_html($p, admin_url('cookies')) ?>
