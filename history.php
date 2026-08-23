<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$pdo = db(); $fame = getHallOfFame($pdo); $years = [];
foreach ($fame['champions'] as $champion) { $years[substr($champion['month'], 0, 4)][] = $champion; }
$pageTitle = 'Hall of Fame'; $pageDescription = 'Monthly champions and all-time Leipzig Gully Cricket records.'; $activePage = 'history'; require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="shell"><div class="section-head"><div><p class="eyebrow">Names on the honours board</p><h1>Hall of Fame</h1><p>Monthly crowns and career records earned across the fields of Grünau.</p></div></div>
    <div class="records-grid"><?php foreach ($fame['records'] as $record): $player = $record['player']; ?><article class="surface record-card"><span class="record-label"><?= e($record['label']) ?></span><?php if ($player): ?><strong><?= e($player['name']) ?></strong><span class="record-value"><?= (int)$player['value'] ?> <?= e($record['unit']) ?></span><?php else: ?><strong>Waiting</strong><span class="muted">No record yet</span><?php endif; ?></article><?php endforeach; ?></div>
</div></section>
<section class="section-sm"><div class="shell"><div class="section-head"><div><p class="eyebrow">Monthly champions</p><h2>The roll of honour</h2></div></div>
    <?php if ($years): ?><div class="champion-years"><?php foreach ($years as $year => $champions): ?><section><h2 class="year-title"><?= e($year) ?></h2><div class="champion-grid"><?php foreach ($champions as $champion): ?><a class="surface champion-card" href="<?= e(url('player.php?id='.(int)$champion['id'])) ?>"><?= avatar_html($champion,'avatar') ?><div><span class="pill"><?= e((new DateTimeImmutable($champion['month'].'-01'))->format('F')) ?></span><h3><?= e($champion['name']) ?></h3><p><?= (int)$champion['points'] ?> points · <?= (int)$champion['sessions'] ?> sessions</p></div></a><?php endforeach; ?></div></section><?php endforeach; ?></div>
    <?php else: ?><div class="surface empty-state"><div class="empty-ball"></div><h2>The honours board is waiting</h2><p>A monthly champion appears automatically after the calendar month ends.</p></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
