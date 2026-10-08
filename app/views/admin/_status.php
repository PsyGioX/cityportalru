<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $n */
$live = $n['status'] === 'published' && $n['published_at'] && strtotime((string) $n['published_at']) <= time();
$sched = $n['status'] === 'published' && !$live;
[$cls, $label] = $live ? ['ok', 'Опубликовано'] : ($sched ? ['info', 'Запланировано'] : ($n['status'] === 'review' ? ['warn', 'На проверке'] : ['muted', 'Черновик'])); ?>
<span class="pill pill--<?= $cls ?>"><?= $label ?></span>
