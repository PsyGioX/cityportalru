<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var string $error */ ?>
<h1>Код подтверждения</h1>
<p class="muted">Введите 6-значный код из приложения-аутентификатора или один из резервных кодов.</p>
<?php if ($error): ?><div class="alert alert--err" role="alert"><?= e($error) ?></div><?php endif ?>
<form method="post" class="form">
  <?= csrf_field() ?>
  <label>Код<input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="20"></label>
  <button class="btn btn--primary btn--lg" type="submit">Подтвердить</button>
</form>
<p><a href="<?= e(admin_url('login')) ?>">← Вернуться ко входу</a></p>
