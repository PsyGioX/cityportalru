#!/usr/bin/env python3
# CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
# Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
# Project: https://github.com/PsyGioX/cityportalru
"""
Браузерные тесты (Playwright): радио не прерывается при переходах, переключатель темы, отсутствие внешних запросов.
Запуск на тестовом экземпляре:  BASE=http://127.0.0.1:8080 DB=tk ADMIN_PASS='…' python3 tools/browser_tests.py
Сам поднимает локальный «радиопоток» (WAV) на :8090 и временно подставляет его в настройки.
"""
import os, re, subprocess, struct, sys, time, math, wave, threading, http.server, socketserver
from playwright.sync_api import sync_playwright

BASE = os.environ.get('BASE', 'http://127.0.0.1:8080'); DB = os.environ.get('DB', 'tk'); PASS = os.environ.get('ADMIN_PASS', '')
sql = lambda q: subprocess.run(['mysql', '-N', DB, '-e', q], capture_output=True, text=True).stdout.strip()
res = []
def check(n, ok, d=''):
    res.append(bool(ok)); print(('  PASS ' if ok else '  FAIL ') + n + (f'  [{d}]' if d and not ok else ''))

# локальный «эфир»
os.makedirs('/tmp/radio', exist_ok=True)
with wave.open('/tmp/radio/stream.wav', 'wb') as w:
    w.setnchannels(1); w.setsampwidth(1); w.setframerate(8000)
    w.writeframes(bytes(int(128 + 40 * math.sin(2 * math.pi * 440 * i / 8000)) for i in range(8000 * 90)))
class H(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **k): super().__init__(*a, directory='/tmp/radio', **k)
    def log_message(self, *a): pass
    def end_headers(self): self.send_header('Access-Control-Allow-Origin', '*'); super().end_headers()
socketserver.TCPServer.allow_reuse_address = True
srv = socketserver.ThreadingTCPServer(('127.0.0.1', 8090), H); threading.Thread(target=srv.serve_forever, daemon=True).start()
old = sql("SELECT v FROM settings WHERE k='radio_url'")
sql("INSERT INTO settings (k,v) VALUES ('radio_url','http://127.0.0.1:8090/stream.wav') ON DUPLICATE KEY UPDATE v=VALUES(v)")
sql("INSERT INTO settings (k,v) VALUES ('content_version', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE v=UNIX_TIMESTAMP()")

HOOK = """(() => { window.__audios = []; const p = HTMLMediaElement.prototype.play;
 HTMLMediaElement.prototype.play = function () { if (!window.__audios.includes(this)) window.__audios.push(this); return p.apply(this, arguments); }; })();"""
