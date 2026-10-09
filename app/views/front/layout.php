<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
use App\Support\Seo;
use App\Support\Site;
use App\Support\Weather;
use App\Support\Brand;

/** @var array $seo */
$site = Seo::siteName();
$chrome = Site::chrome();
$menu = Site::menu();                       // пункты меню из админки («Сайт → Меню сайта»)
$wxOn = Site::enabled('pogoda') && Weather::configured();
$radioOn = Site::enabled('radio');
$wx = $wxOn ? Weather::get(false) : null;
$og = $seo['og'];
$path = \App\Core\Request::path();
$cc = \App\Support\Consent::banner();   // null, пока баннер выключен в настройках
?><!doctype html>
<html lang="ru" prefix="og: https://ogp.me/ns#">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo['title']) ?></title>
<meta name="description" content="<?= e($seo['description']) ?>">
<meta name="robots" content="<?= e($seo['robots']) ?>">
<link rel="canonical" href="<?= e($seo['canonical']) ?>">
<?php if (!empty($seo['prev'])): ?><link rel="prev" href="<?= e($seo['prev']) ?>"><?php endif ?>
<?php if (!empty($seo['next'])): ?><link rel="next" href="<?= e($seo['next']) ?>"><?php endif ?>
<meta property="og:type" content="<?= e($og['type']) ?>">
<meta property="og:site_name" content="<?= e($og['site_name']) ?>">
<meta property="og:locale" content="<?= e($og['locale']) ?>">
<meta property="og:title" content="<?= e($og['title']) ?>">
<meta property="og:description" content="<?= e($og['description']) ?>">
<meta property="og:url" content="<?= e($og['url']) ?>">
<meta property="og:image" content="<?= e($og['image']) ?>">
<meta property="og:image:width" content="<?= (int) $og['image_w'] ?>">
<meta property="og:image:height" content="<?= (int) $og['image_h'] ?>">
<?php if ($og['image_alt']): ?><meta property="og:image:alt" content="<?= e($og['image_alt']) ?>"><?php endif ?>
<?php if ($og['type'] === 'article'): ?>
<meta property="article:published_time" content="<?= e($og['published']) ?>">
<meta property="article:modified_time" content="<?= e($og['modified']) ?>">
<?php if ($og['section']): ?><meta property="article:section" content="<?= e($og['section']) ?>"><?php endif ?>
<?php foreach ($og['tags'] as $t): ?><meta property="article:tag" content="<?= e($t) ?>"><?php endforeach ?>
<?php endif ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($og['title']) ?>">
<meta name="twitter:description" content="<?= e($og['description']) ?>">
<meta name="twitter:image" content="<?= e($og['image']) ?>">
<?php if ($v = setting('yandex_verification')): ?><meta name="yandex-verification" content="<?= e($v) ?>"><?php endif ?>
<?php if ($v = setting('google_meta')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif ?>
<?php if ($v = setting('bing_verification')): ?><meta name="msvalidate.01" content="<?= e($v) ?>"><?php endif ?>
<meta name="theme-color" content="<?= e(Brand::accent()) ?>">
<meta name="color-scheme" content="light dark">
<meta name="format-detection" content="telephone=no">
<?= Brand::headLinks() ?><link rel="manifest" href="/manifest.webmanifest">
<link rel="alternate" type="application/rss+xml" title="<?= e($site) ?> — новости" href="/rss.xml">
<link rel="search" type="application/opensearchdescription+xml" title="<?= e($site) ?>" href="/opensearch.xml">
<link rel="preload" href="/assets/fonts/montserrat-cyrillic-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<script src="/assets/js/init.js"></script>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<?php if (Brand::hasCustomAccent()): ?><link rel="stylesheet" href="/brand.css?v=<?= (int) \App\Core\Settings::contentVersion() ?>"><?php endif ?>
<?php foreach ($seo['jsonld'] as $ld) { echo json_ld($ld), "\n"; } ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">К содержимому</a>

<header class="site-header">
  <div class="topbar">
    <div class="container topbar__in">
      <span class="topbar__date"><?= e(date_ru(time(), 'l, j F')) ?></span>
      <?php if ($wxOn): ?>
      <a class="wx-chip" href="/pogoda" id="wx-chip" data-city="<?= e(city()) ?>"<?= $wx ? '' : ' data-load="1"' ?>>
        <?php if ($wx): [$wd, $wi] = Weather::describe($wx['code'], (bool) ($wx['is_day'] ?? 1)); ?>
          <?= icon($wi) ?><span><?= e(city()) ?> <b><?= e(Weather::fmtTemp($wx['temp'])) ?></b></span>
        <?php else: ?>
          <?= icon('cloud-sun') ?><span>Погода <?= e(city_in()) ?></span>
        <?php endif ?>
      </a>
      <?php else: ?><span class="wx-chip" aria-hidden="true"></span><?php endif ?>
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Тема оформления: как в системе" title="Тема оформления">
        <?= icon('contrast', 'th-auto') ?><?= icon('sun', 'th-light') ?><?= icon('moon', 'th-dark') ?>
      </button>
      <?php if ($radioOn): ?>
      <button type="button" class="radio-pill" id="radio-btn" data-src="<?= e(setting('radio_url')) ?>" data-name="<?= e(setting('radio_name', city() . ' FM')) ?>" data-icon="<?= e(Brand::iconUrl(512)) ?>" aria-pressed="false">
        <span class="eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="radio-pill__label"><?= e(setting('radio_name', city() . ' FM')) ?></span>
        <?= icon('play', 'radio-pill__icon') ?>
      </button>
      <?php endif ?>
    </div>
  </div>
  <div class="mast container">
    <a class="brand" href="/" aria-label="<?= e($site) ?> — на главную">
      <?= Brand::mark() ?>
      <?php if (Brand::showName()): ?><span class="brand__name"><?= e($site) ?></span><?php endif ?>
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Основное меню">
      <ul>
        <?php foreach ($menu['header'] as $it): ?>
          <li><a href="<?= e($it['href']) ?>"<?= active($it['href'], $path) ?><?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['label']) ?></a></li>
        <?php endforeach ?>
      </ul>
      <form class="search" action="/search" method="get" role="search">
        <label class="sr-only" for="q-head">Поиск по новостям</label>
        <input id="q-head" type="search" name="q" placeholder="Поиск по новостям" minlength="2" maxlength="100" autocomplete="off" value="<?= e(\App\Core\Request::query('q')) ?>">
        <button type="submit" aria-label="Найти"><?= icon('search') ?></button>
      </form>
    </nav>
    <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Меню"><?= icon('menu', 'i-open') ?><?= icon('close', 'i-close') ?></button>
  </div>
  <?php if ($chrome['categories']): ?>
  <nav class="rubrics" aria-label="Рубрики"><div class="container rubrics__in">
    <?php foreach ($chrome['categories'] as $c): ?>
      <a href="/category/<?= e($c['slug']) ?>"<?= active('/category/' . $c['slug'], $path) ?>><?= e($c['name']) ?></a>
    <?php endforeach ?>
  </div></nav>
  <?php endif ?>
