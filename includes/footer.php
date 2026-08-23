<?php declare(strict_types=1); ?>
</main>
<footer class="site-footer">
    <div class="shell footer-grid">
        <div>
            <a class="brand footer-brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true"><i></i></span>
                <span><strong>Leipzig Gully Cricket</strong></span>
            </a>
            <p>Cricket, friendship &amp; competition in Leipzig-Grünau.</p>
        </div>
        <div class="footer-note"><span>Since 2026</span><span>Made for the Grünau crew</span></div>
    </div>
</footer>
<nav class="mobile-dock" aria-label="Mobile navigation">
    <?php foreach (array_slice($navItems, 0, 5, true) as $key => [$label, $path]): ?>
        <a class="<?= $activePage === $key ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>">
            <span class="dock-icon dock-<?= e($key) ?>" aria-hidden="true"></span><small><?= e($label === 'Hall of Fame' ? 'Fame' : $label) ?></small>
        </a>
    <?php endforeach; ?>
</nav>
</body>
</html>
