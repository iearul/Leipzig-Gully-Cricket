<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$month = date('Y-m');
$ranking = with_rank_movement($pdo, getMonthlyRanking($pdo, $month), $month);
$leader = $ranking[0] ?? null;
$topThree = array_slice($ranking, 0, 3);
$latest = get_latest_session($pdo);
$totals = get_club_totals($pdo);
$draw = current_draw($pdo, true);
$drawRows = $draw ? draw_player_rows($pdo, $draw) : [];

$highestScorer = null;
$bestBowler = null;
if ($latest) {
    foreach ($latest['eligible_players'] as $performance) {
        if ($performance['runs'] !== null && ($highestScorer === null || (int) $performance['runs'] > (int) $highestScorer['runs'])) {
            $highestScorer = $performance;
        }
        if (($performance['wickets'] !== null || $performance['runs_conceded'] !== null) && ($bestBowler === null
            || (int) ($performance['wickets'] ?? 0) > (int) ($bestBowler['wickets'] ?? 0)
            || ((int) ($performance['wickets'] ?? 0) === (int) ($bestBowler['wickets'] ?? 0)
                && (int) ($performance['runs_conceded'] ?? PHP_INT_MAX) < (int) ($bestBowler['runs_conceded'] ?? PHP_INT_MAX)))) {
            $bestBowler = $performance;
        }
    }
}

$pageTitle = 'Home';
$pageDescription = 'Leipzig Gully Cricket — individual cricket statistics, monthly rankings and stories from Grünau.';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="shell hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">Born in Grünau · Leipzig</p>
            <h1>Gully <span>Cricket</span></h1>
            <p class="tagline">Casual Cricket. Serious Rankings.</p>
            <p>Friends on the field, rivals on the board. Every run, wicket and catch shapes the race for Leipzig’s monthly cricket crown.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= e(url('rankings.php')) ?>">View rankings <span aria-hidden="true">→</span></a>
                <a class="btn btn-ghost" href="<?= e(url('sessions.php')) ?>">Latest sessions</a>
            </div>
        </div>
        <?php if ($leader): ?>
            <article class="surface leader-card">
                <div class="leader-top"><span class="pill"><?= e(format_month($month)) ?> leader</span><span class="rank-badge">#1</span></div>
                <div class="leader-portrait"><?= avatar_html($leader, 'avatar') ?></div>
                <div class="leader-name"><h2><?= e($leader['name']) ?></h2><p><?= e($leader['nickname'] ? '“' . $leader['nickname'] . '”' : 'Setting the pace this month') ?></p></div>
                <div class="stat-row">
                    <div class="mini-stat"><strong data-count="<?= (int) $leader['points'] ?>"><?= (int) $leader['points'] ?></strong><span>Points</span></div>
                    <div class="mini-stat"><strong><?= (int) $leader['sessions'] ?></strong><span>Sessions</span></div>
                    <div class="mini-stat"><strong><?= (int) $leader['runs'] ?></strong><span>Runs</span></div>
                    <div class="mini-stat"><strong><?= (int) $leader['wickets'] ?></strong><span>Wickets</span></div>
                </div>
            </article>
        <?php else: ?>
            <div class="surface empty-state"><div class="empty-ball"></div><h2>The crown is waiting</h2><p>No eligible performance has been recorded this month. The next session can change that.</p><a class="btn btn-primary" href="<?= e(url('about.php')) ?>">How rankings work</a></div>
        <?php endif; ?>
    </div>
</section>

<?php if ($draw): ?>
<section class="section-sm">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">Match day · <?= e(format_date($draw['draw_date'])) ?></p><h2><?= $draw['mode'] === 'teams' ? 'Today’s teams' : 'Today’s batting order' ?></h2></div></div>
        <div class="surface draw-result"><?php require __DIR__ . '/includes/draw-board.php'; ?></div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">The front runners</p><h2>Current Top 3</h2><p>Every eligible session in <?= e(format_month($month)) ?>, scored under the club’s live point settings.</p></div><a class="text-link" href="<?= e(url('rankings.php')) ?>">Full leaderboard →</a></div>
        <?php if ($topThree): ?>
            <div class="podium">
                <?php foreach ($topThree as $index => $player): $classes = ['first', 'second', 'third']; $medalClass = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$player['rank']] ?? 'bronze'; ?>
                    <a class="surface podium-card <?= e($classes[$index]) ?>" href="<?= e(url('player.php?id=' . (int) $player['id'])) ?>">
                        <span class="medal <?= e($medalClass) ?>"><?= (int) $player['rank'] ?></span>
                        <?= avatar_html($player, 'avatar') ?>
                        <h3><?= e($player['name']) ?></h3>
                        <strong class="podium-points"><?= (int) $player['points'] ?> <small>pts</small></strong>
                        <span class="substats"><?= (int) $player['runs'] ?> runs · <?= (int) $player['wickets'] ?> wickets</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="surface empty-state"><div class="empty-ball"></div><h2>No rankings yet</h2><p>The first battle in Grünau is still waiting.</p></div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">Club pulse</p><h2>Numbers from the crease</h2></div></div>
        <div class="metric-grid">
            <div class="surface metric-card"><span class="metric-value" data-count="<?= $totals['sessions'] ?>"><?= $totals['sessions'] ?></span><span class="metric-label">Club sessions</span></div>
            <div class="surface metric-card"><span class="metric-value" data-count="<?= $totals['runs'] ?>"><?= $totals['runs'] ?></span><span class="metric-label">Runs scored</span></div>
            <div class="surface metric-card"><span class="metric-value" data-count="<?= $totals['wickets'] ?>"><?= $totals['wickets'] ?></span><span class="metric-label">Wickets taken</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">From the latest session</p><h2>Last time in Grünau</h2></div><?php if ($latest): ?><a class="text-link" href="<?= e(url('session.php?id=' . (int) $latest['id'])) ?>">View scorecard →</a><?php endif; ?></div>
        <?php if ($latest): ?>
            <div class="latest-grid">
                <article class="surface latest-main">
                    <span class="pill"><?= count($latest['eligible_players']) ?> eligible players</span>
                    <div class="session-date"><?= e(format_date($latest['session_date'])) ?></div>
                    <div class="session-meta"><span><?= e($latest['location']) ?></span><?php if ($latest['title']): ?><span><?= e($latest['title']) ?></span><?php endif; ?></div>
                    <?php if ($latest['notes']): ?><p class="muted"><?= nl2br(e($latest['notes'])) ?></p><?php endif; ?>
                </article>
                <article class="surface latest-performance">
                    <span class="pill">Session standouts</span>
                    <div class="performance-list">
                        <div class="performance-item"><div><span>Highest scorer</span><strong><?= e($highestScorer['name'] ?? 'Not recorded') ?></strong></div><strong><?= $highestScorer ? (int)$highestScorer['runs'] . ' runs' : '—' ?></strong></div>
                        <div class="performance-item"><div><span>Best bowling</span><strong><?= e($bestBowler['name'] ?? 'Not recorded') ?></strong></div><strong><?= $bestBowler ? (int)($bestBowler['wickets'] ?? 0) . '/' . (int)($bestBowler['runs_conceded'] ?? 0) : '—' ?></strong></div>
                        <div class="performance-item"><div><span>Player of the Day</span><strong><?= e($latest['player_of_day_name'] ?? 'Not selected') ?></strong></div><span>আজকের সেরা খেলোয়াড়</span></div>
                    </div>
                </article>
            </div>
        <?php else: ?>
            <div class="surface empty-state"><div class="empty-ball"></div><h2>No cricket session recorded yet</h2><p>The first battle in Grünau is still waiting.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
