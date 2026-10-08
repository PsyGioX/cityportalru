<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $u @var int $id @var array $errors @var bool $self @var array $sessions */ ?>
<form method="post" class="form form--wide" autocomplete="off"><?= csrf_field() ?>
  <div class="field-row"><div class="field"><label for="u-username">Логин</label><input id="u-username" name="username" value="<?= e($u['username']) ?>" required><?= isset($errors['username']) ? '<p class="field__error">' . e($errors['username']) . '</p>' : '' ?></div>
    <div class="field"><label for="u-email">E-mail</label><input id="u-email" name="email" type="email" value="<?= e($u['email']) ?>" required><?= isset($errors['email']) ? '<p class="field__error">' . e($errors['email']) . '</p>' : '' ?></div></div>
  <div class="field"><label for="u-name">Отображаемое имя</label><input id="u-name" name="display_name" value="<?= e($u['display_name']) ?>"></div>
  <div class="field"><label for="u-role">Роль</label><select id="u-role" name="role" <?= $self ? 'disabled' : '' ?>><?php foreach (\App\Core\Auth::ROLES as $k => $l): ?><option value="<?= e($k) ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
    <?php if ($self): ?><input type="hidden" name="role" value="<?= e($u['role']) ?>"><?php endif ?><?= isset($errors['role']) ? '<p class="field__error">' . e($errors['role']) . '</p>' : '' ?></div>
  <div class="field"><label for="u-pw"><?= $id ? 'Новый пароль (оставьте пустым, чтобы не менять)' : 'Пароль' ?></label><input id="u-pw" name="password" type="password" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
    <p class="hint">Не короче 12 символов. При смене пароля все сеансы пользователя завершаются.</p><?= isset($errors['password']) ? '<p class="field__error">' . e($errors['password']) . '</p>' : '' ?></div>
  <div class="field field--check"><label><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>> Учётная запись активна</label></div>
  <div class="field field--check"><label><input type="checkbox" name="must_change_password" value="1" <?= !empty($u['must_change_password']) ? 'checked' : '' ?>> Потребовать смену пароля при следующем входе</label></div>
  <?php if ($id && $u['totp_enabled']): ?><div class="field field--check"><label><input type="checkbox" name="reset_2fa" value="1"> Сбросить двухфакторную аутентификацию (пользователь потерял устройство)</label></div><?php endif ?>
  <div class="actions"><button class="btn btn--primary btn--lg">Сохранить</button><a class="btn btn--ghost btn--lg" href="<?= e(admin_url('users')) ?>">Отмена</a></div>
</form>
<?php if ($sessions): ?><section class="card-a"><h2>Активные сеансы</h2><ul class="plain"><?php foreach ($sessions as $s): ?><li><?= e($s['ip']) ?> · <span class="small muted"><?= e(str_limit($s['user_agent'], 80)) ?> · <?= e(pub_date(date('Y-m-d H:i:s', (int) $s['last_activity']))) ?></span></li><?php endforeach ?></ul></section><?php endif ?>
