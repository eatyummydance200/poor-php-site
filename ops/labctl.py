#!/usr/bin/env python3
"""Start, stop, reset and report the three isolated VMs. Run as root."""
import argparse
import json
import os
import pathlib
import pwd
import socket
import subprocess

ROOT = pathlib.Path('/home/poor-php-lab-runtime')
INSTANCES = {'a': (18880, 'vulnerable'), 'b': (18882, 'vulnerable'), 'patched': (18881, 'patched')}

def run(*args):
    return subprocess.run(args, check=True)

def active(name):
    return subprocess.run(['systemctl', 'is-active', '--quiet', f'poorphp-{name}.service']).returncode == 0

def start(name):
    port, mode = INSTANCES[name]
    if active('provision'):
        raise SystemExit('Provisioning is still active. Wait for clean poweroff.')
    if 'POORPHP_GOLDEN_READY' not in (ROOT / 'provision-serial.log').read_text(errors='replace'):
        raise SystemExit('Golden image has not passed preparation.')
    if (ROOT / 'golden.qcow2').stat().st_mode & 0o222:
        raise SystemExit('Mark the completed golden disk read-only before cloning.')
    if active(name):
        print(name, 'already running'); return
    # Never replace a live overlay. QEMU also takes an exclusive disk lock.
    image = ROOT / f'{name}.qcow2'
    if not image.exists():
        run('qemu-img', 'create', '-f', 'qcow2', '-F', 'qcow2', '-b', str(ROOT / 'golden.qcow2'), str(image))
    account = pwd.getpwnam('poorphpvm')
    os.chown(image, account.pw_uid, account.pw_gid)
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', port))
    run('systemd-run', f'--unit=poorphp-{name}', '--collect', '--service-type=exec',
        '--property=User=poorphpvm', '--property=SupplementaryGroups=kvm',
        '--property=NoNewPrivileges=yes', '--property=ProtectSystem=strict',
        '--property=ProtectHome=read-only', f'--property=ReadWritePaths={ROOT}',
        '--property=PrivateTmp=yes', '--property=RestrictSUIDSGID=yes',
        '--property=MemoryMax=1400M', '--property=CPUQuota=100%', '--property=TasksMax=40',
        '--property=IOWeight=10', '--property=UMask=0077',
        'qemu-system-x86_64', '-enable-kvm', '-cpu', 'host', '-m', '1024', '-smp', '1',
        '-name', f'poorphp-{name}', '-smbios', f'type=1,serial={mode}',
        '-drive', f'file={image},format=qcow2,if=virtio',
        '-netdev', f'user,id=lab,restrict=on,ipv6=off,hostfwd=tcp:127.0.0.1:{port}-:8080',
        '-device', 'virtio-net-pci,netdev=lab', '-display', 'none', '-monitor', 'none',
        '-serial', f'file:{ROOT}/{name}-serial.log', '-no-reboot')
    print(f'{name}: http://127.0.0.1:{port}')

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('action', choices=['start', 'stop', 'reset', 'status'])
parser.add_argument('instance', choices=[*INSTANCES, 'all'])
args = parser.parse_args()
if os.geteuid() != 0: raise SystemExit('Run with sudo.')
names = list(INSTANCES) if args.instance == 'all' else [args.instance]
for name in names:
    if args.action in ('stop', 'reset') and active(name):
        run('systemctl', 'stop', f'poorphp-{name}.service')
    if args.action == 'reset':
        # Deliberately restricted to this manager's fixed instance names and paths.
        (ROOT / f'{name}.qcow2').unlink(missing_ok=True)
    if args.action in ('start', 'reset'): start(name)
    if args.action == 'status':
        print(json.dumps({'instance': name, 'active': active(name), 'port': INSTANCES[name][0]}))
