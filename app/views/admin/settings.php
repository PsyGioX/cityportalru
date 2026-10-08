<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $groups @var array $errors */
$wxErr = \App\Support\Weather::lastError(); ?>
<form method="post" class="settings" data-dirty-guard><?= csrf_field() ?>
  <nav class="tabs" aria-label="Разделы настроек"><?php foreach ($groups as $gk => [$gl]): ?><a href="#g-<?= e($gk) ?>"><?= e($gl) ?></a><?php endforeach ?></nav>
  <?php foreach ($groups as $gk => [$gl, $fields]): ?>
  <section class="card-a" id="g-<?= e($gk) ?>"><h2><?= e($gl) ?></h2>
    <div class="form form--wide">
    <?php foreach ($fields as $key => $d): [$type, $label] = $d; $help = $d[2] ?? ''; $val = (string) setting($key, ''); ?>
      <div class="field<?= $type === 'checkbox' ? ' field--check' : '' ?>">
        <?php if ($type === 'checkbox'): ?><label><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
        <?php else: ?><label for="s-<?= e($key) ?>"><?= e($label) ?></label>
          <?php if ($type === 'textarea'): ?><textarea id="s-<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($val) ?></textarea>
          <?php elseif ($type === 'secret'): ?><input id="s-<?= e($key) ?>" name="<?= e($key) ?>" type="password" autocomplete="new-password" placeholder="<?= $val !== '' ? '•••••••• (задан, оставьте пустым чтобы не менять)' : 'не задан' ?>">
            <?php if ($val !== ''): ?><label class="inline-check"><input type="checkbox" name="clear_<?= e($key) ?>" value="1"> удалить значение</label><?php endif ?>
          <?php elseif ($type === 'select'): ?><select id="s-<?= e($key) ?>" name="<?= e($key) ?>"><?php foreach ($d[3] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= ($val ?: array_key_first($d[3])) === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
          <?php elseif ($type === 'media'): echo \App\Core\View::partial('admin/_media-field', ['name' => $key, 'mediaId' => (int) $val]);
          elseif ($type === 'color'): $cv = preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? $val : \App\Support\Brand::DEFAULT_ACCENT; ?><input id="s-<?= e($key) ?>" type="color" name="<?= e($key) ?>" value="<?= e($cv) ?>">
          <?php elseif ($type === 'date'): ?><input id="s-<?= e($key) ?>" type="date" name="<?= e($key) ?>" value="<?= e($val) ?>">
          <?php else: ?><input id="s-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>"><?php endif ?>
        <?php endif ?>
        <?php if ($help): ?><p class="hint"><?= e($help) ?></p><?php endif ?>
        <?php if ($key === 'weather_api_key' && $wxErr !== ''): ?><p class="field__error">Последний ответ WeatherAPI: <?= e($wxErr) ?></p><?php endif ?>
        <?php if (isset($errors[$key])): ?><p class="field__error"><?= e($errors[$key]) ?></p><?php endif ?>
      </div>
    <?php endforeach ?>
    </div></section>
  <?php endforeach ?>
  <div class="actions actions--sticky"><button class="btn btn--primary btn--lg">Сохранить настройки</button></div>
</form>
