<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin(); $pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $playerId = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);
    if (!$playerId) { flash('error','Invalid player.'); redirect('admin/players.php'); }
    $statement = $pdo->prepare('UPDATE players SET active = NOT active WHERE id = ?'); $statement->execute([$playerId]);
    flash('success','Player status updated.'); redirect('admin/players.php');
}
$players = $pdo->query(
    "SELECT p.*, COUNT(DISTINCT CASE WHEN sp.excluded = 0 AND s.status = 'completed' THEN s.id END) AS sessions,
        COALESCE(SUM(CASE WHEN sp.excluded = 0 AND s.status = 'completed' THEN sp.runs ELSE 0 END),0) AS runs
     FROM players p LEFT JOIN session_players sp ON sp.player_id = p.id LEFT JOIN sessions s ON s.id = sp.session_id
     GROUP BY p.id ORDER BY p.active DESC, p.name ASC"
)->fetchAll();
$pageTitle = 'Players'; $adminPage = 'players'; require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-titlebar"><div><p class="eyebrow">Squad management</p><h1>Players</h1><p>Add profiles and keep availability up to date.</p></div><div class="admin-actions"><a class="btn btn-primary" href="<?= e(url('admin/player-form.php')) ?>">+ Add player</a></div></div>
<?php if ($players): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Number</th><th>Joined</th><th>Sessions</th><th>Runs</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($players as $player): ?><tr><td><div class="admin-player"><?= avatar_html($player,'avatar') ?><span><strong><?= e($player['name']) ?></strong><small><?= e($player['nickname'] ?: 'No nickname') ?></small></span></div></td><td><?= e($player['player_number'] ?: '—') ?></td><td><?= e(format_date($player['joined_date'])) ?></td><td><?= (int)$player['sessions'] ?></td><td><?= (int)$player['runs'] ?></td><td><span class="pill <?= $player['active'] ? '' : 'inactive' ?>"><?= $player['active'] ? 'Active' : 'Inactive' ?></span></td><td><div class="row-actions"><a class="small-button" href="<?= e(url('admin/player-form.php?id='.(int)$player['id'])) ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>"><button class="small-button" type="submit"><?= $player['active'] ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><div class="surface admin-empty"><h2>No players yet</h2><p>Create the squad before recording a session.</p><a class="btn btn-primary" href="<?= e(url('admin/player-form.php')) ?>">Add first player</a></div><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
