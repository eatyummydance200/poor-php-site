# Private VM operations

## Build once

Host prerequisites: KVM and the Ubuntu packages `qemu-system-x86 qemu-utils
cloud-image-utils`. Install them only on an authorized host. No host PHP is needed.

Download the Ubuntu Noble amd64 cloud image and its SHA256SUMS from
https://cloud-images.ubuntu.com/noble/current/ into
`/home/poor-php-lab-runtime/`, naming the image `ubuntu-noble.img`. Verify its
checksum against the official manifest before use. The mutable `current` URL is
not a reproducibility pin: preserve the manifest, image hash and guest package list.

Run `sudo bash ops/provision.sh`. This creates a dedicated non-login VM account
and one 8GiB virtual disk (sparse). Provisioning temporarily has outbound network
access to official package mirrors. **The PHP service is never started at this
stage**, and no web/SSH host forward is configured. Wait until the serial log
contains `POORPHP_GOLDEN_READY`, the VM has powered off, and no cloud-init error
is present. Mark the golden disk read-only before cloning.

Run `sudo python3 ops/labctl.py start all`. Three independent overlays use 1 vCPU
and 1GiB guest RAM each (host process MemoryMax 1400MiB each). No host firewall,
Tailscale Funnel, Kubernetes or existing container configuration is changed.
Runtime instances have outbound network disabled by QEMU user networking,
including access to the host and LAN; IPv6 is disabled on that virtual network.
The only configured host forward is a loopback TCP listener into guest port 8080.
The network setting follows the [QEMU user-network documentation](https://www.qemu.org/docs/master/system/invocation.html).
The host VM account can write lab overlays but not application/operating data.
Isolation is not a guarantee against unknown guest/hypervisor vulnerabilities.

## Laptop access

Use your existing SSH identity and server Tailscale address:

```sh
ssh -N -o ExitOnForwardFailure=yes \
  -L 127.0.0.1:18880:127.0.0.1:18880 \
  -L 127.0.0.1:18881:127.0.0.1:18881 \
  -L 127.0.0.1:18882:127.0.0.1:18882 USER@SERVER_TAILSCALE_ADDRESS
```

Open http://127.0.0.1:18880 for A, :18882 for B, :18881 for patched.
A tool running in another container or cloud cannot use the laptop loopback URL
automatically. Put a constrained runner on the laptop with a dedicated per-model
allowlist. Never hand models an unrestricted SSH session or administrative key.

## Stop and reset

```sh
sudo python3 ops/labctl.py status all
sudo python3 ops/labctl.py stop all
# Destructive to the selected lab instance only: save evidence first.
sudo python3 ops/labctl.py reset a
```

Reset stops the VM, deletes only its named overlay and clones the clean golden
disk again. VM services are transient: host reboot does not restart them. Resume
with `start all`. The golden disk and official base image must remain unchanged.
Do not reprovision over an existing golden disk. To update, preserve evidence,
stop all lab VMs and build a new version in a new runtime directory first.

## Logs and monitoring

Host QEMU service logs: `journalctl -u poorphp-a` (or b/patched).
Boot logs: `/home/poor-php-lab-runtime/*-serial.log`. These are not complete HTTP
evidence. Collect requests/responses with the laptop runner; VM PHP access/error
logs stay in its journal. Do not install production monitoring credentials in a
target. Existing host monitoring can observe resource use without guest access.
No exploit payloads or attacks are executed by deployment or smoke checks.
