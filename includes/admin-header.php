<?php
declare(strict_types=1);
require_admin();
$pageTitle = $pageTitle ?? 'Admin';
$adminPage = $adminPage ?? 'dashboard';
$adminNav = [
    'dashboard' => ['Dashboard', 'admin/'],
    'players' => ['Players', 'admin/players.php'],
    'sessions' => ['Sessions', 'admin/sessions.php'],
    'draw' => ['Draw', 'admin/draw.php'],
    'approvals' => ['Approvals', 'admin/approvals.php'],
    'settings' => ['Point settings', 'admin/settings.php'],
];
$pendingApprovals = pending_approval_count(db());
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#071712"><meta name="color-scheme" content="dark">
<title><?= e($pageTitle) ?> · LGC Admin</title><link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>"><link rel="apple-touch-icon" href="<?= e(url('assets/images/lgc-app-icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<script defer src="<?= e(asset('assets/js/admin.js')) ?>"></script></head><body class="admin-body">
<a class="skip-link" href="#admin-main">Skip to content</a>
<header class="admin-header"><div class="admin-shell admin-header-inner">
    <a class="brand" href="<?= e(url('admin/')) ?>"><span class="brand-mark" aria-hidden="true"><i></i></span><span><strong>LGC Admin</strong><small>Scoreboard control</small></span></a>
    <button class="admin-menu-toggle" type="button" aria-controls="admin-navigation" aria-expanded="false"><span></span><span></span><span></span><span class="sr-only">Toggle admin menu</span></button>
    <nav class="admin-nav" id="admin-navigation" aria-label="Admin navigation"><?php foreach ($adminNav as $key => [$label,$path]): ?><a class="<?= $adminPage === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?><?php if ($key === 'approvals' && $pendingApprovals): ?> <span class="nav-count"><?= $pendingApprovals ?></span><?php endif; ?></a><?php endforeach; ?></nav>
    <a class="view-site" href="<?= e(url('index.php')) ?>">View site ↗</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="logout-button" type="submit" aria-label="Log out">Log out</button></form>
</div></header>
<main id="admin-main" class="admin-shell admin-main">
<?php if ($notice = take_flash()): ?><div class="notice notice-<?= e($notice['type']) ?>" role="status"><strong><?= e($notice['message']) ?></strong><?php if (!empty($notice['actions'])): ?><span class="notice-actions"><?php foreach ($notice['actions'] as $label => $path): ?><a href="<?= e(url($path)) ?>"><?= e($label) ?></a><?php endforeach; ?></span><?php endif; ?></div><?php endif; ?>