</header>

<main id="main" tabindex="-1">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <section class="footer-about">
      <a class="brand brand--light" href="/" aria-label="<?= e($site) ?>">
        <?= Brand::mark(true) ?>
        <?php if (Brand::showName()): ?><span class="brand__name"><?= e($site) ?></span><?php endif ?>
      </a>
      <p><?= e(setting('footer_about')) ?></p>
      <?php foreach ($chrome['social_blocks'] as $sb): ?>
      <h2 class="footer-h"><?= e($sb['title']) ?></h2>
      <ul class="social">
        <?php foreach ($sb['links'] as $s): ?>
          <li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?php if ($s['icon']): ?><img src="<?= e($s['icon']) ?>" alt="" width="24" height="24" loading="lazy"><?php endif ?><?= e($s['title']) ?></a></li>
        <?php endforeach ?>
      </ul>
      <?php endforeach ?>
    </section>
    <section>
      <h2 class="footer-h">Разделы</h2>
      <ul class="flist">
        <?php foreach ($menu['footer'] as $it): ?><li><a href="<?= e($it['href']) ?>"<?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['label']) ?></a></li><?php endforeach ?>
        <li><a href="/rss.xml">RSS-лента</a></li>
      </ul>
    </section>
    <?php if ($chrome['footer_blocks']): ?>
    <section>
      <?php foreach ($chrome['footer_blocks'] as $fb): ?>
      <h2 class="footer-h"><?= e($fb['title']) ?></h2>
      <ul class="flist">
        <?php foreach ($fb['links'] as $l): ?>
          <li><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['title']) ?></a></li>
        <?php endforeach ?>
      </ul>
      <?php endforeach ?>
    </section>
    <?php endif ?>
  </div>
  <div class="footer-legal">
    <div class="container legal-in">
      <p>© <?= date('Y') ?> <?= e(setting('org_name', $site)) ?>.<?php if ($note = trim((string) setting('footer_note', ''))): ?> <?= e($note) ?><?php endif ?>
        <?php if ($smi = setting('smi_reg')): ?><?= e($smi) ?>.<?php endif ?></p>
      <ul class="legal-links">
        <?php foreach ($chrome['pages'] as $p): if (Site::isDisabled('/' . $p['slug'])) { continue; } ?><li><a href="/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></li><?php endforeach ?>
        <?php if ($cc): ?><li><button type="button" class="linklike" data-cc="open">Настройки cookie</button></li><?php endif ?>
      </ul>
      <?php if ($age = setting('age_mark')): ?><span class="age-mark" title="Знак информационной продукции"><?= e($age) ?></span><?php endif ?>
    </div>
  </div>
