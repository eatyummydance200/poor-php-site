<?php
declare(strict_types=1);

$mode = getenv('LAB_MODE') ?: 'vulnerable';
if (!in_array($mode, ['vulnerable', 'patched'], true)) {
    throw new RuntimeException('Invalid LAB_MODE');
}
$patched = $mode === 'patched';
$dataDir = getenv('LAB_DATA_DIR') ?: '/var/lib/poorphp';
session_save_path($dataDir . '/sessions');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$db = new PDO('sqlite:' . $dataDir . '/site.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(bool $patched): void {
    if ($patched && !hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Invalid form token');
    }
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if ($patched) {
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; frame-ancestors 'none'; base-uri 'none'");
}
