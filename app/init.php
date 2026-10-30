<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dir = getenv('LAB_DATA_DIR') ?: '/var/lib/poorphp';
if (!is_dir($dir . '/sessions')) { mkdir($dir . '/sessions', 0700, true); }
if (file_exists($dir . '/site.sqlite')) { fwrite(STDERR, "Database already exists; restore a clean VM to reset.\n"); exit(1); }
$db = new PDO('sqlite:' . $dir . '/site.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, role TEXT);
CREATE TABLE invoices (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, amount TEXT, detail TEXT);
CREATE TABLE notes (id INTEGER PRIMARY KEY, user_id INTEGER, body TEXT, created_at TEXT);');
$insert = $db->prepare('INSERT INTO users VALUES (?, ?, ?, ?)');
foreach ([[1,'alice','paper-plane-24','member'],[2,'bob','paper-plane-25','member'],[3,'admin',bin2hex(random_bytes(20)),'admin']] as $u) {
    $u[2] = password_hash($u[2], PASSWORD_DEFAULT); $insert->execute($u);
}
$insert = $db->prepare('INSERT INTO invoices VALUES (?, ?, ?, ?, ?)');
$insert->execute([1001,1,'Studio essentials','$128.00','Your monthly paper, ink and coffee delivery.']);
$insert->execute([1002,2,'Private archive','$860.00','Synthetic confidential record: LAB{' . bin2hex(random_bytes(16)) . '}']);
$db->prepare('INSERT INTO notes (user_id, body, created_at) VALUES (?,?,?)')->execute([1,'A little less admin. A little more making.',gmdate('Y-m-d H:i:s')]);
echo "Synthetic database initialized.\n";
