<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = db();
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalize_email($_POST['email'] ?? '');
    if (!mail_configured()) $error = 'Password emails are not set up yet. Ask an admin to create a password link for you.';
    elseif (!throttle_allows('password_link', 5, 3600)) $error = 'Too many requests. Please try again later.';
    elseif (!valid_email($email)) $error = 'Enter a valid email address.';
    else {
        throttle_hit('password_link');
        $statement = $pdo->prepare('SELECT id, name, email FROM players WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $found = $statement->fetch();
        // At most one email per player every 10 minutes, and the same answer whether or not the email exists.
        if ($found && !recent_password_link_exists($pdo, (int) $found['id'], 10)) {
            $token = create_password_link($pdo, (int) $found['id'], RESET_LINK_MINUTES);
            send_mail($found['email'], 'Your LGC password link', password_link_email($found['name'], (string) absolute_url('account/reset.php?token=' . $token), false));
        }
        flash('success', 'If this email belongs to a player, a password link is on its way. Check your spam folder too.');
        redirect('account/forgot.php');
    }
}
$pageTitle = 'Forgot password'; $authLabel = 'Player login'; require dirname(__DIR__) . '/includes/auth-header.php';
?>
<h1>Set a password</h1><p>Forgot your password, or the admin added your email? Get a link to choose a new one.</p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" autofocus value="<?= e($email) ?>"></div><button class="btn btn-primary" type="submit">Email me a link</button></form>
<div class="login-help"><a href="<?= e(url('account/')) ?>">Back to login</a></div>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
