<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();
$pdo = db();
$sessionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$session = ['id'=>null,'session_date'=>date('Y-m-d'),'location'=>'Grünau, Leipzig','title'=>'','notes'=>'','team_info'=>'','status'=>'completed','player_of_day_id'=>null];
$currentStats = [];
if ($sessionId) {
    $fullSession = getSessionStats($pdo, (int)$sessionId);
    if (!$fullSession) { http_response_code(404); exit('Session not found.'); }
    $session = $fullSession;
    foreach ($fullSession['players'] as $row) $currentStats[(int)$row['player_id']] = $row;
}
$playersStatement = $sessionId
    ? $pdo->prepare('SELECT DISTINCT p.* FROM players p LEFT JOIN session_players sp ON sp.player_id=p.id AND sp.session_id=? WHERE p.active=1 OR sp.id IS NOT NULL ORDER BY p.name')
    : $pdo->prepare('SELECT * FROM players WHERE active=1 ORDER BY name');
$playersStatement->execute($sessionId ? [$sessionId] : []);
$players = $playersStatement->fetchAll();
$allowedIds = array_map(static fn(array $player): int => (int)$player['id'],$players);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $session['session_date'] = (string)($_POST['session_date'] ?? ''); $session['location'] = trim((string)($_POST['location'] ?? '')); $session['title'] = trim((string)($_POST['title'] ?? '')); $session['notes'] = trim((string)($_POST['notes'] ?? '')); $session['team_info'] = trim((string)($_POST['team_info'] ?? '')); $session['status'] = (string)($_POST['status'] ?? 'completed');
    $participants = array_values(array_unique(array_map('intval', is_array($_POST['participants'] ?? null) ? $_POST['participants'] : [])));
    $participants = array_values(array_intersect($participants,$allowedIds));
    $postedStats = is_array($_POST['stats'] ?? null) ? $_POST['stats'] : [];
    $podId = nullable_int($_POST['player_of_day_id'] ?? null,1);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$session['session_date']);
    if (!$date || $date->format('Y-m-d') !== $session['session_date']) $error='Choose a valid session date.';
    elseif ($session['session_date'] > date('Y-m-d')) $error='A session date cannot be in the future.';
    elseif ($session['location']==='' || strlen($session['location'])>320) $error='Enter a location up to 160 characters.';
    elseif (strlen($session['title'])>320) $error='Keep the title under 160 characters.';
    elseif (!in_array($session['status'],['draft','completed','cancelled'],true)) $error='Choose a valid session status.';
    elseif ($session['status']==='completed' && !$participants) $error='Select at least one participating player for a completed session.';
    elseif ($podId !== null && !in_array($podId,$participants,true)) $error='Player of the Day must be a selected participant.';
    elseif ($podId !== null && !empty($postedStats[$podId]['excluded'])) $error='An excluded player cannot be Player of the Day.';
    else {
        try {
            $pdo->beginTransaction();
            $sessionValues=[$session['session_date'],$session['location'],$session['title']?:null,$session['notes']?:null,$session['team_info']?:null,$session['status'],$podId];
            if ($sessionId) { $update=$pdo->prepare('UPDATE sessions SET session_date=?,location=?,title=?,notes=?,team_info=?,status=?,player_of_day_id=? WHERE id=?');$sessionValues[]=$sessionId;$update->execute($sessionValues);$pdo->prepare('DELETE FROM session_players WHERE session_id=?')->execute([$sessionId]); }
            else { $insertSession=$pdo->prepare('INSERT INTO sessions (session_date,location,title,notes,team_info,status,player_of_day_id) VALUES (?,?,?,?,?,?,?)');$insertSession->execute($sessionValues);$sessionId=(int)$pdo->lastInsertId(); }
            $insert=$pdo->prepare('INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,bonus_points,penalty_points,excluded,admin_note,calculated_points) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)');
            foreach($participants as $playerId){ $stats=is_array($postedStats[$playerId]??null)?$postedStats[$playerId]:[]; $isOut=in_array((string)($stats['is_out']??''),['0','1'],true)?(int)$stats['is_out']:null; $values=[$sessionId,$playerId,nullable_int($stats['runs']??null),nullable_int($stats['balls_faced']??null),nullable_int($stats['fours']??null),nullable_int($stats['sixes']??null),$isOut,nullable_int($stats['wickets']??null),nullable_int($stats['balls_bowled']??null),nullable_int($stats['runs_conceded']??null),nullable_int($stats['catches']??null),nullable_int($stats['run_outs']??null),nullable_int($stats['stumpings']??null),nullable_int($stats['bonus_points']??null)??0,nullable_int($stats['penalty_points']??null)??0,!empty($stats['excluded'])?1:0,trim((string)($stats['admin_note']??''))?:null]; $insert->execute($values); }
            recalculate_session_points($pdo,(int)$sessionId); $pdo->commit();
            flash('success','Session saved successfully',['View Session'=>'session.php?id='.$sessionId,'View Updated Ranking'=>'rankings.php?month='.substr($session['session_date'],0,7)]); redirect('admin/session-form.php?id='.$sessionId);
        } catch(Throwable $exception){ if($pdo->inTransaction())$pdo->rollBack(); error_log('Session save failed: '.$exception->getMessage()); $error='The session could not be saved. Please try again.'; }
    }
    $currentStats=[]; foreach($participants as $playerId){$row=is_array($postedStats[$playerId]??null)?$postedStats[$playerId]:[];$row['player_id']=$playerId;$currentStats[$playerId]=$row;} $session['player_of_day_id']=$podId;
}
$pageTitle=$sessionId?'Edit session':'Add session';$adminPage='sessions';require dirname(__DIR__).'/includes/admin-header.php';
$scoreValue=static function(array $rows,int $playerId,string $key):mixed{return $rows[$playerId][$key]??'';};
?>
<div class="admin-titlebar"><div><p class="eyebrow">Fast score entry</p><h1><?= $sessionId?'Edit session':'Add session'?></h1><p>Select the players, enter what you know, and save the updated ranking.</p></div><div class="admin-actions"><a class="btn btn-ghost" href="<?= e(url('admin/sessions.php'))?>">Back to sessions</a></div></div>
<?php if($error):?><div class="notice notice-error" role="alert"><?= e($error)?></div><?php endif;?>
<?php if(!$players):?><div class="surface admin-empty"><h2>Add players first</h2><p>There are no active players available for a new session.</p><a class="btn btn-primary" href="<?= e(url('admin/player-form.php'))?>">Add player</a></div><?php else:?><form method="post" class="session-form"><?= csrf_field()?><?php if($sessionId):?><input type="hidden" name="id" value="<?= (int)$sessionId?>"><?php endif;?>
<section class="surface form-card session-core"><div class="form-grid"><div class="field"><label for="session_date">Date *</label><input id="session_date" name="session_date" type="date" max="<?= e(date('Y-m-d'))?>" required value="<?= e($session['session_date'])?>"></div><div class="field"><label for="location">Location *</label><input id="location" name="location" maxlength="160" required value="<?= e($session['location'])?>"></div><div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="160" placeholder="e.g. Sunday evening session" value="<?= e($session['title'])?>"></div><div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach(['completed'=>'Completed','draft'=>'Draft','cancelled'=>'Cancelled'] as $value=>$label):?><option value="<?= e($value)?>" <?=$session['status']===$value?'selected':''?>><?= e($label)?></option><?php endforeach;?></select></div><div class="field field-full"><label for="notes">Public note</label><textarea id="notes" name="notes" maxlength="3000" placeholder="Optional session story or conditions…"><?= e($session['notes'])?></textarea></div><div class="field field-full"><label for="team_info">Optional team information</label><textarea id="team_info" name="team_info" maxlength="2000" placeholder="Only when you divided into teams…"><?= e($session['team_info'])?></textarea></div></div></section>

