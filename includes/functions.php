<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('This form expired. Please go back, refresh the page, and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id']) && is_int($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? url('admin/');
        redirect('admin/');
    }
}

function flash(string $type, string $message, array $actions = []): void
{
    $_SESSION['flash'] = compact('type', 'message', 'actions');
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return (new DateTimeImmutable($date, new DateTimeZone(APP_TIMEZONE)))->format('j M Y');
}

function format_month(string $month): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone(APP_TIMEZONE));
    return $date ? $date->format('F Y') : '';
}

function valid_month(?string $month): string
{
    $current = date('Y-m');
    if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        return $current;
    }
    return $month > $current ? $current : $month;
}

function shift_month(string $month, int $amount): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month) ?: new DateTimeImmutable('first day of this month');
    return $date->modify(($amount >= 0 ? '+' : '') . $amount . ' month')->format('Y-m');
}

function nullable_int(mixed $value, int $minimum = 0): ?int
{
    if ($value === '' || $value === null) {
        return null;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) {
        return null;
    }
    return max($minimum, (int) $number);
}

function default_point_settings(): array
{
    return [
        'run_point' => 1,
        'four_bonus' => 1,
        'six_bonus' => 2,
        'thirty_bonus' => 5,
        'fifty_bonus' => 10,
        'hundred_bonus' => 25,
        'wicket_point' => 15,
        'three_wicket_bonus' => 10,
        'five_wicket_bonus' => 25,
        'catch_point' => 5,
        'run_out_point' => 7,
        'stumping_point' => 7,
        'player_of_day_bonus' => 10,
    ];
}

function point_setting_labels(): array
{
    return [
        'run_point' => ['Batting', 'Points per run'],
        'four_bonus' => ['Batting', 'Bonus per four'],
        'six_bonus' => ['Batting', 'Bonus per six'],
        'thirty_bonus' => ['Batting', '30+ runs bonus'],
        'fifty_bonus' => ['Batting', '50+ runs bonus'],
        'hundred_bonus' => ['Batting', '100+ runs bonus'],
        'wicket_point' => ['Bowling', 'Points per wicket'],
        'three_wicket_bonus' => ['Bowling', '3+ wickets bonus'],
        'five_wicket_bonus' => ['Bowling', '5+ wickets bonus'],
        'catch_point' => ['Fielding', 'Points per catch'],
        'run_out_point' => ['Fielding', 'Points per run out'],
        'stumping_point' => ['Fielding', 'Points per stumping'],
        'player_of_day_bonus' => ['Awards', 'Player of the Day bonus'],
    ];
}

function get_point_settings(PDO $pdo): array
{
    $settings = default_point_settings();
    foreach ($pdo->query('SELECT setting_key, setting_value FROM point_settings')->fetchAll() as $row) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = (float) $row['setting_value'];
        }
    }
    return $settings;
}

function calculatePlayerSessionPoints(array $stats, array $settings, bool $isPlayerOfDay = false): int
{
    if (!empty($stats['excluded'])) {
        return 0;
    }

    $runs = (int) ($stats['runs'] ?? 0);
    $wickets = (int) ($stats['wickets'] ?? 0);
    $points = $runs * $settings['run_point'];
    $points += (int) ($stats['fours'] ?? 0) * $settings['four_bonus'];
    $points += (int) ($stats['sixes'] ?? 0) * $settings['six_bonus'];

    if ($runs >= 100) {
        $points += $settings['hundred_bonus'];
    } elseif ($runs >= 50) {
        $points += $settings['fifty_bonus'];
    } elseif ($runs >= 30) {
        $points += $settings['thirty_bonus'];
    }

    $points += $wickets * $settings['wicket_point'];
    if ($wickets >= 5) {
        $points += $settings['five_wicket_bonus'];
    } elseif ($wickets >= 3) {
        $points += $settings['three_wicket_bonus'];
    }

    $points += (int) ($stats['catches'] ?? 0) * $settings['catch_point'];
    $points += (int) ($stats['run_outs'] ?? 0) * $settings['run_out_point'];
    $points += (int) ($stats['stumpings'] ?? 0) * $settings['stumping_point'];
    $points += (int) ($stats['bonus_points'] ?? 0);
    $points -= (int) ($stats['penalty_points'] ?? 0);
    if ($isPlayerOfDay) {
        $points += $settings['player_of_day_bonus'];
    }

    return (int) round($points);
}

