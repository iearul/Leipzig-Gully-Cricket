<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$players = $pdo->query('SELECT * FROM players ORDER BY active DESC, name ASC')->fetchAll();
$ranked = ranking_by_player(getMonthlyRanking($pdo, date('Y-m')));
$pageTitle = 'Players';
$pageDescription = 'Meet the Leipzig Gully Cricket players and see their current monthly form.';
$activePage = 'players';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">The Grünau crew</p><h1>Players</h1><p>Different styles. One pitch. Meet the players behind every run, wicket and monthly ranking battle.</p></div><span class="pill"><?= count($players) ?> registered</span></div>
        <?php if ($players): ?>
            <label class="player-search"><span class="sr-only">Search players</span><input type="search" inputmode="search" placeholder="Search by name or nickname…" data-player-search></label>
            <div class="player-grid">
                <?php foreach ($players as $player): $form = $ranked[(int) $player['id']] ?? null; ?>
                    <a class="surface player-card" data-player-card href="<?= e(url('player.php?id=' . (int) $player['id'])) ?>">
                        <div class="player-photo"><?= avatar_html($player, 'avatar') ?><span class="player-rank"><?= $form ? '#' . (int) $form['rank'] : '—' ?></span></div>
                        <div class="player-card-body">
                            <h2><?= e($player['name']) ?></h2>
                            <p><?= e($player['nickname'] ?: ($player['active'] ? 'Active player' : 'Inactive player')) ?> <?php if (!$player['active']): ?><span class="pill inactive">Inactive</span><?php endif; ?></p>
                            <div class="card-stats">
                                <div><strong><?= (int) ($form['points'] ?? 0) ?></strong><span>Points</span></div><div><strong><?= (int) ($form['sessions'] ?? 0) ?></strong><span>Sessions</span></div><div><strong><?= (int) ($form['runs'] ?? 0) ?></strong><span>Runs</span></div><div><strong><?= (int) ($form['wickets'] ?? 0) ?></strong><span>Wickets</span></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="surface empty-state" data-search-empty hidden><h2>No player found</h2><p>Try a different name or nickname.</p></div>
        <?php else: ?>
            <div class="surface empty-state"><div class="empty-ball"></div><h2>No players yet</h2><p>The squad list is ready for its first name.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
