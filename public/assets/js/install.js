/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/* Мастер установки: генератор пароля и адреса админки, показ пароля. Без внешних библиотек. */
(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  function rnd(n, alphabet) {
    var out = '', buf = new Uint32Array(n); window.crypto.getRandomValues(buf);
    for (var i = 0; i < n; i++) out += alphabet[buf[i] % alphabet.length];
    return out;
  }
  var p1 = $('#admin_pass'), p2 = $('#admin_pass2'), note = $('#pw-note');
  var gen = $('[data-gen-pass]'), tog = $('[data-toggle-pass]'), path = $('[data-gen-path]');
  if (gen) gen.addEventListener('click', function () {
    var pw = rnd(18, 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_');
    p1.value = p2.value = pw; p1.type = p2.type = 'text';
    if (tog) tog.textContent = 'Скрыть пароль';
    if (note) note.textContent = 'Запишите пароль в менеджер паролей — после установки он нигде не показывается.';
  });
  if (tog) tog.addEventListener('click', function () {
    var show = p1.type === 'password'; p1.type = p2.type = show ? 'text' : 'password';
    tog.textContent = show ? 'Скрыть пароль' : 'Показать пароль';
  });
  if (path) path.addEventListener('click', function () { $('#admin_path').value = 'cp-' + rnd(6, 'abcdefghjkmnpqrstuvwxyz23456789'); });
  var box = document.querySelector('[data-select-on-focus]');
  if (box) box.addEventListener('focus', function () { box.select(); });
})();
