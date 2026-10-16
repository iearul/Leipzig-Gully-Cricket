<?php
declare(strict_types=1);

// Match-day draw: two random teams with a toss, or a random batting order.
// Only the latest draw is kept; the homepage shows it on the day it was drawn.

const DRAW_MODES = ['teams', 'order'];
const DRAW_MAX_GUESTS = 20;
const DRAW_GUEST_NAME_MAX = 60;

function draw_shuffle(array $items): array
{
    $items = array_values($items);
    for ($index = count($items) - 1; $index > 0; $index--) {
        $swap = random_int(0, $index);
        [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
    }
    return $items;
}

function parse_guest_names(string $input): array
{
    $names = [];
    foreach (preg_split('/[\r\n,]+/u', $input) ?: [] as $name) {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name !== '' && !isset($names[strtolower($name)])) {
            $names[strtolower($name)] = $name;
        }
    }
    return array_values($names);
}

// $entrants: list of ['id' => player id or null for a guest, 'name' => string].
function make_draw(string $mode, array $entrants): array
{
    $shuffled = draw_shuffle($entrants);
    if ($mode === 'order') {
        return ['order' => $shuffled];
    }
    $half = intdiv(count($shuffled) + 1, 2);
    $teams = [array_slice($shuffled, 0, $half), array_slice($shuffled, $half)];
    if (random_int(0, 1) === 1) {
        $teams = array_reverse($teams);
    }
    return ['teams' => $teams, 'toss' => random_int(0, 1)];
}

function save_draw(PDO $pdo, string $mode, array $result): void
{
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM draws');
        $pdo->prepare('INSERT INTO draws (draw_date, mode, result, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())')
            ->execute([date('Y-m-d'), $mode, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function current_draw(PDO $pdo, bool $todayOnly = false): ?array
{
    try {
        $draw = $pdo->query('SELECT * FROM draws ORDER BY id DESC LIMIT 1')->fetch();
    } catch (PDOException $exception) {
        error_log('Draw lookup failed (is migrations/002-draws.sql imported?): ' . $exception->getMessage());
        return null;
    }
    if (!$draw || ($todayOnly && $draw['draw_date'] !== date('Y-m-d'))) {
        return null;
    }
    $result = json_decode((string) $draw['result'], true);
    if (!is_array($result)) {
        return null;
    }
    unset($draw['result']);
    return $draw + $result;
}

function set_draw_toss_choice(PDO $pdo, int $drawId, string $choice): void
{
    $pdo->prepare("UPDATE draws SET toss_choice = ? WHERE id = ? AND mode = 'teams'")->execute([$choice, $drawId]);
}

function clear_draw(PDO $pdo): void
{
    $pdo->exec('DELETE FROM draws');
}

function draw_entrants(array $draw): array
{
    return $draw['mode'] === 'teams' ? array_merge(...$draw['teams']) : $draw['order'];
}

// Player rows (for avatars) of the registered players in a draw, keyed by id.
function draw_player_rows(PDO $pdo, array $draw): array
{
    $ids = array_values(array_filter(array_map(static fn(array $entrant): ?int => $entrant['id'], draw_entrants($draw))));
    if (!$ids) {
        return [];
    }
    $statement = $pdo->prepare('SELECT id, name, nickname, photo FROM players WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $statement->execute($ids);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

function draw_team_name(int $index): string
{
    return $index === 0 ? 'Team A' : 'Team B';
}

function draw_toss_text(array $draw): string
{
    $winner = draw_team_name((int) $draw['toss']) . ' won the toss';
    return match ($draw['toss_choice']) {
        'bat' => $winner . ' and chose to bat',
        'bowl' => $winner . ' and chose to bowl',
        default => $winner,
    };
}

// Plain text of a draw: for the session's team information, or ($forChat) a message for the WhatsApp group.
function draw_text(array $draw, bool $forChat = false): string
{
    $lines = [];
    if ($forChat) {
        $lines[] = '🏏 LGC ' . ($draw['mode'] === 'teams' ? 'teams' : 'batting order') . ' · ' . format_date($draw['draw_date']);
        $lines[] = '';
    }
    if ($draw['mode'] === 'teams') {
        foreach ($draw['teams'] as $index => $team) {
            $lines[] = draw_team_name($index) . ' (' . count($team) . '): ' . implode(', ', array_column($team, 'name'));
        }
        $lines[] = ($forChat ? '🪙 ' : '') . draw_toss_text($draw) . '.';
    } else {
        foreach ($draw['order'] as $index => $entrant) {
            $lines[] = ($index + 1) . '. ' . $entrant['name'];
        }
    }
    return implode("\n", $lines);
}
