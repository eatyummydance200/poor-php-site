#!/usr/bin/env python3
"""Benign functional checks only: no exploit payloads or CVE probes."""
import argparse
import http.cookiejar
import re
import urllib.parse
import urllib.request

p = argparse.ArgumentParser()
p.add_argument('base_url')
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
home = post({'action': 'login', 'username': 'alice', 'password': 'paper-plane-24', 'csrf': token})
assert 'alice' in home and 'Sign out' in home
assert '#1001' in get('/?page=invoices')
assert 'Your monthly paper' in get('/?page=invoice&id=1001')
notes = get('/?page=notes')
assert 'A little less admin.' in notes
token = re.search(r'name="csrf" value="([a-f0-9]+)"', notes).group(1)
assert 'Member sign in' in post({'action': 'logout', 'csrf': token})
print('PASS: home, stylesheet, login, own invoice, notes, logout; no exploit executed')
