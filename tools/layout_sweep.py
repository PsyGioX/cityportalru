#!/usr/bin/env python3
# CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
# Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
# Project: https://github.com/PsyGioX/cityportalru
"""Проверка адаптивности: ни на одной ширине (320–1920 px) нет горизонтальной прокрутки.  BASE=http://127.0.0.1:8080 DB=tk python3 tools/layout_sweep.py"""
import os, subprocess, sys
from playwright.sync_api import sync_playwright
B = os.environ.get('BASE', 'http://127.0.0.1:8080'); DB = os.environ.get('DB', 'tk')
slug = subprocess.run(['mysql', '-N', DB, '-e', 'SELECT slug FROM news ORDER BY id LIMIT 1'], capture_output=True, text=True).stdout.strip()
pages = ['/', '/afisha', '/news', '/radio', '/pogoda', '/kino', '/about', '/kontakty', '/principles', '/search?q=погода', '/nope'] + (['/news/' + slug] if slug else [])
bad = n = 0
with sync_playwright() as p:
    b = p.chromium.launch()
    for w in (320, 360, 390, 430, 600, 768, 900, 1024, 1100, 1179, 1181, 1200, 1280, 1440, 1920):
        pg = b.new_context(viewport={'width': w, 'height': 700}).new_page()
        for u in pages:
            pg.goto(B + u); pg.wait_for_timeout(80); n += 1
            ov = pg.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
            if ov > 0: bad += 1; print('ПЕРЕПОЛНЕНИЕ', w, u, ov)
    b.close()
print(f'Проверено {n} сочетаний, переполнений: {bad}'); sys.exit(1 if bad else 0)
