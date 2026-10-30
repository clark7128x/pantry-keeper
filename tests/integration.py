"""End-to-end PHP checks with isolated SQLite data; Python standard library only."""
import html
from contextlib import closing
import http.cookiejar
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = sys.argv[1] if len(sys.argv) > 1 else 'php'
checks = 0


def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1


class Browser:
    def __init__(self, base):
        self.base = base
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path='/', data=None):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.client.open(self.base + path, encoded, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode(), response.headers

    def token(self):
        return re.search(r'name="csrf" value="([a-f0-9]+)"', self.request()[1]).group(1)

    def post(self, **data):
        return self.request(data={'csrf': self.token(), **data})


with tempfile.TemporaryDirectory(prefix='pantry-http-') as directory:
    database = str(Path(directory) / 'test.sqlite')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with open(Path(directory) / 'server.log', 'w') as log:
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public'], cwd=ROOT,
                                  env={**os.environ, 'PANTRY_DB': database}, stdout=log, stderr=log)
        try:
            base = f'http://127.0.0.1:{port}'
            a, b = Browser(base), Browser(base)
            for _ in range(50):
                try:
                    a.request()
                    break
                except urllib.error.URLError:
                    time.sleep(0.1)
            status, page, headers = a.request()
            check(status == 200 and 'Увійти' in page, 'guest login page')
            check('frame-ancestors' in headers['Content-Security-Policy'], 'security headers')
            check(a.request('/config.local.php')[0] == 404, 'private config outside web root')
            check(a.request(data={'action': 'register'})[0] == 403, 'missing CSRF rejected')
            check(a.post(action='register', email='bad', password='short', name='')[0] == 422, 'registration validation')
            status, page, _ = a.post(action='register', email='a@example.invalid', password='Long-test-password-123', name='Alice')
            check(status == 200 and 'Мої продукти' in page, 'registration and session')
            data = dict(action='add', name='<script>alert(1)</script>', category='dairy', storage='fridge', expires_on='2028-02-29', request_token='a' * 32)
            status, page, _ = a.post(**data)
            check(status == 200 and '&lt;script&gt;' in page and '<script>alert(1)</script>' not in page, 'add and XSS escaping')
            a.post(**data)
            with closing(sqlite3.connect(database)) as db:
                check(db.execute('SELECT COUNT(*) FROM products').fetchone()[0] == 1, 'idempotent repeated add')
                product_id = db.execute('SELECT id FROM products').fetchone()[0]
                check(db.execute('SELECT password_hash FROM users').fetchone()[0].startswith('$2y$'), 'password hashed')
            check(a.post(**{**data, 'expires_on': '2027-02-29'})[0] == 422, 'invalid calendar date')
            check(a.request(data={'csrf': a.token(), 'action': 'add', 'name[]': 'bad'})[0] == 422, 'array inputs rejected without crash')
            status, page, _ = a.request(f'/?page=edit&id={product_id}')
            check(f'name="id" value="{product_id}"' in page, 'edit preserves numeric ID')
            check(a.post(**{**data, 'action': 'edit', 'id': product_id, 'name': 'Milk'})[0] == 200, 'edit succeeds')
            b.post(action='register', email='b@example.invalid', password='Other-test-password-123', name='Bob')
            check('Milk' not in b.request()[1], 'separate user inventory')
            check(b.request(f'/?page=edit&id={product_id}')[0] == 404, 'cross-user read rejected')
            check(b.post(**{**data, 'action': 'edit', 'id': product_id})[0] == 404, 'cross-user edit rejected')
            check(b.post(action='status', id=product_id, status='consumed')[0] == 404, 'cross-user status rejected')
            check(b.post(action='restore', id=product_id)[0] == 404, 'cross-user restore rejected')
            check(a.post(action='status', id=product_id, status='unknown')[0] == 422, 'status allowlist')
            a.post(action='status', id=product_id, status='consumed')
            check('Milk' not in a.request()[1] and 'Milk' in a.request('/?page=history')[1], 'consumed moves to history')
            a.post(action='restore', id=product_id)
            check('Milk' in a.request()[1], 'restore from history')
            for locale, title in [('en', 'My food'), ('de', 'Meine Lebensmittel'), ('uk', 'Мої продукти')]:
                status, page, _ = a.post(action='language', language=locale)
                check(status == 200 and f'lang="{locale}"' in page and title in page, 'locale ' + locale)
            check(a.post(action='language', language='bad')[0] == 422, 'unknown locale rejected')
            check(a.request('/push.php')[0] == 405, 'push rejects GET')
            check(a.request('/push.php', {'csrf': a.token()})[0] == 403, 'push requires CSRF header')
            a.post(action='logout')
            check('Milk' not in a.request()[1], 'logout removes access')
            check(a.post(action='login', email='a@example.invalid', password='wrong')[0] == 422, 'bad password rejected')
            status, page, _ = a.post(action='login', email='A@example.invalid', password='Long-test-password-123')
            check(status == 200 and 'Milk' in page, 'login restores persisted inventory')
            a.post(action='logout')
            check(a.post(action='register', email='a@example.invalid', password='Long-test-password-123', name='Duplicate')[0] == 422, 'duplicate registration rejected')
            for _ in range(11):
                result = a.post(action='login', email='throttle@example.invalid', password='bad')
            check(result[0] == 429, 'login throttling')
            print(f'{checks} HTTP checks passed.')
        finally:
            server.terminate()
            server.wait(timeout=10)
