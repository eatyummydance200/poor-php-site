#!/bin/bash
# Provision with outbound access, with the vulnerable service still stopped.
# Run from this repository as root, after downloading the official base image.
set -euo pipefail
task_root=/home/poor-php-lab-runtime
repo_root=$(cd -- "$(dirname -- "$0")/.." && pwd)
test "$(id -u)" = 0
test -f "$task_root/ubuntu-noble.img"
test ! -e "$task_root/golden.qcow2"
python3 "$repo_root/ops/build_seed.py" --output "$task_root/seed"
qemu-img create -f qcow2 -F qcow2 -b "$task_root/ubuntu-noble.img" "$task_root/golden.qcow2" 8G
if ! id poorphpvm >/dev/null 2>&1; then
  useradd --system --no-create-home --shell /usr/sbin/nologin poorphpvm
fi
chown -R poorphpvm:poorphpvm "$task_root"
chmod 0750 "$task_root"
systemd-run --unit=poorphp-provision --collect --service-type=exec \
  --property=User=poorphpvm --property=SupplementaryGroups=kvm \
  --property=NoNewPrivileges=yes --property=MemoryMax=1800M --property=CPUQuota=100% \
  qemu-system-x86_64 -enable-kvm -cpu host -m 1280 -smp 1 \
  -name poorphp-provision -smbios type=1,serial=provision \
  -drive "file=$task_root/golden.qcow2,format=qcow2,if=virtio" \
  -drive "file=$task_root/seed/seed.img,format=raw,if=virtio,readonly=on" \
  -netdev user,id=setup,ipv6=off -device virtio-net-pci,netdev=setup \
  -display none -monitor none -serial "file:$task_root/provision-serial.log" -no-reboot
echo 'Wait for POORPHP_GOLDEN_READY and VM poweroff before starting lab instances.'
