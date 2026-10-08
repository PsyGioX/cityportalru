#!/usr/bin/env python3
# CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
# Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
# Project: https://github.com/PsyGioX/cityportalru
"""Тесты 2FA на тестовом экземпляре (БД tk, пользователь tfa_t создаётся и удаляется): python3 tools/security_tests_2fa.py"""
import re, subprocess, requests, time
import os
B=os.environ.get('BASE','http://127.0.0.1:8080'); R=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sql=lambda q: subprocess.run(['mysql','-N',os.environ.get('DB','tk'),'-e',q],capture_output=True,text=True).stdout.strip()
php=lambda c: subprocess.run(['php','-r','require "'+R+'/app/bootstrap.php"; '+c],capture_output=True,text=True,cwd=R).stdout.strip()
csrf=lambda h: (re.search(r'name="_csrf" value="([a-f0-9]+)"',h) or [0,''])[1]
ok=lambda n,c: print(('PASS ' if c else 'FAIL ')+n)
# RFC 6238 тестовый вектор (SHA1, T=59с → шаг 1 → 94287082, 6 цифр = 287082)
ok('TOTP совпадает с вектором RFC 6238', php('echo App\\Core\\Totp::code("GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ", 1);')=='287082')
secret=php('echo App\\Core\\Totp::generateSecret();')
enc=php(f'echo App\\Core\\Security::encrypt("{secret}");')
ok('Секрет 2FA хранится зашифрованным (AES-256-GCM)', enc.startswith('enc1:') and secret not in enc)
hh=php('echo App\\Core\\Auth::hashPassword("Tfa-Test-Pass-2026-zz");')
plain,hashes=None,None
codes=php('[$p,$h]=App\\Core\\Auth::newRecoveryCodes(); echo json_encode([$p,$h]);')
import json; plain,hashes=json.loads(codes)
sql("DELETE FROM users WHERE username='tfa_t'")
sql(f"INSERT INTO users (username,email,display_name,password_hash,role,is_active,totp_secret,totp_enabled,recovery_codes,created_at,updated_at) VALUES ('tfa_t','tfa@t.ru','T','{hh}','admin',1,'{enc}',1,'{json.dumps(hashes)}',NOW(),NOW())")
def start():
    s=requests.Session(); r=s.get(B+'/admin/login')
    r=s.post(B+'/admin/login',data={'username':'tfa_t','password':'Tfa-Test-Pass-2026-zz','_csrf':csrf(r.text)},allow_redirects=False); return s,r
sql("DELETE FROM login_attempts")
s,r=start(); ok('Пароль верный → требуется второй фактор (редирект на /2fa)', r.status_code==302 and r.headers['Location'].endswith('/admin/2fa'))
ok('Без кода доступ к админке закрыт', s.get(B+'/admin',allow_redirects=False).status_code==302)
p=s.get(B+'/admin/2fa'); r=s.post(B+'/admin/2fa',data={'code':'000000','_csrf':csrf(p.text)},allow_redirects=False); ok('Неверный код отклонён', r.status_code==200 and 'Неверный код' in r.text)
step=int(time.time())//30
good=php(f'echo App\\Core\\Totp::code("{secret}", {step});')
p=s.get(B+'/admin/2fa'); r=s.post(B+'/admin/2fa',data={'code':good,'_csrf':csrf(p.text)},allow_redirects=False); ok('Верный TOTP-код → вход', r.status_code==302 and s.get(B+'/admin',allow_redirects=False).status_code==200)
s2,_=start(); p=s2.get(B+'/admin/2fa'); r=s2.post(B+'/admin/2fa',data={'code':good,'_csrf':csrf(p.text)},allow_redirects=False); ok('Повторное использование того же кода отклонено (replay)', r.status_code==200 and 'Неверный код' in r.text)
s3,_=start(); p=s3.get(B+'/admin/2fa'); r=s3.post(B+'/admin/2fa',data={'code':plain[0],'_csrf':csrf(p.text)},allow_redirects=False); ok('Резервный код работает', r.status_code==302)
s4,_=start(); p=s4.get(B+'/admin/2fa'); r=s4.post(B+'/admin/2fa',data={'code':plain[0],'_csrf':csrf(p.text)},allow_redirects=False); ok('Резервный код одноразовый', r.status_code==200)
s5,_=start()
for i in range(7):
    p=s5.get(B+'/admin/2fa'); r=s5.post(B+'/admin/2fa',data={'code':'%06d'%i,'_csrf':csrf(p.text)},allow_redirects=False)
ok('После 5 неверных кодов 2FA-сессия сбрасывается', r.status_code==302 and r.headers['Location'].endswith('/admin/login'))
# принудительная 2FA
sql("UPDATE users SET totp_enabled=0, totp_secret=NULL WHERE username='tfa_t'"); sql("INSERT INTO settings (k,v) VALUES ('require_2fa','1') ON DUPLICATE KEY UPDATE v='1'"); sql("DELETE FROM login_attempts")
s,r=start(); r=s.get(B+'/admin/news',allow_redirects=False); ok('require_2fa: без 2FA доступен только «Профиль»', r.status_code==302 and r.headers['Location'].endswith('/admin/profile') and s.get(B+'/admin/profile').status_code==200)
sql("INSERT INTO settings (k,v) VALUES ('require_2fa','0') ON DUPLICATE KEY UPDATE v='0'"); sql("DELETE FROM users WHERE username='tfa_t'"); sql("DELETE FROM login_attempts")
