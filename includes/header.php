<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? APP_TAGLINE;
$activePage = $activePage ?? '';
$extraStyles = $extraStyles ?? [];
$extraScripts = $extraScripts ?? [];
$signedInPlayer = current_player();
$navItems = [
    'home' => ['Home', 'index.php'],
    'players' => ['Players', 'players.php'],
    'rankings' => ['Rankings', 'rankings.php'],
    'sessions' => ['Sessions', 'sessions.php'],
    'history' => ['Hall of Fame', 'history.php'],
    'about' => ['About', 'about.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#071712">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="color-scheme" content="dark">
    <title><?= e($pageTitle) ?> · LGC</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/images/lgc-app-icon-192.png')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <?php foreach ($extraStyles as $style): ?><link rel="stylesheet" href="<?= e(asset($style)) ?>"><?php endforeach; ?>
    <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?php foreach ($extraScripts as $script): ?><script defer src="<?= e(asset($script)) ?>"></script><?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="Leipzig Gully Cricket home">
            <span class="brand-mark" aria-hidden="true"><i></i></span>
            <span><strong>LGC</strong><small>Leipzig Gully Cricket</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">
            <span></span><span></span><span></span><span class="sr-only">Toggle menu</span>
        </button>
        <nav id="main-navigation" class="desktop-nav" aria-label="Main navigation">
            <?php foreach ($navItems as $key => [$label, $path]): ?>
                <a class="<?= $activePage === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="account-link<?= $activePage === 'account' ? ' is-active' : '' ?>" href="<?= e(url('account/')) ?>"><?= $signedInPlayer ? 'My profile' : 'Player login' ?></a>
        <a class="admin-link" href="<?= e(url('admin/')) ?>">Admin</a>
    </div>
</header>
<main id="main-content">
