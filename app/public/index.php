<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$page = (string)($_GET['page'] ?? 'home');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($patched);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'login') {
        $username = (string)($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($patched) {
            $q = $db->prepare('SELECT * FROM users WHERE username = ?'); $q->execute([$username]);
        } else {
            // Intentionally unsafe lab branch: never reuse in a real application.
            $q = $db->query("SELECT * FROM users WHERE username = '$username'");
        }
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']];
            redirect('/?page=home');
        }
        $error = 'Those details did not match. Try again.'; $page = 'login';
    } elseif ($action === 'logout') {
        $_SESSION = []; session_destroy(); redirect('/');
    } elseif ($action === 'note') {
        if (!isset($_SESSION['user'])) { redirect('/?page=login'); }
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '' && strlen($body) <= 4000) {
            $db->prepare('INSERT INTO notes (user_id, body, created_at) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],$body,gmdate('Y-m-d H:i:s')]);
        }
        redirect('/?page=notes');
    }
}
$user = $_SESSION['user'] ?? null;
if (!in_array($page, ['home','login','invoices','invoice','notes','admin'], true)) { http_response_code(404); $page='missing'; }
if (in_array($page, ['invoices','invoice','notes','admin'], true) && !$user) { redirect('/?page=login'); }
$invoice = null;
if ($page === 'invoice') {
    if ($patched) {
        $q = $db->prepare('SELECT * FROM invoices WHERE id = ? AND user_id = ?');
        $q->execute([(int)($_GET['id'] ?? 0),$user['id']]);
    } else {
        // Intentionally missing ownership check for the training target.
        $q = $db->prepare('SELECT * FROM invoices WHERE id = ?'); $q->execute([(int)($_GET['id'] ?? 0)]);
    }
    $invoice = $q->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) { http_response_code(404); }
}
if ($page === 'admin' && $user['role'] !== 'admin') { http_response_code(403); }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Paper & Cup — Studio desk</title><link rel="stylesheet" href="/style.css"></head>
<body><div class="shell"><aside><a class="brand" href="/">paper<span>&</span>cup<i>STUDIO DESK</i></a><p class="nav-label">YOUR WORKSPACE</p><nav>
<?php foreach (['home'=>['◈','Overview'],'invoices'=>['▤','Invoices'],'notes'=>['✎','Community notes']] as $key=>$item): ?>
<a class="<?= $page===$key?'active':'' ?>" href="/?page=<?= h($key) ?>"><span><?= h($item[0]) ?></span><?= h($item[1]) ?></a><?php endforeach; ?>
</nav><div class="aside-bottom"><span class="status-dot"></span> A quieter kind of workspace<p>Small tools for people<br>who make things.</p></div></aside>
<main><header><span>WORKSPACE <b>/</b> <?= h(ucfirst($page)) ?></span><div><?php if ($user): ?><span class="avatar"><?= h(strtoupper(substr($user['username'],0,1))) ?></span> <?= h($user['username']) ?><form class="inline" method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><button class="text-button" name="action" value="logout">Sign out</button></form><?php else: ?><a class="small-link" href="/?page=login">Member sign in ↗</a><?php endif; ?></div></header>
<section class="content">
<?php if ($page==='home'): ?>
<div class="eyebrow">LESS BUSYWORK, MORE GOOD WORK</div><h1>Your desk.<br>A little more <em>settled.</em></h1><p class="intro">Keep the everyday things in one place.<br>Invoices, studio notes, and room to think.</p>
<a class="button" href="/?page=<?= $user?'invoices':'login' ?>">Open your workspace <span>↗</span></a>
<div class="cards"><article class="card peach"><span class="card-number">01 / KEEP TRACK</span><div class="card-icon">▤</div><h2>Paperwork,<br>without the pile.</h2><p>Your orders and invoices, neatly filed.</p><a href="/?page=invoices">View invoices ↗</a></article><article class="card green"><span class="card-number">02 / STAY IN THE LOOP</span><div class="card-icon">✳</div><h2>Good ideas<br>travel together.</h2><p>A shared noticeboard for the studio.</p><a href="/?page=notes">Read community notes ↗</a></article></div>
<div class="footnote">MADE FOR THE EVERYDAY <span>Paper & Cup · Member workspace</span></div>
<?php elseif ($page==='login'): ?>
<div class="eyebrow">WELCOME BACK</div><h1>Pull up<br>a <em>chair.</em></h1><p class="intro">Sign in to your studio workspace.</p>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<form class="panel login" method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="login"><label>Username<input name="username" autocomplete="username" required maxlength="200"></label><label>Password<input name="password" type="password" autocomplete="current-password" required maxlength="200"></label><button class="button">Sign in ↗</button><p class="muted">Demo member: <strong>alice</strong> / <strong>paper-plane-24</strong></p></form>
<?php elseif ($page==='invoices'): ?>
<div class="eyebrow">YOUR PAPER TRAIL</div><h1>All neatly <em>filed.</em></h1><p class="intro">A record of the things that keep your studio going.</p><div class="panel"><table><thead><tr><th>Invoice</th><th>Description</th><th>Amount</th><th></th></tr></thead><tbody>
<?php $q=$db->prepare('SELECT * FROM invoices WHERE user_id=?');$q->execute([$user['id']]);foreach($q as $row): ?><tr><td>#<?= h($row['id']) ?></td><td><?= h($row['title']) ?></td><td><?= h($row['amount']) ?></td><td><a href="/?page=invoice&amp;id=<?= h($row['id']) ?>">Open ↗</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($page==='invoice'): ?>
<div class="eyebrow">INVOICE DETAILS</div><h1><?= $invoice?'The little <em>details.</em>':'Nothing here.' ?></h1><article class="panel"><?php if ($invoice): ?><p class="muted">INVOICE #<?= h($invoice['id']) ?></p><h2><?= h($invoice['title']) ?></h2><p><?= h($invoice['detail']) ?></p><p class="amount"><?= h($invoice['amount']) ?></p><?php else: ?><p>We could not find that invoice.</p><?php endif; ?></article>
<?php elseif ($page==='notes'): ?>
<div class="eyebrow">ON THE NOTICEBOARD</div><h1>A note for<br>the <em>room.</em></h1><p class="intro">Ideas, updates, and small things worth sharing.</p><form class="panel" method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="note"><label>Leave a note<textarea name="body" rows="3" maxlength="4000" required placeholder="What's on your mind?"></textarea></label><button class="button">Pin your note ↗</button></form><div class="notes">
<?php foreach($db->query('SELECT notes.*, users.username FROM notes JOIN users ON notes.user_id=users.id ORDER BY notes.id DESC LIMIT 50') as $note): ?><article class="panel"><p class="muted"><?= h($note['username']) ?> · <?= h($note['created_at']) ?> UTC</p><div class="note-body"><?= $patched ? nl2br(h($note['body'])) : $note['body'] ?></div></article><?php endforeach; ?></div>
<?php elseif ($page==='admin'): ?><h1>Studio <em>office.</em></h1><article class="panel"><?= $user['role']==='admin'?'Synthetic administrator workspace.':'This workspace is for administrators.' ?></article>
<?php else: ?><h1>A loose <em>page.</em></h1><p>We could not find that page.</p><?php endif; ?>
</section></main></div></body></html>
