<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $u @var array $errors @var ?string $pending @var ?array $codes @var string $uri @var array $sessions @var string $currentSid @var int $recoveryLeft */ ?>
<?php if ($codes): ?><div class="alert alert--warn"><b>Сохраните резервные коды</b> — каждый подходит один раз, если потеряете телефон. Они больше не будут показаны.
  <ul class="codes"><?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach ?></ul></div><?php endif ?>
<div class="cols">
<section class="card-a"><h2>Смена пароля</h2>
  <form method="post" class="form" autocomplete="off"><?= csrf_field() ?>
    <label>Отображаемое имя<input name="display_name" value="<?= e($u['display_name']) ?>"></label>
    <label>Текущий пароль<input type="password" name="current" autocomplete="current-password"><?= isset($errors['current']) ? '<span class="field__error">' . e($errors['current']) . '</span>' : '' ?></label>
    <label>Новый пароль<input type="password" name="password" autocomplete="new-password" minlength="12"><?= isset($errors['password']) ? '<span class="field__error">' . e($errors['password']) . '</span>' : '' ?></label>
    <label>Повторите<input type="password" name="password2" autocomplete="new-password"><?= isset($errors['password2']) ? '<span class="field__error">' . e($errors['password2']) . '</span>' : '' ?></label>
    <button class="btn btn--primary">Сохранить</button></form></section>
<section class="card-a"><h2>Двухфакторная аутентификация</h2>
<?php if ($u['totp_enabled']): ?>
  <p><span class="pill pill--ok">Включена</span> Резервных кодов осталось: <?= $recoveryLeft ?>.</p>
  <form method="post" action="<?= e(admin_url('profile/2fa/disable')) ?>" class="form" data-confirm="Отключить 2FA? Защита аккаунта снизится."><?= csrf_field() ?><label>Текущий пароль<input type="password" name="current" required autocomplete="current-password"></label><button class="btn btn--danger">Отключить 2FA</button></form>
<?php else: ?>
  <p>Защитите вход: даже если пароль утечёт, без телефона войти не получится. Подойдёт Яндекс Ключ, Google Authenticator, Authy, 2FAS.</p>
  <div class="qr" id="qr" data-uri="<?= e($uri) ?>" aria-label="QR-код для приложения"></div>
  <p class="small">Не получается отсканировать? Введите ключ вручную: <code class="secret"><?= e(trim(chunk_split((string) $pending, 4, ' '))) ?></code></p>
  <form method="post" action="<?= e(admin_url('profile/2fa/enable')) ?>" class="form form--inline"><?= csrf_field() ?><label>Код из приложения<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></label><button class="btn btn--primary">Включить</button></form>
<?php endif ?></section>
</div>
<section class="card-a"><h2>Мои сеансы</h2>
  <table class="table"><tbody><?php foreach ($sessions as $s): $cur = $s['id'] === $currentSid; ?><tr><td><?= e($s['ip']) ?> <?= $cur ? '<span class="pill pill--ok">этот</span>' : '' ?><div class="small muted"><?= e(str_limit($s['user_agent'], 90)) ?></div></td>
    <td class="small">Активность: <?= e(pub_date(date('Y-m-d H:i:s', (int) $s['last_activity']))) ?></td>
    <td class="r"><?php if (!$cur): ?><form method="post" class="inline" action="<?= e(admin_url('profile/sessions/' . $s['id'] . '/revoke')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--sm">Завершить</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></section>
<script src="/assets/vendor/qrcode.js" defer></script>
