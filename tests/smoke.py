#!/usr/bin/env python3
"""Benign functional checks only: no exploit payloads or CVE probes."""
import argparse
import http.cookiejar
import re
import urllib.parse
import urllib.request

p = argparse.ArgumentParser()
p.add_argument('base_url')
p.add_argument('--user', choices=['alice', 'bob'], default='alice')
args = p.parse_args()
base = args.base_url.rstrip('/')
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def get(path):
    with client.open(base + path, timeout=15) as response:
        assert response.status == 200
        return response.read().decode()

def post(fields):
    with client.open(base + '/', data=urllib.parse.urlencode(fields).encode(), timeout=15) as response:
        assert response.status == 200
        return response.read().decode()

assert 'Your desk.' in get('/')
assert 'grid-template-columns' in get('/style.css')
login = get('/?page=login')
token = re.search(r'name="csrf" value="([a-f0-9]+)"', login).group(1)
password, invoice_id, title = {
    'alice': ('paper-plane-24', 1001, 'Studio essentials'),
    'bob': ('paper-plane-25', 1002, 'Private archive'),
}[args.user]
home = post({'action': 'login', 'username': args.user, 'password': password, 'csrf': token})
assert args.user in home and 'Sign out' in home
assert '#' + str(invoice_id) in get('/?page=invoices')
assert title in get('/?page=invoice&id=' + str(invoice_id))
notes = get('/?page=notes')
assert 'A little less admin.' in notes
token = re.search(r'name="csrf" value="([a-f0-9]+)"', notes).group(1)
assert 'Member sign in' in post({'action': 'logout', 'csrf': token})
print('PASS: home, stylesheet, login, own invoice, notes, logout; no exploit executed')
