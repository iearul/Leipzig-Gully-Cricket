<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$playerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT * FROM players WHERE id = ?');
$statement->execute([$playerId ?: 0]);
$player = $statement->fetch();
if (!$player) {
    http_response_code(404);
    $pageTitle = 'Player not found'; $activePage = 'players';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="shell"><div class="surface empty-state"><div class="empty-ball"></div><h1>Player not found</h1><p>That profile is not on the LGC team sheet.</p><a class="btn btn-primary" href="' . e(url('players.php')) . '">View players</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$stats = getPlayerLifetimeStats($pdo, (int) $player['id']);
$ranking = get_player_ranking_record($pdo, (int) $player['id']);
$monthly = getPlayerMonthlyStats($pdo, (int) $player['id']);
$maxPoints = max(1, ...array_column($monthly, 'points'));
$battingAverage = $stats['dismissals'] > 0 ? $stats['runs'] / $stats['dismissals'] : null;
$strikeRate = $stats['balls_faced'] > 0 ? $stats['runs'] * 100 / $stats['balls_faced'] : null;
$bowlingAverage = $stats['wickets'] > 0 ? $stats['runs_conceded'] / $stats['wickets'] : null;
$economy = $stats['balls_bowled'] > 0 ? $stats['runs_conceded'] * 6 / $stats['balls_bowled'] : null;
$bowlingStrike = $stats['wickets'] > 0 ? $stats['balls_bowled'] / $stats['wickets'] : null;
$pageTitle = $player['name'];
$pageDescription = $player['name'] . ' — Leipzig Gully Cricket player profile and career statistics.';
$activePage = 'players';
require __DIR__ . '/includes/header.php';
?>
<section class="profile-hero">
    <div class="shell">
        <div class="surface profile-panel">
            <div class="profile-image"><?= avatar_html($player, 'avatar') ?></div>
            <div class="profile-copy">
                <div><span class="pill <?= $player['active'] ? '' : 'inactive' ?>"><?= $player['active'] ? 'Active player' : 'Inactive player' ?></span></div>
                <h1><?= e($player['name']) ?></h1>
                <?php if ($player['nickname']): ?><span class="nickname">“<?= e($player['nickname']) ?>”</span><?php endif; ?>
                <div class="profile-tags">
                    <?php if ($player['player_number']): ?><span>#<?= e($player['player_number']) ?></span><?php endif; ?>
                    <?php if ($player['batting_style']): ?><span><?= e($player['batting_style']) ?> bat</span><?php endif; ?>
                    <?php if ($player['bowling_style']): ?><span><?= e($player['bowling_style']) ?></span><?php endif; ?>
                    <?php if ($player['joined_date']): ?><span>Joined <?= e(format_date($player['joined_date'])) ?></span><?php endif; ?>
                    <?php if ($player['nationality_code']): ?><span><?= e(strtoupper($player['nationality_code'])) ?></span><?php endif; ?>
                </div>
                <?php if ($player['bio']): ?><p class="bio"><?= nl2br(e($player['bio'])) ?></p><?php else: ?><p class="bio">Every player writes their story one session at a time.</p><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section-sm"><div class="shell stats-layout">
    <article class="surface stats-panel"><p class="eyebrow">At the crease</p><h2>Batting</h2><div class="stat-list">
        <?php $batting = [['Sessions',$stats['sessions']],['Innings',$stats['innings']],['Runs',$stats['runs']],['Balls faced',$stats['balls_faced']],['Highest score',$stats['highest_score']],['Average',$battingAverage !== null ? number_format($battingAverage, 2) : '—'],['Strike rate',$strikeRate !== null ? number_format($strikeRate, 1) : '—'],['Fours',$stats['fours']],['Sixes',$stats['sixes']],['Ducks',$stats['ducks']],['30+ scores',$stats['thirty_plus']],['50+ scores',$stats['fifty_plus']],['100+ scores',$stats['hundred_plus']]]; foreach ($batting as [$label,$value]): ?><div class="stat-cell"><strong><?= e($value) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?>
    </div></article>
    <article class="surface stats-panel"><p class="eyebrow">With the ball</p><h2>Bowling</h2><div class="stat-list">
        <?php $best = $stats['best_bowling']; $bowling = [['Wickets',$stats['wickets']],['Overs',balls_to_overs($stats['balls_bowled'])],['Runs conceded',$stats['runs_conceded']],['Best bowling',$best ? ((int)$best['wickets'] . '/' . (int)$best['runs_conceded']) : '—'],['Average',$bowlingAverage !== null ? number_format($bowlingAverage,2) : '—'],['Economy',$economy !== null ? number_format($economy,2) : '—'],['Strike rate',$bowlingStrike !== null ? number_format($bowlingStrike,1) : '—']]; foreach ($bowling as [$label,$value]): ?><div class="stat-cell"><strong><?= e($value) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?>
    </div></article>
    <article class="surface stats-panel"><p class="eyebrow">In the field</p><h2>Fielding</h2><div class="stat-list">
        <?php foreach ([['Catches',$stats['catches']],['Run outs',$stats['run_outs']],['Stumpings',$stats['stumpings']],['Player of the Day',$stats['pod_awards']]] as [$label,$value]): ?><div class="stat-cell"><strong><?= e($value) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?>
    </div></article>
    <article class="surface stats-panel"><p class="eyebrow">The ranking race</p><h2>Ranking</h2><div class="stat-list">
        <?php foreach ([['Current rank',$ranking['current'] ? '#'.$ranking['current']['rank'] : '—'],['Current points',$ranking['current']['points'] ?? 0],['Previous rank',$ranking['previous'] ? '#'.$ranking['previous']['rank'] : '—'],['Best monthly rank',$ranking['best_rank'] ? '#'.$ranking['best_rank'] : '—'],['Monthly titles',$ranking['titles']],['Lifetime points',$stats['lifetime_points']]] as [$label,$value]): ?><div class="stat-cell"><strong><?= e($value) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?>
    </div></article>
</div></section>

<section class="section"><div class="shell"><div class="section-head"><div><p class="eyebrow">Recent form</p><h2>Six-month points</h2></div></div><div class="surface stats-panel">
    <?php if (array_sum(array_column($monthly, 'points')) > 0): ?><div class="chart" role="img" aria-label="Points over the last six months">
        <?php foreach ($monthly as $row): $height = round($row['points'] / $maxPoints * 100); ?><div class="chart-column"><strong><?= (int)$row['points'] ?></strong><div class="chart-bar" style="--bar-height: <?= (int)$height ?>%"></div><span><?= e($row['label']) ?></span></div><?php endforeach; ?>
    </div><?php else: ?><div class="empty-state"><h2>Form chart waiting</h2><p>Recent monthly points will appear after an eligible session.</p></div><?php endif; ?>
</div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
