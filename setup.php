<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$hasAdmin = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($hasAdmin) { http_response_code(403); $error = 'Setup is locked because an administrator already exists.'; }
    else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) $error = 'Use 3–80 letters, numbers, dots, dashes, or underscores for the username.';
        elseif (strlen($password) < 12) $error = 'Use a password with at least 12 characters.';
        elseif ($password !== $confirmation) $error = 'The password confirmation does not match.';
        else { $insert = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)'); $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]); flash('success', 'Administrator created. You can log in now.'); redirect('admin/'); }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071712"><title>Set up LGC</title><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>"></head><body class="login-page"><main class="surface login-card">
<a class="brand login-brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark"></span><span><strong>LGC</strong><small>First-time setup</small></span></a><h1>Create administrator</h1><p>This page locks automatically after the first account is created.</p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($hasAdmin): ?><div class="admin-empty"><h2>Setup complete</h2><p>An administrator already exists.</p><a class="btn btn-primary" href="<?= e(url('admin/')) ?>">Go to login</a></div><?php else: ?><form method="post"><?= csrf_field() ?><div class="field"><label for="username">Admin username</label><input id="username" name="username" required minlength="3" maxlength="80" autocomplete="username"></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div><button class="btn btn-primary" type="submit">Create administrator</button></form><?php endif; ?>
</main></body></html>
