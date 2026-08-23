<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

$socket = $argv[1] ?? '';
if ($socket === '') {
    fwrite(STDERR, "Usage: php tests/workflow.php /path/to/mysql.sock\n");
    exit(2);
}
$pdo = new PDO("mysql:unix_socket=$socket;dbname=lgc_test;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function workflow_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$adminHash = password_hash('safe-workflow-password', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO admins (username,password_hash) VALUES (?,?)')->execute(['admin',$adminHash]);
workflow_check(password_verify('safe-workflow-password',(string)$pdo->query("SELECT password_hash FROM admins WHERE username='admin'")->fetchColumn()),'administrator creation stores a password hash');

$addPlayer = $pdo->prepare('INSERT INTO players (name,nickname,joined_date,active) VALUES (?,?,CURRENT_DATE,1)');
$playerIds = [];
foreach ([['Muhammad','Mushi'],['Rahim','Rahi'],['Imran','Imu'],['Hasan','Hasu'],['Shakib','Shak'],['Tanvir','Tani']] as [$name,$nickname]) {
    $addPlayer->execute([$name,$nickname]);
    $playerIds[] = (int)$pdo->lastInsertId();
}
workflow_check((int)$pdo->query('SELECT COUNT(*) FROM players')->fetchColumn()===6,'admin can create six players');

$addSession = $pdo->prepare("INSERT INTO sessions (session_date,location,title,status,player_of_day_id) VALUES (CURRENT_DATE,'Grünau, Leipzig','Workflow session','completed',?)");
$addSession->execute([$playerIds[0]]);
$todaySession = (int)$pdo->lastInsertId();
$addPerformance = $pdo->prepare('INSERT INTO session_players (session_id,player_id,runs,balls_faced,fours,sixes,is_out,wickets,balls_bowled,runs_conceded,catches,run_outs,stumpings,excluded,calculated_points) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)');
$performances = [
    [$playerIds[0],42,29,5,2,1,2,18,16,1,0,0,0],
    [$playerIds[1],25,21,3,1,1,1,12,14,0,0,0,0],
    [$playerIds[2],31,24,4,0,1,3,24,20,1,0,0,0],
    [$playerIds[3],18,18,2,0,1,1,12,13,1,0,0,0],
    [$playerIds[4],999,99,10,10,0,10,30,1,10,5,2,1],
];
foreach ($performances as $row) {
    $addPerformance->execute([$todaySession,...$row]);
}
recalculate_session_points($pdo,$todaySession);
workflow_check((int)$pdo->query("SELECT COUNT(*) FROM session_players WHERE session_id=$todaySession")->fetchColumn()===5,'today session stores five participating players');
workflow_check((int)$pdo->query("SELECT COUNT(*) FROM session_players WHERE session_id=$todaySession AND excluded=1")->fetchColumn()===1,'one participating player is marked excluded');
workflow_check((int)$pdo->query("SELECT calculated_points FROM session_players WHERE session_id=$todaySession AND player_id={$playerIds[4]}")->fetchColumn()===0,'excluded player receives no session points');
workflow_check((int)$pdo->query("SELECT COUNT(*) FROM session_players WHERE session_id=$todaySession AND player_id={$playerIds[5]}")->fetchColumn()===0,'registered non-participant is absent from the scorecard');

$current = getMonthlyRanking($pdo,date('Y-m'));
$currentByPlayer = ranking_by_player($current);
workflow_check(!isset($currentByPlayer[$playerIds[4]]),'excluded player is absent from the monthly ranking');
workflow_check(!isset($currentByPlayer[$playerIds[5]]),'non-participant is absent from the monthly ranking');
workflow_check(isset($currentByPlayer[$playerIds[0]]) && $currentByPlayer[$playerIds[0]]['pod_awards']===1,'Player of the Day is counted in rankings');
$muhammad = getPlayerLifetimeStats($pdo,$playerIds[0]);
$excluded = getPlayerLifetimeStats($pdo,$playerIds[4]);
workflow_check($muhammad['sessions']===1 && $muhammad['runs']===42 && $muhammad['wickets']===2,'eligible session updates the player profile and lifetime totals');
workflow_check($excluded['sessions']===0 && $excluded['runs']===0 && $excluded['lifetime_points']===0,'excluded statistics do not affect the player profile or lifetime totals');

$beforeEdit = $currentByPlayer[$playerIds[1]]['points'];
$pdo->prepare('UPDATE session_players SET runs=55,fours=6,sixes=2 WHERE session_id=? AND player_id=?')->execute([$todaySession,$playerIds[1]]);
recalculate_session_points($pdo,$todaySession);
$afterEdit = ranking_by_player(getMonthlyRanking($pdo,date('Y-m')))[$playerIds[1]]['points'];
workflow_check($afterEdit>$beforeEdit,'editing a session recalculates monthly rankings');
workflow_check(getPlayerLifetimeStats($pdo,$playerIds[1])['runs']===55,'editing a session recalculates lifetime profiles');

$previousMonth = shift_month(date('Y-m'),-1);
$pdo->prepare("INSERT INTO sessions (session_date,location,title,status) VALUES (?,'Grünau, Leipzig','Previous month','completed')")->execute([$previousMonth.'-12']);
$previousSession=(int)$pdo->lastInsertId();
$addPerformance->execute([$previousSession,$playerIds[1],10,10,1,0,1,0,0,0,0,0,0,0]);
$addPerformance->execute([$previousSession,$playerIds[2],20,15,2,1,1,0,0,0,0,0,0,0]);
recalculate_session_points($pdo,$previousSession);
$previousRanking=getMonthlyRanking($pdo,$previousMonth);
workflow_check(count($previousRanking)===2 && $previousRanking[0]['id']===$playerIds[2],'previous calendar months can be viewed and ranked independently');
$pdo->prepare('UPDATE session_players SET runs=60 WHERE session_id=? AND player_id=?')->execute([$previousSession,$playerIds[1]]);
recalculate_session_points($pdo,$previousSession);
workflow_check(getMonthlyRanking($pdo,$previousMonth)[0]['id']===$playerIds[1],'editing historical data recalculates that historical month');

foreach (['index.php','players.php','player.php','rankings.php','sessions.php','session.php','history.php','about.php'] as $publicFile) {
    $source=file_get_contents(dirname(__DIR__).'/'.$publicFile);
    workflow_check(!str_contains($source,'$_POST'),'public page '.$publicFile.' exposes no write handler');
}
$adminCss=file_get_contents(dirname(__DIR__).'/assets/css/admin.css');
$adminJs=file_get_contents(dirname(__DIR__).'/assets/js/admin.js');
workflow_check(str_contains($adminCss,'@media (max-width: 640px)') && str_contains($adminCss,'min-height: 52px'),'admin score entry has phone-sized responsive controls');
workflow_check(str_contains($adminJs,'[data-step]'),'admin score entry supports quick plus/minus controls');

echo "Complete LGC database workflow verification passed.\n";
