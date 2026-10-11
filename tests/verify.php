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

foreach(['admin/players.php','admin/player-form.php','admin/sessions.php','admin/session-form.php','admin/settings.php','admin/approvals.php'] as $protected){$source=file_get_contents(dirname(__DIR__).'/'.$protected);check(str_contains($source,'require_admin()'),$protected.' requires an authenticated administrator');}

check(str_contains(file_get_contents(dirname(__DIR__).'/account/stats.php'),'require_player()'),'stat submission page requires a logged-in player');

check(normalize_email('  Rahim@Example.TEST ')==='rahim@example.test' && valid_email('rahim@example.test') && !valid_email('not-an-email'),'login emails are normalised and validated');
check(password_problem('short','short')!==null && password_problem('long-enough','different')!==null && password_problem('long-enough','long-enough')===null,'player passwords need a minimum length and matching confirmation');
[$values,$error]=parse_stat_input(['runs'=>'42','balls_faced'=>'29','fours'=>'5','sixes'=>'2','is_out'=>'1','wickets'=>'','catches'=>'1']);
check($error===null && $values['runs']===42 && $values['wickets']===null && $values['is_out']===1,'submitted stats keep blanks as unknown and numbers as integers');
check(parse_stat_input(['is_out'=>'1'])[1]!==null,'a submission needs at least one number');
check(parse_stat_input(['runs'=>'10','sixes'=>'2'])[1]!==null,'fours and sixes cannot exceed the submitted runs');
check(parse_stat_input(['runs'=>'-3'])[1]!==null && parse_stat_input(['runs'=>'1000'])[1]!==null && parse_stat_input(['runs'=>['x']])[1]!==null,'negative, huge and malformed stat values are rejected');
check(submission_date_problem('2026-10-11','2026-10-11')===null && submission_date_problem('2026-10-12','2026-10-11')!==null && submission_date_problem('2026-09-01','2026-10-11')!==null && submission_date_problem('2026-02-30','2026-10-11')!==null,'stat dates must be real, recent and not in the future');
check(stat_summary(['runs'=>42,'balls_faced'=>29,'fours'=>5,'sixes'=>0,'is_out'=>0,'wickets'=>2,'balls_bowled'=>18,'runs_conceded'=>16,'catches'=>1,'run_outs'=>null,'stumpings'=>null])===['batting'=>'42 runs (29 balls) · 5×4 · not out','bowling'=>'2 wkts · 16 runs · 3.0 ov','fielding'=>'1 ct'],'stat summaries read like a scorecard');
check(str_contains($schema,'CREATE TABLE password_resets') && str_contains($schema,'UNIQUE KEY uq_password_resets_token') && str_contains($schema,'CREATE TABLE stat_submissions') && str_contains($schema,'UNIQUE KEY uq_players_email'),'schema stores hashed password links, sign-ups and stat submissions');
check(trim((string)@file_get_contents(dirname(__DIR__).'/migrations/.htaccess'))==='Require all denied' && str_contains((string)file_get_contents(dirname(__DIR__).'/migrations/001-player-accounts.sql'),'ALTER TABLE players'),'existing databases get a protected migration file');

echo "All lightweight verification checks passed.\n";
