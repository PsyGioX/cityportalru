<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $req @var array $in @var array $errors @var ?array $notice @var ?array $done @var ?string $manual @var string $csrf */
use App\Support\Installer;
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Установка сайта</title><link rel="stylesheet" href="/assets/css/admin.css"></head>
<body class="install"><main class="install__box">
<h1>Установка сайта</h1>
<?php if ($done): ?>
  <div class="alert alert--ok"><b>Готово!</b> Сайт «<?= $h($in['site_name']) ?>» установлен.</div>
  <ul class="checklist"><?php foreach ($done as $l): ?><li><?= $h($l) ?></li><?php endforeach ?></ul>
  <p><a class="btn btn--primary" href="/<?= $h($in['admin_path']) ?>/login">Войти в админ-панель</a></p>
  <?php if ($manual): ?><div class="alert alert--warn"><b>Сохраните файл вручную.</b> Создайте <code>config/config.php</code> с этим содержимым (иначе сайт не заработает):</div>
    <textarea readonly rows="16" class="codebox" data-select-on-focus><?= $h($manual) ?></textarea><?php endif ?>
  <div class="alert alert--info"><b>Что сделать дальше (5 минут):</b><ol>
    <li><b>Войдите и включите 2FA</b> в «Профиль» — это главная защита админки.</li>
    <li>«Настройки → Оформление и бренд»: загрузите логотип и фавикон, выберите фирменный цвет.</li>
    <li>«Настройки → Радио, погода, кино»: впишите ключ WeatherAPI.com, если не сделали это при установке, и адрес потока радио.</li>
    <li>«Сайт → Меню сайта»: отключите разделы, которые вам не нужны (например, «Кино»), и добавьте свои страницы.</li>
    <li>Для публикации по расписанию добавьте cron: <code>*/5 * * * * php <?= $h(BASE_PATH) ?>/bin/cron.php</code> (без него работает «псевдо-крон» при заходах на сайт).</li>
    <li>Проверьте «Система»: там видно, закрыты ли служебные файлы от посторонних, и можно сделать резервную копию.</li></ol></div>
