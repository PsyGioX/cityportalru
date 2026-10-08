/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/* Переключатель темы: «как в системе» → светлая → тёмная. Выбор хранится только в localStorage браузера читателя
   (не cookie, на сервер не отправляется). */
(function () {
  'use strict';
  var root = document.documentElement, KEY = 'tk-theme', order = ['auto', 'light', 'dark'];
  var names = { auto: 'как в системе', light: 'светлая', dark: 'тёмная' };
  function get() { try { var v = localStorage.getItem(KEY); return v === 'light' || v === 'dark' ? v : 'auto'; } catch (e) { return 'auto'; } }
  function apply(v) {
    if (v === 'auto') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', v);
    root.setAttribute('data-theme-pref', v);
    document.querySelectorAll('.theme-toggle').forEach(function (b) {
      var next = order[(order.indexOf(v) + 1) % 3];
      b.setAttribute('aria-label', 'Тема оформления: ' + names[v] + '. Нажмите, чтобы выбрать: ' + names[next]);
      b.setAttribute('title', 'Тема: ' + names[v]);
    });
  }
  function set(v) { try { if (v === 'auto') localStorage.removeItem(KEY); else localStorage.setItem(KEY, v); } catch (e) {} apply(v); }
  apply(get());
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.theme-toggle')) return;
    set(order[(order.indexOf(get()) + 1) % 3]);
  });
  window.addEventListener('storage', function (e) { if (e.key === KEY) apply(get()); }); // синхронизация между вкладками
})();