function recalculate_session_points(PDO $pdo, int $sessionId): void
{
    $session = $pdo->prepare('SELECT player_of_day_id FROM sessions WHERE id = ?');
    $session->execute([$sessionId]);
    $playerOfDayId = $session->fetchColumn();
    $settings = get_point_settings($pdo);
    $rows = $pdo->prepare('SELECT * FROM session_players WHERE session_id = ?');
    $rows->execute([$sessionId]);
    $update = $pdo->prepare('UPDATE session_players SET calculated_points = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    foreach ($rows->fetchAll() as $row) {
        $points = calculatePlayerSessionPoints($row, $settings, (int) $playerOfDayId === (int) $row['player_id']);
        $update->execute([$points, $row['id']]);
    }
}

function recalculate_all_points(PDO $pdo): void
{
    foreach ($pdo->query('SELECT id FROM sessions')->fetchAll(PDO::FETCH_COLUMN) as $sessionId) {
        recalculate_session_points($pdo, (int) $sessionId);
    }
}

function getMonthlyRanking(PDO $pdo, string $month): array
{
    $month = valid_month($month);
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
    $query = $pdo->prepare(
        "SELECT p.id, p.name, p.nickname, p.photo, p.player_number,
            COUNT(DISTINCT s.id) AS sessions,
            COALESCE(SUM(sp.runs), 0) AS runs,
            COALESCE(SUM(sp.wickets), 0) AS wickets,
            COALESCE(SUM(sp.catches), 0) AS catches,
            COALESCE(SUM(sp.calculated_points), 0) AS points,
            COALESCE(SUM(CASE WHEN s.player_of_day_id = p.id THEN 1 ELSE 0 END), 0) AS pod_awards
         FROM session_players sp
         INNER JOIN sessions s ON s.id = sp.session_id
         INNER JOIN players p ON p.id = sp.player_id
         WHERE s.session_date >= ? AND s.session_date < ? AND s.status = 'completed' AND sp.excluded = 0
         GROUP BY p.id, p.name, p.nickname, p.photo, p.player_number"
    );
    $query->execute([$start, $end]);
    return rank_player_rows($query->fetchAll());
}

