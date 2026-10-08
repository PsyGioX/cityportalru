/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
(function () {
  var d = document.documentElement;
  d.classList.add('js');
  try {
    var t = localStorage.getItem('tk-theme');
    if (t === 'dark' || t === 'light') { d.setAttribute('data-theme', t); d.setAttribute('data-theme-pref', t); }
    else d.setAttribute('data-theme-pref', 'auto');
  } catch (e) { d.setAttribute('data-theme-pref', 'auto'); }
})();
