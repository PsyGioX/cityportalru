#!/usr/bin/env python3
# CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
# Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
# Project: https://github.com/PsyGioX/cityportalru
"""
Динамические тесты безопасности для «Твой Кореновск».
Запуск на тестовом экземпляре (НЕ на боевом сайте!):
    BASE=http://127.0.0.1:8080 ADMIN_PASS='...' python3 tools/security_tests.py
Нужен доступ к БД через команду `mysql` (для подготовки тестовых пользователей).
"""
import os, re, struct, subprocess, sys, time, zlib, io, json
import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1:8080')
ADMIN = os.environ.get('ADMIN_USER', 'admin')
PASS = os.environ.get('ADMIN_PASS', '')
DB = os.environ.get('DB', 'tk')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
results = []

def check(name, ok, detail=''):
    results.append((name, bool(ok), detail))
    print(('  PASS ' if ok else '  FAIL ') + name + (f'  [{detail}]' if detail and not ok else ''))

def sql(q):
    return subprocess.run(['mysql', '-N', DB, '-e', q], capture_output=True, text=True).stdout.strip()

def php(code):
    return subprocess.run(['php', '-r', 'require "' + ROOT + '/app/bootstrap.php"; ' + code], capture_output=True, text=True, cwd=ROOT).stdout.strip()

def csrf(html):
    m = re.search(r'name="_csrf" value="([a-f0-9]+)"', html)
    return m.group(1) if m else ''

def login(user, pw, s=None):
    s = s or requests.Session()
    r = s.get(BASE + '/admin/login')
    r = s.post(BASE + '/admin/login', data={'username': user, 'password': pw, '_csrf': csrf(r.text)}, allow_redirects=False)
    return s, r

def section(t):
    print('\n== ' + t)

# ------------------------------------------------------------------ 1. заголовки
section('1. Заголовки безопасности')
r = requests.get(BASE + '/')
h = r.headers
csp = h.get('Content-Security-Policy', '')
check('CSP задан и без unsafe-inline/unsafe-eval', csp and 'unsafe-inline' not in csp and 'unsafe-eval' not in csp)
check("CSP: object-src 'none', base-uri, frame-ancestors, form-action", all(x in csp for x in ["object-src 'none'", 'base-uri', 'frame-ancestors', 'form-action']))
check('X-Content-Type-Options: nosniff', h.get('X-Content-Type-Options') == 'nosniff')
check('X-Frame-Options', h.get('X-Frame-Options') in ('SAMEORIGIN', 'DENY'))
check('Referrer-Policy', 'strict-origin' in h.get('Referrer-Policy', ''))
check('Permissions-Policy', 'camera=()' in h.get('Permissions-Policy', ''))
check('Cross-Origin-Opener-Policy', h.get('Cross-Origin-Opener-Policy') == 'same-origin')
check('Нет X-Powered-By', 'X-Powered-By' not in h)
ra = requests.get(BASE + '/admin/login')
check('Админка: Cache-Control no-store', 'no-store' in ra.headers.get('Cache-Control', ''))
check('Админка: X-Robots-Tag noindex', 'noindex' in ra.headers.get('X-Robots-Tag', ''))
check("Админка: frame-ancestors 'none'", "frame-ancestors 'none'" in ra.headers.get('Content-Security-Policy', ''))

# ------------------------------------------------------------------ 2. доступ без авторизации
section('2. Доступ к админке без входа')
paths = ['', '/news', '/news/new', '/news/11', '/settings', '/users', '/users/new', '/media', '/media/list', '/seo', '/seo/redirects', '/system', '/audit', '/profile', '/events',
         '/pages', '/categories', '/tags', '/links', '/system/backup/x.sql.gz', '/news/11/preview']
bad = []
for p in paths:
    rr = requests.get(BASE + '/admin' + p, allow_redirects=False)
    if rr.status_code != 302 or '/admin/login' not in rr.headers.get('Location', ''):
        bad.append((p, rr.status_code))
check(f'Все {len(paths)} адресов админки перенаправляют на вход', not bad, str(bad))
posts = ['/news/bulk', '/news/11/delete', '/media/upload', '/settings', '/users/new', '/seo/regenerate', '/system/backup', '/logout']
bad = [(p, requests.post(BASE + '/admin' + p, data={}, allow_redirects=False).status_code) for p in posts]
bad = [x for x in bad if x[1] not in (302, 419)]
check('POST без сессии/CSRF отклоняются', not bad, str(bad))

