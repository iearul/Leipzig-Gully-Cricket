<?php
declare(strict_types=1);
// Standalone card layout for player login, sign-up and password pages. Set $pageTitle and $authLabel first.
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071712"><meta name="color-scheme" content="dark"><title><?= e($pageTitle) ?> · LGC</title><link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"></head><body class="login-page"><main class="surface login-card">
<a class="brand login-brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark"></span><span><strong>LGC</strong><small><?= e($authLabel) ?></small></span></a>
<?php if ($notice = take_flash()): ?><div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div><?php endif; ?>
