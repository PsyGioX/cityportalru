/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/* Админ-панель: редактор, медиатека, SEO-проверки, подтверждения. Без зависимостей. */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var BODY = document.body, ADMIN = BODY.getAttribute('data-admin') || '/admin', CSRF = BODY.getAttribute('data-csrf') || '';
  function h(tag, attrs, html) { var e = document.createElement(tag); for (var k in (attrs || {})) e.setAttribute(k, attrs[k]); if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var dirty = false;

  /* Боковое меню на мобильных: открывается кнопкой, закрывается по фону, крестику, Esc и переходу по ссылке */
  var side = $('#side'), backdrop = $('.side-backdrop'), mq = window.matchMedia('(max-width: 960px)');
  function sideOpen(on) {
    if (!side) return;
    side.classList.toggle('is-open', on);
    if (backdrop) backdrop.hidden = !on;
    document.documentElement.classList.toggle('side-open', on);
    $$('[data-toggle=side]').forEach(function (b) { b.setAttribute('aria-expanded', on ? 'true' : 'false'); });
    if (on) { var f = $('a', side); if (f) f.focus({ preventScroll: true }); }
  }
  $$('[data-toggle=side]').forEach(function (b) { b.addEventListener('click', function () { sideOpen(!side.classList.contains('is-open')); }); });
  $$('[data-close-side]').forEach(function (b) { b.addEventListener('click', function () { sideOpen(false); }); });
  if (side) side.addEventListener('click', function (e) { if (e.target.closest('a')) sideOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && side && side.classList.contains('is-open')) { sideOpen(false); var t = $('[data-toggle=side]'); if (t) t.focus(); } });
  (mq.addEventListener ? mq.addEventListener.bind(mq, 'change') : mq.addListener.bind(mq))(function () { if (!mq.matches) sideOpen(false); });
  window.addEventListener('pageshow', function () { sideOpen(false); }); // возврат «Назад» из кэша браузера

  /* Подтверждения */
  document.addEventListener('submit', function (e) {
    var f = e.target, msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    if (f.hasAttribute('data-confirm-action')) { var s = f.elements.namedItem('action'); var n = $$('input[name="ids[]"]:checked').length; if (s && !s.value) { e.preventDefault(); window.alert('Выберите действие.'); } else if (!n) { e.preventDefault(); window.alert('Отметьте хотя бы одну запись.'); } else if (s && s.value === 'delete' && !window.confirm('Удалить выбранные записи (' + n + ')? Это нельзя отменить.')) e.preventDefault(); }
    dirty = false;
  }, true);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-confirm]'); if (b && b.form && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });
  /* Выбор строк и закреплённая снизу панель массовых действий */
  var bars = $$('.bulk, .bulkbar').filter(function (b) { return b.querySelector('select[name=action]'); });
  var bar = bars[bars.length - 1] || null, cnt = null;
  if (bar) {
    bars.forEach(function (b) { if (b !== bar) b.parentNode.removeChild(b); });
    bar.classList.add('bulkfloat'); bar.hidden = true; bar.setAttribute('role', 'region'); bar.setAttribute('aria-label', 'Действия с отмеченными');
    cnt = h('span', { 'class': 'bulkfloat__n', 'aria-live': 'polite' });
    bar.insertBefore(cnt, bar.firstChild);
    var clr = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm bulkfloat__x' }, 'Снять выбор');
    bar.appendChild(clr);
    clr.addEventListener('click', function () { $$('input[name="ids[]"]').forEach(function (x) { x.checked = false; }); syncBulk(); });
  }
  function syncBulk() {
    var all = $$('input[name="ids[]"]'), on = all.filter(function (x) { return x.checked; }).length;
    $$('[data-check-all]').forEach(function (c) { c.checked = on > 0 && on === all.length; c.indeterminate = on > 0 && on < all.length; });
    $$('tr').forEach(function (tr) { var x = tr.querySelector('input[name="ids[]"]'); if (x) tr.classList.toggle('is-picked', x.checked); });
    if (!bar) return;
    bar.hidden = on === 0;
    document.documentElement.classList.toggle('has-bulk', on > 0);
    if (cnt) cnt.textContent = 'Выбрано: ' + on;
  }
  $$('[data-check-all]').forEach(function (c) { c.addEventListener('change', function () { $$('input[name="ids[]"]').forEach(function (x) { x.checked = c.checked; }); syncBulk(); }); });
  document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('input[name="ids[]"]')) syncBulk(); });
  window.addEventListener('pageshow', syncBulk);
  syncBulk();

  /* Защита от потери несохранённых изменений */
  $$('[data-dirty-guard]').forEach(function (f) { f.addEventListener('input', function () { dirty = true; }); });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* Транслитерация адреса */
  var TR = { 'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya' };
  function slugify(s) { return s.toLowerCase().replace(/[а-яё]/g, function (c) { return TR[c]; }).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80); }
  $$('[data-slug-from]').forEach(function (inp) {
    var src = document.getElementById('f-' + inp.getAttribute('data-slug-from')); if (!src) return;
    var touched = inp.value !== '';
    inp.addEventListener('input', function () { touched = inp.value !== ''; });
    src.addEventListener('input', function () { if (!touched) { inp.value = slugify(src.value); inp.dispatchEvent(new Event('slug')); } });
  });

  /* ---------- Окно выбора изображений ---------- */
  var picker = null;
  function openPicker(multi, cb) {
    if (!picker) {
      picker = h('dialog', { 'class': 'picker', 'aria-label': 'Медиатека' });
      picker.innerHTML = '<div class="picker__in"><div class="picker__head"><h2>Медиатека</h2><input type="search" placeholder="Поиск" aria-label="Поиск"><label class="btn btn--ghost btn--sm">Загрузить<input type="file" accept="image/*" multiple hidden></label><button type="button" class="iconbtn" data-x aria-label="Закрыть">✕</button></div><div class="picker__grid" role="group" aria-label="Изображения"></div><div class="picker__foot"><span class="small muted" data-info></span><span><button type="button" class="btn btn--ghost btn--sm" data-more>Ещё</button> <button type="button" class="btn btn--primary" data-ok>Выбрать</button></span></div></div>';
      document.body.appendChild(picker);
    }
    var grid = $('.picker__grid', picker), page = 1, pages = 1, q = '', sel = {}, order = [], loading = false, token = 0;
    var info = $('[data-info]', picker), more = $('[data-more]', picker), ok = $('[data-ok]', picker), search = $('input[type=search]', picker), file = $('input[type=file]', picker);
    function upd() { info.textContent = 'Выбрано: ' + order.length; more.hidden = loading || page >= pages; }
    function add(it, prepend) {
      if ($('.picker__item[data-id="' + it.id + '"]', grid)) return; // одно изображение — одна плитка, дублей не бывает
      var b = h('button', { type: 'button', 'class': 'picker__item', 'aria-pressed': sel[it.id] ? 'true' : 'false', title: it.alt || '' }, '<img src="' + esc(it.thumb) + '" alt="' + esc(it.alt) + '" loading="lazy">');
      b._it = it; b.setAttribute('data-id', it.id);
      b.addEventListener('click', function () {
        if (sel[it.id]) { delete sel[it.id]; order = order.filter(function (x) { return x !== it.id; }); b.setAttribute('aria-pressed', 'false'); }
        else { if (!multi) { sel = {}; order = []; $$('.picker__item', grid).forEach(function (x) { x.setAttribute('aria-pressed', 'false'); }); } sel[it.id] = it; order.push(it.id); b.setAttribute('aria-pressed', 'true'); }
        upd();
      });
      b.addEventListener('dblclick', function () { if (!multi) { sel = {}; sel[it.id] = it; order = [it.id]; ok.click(); } });
      var empty = $('p', grid); if (empty) empty.remove();
      prepend ? grid.insertBefore(b, grid.firstChild) : grid.appendChild(b);
    }
    // Загрузка одной страницы списка. token отсекает устаревшие ответы (быстрый ввод в поиск, повторные клики).
    function fetchPage() {
      var my = token; loading = true; upd();
      fetch(ADMIN + '/media/list?page=' + page + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (my !== token) return;
          pages = d.pages || 1;
          (d.items || []).forEach(function (i) { add(i); });
          if (!grid.children.length) grid.innerHTML = '<p class="muted">Ничего нет. Загрузите изображение.</p>';
        })
        .catch(function () { if (my === token && page > 1) page--; }) // сбой сети: следующая попытка загрузит ту же страницу
        .then(function () { if (my === token) { loading = false; upd(); } });
    }
    function load(reset) { // reset — начать список заново (поиск, открытие окна)
      token++; loading = false; grid.innerHTML = ''; grid.scrollTop = 0; page = 1; pages = 1; fetchPage();
    }
    function loadMore() { if (loading || page >= pages) return; page++; fetchPage(); }
    more.onclick = loadMore;
    grid.onscroll = function () { if (grid.scrollTop + grid.clientHeight >= grid.scrollHeight - 160) loadMore(); }; // бесконечная прокрутка
    var t; search.oninput = function () { clearTimeout(t); t = setTimeout(function () { q = search.value; load(true); }, 300); };
    var busy = false;
    file.onchange = function () {
      if (busy || !file.files.length) { file.value = ''; return; }
      busy = true;
      var fd = new FormData(); Array.prototype.forEach.call(file.files, function (f) { fd.append('files[]', f); }); fd.append('_csrf', CSRF);
      info.textContent = 'Загрузка…';
      fetch(ADMIN + '/media/upload', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF } }).then(function (r) { return r.json(); }).then(function (d) {
        (d.items || []).slice().reverse().forEach(function (i) { if (!$('.picker__item[data-id="' + i.id + '"]', grid)) add(i, true); });
        var dups = (d.items || []).filter(function (i) { return i.dup; }).length;
        if (d.errors && d.errors.length) alert(d.errors.join('\n')); else if (dups) info.textContent = 'Файл уже был в медиатеке — копия не создана';
        file.value = ''; busy = false; if (!dups) upd(); else more.hidden = loading || page >= pages;
      }).catch(function () { file.value = ''; busy = false; alert('Не удалось загрузить файл'); });
    };
    ok.onclick = function () { var r = order.map(function (id) { return sel[id]; }); picker.close(); if (r.length) cb(multi ? r : r[0]); };
    $('[data-x]', picker).onclick = function () { picker.close(); };
    search.value = ''; load(true); picker.showModal();
  }

  /* Поле «Изображение» */
  $$('[data-media-field]').forEach(function (f) {
    var input = $('input[type=hidden]', f), prev = $('.mediafield__preview', f), clear = $('[data-clear]', f);
    $('[data-pick=single]', f).addEventListener('click', function () {
      openPicker(false, function (m) { input.value = m.id; prev.innerHTML = '<img src="' + esc(m.thumb) + '" alt="">'; clear.hidden = false; dirty = true; input.dispatchEvent(new Event('input', { bubbles: true })); });
    });
    clear.addEventListener('click', function () { input.value = ''; prev.innerHTML = ''; clear.hidden = true; dirty = true; input.dispatchEvent(new Event('input', { bubbles: true })); });
  });

  /* Поле «Иконка / логотип по пути»: значение — адрес файла (/uploads/…), выбирается из медиатеки */
  $$('[data-media-path-field]').forEach(function (f) {
    var input = $('input[type=hidden]', f), prev = $('.mediafield__preview', f), label = $('[data-path-label]', f), clear = $('[data-clear]', f);
    function show(path) {
      input.value = path || '';
      prev.innerHTML = path ? '<img src="' + esc(path) + '" alt="">' : '';
      if (label) label.textContent = path || 'Иконка не выбрана';
      clear.hidden = !path; dirty = true; input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    $('[data-pick=single]', f).addEventListener('click', function () { openPicker(false, function (m) { show(m.thumb); }); });
    clear.addEventListener('click', function () { show(''); });
  });

  /* Поля формы, зависящие от типа (меню сайта: страница / ссылка) */
  var kindSel = document.getElementById('f-kind');
  if (kindSel) {
    var fp = $('[data-field=page_slug]'), fu = $('[data-field=url]');
    var applyKind = function () { if (fp) fp.hidden = kindSel.value !== 'page'; if (fu) fu.hidden = kindSel.value === 'page'; };
    kindSel.addEventListener('change', applyKind); applyKind();
  }

  /* Галерея новости */
  var gl = $('#gallery-list'), gi = $('#gallery-ids');
  if (gl && gi) {
    var sync = function () { gi.value = $$('li', gl).map(function (li) { return li.getAttribute('data-id'); }).join(','); dirty = true; };
    var mk = function (m) { var li = h('li', { 'data-id': m.id }, '<img src="' + esc(m.thumb) + '" alt="' + esc(m.alt) + '"><span class="gallery-adm__ctl"><button type="button" data-mv="-1" aria-label="Левее">‹</button><button type="button" data-mv="1" aria-label="Правее">›</button><button type="button" data-rm aria-label="Убрать">✕</button></span>'); gl.appendChild(li); };
    gl.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var li = b.closest('li');
      if (b.hasAttribute('data-rm')) li.remove();
      else { var d = parseInt(b.getAttribute('data-mv'), 10); if (d < 0 && li.previousElementSibling) gl.insertBefore(li, li.previousElementSibling); if (d > 0 && li.nextElementSibling) gl.insertBefore(li.nextElementSibling, li); }
      sync();
    });
    $('[data-pick=multi]').addEventListener('click', function () { openPicker(true, function (arr) { arr.forEach(function (m) { if (!$('li[data-id="' + m.id + '"]', gl)) mk(m); }); sync(); }); });
  }

  /* ---------- Теги: выпадающий список подсказок по последнему вводимому тегу ---------- */
  $$('[data-tags]').forEach(function (box) {
    var input = $('input', box), list = $('.tagbox__list', box), all = [], idx = -1, shown = [];
    try { all = JSON.parse(box.getAttribute('data-suggest') || '[]'); } catch (e) {}
    function parts() { return input.value.split(','); }
    function norm(t) { return t.replace(/^\s*#?/, '').trim(); }
    function chosen() { return parts().map(function (t) { return norm(t).toLowerCase(); }).filter(Boolean); }
    function setVal(arr, trail) { input.value = arr.join(', ') + (trail && arr.length ? ', ' : ''); input.dispatchEvent(new Event('input', { bubbles: true })); }
    function hide() { list.hidden = true; idx = -1; input.setAttribute('aria-expanded', 'false'); }
    function mark() { $$('li', list).forEach(function (li, i) { li.setAttribute('aria-selected', i === idx ? 'true' : 'false'); }); }
    function pick(t) { var p = parts().map(norm); p.pop(); p.push(t); setVal(p.filter(Boolean), true); input.focus(); render(); }
    function render() {
      var p = parts(), q = norm(p[p.length - 1]).toLowerCase(), have = chosen();
      if (q) have.pop(); // последний фрагмент — это то, что вводится сейчас, он не считается выбранным
      shown = all.filter(function (t) { var l = t.toLowerCase(); return have.indexOf(l) < 0 && (!q || l.indexOf(q) > -1) && l !== q; }).slice(0, 8);
      list.innerHTML = '';
      shown.forEach(function (t) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.textContent = t;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(t); });
        list.appendChild(li);
      });
      idx = -1; list.hidden = !shown.length; input.setAttribute('aria-expanded', shown.length ? 'true' : 'false');
      var now = chosen();
      $$('[data-add-tag]', box).forEach(function (b) { var on = now.indexOf(b.getAttribute('data-add-tag').toLowerCase()) > -1; b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.disabled = on; });
    }
    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('blur', function () { setTimeout(hide, 120); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % shown.length; mark(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = (idx - 1 + shown.length) % shown.length; mark(); }
      else if (e.key === 'Enter' && idx > -1) { e.preventDefault(); pick(shown[idx]); }
      else if (e.key === 'Escape') { hide(); }
    });
    $$('[data-add-tag]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        var p = parts().map(norm).filter(Boolean), t = b.getAttribute('data-add-tag');
        if (p.map(function (x) { return x.toLowerCase(); }).indexOf(t.toLowerCase()) < 0) p.push(t);
        setVal(p, true); render();
      });
    });
    render(); hide();
  });

  /* ---------- Визуальный редактор ---------- */
  function cleanPaste(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    $$('script,style,meta,link,iframe,object,svg,form', doc).forEach(function (n) { n.remove(); });
    $$('*', doc).forEach(function (n) { Array.prototype.slice.call(n.attributes).forEach(function (a) { if (!/^(href|src|alt)$/.test(a.name)) n.removeAttribute(a.name); }); });
    return doc.body.innerHTML;
  }
  function initRte(ta) {
    var allowImg = ta.hasAttribute('data-images') || true;
    var wrap = h('div', { 'class': 'rte' }), bar = h('div', { 'class': 'rte__bar', role: 'toolbar', 'aria-label': 'Форматирование' });
    var area = h('div', { 'class': 'rte__area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Текст', 'data-placeholder': 'Начните писать…' });
    var src = h('textarea', { 'class': 'rte__src', spellcheck: 'false', 'aria-label': 'HTML-код' }), status = h('div', { 'class': 'rte__status' }, '<span data-wc></span><span>Ctrl+B / Ctrl+I / Ctrl+K</span>');
    area.innerHTML = ta.value;
    var tools = [['bold', 'Ж', 'Жирный (Ctrl+B)'], ['italic', 'К', 'Курсив (Ctrl+I)'], ['|'], ['h2', 'H2', 'Подзаголовок'], ['h3', 'H3', 'Малый подзаголовок'], ['p', '¶', 'Обычный абзац'], ['|'],
      ['ul', '• Список', 'Маркированный список'], ['ol', '1. Список', 'Нумерованный список'], ['quote', '❝', 'Цитата'], ['|'], ['link', '🔗', 'Ссылка (Ctrl+K)'], ['unlink', '⛓︎', 'Убрать ссылку'], ['image', '🖼', 'Вставить изображение'], ['hr', '—', 'Разделитель'], ['|'], ['clear', 'Тx', 'Очистить форматирование'], ['src', '</>', 'HTML-код']];
    tools.forEach(function (t) { if (t[0] === '|') { bar.appendChild(h('span', { 'class': 'sep' })); return; } bar.appendChild(h('button', { type: 'button', 'data-cmd': t[0], title: t[2], 'aria-label': t[2] }, esc(t[1]))); });
    wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(src); wrap.appendChild(status);
    ta.style.display = 'none'; ta.parentNode.insertBefore(wrap, ta.nextSibling);
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
    function words() { var t = (area.innerText || '').trim(); $('[data-wc]', status).textContent = (t ? t.split(/\s+/).length : 0) + ' слов · ' + t.length + ' зн.'; }
    function push() { ta.value = wrap.classList.contains('is-src') ? src.value : area.innerHTML; }
    function block(tag) { var cur = (document.queryCommandValue('formatBlock') || '').toLowerCase(); document.execCommand('formatBlock', false, cur === tag ? 'p' : tag); }
    var saved = null;
    function remember() { var s = window.getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); }
    ['keyup', 'mouseup', 'input', 'focus'].forEach(function (ev) { area.addEventListener(ev, remember); });
    function restore() {
      area.focus(); var s = window.getSelection(); s.removeAllRanges();
      if (saved && area.contains(saved.startContainer)) s.addRange(saved);
      else { var r = document.createRange(); r.selectNodeContents(area); r.collapse(false); s.addRange(r); } // нет курсора — в конец текста
    }
    function ins(html) { restore(); document.execCommand('insertHTML', false, html); remember(); }
    function link() { var u = window.prompt('Адрес ссылки (https://… или /news/…):', 'https://'); if (u && /^(https?:\/\/|mailto:|tel:|\/|#)/i.test(u)) document.execCommand('createLink', false, u); }
    bar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var c = b.getAttribute('data-cmd');
      if (c === 'src') { var on = wrap.classList.toggle('is-src'); if (on) src.value = area.innerHTML; else area.innerHTML = src.value; b.setAttribute('aria-pressed', on); push(); return; }
      area.focus();
      if (c === 'bold' || c === 'italic') document.execCommand(c);
      else if (c === 'h2' || c === 'h3' || c === 'quote' || c === 'p') block(c === 'quote' ? 'blockquote' : c);
      else if (c === 'ul') document.execCommand('insertUnorderedList'); else if (c === 'ol') document.execCommand('insertOrderedList');
      else if (c === 'link') link(); else if (c === 'unlink') document.execCommand('unlink'); else if (c === 'hr') document.execCommand('insertHorizontalRule');
      else if (c === 'clear') { document.execCommand('removeFormat'); document.execCommand('formatBlock', false, 'p'); }
      else if (c === 'image') { remember(); openPicker(true, function (arr) { ins(arr.map(function (m) { return '<figure><img src="' + esc(m.src) + '" alt="' + esc(m.alt) + '"><figcaption></figcaption></figure>'; }).join('') + '<p><br></p>'); push(); words(); dirty = true; }); }
      push(); words(); dirty = true;
    });
    area.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); link(); } });
    area.addEventListener('paste', function (e) {
      var cd = e.clipboardData; if (!cd) return; e.preventDefault();
      var html = cd.getData('text/html'), txt = cd.getData('text/plain');
      if (html && !e.shiftKey) document.execCommand('insertHTML', false, cleanPaste(html));
      else document.execCommand('insertHTML', false, esc(txt).split(/\n{2,}/).map(function (p) { return '<p>' + p.replace(/\n/g, '<br>') + '</p>'; }).join(''));
    });
    area.addEventListener('input', function () { push(); words(); dirty = true; });
    src.addEventListener('input', function () { push(); dirty = true; });
    ta.form && ta.form.addEventListener('submit', push, true);
    ta._rte = { area: area, push: push }; words();
  }
  $$('textarea[data-richtext]').forEach(initRte);

  /* ---------- SEO-проверки в редакторе новости ---------- */
  var seo = $('#seo-panel');
  if (seo) {
    var f = function (id) { return document.getElementById(id); };
    var title = f('f-title'), st = f('f-seo_title'), sd = f('f-seo_description'), ex = f('f-excerpt'), slug = f('f-slug'), body = f('f-body'), cat = f('f-cat'), tags = f('f-tags'), cover = f('f-cover_media_id');
    var list = f('seo-checks');
    function counters() {
      $$('.counter').forEach(function (c) { var i = f(c.getAttribute('data-for')); var n = i.value.length, mn = +c.getAttribute('data-min'), mx = +c.getAttribute('data-max'); c.textContent = n + ' / ' + mx; c.className = 'counter ' + (n >= mn && n <= mx ? 'ok' : (n ? 'bad' : '')); });
    }
    function run() {
      counters();
      var t = st.value || title.value, d = sd.value || ex.value, html = body._rte ? body._rte.area.innerHTML : body.value, txt = body._rte ? body._rte.area.innerText : html.replace(/<[^>]+>/g, ' ');
      f('sn-title').textContent = t || 'Заголовок материала'; f('sn-desc').textContent = d || (txt || '').trim().slice(0, 160); f('sn-slug').textContent = slug.value || slugify(title.value) || '…';
      var tmp = document.createElement('div'); tmp.innerHTML = html;
      var imgs = $$('img', tmp), noAlt = imgs.filter(function (i) { return !i.getAttribute('alt'); }).length, links = $$('a[href]', tmp).filter(function (a) { return /^\/|^https?:\/\/[^/]*korenovsk/i.test(a.getAttribute('href')); }).length;
      var L = (txt || '').trim().length, hasH = $$('h2,h3', tmp).length;
      var items = [
        [t.length >= 20 && t.length <= 70 ? 'ok' : 'warn', 'Заголовок в поиске: ' + t.length + ' зн. (оптимально 20–70)'],
        [(sd.value.length >= 70 && sd.value.length <= 160) ? 'ok' : (sd.value ? 'warn' : 'info'), sd.value ? 'SEO-описание: ' + sd.value.length + ' зн. (оптимально 70–160)' : 'SEO-описание не задано — создастся автоматически из лида или текста'],
        [L >= 800 ? 'ok' : (L >= 400 ? 'info' : 'warn'), 'Объём текста: ' + L + ' зн.' + (L < 400 ? ' — маловато для индексации' : '')],
        [L < 1500 || hasH ? 'ok' : 'warn', hasH ? 'Есть подзаголовки' : (L < 1500 ? 'Подзаголовки не обязательны для короткого текста' : 'Длинный текст без подзаголовков H2/H3')],
        [cover && cover.value ? 'ok' : 'info', cover && cover.value ? 'Обложка выбрана' : 'Обложки нет — для соцсетей создастся автокартинка'],
        [noAlt ? 'warn' : 'ok', imgs.length ? (noAlt ? 'У ' + noAlt + ' из ' + imgs.length + ' изображений в тексте нет alt' : 'Изображения в тексте с alt') : 'Изображений в тексте нет'],
        [cat && cat.value ? 'ok' : 'warn', cat && cat.value ? 'Рубрика выбрана' : 'Выберите рубрику'],
        [tags && tags.value.trim() ? 'ok' : 'info', tags && tags.value.trim() ? 'Теги добавлены' : 'Добавьте 2–5 тегов'],
        [links ? 'ok' : 'info', links ? 'Есть внутренние ссылки: ' + links : 'Добавьте ссылку на другой материал сайта (внутренняя перелинковка)'],
        [(slug.value || slugify(title.value)).length <= 60 ? 'ok' : 'warn', 'Длина адреса: ' + (slug.value || slugify(title.value)).length + ' зн. (до 60)']
      ];
      var ico = { ok: '✓', warn: '!', info: 'i' };
      list.innerHTML = items.map(function (i) { return '<li class="' + (i[0] === 'ok' ? 'ok' : (i[0] === 'warn' ? 'warn' : '')) + '"><span class="pill pill--' + (i[0] === 'ok' ? 'ok' : (i[0] === 'warn' ? 'warn' : 'info')) + '">' + ico[i[0]] + '</span> ' + esc(i[1]) + '</li>'; }).join('');
    }
    var tm; function later() { clearTimeout(tm); tm = setTimeout(run, 250); }
    $('#news-form').addEventListener('input', later); slug.addEventListener('slug', later); run();
  }

  /* ---------- Загрузка в медиатеке (drag & drop) ---------- */
  var dz = $('#dropzone');
  if (dz) {
    var inp = $('#dz-input'), st2 = $('#upload-status'), sending = false;
    function send(files) {
      if (sending) return; sending = true; dz.setAttribute('aria-busy', 'true');
      var fd = new FormData(); Array.prototype.forEach.call(files, function (f) { fd.append('files[]', f); }); fd.append('_csrf', CSRF);
      st2.innerHTML = '<li>Загрузка и обработка…</li>';
      fetch(dz.getAttribute('data-upload'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF } }).then(function (r) { return r.json(); }).then(function (d) {
        st2.innerHTML = (d.items || []).map(function (i) { return '<li class="ok">' + (i.dup ? '= Уже есть в медиатеке (копия не создана): ' : '✓ Загружено: ') + esc(i.alt || 'файл') + '</li>'; }).join('') + (d.errors || []).map(function (x) { return '<li class="err">✗ ' + esc(x) + '</li>'; }).join('');
        if ((d.items || []).some(function (i) { return !i.dup; })) setTimeout(function () { location.reload(); }, 900);
      }).catch(function () { st2.innerHTML = '<li class="err">Ошибка загрузки</li>'; }).then(function () { sending = false; inp.value = ''; dz.removeAttribute('aria-busy'); });
    }
    // клик по самому <input> не должен снова вызывать inp.click() (в некоторых браузерах окно выбора открывалось дважды)
    dz.addEventListener('click', function (e) { if (e.target === inp) return; inp.click(); });
    dz.addEventListener('keydown', function (e) { if (e.target === dz && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); inp.click(); } });
    inp.addEventListener('change', function () { if (inp.files.length) send(inp.files); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('is-over'); }); });
    dz.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) send(e.dataTransfer.files); });
  }

  /* ---------- QR для 2FA ---------- */
  var qr = $('#qr');
  if (qr && window.qrcode) {
    var q = window.qrcode(0, 'M'); q.addData(qr.getAttribute('data-uri')); q.make();
    qr.innerHTML = '<img alt="QR-код для приложения-аутентификатора" src="' + q.createDataURL(5, 2) + '">';
  }
})();