# ------------------------------------------------------------------ 3. инъекции и обход пути
section('3. SQL-инъекции, XSS, обход пути')
t0 = time.time()
probes = ["/search?q=%27%20OR%201%3D1--", "/search?q=%27%3B%20SELECT%20SLEEP(5)--", "/category/x%27%20OR%20%271%27%3D%271", "/tag/%27%20UNION%20SELECT%20NULL--", "/news/a%27%20OR%20SLEEP(5)%23",
          "/obyavleniya?cat=sell%27%20OR%20SLEEP(5)--", "/news?page=1%20OR%201=1", "/search?q[]=1", "/news?page[]=1"]
codes = [requests.get(BASE + p).status_code for p in probes]
check('SQL-пробы не вызывают 500', all(c in (200, 404, 429) for c in codes), str(codes))
check('Time-based SQLi (SLEEP) не срабатывает', time.time() - t0 < 4.5, f'{time.time()-t0:.1f}s')
x = requests.get(BASE + '/search', params={'q': '<script>alert(1)</script>"\'><img src=x onerror=alert(2)>'})
check('Отражённый XSS в поиске экранируется', '<script>alert(1)</script>' not in x.text and 'onerror=alert(2)>' not in x.text.replace('&quot;', '"').replace('&gt;', '>') or '&lt;script&gt;' in x.text)
x = requests.get(BASE + '/%3Cscript%3Ealert(1)%3C/script%3E')
check('404-страница не отражает путь', x.status_code == 404 and '<script>alert(1)' not in x.text)
trav = ['/uploads/../config/config.php', '/..%2f..%2fconfig/config.php', '/%2e%2e/app/bootstrap.php', '/assets/../../config/config.php', '/assets/%2e%2e/%2e%2e/config/config.php',
        '/uploads/..%252f..%252fconfig/config.php', '/config/config.php', '/storage/logs/error.log', '/database/schema.sql', '/.git/config', '/.env', '/app/Core/Auth.php', '/tools/security_tests.py']
bad = []
for p in trav:
    rr = requests.get(BASE + p, allow_redirects=False)
    if rr.status_code == 200 and ('<?php' in rr.text or 'db' in rr.text[:200].lower() and 'pass' in rr.text.lower() or 'CREATE TABLE' in rr.text):
        bad.append(p)
check('Обход пути/служебные файлы недоступны', not bad, str(bad))
check('Листинг каталогов uploads закрыт', requests.get(BASE + '/uploads/2024/10/').status_code in (403, 404))
check('Прямой запуск .php из public запрещён', requests.get(BASE + '/index.php/../app/bootstrap.php').status_code in (403, 404, 200) and 'APP_VERSION' not in requests.get(BASE + '/index.php/../app/bootstrap.php').text)
x = requests.request('PUT', BASE + '/news')
check('Неподдерживаемые методы не исполняются (405/404)', x.status_code in (404, 405))
x = requests.get(BASE + '/', headers={'Host': 'evil.example'})
check('Host-header injection: в страницах нет evil.example', 'evil.example' not in x.text)

# ------------------------------------------------------------------ 4. приватность читателей
section('4. Приватность: сайт не собирает данные читателей')
pages = ['/', '/news', '/news/dozhdi-doberutsya-do-krasnodarskogo-kraya', '/afisha', '/kino', '/radio', '/pogoda', '/about', '/kontakty', '/principles', '/search?q=test', '/nope-404']
cookies = [p for p in pages if requests.get(BASE + p).headers.get('Set-Cookie')]
check('Читателям не выдаётся ни одной cookie (все публичные страницы)', not cookies, str(cookies))
hosts = set(); forms = []
for p in pages:
    h = requests.get(BASE + p).text
    for m in re.finditer(r'<(?:script|img|link|iframe|source|audio|video)\b[^>]*?(?:src|href)="(https?://[^"]+)"', h):
        if not re.search(r'rel="(canonical|alternate|search)"', m.group(0)) and 'rel="canonical"' not in m.group(0):
            hosts.add(re.sub(r'^(https?://[^/]+).*', r'\1', m.group(1)))
    forms += [(p, f) for f in re.findall(r'<form\b[^>]*>', h) if 'action="/search"' not in f]
