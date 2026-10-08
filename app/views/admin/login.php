<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $error @var string $next */ ?>
<h1>Вход</h1>
<?php if ($error): ?><div class="alert alert--err" role="alert"><?= e($error) ?></div><?php endif ?>
<form method="post" class="form" autocomplete="on">
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <?= csrf_field() ?>
  <label>Логин или e-mail<input name="username" autocomplete="username" required autofocus value="<?= e(old('username')) ?>"></label>
  <label>Пароль<input name="password" type="password" autocomplete="current-password" required></label>
  <button class="btn btn--primary btn--lg" type="submit">Войти</button>
</form>
<p class="muted small">После 5 неудачных попыток вход блокируется на 15 минут.</p>
