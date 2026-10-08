/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/* Публичная часть. Без зависимостей, без отправки данных куда-либо.
   Страницы подгружаются «плавно» (как в приложении): шапка и аудиоплеер не пересоздаются,
   поэтому радио продолжает играть при переходах по сайту. */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ======================================================= Радио (живёт, пока открыта вкладка) */
  var radio = { audio: null, wanted: false, playing: false, text: '', retry: 0, timer: null };
  var radioBtn = $('#radio-btn');
  var radioName = radioBtn ? radioBtn.getAttribute('data-name') : 'Радио';
  var radioIcon = (radioBtn && radioBtn.getAttribute('data-icon')) || '/assets/img/icon-512.png';
  function radioUrl() { return radioBtn ? radioBtn.getAttribute('data-src') : ''; }
  function remember(on) { try { on ? sessionStorage.setItem('tk-radio', '1') : sessionStorage.removeItem('tk-radio'); } catch (e) {} }

  function syncRadio() { // обновляет все кнопки и подписи — вызывается и после смены страницы
    $$('#radio-btn, #radio-big').forEach(function (b) { b.setAttribute('aria-pressed', radio.playing ? 'true' : 'false'); });
    var p = $('.player'); if (p) p.classList.toggle('is-playing', radio.playing);
    var st = $('#radio-state'); if (st && radio.text) st.textContent = radio.text;
    var lab = $('.radio-pill__label'); if (lab) lab.textContent = radio.playing ? 'В эфире' : radioName;
  }
  function setRadio(playing, text) { radio.playing = playing; if (text) radio.text = text; syncRadio(); }

  function mediaSession() {
    if (!('mediaSession' in navigator)) return;
    try {
      navigator.mediaSession.metadata = new MediaMetadata({ title: radioName, artist: 'Прямой эфир', artwork: [{ src: radioIcon, sizes: '512x512', type: 'image/png' }] });
      navigator.mediaSession.setActionHandler('play', function () { startRadio(); });
      navigator.mediaSession.setActionHandler('pause', function () { stopRadio(); });
      navigator.mediaSession.setActionHandler('stop', function () { stopRadio(); });
    } catch (e) {}
  }
  function ensureAudio() {
    if (radio.audio) return radio.audio;
    var a = new Audio(); a.preload = 'none'; a.setAttribute('playsinline', '');
    a.addEventListener('playing', function () { radio.retry = 0; setRadio(true, 'В эфире'); });
    a.addEventListener('waiting', function () { if (radio.wanted) setRadio(true, 'Буферизация…'); });
    // живой поток может оборваться — переподключаемся, пока слушатель не нажал «стоп»
    function lost() {
      if (!radio.wanted) return;
      if (radio.retry >= 5) { radio.wanted = false; remember(false); setRadio(false, 'Поток недоступен. Попробуйте позже.'); return; }
      radio.retry++; setRadio(true, 'Переподключение…');
      clearTimeout(radio.timer);
      radio.timer = setTimeout(function () { if (radio.wanted) { a.src = radioUrl(); a.play().catch(function () {}); } }, 1500 * radio.retry);
    }
    a.addEventListener('error', lost); a.addEventListener('ended', lost);
    a.addEventListener('stalled', function () { setTimeout(function () { if (radio.wanted && a.readyState < 3 && a.networkState !== 2) lost(); }, 6000); });
    return (radio.audio = a);
  }
  function startRadio(silent) {
    var url = radioUrl(); if (!url) return;
    var a = ensureAudio(); radio.wanted = true; radio.retry = 0;
    a.src = url; setRadio(true, 'Подключаемся…');
    var pr = a.play();
    if (pr && pr.catch) pr.catch(function () { radio.wanted = false; remember(false); setRadio(false, silent ? '' : 'Не удалось запустить поток. Нажмите ещё раз.'); });
    remember(true); mediaSession();
  }
  function stopRadio() {
    radio.wanted = false; clearTimeout(radio.timer); remember(false);
    if (radio.audio) { radio.audio.pause(); radio.audio.removeAttribute('src'); radio.audio.load(); }
    setRadio(false, 'Остановлено');
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#radio-btn, #radio-big')) return;
    radio.wanted ? stopRadio() : startRadio();
  });
  // Если страницу именно перезагрузили (а не перешли плавно) — пробуем продолжить эфир; браузер может запретить без нажатия.
  try { if (sessionStorage.getItem('tk-radio') === '1' && radioUrl()) startRadio(true); } catch (e) {}

  /* ======================================================= Меню */
  var toggle = $('.menu-toggle'), nav = $('#main-nav');
  function closeMenu() { if (nav && nav.classList.contains('is-open')) { nav.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); } }
  if (toggle && nav) {
    toggle.addEventListener('click', function () { var open = nav.classList.toggle('is-open'); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && nav.classList.contains('is-open')) { closeMenu(); toggle.focus(); } });
  }

  /* ======================================================= Погода в шапке (кэш на сервере устарел — обновляем без блокировки страницы) */
  var chip = $('#wx-chip[data-load]');
  if (chip && window.fetch) {
    fetch('/api/weather', { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d || !d.ok) return;
      var span = chip.querySelector('span'), city = chip.getAttribute('data-city') || '';
      span.textContent = ''; var b = document.createElement('b'); b.textContent = d.temp;
      span.append(document.createTextNode((city ? city + ' ' : d.desc + ' ')), b);
      var u = chip.querySelector('use'); if (u) u.setAttribute('href', '/assets/img/sprite.svg#' + d.icon);
    }).catch(function () {});
  }

  /* ======================================================= Делегированные обработчики (работают и после плавных переходов) */
  document.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('[data-open-dialog]'))) { var d = document.getElementById(t.getAttribute('data-open-dialog')); if (d && d.showModal) d.showModal(); return; }
    if ((t = e.target.closest('[data-close-dialog]'))) { t.closest('dialog').close(); return; }
    if (e.target.matches && e.target.matches('dialog.modal')) { e.target.close(); return; }
    if ((t = e.target.closest('[data-copy]'))) {
      var txt = t.getAttribute('data-copy'), label = t.querySelector('span'), old = label && label.textContent;
      (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () {
        if (label) { label.textContent = 'Скопировано'; setTimeout(function () { label.textContent = old; }, 1800); }
      }).catch(function () { window.prompt('Скопируйте ссылку:', txt); });
      return;
    }
    if ((t = e.target.closest('[data-share]')) && navigator.share) { navigator.share({ title: t.getAttribute('data-title'), url: t.getAttribute('data-share') }).catch(function () {}); }
  });
  // при копировании большого фрагмента статьи добавляем ссылку на источник
  document.addEventListener('copy', function (e) {
    var prose = $('[data-attrib]'), sel = window.getSelection();
    if (!prose || !sel || sel.isCollapsed || !e.clipboardData) return;
    var text = sel.toString();
    if (text.length < 120 || !prose.contains(sel.anchorNode)) return;
    e.clipboardData.setData('text/plain', text + '\n\nЧитайте подробнее: ' + location.href.split('#')[0]);
    e.preventDefault();
  });

  /* ======================================================= Содержимое страницы (вызывается после каждой подгрузки) */
  function initPage(scope) {
    $$('[data-gallery]', scope).forEach(function (g) {
      var track = $('.gallery__track', g), count = $('.gallery__count', g), slides = $$('.gallery__slide', g);
      if (!track || !slides.length) return;
      function idx() { return Math.round(track.scrollLeft / (slides[0].offsetWidth + 12)); }
      function go(i) { i = Math.max(0, Math.min(slides.length - 1, i)); track.scrollTo({ left: i * (slides[0].offsetWidth + 12), behavior: 'smooth' }); }
      track.addEventListener('scroll', function () { if (count) count.textContent = (idx() + 1) + ' / ' + slides.length; }, { passive: true });
      $$('[data-dir]', g).forEach(function (b) { b.addEventListener('click', function () { go(idx() + parseInt(b.getAttribute('data-dir'), 10)); }); });
      track.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') go(idx() + 1); if (e.key === 'ArrowLeft') go(idx() - 1); });
      var lb = $('#lightbox', scope);
      if (lb && lb.showModal) {
        $$('.gallery__open', g).forEach(function (b) { b.addEventListener('click', function () { $('img', lb).src = b.getAttribute('data-full'); lb.showModal(); }); });
        lb.addEventListener('click', function (e) { if (e.target === lb || e.target.closest('.lightbox__close')) lb.close(); });
      }
    });
    if (navigator.share) $$('.share__native', scope).forEach(function (li) { li.hidden = false; });
    syncRadio();
  }
  initPage(document);

  /* ======================================================= «Показать ещё»: подгрузка следующей страницы списка (ссылки на страницы остаются для поисковиков и без JS) */
  var moreBusy = false;
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-more]') : null;
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || moreBusy || !window.fetch || !window.DOMParser) return;
    var list = $(a.getAttribute('data-more')); if (!list) return;
    e.preventDefault(); moreBusy = true; a.setAttribute('aria-busy', 'true'); a.classList.add('is-busy');
    fetch(a.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('http ' + r.status);
      return r.text();
    }).then(function (t) {
      var doc = new DOMParser().parseFromString(t, 'text/html'), src = $(a.getAttribute('data-more'), doc);
      if (!src) throw new Error('no list');
      var first = null;
      Array.prototype.slice.call(src.children).forEach(function (n) { var c = document.importNode(n, true); if (!first) first = c; list.appendChild(c); });
      // обновить кнопку и номера страниц по загруженной странице
      var nb = $('a[data-more]', doc), box = a.closest('.load-more');
      if (nb) { a.href = nb.getAttribute('href'); a.removeAttribute('aria-busy'); a.classList.remove('is-busy'); } else if (box) box.parentNode.removeChild(box);
      var np = $('.pagination', doc), op = $('.pagination', $('#main') || document);
      if (np && op) op.parentNode.replaceChild(document.importNode(np, true), op);
      var link = first && $('a', first); if (link) link.focus({ preventScroll: true });
    }).catch(function () {
      location.href = a.href; // запасной вариант — обычный переход на следующую страницу
    }).then(function () { moreBusy = false; a.removeAttribute('aria-busy'); a.classList.remove('is-busy'); });
  });

  /* ======================================================= Плавная навигация: шапка и радио не перезагружаются */
  if (!window.fetch || !window.history || !history.pushState || !window.DOMParser || !window.AbortController) return;
  var main = $('#main'), ctrl = null;
  var META = ['meta[name="description"]', 'link[rel="canonical"]', 'meta[name="robots"]', 'meta[property="og:title"]', 'meta[property="og:description"]', 'meta[property="og:url"]',
    'meta[property="og:image"]', 'meta[property="og:type"]', 'meta[name="twitter:title"]', 'meta[name="twitter:description"]', 'meta[name="twitter:image"]'];
  history.scrollRestoration = 'manual';
  history.replaceState({ y: 0 }, '', location.href);

  function eligible(a, e) {
    if (e && (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) return false;
    if (!a.href || (a.target && a.target !== '_self') || a.hasAttribute('download') || a.getAttribute('rel') === 'external') return false;
    var u; try { u = new URL(a.href, location.href); } catch (x) { return false; }
    if (u.origin !== location.origin) return false;
    if (/\.[a-z0-9]{2,5}$/i.test(u.pathname)) return false;                  // файлы: rss.xml, sitemap.xml, картинки…
    if (/^\/(admin|install|api|og)(\/|$)/.test(u.pathname)) return false;
    if (u.pathname === location.pathname && u.search === location.search && u.hash) return false; // якорь на той же странице
    return true;
  }

  function swap(doc, url, push, y) {
    var nm = doc.getElementById('main'); if (!nm) throw new Error('no main');
    document.title = doc.title;
    META.forEach(function (sel) {
      var n = doc.head.querySelector(sel), o = document.head.querySelector(sel);
      if (n && o) { var at = n.hasAttribute('content') ? 'content' : 'href'; o.setAttribute(at, n.getAttribute(at)); }
    });
    main.innerHTML = nm.innerHTML;
    document.body.className = doc.body.className;
    // подсветка текущего раздела в меню и рубриках
    var cur = {}; $$('.main-nav a[aria-current], .rubrics a[aria-current]', doc).forEach(function (a) { cur[a.getAttribute('href')] = 1; });
    $$('.main-nav a, .rubrics a').forEach(function (a) { cur[a.getAttribute('href')] ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current'); });
    var q = $('.main-nav input[name=q]'), nq = $('.main-nav input[name=q]', doc); if (q) q.value = nq ? nq.value : '';
    if (push) history.pushState({ y: 0 }, '', url);
    closeMenu(); initPage(main);
    var h = location.hash && document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (h) h.scrollIntoView(); else window.scrollTo(0, y || 0);
    if (push) main.focus({ preventScroll: true });
    document.dispatchEvent(new CustomEvent('tk:page', { detail: { url: url } }));
  }

  function load(url, push, y) {
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    document.documentElement.classList.add('is-loading');
    if (push) history.replaceState({ y: window.scrollY }, '', location.href); // запомнить прокрутку уходящей страницы
    fetch(url, { headers: { 'X-Requested-With': 'pjax', Accept: 'text/html' }, signal: ctrl.signal, credentials: 'same-origin' }).then(function (r) {
      if (!/text\/html/.test(r.headers.get('Content-Type') || '')) throw new Error('not html');
      return r.text().then(function (t) { return { t: t, url: r.url }; });
    }).then(function (res) {
      swap(new DOMParser().parseFromString(res.t, 'text/html'), res.url, push, y);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      location.href = url; // запасной вариант — обычный переход (радио в этом случае остановится)
    }).then(function () { document.documentElement.classList.remove('is-loading'); });
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a'); if (!a || !eligible(a, e)) return;
    e.preventDefault(); load(a.href, true);
  });
  document.addEventListener('submit', function (e) { // форма поиска: GET /search?q=…
    var f = e.target; if (!f.matches || !f.matches('form.search') || (f.method || 'get').toLowerCase() !== 'get') return;
    var u = new URL(f.action, location.href); if (u.origin !== location.origin) return;
    e.preventDefault(); new FormData(f).forEach(function (v, k) { u.searchParams.set(k, v); });
    load(u.pathname + u.search, true);
  });
  window.addEventListener('popstate', function (e) { load(location.href, false, e.state && e.state.y); });
})();
