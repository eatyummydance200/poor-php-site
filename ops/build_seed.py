#!/usr/bin/env python3
"""Build a cloud-init seed. No credentials or operator documents enter the VM."""
import argparse
import base64
import json
import pathlib
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
root = pathlib.Path(__file__).resolve().parents[1]
out = pathlib.Path(args.output).resolve()
out.mkdir(parents=True, exist_ok=True)
files = []

def add(path, content, permissions='0644'):
    files.append({'path': path, 'permissions': permissions, 'encoding': 'b64',
                  'content': base64.b64encode(content.encode()).decode()})

for path in sorted((root / 'app').rglob('*')):
    if path.is_file():
        add('/opt/poorphp/' + str(path.relative_to(root / 'app')), path.read_text())

add('/etc/systemd/system/poorphp-mode.service', '''[Unit]
Description=Select lab variant from the VM identity
Before=poorphp.service
[Service]
Type=oneshot
ExecStart=/usr/local/sbin/poorphp-mode
RemainAfterExit=yes
''')
add('/usr/local/sbin/poorphp-mode', '''#!/usr/bin/python3
from pathlib import Path
mode = Path('/sys/class/dmi/id/product_serial').read_text().strip()
if mode not in ('vulnerable', 'patched'):
    raise SystemExit('Invalid VM lab variant')
Path('/run/poorphp-mode').write_text('LAB_MODE=' + mode + '\\n')
''', '0755')
add('/etc/systemd/system/poorphp.service', '''[Unit]
Description=Paper and Cup PHP application lab
After=network.target poorphp-mode.service
Requires=poorphp-mode.service
ConditionPathExists=/var/lib/poorphp/site.sqlite
[Service]
User=www-data
Group=www-data
EnvironmentFile=/run/poorphp-mode
Environment=LAB_DATA_DIR=/var/lib/poorphp
Environment=PHP_CLI_SERVER_WORKERS=4
WorkingDirectory=/opt/poorphp/public
ExecStart=/usr/bin/php -d expose_php=Off -d display_errors=Off -d log_errors=On -d memory_limit=128M -d post_max_size=1M -d upload_max_filesize=1M -S 0.0.0.0:8080 -t /opt/poorphp/public
Restart=on-failure
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=strict
ProtectHome=yes
ReadWritePaths=/var/lib/poorphp
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX
CapabilityBoundingSet=
MemoryMax=384M
TasksMax=24
UMask=0077
StandardOutput=journal
StandardError=journal
[Install]
WantedBy=multi-user.target
''')
add('/usr/local/sbin/prepare-poorphp', '''#!/bin/sh
set -eu
for file in /opt/poorphp/*.php /opt/poorphp/public/*.php; do php -l "$file"; done
install -d -o www-data -g www-data -m 0700 /var/lib/poorphp /var/lib/poorphp/sessions
runuser -u www-data -- php /opt/poorphp/init.php
systemctl daemon-reload
systemctl enable poorphp.service
# The vulnerable service never starts while provisioning has outbound access.
systemctl disable ssh.service ssh.socket 2>/dev/null || true
touch /etc/cloud/cloud-init.disabled
dpkg-query -W php8.3-cli php8.3-sqlite3 > /var/lib/poorphp/build-packages.txt
echo 'POORPHP_GOLDEN_READY' > /dev/ttyS0
sync
''', '0755')
config = {'hostname': 'poorphp-lab', 'manage_etc_hosts': True,
          'ssh_pwauth': False, 'disable_root': True,
          'package_update': True, 'packages': ['php8.3-cli', 'php8.3-sqlite3', 'curl'],
          'write_files': files, 'runcmd': [['/usr/local/sbin/prepare-poorphp']],
          'power_state': {'mode': 'poweroff', 'timeout': 30, 'condition': True}}
(out / 'user-data').write_text('#cloud-config\n' + json.dumps(config))
(out / 'meta-data').write_text('instance-id: poorphp-golden-v1\nlocal-hostname: poorphp-lab\n')
subprocess.run(['cloud-localds', str(out / 'seed.img'), str(out / 'user-data'), str(out / 'meta-data')], check=True)
print(out / 'seed.img')
