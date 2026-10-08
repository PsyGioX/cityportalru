#!/usr/bin/env python3
# CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
# Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
# Project: https://github.com/PsyGioX/cityportalru
"""
Миграция статического сайта «Твой Кореновск» в базу данных.

Использование:
    python3 tools/migrate_static.py /путь/к/старому/сайту

Что делает:
  * разбирает pages_news/*.html (заголовок, дата, текст, галерея);
  * делает из картинок оптимизированные JPEG/PNG + WebP-варианты (480/800/1200/1600 px)
    и кладёт в public/uploads/ГГГГ/ММ/;
  * генерирует database/seed.sql (категории, статьи, теги, ссылки, страницы, настройки).

Пароли и учётные записи в seed.sql НЕ создаются — администратор создаётся установщиком.
"""
import hashlib, json, os, re, shutil, sys
from datetime import datetime
from pathlib import Path
from bs4 import BeautifulSoup
from PIL import Image, ImageOps

SRC = Path(sys.argv[1] if len(sys.argv) > 1 else '/home/claude/src/tvoykorenovsk')
ROOT = Path(__file__).resolve().parent.parent
UP = ROOT / 'public' / 'uploads'
WIDTHS = [480, 800, 1200, 1600]

TR = dict(zip('абвгдеёжзийклмнопрстуфхцчшщъыьэюя',
              ['a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','','y','','e','yu','ya']))
def slugify(s, limit=80):
    s = s.lower().replace('ё', 'е')
    s = ''.join(TR.get(c, c) for c in s)
    s = re.sub(r'[^a-z0-9]+', '-', s).strip('-')
    if len(s) > limit:
        s = s[:limit].rsplit('-', 1)[0]
    return s or 'item'

def q(v):
    if v is None: return 'NULL'
    if isinstance(v, (int, float)): return str(v)
    v = str(v).replace('\\', '\\\\').replace("'", "\\'").replace('\r', '\\r').replace('\n', '\\n').replace('\x00', '')
    return "'" + v + "'"

def ins(table, cols, rows):
    if not rows: return ''
    out = f'INSERT INTO `{table}` (' + ', '.join(f'`{c}`' for c in cols) + ') VALUES\n'
    out += ',\n'.join('(' + ', '.join(q(x) for x in r) + ')' for r in rows) + ';\n'
    return out

# ---------------------------------------------------------------- media
media_rows, media_by_src = [], {}
def add_media(src_rel, alt, when, credit=''):
    if src_rel in media_by_src: return media_by_src[src_rel]
    p = SRC / src_rel.lstrip('/')
    if not p.exists():
        print('  ! нет файла', p); return None
    h = hashlib.sha1(p.read_bytes()).hexdigest()[:12]
    d = UP / f'{when:%Y}' / f'{when:%m}'; d.mkdir(parents=True, exist_ok=True)
    base = f'{when:%Y}/{when:%m}/{h}'
    im = Image.open(p)
    animated = getattr(im, 'n_frames', 1) > 1
    if animated:
        # анимированный GIF → анимированный WebP (в разы легче)
        frames = []
        durs = []
        for i in range(im.n_frames):
            im.seek(i); frames.append(im.convert('RGBA').copy()); durs.append(im.info.get('duration', 80))
        w0, h0 = frames[0].size
        if w0 > 800:
            frames = [f.resize((800, int(h0 * 800 / w0)), Image.LANCZOS) for f in frames]
        out = d / f'{h}.webp'
        frames[0].save(out, save_all=True, append_images=frames[1:], duration=durs, loop=0, quality=70, method=4)
        w, hh = frames[0].size
        ext, widths = 'webp', []
    else:
        im = ImageOps.exif_transpose(im)
        alpha = im.mode in ('RGBA', 'LA') or (im.mode == 'P' and 'transparency' in im.info)
        im = im.convert('RGBA' if alpha else 'RGB')
        if im.width > 2000:
            im = im.resize((2000, int(im.height * 2000 / im.width)), Image.LANCZOS)
        w, hh = im.size
        ext = 'png' if alpha else 'jpg'
        if alpha: im.save(d / f'{h}.png', optimize=True)
        else: im.save(d / f'{h}.jpg', quality=84, optimize=True, progressive=True)
        widths = [x for x in WIDTHS if x < w] + [w]
        widths = sorted(set(widths))
        for x in widths:
            r = im if x == w else im.resize((x, max(1, int(hh * x / w))), Image.LANCZOS)
            r.save(d / f'{h}-{x}.webp', quality=78, method=5)
    size = sum(f.stat().st_size for f in d.glob(f'{h}*'))
    mid = len(media_rows) + 1
    media_rows.append((mid, base, ext, w, hh, size, alt, '', credit, json.dumps(widths), when.strftime('%Y-%m-%d %H:%M:%S')))
    media_by_src[src_rel] = mid
    return mid