base_host = re.sub(r'^(https?://[^/]+).*', r'\1', BASE)
ext = {h for h in hosts if h != base_host}
check('В страницах нет подключаемых внешних скриптов, картинок, шрифтов, iframe', not ext, str(ext))
check('Нет ни одной формы, кроме поиска (формы обратной связи и объявлений удалены)', not forms, str(forms[:2]))
csp = requests.get(BASE + '/').headers.get('Content-Security-Policy', '')
third = re.findall(r'https?://[^\s;]+', csp)
check("CSP: единственный внешний источник — поток радио; frame-src 'none'", all('127.0.0.1' in x or 'radioheart' in x or 'localhost' in x for x in third) and "frame-src 'none'" in csp, csp[:200])
check('Нет скриптов аналитики/рекламы в HTML и JS', not re.search(r'mc\.yandex|googletagmanager|google-analytics|smartcaptcha|metrika|gtag\(', requests.get(BASE + '/').text + requests.get(BASE + '/assets/js/site.js').text, re.I))
removed = {'/reklama': 404, '/obyavleniya': 404, '/obyavleniya/podat': 404, '/go/1': 404, '/kontakty-form': 404}
bad = {p: requests.get(BASE + p, allow_redirects=False).status_code for p in removed}
check('Рекламные адреса и адреса сбора данных удалены (404)', all(c == 404 for c in bad.values()), str(bad))
check('POST на публичные адреса не принимается', all(requests.post(BASE + p, data={'name': 'x'}).status_code in (404, 405) for p in ['/kontakty', '/reklama', '/obyavleniya/podat', '/api/consent']))
r = requests.get(BASE + '/advertising.html', allow_redirects=False)
check('Старый адрес /advertising.html → 301 на главную', r.status_code == 301 and r.headers.get('Location') == '/', str(r.headers.get('Location')))
check('В sitemap/llms/robots нет рекламных и пользовательских разделов', not re.search(r'reklama|obyavleniya|go/', requests.get(BASE + '/sitemap-pages.xml').text + requests.get(BASE + '/llms.txt').text + requests.get(BASE + '/robots.txt').text))
check('БД: нет таблиц с данными читателей', sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('messages','classifieds','consent_log','banners')") == '0')
check('БД: журнал 404 не хранит referrer', sql("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='not_found_log' AND column_name='referrer'") == '0')
rl0 = sql('SELECT COUNT(*) FROM rate_limits'); v0 = sql("SELECT views FROM news WHERE id=11")
for _ in range(3): requests.get(BASE + '/news/dozhdi-doberutsya-do-krasnodarskogo-kraya', headers={'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130'})
check('Просмотр статьи не создаёт записей о посетителе (ни IP, ни хэша)', sql('SELECT COUNT(*) FROM rate_limits') == rl0 and int(sql("SELECT views FROM news WHERE id=11")) >= int(v0) + 3, f'{rl0} {v0}')

# ------------------------------------------------------------------ 5. вход, сессии, перебор
section('5. Аутентификация')
sql("DELETE FROM login_attempts")
s, r = login(ADMIN, PASS)
check('Вход администратора успешен', r.status_code == 302 and r.headers.get('Location', '').rstrip('/').endswith('/admin'))
sc = r.headers.get('Set-Cookie', '')
check('Cookie сессии: HttpOnly и SameSite', 'httponly' in sc.lower() and 'samesite=lax' in sc.lower(), sc[:120])
pre = requests.Session(); pre.get(BASE + '/admin/login'); pre_id = pre.cookies.get('tksid')
r0 = pre.get(BASE + '/admin/login'); r1 = pre.post(BASE + '/admin/login', data={'username': ADMIN, 'password': PASS, '_csrf': csrf(r0.text)}, allow_redirects=False)
check('Фиксация сессии: ID меняется после входа', pre_id and pre.cookies.get('tksid') != pre_id)
h = php('echo \\App\\Core\\DB::val("SELECT password_hash FROM users WHERE username=\'admin\'");')
check('Пароль хранится как Argon2id/bcrypt', h.startswith('$argon2id$') or h.startswith('$2y$'), h[:12])
sid = s.cookies.get('tksid'); stored = sql(f"SELECT COUNT(*) FROM sessions WHERE id = '{sid}'")
check('ID сессии в БД хранится только в виде хэша', stored == '0' and sql('SELECT COUNT(*) FROM sessions') != '0')
r = s.get(BASE + '/admin/login?next=//evil.example', allow_redirects=False)
s2, r2 = login(ADMIN, PASS)
r3 = requests.Session(); rr = r3.get(BASE + '/admin/login?next=//evil.example')
rp = r3.post(BASE + '/admin/login?next=//evil.example', data={'username': ADMIN, 'password': PASS, '_csrf': csrf(rr.text), 'next': '//evil.example'}, allow_redirects=False)
check('Open redirect через next невозможен', 'evil.example' not in rp.headers.get('Location', ''), rp.headers.get('Location', ''))
# перебор
sql("DELETE FROM login_attempts")
msgs = []
for i in range(7):
    _, rr = login('victim', f'wrong-{i}')
    msgs.append(rr.status_code)
_, rr = login('victim', 'wrong-x')
check('Блокировка после 5 неудачных попыток', 'Слишком много' in rr.text)
_, ok = login(ADMIN, PASS)
check('Блокировка чужого логина не лишает входа владельца сайта', ok.status_code == 302)
sql("DELETE FROM login_attempts")
_, rr = login('nonexistent-user', 'x'); _, rr2 = login(ADMIN, 'bad-password')
e1 = re.search(r'alert--err[^>]*>([^<]+)', rr.text); e2 = re.search(r'alert--err[^>]*>([^<]+)', rr2.text)
check('Нет перечисления пользователей (одинаковый текст ошибки)', e1 and e2 and e1.group(1) == e2.group(1))
sql("DELETE FROM login_attempts")
weak = php('foreach(["password1234","qwertyqwerty12","Korenovsk2026!!","aaaaaaaaaaaaaa","short1!"] as $p) echo count(App\\Core\\Auth::passwordErrors($p,"admin"))>0 ? "1":"0";')
check('Политика паролей отклоняет слабые пароли', weak == '11111', weak)

# ------------------------------------------------------------------ 6. CSRF и права
section('6. CSRF и разграничение прав')
s, _ = login(ADMIN, PASS)
r = s.post(BASE + '/admin/seo/regenerate', data={}, allow_redirects=False)
check('POST без CSRF-токена → 419', r.status_code == 419, str(r.status_code))
page = s.get(BASE + '/admin/seo').text
tok = csrf(page)
r = s.post(BASE + '/admin/seo/regenerate', data={'_csrf': tok}, headers={'Origin': 'https://evil.example'}, allow_redirects=False)
check('POST с чужим Origin → 419', r.status_code == 419, str(r.status_code))
r = s.post(BASE + '/admin/seo/regenerate', data={'_csrf': 'a' * 64}, allow_redirects=False)
check('POST с неверным токеном → 419', r.status_code == 419)
r = s.post(BASE + '/admin/seo/regenerate', data={'_csrf': tok}, allow_redirects=False)
check('POST с верным токеном проходит', r.status_code == 302)
# роль «автор»
hh = php('echo App\\Core\\Auth::hashPassword("Author-Test-Pass-99!x");')
sql("DELETE FROM users WHERE username IN ('author_t','editor_t')")
sql(f"INSERT INTO users (username,email,display_name,password_hash,role,is_active,created_at,updated_at) VALUES ('author_t','a@t.ru','A','{hh}','author',1,NOW(),NOW()),('editor_t','e@t.ru','E','{hh}','editor',1,NOW(),NOW())")
sql("UPDATE settings SET v='0' WHERE k='require_2fa'")
a, r = login('author_t', 'Author-Test-Pass-99!x')
forbidden = {}
for p in ['/settings', '/users', '/users/new', '/system', '/audit', '/seo', '/links', '/pages', '/events', '/categories']:
    forbidden[p] = a.get(BASE + '/admin' + p, allow_redirects=False).status_code
check('Автор не имеет доступа к настройкам/пользователям/SEO/страницам', all(v == 403 for v in forbidden.values()), str(forbidden))
r = a.get(BASE + '/admin/news/11', allow_redirects=False)
check('Автор не может открыть чужой материал (IDOR)', r.status_code == 403, str(r.status_code))
r = a.get(BASE + '/admin/news/11/preview', allow_redirects=False)
check('Автор не видит предпросмотр чужого материала', r.status_code in (403, 404))
p = a.get(BASE + '/admin/news/new').text
r = a.post(BASE + '/admin/news/new', data={'_csrf': csrf(p), 'title': 'Автор пытается опубликовать', 'body': '<p>' + 'Текст материала для проверки прав. ' * 5 + '</p>', 'status': 'published', 'is_featured': '1'}, allow_redirects=False)
st = sql("SELECT status, is_featured FROM news WHERE title='Автор пытается опубликовать'")
check('Автор не может опубликовать/закрепить (статус понижен до «на проверке»)', st.split('\t')[0] == 'review' and st.split('\t')[1] == '0', st)
e, _ = login('editor_t', 'Author-Test-Pass-99!x')
check('Редактор не имеет доступа к настройкам и пользователям', e.get(BASE + '/admin/settings', allow_redirects=False).status_code == 403 and e.get(BASE + '/admin/users', allow_redirects=False).status_code == 403)
check('Редактор имеет доступ к SEO и страницам', e.get(BASE + '/admin/seo').status_code == 200 and e.get(BASE + '/admin/pages').status_code == 200)

# ------------------------------------------------------------------ 7. хранимый XSS и санитайзер
section('7. Хранимый XSS и санитайзер HTML')
s, _ = login(ADMIN, PASS)
dirty = ('<p onclick="x()">Текст <b>жирный</b></p><script>alert(1)</script><iframe src="//evil"></iframe><a href="javascript:alert(1)">ссылка</a>'
         '<img src="https://evil.example/x.png" onerror="alert(1)"><svg onload=alert(1)><p style="x">ok</p>' + 'Достаточно длинный текст. ' * 5)
p = s.get(BASE + '/admin/news/new').text
r = s.post(BASE + '/admin/news/new', data={'_csrf': csrf(p), 'title': 'Тест санитайзера HTML', 'body': dirty, 'status': 'draft', 'canonical_url': 'javascript:alert(1)', 'source_url': 'data:text/html,x'}, allow_redirects=False)
body = sql("SELECT body FROM news WHERE title='Тест санитайзера HTML'")
bad = [m for m in ['<script', 'onclick', 'onerror', 'javascript:', '<iframe', 'evil.example', 'onload', '<svg'] if m in body]
check('Санитайзер вычищает скрипты, обработчики событий, iframe, javascript:', not bad, str(bad))
check('Недопустимые canonical/source URL отброшены', sql("SELECT IFNULL(canonical_url,'')||IFNULL(source_url,'') FROM news WHERE title='Тест санитайзера HTML'") in ('', 'NULL') or sql("SELECT canonical_url IS NULL AND source_url IS NULL FROM news WHERE title='Тест санитайзера HTML'") == '1')

# ------------------------------------------------------------------ 8. загрузка файлов
section('8. Загрузка файлов')
def png(w, h, extra=b''):
    raw = b''.join(b'\x00' + b'\xff\x00\x00' * w for _ in range(h)) if w * h < 1_000_000 else b''
    def ch(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    if not raw:  # большой, но сжимаемый
        c = zlib.compressobj(9); raw_c = b''
        line = b'\x00' + b'\xff\x00\x00' * w
        for _ in range(h): raw_c += c.compress(line)
        raw_c += c.flush()
    else:
        raw_c = zlib.compress(raw)
    return b'\x89PNG\r\n\x1a\n' + ch(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + ch(b'IDAT', raw_c) + ch(b'IEND', b'') + extra
tok = csrf(s.get(BASE + '/admin/media').text)
def up(name, content, ctype):
    return s.post(BASE + '/admin/media/upload', files={'files[]': (name, content, ctype)}, data={'_csrf': tok}, headers={'X-Requested-With': 'XMLHttpRequest'}).json()
d = up('shell.php.jpg', b'<?php system($_GET["c"]); ?>', 'image/jpeg')
check('PHP-код под видом JPEG отклонён', not d.get('items') and d.get('errors'))
d = up('x.svg', b'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'image/svg+xml')
check('SVG (вектор XSS) отклонён', not d.get('items'))
d = up('x.html', b'<script>alert(1)</script>', 'text/html')
check('HTML-файл отклонён', not d.get('items'))
d = up('huge.png', png(6000, 6000), 'image/png')
check('Слишком большое по пикселям изображение (36 Мпикс) отклонено', not d.get('items'), str(d)[:120])
poly = png(40, 40, b'<?php system($_GET["c"]); ?>')
d = up('polyglot.png', poly, 'image/png')
ok = bool(d.get('items'))
stored_clean = True
if ok:
    path = sql(f"SELECT path FROM media WHERE id={d['items'][0]['id']}")
    for f in os.listdir(os.path.join(ROOT, 'public/uploads', os.path.dirname(path))):
        if os.path.basename(path) in f:
            stored_clean &= b'<?php' not in open(os.path.join(ROOT, 'public/uploads', os.path.dirname(path), f), 'rb').read()
check('PNG с дописанным PHP принят только после перекодирования (в файлах нет <?php)', (not ok) or stored_clean)
check('Имена файлов случайные (не берутся от пользователя)', (not ok) or ('polyglot' not in sql(f"SELECT path FROM media WHERE id={d['items'][0]['id']}")))
def jpg(w, h, color=(10, 90, 200)):
    from PIL import Image
    b = io.BytesIO(); Image.new('RGB', (w, h), color).save(b, 'JPEG'); return b.getvalue()
def count_media(): return int(sql('SELECT COUNT(*) FROM media'))
img1 = jpg(1600, 900); n0 = count_media()
a1 = up('copy-test.jpg', img1, 'image/jpeg'); n1 = count_media()
a2 = up('copy-test-again.jpg', img1, 'image/jpeg'); a3 = up('copy-test-third.jpg', img1, 'image/jpeg'); n3 = count_media()
check('Повторная загрузка того же файла не создаёт копий (3 загрузки → 1 запись)', n1 == n0 + 1 and n3 == n1 and a2['items'][0]['id'] == a1['items'][0]['id'] == a3['items'][0]['id'] and a2['items'][0].get('dup'), f'{n0}->{n1}->{n3}')
from PIL import Image as _I
mid = a1['items'][0]['id']; row = sql(f'SELECT path, widths FROM media WHERE id={mid}').split('\t'); wj = json.loads(row[1]); ws = wj['w'] if isinstance(wj, dict) else wj; ve = wj['e'] if isinstance(wj, dict) else 'webp'
ok_ratio = True
for wv in ws:
    im = _I.open(os.path.join(ROOT, 'public/uploads', f'{row[0]}-{wv}.{ve}')); ok_ratio &= abs(im.size[0] / im.size[1] - 1600 / 900) < 0.01 and im.size[0] == wv
check('Все нарезанные варианты сохраняют пропорции 16:9 (нет сжатия по высоте)', ok_ratio, str(ws))
# в тексте новости размеры картинки берутся из медиатеки, а не из HTML (защита от искажения при растягивании в редакторе)
p = s.get(BASE + '/admin/news/new').text
s.post(BASE + '/admin/news/new', data={'_csrf': csrf(p), 'title': 'Тест размеров картинки', 'status': 'draft', 'body': f'<p>Текст для проверки размеров изображения в материале.</p><img src="/uploads/{row[0]}-1200.{ve}" width="50" height="5" style="height:5px">'}, allow_redirects=False)
body = sql("SELECT body FROM news WHERE title='Тест размеров картинки'")
check('Размеры картинки в тексте исправлены по медиатеке (1600×900), style/растягивание отброшены', 'width="1600"' in body and 'height="900"' in body and 'height="5"' not in body and 'style' not in body, body[-160:])
sql("DELETE FROM news WHERE title='Тест размеров картинки'")
check('В папке uploads есть .htaccess, запрещающий исполнение', 'php_flag engine off' in open(os.path.join(ROOT, 'public/uploads/.htaccess')).read())

# ------------------------------------------------------------------ 9. кэширование, робот-файлы
section('9. Прочее')
x = requests.get(BASE + '/news/dozhdi-doberutsya-do-krasnodarskogo-kraya')
etag = x.headers.get('ETag')
x2 = requests.get(BASE + '/news/dozhdi-doberutsya-do-krasnodarskogo-kraya', headers={'If-None-Match': etag or ''})
check('Условный GET: 304 Not Modified для краулеров', x2.status_code == 304)
rb = requests.get(BASE + '/robots.txt').text
check('robots.txt не раскрывает нестандартный адрес админки', 'Disallow: /admin/' in rb or '/admin' not in rb)
cfg = os.path.join(ROOT, 'config/config.php')
check('config.php: права не шире 0640', (os.stat(cfg).st_mode & 0o007) == 0, oct(os.stat(cfg).st_mode & 0o777))
sql("DELETE FROM users WHERE username IN ('author_t','editor_t')")
sql("DELETE FROM news WHERE title IN ('Автор пытается опубликовать','Тест санитайзера HTML')"); sql("DELETE FROM media WHERE alt LIKE 'copy test%'")
sql("DELETE FROM login_attempts"); sql("DELETE FROM rate_limits")

print('\n' + '=' * 60)
fails = [r for r in results if not r[1]]
print(f'Итого: {len(results) - len(fails)} из {len(results)} проверок пройдено')
for n, _, d in fails:
    print('  ПРОВАЛ:', n, d)
json.dump([{'name': n, 'ok': ok, 'detail': d} for n, ok, d in results], open('/tmp/sec_results.json', 'w'), ensure_ascii=False, indent=1)
sys.exit(1 if fails else 0)