allowed = {re.sub(r'^https?://', '', BASE), '127.0.0.1:8090'}
try:
    with sync_playwright() as p:
        b = p.chromium.launch(args=['--autoplay-policy=no-user-gesture-required'])
        ctx = b.new_context(viewport={'width': 1300, 'height': 900}, locale='ru-RU'); ctx.add_init_script(HOOK)
        pg = ctx.new_page(); errors = []; urls = []
        pg.on('console', lambda m: errors.append(m.text[:140]) if m.type == 'error' and 'favicon' not in m.text else None)
        pg.on('pageerror', lambda e: errors.append('PAGEERR ' + str(e)[:140])); pg.on('request', lambda r: urls.append(r.url))
        playing = lambda: pg.evaluate("window.__audios.length > 0 && window.__audios.some(a => !a.paused && a.currentTime > 0)")
        def wait_play(timeout=8.0):  # опрос через evaluate: wait_for_function использует eval, а строгий CSP сайта его запрещает
            end = time.time() + timeout
            while time.time() < end:
                if pg.evaluate("window.__audios.some(a => !a.paused && a.currentTime > 0.2)"): return True
                pg.wait_for_timeout(150)
            return False

        print('\n== Радио при переходах')
        pg.goto(BASE + '/'); pg.evaluate("window.__marker = 'alive'")
        pg.click('#radio-btn'); wait_play()
        check('Радио запускается по кнопке в шапке', playing() and pg.get_attribute('#radio-btn', 'aria-pressed') == 'true')
        steps = [('Новости', '/news'), ('Афиша', '/afisha'), ('Погода', '/pogoda'), ('О редакции', '/about')]
        for label, path in steps:
            pg.click(f'.main-nav a[href="{path}"]'); pg.wait_for_url('**' + path); pg.wait_for_timeout(250)
            ok = pg.evaluate("window.__marker") == 'alive' and playing() and pg.get_attribute('#radio-btn', 'aria-pressed') == 'true'
            check(f'Переход «{label}»: страница не перезагружалась, радио играет', ok)
        pg.click('.main-nav a[href="/news"]'); pg.wait_for_url('**/news'); pg.wait_for_selector('.card__title a')
        title0 = pg.title(); pg.click('.card__title a >> nth=0'); pg.wait_for_url('**/news/*'); pg.wait_for_timeout(300)
        check('Переход в статью: радио играет, заголовок вкладки и canonical обновились', playing() and pg.title() != title0 and '/news/' in (pg.get_attribute('link[rel=canonical]', 'href') or ''))
        pg.go_back(); pg.wait_for_url('**/news'); pg.wait_for_timeout(300)
        check('Кнопка «Назад»: тоже без перезагрузки и без остановки эфира', pg.evaluate("window.__marker") == 'alive' and playing())
        pg.fill('#q-head', 'погода'); pg.press('#q-head', 'Enter'); pg.wait_for_url('**/search**'); pg.wait_for_timeout(300)
        check('Поиск из шапки — без перезагрузки, радио играет', pg.evaluate("window.__marker") == 'alive' and playing())
        pg.click('.main-nav a[href="/radio"]'); pg.wait_for_url('**/radio'); pg.wait_for_timeout(200)
        check('Страница «Радио»: большая кнопка показывает, что эфир включён', pg.get_attribute('#radio-big', 'aria-pressed') == 'true')
        pg.click('#radio-big'); pg.wait_for_timeout(400)
        check('Большая кнопка останавливает эфир, кнопка в шапке синхронизируется', not playing() and pg.get_attribute('#radio-btn', 'aria-pressed') == 'false')
        pg.click('#radio-big'); wait_play()
        check('И снова запускает', playing() and pg.get_attribute('#radio-btn', 'aria-pressed') == 'true')
        pg.click('.brand'); pg.wait_for_url(BASE + '/'); pg.wait_for_timeout(200)
        check('Возврат на главную: эфир продолжается', playing() and pg.evaluate("window.__marker") == 'alive')
        pg.click('#radio-btn'); pg.wait_for_timeout(300)
        check('Кнопка в шапке останавливает эфир на любой странице', not playing())

        print('\n== Тема оформления')
        get = lambda: pg.evaluate("[document.documentElement.getAttribute('data-theme'), document.documentElement.getAttribute('data-theme-pref'), localStorage.getItem('tk-theme')]")
        bg = lambda: pg.evaluate("getComputedStyle(document.body).backgroundColor")
        pg.evaluate("localStorage.clear()"); pg.reload(); pg.wait_for_timeout(200)
        check('По умолчанию — «как в системе» (без data-theme)', get()[0] is None and get()[1] == 'auto')
        pg.click('#theme-toggle'); light_bg = bg()
        check('1-й клик: светлая тема', get() == ['light', 'light', 'light'] and light_bg == 'rgb(255, 255, 255)', str(get()) + light_bg)
        pg.click('#theme-toggle'); dark_bg = bg()
        check('2-й клик: тёмная тема, фон меняется', get() == ['dark', 'dark', 'dark'] and dark_bg != light_bg, dark_bg)
        pg.click('.main-nav a[href="/afisha"]'); pg.wait_for_url('**/afisha'); pg.wait_for_timeout(200)
        check('Тема сохраняется при плавном переходе', get()[0] == 'dark' and bg() == dark_bg)
        pg.reload(); pg.wait_for_timeout(200)
        check('Тема сохраняется после перезагрузки (без «вспышки»: атрибут ставится до отрисовки)', get()[0] == 'dark' and bg() == dark_bg)
        pg.click('#theme-toggle')
        check('3-й клик: снова «как в системе»', get() == [None, 'auto', None])
        ctx2 = b.new_context(color_scheme='dark', viewport={'width': 1300, 'height': 900}); p2 = ctx2.new_page(); p2.goto(BASE + '/')
        check('«Как в системе»: при тёмной системной теме сайт тёмный', p2.evaluate("getComputedStyle(document.body).backgroundColor") != 'rgb(255, 255, 255)')
        p2.click('#theme-toggle'); check('Явная «светлая» перебивает тёмную системную', p2.evaluate("getComputedStyle(document.body).backgroundColor") == 'rgb(255, 255, 255)')
        ctx2.close()

        print('\n== Мобильная версия и приватность')
        mc = b.new_context(viewport={'width': 390, 'height': 800}); m = mc.new_page(); m.goto(BASE + '/')
        m.click('.menu-toggle'); open_ = m.get_attribute('.menu-toggle', 'aria-expanded') == 'true'
        m.click('.main-nav a[href="/afisha"]'); m.wait_for_url('**/afisha'); m.wait_for_timeout(200)
        check('Мобильное меню: открывается, после перехода закрывается', open_ and m.get_attribute('.menu-toggle', 'aria-expanded') == 'false')
        check('Нет горизонтальной прокрутки на 390 px', m.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth"))
        mc.close()
        hosts = {re.sub(r'^https?://', '', u).split('/')[0] for u in urls if u.startswith('http')}
        check('За всё время сессии браузер обращался только к сайту и потоку радио (нет сторонних запросов)', hosts <= allowed, str(hosts - allowed))
        check('Нет ошибок в консоли браузера', not errors, str(errors[:3]))
        cookies = ctx.cookies(); check('Браузер не получил ни одной cookie от сайта', not cookies, str(cookies))

        if PASS:
            print('\n== Админка: тема и Медиатека')
            a = b.new_context(viewport={'width': 1300, 'height': 900}); ap = a.new_page(); ap.goto(BASE + '/admin/login'); ap.fill('[name=username]', 'admin'); ap.fill('[name=password]', PASS); ap.click('button[type=submit]'); ap.wait_for_load_state()
            abg = lambda: ap.evaluate("getComputedStyle(document.body).backgroundColor")
            ap.goto(BASE + '/admin/news'); ap.evaluate("localStorage.clear()"); ap.reload(); ap.click('.theme-toggle'); l = abg(); ap.click('.theme-toggle'); d = abg()
            check('Админка: переключатель темы работает (светлая → тёмная)', l != d and ap.evaluate("document.documentElement.getAttribute('data-theme')") == 'dark', f'{l} {d}')
            # Медиатека: быстрые повторные клики по зоне загрузки
            ap.goto(BASE + '/admin/media'); n0 = int(sql('SELECT COUNT(*) FROM media')); choosers = []
            ap.on('filechooser', lambda fc: choosers.append(fc))
            from PIL import Image
            Image.new('RGB', (1200, 800), (120, 30, 160)).save('/tmp/bt_img.jpg')
            for _ in range(4): ap.click('#dropzone', delay=10)
            ap.wait_for_timeout(500)
            check('Серия быстрых кликов по зоне загрузки открывает окно выбора не больше раза на клик', len(choosers) <= 4, str(len(choosers)))
            choosers[0].set_files('/tmp/bt_img.jpg'); ap.wait_for_timeout(2500)
            n1 = int(sql('SELECT COUNT(*) FROM media'))
            check('Загрузка одного файла = одна запись', n1 == n0 + 1, f'{n0}->{n1}')
            for fc in choosers[1:2]: pass
            ap.goto(BASE + '/admin/media'); choosers.clear(); ap.click('#dropzone'); ap.wait_for_timeout(300); choosers[0].set_files('/tmp/bt_img.jpg'); ap.wait_for_timeout(2500)
            check('Повторная загрузка того же файла копию не создаёт', int(sql('SELECT COUNT(*) FROM media')) == n1)
            # вставка картинки в текст: каретка сохраняется, размеры верные, повторные вставки не искажают
            ap.goto(BASE + '/admin/news/new'); ap.wait_for_timeout(400); ap.click('.rte__area'); ap.keyboard.type('Начало текста. ')
            for _ in range(3):
                ap.click('[data-cmd=image]'); ap.wait_for_selector('dialog.picker[open]'); ap.wait_for_timeout(500); ap.locator('.picker__item').first.click(); ap.click('[data-ok]'); ap.wait_for_timeout(300)
            ap.wait_for_timeout(300)
            dims = ap.evaluate("Array.from(document.querySelectorAll('.rte__area img')).map(i=>{const r=i.getBoundingClientRect();return [Math.round(r.width),Math.round(r.height),i.naturalWidth,i.naturalHeight]})")
            ratio_ok = all(w > 0 and abs(w / h - nw / nh) < 0.03 for w, h, nw, nh in dims if nh)
            check('3 вставки изображения → 3 картинки в тексте, текст «Начало текста» на месте', len(dims) == 3 and 'Начало текста' in ap.evaluate("document.querySelector('.rte__area').innerText"), str(dims))
            check('Пропорции вставленных картинок не искажены (ширина/высота = исходные)', ratio_ok, str(dims))
            check('Новых файлов при вставке не создаётся', int(sql('SELECT COUNT(*) FROM media')) == n1)
            a.close()
        b.close()
finally:
    sql("UPDATE settings SET v='%s' WHERE k='radio_url'" % old.replace("'", "''")); srv.shutdown()
print(f'\nИтого: {sum(res)} из {len(res)} проверок пройдено'); sys.exit(0 if all(res) else 1)