# ---------------------------------------------------------------- content
CATS = [
    (1, 'obshchestvo', 'Общество', 'Новости Кореновска и Кореновского района: жизнь горожан, социальная поддержка, образование, дети.'),
    (2, 'ekonomika-i-apk', 'Экономика и АПК', 'Сельское хозяйство, урожай, пенсии, экономика Кубани и Кореновского района.'),
    (3, 'bezopasnost', 'Безопасность', 'Профилактика правонарушений, полиция, МЧС и безопасность жителей Кореновска.'),
    (4, 'pogoda', 'Погода', 'Прогноз и предупреждения о погоде в Кореновске и Краснодарском крае.'),
    (5, 'sport', 'Спорт', 'Спортивные новости Кореновска: турниры, соревнования, результаты.'),
    (6, 'kultura-i-vera', 'Культура и вера', 'Культурные события, праздники и религиозная жизнь Кореновского района.'),
    (7, 'gorod', 'Город', 'Благоустройство, транспорт и жизнь города Кореновска.'),
    (8, 'goroskop', 'Гороскоп', 'Еженедельный гороскоп для всех знаков зодиака.'),
]
NEWS_META = {  # id: (категория, [теги], время публикации)
    1: (2, ['пенсии', 'сельское хозяйство', 'Госдума'], '2024-10-23 11:00'),
    2: (1, ['дети', 'творчество'], '2024-10-23 15:30'),
    3: (3, ['профилактика', 'полиция'], '2024-10-24 10:00'),
    4: (1, ['соцподдержка', 'Кореновский район'], '2024-10-24 12:00'),
    5: (4, ['магнитные бури', 'погода'], '2024-10-24 17:00'),
    6: (2, ['День урожая', 'Кубань'], '2024-10-27 10:00'),
    7: (5, ['футбол', 'турнир'], '2024-10-27 11:00'),
    8: (6, ['крестный ход', 'православие'], '2024-10-27 12:00'),
    9: (7, ['благоустройство', 'цветы'], '2024-10-27 09:00'),
    10: (8, ['гороскоп'], '2024-10-27 08:00'),
    11: (4, ['погода', 'дожди', 'Краснодарский край'], '2024-10-28 09:00'),
}
LEAD = {11}  # главная новость на витрине

def clean_text(s):
    return re.sub(r'\s+', ' ', s).strip()

def convert_body(p):
    html = p.decode_contents()
    html = re.sub(r'<br\s*/?>', '<br>', html)
    parts = re.split(r'(?:\s*<br>\s*){2,}', html)
    out = []
    for part in parts:
        part = part.strip()
        if not part: continue
        part = re.sub(r'\s+', ' ', part)
        part = re.sub(r'\sclass="[^"]*"', '', part)
        part = re.sub(r'<a\s+href="([^"]+)"[^>]*>', r'<a href="\1" rel="noopener" target="_blank">', part)
        part = part.replace('<br>', ' ').strip() if part.count('<br>') and len(part) < 40 else part
        m = re.fullmatch(r'<b><i>\s*([^<]+?):?\s*</i></b>', part)
        if m: out.append(f'<h3>{m.group(1)}</h3>'); continue
        out.append(f'<p>{part}</p>')
    return '\n'.join(out)

