<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = db();
if (current_player()) redirect('account/');
$form = ['name' => '', 'nickname' => '', 'email' => '', 'batting_style' => '', 'bowling_style' => '', 'note' => ''];
$error = '';
$sent = !empty($_SESSION['registration_sent']);
unset($_SESSION['registration_sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($form as $field => $value) {
        $form[$field] = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
    }
    $form['email'] = normalize_email($form['email']);
    $password = (string) ($_POST['password'] ?? '');
    // Bots fill the hidden "website" field; pretend success so they move on.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $_SESSION['registration_sent'] = true;
        redirect('account/register.php');
    }
    $existingLogin = false;
    if (valid_email($form['email'])) {
        $existing = $pdo->prepare('SELECT password_hash IS NOT NULL FROM players WHERE email = ?');
        $existing->execute([$form['email']]);
        $existingLogin = $existing->fetchColumn();
    }

    if (!throttle_allows('player_register', 3, 3600)) $error = 'Too many sign-ups from this browser. Please try again later.';
    elseif ($form['name'] === '' || !text_fits($form['name'], 120)) $error = 'Enter your full name (up to 120 characters).';
    elseif (!valid_email($form['email'])) $error = 'Enter a valid email address.';
    elseif ($existingLogin !== false) $error = (int) $existingLogin === 1 ? 'You already have a login. Log in or reset your password instead.' : 'You are already in the squad. Use "Forgot password" on the login page to set your password.';
    elseif (email_in_use($pdo, $form['email'])) $error = 'A sign-up with this email is already waiting for approval.';
    elseif ($problem = password_problem($password, (string) ($_POST['password_confirmation'] ?? ''))) $error = $problem;
    elseif (!text_fits($form['nickname'], 80) || !text_fits($form['bowling_style'], 80)) $error = 'Keep nickname and bowling style under 80 characters.';
    elseif ($form['batting_style'] !== '' && !in_array($form['batting_style'], batting_style_options(), true)) $error = 'Choose a batting style from the list.';
    elseif (!text_fits($form['note'], 500)) $error = 'Keep the note for the admin under 500 characters.';
    elseif ((int) $pdo->query('SELECT COUNT(*) FROM player_registrations')->fetchColumn() >= MAX_PENDING_REGISTRATIONS) $error = 'Sign-ups are paused for now. Please ask an admin.';
    else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            try { $photo = save_player_photo($_FILES['photo']); } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
        }
        if ($error === '') {
            try {
                $pdo->prepare('INSERT INTO player_registrations (name, nickname, email, password_hash, photo, batting_style, bowling_style, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$form['name'], $form['nickname'] ?: null, $form['email'], password_hash($password, PASSWORD_DEFAULT), $photo, $form['batting_style'] ?: null, $form['bowling_style'] ?: null, $form['note'] ?: null]);
                throttle_hit('player_register');
                $_SESSION['registration_sent'] = true;
                redirect('account/register.php');
            } catch (PDOException $exception) {
                delete_uploaded_photo($photo);
                if ($exception->getCode() !== '23000') throw $exception;
                $error = 'A sign-up with this email is already waiting for approval.';
            }
        }
    }
}
$pageTitle = 'Join LGC'; $authLabel = 'New player'; require dirname(__DIR__) . '/includes/auth-header.php';
?>
<?php if ($sent): ?>
<h1>Thanks!</h1><p>An admin will check your profile soon. You can log in as soon as it is approved.</p>
<a class="btn btn-primary" href="<?= e(url('account/')) ?>">Go to player login</a>
<?php else: ?>
<h1>Join LGC</h1><p>Create your player profile. An admin approves it before you appear on the site.</p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="field"><label for="name">Full name *</label><input id="name" name="name" required maxlength="120" autocomplete="name" value="<?= e($form['name']) ?>"></div>
<div class="field"><label for="email">Email *</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($form['email']) ?>"></div>
<div class="field"><label for="password">Password *</label><input id="password" name="password" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password"><small>At least <?= PLAYER_PASSWORD_MIN ?> characters.</small></div>
<div class="field"><label for="password_confirmation">Confirm password *</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password"></div>
<div class="field"><label for="nickname">Nickname</label><input id="nickname" name="nickname" maxlength="80" value="<?= e($form['nickname']) ?>"></div>
<div class="field"><label for="batting_style">Batting style</label><select id="batting_style" name="batting_style"><option value="">Not specified</option><?php foreach (batting_style_options() as $option): ?><option <?= $form['batting_style'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="bowling_style">Bowling style</label><input id="bowling_style" name="bowling_style" maxlength="80" placeholder="e.g. Right-arm medium" value="<?= e($form['bowling_style']) ?>"></div>
<div class="field"><label for="photo">Photo</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>Optional. JPG, PNG or WebP, maximum 5 MB.</small></div>
<div class="field"><label for="note">Note for the admin</label><textarea id="note" name="note" maxlength="500" placeholder="e.g. who brought you along, which days you play"><?= e($form['note']) ?></textarea></div>
<div class="field honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<button class="btn btn-primary" type="submit">Send for approval</button></form>
<div class="login-help">Already approved? <a href="<?= e(url('account/')) ?>">Log in</a></div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; ?>