<div class="form-section-title"><div><h2>1. Select participants</h2><p>Only selected players appear in the score entry.</p></div><div class="row-actions"><button class="small-button" type="button" data-select-all>Select all</button><button class="small-button" type="button" data-clear-all>Clear</button></div></div>
<div class="participant-grid"><?php foreach($players as $player):$pid=(int)$player['id'];?><label class="participant-card"><input type="checkbox" name="participants[]" value="<?=$pid?>" data-participant <?=isset($currentStats[$pid])?'checked':''?>><?=avatar_html($player,'avatar')?><span><strong><?=e($player['name'])?></strong><small><?=e($player['nickname']?:($player['active']?'Active':'Historic player'))?></small></span></label><?php endforeach;?></div>

<div class="form-section-title"><div><h2>2. Enter performances</h2><p>Blank means unavailable. Zero is a valid recorded value.</p></div></div>
<div class="score-entry-list"><?php foreach($players as $player):$pid=(int)$player['id'];?><section class="surface score-entry" data-score-player="<?=$pid?>" <?=isset($currentStats[$pid])?'':'hidden'?>><header class="score-entry-head"><?=avatar_html($player,'avatar')?><div><h3><?=e($player['name'])?></h3><p>Individual session statistics</p></div></header><div class="score-entry-body"><div class="score-groups">
<div class="score-group"><h4>Batting</h4>
<?php foreach([['runs','Runs',[-5,-1,1,5]],['balls_faced','Balls faced',[-5,-1,1,5]]] as [$key,$label,$steps]):?><div class="quick-field"><label for="<?=$key?>-<?=$pid?>"><?=e($label)?></label><div class="stepper"><button type="button" data-step="<?=$steps[0]?>"><?=$steps[0]?></button><button type="button" data-step="<?=$steps[1]?>"><?=$steps[1]?></button><input id="<?=$key?>-<?=$pid?>" name="stats[<?=$pid?>][<?=$key?>]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,$key))?>"><button type="button" data-step="<?=$steps[2]?>">+<?=$steps[2]?></button><button type="button" data-step="<?=$steps[3]?>">+<?=$steps[3]?></button></div></div><?php endforeach;?>
<div class="form-grid"><div class="field"><label for="fours-<?=$pid?>">Fours</label><input id="fours-<?=$pid?>" name="stats[<?=$pid?>][fours]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'fours'))?>"></div><div class="field"><label for="sixes-<?=$pid?>">Sixes</label><input id="sixes-<?=$pid?>" name="stats[<?=$pid?>][sixes]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'sixes'))?>"></div></div><div class="field"><label for="out-<?=$pid?>">Dismissal</label><select id="out-<?=$pid?>" name="stats[<?=$pid?>][is_out]"><option value="">Unknown</option><option value="0" <?=((string)$scoreValue($currentStats,$pid,'is_out')==='0')?'selected':''?>>Not out</option><option value="1" <?=((string)$scoreValue($currentStats,$pid,'is_out')==='1')?'selected':''?>>Out</option></select></div></div>
<div class="score-group"><h4>Bowling</h4><div class="quick-field"><label for="wickets-<?=$pid?>">Wickets</label><div class="stepper compact"><button type="button" data-step="-1">−1</button><input id="wickets-<?=$pid?>" name="stats[<?=$pid?>][wickets]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'wickets'))?>"><button type="button" data-step="1">+1</button></div></div><div class="field"><label for="bowled-<?=$pid?>">Balls bowled</label><input id="bowled-<?=$pid?>" name="stats[<?=$pid?>][balls_bowled]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'balls_bowled'))?>"><small>6 balls = 1 over</small></div><div class="field"><label for="conceded-<?=$pid?>">Runs conceded</label><input id="conceded-<?=$pid?>" name="stats[<?=$pid?>][runs_conceded]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'runs_conceded'))?>"></div></div>
<div class="score-group"><h4>Fielding</h4><?php foreach([['catches','Catches'],['run_outs','Run outs'],['stumpings','Stumpings']] as [$key,$label]):?><div class="quick-field"><label for="<?=$key?>-<?=$pid?>"><?=e($label)?></label><div class="stepper compact"><button type="button" data-step="-1">−1</button><input id="<?=$key?>-<?=$pid?>" name="stats[<?=$pid?>][<?=$key?>]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,$key))?>"><button type="button" data-step="1">+1</button></div></div><?php endforeach;?></div>
</div><div class="score-extras"><div class="field"><label for="bonus-<?=$pid?>">Admin bonus points</label><input id="bonus-<?=$pid?>" name="stats[<?=$pid?>][bonus_points]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'bonus_points'))?>"></div><div class="field"><label for="penalty-<?=$pid?>">Admin penalty points</label><input id="penalty-<?=$pid?>" name="stats[<?=$pid?>][penalty_points]" type="number" min="0" inputmode="numeric" value="<?=e($scoreValue($currentStats,$pid,'penalty_points'))?>"></div><label class="checkbox-field exclude-toggle"><input type="checkbox" name="stats[<?=$pid?>][excluded]" value="1" <?=!empty($currentStats[$pid]['excluded'])?'checked':''?>><span>Exclude from this session and rankings</span></label><div class="field"><label for="note-<?=$pid?>">Private admin note</label><input id="note-<?=$pid?>" name="stats[<?=$pid?>][admin_note]" maxlength="255" value="<?=e($scoreValue($currentStats,$pid,'admin_note'))?>"></div></div></div></section><?php endforeach;?></div>

<div class="form-section-title"><div><h2>3. Choose the session star</h2><p>Optional. An excluded player cannot receive the award.</p></div></div><section class="surface form-card"><div class="form-grid"><div class="field"><label for="player_of_day_id">Player of the Day</label><select id="player_of_day_id" name="player_of_day_id" data-pod-select><option value="">Not selected</option><?php foreach($players as $player):?><option value="<?= (int)$player['id']?>" <?=((int)$session['player_of_day_id']===(int)$player['id'])?'selected':''?>><?=e($player['name'])?></option><?php endforeach;?></select></div></div><div class="form-actions"><a class="btn btn-ghost" href="<?=e(url('admin/sessions.php'))?>">Cancel</a><button class="btn btn-primary" type="submit">Save session</button></div></section>
</form><?php endif;?>
<?php require dirname(__DIR__).'/includes/admin-footer.php';?>
