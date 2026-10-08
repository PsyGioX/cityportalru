<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $n @var int $id @var array $errors @var string $tags @var array $galleryMedia @var array $cats @var array $allTags @var bool $canPublish */
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : ''; $live = $id && $n['status'] === 'published' && strtotime((string) $n['published_at']) <= time(); ?>
<form method="post" class="editor-form" data-dirty-guard id="news-form" data-site="<?= e(\App\Core\Request::baseUrl()) ?>">
  <?= csrf_field() ?>
  <div class="editor-main">
    <div class="field"><label for="f-title">Заголовок <span class="req">*</span></label>
      <input id="f-title" name="title" class="input-xl" value="<?= e($n['title']) ?>" maxlength="255" required<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['title'])): ?><p class="field__error"><?= e($errors['title']) ?></p><?php endif ?></div>
    <div class="field"><label for="f-slug">Адрес (URL)</label>
      <div class="slugbox"><span class="muted">/news/</span><input id="f-slug" name="slug" value="<?= e($n['slug']) ?>" maxlength="100" data-slug-from="title" placeholder="создастся автоматически"></div>
      <?php if ($live): ?><p class="hint">Если изменить адрес опубликованной новости, 301-редирект со старого адреса создастся автоматически.</p><?php endif ?></div>
    <div class="field"><label for="f-excerpt">Лид (краткое описание)</label>
      <textarea id="f-excerpt" name="excerpt" rows="3" maxlength="600" data-count><?= e($n['excerpt'] ?? '') ?></textarea><p class="hint">Показывается в списке и в поиске. Если пусто — возьмём начало текста.</p></div>
    <div class="field"><label for="f-body">Текст <span class="req">*</span></label>
      <textarea id="f-body" name="body" data-richtext data-images="1" rows="18"><?= e($n['body']) ?></textarea>
      <?php if (isset($errors['body'])): ?><p class="field__error"><?= e($errors['body']) ?></p><?php endif ?></div>

    <section class="panel"><h2>Фотогалерея</h2>
      <p class="hint">Дополнительные фото под текстом. Порядок меняется стрелками.</p>
      <input type="hidden" name="gallery" id="gallery-ids" value="<?= e(implode(',', array_column($galleryMedia, 'id'))) ?>">
      <ul class="gallery-adm" id="gallery-list">
        <?php foreach ($galleryMedia as $g): ?><li data-id="<?= (int) $g['id'] ?>"><img src="<?= e(media_url($g, 480)) ?>" alt="<?= e($g['alt']) ?>"><span class="gallery-adm__ctl"><button type="button" data-mv="-1" aria-label="Левее"><?= icon('chevron-left') ?></button><button type="button" data-mv="1" aria-label="Правее"><?= icon('chevron-right') ?></button><button type="button" data-rm aria-label="Убрать"><?= icon('close') ?></button></span></li><?php endforeach ?>
      </ul>
      <button type="button" class="btn btn--ghost btn--sm" data-pick="multi" data-target="gallery"><?= icon('plus') ?> Добавить фото</button>
    </section>

    <section class="panel seo" id="seo-panel"><h2><?= icon('activity') ?> SEO</h2>
      <div class="snippet" aria-label="Так материал может выглядеть в поиске">
        <p class="snippet__url" id="sn-url"><?= e(\App\Core\Request::baseUrlHost()) ?> › news › <span id="sn-slug"><?= e($n['slug'] ?: '…') ?></span></p>
        <p class="snippet__title" id="sn-title"><?= e($n['seo_title'] ?: $n['title'] ?: 'Заголовок материала') ?></p>
        <p class="snippet__desc" id="sn-desc"><?= e($n['seo_description'] ?: ($n['excerpt'] ?? '')) ?></p>
      </div>
      <div class="field"><label for="f-seo_title">SEO-заголовок <span class="counter" data-for="f-seo_title" data-min="30" data-max="70"></span></label>
        <input id="f-seo_title" name="seo_title" value="<?= e($n['seo_title'] ?? '') ?>" maxlength="190" placeholder="По умолчанию — заголовок материала"><p class="hint">Оптимально 30–70 символов. Ключевая фраза — ближе к началу.</p></div>
      <div class="field"><label for="f-seo_description">SEO-описание <span class="counter" data-for="f-seo_description" data-min="70" data-max="160"></span></label>
        <textarea id="f-seo_description" name="seo_description" rows="3" maxlength="320" placeholder="По умолчанию — лид или начало текста"><?= e($n['seo_description'] ?? '') ?></textarea><p class="hint">Оптимально 70–160 символов: коротко и с пользой для читателя.</p></div>
      <details class="adv"><summary>Дополнительно</summary>
        <div class="field"><label for="f-canonical_url">Канонический URL</label><input id="f-canonical_url" name="canonical_url" type="url" value="<?= e($n['canonical_url'] ?? '') ?>" placeholder="Только если материал первоисточник на другом сайте"></div>
        <div class="field field--check"><label><input type="checkbox" name="noindex" value="1" <?= !empty($n['noindex']) ? 'checked' : '' ?>> Не индексировать (noindex, исключить из sitemap)</label></div></details>
      <h3>Проверка материала</h3>
      <ul class="checks" id="seo-checks" aria-live="polite"></ul>
    </section>
  </div>

  <aside class="editor-side">
    <section class="panel"><h2>Публикация</h2>
      <div class="field"><label for="f-status">Статус</label>
        <select id="f-status" name="status">
          <option value="draft" <?= $n['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option>
          <option value="review" <?= $n['status'] === 'review' ? 'selected' : '' ?>>На проверке</option>
          <?php if ($canPublish): ?><option value="published" <?= $n['status'] === 'published' ? 'selected' : '' ?>>Опубликовано</option><?php endif ?>
        </select></div>
      <div class="field"><label for="f-published_at">Дата публикации</label><input type="datetime-local" id="f-published_at" name="published_at" value="<?= e($dt($n['published_at'])) ?>"><p class="hint">Будущая дата — запланированная публикация: новость появится сама, sitemap и поисковики обновятся автоматически.</p></div>
      <?php if ($canPublish): ?>
      <div class="field field--check"><label><input type="checkbox" name="is_featured" value="1" <?= !empty($n['is_featured']) ? 'checked' : '' ?>> Главная новость на витрине</label></div>
      <div class="field field--check"><label><input type="checkbox" name="is_pinned" value="1" <?= !empty($n['is_pinned']) ? 'checked' : '' ?>> Закрепить вверху ленты</label></div>
      <?php else: ?><p class="hint">Публикует редактор. Отправьте материал «На проверке».</p><?php endif ?>
      <div class="actions actions--stack">
        <button class="btn btn--primary btn--block" type="submit"><?= $canPublish && !$live ? 'Сохранить' : 'Сохранить' ?></button>
        <?php if ($id): ?><a class="btn btn--ghost btn--block" href="<?= e(admin_url('news/' . $id . '/preview')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Предпросмотр</a>
          <?php if ($live): ?><a class="btn btn--ghost btn--block" href="/news/<?= e($n['slug']) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Открыть на сайте</a><?php endif ?><?php endif ?>
      </div>
      <?php if ($id): ?><p class="small muted">Просмотров: <?= number_format((int) $n['views'], 0, ',', ' ') ?></p><?php endif ?>
    </section>
    <section class="panel"><h2>Рубрика и теги</h2>
      <div class="field"><label for="f-cat">Рубрика</label><select id="f-cat" name="category_id"><option value="">— без рубрики —</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($n['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label for="f-tags">Теги (через запятую)</label>
        <div class="tagbox" data-tags data-suggest="<?= e(json_encode(array_values($allTags), JSON_UNESCAPED_UNICODE)) ?>">
          <input id="f-tags" name="tags" value="<?= e($tags) ?>" autocomplete="off" maxlength="600" role="combobox" aria-expanded="false" aria-controls="tag-suggest" aria-autocomplete="list" placeholder="например: спорт, дороги, культура">
          <ul class="tagbox__list" id="tag-suggest" role="listbox" hidden></ul>
          <?php if ($allTags): ?><p class="small muted tagbox__lbl">Популярные теги — нажмите, чтобы добавить:</p>
          <p class="tagchips"><?php foreach (array_slice($allTags, 0, 12) as $t): ?><button type="button" class="tagchip" data-add-tag="<?= e($t) ?>">#<?= e($t) ?></button><?php endforeach ?></p><?php endif ?>
        </div>
        <p class="hint">Начните вводить — появятся существующие теги (↑ ↓ и Enter, либо щелчок). Новый тег создастся сам. Не больше 10.</p></div>
    </section>
    <section class="panel"><h2>Обложка</h2>
      <?= \App\Core\View::partial('admin/_media-field', ['name' => 'cover_media_id', 'mediaId' => (int) ($n['cover_media_id'] ?? 0)]) ?>
      <p class="hint">Без обложки для соцсетей автоматически создаётся картинка с заголовком.</p></section>
    <section class="panel"><h2>Источник</h2>
      <div class="field"><label for="f-sn">Название источника</label><input id="f-sn" name="source_name" value="<?= e($n['source_name'] ?? '') ?>" maxlength="190"></div>
      <div class="field"><label for="f-su">Ссылка на источник</label><input id="f-su" name="source_url" type="url" value="<?= e($n['source_url'] ?? '') ?>" maxlength="500"></div></section>
    <?php if ($id && \App\Core\Auth::can('news.publish')): ?>
    <button class="btn btn--danger btn--block" type="submit" form="del-form" data-confirm="Удалить новость? Для её адреса включится ответ 410."><?= icon('trash') ?> Удалить</button>
    <?php endif ?>
  </aside>
</form>
<?php if ($id && \App\Core\Auth::can('news.publish')): ?>
<form method="post" id="del-form" action="<?= e(admin_url('news/' . $id . '/delete')) ?>"><?= csrf_field() ?></form>
<?php endif ?>