<?php else: ?>
  <p class="muted">Мастер создаст таблицы в пустой базе MySQL/MariaDB, настроит сайт под ваш город и создаст администратора. Всё, что вы введёте здесь, потом можно изменить в админ-панели.</p>
  <h2>1. Проверка сервера <small>только подсказки — установке они не мешают</small></h2>
  <ul class="checklist"><?php foreach ($req as [$name, $ok, $hint]): ?>
    <li class="<?= $ok ? 'ok' : 'warn' ?>"><?= $ok ? '✓' : '!' ?> <?= $h($name) ?><?= $ok ? '' : ' — <small>' . $h($hint) . '</small>' ?></li><?php endforeach ?></ul>
  <?php foreach ($errors as $e): ?><div class="alert alert--err" role="alert"><?= $h($e) ?></div><?php endforeach ?>
  <?php if ($notice): ?><div class="alert alert--<?= $notice[0] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= $h($notice[1]) ?></div><?php endif ?>
  <form method="post" autocomplete="off" class="form" id="install-form">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
    <h2>2. База данных</h2>
    <p class="muted">Создайте пустую БД (кодировка utf8mb4) в панели хостинга или phpMyAdmin и пользователя к ней. Рекомендуются MySQL 8.4+ или MariaDB 10.11+.</p>
    <div class="grid2">
      <label>Хост<input name="db_host" value="<?= $h($in['db_host']) ?>" required></label>
      <label>Порт<input name="db_port" value="<?= $h($in['db_port']) ?>" inputmode="numeric"></label>
      <label>Имя базы<input name="db_name" value="<?= $h($in['db_name']) ?>" required></label>
      <label>Пользователь<input name="db_user" value="<?= $h($in['db_user']) ?>" required></label>
      <label class="full">Пароль<input name="db_pass" type="password" value="<?= $h($in['db_pass']) ?>" autocomplete="new-password"></label>
    </div>
    <p><button class="btn btn--ghost btn--sm" type="submit" name="do" value="test" formnovalidate>Проверить подключение</button> <small class="muted">ничего не устанавливает — только проверяет доступ и права</small></p>

    <h2>3. Сайт и город</h2>
    <div class="grid2">
      <label class="full">Адрес сайта (канонический, как в Яндекс.Вебмастере)<input name="site_url" value="<?= $h($in['site_url']) ?>" required><small>Например, https://example.ru. На http://localhost сайт будет закрыт от индексации.</small></label>
      <label>Тип проекта<select name="kind"><?php foreach (SITE_KINDS as $kc => $kv): ?><option value="<?= $h($kc) ?>" <?= $in['kind'] === $kc ? 'selected' : '' ?>><?= $h($kv[0]) ?></option><?php endforeach ?></select><small>Газете и СМИ не нужны кино и радио — они будут отключены (включаются в «Меню сайта»)</small></label>
      <label>Название сайта<input name="site_name" value="<?= $h($in['site_name']) ?>" required></label>
      <label>Адрес админки (/…)<span class="inline-field"><input name="admin_path" id="admin_path" value="<?= $h($in['admin_path']) ?>" pattern="[a-z0-9\-]{3,30}" required><button type="button" class="btn btn--ghost btn--sm" data-gen-path>Случайный</button></span><small>Не «admin» — меньше случайных обращений ботов.</small></label>
      <label>Город<input name="city_name" value="<?= $h($in['city_name']) ?>" required><small>Именительный падеж: Кореновск</small></label>
      <label>Регион<input name="region_name" value="<?= $h($in['region_name']) ?>"></label>
      <label>«Где?» (предложный падеж)<input name="city_name_in" value="<?= $h($in['city_name_in']) ?>" placeholder="в Кореновске"><small>Пусто — подставится автоматически; для редких названий впишите вручную</small></label>
      <label>«Чего?» (родительный падеж)<input name="city_name_of" value="<?= $h($in['city_name_of']) ?>" placeholder="Кореновска"></label>
      <label>Широта<input name="city_lat" value="<?= $h($in['city_lat']) ?>" inputmode="decimal"><small>Для погоды. Координаты города — на Яндекс/Google Картах</small></label>
      <label>Долгота<input name="city_lon" value="<?= $h($in['city_lon']) ?>" inputmode="decimal"></label>
      <label>Часовой пояс<input name="timezone" value="<?= $h($in['timezone']) ?>" list="tzlist"><datalist id="tzlist"><?php foreach (['Europe/Kaliningrad', 'Europe/Moscow', 'Europe/Samara', 'Asia/Yekaterinburg', 'Asia/Omsk', 'Asia/Krasnoyarsk', 'Asia/Irkutsk', 'Asia/Yakutsk', 'Asia/Vladivostok', 'Asia/Magadan', 'Asia/Kamchatka', 'Europe/Kyiv', 'Europe/Minsk', 'Asia/Almaty'] as $z): ?><option value="<?= $h($z) ?>"><?php endforeach ?></datalist></label>
      <label>Ключ WeatherAPI.com <small>(необязательно)</small><input name="weather_api_key" value="<?= $h($in['weather_api_key']) ?>" autocomplete="off"><small>Бесплатно: weatherapi.com → My Account → API Key. Без ключа блок погоды скрыт.</small></label>
    </div>

    <h2>4. Какие данные загрузить</h2>
    <div class="profiles" role="radiogroup" aria-label="Набор данных">
      <?php foreach (Installer::PROFILES as $code => [$title, $desc]): ?>
        <label class="profile"><input type="radio" name="profile" value="<?= $h($code) ?>" <?= $in['profile'] === $code ? 'checked' : '' ?>><span><b><?= $h($title) ?></b><small><?= $h($desc) ?></small></span></label>
      <?php endforeach ?>
    </div>
    <p class="muted small">Для другого города выберите «Чистый сайт» и замените название, падежи и координаты выше — в «Кореновск» ничего не подмешается.</p>

    <h2>5. Администратор</h2>
    <div class="grid2">
      <label>Логин<input name="admin_user" value="<?= $h($in['admin_user']) ?>" required></label>
      <label>E-mail<input name="admin_email" type="email" value="<?= $h($in['admin_email']) ?>" required></label>
      <label>Пароль (от 10 символов)<input name="admin_pass" id="admin_pass" type="password" minlength="10" required autocomplete="new-password"></label>
      <label>Повторите пароль<input name="admin_pass2" id="admin_pass2" type="password" minlength="10" required autocomplete="new-password"></label>
    </div>
    <p><button type="button" class="btn btn--ghost btn--sm" data-gen-pass>Придумать надёжный пароль</button> <button type="button" class="btn btn--ghost btn--sm" data-toggle-pass>Показать пароль</button> <span class="small muted" id="pw-note" aria-live="polite"></span></p>

    <h2>6. Издание <small>необязательно, можно позже</small></h2>
    <div class="grid2">
      <label>Название издателя (для © в подвале)<input name="org_name" value="<?= $h($in['org_name']) ?>" placeholder="Редакция «Название»"></label>
      <label>E-mail редакции (публичный)<input name="contact_email" type="email" value="<?= $h($in['contact_email']) ?>"><small>Форм на сайте нет: читатели пишут вам напрямую</small></label>
    </div>
    <button class="btn btn--primary btn--lg" type="submit" name="do" value="install">Установить</button>
  </form>
  <script src="/assets/js/install.js"></script>
<?php endif ?>
</main></body></html>
