<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $exposed @var array $backups @var array $info @var array $dirs @var array $db */ ?>
<div class="cols">
<section class="card-a"><h2>Безопасность сервера</h2><p class="small muted">Реальные запросы к вашему сайту: служебные файлы не должны отдаваться (ожидаем 403/404).</p>
  <ul class="checks"><?php foreach ($exposed as $p => $c): $ok = !in_array($c, [200, 206], true); ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'check' : 'alert') ?><div><code><?= e($p) ?></code> — ответ <?= $c ?: 'нет связи' ?><?php if (!$ok): ?><small>ФАЙЛ ДОСТУПЕН ИЗ ИНТЕРНЕТА! Направьте корень сайта на public/ или проверьте .htaccess / nginx.</small><?php endif ?></div></li><?php endforeach ?></ul>
  <h3>Права на запись</h3><ul class="checks"><?php foreach ($dirs as $d => $w): ?><li class="<?= $w ? 'ok' : 'bad' ?>"><?= icon($w ? 'check' : 'alert') ?><div><code><?= e($d) ?></code></div></li><?php endforeach ?></ul></section>
<section class="card-a"><h2>Окружение</h2><dl class="dl"><?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach ?></dl></section>
</div>
<section class="card-a"><h2>Резервные копии базы данных</h2>
  <div class="actions"><form method="post" action="<?= e(admin_url('system/backup')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn--primary"><?= icon('download') ?> Создать копию</button></form>
    <form method="post" action="<?= e(admin_url('system/cache')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn--ghost"><?= icon('refresh') ?> Сбросить кэш</button></form>
    <form method="post" action="<?= e(admin_url('system/cleanup')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn--ghost">Запустить фоновые задачи</button></form></div>
  <table class="table"><tbody><?php foreach ($backups as $b): ?><tr><td><code><?= e($b['name']) ?></code></td><td><?= e(bytes_h((int) $b['size'])) ?></td><td class="small"><?= e(pub_date(date('Y-m-d H:i:s', $b['time']))) ?></td><td class="r"><a class="btn btn--ghost btn--sm" href="<?= e(admin_url('system/backup/' . $b['name'])) ?>"><?= icon('download') ?> Скачать</a></td></tr><?php endforeach ?>
  <?php if (!$backups): ?><tr><td class="muted">Копий пока нет. Хранятся последние 14. Загрузите скачанную копию в phpMyAdmin → Импорт.</td></tr><?php endif ?></tbody></table>
  <p class="small muted">Файлы изображений (папка <code>public/uploads</code>) копируйте отдельно через FTP/панель хостинга. Автокопия: <code>0 3 * * * php <?= e(BASE_PATH) ?>/bin/backup.php</code></p></section>
<section class="card-a"><h2>Таблицы</h2><table class="table"><tbody><?php foreach ($db as $t): ?><tr><td><code><?= e($t['t']) ?></code></td><td class="r"><?= (int) $t['r'] ?> строк</td><td class="r"><?= (int) $t['kb'] ?> КБ</td></tr><?php endforeach ?></tbody></table></section>
