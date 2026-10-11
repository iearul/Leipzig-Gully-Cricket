<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = db();
$player = current_player();
$error = '';

if (!$player) {
    $email = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = normalize_email($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!throttle_allows('player_login', 5, 900)) {
            $error = 'Too many login attempts. Please wait 15 minutes and try again.';
        } else {
            $statement = $pdo->prepare('SELECT id, password_hash FROM players WHERE email = ? AND password_hash IS NOT NULL LIMIT 1');
            $statement->execute([$email]);
            $found = $statement->fetch();
            if ($found && password_verify($password, $found['password_hash'])) {
                unset($_SESSION['throttle']['player_login']);
                log_in_player($pdo, $found);
                redirect('account/');
            }
            throttle_hit('player_login');
            $pending = $pdo->prepare('SELECT password_hash FROM player_registrations WHERE email = ? LIMIT 1');
            $pending->execute([$email]);
            $pendingHash = $pending->fetchColumn();
            $error = is_string($pendingHash) && password_verify($password, $pendingHash)
                ? 'Your profile is waiting for admin approval. You can log in once it is approved.'
                : 'The email or password is incorrect.';
            usleep(350000);
        }
    }
    $pageTitle = 'Player login'; $authLabel = 'Player login'; require dirname(__DIR__) . '/includes/auth-header.php';
    ?>
<h1>Player login</h1><p>Update your profile and send your match stats.</p>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" autofocus value="<?= e($email) ?>"></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div><button class="btn btn-primary" type="submit">Log in</button></form>
<div class="login-help"><a href="<?= e(url('account/forgot.php')) ?>">Forgot or never set a password?</a></div>
<div class="login-help">New here? <a href="<?= e(url('account/register.php')) ?>">Create your player profile</a></div>
<?php require dirname(__DIR__) . '/includes/auth-footer.php'; exit;
}

$profile = $player;
$passwordError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $player['password_hash'])) {
            $passwordError = 'Your current password is incorrect.';
        } elseif ($problem = password_problem($newPassword, (string) ($_POST['password_confirmation'] ?? ''))) {
            $passwordError = $problem;
        } else {
            $_SESSION['player_auth'] = player_session_fingerprint(set_player_password($pdo, (int) $player['id'], $newPassword));
            flash('success', 'Password changed.');
            redirect('account/');
        }
    } else {
        foreach (['nickname', 'batting_style', 'bowling_style', 'nationality_code', 'bio'] as $field) {
            $profile[$field] = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
        }
        $profile['nationality_code'] = strtoupper($profile['nationality_code']);
        if (!text_fits($profile['nickname'], 80) || !text_fits($profile['bowling_style'], 80)) $error = 'Keep nickname and bowling style under 80 characters.';
        elseif ($profile['batting_style'] !== '' && !in_array($profile['batting_style'], batting_style_options(), true)) $error = 'Choose a batting style from the list.';
        elseif ($profile['nationality_code'] !== '' && !preg_match('/^[A-Z]{2}$/', $profile['nationality_code'])) $error = 'Use a two-letter nationality code, such as BD or DE.';
        elseif (!text_fits($profile['bio'], 1000)) $error = 'Keep your bio under 1000 characters.';
        else {
            $photo = $player['photo'];
            if (!empty($_FILES['photo']['name'])) {
                try { $photo = save_player_photo($_FILES['photo']); } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
            }
            if ($error === '') {
                $pdo->prepare('UPDATE players SET nickname = ?, photo = ?, batting_style = ?, bowling_style = ?, nationality_code = ?, bio = ? WHERE id = ?')
                    ->execute([$profile['nickname'] ?: null, $photo, $profile['batting_style'] ?: null, $profile['bowling_style'] ?: null, $profile['nationality_code'] ?: null, $profile['bio'] ?: null, (int) $player['id']]);
                if ($photo !== $player['photo']) delete_uploaded_photo($player['photo']);
                flash('success', 'Profile saved.');
                redirect('account/');
            }
        }
    }
}

$pendingCount = $pdo->prepare("SELECT COUNT(*) FROM stat_submissions WHERE player_id = ? AND status = 'pending'");
$pendingCount->execute([(int) $player['id']]);
$pendingCount = (int) $pendingCount->fetchColumn();
$firstName = preg_split('/\s+/', trim($player['nickname'] ?: $player['name']))[0] ?? 'player';
$pageTitle = 'My profile'; $activePage = 'account'; $extraStyles = ['assets/css/admin.css']; $extraScripts = ['assets/js/admin.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-sm"><div class="shell account-shell">
<div class="admin-titlebar"><div><p class="eyebrow">My profile</p><h1>Hi, <?= e($firstName) ?>.</h1><?php if ($pendingCount): ?><p><?= $pendingCount ?> stat <?= $pendingCount === 1 ? 'entry is' : 'entries are' ?> waiting for admin approval.</p><?php endif; ?></div><div class="admin-actions"><a class="btn btn-primary" href="<?= e(url('account/stats.php')) ?>">Submit match stats</a><a class="btn btn-ghost" href="<?= e(url('player.php?id=' . (int) $player['id'])) ?>">Public profile ↗</a><form method="post" action="<?= e(url('account/logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Log out</button></form></div></div>
<?php if ($notice = take_flash()): ?><div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="surface form-card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><div class="form-grid">
<div class="field field-full"><label for="photo">Profile photo</label><div class="upload-preview"><?= avatar_html($profile, 'avatar') ?><div><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Maximum 5 MB.</small></div></div></div>
<div class="field"><label for="name">Full name</label><input id="name" value="<?= e($player['name']) ?>" disabled><small>Ask an admin to change your name.</small></div><div class="field"><label for="email">Login email</label><input id="email" value="<?= e($player['email']) ?>" disabled></div>
<div class="field"><label for="nickname">Nickname</label><input id="nickname" name="nickname" maxlength="80" value="<?= e($profile['nickname']) ?>"></div><div class="field"><label for="nationality_code">Nationality code</label><input id="nationality_code" name="nationality_code" maxlength="2" placeholder="e.g. BD" value="<?= e($profile['nationality_code']) ?>"></div>
<div class="field"><label for="batting_style">Batting style</label><select id="batting_style" name="batting_style"><option value="">Not specified</option><?php foreach (batting_style_options() as $option): ?><option <?= $profile['batting_style'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field"><label for="bowling_style">Bowling style</label><input id="bowling_style" name="bowling_style" maxlength="80" placeholder="e.g. Right-arm medium" value="<?= e($profile['bowling_style']) ?>"></div>
<div class="field field-full"><label for="bio">Short bio</label><textarea id="bio" name="bio" maxlength="1000"><?= e($profile['bio']) ?></textarea></div>
</div><div class="form-actions"><button class="btn btn-primary" type="submit">Save profile</button></div></form>

<form class="surface form-card account-password" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><h2>Change password</h2>
<?php if ($passwordError): ?><div class="notice notice-error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
<div class="form-grid"><div class="field field-full"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div><div class="field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password"></div><div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="<?= PLAYER_PASSWORD_MIN ?>" autocomplete="new-password"></div></div>
<div class="form-actions"><button class="btn btn-ghost" type="submit">Change password</button></div></form>
</div></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
