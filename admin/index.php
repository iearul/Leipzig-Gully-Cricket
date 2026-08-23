<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = db();
$error = '';
if (!is_admin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $now = time();
        $attempts = array_values(array_filter($_SESSION['login_attempts'] ?? [], static fn($time): bool => (int)$time > $now - 900));
        if (count($attempts) >= 5) $error = 'Too many login attempts. Please wait 15 minutes and try again.';
        else {
            $username = trim((string)($_POST['username'] ?? '')); $password = (string)($_POST['password'] ?? '');
            $statement = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1'); $statement->execute([$username]); $admin = $statement->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) { session_regenerate_id(true); $_SESSION['admin_id'] = (int)$admin['id']; $_SESSION['admin_username'] = $admin['username']; unset($_SESSION['login_attempts']); redirect('admin/'); }
            $attempts[] = $now; $_SESSION['login_attempts'] = $attempts; $error = 'The username or password is incorrect.'; usleep(350000);
        }
    }
    $hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    $notice = take_flash();
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071712"><title>Admin login · LGC</title><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>"></head><body class="login-page"><main class="surface login-card">
    <a class="brand login-brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark"></span><span><strong>LGC Admin</strong><small>Scoreboard control</small></span></a><h1>Welcome back</h1><p>Sign in to manage players, sessions and rankings.</p>
    <?php if ($notice): ?><div class="notice notice-<?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?><?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($hasAdmin): ?><form method="post"><?= csrf_field() ?><div class="field"><label for="username">Username</label><input id="username" name="username" required autocomplete="username" autofocus></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div><button class="btn btn-primary" type="submit">Log in</button></form><?php else: ?><div class="admin-empty"><h2>First-time setup</h2><p>Create the first administrator after importing the database.</p><a class="btn btn-primary" href="<?= e(url('setup.php')) ?>">Create administrator</a></div><?php endif; ?><div class="login-help"><a href="<?= e(url('index.php')) ?>">← Back to public website</a></div>
    </main></body></html><?php exit;
}

$month = date('Y-m'); $ranking = getMonthlyRanking($pdo, $month); $latest = get_latest_session($pdo);
$activePlayers = (int)$pdo->query('SELECT COUNT(*) FROM players WHERE active = 1')->fetchColumn();
$monthSessionsStatement = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE status = 'completed' AND session_date >= ? AND session_date < ?"); $monthSessionsStatement->execute([$month.'-01', shift_month($month,1).'-01']); $monthSessions = (int)$monthSessionsStatement->fetchColumn();
$pageTitle = 'Dashboard'; $adminPage = 'dashboard'; require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-titlebar"><div><p class="eyebrow">Scoreboard control</p><h1>Good to see you, <?= e($_SESSION['admin_username'] ?? 'Admin') ?>.</h1><p>Here is the current state of play.</p></div><div class="admin-actions"><a class="btn btn-primary" href="<?= e(url('admin/session-form.php')) ?>">+ Add session</a></div></div>
<div class="admin-grid"><div class="surface admin-metric"><span>Monthly leader</span><strong><?= e($ranking[0]['name'] ?? '—') ?></strong></div><div class="surface admin-metric"><span>Leader points</span><strong><?= (int)($ranking[0]['points'] ?? 0) ?></strong></div><div class="surface admin-metric"><span>Active players</span><strong><?= $activePlayers ?></strong></div><div class="surface admin-metric"><span>Sessions this month</span><strong><?= $monthSessions ?></strong></div></div>
<div class="admin-columns"><section class="surface admin-panel"><div class="admin-panel-head"><h2>Current Top 3</h2><a class="text-link" href="<?= e(url('rankings.php')) ?>">Public ranking ↗</a></div><?php if ($ranking): ?><div class="admin-list"><?php foreach (array_slice($ranking,0,3) as $row): ?><a class="admin-list-row" href="<?= e(url('player.php?id='.(int)$row['id'])) ?>"><div class="admin-player"><?= avatar_html($row,'avatar') ?><span><h3>#<?= (int)$row['rank'] ?> <?= e($row['name']) ?></h3><p><?= (int)$row['runs'] ?> runs · <?= (int)$row['wickets'] ?> wickets</p></span></div><div class="admin-list-value"><strong><?= (int)$row['points'] ?></strong><span>points</span></div></a><?php endforeach; ?></div><?php else: ?><div class="admin-empty"><p>No eligible ranking data this month.</p></div><?php endif; ?></section>
<section class="surface admin-panel"><div class="admin-panel-head"><h2>Latest session</h2><a class="text-link" href="<?= e(url('admin/sessions.php')) ?>">Manage</a></div><?php if ($latest): ?><div class="admin-list"><a class="admin-list-row" href="<?= e(url('admin/session-form.php?id='.(int)$latest['id'])) ?>"><div><h3><?= e(format_date($latest['session_date'])) ?></h3><p><?= e($latest['location']) ?> · <?= count($latest['eligible_players']) ?> eligible</p></div><div class="admin-list-value"><strong>Edit</strong><span>scorecard</span></div></a></div><?php else: ?><div class="admin-empty"><h2>No sessions yet</h2><p>Add the first cricket day and its performances.</p><a class="btn btn-primary" href="<?= e(url('admin/session-form.php')) ?>">Add first session</a></div><?php endif; ?></section></div>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
