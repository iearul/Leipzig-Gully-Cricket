<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin(); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['type'] ?? '') === 'registration') {
            $registrationId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT) ?: 0;
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'reject') {
                reject_registration($pdo, $registrationId);
                flash('success', 'Sign-up rejected and deleted.');
            } else {
                $linkId = null;
                if ($action === 'link') {
                    $linkId = filter_input(INPUT_POST, 'link_player_id', FILTER_VALIDATE_INT) ?: null;
                    if (!$linkId) throw new RuntimeException('Choose the existing player to link this login to.');
                }
                $playerId = approve_registration($pdo, $registrationId, $linkId);
                $statement = $pdo->prepare('SELECT name, email FROM players WHERE id = ?'); $statement->execute([$playerId]); $player = $statement->fetch();
                $loginUrl = absolute_url('account/', true);
                if ($loginUrl) send_mail($player['email'], 'Your LGC profile is approved', "Hi {$player['name']},\n\nYour Leipzig Gully Cricket player profile was approved. Log in with your email address and password:\n\n$loginUrl\n\n" . APP_NAME);
                flash('success', $linkId ? 'Login linked to ' . $player['name'] . '.' : $player['name'] . ' added to the squad.', ['Edit player' => 'admin/player-form.php?id=' . $playerId]);
            }
        } elseif (($_POST['type'] ?? '') === 'stats') {
            if ($rejectId = filter_input(INPUT_POST, 'reject', FILTER_VALIDATE_INT)) {
                reject_stat_submission($pdo, $rejectId);
                flash('success', 'Entry rejected.');
            } else {
                $ids = isset($_POST['approve_all']) ? (is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []) : [filter_input(INPUT_POST, 'approve', FILTER_VALIDATE_INT) ?: 0];
                $choice = (string) ($_POST['session_id'] ?? 'new');
                $sessionId = approve_stat_submissions($pdo, $ids, $choice === 'new' ? null : (int) $choice);
                flash('success', count($ids) === 1 ? 'Entry approved.' : count($ids) . ' entries approved.', ['Open scorecard' => 'admin/session-form.php?id=' . $sessionId]);
            }
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/approvals.php');
}

$registrations = $pdo->query('SELECT * FROM player_registrations ORDER BY created_at ASC, id ASC')->fetchAll();
$linkable = $pdo->query('SELECT id, name, nickname FROM players WHERE password_hash IS NULL ORDER BY active DESC, name ASC')->fetchAll();
$submissions = $pdo->query(
    "SELECT ss.*, p.name, p.nickname, p.photo,
        (SELECT COUNT(*) FROM session_players sp INNER JOIN sessions s ON s.id = sp.session_id WHERE sp.player_id = ss.player_id AND s.session_date = ss.session_date) AS on_scorecard
     FROM stat_submissions ss INNER JOIN players p ON p.id = ss.player_id
     WHERE ss.status = 'pending' ORDER BY ss.session_date DESC, p.name ASC"
)->fetchAll();
$groups = [];
foreach ($submissions as $row) $groups[$row['session_date']][] = $row;
$sessionsByDate = [];
if ($groups) {
    $dates = array_keys($groups);
    $statement = $pdo->prepare('SELECT s.id, s.session_date, s.title, s.location, s.status, COUNT(sp.id) AS players FROM sessions s LEFT JOIN session_players sp ON sp.session_id = s.id WHERE s.session_date IN (' . implode(',', array_fill(0, count($dates), '?')) . ') GROUP BY s.id ORDER BY s.id');
    $statement->execute($dates);
    foreach ($statement->fetchAll() as $session) $sessionsByDate[$session['session_date']][] = $session;
}
$settings = get_point_settings($pdo);
$pageTitle = 'Approvals'; $adminPage = 'approvals'; require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-titlebar"><div><p class="eyebrow">Player self-service</p><h1>Approvals</h1><p>Nothing appears on the public site until you approve it.</p></div></div>

