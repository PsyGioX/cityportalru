<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $e @var int $id @var array $errors */ $dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : ''; ?>
<form method="post" class="form form--wide" data-dirty-guard><?= csrf_field() ?>
  <div class="field"><label for="f-title">Название <span class="req">*</span></label><input id="f-title" name="title" value="<?= e($e['title']) ?>" required maxlength="255"><?= isset($errors['title']) ? '<p class="field__error">' . e($errors['title']) . '</p>' : '' ?></div>
  <div class="field-row">
    <div class="field"><label for="f-starts_at">Начало <span class="req">*</span></label><input type="datetime-local" id="f-starts_at" name="starts_at" value="<?= e($dt($e['starts_at'])) ?>" required><?= isset($errors['starts_at']) ? '<p class="field__error">' . e($errors['starts_at']) . '</p>' : '' ?></div>
    <div class="field"><label for="f-ends_at">Окончание</label><input type="datetime-local" id="f-ends_at" name="ends_at" value="<?= e($dt($e['ends_at'])) ?>"><?= isset($errors['ends_at']) ? '<p class="field__error">' . e($errors['ends_at']) . '</p>' : '' ?></div>
  </div>
  <div class="field-row">
    <div class="field"><label for="f-place">Место</label><input id="f-place" name="place" value="<?= e($e['place']) ?>" placeholder="Городской парк культуры и отдыха"></div>
    <div class="field"><label for="f-address">Адрес</label><input id="f-address" name="address" value="<?= e($e['address']) ?>" placeholder="ул. Красная, 1"></div>
  </div>
  <div class="field-row">
    <div class="field"><label for="f-price">Стоимость</label><input id="f-price" name="price" value="<?= e($e['price']) ?>" placeholder="Бесплатно / 300 ₽"></div>
    <div class="field"><label for="f-ticket_url">Ссылка на покупку билета</label><input id="f-ticket_url" name="ticket_url" type="url" value="<?= e($e['ticket_url']) ?>"></div>
  </div>
  <div class="field"><label>Афиша / обложка</label><?= \App\Core\View::partial('admin/_media-field', ['name' => 'cover_media_id', 'mediaId' => (int) ($e['cover_media_id'] ?? 0)]) ?></div>
  <div class="field"><label for="f-description">Описание</label><textarea id="f-description" name="description" data-richtext rows="10"><?= e($e['description']) ?></textarea></div>
  <details class="adv"><summary>SEO и адрес</summary>
    <div class="field"><label for="f-slug">Адрес (URL)</label><input id="f-slug" name="slug" value="<?= e($e['slug']) ?>" placeholder="создастся из названия и даты"></div>
    <div class="field"><label for="f-seo_title">SEO-заголовок</label><input id="f-seo_title" name="seo_title" value="<?= e($e['seo_title'] ?? '') ?>" maxlength="190"></div>
    <div class="field"><label for="f-seo_description">SEO-описание</label><textarea id="f-seo_description" name="seo_description" rows="3" maxlength="320"><?= e($e['seo_description'] ?? '') ?></textarea></div></details>
  <div class="field"><label for="f-status">Статус</label><select id="f-status" name="status"><option value="published" <?= $e['status'] === 'published' ? 'selected' : '' ?>>Опубликовано</option><option value="draft" <?= $e['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option></select></div>
  <div class="actions"><button class="btn btn--primary btn--lg">Сохранить</button><a class="btn btn--ghost btn--lg" href="<?= e(admin_url('events')) ?>">Отмена</a></div>
</form>
