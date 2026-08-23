<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$sessions = $pdo->query(
    "SELECT s.*, COUNT(CASE WHEN sp.excluded = 0 THEN 1 END) AS player_count,
        (SELECT p.name FROM session_players x INNER JOIN players p ON p.id = x.player_id WHERE x.session_id = s.id AND x.excluded = 0 ORDER BY x.calculated_points DESC, p.name ASC LIMIT 1) AS top_player,
        (SELECT x.calculated_points FROM session_players x WHERE x.session_id = s.id AND x.excluded = 0 ORDER BY x.calculated_points DESC LIMIT 1) AS top_points
     FROM sessions s LEFT JOIN session_players sp ON sp.session_id = s.id
     WHERE s.status = 'completed' GROUP BY s.id ORDER BY s.session_date DESC, s.id DESC"
)->fetchAll();
$pageTitle = 'Sessions'; $pageDescription = 'Browse every Leipzig Gully Cricket session and individual scorecard.'; $activePage = 'sessions';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="shell">
    <div class="section-head"><div><p class="eyebrow">Every day counts</p><h1>Sessions</h1><p>Scorecards from casual cricket days in Grünau, with every eligible performance kept on the record.</p></div><span class="pill"><?= count($sessions) ?> played</span></div>
    <?php if ($sessions): ?><div class="session-list">
        <?php foreach ($sessions as $session): $date = new DateTimeImmutable($session['session_date']); ?>
            <a class="surface session-card" href="<?= e(url('session.php?id='.(int)$session['id'])) ?>">
                <div class="date-block"><strong><?= e($date->format('d')) ?></strong><span><?= e($date->format('M Y')) ?></span></div>
                <div><h2><?= e($session['title'] ?: $session['location']) ?></h2><p><?= e($session['location']) ?> · <?= (int)$session['player_count'] ?> eligible players</p></div>
                <div class="session-score"><strong><?= (int)($session['top_points'] ?? 0) ?> pts</strong><span><?= e($session['top_player'] ?: 'No top performer') ?></span></div>
            </a>
        <?php endforeach; ?>
    </div><?php else: ?><div class="surface empty-state"><div class="empty-ball"></div><h2>No cricket session recorded yet</h2><p>The first battle in Grünau is still waiting.</p></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