<div class="form-section-title"><div><h2>New players</h2><p><?= count($registrations) ?> waiting</p></div></div>
<?php if ($registrations): ?><div class="approval-list"><?php foreach ($registrations as $registration): $rid = (int) $registration['id']; $match = null; foreach ($linkable as $candidate) { if (strcasecmp(trim($candidate['name']), trim($registration['name'])) === 0) { $match = (int) $candidate['id']; break; } } ?>
<article class="surface approval-card">
<div class="admin-player"><?= avatar_html($registration, 'avatar') ?><span><strong><?= e($registration['name']) ?></strong><small><?= e($registration['email']) ?></small></span></div>
<dl class="approval-facts"><div><dt>Nickname</dt><dd><?= e($registration['nickname'] ?: '—') ?></dd></div><div><dt>Batting</dt><dd><?= e($registration['batting_style'] ?: '—') ?></dd></div><div><dt>Bowling</dt><dd><?= e($registration['bowling_style'] ?: '—') ?></dd></div><div><dt>Signed up</dt><dd><?= e(format_date($registration['created_at'])) ?></dd></div></dl>
<?php if ($registration['note']): ?><p class="approval-note"><?= nl2br(e($registration['note'])) ?></p><?php endif; ?>
<?php if ($match): ?><p class="warning-text">Same name as an existing player without login. Link instead of adding a duplicate?</p><?php endif; ?>
<div class="approval-actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="registration"><input type="hidden" name="registration_id" value="<?= $rid ?>"><button class="small-button" type="submit" name="action" value="approve">Approve as new player</button></form>
<?php if ($linkable): ?><form method="post" class="approval-link"><?= csrf_field() ?><input type="hidden" name="type" value="registration"><input type="hidden" name="registration_id" value="<?= $rid ?>"><label class="sr-only" for="link-<?= $rid ?>">Existing player</label><select id="link-<?= $rid ?>" name="link_player_id"><option value="">Existing player…</option><?php foreach ($linkable as $candidate): ?><option value="<?= (int) $candidate['id'] ?>" <?= $match === (int) $candidate['id'] ? 'selected' : '' ?>><?= e($candidate['name'] . ($candidate['nickname'] ? ' (' . $candidate['nickname'] . ')' : '')) ?></option><?php endforeach; ?></select><button class="small-button" type="submit" name="action" value="link">Link login</button></form><?php endif; ?>
<form method="post" data-confirm="Reject and delete this sign-up?"><?= csrf_field() ?><input type="hidden" name="type" value="registration"><input type="hidden" name="registration_id" value="<?= $rid ?>"><button class="small-button danger" type="submit" name="action" value="reject">Reject</button></form>
</div></article><?php endforeach; ?></div>
<?php else: ?><div class="surface admin-empty"><p>No sign-ups waiting.</p></div><?php endif; ?>

<div class="form-section-title"><div><h2>Submitted stats</h2><p><?= count($submissions) ?> waiting</p></div></div>
<?php if ($groups): foreach ($groups as $date => $rows): $sessions = $sessionsByDate[$date] ?? []; ?>
<form method="post" class="surface approval-group"><?= csrf_field() ?><input type="hidden" name="type" value="stats"><?php foreach ($rows as $row): ?><input type="hidden" name="ids[]" value="<?= (int) $row['id'] ?>"><?php endforeach; ?>
<div class="approval-group-head"><div><h3><?= e(format_date($date)) ?></h3><p><?= count($rows) ?> <?= count($rows) === 1 ? 'player' : 'players' ?></p></div>
<div class="field"><label for="session-<?= e($date) ?>">Add to scorecard</label><select id="session-<?= e($date) ?>" name="session_id"><?php foreach ($sessions as $session): ?><option value="<?= (int) $session['id'] ?>"><?= e(($session['title'] ?: $session['location']) . ' · ' . $session['players'] . ' players' . ($session['status'] !== 'completed' ? ' · ' . $session['status'] : '')) ?></option><?php endforeach; ?><option value="new">New session on this date</option></select></div>
<button class="btn btn-primary" type="submit" name="approve_all" value="1">Approve all <?= count($rows) ?></button></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Batting</th><th>Bowling</th><th>Fielding</th><th>Points</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): $summary = stat_summary($row); ?><tr><td><div class="admin-player"><?= avatar_html($row, 'avatar') ?><span><strong><?= e($row['name']) ?></strong><?php if ((int) $row['on_scorecard']): ?><small class="warning-text">Already on a scorecard this day. Approving into that session replaces those numbers.</small><?php endif; ?></span></div></td><td><?= e($summary['batting'] ?? '—') ?></td><td><?= e($summary['bowling'] ?? '—') ?></td><td><?= e($summary['fielding'] ?? '—') ?></td><td><?= calculatePlayerSessionPoints($row, $settings) ?></td><td><div class="row-actions"><button class="small-button" type="submit" name="approve" value="<?= (int) $row['id'] ?>">Approve</button><button class="small-button danger" type="submit" name="reject" value="<?= (int) $row['id'] ?>">Reject</button></div></td></tr><?php endforeach; ?>
</tbody></table></div></form>
<?php endforeach; else: ?><div class="surface admin-empty"><p>No stats waiting.</p></div><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
