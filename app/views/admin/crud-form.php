<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $row @var bool $isNew @var array $errors @var \App\Controllers\Admin\CrudController $c @var int $id */ ?>
<form method="post" class="form form--wide" data-dirty-guard>
  <?= csrf_field() ?>
  <?php foreach ($c->fields() as $k => $f): $t = $f['type'] ?? 'text'; $v = $row[$k] ?? ($f['default'] ?? ''); $err = $errors[$k] ?? null; ?>
    <div class="field<?= $t === 'checkbox' ? ' field--check' : '' ?>" data-field="<?= e($k) ?>">
      <?php if ($t === 'checkbox'): ?>
        <label><input type="checkbox" name="<?= e($k) ?>" value="1" <?= $v ? 'checked' : '' ?>> <?= e($f['label']) ?></label>
      <?php else: ?>
        <label for="f-<?= e($k) ?>"><?= e($f['label']) ?><?= !empty($f['required']) ? ' <span class="req">*</span>' : '' ?></label>
        <?php if ($t === 'textarea'): ?><textarea id="f-<?= e($k) ?>" name="<?= e($k) ?>" rows="4"><?= e($v) ?></textarea>
        <?php elseif ($t === 'richtext'): ?><textarea id="f-<?= e($k) ?>" name="<?= e($k) ?>" data-richtext rows="14"><?= e($v) ?></textarea>
        <?php elseif ($t === 'select'): ?><select id="f-<?= e($k) ?>" name="<?= e($k) ?>"><?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string) $v === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
        <?php elseif ($t === 'media'): echo \App\Core\View::partial('admin/_media-field', ['name' => $k, 'mediaId' => (int) $v]);
        elseif ($t === 'mediapath'): echo \App\Core\View::partial('admin/_media-path-field', ['name' => $k, 'path' => (string) $v]);
        elseif ($t === 'datetime'): ?><input type="datetime-local" id="f-<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '') ?>">
        <?php elseif ($t === 'number'): ?><input type="number" id="f-<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($v) ?>">
        <?php else: ?><input id="f-<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($v) ?>" maxlength="<?= (int) ($f['max'] ?? 255) ?>"<?= $t === 'url' ? ' type="url"' : '' ?><?= $t === 'slug' ? ' data-slug-from="' . e($f['from'] ?? 'title') . '"' : '' ?>><?php endif ?>
      <?php endif ?>
      <?php if (!empty($f['help'])): ?><p class="hint"><?= e($f['help']) ?></p><?php endif ?>
      <?php if ($err): ?><p class="field__error"><?= e($err) ?></p><?php endif ?>
    </div>
  <?php endforeach ?>
  <div class="actions"><button class="btn btn--primary btn--lg" type="submit">Сохранить</button><a class="btn btn--ghost btn--lg" href="<?= e(admin_url($c->route())) ?>">Отмена</a></div>
</form>
