<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

// Match-day draw workflow. Run against a fresh database built from database.sql:
// php tests/draws.php /path/to/mysql.sock
$socket = $argv[1] ?? '';
if ($socket === '') {
    fwrite(STDERR, "Usage: php tests/draws.php /path/to/mysql.sock\n");
    exit(2);
}
$pdo = new PDO("mysql:unix_socket=$socket;dbname=lgc_test;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("SET time_zone = '+00:00'");

function draws_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

draws_check(current_draw($pdo) === null, 'there is no draw before the first one');

$addPlayer = $pdo->prepare('INSERT INTO players (name, active) VALUES (?, 1)');
$entrants = [];
foreach (['Rahim', 'Imran', 'Hasan', 'Shakib'] as $name) {
    $addPlayer->execute([$name]);
    $entrants[] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name];
}
$entrants[] = ['id' => null, 'name' => 'Karim'];

save_draw($pdo, 'order', make_draw('order', $entrants));
save_draw($pdo, 'teams', make_draw('teams', $entrants));
$draw = current_draw($pdo, true);
draws_check((int) $pdo->query('SELECT COUNT(*) FROM draws')->fetchColumn() === 1 && $draw !== null && $draw['mode'] === 'teams', 'a new draw replaces the previous one');
draws_check($draw['draw_date'] === date('Y-m-d') && count(draw_entrants($draw)) === 5 && $draw['toss_choice'] === null, 'the draw is dated today and keeps all players, guests included');
draws_check(count(draw_player_rows($pdo, $draw)) === 4, 'avatars are looked up for registered players only');

set_draw_toss_choice($pdo, (int) $draw['id'], 'bat');
draws_check(current_draw($pdo)['toss_choice'] === 'bat' && str_ends_with(draw_text(current_draw($pdo)), 'won the toss and chose to bat.'), 'the toss winner’s choice is stored and shown');

$pdo->exec('UPDATE draws SET draw_date = CURRENT_DATE - INTERVAL 1 DAY');
draws_check(current_draw($pdo, true) === null && current_draw($pdo) !== null, 'the homepage hides a draw from an earlier day; admin still sees it');

save_draw($pdo, 'order', make_draw('order', $entrants));
set_draw_toss_choice($pdo, (int) current_draw($pdo)['id'], 'bowl');
draws_check(current_draw($pdo)['toss_choice'] === null, 'a batting order has no toss choice');

clear_draw($pdo);
draws_check(current_draw($pdo) === null, 'removing the draw takes it off the homepage');

echo "Match-day draw workflow verification passed.\n";
