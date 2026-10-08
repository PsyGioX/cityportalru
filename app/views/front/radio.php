<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $name */ ?>
<div class="container container--narrow">
  <?= \App\Core\View::partial('partials/breadcrumbs', ['crumbs' => [['Главная', '/'], ['Радио', null]]]) ?>
  <header class="page-head"><h1><?= e(sec($sec ?? [], 'h1', 'Радио «' . $name . '» онлайн')) ?></h1><p class="page-head__intro"><?= e(sec($sec ?? [], 'intro', 'Прямой эфир городского радио. Нажмите кнопку — звук запустится сразу.')) ?></p></header>
  <div class="player">
    <button type="button" class="player__btn" id="radio-big" aria-pressed="false" aria-label="Слушать «<?= e($name) ?>»"><?= icon('play', 'ico-play') ?><?= icon('pause', 'ico-pause') ?></button>
    <div><p class="player__name"><?= e($name) ?></p><p class="player__state" id="radio-state" aria-live="polite">В эфире</p></div>
    <span class="eq eq--lg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
  </div>
  <p class="muted">Радио продолжит играть, пока вы переходите по страницам сайта и читаете новости. Эфир остановится, если обновить страницу (F5), закрыть вкладку или открыть другой сайт в этой же вкладке.</p>
  <?= \App\Core\View::partial('partials/section-body', ['sec' => $sec ?? []]) ?>
</div>
