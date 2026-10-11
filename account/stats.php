<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = db();
$player = require_player();
$playerId = (int) $player['id'];
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'edit', FILTER_VALIDATE_INT) ?: null;
$form = ['session_date' => date('Y-m-d')] + array_fill_keys(stat_fields(), null);
$error = '';

if ($editId) {
    $statement = $pdo->prepare("SELECT * FROM stat_submissions WHERE id = ? AND player_id = ? AND status = 'pending'");
    $statement->execute([$editId, $playerId]);
    $existing = $statement->fetch();
    if (!$existing) { flash('error', 'This entry was already reviewed by an admin.'); redirect('account/stats.php'); }
    $form = array_intersect_key($existing, $form);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'withdraw') {
        $withdrawId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT) ?: 0;
        $pdo->prepare("DELETE FROM stat_submissions WHERE id = ? AND player_id = ? AND status = 'pending'")->execute([$withdrawId, $playerId]);
        flash('success', 'Entry withdrawn.');
        redirect('account/stats.php');
    }
    [$values, $error] = parse_stat_input($_POST);
    $form = ['session_date' => is_string($_POST['session_date'] ?? null) ? $_POST['session_date'] : ''] + array_map(static fn($value) => is_string($value) ? $value : null, array_intersect_key($_POST, $form)) + $form;
    $error ??= submission_date_problem($form['session_date']);
    if ($error === null) {
        try {
            save_stat_submission($pdo, $playerId, $form['session_date'], $values, $editId);
            flash('success', 'Stats sent. An admin will check them before they count.');
            redirect('account/stats.php');
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$history = $pdo->prepare('SELECT * FROM stat_submissions WHERE player_id = ? ORDER BY session_date DESC, id DESC LIMIT 30');
$history->execute([$playerId]);
$history = $history->fetchAll();
$settings = get_point_settings($pdo);
$value = static fn(string $key): string => (string) ($form[$key] ?? '');
$pageTitle = 'Submit stats'; $activePage = 'account'; $extraStyles = ['assets/css/admin.css']; $extraScripts = ['assets/js/admin.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-sm"><div class="shell account-shell">
<div class="admin-titlebar"><div><p class="eyebrow">Match stats</p><h1><?= $editId ? 'Edit your stats' : 'Submit your stats' ?></h1><p>Your numbers count in the rankings after an admin approves them.</p></div></div>
<?php if ($notice = take_flash()): ?><div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="surface form-card" method="post"><?= csrf_field() ?><?php if ($editId): ?><input type="hidden" name="edit" value="<?= (int) $editId ?>"><?php endif; ?>
<div class="form-grid"><div class="field"><label for="session_date">Match date *</label><input id="session_date" name="session_date" type="date" required max="<?= e(date('Y-m-d')) ?>" min="<?= e((new DateTimeImmutable())->modify('-' . SUBMISSION_MAX_AGE_DAYS . ' days')->format('Y-m-d')) ?>" value="<?= e($value('session_date')) ?>"></div></div>
<div class="score-groups account-score-groups">
<div class="score-group"><h4>Batting</h4>
<?php foreach ([['runs', 'Runs', [-5, -1, 1, 5]], ['balls_faced', 'Balls faced', [-5, -1, 1, 5]]] as [$key, $label, $steps]): ?><div class="quick-field"><label for="<?= $key ?>"><?= e($label) ?></label><div class="stepper"><button type="button" data-step="<?= $steps[0] ?>"><?= $steps[0] ?></button><button type="button" data-step="<?= $steps[1] ?>"><?= $steps[1] ?></button><input id="<?= $key ?>" name="<?= $key ?>" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value($key)) ?>"><button type="button" data-step="<?= $steps[2] ?>">+<?= $steps[2] ?></button><button type="button" data-step="<?= $steps[3] ?>">+<?= $steps[3] ?></button></div></div><?php endforeach; ?>
<div class="form-grid"><div class="field"><label for="fours">Fours</label><input id="fours" name="fours" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value('fours')) ?>"></div><div class="field"><label for="sixes">Sixes</label><input id="sixes" name="sixes" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value('sixes')) ?>"></div></div>
<div class="field"><label for="is_out">Dismissal</label><select id="is_out" name="is_out"><option value="">Did not bat / unknown</option><option value="0" <?= $value('is_out') === '0' ? 'selected' : '' ?>>Not out</option><option value="1" <?= $value('is_out') === '1' ? 'selected' : '' ?>>Out</option></select></div></div>
<div class="score-group"><h4>Bowling</h4><div class="quick-field"><label for="wickets">Wickets</label><div class="stepper compact"><button type="button" data-step="-1">−1</button><input id="wickets" name="wickets" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value('wickets')) ?>"><button type="button" data-step="1">+1</button></div></div><div class="field"><label for="balls_bowled">Balls bowled</label><input id="balls_bowled" name="balls_bowled" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value('balls_bowled')) ?>"><small>6 balls = 1 over</small></div><div class="field"><label for="runs_conceded">Runs conceded</label><input id="runs_conceded" name="runs_conceded" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value('runs_conceded')) ?>"></div></div>
<div class="score-group"><h4>Fielding</h4><?php foreach ([['catches', 'Catches'], ['run_outs', 'Run outs'], ['stumpings', 'Stumpings']] as [$key, $label]): ?><div class="quick-field"><label for="<?= $key ?>"><?= e($label) ?></label><div class="stepper compact"><button type="button" data-step="-1">−1</button><input id="<?= $key ?>" name="<?= $key ?>" type="number" min="0" max="999" inputmode="numeric" value="<?= e($value($key)) ?>"><button type="button" data-step="1">+1</button></div></div><?php endforeach; ?></div>
</div>
<div class="form-actions"><?php if ($editId): ?><a class="btn btn-ghost" href="<?= e(url('account/stats.php')) ?>">Cancel</a><?php endif; ?><button class="btn btn-primary" type="submit"><?= $editId ? 'Save changes' : 'Send for approval' ?></button></div></form>

<div class="form-section-title"><div><h2>My entries</h2></div></div>
<?php if ($history): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Performance</th><th>Points</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($history as $row): $summary = array_filter(stat_summary($row)); ?><tr><td><strong><?= e(format_date($row['session_date'])) ?></strong></td><td><?php foreach ($summary as $line): ?><span class="stat-line"><?= e($line) ?></span><?php endforeach; ?></td><td><?= calculatePlayerSessionPoints($row, $settings) ?></td><td><span class="pill <?= $row['status'] === 'rejected' ? 'inactive' : ($row['status'] === 'pending' ? 'pending' : '') ?>"><?= e($row['status'] === 'pending' ? 'Waiting' : $row['status']) ?></span></td><td><?php if ($row['status'] === 'pending'): ?><div class="row-actions"><a class="small-button" href="<?= e(url('account/stats.php?edit=' . (int) $row['id'])) ?>">Edit</a><form method="post" data-confirm="Withdraw this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="submission_id" value="<?= (int) $row['id'] ?>"><button class="small-button danger" type="submit">Withdraw</button></form></div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><div class="surface admin-empty"><p>No entries yet.</p></div><?php endif; ?>
</div></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
