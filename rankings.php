<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$ranking = with_rank_movement($pdo, getMonthlyRanking($pdo, $month), $month);
$previousMonth = shift_month($month, -1);
$nextMonth = shift_month($month, 1);
$pageTitle = format_month($month) . ' Rankings';
$pageDescription = 'Leipzig Gully Cricket monthly individual player rankings for ' . format_month($month) . '.';
$activePage = 'rankings';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="shell">
        <div class="section-head"><div><p class="eyebrow">Monthly standings</p><h1><?= e(format_month($month)) ?></h1><p>Eligible sessions only. Ties are resolved by awards, runs, wickets, catches, then efficiency.</p></div><span class="pill"><?= count($ranking) ?> ranked players</span></div>
        <div class="ranking-controls">
            <div class="month-switch"><a href="<?= e(url('rankings.php?month=' . $previousMonth)) ?>" aria-label="Previous month">←</a><strong><?= e(format_month($month)) ?></strong><?php if ($nextMonth <= date('Y-m')): ?><a href="<?= e(url('rankings.php?month=' . $nextMonth)) ?>" aria-label="Next month">→</a><?php else: ?><span class="disabled" aria-hidden="true">→</span><?php endif; ?></div>
            <a class="btn btn-ghost" href="<?= e(url('rankings.php')) ?>">Current month</a>
            <a class="btn btn-ghost" href="<?= e(url('rankings.php?month=' . shift_month(date('Y-m'), -1))) ?>">Previous month</a>
            <form method="get" class="field"><label class="sr-only" for="ranking-month">Select month</label><input id="ranking-month" name="month" type="month" max="<?= e(date('Y-m')) ?>" value="<?= e($month) ?>" onchange="this.form.submit()"></form>
        </div>
        <?php if ($ranking): ?>
            <div class="surface ranking-table">
                <div class="ranking-row table-head"><span>Rank</span><span>Player</span><span class="rank-stat">Sessions</span><span class="rank-stat">Runs</span><span class="rank-stat">Wickets</span><span class="rank-stat">Catches</span><span class="rank-points">Points</span></div>
                <?php foreach ($ranking as $row): $topClass = $row['rank'] === 1 ? 'top-one' : ($row['rank'] === 2 ? 'top-two' : ($row['rank'] === 3 ? 'top-three' : '')); ?>
                    <a class="ranking-row <?= e($topClass) ?>" href="<?= e(url('player.php?id='.(int)$row['id'])) ?>">
                        <span class="rank-number"><i class="rank-dot"></i>#<?= (int)$row['rank'] ?><?php if ($row['movement'] !== null): ?><small class="movement <?= $row['movement'] > 0 ? 'up' : ($row['movement'] < 0 ? 'down' : 'same') ?>"><?= $row['movement'] > 0 ? '↑ +' . $row['movement'] : ($row['movement'] < 0 ? '↓ ' . $row['movement'] : '— 0') ?></small><?php endif; ?></span>
                        <span class="rank-player"><?= avatar_html($row, 'avatar') ?><span><strong><?= e($row['name']) ?></strong><small><?= e($row['nickname'] ?: 'LGC player') ?></small></span></span>
                        <span class="rank-stat"><?= (int)$row['sessions'] ?></span><span class="rank-stat"><?= (int)$row['runs'] ?></span><span class="rank-stat"><?= (int)$row['wickets'] ?></span><span class="rank-stat"><?= (int)$row['catches'] ?></span><span class="rank-points"><?= (int)$row['points'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="surface empty-state"><div class="empty-ball"></div><h2>No ranking for <?= e(format_month($month)) ?></h2><p>No eligible session was recorded in this calendar month.</p></div><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
