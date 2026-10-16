<?php
declare(strict_types=1);
// Teams or batting order of a draw. Expects $draw (current_draw) and $drawRows (draw_player_rows).
$drawPerson = static function (array $entrant) use ($drawRows): string {
    $player = $entrant['id'] !== null ? ($drawRows[(int) $entrant['id']] ?? null) : null;
    return avatar_html($player ?? ['name' => $entrant['name']], 'avatar') . '<span>' . e($entrant['name']) . '</span>'
        . ($entrant['id'] === null ? '<small class="draw-guest">Guest</small>' : '');
};
?>
<?php if ($draw['mode'] === 'teams'): ?>
<div class="draw-teams"><?php foreach ($draw['teams'] as $index => $team): ?><div class="draw-team<?= (int) $draw['toss'] === $index ? ' has-toss' : '' ?>"><h3><?= e(draw_team_name($index)) ?> <small><?= count($team) ?> players</small><?php if ((int) $draw['toss'] === $index): ?><span class="pill">Won toss</span><?php endif; ?></h3><ul class="draw-people"><?php foreach ($team as $entrant): ?><li><?= $drawPerson($entrant) ?></li><?php endforeach; ?></ul></div><?php endforeach; ?></div>
<p class="draw-toss"><?= e(draw_toss_text($draw)) ?>.</p>
<?php else: ?>
<ol class="draw-people draw-order"><?php foreach ($draw['order'] as $index => $entrant): ?><li><span class="draw-number"><?= $index + 1 ?></span><?= $drawPerson($entrant) ?></li><?php endforeach; ?></ol>
<?php endif; ?>