function rank_player_rows(array $ranking): array
{
    usort($ranking, static function (array $a, array $b): int {
        foreach ([['points', -1], ['pod_awards', -1], ['runs', -1], ['wickets', -1], ['catches', -1], ['sessions', 1]] as [$key, $direction]) {
            $comparison = (int) $a[$key] <=> (int) $b[$key];
            if ($comparison !== 0) {
                return $comparison * $direction;
            }
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $lastComparable = null;
    $lastRank = 0;
    foreach ($ranking as $index => &$row) {
        foreach (['sessions', 'runs', 'wickets', 'catches', 'points', 'pod_awards'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $comparable = implode(':', [$row['points'], $row['pod_awards'], $row['runs'], $row['wickets'], $row['catches'], $row['sessions']]);
        if ($comparable !== $lastComparable) {
            $lastRank = $index + 1;
        }
        $row['rank'] = $lastRank;
        $lastComparable = $comparable;
    }
    unset($row);

    return $ranking;
}

function ranking_by_player(array $ranking): array
{
    $result = [];
    foreach ($ranking as $row) {
        $result[(int) $row['id']] = $row;
    }
    return $result;
}

function with_rank_movement(PDO $pdo, array $ranking, string $month): array
{
    $previous = ranking_by_player(getMonthlyRanking($pdo, shift_month($month, -1)));
    foreach ($ranking as &$row) {
        $oldRank = $previous[(int) $row['id']]['rank'] ?? null;
        $row['previous_rank'] = $oldRank;
        $row['movement'] = $oldRank === null ? null : (int) $oldRank - (int) $row['rank'];
    }
    unset($row);
    return $ranking;
}

function getPlayerLifetimeStats(PDO $pdo, int $playerId): array
{
    $query = $pdo->prepare(
        "SELECT COUNT(DISTINCT s.id) AS sessions,
            SUM(CASE WHEN sp.runs IS NOT NULL OR sp.balls_faced IS NOT NULL OR sp.is_out IS NOT NULL THEN 1 ELSE 0 END) AS innings,
            COALESCE(SUM(sp.runs), 0) AS runs, COALESCE(SUM(sp.balls_faced), 0) AS balls_faced,
            COALESCE(MAX(sp.runs), 0) AS highest_score, COALESCE(SUM(sp.fours), 0) AS fours,
            COALESCE(SUM(sp.sixes), 0) AS sixes,
            SUM(CASE WHEN sp.runs = 0 AND sp.is_out = 1 THEN 1 ELSE 0 END) AS ducks,
            SUM(CASE WHEN sp.runs >= 30 THEN 1 ELSE 0 END) AS thirty_plus,
            SUM(CASE WHEN sp.runs >= 50 THEN 1 ELSE 0 END) AS fifty_plus,
            SUM(CASE WHEN sp.runs >= 100 THEN 1 ELSE 0 END) AS hundred_plus,
            COALESCE(SUM(sp.is_out = 1), 0) AS dismissals,
            COALESCE(SUM(sp.wickets), 0) AS wickets, COALESCE(SUM(sp.balls_bowled), 0) AS balls_bowled,
            COALESCE(SUM(sp.runs_conceded), 0) AS runs_conceded,
            COALESCE(SUM(sp.catches), 0) AS catches, COALESCE(SUM(sp.run_outs), 0) AS run_outs,
            COALESCE(SUM(sp.stumpings), 0) AS stumpings,
            COALESCE(SUM(sp.calculated_points), 0) AS lifetime_points,
            COALESCE(SUM(CASE WHEN s.player_of_day_id = sp.player_id THEN 1 ELSE 0 END), 0) AS pod_awards
         FROM session_players sp INNER JOIN sessions s ON s.id = sp.session_id
         WHERE sp.player_id = ? AND sp.excluded = 0 AND s.status = 'completed'"
    );
    $query->execute([$playerId]);
    $stats = $query->fetch() ?: [];
    foreach ($stats as $key => $value) {
        $stats[$key] = (int) $value;
    }

    $best = $pdo->prepare(
        "SELECT wickets, runs_conceded FROM session_players sp INNER JOIN sessions s ON s.id = sp.session_id
         WHERE sp.player_id = ? AND sp.excluded = 0 AND s.status = 'completed' AND sp.wickets IS NOT NULL
         ORDER BY sp.wickets DESC, sp.runs_conceded ASC LIMIT 1"
    );
    $best->execute([$playerId]);
    $stats['best_bowling'] = $best->fetch() ?: null;
    return $stats;
}

function getPlayerMonthlyStats(PDO $pdo, int $playerId, int $months = 6): array
{
    $result = [];
    $month = date('Y-m');
    for ($offset = $months - 1; $offset >= 0; $offset--) {
        $key = shift_month($month, -$offset);
        $ranking = ranking_by_player(getMonthlyRanking($pdo, $key));
        $row = $ranking[$playerId] ?? null;
        $result[] = [
            'month' => $key,
            'label' => (new DateTimeImmutable($key . '-01'))->format('M'),
            'points' => (int) ($row['points'] ?? 0),
            'rank' => $row['rank'] ?? null,
        ];
    }
    return $result;
}

function getSessionStats(PDO $pdo, int $sessionId): ?array
{
    $session = $pdo->prepare(
        'SELECT s.*, p.name AS player_of_day_name, p.photo AS player_of_day_photo
         FROM sessions s LEFT JOIN players p ON p.id = s.player_of_day_id WHERE s.id = ?'
    );
    $session->execute([$sessionId]);
    $result = $session->fetch();
    if (!$result) {
        return null;
    }
    $players = $pdo->prepare(
        'SELECT sp.*, p.name, p.nickname, p.photo, p.player_number
         FROM session_players sp INNER JOIN players p ON p.id = sp.player_id
         WHERE sp.session_id = ? ORDER BY sp.excluded ASC, sp.calculated_points DESC, p.name ASC'
    );
    $players->execute([$sessionId]);
    $result['players'] = $players->fetchAll();
    $result['eligible_players'] = array_values(array_filter($result['players'], static fn(array $row): bool => !(bool) $row['excluded']));
    return $result;
}

function get_latest_session(PDO $pdo): ?array
{
    $id = $pdo->query("SELECT id FROM sessions WHERE status = 'completed' ORDER BY session_date DESC, id DESC LIMIT 1")->fetchColumn();
    return $id ? getSessionStats($pdo, (int) $id) : null;
}

function get_club_totals(PDO $pdo): array
{
    $sessions = (int) $pdo->query("SELECT COUNT(*) FROM sessions WHERE status = 'completed'")->fetchColumn();
    $stats = $pdo->query(
        "SELECT COALESCE(SUM(sp.runs), 0) AS runs, COALESCE(SUM(sp.wickets), 0) AS wickets
         FROM session_players sp INNER JOIN sessions s ON s.id = sp.session_id
         WHERE sp.excluded = 0 AND s.status = 'completed'"
    )->fetch();
    return ['sessions' => $sessions, 'runs' => (int) $stats['runs'], 'wickets' => (int) $stats['wickets']];
}

function get_player_ranking_record(PDO $pdo, int $playerId): array
{
    $current = ranking_by_player(getMonthlyRanking($pdo, date('Y-m')))[$playerId] ?? null;
    $previous = ranking_by_player(getMonthlyRanking($pdo, shift_month(date('Y-m'), -1)))[$playerId] ?? null;
    $months = $pdo->prepare(
        "SELECT DISTINCT DATE_FORMAT(s.session_date, '%Y-%m') AS month_key
         FROM sessions s INNER JOIN session_players sp ON sp.session_id = s.id
         WHERE sp.player_id = ? AND sp.excluded = 0 AND s.status = 'completed' ORDER BY month_key ASC"
    );
    $months->execute([$playerId]);
    $bestRank = null;
    $titles = 0;
    foreach ($months->fetchAll(PDO::FETCH_COLUMN) as $month) {
        $row = ranking_by_player(getMonthlyRanking($pdo, $month))[$playerId] ?? null;
        if ($row) {
            $bestRank = $bestRank === null ? $row['rank'] : min($bestRank, $row['rank']);
            if ($month < date('Y-m') && $row['rank'] === 1) {
                $titles++;
            }
        }
    }
    return ['current' => $current, 'previous' => $previous, 'best_rank' => $bestRank, 'titles' => $titles];
}

function balls_to_overs(int $balls): string
{
    return intdiv($balls, 6) . '.' . ($balls % 6);
}

function getHallOfFame(PDO $pdo): array
{
    $months = $pdo->query(
        "SELECT DISTINCT DATE_FORMAT(session_date, '%Y-%m') AS month_key
         FROM sessions WHERE status = 'completed' AND session_date < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
         ORDER BY month_key DESC"
    )->fetchAll(PDO::FETCH_COLUMN);
    $champions = [];
    foreach ($months as $month) {
        foreach (getMonthlyRanking($pdo, $month) as $row) {
            if ($row['rank'] !== 1) {
                break;
            }
            $row['month'] = $month;
            $champions[] = $row;
        }
    }

    $leader = static function (PDO $pdo, string $metric): ?array {
        $allowed = ['runs', 'wickets', 'catches'];
        if (!in_array($metric, $allowed, true)) {
            return null;
        }
        $query = $pdo->query(
            "SELECT p.id, p.name, p.photo, COALESCE(SUM(sp.$metric), 0) AS value
             FROM players p INNER JOIN session_players sp ON sp.player_id = p.id
             INNER JOIN sessions s ON s.id = sp.session_id
             WHERE sp.excluded = 0 AND s.status = 'completed'
             GROUP BY p.id, p.name, p.photo ORDER BY value DESC, p.name ASC LIMIT 1"
        );
        $row = $query->fetch();
        return $row ?: null;
    };

    $pod = $pdo->query(
        "SELECT p.id, p.name, p.photo, COUNT(*) AS value FROM sessions s
         INNER JOIN players p ON p.id = s.player_of_day_id
         INNER JOIN session_players sp ON sp.session_id = s.id AND sp.player_id = p.id AND sp.excluded = 0
         WHERE s.status = 'completed' GROUP BY p.id, p.name, p.photo ORDER BY value DESC, p.name ASC LIMIT 1"
    )->fetch() ?: null;
    $sessionHigh = $pdo->query(
        "SELECT p.id, p.name, p.photo, sp.calculated_points AS value, s.session_date
         FROM session_players sp INNER JOIN players p ON p.id = sp.player_id INNER JOIN sessions s ON s.id = sp.session_id
         WHERE sp.excluded = 0 AND s.status = 'completed' ORDER BY sp.calculated_points DESC, s.session_date ASC LIMIT 1"
    )->fetch() ?: null;

    $titleCounts = [];
    $highestMonthly = null;
    foreach ($champions as $champion) {
        $id = (int) $champion['id'];
        $titleCounts[$id] ??= ['id' => $id, 'name' => $champion['name'], 'photo' => $champion['photo'], 'value' => 0];
        $titleCounts[$id]['value']++;
        if ($highestMonthly === null || $champion['points'] > $highestMonthly['value']) {
            $highestMonthly = ['id' => $id, 'name' => $champion['name'], 'photo' => $champion['photo'], 'value' => $champion['points'], 'month' => $champion['month']];
        }
    }
    usort($titleCounts, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);

    return [
        'champions' => $champions,
        'records' => [
            ['label' => 'Most monthly titles', 'unit' => 'titles', 'player' => $titleCounts[0] ?? null],
            ['label' => 'Most Player of the Day awards', 'unit' => 'awards', 'player' => $pod],
            ['label' => 'Highest monthly points', 'unit' => 'points', 'player' => $highestMonthly],
            ['label' => 'Highest session score', 'unit' => 'points', 'player' => $sessionHigh],
            ['label' => 'Most career runs', 'unit' => 'runs', 'player' => $leader($pdo, 'runs')],
            ['label' => 'Most career wickets', 'unit' => 'wickets', 'player' => $leader($pdo, 'wickets')],
            ['label' => 'Most career catches', 'unit' => 'catches', 'player' => $leader($pdo, 'catches')],
        ],
    ];
}

function avatar_html(array $player, string $class = 'avatar'): string
{
    $name = trim((string) ($player['name'] ?? 'Player'));
    if (!empty($player['photo'])) {
        return '<img class="' . e($class) . '" src="' . e(url($player['photo'])) . '" alt="' . e($name) . '" loading="lazy">';
    }
    $initials = '';
    foreach (preg_split('/\s+/', $name) ?: [] as $part) {
        $initials .= substr($part, 0, 1);
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return '<span class="' . e($class) . ' avatar-fallback" aria-hidden="true">' . e(strtoupper($initials ?: 'L')) . '</span>';
}

function save_player_photo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose an image.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The image upload did not complete.');
    }
    if ((int) $file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The image must be 5 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || $dimensions === false) {
        throw new RuntimeException('Please upload a valid JPG, PNG, or WebP image.');
    }
    if ((int) $dimensions[0] * (int) $dimensions[1] > 40000000) {
        throw new RuntimeException('The image dimensions are too large.');
    }

    $directory = dirname(__DIR__) . '/assets/uploads/players';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload folder is not writable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $directory . '/' . $filename;

    if (extension_loaded('gd')) {
        $source = false;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $source = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $source = @imagecreatefrompng($file['tmp_name']);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $source = @imagecreatefromwebp($file['tmp_name']);
        }
        if ($source !== false) {
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, 1200 / max($width, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($newWidth, $newHeight);
            if ($mime !== 'image/jpeg') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
            }
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $saved = false;
            if ($mime === 'image/jpeg' && function_exists('imagejpeg')) {
                $saved = imagejpeg($target, $destination, 84);
            } elseif ($mime === 'image/png' && function_exists('imagepng')) {
                $saved = imagepng($target, $destination, 7);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                $saved = imagewebp($target, $destination, 84);
            }
            imagedestroy($source);
            imagedestroy($target);
            if ($saved) {
                return 'assets/uploads/players/' . $filename;
            }
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'assets/uploads/players/' . $filename;
}