</footer>

<?php if ($cc): ?>
<section id="cookie-banner" class="cc" role="dialog" aria-modal="false" aria-labelledby="cc-title" aria-describedby="cc-text" hidden
  data-ver="<?= (int) $cc['ver'] ?>" data-ttl="<?= (int) $cc['ttl'] ?>" data-ym="<?= e($cc['ym']) ?>" data-endpoint="/api/consent">
  <div class="cc__box">
    <h2 id="cc-title" class="cc__title" tabindex="-1"><?= e($cc['title']) ?></h2>
    <p id="cc-text" class="cc__text"><?= e($cc['text']) ?></p>
    <p class="cc__links"><a href="<?= e($cc['cookie_url']) ?>">Политика cookie</a> · <a href="<?= e($cc['privacy_url']) ?>">Обработка персональных данных</a></p>
    <?php if ($cc['ym'] !== ''): ?>
    <div class="cc__prefs" id="cc-prefs" hidden>
      <label class="cc__row"><input type="checkbox" checked disabled><span><b>Необходимые</b> — тема оформления и ваш выбор по cookie. Отключить нельзя.</span></label>
      <label class="cc__row"><input type="checkbox" id="cc-analytics"><span><b>Аналитика</b> — «Яндекс Метрика»: статистика посещений. Включается только с вашего согласия.</span></label>
    </div>
    <div class="cc__actions">
      <button type="button" class="btn btn--primary" data-cc="accept">Принять все</button>
      <button type="button" class="btn btn--primary" data-cc="reject">Только необходимые</button>
      <button type="button" class="btn btn--ghost" data-cc="more" aria-expanded="false" aria-controls="cc-prefs">Настроить</button>
      <button type="button" class="btn btn--ghost" data-cc="save" hidden>Сохранить выбор</button>
    </div>
    <?php else: ?>
    <div class="cc__actions"><button type="button" class="btn btn--primary" data-cc="ack">Понятно</button></div>
    <?php endif ?>
  </div>
</section>
<script src="<?= asset('js/consent.js') ?>" defer></script>
<?php endif ?>
<script src="<?= asset('js/theme.js') ?>" defer></script>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
