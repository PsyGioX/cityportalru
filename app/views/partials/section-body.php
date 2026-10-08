<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $sec Страница раздела из админки: дополнительный текст под основным содержимым */
$b = trim((string) ($sec['body'] ?? '')); ?>
<?php if ($b !== ''): ?><div class="prose section-body"><?= \App\Support\Site::fillPlaceholders($b) ?></div><?php endif ?>
