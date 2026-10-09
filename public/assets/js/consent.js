/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/* Cookie-баннер. Выбор хранится в localStorage (не в cookie). Аналитика загружается только после согласия;
   на сервер уходит запись «идентификатор, выбор, версия, время» — без IP и браузера. */
(function () {
  'use strict';
  var box = document.getElementById('cookie-banner'); if (!box) return;
  var KEY = 'tk-consent', VER = parseInt(box.getAttribute('data-ver'), 10) || 1, TTL = parseInt(box.getAttribute('data-ttl'), 10) || 12;
  var YM = box.getAttribute('data-ym') || '', EP = box.getAttribute('data-endpoint');
  var prefs = document.getElementById('cc-prefs'), chk = document.getElementById('cc-analytics');
  var more = box.querySelector('[data-cc="more"]'), save = box.querySelector('[data-cc="save"]');
  var opener = null, ymLoaded = false, ymOn = false, mustReload = false, lastUrl = location.href;

  function read() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
  function write(c) { try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) {} }
  function valid(c) { return c && c.v === VER && typeof c.t === 'number' && Date.now() - c.t < TTL * 30.44 * 864e5; }
  function newId() {
    var a = new Uint8Array(16), s = '', i;
    (window.crypto || window.msCrypto).getRandomValues(a);
    for (i = 0; i < a.length; i++) s += ('0' + a[i].toString(16)).slice(-2);
    return s;
  }

  function show(withPrefs) {
    opener = document.activeElement;
    var c = read(); if (chk) chk.checked = !!(c && c.a);
    box.hidden = false; setPrefs(!!withPrefs);
    if (withPrefs) box.querySelector('#cc-title').focus({ preventScroll: true });
  }
  function hide() { box.hidden = true; if (opener && opener.focus && document.contains(opener)) opener.focus({ preventScroll: true }); opener = null; }
  function setPrefs(open) {
    if (!prefs) return;
    prefs.hidden = !open; more.setAttribute('aria-expanded', open ? 'true' : 'false'); save.hidden = !open;
  }

  function loadYm() {
    if (!YM) return;
    ymOn = true; window['disableYaCounter' + YM] = false;
    if (ymLoaded) return;
    ymLoaded = true;
    (function (m, e, t, r, i) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); }; m[i].l = 1 * new Date();
      var s = e.createElement(t); s.async = 1; s.src = r; e.head.appendChild(s);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
    window.ym(+YM, 'init', { defer: true, clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: false });
    window.ym(+YM, 'hit', location.href, { title: document.title });
  }
  function stopYm() {
    if (!YM) return;
    ymOn = false; window['disableYaCounter' + YM] = true;
    var host = location.hostname, parts = host.split('.'), domains = ['', host, '.' + host, '.' + parts.slice(-2).join('.')];
    document.cookie.split(';').forEach(function (p) {
      var n = p.split('=')[0].trim();
      if (n.indexOf('_ym') !== 0) return;
      domains.forEach(function (d) { document.cookie = n + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (d ? '; domain=' + d : ''); });
    });
    try { Object.keys(localStorage).forEach(function (k) { if (k.indexOf('_ym') === 0) localStorage.removeItem(k); }); } catch (e) {}
    if (ymLoaded) mustReload = true;   // уже загруженный скрипт выгрузить нельзя: при следующем переходе страница загрузится заново
  }

  function send(c, action) {
    if (!window.fetch) return;
    fetch(EP, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: c.id, a: action, an: c.a, v: VER }), keepalive: true, credentials: 'omit' }).catch(function () {});
  }
  function choose(analytics, action) {
    var old = read(), c = { v: VER, id: (old && old.id) || newId(), t: Date.now(), a: analytics ? 1 : 0 };
    if (action === 'custom' && !analytics && old && old.a) action = 'withdraw';
    write(c); send(c, action); analytics ? loadYm() : stopYm(); hide();
  }

  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cc]'); if (!b) return;
    var a = b.getAttribute('data-cc');
    if (a === 'accept') choose(true, 'accept_all');
    else if (a === 'reject') choose(false, 'reject');
    else if (a === 'ack') choose(false, 'ack');
    else if (a === 'save') choose(chk && chk.checked, 'custom');
    else if (a === 'more') setPrefs(prefs.hidden);
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest('#cookie-banner')) return;
    if (e.target.closest('[data-cc="open"]')) show(true);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden && valid(read())) hide(); });
  document.addEventListener('tk:page', function (e) {
    if (mustReload) { location.reload(); return; }
    var u = (e.detail && e.detail.url) || location.href;
    if (ymOn && window.ym) window.ym(+YM, 'hit', u, { title: document.title, referrer: lastUrl });
    lastUrl = u;
  });

  var c = read();
  if (valid(c)) { if (c.a) loadYm(); } else show(false);
})();
