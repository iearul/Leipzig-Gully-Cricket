<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin(); $pdo = db();
$playerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$player = ['id'=>null,'name'=>'','nickname'=>'','photo'=>null,'player_number'=>'','batting_style'=>'','bowling_style'=>'','nationality_code'=>'BD','bio'=>'','joined_date'=>date('Y-m-d'),'active'=>1];
if ($playerId) { $statement = $pdo->prepare('SELECT * FROM players WHERE id = ?'); $statement->execute([$playerId]); $existing = $statement->fetch(); if (!$existing) { http_response_code(404); exit('Player not found.'); } $player = $existing; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $player['name'] = trim((string)($_POST['name'] ?? '')); $player['nickname'] = trim((string)($_POST['nickname'] ?? '')); $player['player_number'] = trim((string)($_POST['player_number'] ?? '')); $player['batting_style'] = trim((string)($_POST['batting_style'] ?? '')); $player['bowling_style'] = trim((string)($_POST['bowling_style'] ?? '')); $player['nationality_code'] = strtoupper(trim((string)($_POST['nationality_code'] ?? ''))); $player['bio'] = trim((string)($_POST['bio'] ?? '')); $player['joined_date'] = (string)($_POST['joined_date'] ?? ''); $player['active'] = isset($_POST['active']) ? 1 : 0;
    if ($player['name'] === '' || strlen($player['name']) > 240) $error = 'Enter a player name up to 120 characters.';
    elseif ($player['nationality_code'] !== '' && !preg_match('/^[A-Z]{2}$/',$player['nationality_code'])) $error = 'Use a two-letter nationality code, such as BD or DE.';
    elseif ($player['joined_date'] !== '' && !DateTimeImmutable::createFromFormat('!Y-m-d',$player['joined_date'])) $error = 'Enter a valid joined date.';
    else {
        $photo = $player['photo'];
        if (!empty($_FILES['photo']['name'])) { try { $photo = save_player_photo($_FILES['photo']); } catch (RuntimeException $exception) { $error = $exception->getMessage(); } }
        if ($error === '') {
            $values = [$player['name'],$player['nickname'] ?: null,$photo,$player['player_number'] ?: null,$player['batting_style'] ?: null,$player['bowling_style'] ?: null,$player['nationality_code'] ?: null,$player['bio'] ?: null,$player['joined_date'] ?: null,$player['active']];
            if ($playerId) { $update = $pdo->prepare('UPDATE players SET name=?,nickname=?,photo=?,player_number=?,batting_style=?,bowling_style=?,nationality_code=?,bio=?,joined_date=?,active=? WHERE id=?'); $values[]=$playerId; $update->execute($values); flash('success','Player profile updated.'); }
            else { $insert=$pdo->prepare('INSERT INTO players (name,nickname,photo,player_number,batting_style,bowling_style,nationality_code,bio,joined_date,active) VALUES (?,?,?,?,?,?,?,?,?,?)'); $insert->execute($values); $playerId=(int)$pdo->lastInsertId(); flash('success','Player added to the squad.'); }
            redirect('admin/player-form.php?id='.$playerId);
        }
    }
}
$pageTitle = $playerId ? 'Edit player' : 'Add player'; $adminPage='players'; require dirname(__DIR__).'/includes/admin-header.php';
?>
<div class="admin-titlebar"><div><p class="eyebrow">Squad profile</p><h1><?= $playerId ? 'Edit player' : 'Add player' ?></h1><p>Keep profile details useful and public-facing.</p></div><div class="admin-actions"><a class="btn btn-ghost" href="<?= e(url('admin/players.php')) ?>">Back to players</a><?php if ($playerId): ?><a class="btn btn-ghost" href="<?= e(url('player.php?id='.$playerId)) ?>">View profile ↗</a><?php endif; ?></div></div>
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="surface form-card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><?php if ($playerId): ?><input type="hidden" name="id" value="<?= (int)$playerId ?>"><?php endif; ?><div class="form-grid">
<div class="field"><label for="name">Full name *</label><input id="name" name="name" required maxlength="120" value="<?= e($player['name']) ?>"></div><div class="field"><label for="nickname">Nickname</label><input id="nickname" name="nickname" maxlength="80" value="<?= e($player['nickname']) ?>"></div>
<div class="field"><label for="player_number">Jersey / player number</label><input id="player_number" name="player_number" maxlength="12" inputmode="numeric" value="<?= e($player['player_number']) ?>"></div><div class="field"><label for="joined_date">Joined date</label><input id="joined_date" name="joined_date" type="date" value="<?= e($player['joined_date']) ?>"></div>
<div class="field"><label for="batting_style">Batting style</label><select id="batting_style" name="batting_style"><option value="">Not specified</option><?php foreach (['Right-handed','Left-handed'] as $option): ?><option <?= $player['batting_style']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field"><label for="bowling_style">Bowling style</label><input id="bowling_style" name="bowling_style" maxlength="80" placeholder="e.g. Right-arm medium" value="<?= e($player['bowling_style']) ?>"></div>
<div class="field"><label for="nationality_code">Nationality code</label><input id="nationality_code" name="nationality_code" maxlength="2" value="<?= e($player['nationality_code']) ?>"><small>Optional two-letter code, e.g. BD</small></div><label class="checkbox-field"><input type="checkbox" name="active" value="1" <?= $player['active']?'checked':'' ?>><span>Active and available for new sessions</span></label>
<div class="field field-full"><label for="bio">Short bio</label><textarea id="bio" name="bio" maxlength="1000"><?= e($player['bio']) ?></textarea></div>
<div class="field field-full"><label for="photo">Profile photo</label><div class="upload-preview"><?= avatar_html($player,'avatar') ?><div><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Maximum 5 MB. Large photos are resized when supported.</small></div></div></div>
</div><div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('admin/players.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save player</button></div></form>
<?php require dirname(__DIR__).'/includes/admin-footer.php'; ?>