def first_sentences(text, n=200):
    text = clean_text(text)
    if len(text) <= n: return text
    cut = text[:n]
    m = re.search(r'^(.{60,}?[\.\!\?])\s', text[:n + 80])
    if m and len(m.group(1)) <= n + 60: return m.group(1)
    return cut.rsplit(' ', 1)[0] + '…'

news_rows, nm_rows, tag_ids, tag_rows, nt_rows = [], [], {}, [], []
for n in range(1, 12):
    s = BeautifulSoup((SRC / 'pages_news' / f'{n}.html').read_text(encoding='utf-8'), 'lxml')
    title = clean_text(s.select_one('.tag-name').get_text())
    d, mth, y = s.select_one('.date_p').get_text().strip().split('.')
    cat, tags, when_s = NEWS_META[n]
    when = datetime.strptime(when_s, '%Y-%m-%d %H:%M')
    p = s.select_one('.news_p')
    body = convert_body(p)
    plain = clean_text(BeautifulSoup(body, 'lxml').get_text(' '))
    lead_p = p.find(['b', 'i'])
    excerpt = first_sentences(plain)
    imgs = [i.get('src') for i in s.select('.swiper-slide img') if i.get('src', '').startswith('/media')]
    if n == 5: imgs = imgs[::-1]  # обложка — статичное фото, анимация — в галерее
    ids = []
    for k, src in enumerate(imgs):
        mid = add_media(src, title if k == 0 else f'{title} — фото {k + 1}', when)
        if mid: ids.append(mid)
    cover = ids[0] if ids else None
    if cover is None and n == 1:
        body += ''  # исходное фото было ссылкой на сторонний сайт — загрузите своё в админке
    slug = slugify(title)
    news_rows.append((n, cat, None, title, slug, excerpt, body, plain, cover, 'published', when.strftime('%Y-%m-%d %H:%M:%S'),
                      1 if n in LEAD else 0, 0, None, None, None, 0, None, None,
                      when.strftime('%Y-%m-%d %H:%M:%S'), when.strftime('%Y-%m-%d %H:%M:%S')))
    for i, mid in enumerate(ids[1:] if len(ids) > 1 else []):
        nm_rows.append((n, mid, i))
    for t in tags:
        if t not in tag_ids:
            tag_ids[t] = len(tag_ids) + 1
            tag_rows.append((tag_ids[t], slugify(t), t))
        nt_rows.append((n, tag_ids[t]))

# ---------------------------------------------------------------- статические ресурсы для блоков «сервисы»
IMG = ROOT / 'public' / 'assets' / 'img'
(IMG / 'services').mkdir(parents=True, exist_ok=True)
(IMG / 'social').mkdir(parents=True, exist_ok=True)

def raster_logo(src, dst, h=128):
    im = Image.open(SRC / 'images' / src)
    im = im.convert('RGBA')
    im = im.resize((max(1, int(im.width * h / im.height)), h), Image.LANCZOS) if im.height > h else im
    im.save(IMG / 'services' / dst, quality=88, method=5)

for f, dst in [('decide_together_app_blu_digitale.svg', 'gu-reshaem-vmeste.svg'), ('child_blu_digitale.svg', 'gu-rozhdenie.svg'),
               ('hand_braille_blu_digitale.svg', 'gu-invalidam.svg'), ('auto_app_blu_digitale.svg', 'gu-avto.svg'),
               ('loudspeaker.svg', 'gu-golosovaniya.svg'), ('ruble.svg', 'gu-shtrafy.svg'),
               ('logo.svg', 'mybox.svg'), ('icon-app.svg', 'magnit.svg')]:
    shutil.copy(SRC / 'images' / f, IMG / 'services' / dst)
