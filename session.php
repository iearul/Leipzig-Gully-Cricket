<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$sessionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$session = getSessionStats($pdo, $sessionId ?: 0);
if (!$session || $session['status'] !== 'completed') {
    http_response_code(404); $pageTitle = 'Session not found'; $activePage = 'sessions'; require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="shell"><div class="surface empty-state"><div class="empty-ball"></div><h1>Session not found</h1><p>That scorecard is not available.</p><a class="btn btn-primary" href="'.e(url('sessions.php')).'">View sessions</a></div></div></section>'; require __DIR__ . '/includes/footer.php'; exit;
}
$pageTitle = format_date($session['session_date']) . ' Session'; $pageDescription = 'LGC cricket session scorecard from ' . format_date($session['session_date']) . ' in ' . $session['location'] . '.'; $activePage = 'sessions';
require __DIR__ . '/includes/header.php';
?>
<section class="session-hero"><div class="shell"><p class="eyebrow">Session scorecard</p><h1><?= e($session['title'] ?: format_date($session['session_date'])) ?></h1><div class="session-meta"><span><?= e(format_date($session['session_date'])) ?></span><span><?= e($session['location']) ?></span><span><?= count($session['eligible_players']) ?> eligible players</span></div><?php if ($session['notes']): ?><p class="muted"><?= nl2br(e($session['notes'])) ?></p><?php endif; ?><?php if ($session['team_info']): ?><p class="muted"><strong class="accent">Teams:</strong> <?= nl2br(e($session['team_info'])) ?></p><?php endif; ?>
<?php if ($session['player_of_day_id']): ?><a class="surface pod-highlight" href="<?= e(url('player.php?id='.(int)$session['player_of_day_id'])) ?>"><?= avatar_html(['name'=>$session['player_of_day_name'],'photo'=>$session['player_of_day_photo']], 'avatar') ?><div><span class="bengali">আজকের সেরা খেলোয়াড়</span><h2><?= e($session['player_of_day_name']) ?></h2><span class="muted">Player of the Day</span></div><span class="pill">Session star</span></a><?php endif; ?></div></section>
<section class="section-sm"><div class="shell"><div class="section-head"><div><p class="eyebrow">Individual performances</p><h2>Every contribution</h2></div></div>
    <?php if ($session['players']): ?><div class="performance-cards">
        <?php foreach ($session['players'] as $row): ?><a class="surface performance-card <?= $row['excluded'] ? 'is-excluded' : '' ?>" href="<?= e(url('player.php?id='.(int)$row['player_id'])) ?>">
            <span class="rank-player"><?= avatar_html($row, 'avatar') ?><span><strong><?= e($row['name']) ?></strong><small><?= $row['excluded'] ? 'Excluded from this session' : e($row['nickname'] ?: 'Eligible performance') ?></small></span></span>
            <?php foreach ([['Runs',$row['runs']],['Balls',$row['balls_faced']],['Wickets',$row['wickets']],['Conceded',$row['runs_conceded']],['Catches',$row['catches']]] as [$label,$value]): ?><span class="performance-stat"><strong><?= $value === null ? '—' : (int)$value ?></strong><span><?= e($label) ?></span></span><?php endforeach; ?>
            <span class="points"><?= $row['excluded'] ? '—' : (int)$row['calculated_points'].' pts' ?></span>
        </a><?php endforeach; ?>
    </div><?php else: ?><div class="surface empty-state"><h2>No performances</h2><p>No players were added to this session.</p></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
