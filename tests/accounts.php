<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

// Player accounts workflow. Run against a fresh database built from database.sql:
// php tests/accounts.php /path/to/mysql.sock
$socket = $argv[1] ?? '';
if ($socket === '') {
    fwrite(STDERR, "Usage: php tests/accounts.php /path/to/mysql.sock\n");
    exit(2);
}
$pdo = new PDO("mysql:unix_socket=$socket;dbname=lgc_test;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("SET time_zone = '+00:00'");

function accounts_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

function throws(callable $callback): bool
{
    try {
        $callback();
    } catch (RuntimeException) {
        return true;
    }
    return false;
}

$addPlayer = $pdo->prepare('INSERT INTO players (name, joined_date, active) VALUES (?, CURRENT_DATE, 1)');
$addPlayer->execute(['Rahim Uddin']);
$rahim = (int) $pdo->lastInsertId();
$addPlayer->execute(['Imran Hossain']);
$imran = (int) $pdo->lastInsertId();

$register = $pdo->prepare('INSERT INTO player_registrations (name, nickname, email, password_hash, batting_style) VALUES (?, ?, ?, ?, ?)');
$register->execute(['Karim Ahmed', 'Kari', 'karim@example.test', password_hash('karim-password', PASSWORD_DEFAULT), 'Left-handed']);
$karimRegistration = (int) $pdo->lastInsertId();
accounts_check(pending_approval_count($pdo) === 1, 'a new sign-up waits for approval');
accounts_check((int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn() === 2, 'an unapproved sign-up is not a player yet');
$karim = approve_registration($pdo, $karimRegistration);
$karimRow = $pdo->query("SELECT * FROM players WHERE id = $karim")->fetch();
accounts_check($karimRow['email'] === 'karim@example.test' && password_verify('karim-password', $karimRow['password_hash']) && (int) $karimRow['active'] === 1, 'approving a sign-up creates an active player who can log in with the chosen password');
accounts_check((int) $pdo->query('SELECT COUNT(*) FROM player_registrations')->fetchColumn() === 0, 'an approved sign-up leaves the queue');

$register->execute(['Rahim', 'Rahi', 'rahim@example.test', password_hash('rahim-password', PASSWORD_DEFAULT), null]);
$rahimRegistration = (int) $pdo->lastInsertId();
approve_registration($pdo, $rahimRegistration, $rahim);
$rahimRow = $pdo->query("SELECT * FROM players WHERE id = $rahim")->fetch();
accounts_check($rahimRow['name'] === 'Rahim Uddin' && $rahimRow['nickname'] === 'Rahi' && password_verify('rahim-password', $rahimRow['password_hash']), 'linking a sign-up gives an existing player a login and keeps the admin-entered name');
accounts_check((int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn() === 3, 'linking does not create a duplicate player');
$register->execute(['Someone', null, 'someone@example.test', password_hash('someone-password', PASSWORD_DEFAULT), null]);
$someone = (int) $pdo->lastInsertId();
accounts_check(throws(fn() => approve_registration($pdo, $someone, $rahim)), 'a sign-up cannot take over a player who already has a login');
$pdo->prepare('UPDATE player_registrations SET email = ? WHERE id = ?')->execute(['karim@example.test', $someone]);
accounts_check(throws(fn() => approve_registration($pdo, $someone)), 'a sign-up cannot reuse the email of an existing player');
reject_registration($pdo, $someone);
accounts_check(pending_approval_count($pdo) === 0, 'a rejected sign-up is deleted');
accounts_check(email_in_use($pdo, 'karim@example.test') && !email_in_use($pdo, 'karim@example.test', $karim) && !email_in_use($pdo, 'free@example.test'), 'email uniqueness checks other players only');

$pdo->prepare('UPDATE players SET email = ? WHERE id = ?')->execute(['imran@example.test', $imran]);
$firstToken = create_password_link($pdo, $imran, INVITE_LINK_MINUTES);
accounts_check(find_password_link($pdo, $firstToken)['id'] === $imran, 'an admin password link identifies the player');
accounts_check((int) $pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash = '$firstToken'")->fetchColumn() === 0, 'password links are stored only as hashes');
$secondToken = create_password_link($pdo, $imran, RESET_LINK_MINUTES);
accounts_check(find_password_link($pdo, $firstToken) === null && find_password_link($pdo, $secondToken) !== null, 'a new password link replaces the previous one');
accounts_check(recent_password_link_exists($pdo, $imran, 10), 'recent password links are detected for email rate limiting');
set_player_password($pdo, $imran, 'imran-new-password');
accounts_check(password_verify('imran-new-password', (string) $pdo->query("SELECT password_hash FROM players WHERE id = $imran")->fetchColumn()), 'a password link lets the player choose a password');
accounts_check(find_password_link($pdo, $secondToken) === null, 'a password link works only once');
$expired = bin2hex(random_bytes(32));
$pdo->prepare('INSERT INTO password_resets (player_id, token_hash, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP() - INTERVAL 1 MINUTE, UTC_TIMESTAMP() - INTERVAL 2 HOUR)')->execute([$imran, hash('sha256', $expired)]);
accounts_check(find_password_link($pdo, $expired) === null && find_password_link($pdo, 'not-a-token') === null, 'expired and malformed password links are refused');

$today = date('Y-m-d');
[$karimStats] = parse_stat_input(['runs' => '42', 'balls_faced' => '29', 'fours' => '5', 'sixes' => '2', 'is_out' => '1', 'wickets' => '2', 'catches' => '1']);
$first = save_stat_submission($pdo, $karim, $today, $karimStats);
[$karimStats] = parse_stat_input(['runs' => '45', 'fours' => '5', 'sixes' => '2', 'wickets' => '2', 'catches' => '1']);
$again = save_stat_submission($pdo, $karim, $today, $karimStats);
accounts_check($first === $again && (int) $pdo->query("SELECT runs FROM stat_submissions WHERE id = $first")->fetchColumn() === 45, 'sending stats for the same date again updates the waiting entry');
accounts_check(getMonthlyRanking($pdo, date('Y-m')) === [], 'waiting stats do not count in the rankings');
accounts_check(throws(fn() => save_stat_submission($pdo, $rahim, $today, $karimStats, $first)), 'a player cannot edit somebody else\'s entry');

$sessionId = approve_stat_submissions($pdo, [$first], null);
$session = $pdo->query("SELECT * FROM sessions WHERE id = $sessionId")->fetch();
$scorecard = $pdo->query("SELECT * FROM session_players WHERE session_id = $sessionId AND player_id = $karim")->fetch();
accounts_check($session['session_date'] === $today && $session['status'] === 'completed', 'approving into a new session creates a completed scorecard for that date');
accounts_check((int) $scorecard['runs'] === 45 && (int) $scorecard['calculated_points'] > 0, 'approved stats land on the scorecard with calculated points');
accounts_check(isset(ranking_by_player(getMonthlyRanking($pdo, date('Y-m')))[$karim]), 'approved stats count in the monthly ranking');
accounts_check(throws(fn() => approve_stat_submissions($pdo, [$first], $sessionId)), 'an entry cannot be approved twice');
accounts_check(throws(fn() => save_stat_submission($pdo, $karim, $today, $karimStats, $first)), 'a reviewed entry can no longer be edited');

$pdo->prepare('INSERT INTO session_players (session_id, player_id, runs, bonus_points, calculated_points) VALUES (?, ?, 10, 5, 0)')->execute([$sessionId, $rahim]);
[$rahimStats] = parse_stat_input(['runs' => '30', 'wickets' => '1']);
[$imranStats] = parse_stat_input(['catches' => '2']);
$rahimEntry = save_stat_submission($pdo, $rahim, $today, $rahimStats);
$imranEntry = save_stat_submission($pdo, $imran, $today, $imranStats);
approve_stat_submissions($pdo, [$rahimEntry, $imranEntry], $sessionId);
$rahimScore = $pdo->query("SELECT * FROM session_players WHERE session_id = $sessionId AND player_id = $rahim")->fetch();
accounts_check((int) $rahimScore['runs'] === 30 && (int) $rahimScore['bonus_points'] === 5, 'approving into an existing scorecard replaces the player\'s numbers but keeps admin bonus points');
accounts_check((int) $pdo->query("SELECT COUNT(*) FROM session_players WHERE session_id = $sessionId")->fetchColumn() === 3, 'approve all adds every waiting player of that date to one scorecard');

$yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
$oldEntry = save_stat_submission($pdo, $karim, $yesterday, $karimStats);
accounts_check(throws(fn() => approve_stat_submissions($pdo, [$oldEntry], $sessionId)), 'stats cannot be approved into a session from another date');
reject_stat_submission($pdo, $oldEntry);
accounts_check($pdo->query("SELECT status FROM stat_submissions WHERE id = $oldEntry")->fetchColumn() === 'rejected' && pending_approval_count($pdo) === 0, 'rejected stats leave the queue without touching any scorecard');

echo "Player accounts workflow verification passed.\n";