raster_logo('logo.png', 'sushibox.webp'); raster_logo('sic.webp', 'sicilia.webp')
raster_logo('48e135a.png', 'pyaterochka.webp'); raster_logo('cbjendwqfidz6jweqylj0aitjcrn3ukqve0tu6r1.png', 'yandex-eda.webp')
for f in ['vk', 'tg', 'whatsapp']:
    t = (SRC / 'images' / f'{f}.svg').read_text(encoding='utf-8')
    t = re.sub(r'<!DOCTYPE[^>]*>', '', t); t = re.sub(r'<!--.*?-->', '', t, flags=re.S)
    t = re.sub(r'<g id="SVGRepo_(bgCarrier|tracerCarrier)"[^>]*/>', '', t)
    t = re.sub(r'\s+width="\d+px"\s+height="\d+px"', '', t).replace('fill="#000000">', '>', 1)
    (IMG / 'social' / f'{f}.svg').write_text(re.sub(r'\n\s*\n', '\n', t).strip(), encoding='utf-8')

LINKS = [  # group, title, description, url, icon, new_tab
    ('gosuslugi', '«Госуслуги Решаем вместе»', 'Задайте вопрос, подайте жалобу, внесите предложение', 'https://www.gosuslugi.ru/help/obratitsya_v_pos', '/assets/img/services/gu-reshaem-vmeste.svg'),
    ('gosuslugi', 'Рождение ребёнка', 'Первые документы, детский сад, пособия и другие услуги', 'https://www.gosuslugi.ru/baby', '/assets/img/services/gu-rozhdenie.svg'),
    ('gosuslugi', 'Людям с инвалидностью', 'Справки, обращения, льготы, услуги для реабилитации', 'https://www.gosuslugi.ru/invalidam', '/assets/img/services/gu-invalidam.svg'),
    ('gosuslugi', '«Госуслуги Авто»', 'Электронные документы и сервисы для автовладельцев', 'https://www.gosuslugi.ru/auto', '/assets/img/services/gu-avto.svg'),
    ('gosuslugi', 'Общественные голосования', 'Участвуйте в жизни региона: голосуйте, делитесь мнением', 'https://pos.gosuslugi.ru/lkp/', '/assets/img/services/gu-golosovaniya.svg'),
    ('gosuslugi', 'Оплата штрафов и госпошлин', 'Оплачивайте штрафы и госпошлины онлайн', 'https://www.gosuslugi.ru/pay', '/assets/img/services/gu-shtrafy.svg'),
    ('delivery', 'MYBOX', 'Доставка суши, роллов, лапши вок, боулов', 'https://mybox.ru/korenovsk', '/assets/img/services/mybox.svg'),
    ('delivery', 'Сушибокс', 'Магазин японской кухни', 'https://sushibox.one/', '/assets/img/services/sushibox.webp'),
    ('delivery', 'Сицилия', 'Доставка пиццы', 'https://korenovsk.pizza-sicilia.ru/', '/assets/img/services/sicilia.webp'),
    ('delivery', 'Пятёрочка', 'Доставка продуктов из «Пятёрочки» за 45 минут', 'https://dostavka.5ka.ru/', '/assets/img/services/pyaterochka.webp'),
    ('delivery', 'Магнит', 'Заказывайте в приложении Магнит', 'https://redirect.appmetrica.yandex.com/serve/677272508377869147', '/assets/img/services/magnit.svg'),
    ('delivery', 'Яндекс Еда', 'Доставка еды и продуктов от 30 минут', 'https://eda.yandex.ru/korenovsk?shippingType=delivery', '/assets/img/services/yandex-eda.webp'),
    ('social', 'ВКонтакте', '', 'https://vk.com/tvoykorenovskweb', '/assets/img/social/vk.svg'),
    ('social', 'Telegram-канал', '', 'https://t.me/tvoykorenovsk', '/assets/img/social/tg.svg'),
    ('social', 'Сообщество города в WhatsApp', '', 'https://chat.whatsapp.com/EB8iJYzwM36BtEM2iCLdmM', '/assets/img/social/whatsapp.svg'),
    ('useful', 'Обратиться к главе Кореновского городского поселения', '', 'https://korenovsk-gorod.ru/feedback/index.php', ''),
    ('useful', 'Виртуальная приёмная главы муниципального образования Кореновский район', '', 'https://korenovsk.ru/internet-reception/', ''),
    ('orgs', 'Кореновский районный совет ветеранов', '', 'https://korenovskiy-sovet-veteranov.ru/', ''),
    ('orgs', 'Кореновский городской парк культуры и отдыха', '', 'https://gorpark-kor.ru/', ''),
    ('orgs', 'Кореновский политехнический техникум', '', 'https://gou-npo-pu-25.ucoz.ru/', ''),
    ('orgs', 'Кореновский районный центр народной культуры и досуга', '', 'https://krcnkd.krd.muzkult.ru/', ''),
]

