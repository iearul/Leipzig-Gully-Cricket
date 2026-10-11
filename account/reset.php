<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
header('Referrer-Policy: no-referrer');
$pdo = db();
$token = is_string($_POST['token'] ?? null) ? $_POST['token'] : (is_string($_GET['token'] ?? null) ? $_GET['token'] : '');
$link = find_password_link($pdo, $token);
$error = '';

if ($link && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if ($problem = password_problem($password, (string) ($_POST['password_confirmation'] ?? ''))) $error = $problem;
    else {
        $hash = set_player_password($pdo, (int) $link['id'], $password);
        log_in_player($pdo, ['id' => $link['id'], 'password_hash' => $hash]);
        flash('success', 'Password saved. Welcome to your player profile.');
        redirect('account/');
    }
}
$pageTitle = 'Choose password'; $authLabel = 'Player login'; require dirname(__DIR__) . '/includes/auth-header.php';
?>
<?php if (!$link): ?>
<h1>Link expired</h1><p>This password link has expired or was already used.</p>
<a class="btn btn-primary" href="<?= e(url('account/forgot.php')) ?>">Get a new link</a>
<?php else: ?>
<h1>Choose a password</h1><p>For <?= e($link['name']) ?> · <?= e($link['email']) ?></p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="username" value="<?= e($link['email']) ?>" autocomplete="username">
<div class="field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password" autofocus><small>At least <?= PLAYER_PASSWORD_MIN ?> characters.</small></div>
<div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password"></div>
<button class="btn btn-primary" type="submit">Save password</button></form>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
