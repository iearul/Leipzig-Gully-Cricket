<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$settings = default_point_settings();
$performance = ['runs'=>42,'fours'=>5,'sixes'=>2,'wickets'=>2,'catches'=>1,'run_outs'=>0,'stumpings'=>0,'bonus_points'=>3,'penalty_points'=>2,'excluded'=>0];
check(calculatePlayerSessionPoints($performance,$settings,true) === 102,'raw cricket statistics produce the expected points');
$performance['excluded']=1;
check(calculatePlayerSessionPoints($performance,$settings,true) === 0,'an excluded participant receives zero points');

$rows = [
    ['id'=>1,'name'=>'Alpha','points'=>100,'pod_awards'=>1,'runs'=>40,'wickets'=>2,'catches'=>1,'sessions'=>3],
    ['id'=>2,'name'=>'Bravo','points'=>100,'pod_awards'=>1,'runs'=>40,'wickets'=>2,'catches'=>1,'sessions'=>2],
    ['id'=>3,'name'=>'Charlie','points'=>90,'pod_awards'=>0,'runs'=>50,'wickets'=>1,'catches'=>0,'sessions'=>2],
    ['id'=>4,'name'=>'Delta','points'=>90,'pod_awards'=>0,'runs'=>50,'wickets'=>1,'catches'=>0,'sessions'=>2],
];
$ranked=rank_player_rows($rows);
check($ranked[0]['id']===2,'fewer sessions wins the final efficiency tie-breaker');
check($ranked[2]['rank']===3 && $ranked[3]['rank']===3,'fully tied players receive a joint rank');
check(balls_to_overs(17)==='2.5','bowling balls display as cricket overs');

$hash=password_hash('a-long-verification-password',PASSWORD_DEFAULT);
check(password_verify('a-long-verification-password',$hash),'administrator passwords use a verifiable secure hash');

$schema=file_get_contents(dirname(__DIR__).'/database.sql');
check(str_contains($schema,'UNIQUE KEY uq_session_player'),'database prevents duplicate players within one session');
check(str_contains($schema,'ON DELETE CASCADE'),'deleting a session removes only its attached performance rows');

foreach(['admin/players.php','admin/player-form.php','admin/sessions.php','admin/session-form.php','admin/settings.php'] as $protected){$source=file_get_contents(dirname(__DIR__).'/'.$protected);check(str_contains($source,'require_admin()'),$protected.' requires an authenticated administrator');}

echo "All lightweight verification checks passed.\n";
