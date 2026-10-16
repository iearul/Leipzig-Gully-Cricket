<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin(); $pdo = db();
$players = $pdo->query('SELECT * FROM players WHERE active = 1 ORDER BY name')->fetchAll();
$playersById = array_column($players, null, 'id');
$draw = current_draw($pdo);
$isToday = $draw && $draw['draw_date'] === date('Y-m-d');
$selectedIds = []; $guests = [];
if ($isToday) foreach (draw_entrants($draw) as $entrant) { if ($entrant['id'] !== null) $selectedIds[] = (int)$entrant['id']; else $guests[] = $entrant['name']; }
$guestText = implode("\n", $guests);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'draw');
    if ($action === 'toss') { $choice = (string)($_POST['choice'] ?? ''); if ($draw && in_array($choice, ['bat','bowl'], true)) set_draw_toss_choice($pdo, (int)$draw['id'], $choice); redirect('admin/draw.php'); }
    if ($action === 'clear') { clear_draw($pdo); flash('success', 'Draw removed. It no longer shows on the homepage.'); redirect('admin/draw.php'); }
    $mode = (string)($_POST['mode'] ?? '');
    $selectedIds = array_values(array_filter(array_unique(array_map('intval', is_array($_POST['players'] ?? null) ? $_POST['players'] : [])), static fn(int $id): bool => isset($playersById[$id])));
    $guestText = (string)($_POST['guests'] ?? '');
    $selectedNames = array_map(static fn(int $id): string => strtolower($playersById[$id]['name']), $selectedIds);
    $guests = array_values(array_filter(parse_guest_names($guestText), static fn(string $name): bool => !in_array(strtolower($name), $selectedNames, true)));
    $entrants = array_merge(array_map(static fn(int $id): array => ['id' => $id, 'name' => $playersById[$id]['name']], $selectedIds), array_map(static fn(string $name): array => ['id' => null, 'name' => $name], $guests));
    if (!in_array($mode, DRAW_MODES, true)) $error = 'Choose two teams or a batting order.';
    elseif (count($guests) > DRAW_MAX_GUESTS) $error = 'Add at most ' . DRAW_MAX_GUESTS . ' extra players.';
    elseif (array_filter($guests, static fn(string $name): bool => !text_fits($name, DRAW_GUEST_NAME_MAX))) $error = 'Keep extra player names under ' . DRAW_GUEST_NAME_MAX . ' characters.';
    elseif (count($entrants) < 2) $error = 'Select at least two players.';
    else {
        try { save_draw($pdo, $mode, make_draw($mode, $entrants)); redirect('admin/draw.php'); }
        catch (Throwable $exception) { error_log('Draw failed: ' . $exception->getMessage()); $error = 'The draw could not be saved. Please try again.'; }
    }
}
$drawRows = $draw ? draw_player_rows($pdo, $draw) : [];
$pageTitle = 'Draw'; $adminPage = 'draw'; require dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="admin-titlebar"><div><p class="eyebrow">Match day</p><h1>Draw</h1><p>Pick who is playing, then draw two teams or a batting order.</p></div></div>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($draw): ?><section class="surface draw-result"><div class="admin-panel-head"><div><h2><?= $draw['mode'] === 'teams' ? 'Teams' : 'Batting order' ?></h2><p class="muted"><?= e(format_date($draw['draw_date'])) ?> · <?= count(draw_entrants($draw)) ?> players<?= $isToday ? ' · shown on the homepage today' : '' ?></p></div></div>
<?php require dirname(__DIR__) . '/includes/draw-board.php'; ?>
<?php if ($draw['mode'] === 'teams'): ?><form method="post" class="draw-choice"><?= csrf_field() ?><input type="hidden" name="action" value="toss"><span><?= e(draw_team_name((int)$draw['toss'])) ?> chooses</span><?php foreach (['bat' => 'Bat first', 'bowl' => 'Bowl first'] as $value => $label): ?><button class="small-button<?= $draw['toss_choice'] === $value ? ' is-active' : '' ?>" type="submit" name="choice" value="<?= $value ?>" aria-pressed="<?= $draw['toss_choice'] === $value ? 'true' : 'false' ?>"><?= $label ?></button><?php endforeach; ?></form><?php endif; ?>
<div class="draw-actions"><textarea id="draw-text" class="sr-only" aria-hidden="true" tabindex="-1" readonly><?= e(draw_text($draw, true)) ?></textarea><button class="btn btn-ghost" type="button" data-copy="#draw-text">Copy for WhatsApp</button><a class="btn btn-ghost" href="<?= e('https://wa.me/?text=' . rawurlencode(draw_text($draw, true))) ?>" target="_blank" rel="noopener noreferrer">Share on WhatsApp</a><a class="btn btn-primary" href="<?= e(url('admin/session-form.php?from_draw=1')) ?>">Start session</a><form method="post" data-confirm="Remove this draw? It disappears from the homepage."><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-ghost danger" type="submit">Remove</button></form></div></section><?php endif; ?>

<form method="post" class="draw-form" data-draw-form<?= $isToday ? ' data-confirm="Replace today’s draw? The homepage shows the new one."' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="draw">
<div class="form-section-title"><div><h2>1. Who is playing?</h2><p data-draw-count></p></div><?php if ($players): ?><div class="row-actions"><button class="small-button" type="button" data-select-all>Select all</button><button class="small-button" type="button" data-clear-all>Clear</button></div><?php endif; ?></div>
<?php if ($players): ?><div class="participant-grid"><?php foreach ($players as $player): $pid = (int)$player['id']; ?><label class="participant-card"><input type="checkbox" name="players[]" value="<?= $pid ?>" data-participant <?= in_array($pid, $selectedIds, true) ? 'checked' : '' ?>><?= avatar_html($player, 'avatar') ?><span><strong><?= e($player['name']) ?></strong><small><?= e($player['nickname'] ?: 'Active') ?></small></span></label><?php endforeach; ?></div><?php else: ?><div class="surface admin-empty"><p>No active players yet. Add guests below or <a class="text-link" href="<?= e(url('admin/player-form.php')) ?>">add players</a>.</p></div><?php endif; ?>
<div class="form-section-title"><div><h2>2. Extra players</h2><p>Guests for this draw only. One name per line.</p></div></div>
<section class="surface form-card"><div class="field"><label for="guests">Guest names</label><textarea id="guests" name="guests" maxlength="1300" placeholder="Karim&#10;Rafi"><?= e($guestText) ?></textarea></div>
<div class="form-actions"><button class="btn btn-ghost" type="submit" name="mode" value="order">Draw batting order</button><button class="btn btn-primary" type="submit" name="mode" value="teams">Draw two teams</button></div></section>
</form>
<?php require dirname(__DIR__) . '/includes/admin-footer.php'; ?>
