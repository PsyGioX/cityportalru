<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $latest @var int $code */ ?>
<div class="container container--narrow">
  <div class="e404">
    <p class="e404__code" aria-hidden="true"><?= (int) $code ?></p>
    <h1><?= $code === 410 ? 'Страница удалена' : 'Такой страницы нет' ?></h1>
    <p><?= $code === 410 ? 'Материал был удалён редакцией.' : 'Возможно, адрес изменился или в нём опечатка.' ?> Попробуйте поиск или перейдите на главную.</p>
    <form class="search search--page" action="/search" method="get" role="search"><label class="sr-only" for="q404">Поиск</label><input id="q404" type="search" name="q" placeholder="Поиск по новостям" minlength="2"><button class="btn btn--primary" type="submit">Найти</button></form>
    <p><a class="btn btn--ghost" href="/">На главную</a></p>
  </div>
  <?php if ($latest): ?><section class="block"><h2 class="block-title">Свежие новости</h2><div class="cards"><?php foreach ($latest as $n) { echo \App\Core\View::partial('partials/card', ['n' => $n]); } ?></div></section><?php endif ?>
</div>
