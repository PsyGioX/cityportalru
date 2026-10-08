<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $stats @var array $days @var array $top @var array $recent @var array $nfTop @var array $health */
$max = max(1, max($days)); $n = count($days); $bw = 100 / $n; ?>
<div class="stats">
  <a class="stat" href="<?= e(admin_url('news')) ?>"><b><?= $stats['published'] ?></b><span>опубликовано</span></a>
  <a class="stat" href="<?= e(admin_url('news?status=draft')) ?>"><b><?= $stats['drafts'] ?></b><span>черновики и на проверке</span></a>
  <div class="stat"><b><?= number_format($stats['views7'], 0, ',', ' ') ?></b><span>просмотров за 7 дней</span></div>
  <div class="stat"><b><?= number_format($stats['views'], 0, ',', ' ') ?></b><span>просмотров всего</span></div>
</div>
<div class="cols">
  <section class="card-a">
    <h2>Просмотры за 14 дней</h2>
    <svg class="chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Диаграмма просмотров по дням">
      <?php $i = 0; foreach ($days as $d => $v): $h = $v / $max * 34; ?>
        <rect x="<?= round($i * $bw + 0.6, 2) ?>" y="<?= round(38 - $h, 2) ?>" width="<?= round($bw - 1.2, 2) ?>" height="<?= max(0.4, round($h, 2)) ?>" rx="0.6"><title><?= e(date_ru($d, 'j F')) ?>: <?= $v ?></title></rect>
      <?php $i++; endforeach ?>
    </svg>
    <p class="chart__axis"><span><?= e(date_ru(array_key_first($days), 'j F')) ?></span><span>сегодня</span></p>
  </section>
  <section class="card-a">
    <h2>Популярные материалы</h2>
    <?php if ($top): ?><ol class="rank"><?php foreach ($top as $t): ?><li><a href="<?= e(admin_url('news/' . $t['id'])) ?>"><?= e($t['title']) ?></a><b><?= number_format((int) $t['views'], 0, ',', ' ') ?></b></li><?php endforeach ?></ol>
    <?php else: ?><p class="muted">Данных пока нет.</p><?php endif ?>
  </section>
</div>
<div class="cols">
  <section class="card-a">
    <div class="card-a__head"><h2>Последние правки</h2><a class="btn btn--primary btn--sm" href="<?= e(admin_url('news/new')) ?>"><?= icon('plus') ?> Новость</a></div>
    <table class="table"><tbody>
      <?php foreach ($recent as $r): ?><tr><td><a href="<?= e(admin_url('news/' . $r['id'])) ?>"><?= e($r['title']) ?></a><div class="small muted"><?= e($r['display_name'] ?: '—') ?> · <?= e(pub_date($r['updated_at'])) ?></div></td><td class="r"><?= \App\Core\View::partial('admin/_status', ['n' => $r]) ?></td></tr><?php endforeach ?>
    </tbody></table>
  </section>
  <section class="card-a">
    <h2>Здоровье сайта</h2>
    <ul class="checks">
      <?php foreach ($health as [$label, $ok, $hint]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'check' : 'alert') ?><div><?= e($label) ?><?php if (!$ok): ?><small><?= e($hint) ?></small><?php endif ?></div></li><?php endforeach ?>
    </ul>
    <?php if ($nfTop): ?><h3>Частые 404</h3><ul class="plain"><?php foreach ($nfTop as $x): ?><li><code><?= e($x['path']) ?></code> <b><?= (int) $x['hits'] ?></b></li><?php endforeach ?></ul><a href="<?= e(admin_url('seo/404')) ?>">Разобрать →</a><?php endif ?>
  </section>
</div>