PAGES = []  # базовые страницы («О редакции», «Контакты», «Принципы») создаёт BasePages из database/pages/*.html

SETTINGS = {
    'site_name': 'Твой Кореновск',
    'site_tagline': 'Независимое издание Кореновска',
    'site_description': 'Независимое издание Кореновска: новости города и района, афиша, погода и радио. Без рекламы и без сбора данных о читателях.',
    'site_locale': 'ru_RU',
    'contact_email': '', 'contact_phone': '', 'org_name': 'Редакция', 'smi_reg': '', 'age_mark': '',
    'city_name': 'Кореновск', 'city_lat': '45.4647', 'city_lon': '39.4492', 'city_tz': 'Europe/Moscow',
    'radio_name': 'Кореновск FM', 'radio_url': 'https://s0.radioheart.ru:8000/RH41064',
    'yandex_verification': 'e8eb70726eb51479', 'google_verification': '', 'google_meta': '', 'bing_verification': '',
    'cinema_widget_url': 'https://kinowidget.kinoplan.ru/afisha/2036', 'cinema_site_url': 'https://xn--80ackljdsictdrcd6mvb.xn--p1ai/',
    'home_news_count': '12', 'news_per_page': '12',
    'robots_block_ai': '0', 'robots_extra': '', 'noindex_site': '0',
    'indexnow_enabled': '1', 'indexnow_key': '', 'require_2fa': '0', 'weather_api_key': '',
    'footer_about': 'Кореновск — город в Краснодарском крае, административный центр Кореновского района. Население — 42 354 человека (2020). Город расположен на реке Бейсужек Левый, в 65 км северо-восточнее Краснодара.',
    'db_version': '2',
}

# ---------------------------------------------------------------- запись SQL
sql = ['-- Начальные данные «Твой Кореновск» (сгенерировано tools/migrate_static.py)',
       'SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS = 0;', '']
sql.append(ins('categories', ['id', 'slug', 'name', 'description', 'sort_order'], [(c[0], c[1], c[2], c[3], c[0] * 10) for c in CATS]))
sql.append(ins('media', ['id', 'path', 'ext', 'width', 'height', 'size', 'alt', 'caption', 'credit', 'widths', 'created_at'], media_rows))
sql.append(ins('news', ['id', 'category_id', 'author_id', 'title', 'slug', 'excerpt', 'body', 'body_text', 'cover_media_id', 'status', 'published_at',
                        'is_featured', 'is_pinned', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'source_name', 'source_url', 'created_at', 'updated_at'], news_rows))
sql.append(ins('news_media', ['news_id', 'media_id', 'sort_order'], nm_rows))
sql.append(ins('tags', ['id', 'slug', 'name'], tag_rows))
sql.append(ins('news_tags', ['news_id', 'tag_id'], nt_rows))
sql.append(ins('links', ['group_key', 'title', 'description', 'url', 'icon', 'sort_order', 'new_tab'], [(l[0], l[1], l[2], l[3], l[4], i * 10, 1) for i, l in enumerate(LINKS)]))
sql.append(ins('settings', ['k', 'v'], list(SETTINGS.items())))
sql.append('SET FOREIGN_KEY_CHECKS = 1;')
(ROOT / 'database' / 'seed.sql').write_text('\n'.join(sql), encoding='utf-8')
print(f'Готово: {len(news_rows)} статей, {len(media_rows)} изображений, {len(tag_rows)} тегов, {len(LINKS)} ссылок.')
