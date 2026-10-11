<?php
declare(strict_types=1);

// Player self-service: logins, password links, sign-up approval and player-submitted stats.

const PLAYER_PASSWORD_MIN = 8;
const RESET_LINK_MINUTES = 60;
const INVITE_LINK_MINUTES = 7 * 24 * 60;
const SUBMISSION_MAX_AGE_DAYS = 30;
const MAX_PENDING_REGISTRATIONS = 30;

function normalize_email(mixed $value): string
{
    return is_string($value) ? strtolower(trim($value)) : '';
}

function valid_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function text_fits(string $value, int $maxCharacters): bool
{
    return preg_match('/^.{0,' . $maxCharacters . '}$/us', $value) === 1;
}

function batting_style_options(): array
{
    return ['Right-handed', 'Left-handed'];
}

function password_problem(string $password, string $confirmation): ?string
{
    if (strlen($password) < PLAYER_PASSWORD_MIN) {
        return 'Use a password with at least ' . PLAYER_PASSWORD_MIN . ' characters.';
    }
    if (strlen($password) > 200) {
        return 'Use a password with at most 200 characters.';
    }
    if ($password !== $confirmation) {
        return 'The password confirmation does not match.';
    }
    return null;
}

function email_in_use(PDO $pdo, string $email, ?int $exceptPlayerId = null): bool
{
    $player = $pdo->prepare('SELECT COUNT(*) FROM players WHERE email = ? AND id <> ?');
    $player->execute([$email, $exceptPlayerId ?? 0]);
    if ((int) $player->fetchColumn() > 0) {
        return true;
    }
    $registration = $pdo->prepare('SELECT COUNT(*) FROM player_registrations WHERE email = ?');
    $registration->execute([$email]);
    return (int) $registration->fetchColumn() > 0;
}

function throttle_allows(string $key, int $limit, int $windowSeconds): bool
{
    $now = time();
    $hits = array_values(array_filter($_SESSION['throttle'][$key] ?? [], static fn($time): bool => (int) $time > $now - $windowSeconds));
    $_SESSION['throttle'][$key] = $hits;
    return count($hits) < $limit;
}

function throttle_hit(string $key): void
{
    $_SESSION['throttle'][$key][] = time();
}

function player_session_fingerprint(string $passwordHash): string
{
    return hash('sha256', $passwordHash);
}

function log_in_player(PDO $pdo, array $player): void
{
    session_regenerate_id(true);
    $_SESSION['player_id'] = (int) $player['id'];
    $_SESSION['player_auth'] = player_session_fingerprint((string) $player['password_hash']);
    $pdo->prepare('UPDATE players SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $player['id']]);
}

function log_out_player(): void
{
    unset($_SESSION['player_id'], $_SESSION['player_auth']);
    session_regenerate_id(true);
}

// The session stays valid only while the player keeps the same email login and password.
function current_player(): ?array
{
    $playerId = $_SESSION['player_id'] ?? null;
    if (!is_int($playerId)) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM players WHERE id = ? AND email IS NOT NULL AND password_hash IS NOT NULL');
    $statement->execute([$playerId]);
    $player = $statement->fetch();
    if (!$player || !hash_equals((string) ($_SESSION['player_auth'] ?? ''), player_session_fingerprint($player['password_hash']))) {
        unset($_SESSION['player_id'], $_SESSION['player_auth']);
        return null;
    }
    return $player;
}

function require_player(): array
{
    $player = current_player();
    if (!$player) {
        flash('error', 'Please log in first.');
        redirect('account/');
    }
    return $player;
}

// Emailed links must not trust the Host header; only admin-created links may fall back to it.
function absolute_url(string $path, bool $allowRequestHost = false): ?string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/') . url($path);
    }
    if (!$allowRequestHost || empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . url($path);
}

function mail_configured(): bool
{
    return SITE_URL !== '' && valid_email(MAIL_FROM) && function_exists('mail');
}

function send_mail(string $to, string $subject, string $body): bool
{
    if (!valid_email(MAIL_FROM) || !valid_email($to) || !function_exists('mail')) {
        return false;
    }
    $headers = [
        'From' => APP_NAME . ' <' . MAIL_FROM . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    return @mail($to, str_replace(["\r", "\n"], ' ', $subject), $body, $headers, '-f' . MAIL_FROM);
}

function password_link_email(string $name, string $link, bool $invite): string
{
    $intro = $invite
        ? "An LGC admin created a player login for you. Open this link to choose your password (valid for 7 days):"
        : "Someone asked to reset the password of your LGC player login. If it was you, open this link within 60 minutes:";
    $outro = $invite
        ? "Afterwards you can log in with this email address to update your photo and submit your match stats."
        : "If you did not ask for this, ignore this email. Your password stays the same.";
    return "Hi $name,\n\n$intro\n\n$link\n\n$outro\n\n" . APP_NAME;
}

function create_password_link(PDO $pdo, int $playerId, int $validMinutes): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE player_id = ? AND used_at IS NULL')->execute([$playerId]);
    $pdo->prepare('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 30 DAY')->execute();
    $pdo->prepare('INSERT INTO password_resets (player_id, token_hash, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE, UTC_TIMESTAMP())')
        ->execute([$playerId, hash('sha256', $token), $validMinutes]);
    return $token;
}

function recent_password_link_exists(PDO $pdo, int $playerId, int $minutes): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE player_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE');
    $statement->execute([$playerId, $minutes]);
    return (int) $statement->fetchColumn() > 0;
}

function find_password_link(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT p.id, p.name, p.email FROM password_resets r INNER JOIN players p ON p.id = r.player_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > UTC_TIMESTAMP() AND p.email IS NOT NULL LIMIT 1'
    );
    $statement->execute([hash('sha256', $token)]);
    return $statement->fetch() ?: null;
}

function set_player_password(PDO $pdo, int $playerId, string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE players SET password_hash = ? WHERE id = ?')->execute([$hash, $playerId]);
    $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE player_id = ? AND used_at IS NULL')->execute([$playerId]);
    return $hash;
}

function delete_uploaded_photo(?string $path): void
{
    if ($path && preg_match('#^assets/uploads/players/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
        @unlink(dirname(__DIR__) . '/' . $path);
    }
}

// Approves a sign-up as a new player, or attaches the login to an existing player who has none yet.
function approve_registration(PDO $pdo, int $registrationId, ?int $linkPlayerId = null): int
{
    $unusedPhoto = null;
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM player_registrations WHERE id = ? FOR UPDATE');
        $statement->execute([$registrationId]);
        $registration = $statement->fetch();
        if (!$registration) {
            throw new RuntimeException('This sign-up was already handled.');
        }
        $conflict = $pdo->prepare('SELECT COUNT(*) FROM players WHERE email = ? AND id <> ?');
        $conflict->execute([$registration['email'], $linkPlayerId ?? 0]);
        if ((int) $conflict->fetchColumn() > 0) {
            throw new RuntimeException('Another player already uses ' . $registration['email'] . '.');
        }

        if ($linkPlayerId !== null) {
            $target = $pdo->prepare('SELECT id, photo, password_hash FROM players WHERE id = ? FOR UPDATE');
            $target->execute([$linkPlayerId]);
            $player = $target->fetch();
            if (!$player) {
                throw new RuntimeException('That player no longer exists.');
            }
            if ($player['password_hash'] !== null) {
                throw new RuntimeException('That player already has a login.');
            }
            $pdo->prepare(
                'UPDATE players SET email = ?, password_hash = ?, nickname = COALESCE(nickname, ?), photo = COALESCE(photo, ?),
                    batting_style = COALESCE(batting_style, ?), bowling_style = COALESCE(bowling_style, ?) WHERE id = ?'
            )->execute([$registration['email'], $registration['password_hash'], $registration['nickname'], $registration['photo'],
                $registration['batting_style'], $registration['bowling_style'], $linkPlayerId]);
            $playerId = $linkPlayerId;
            if ($player['photo'] !== null) {
                $unusedPhoto = $registration['photo'];
            }
        } else {
            $pdo->prepare(
                'INSERT INTO players (name, email, password_hash, nickname, photo, batting_style, bowling_style, joined_date, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
            )->execute([$registration['name'], $registration['email'], $registration['password_hash'], $registration['nickname'],
                $registration['photo'], $registration['batting_style'], $registration['bowling_style'], date('Y-m-d')]);
            $playerId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM player_registrations WHERE id = ?')->execute([$registrationId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    delete_uploaded_photo($unusedPhoto);
    return $playerId;
}

function reject_registration(PDO $pdo, int $registrationId): void
{
    $statement = $pdo->prepare('SELECT photo FROM player_registrations WHERE id = ?');
    $statement->execute([$registrationId]);
    $photo = $statement->fetchColumn();
    $pdo->prepare('DELETE FROM player_registrations WHERE id = ?')->execute([$registrationId]);
    delete_uploaded_photo(is_string($photo) ? $photo : null);
}

function stat_fields(): array
{
    return ['runs', 'balls_faced', 'fours', 'sixes', 'is_out', 'wickets', 'balls_bowled', 'runs_conceded', 'catches', 'run_outs', 'stumpings'];
}

/** @return array{0: array<string, ?int>, 1: ?string} values keyed by stat_fields() and an error message */
function parse_stat_input(array $input): array
{
    $values = [];
    foreach (stat_fields() as $field) {
        $raw = $input[$field] ?? '';
        if (!is_string($raw) && !is_int($raw)) {
            return [$values, 'Use whole numbers from 0 to 999.'];
        }
        $raw = trim((string) $raw);
        if ($field === 'is_out') {
            $values[$field] = in_array($raw, ['0', '1'], true) ? (int) $raw : null;
            continue;
        }
        if ($raw === '') {
            $values[$field] = null;
            continue;
        }
        $number = filter_var($raw, FILTER_VALIDATE_INT);
        if ($number === false || $number < 0 || $number > 999) {
            return [$values, 'Use whole numbers from 0 to 999.'];
        }
        $values[$field] = $number;
    }
    $numbers = array_filter($values, static fn(?int $value, string $field): bool => $field !== 'is_out' && $value !== null, ARRAY_FILTER_USE_BOTH);
    if (!$numbers) {
        return [$values, 'Enter at least one number.'];
    }
    if ($values['runs'] !== null && 4 * (int) $values['fours'] + 6 * (int) $values['sixes'] > $values['runs']) {
        return [$values, 'Your fours and sixes add up to more runs than you scored.'];
    }
    return [$values, null];
}

function submission_date_problem(string $date, ?string $today = null): ?string
{
    $today ??= date('Y-m-d');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return 'Choose a valid date.';
    }
    if ($date > $today) {
        return 'The date cannot be in the future.';
    }
    $oldest = (new DateTimeImmutable($today))->modify('-' . SUBMISSION_MAX_AGE_DAYS . ' days')->format('Y-m-d');
    if ($date < $oldest) {
        return 'You can submit stats from the last ' . SUBMISSION_MAX_AGE_DAYS . ' days. Ask an admin for older games.';
    }
    return null;
}

// One pending entry per player and date: sending the same date again updates it.
function save_stat_submission(PDO $pdo, int $playerId, string $date, array $values, ?int $editId = null): int
{
    $sameDate = $pdo->prepare("SELECT id FROM stat_submissions WHERE player_id = ? AND session_date = ? AND status = 'pending' LIMIT 1");
    $sameDate->execute([$playerId, $date]);
    $sameDateId = (int) $sameDate->fetchColumn() ?: null;
    $targetId = $sameDateId;
    if ($editId !== null) {
        $own = $pdo->prepare("SELECT COUNT(*) FROM stat_submissions WHERE id = ? AND player_id = ? AND status = 'pending'");
        $own->execute([$editId, $playerId]);
        if ((int) $own->fetchColumn() === 0) {
            throw new RuntimeException('This entry was already reviewed by an admin.');
        }
        if ($sameDateId !== null && $sameDateId !== $editId) {
            throw new RuntimeException('You already sent stats for that date. Edit that entry instead.');
        }
        $targetId = $editId;
    }

    $fields = stat_fields();
    $ordered = array_map(static fn(string $field): ?int => $values[$field] ?? null, $fields);
    if ($targetId !== null) {
        $assignments = implode(', ', array_map(static fn(string $field): string => "$field = ?", $fields));
        $pdo->prepare("UPDATE stat_submissions SET session_date = ?, $assignments WHERE id = ?")->execute([$date, ...$ordered, $targetId]);
        return $targetId;
    }
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $pdo->prepare('INSERT INTO stat_submissions (player_id, session_date, ' . implode(', ', $fields) . ") VALUES (?, ?, $placeholders)")
        ->execute([$playerId, $date, ...$ordered]);
    return (int) $pdo->lastInsertId();
}

// Copies pending entries of one date onto a scorecard. A null session creates a new completed session on that date.
function approve_stat_submissions(PDO $pdo, array $submissionIds, ?int $sessionId): int
{
    $submissionIds = array_values(array_unique(array_filter(array_map('intval', $submissionIds), static fn(int $id): bool => $id > 0)));
    if (!$submissionIds) {
        throw new RuntimeException('Nothing selected to approve.');
    }
    $pdo->beginTransaction();
    try {
        $placeholders = implode(', ', array_fill(0, count($submissionIds), '?'));
        $statement = $pdo->prepare("SELECT * FROM stat_submissions WHERE id IN ($placeholders) AND status = 'pending' FOR UPDATE");
        $statement->execute($submissionIds);
        $submissions = $statement->fetchAll();
        if (count($submissions) !== count($submissionIds)) {
            throw new RuntimeException('Some entries were already reviewed. Refresh the page and try again.');
        }
        $dates = array_values(array_unique(array_column($submissions, 'session_date')));
        if (count($dates) !== 1) {
            throw new RuntimeException('Approve entries one date at a time.');
        }

        if ($sessionId === null) {
            $pdo->prepare("INSERT INTO sessions (session_date, status) VALUES (?, 'completed')")->execute([$dates[0]]);
            $sessionId = (int) $pdo->lastInsertId();
        } else {
            $session = $pdo->prepare('SELECT session_date FROM sessions WHERE id = ? FOR UPDATE');
            $session->execute([$sessionId]);
            if ($session->fetchColumn() !== $dates[0]) {
                throw new RuntimeException('Choose a session from the same date.');
            }
        }

        $fields = stat_fields();
        $existing = $pdo->prepare('SELECT id FROM session_players WHERE session_id = ? AND player_id = ?');
        $update = $pdo->prepare('UPDATE session_players SET ' . implode(', ', array_map(static fn(string $field): string => "$field = ?", $fields)) . ' WHERE id = ?');
        $insert = $pdo->prepare('INSERT INTO session_players (session_id, player_id, ' . implode(', ', $fields) . ', calculated_points) VALUES (?, ?, ' . implode(', ', array_fill(0, count($fields), '?')) . ', 0)');
        $markApproved = $pdo->prepare("UPDATE stat_submissions SET status = 'approved', session_id = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?");
        foreach ($submissions as $submission) {
            $values = array_map(static fn(string $field): ?int => $submission[$field] === null ? null : (int) $submission[$field], $fields);
            $existing->execute([$sessionId, $submission['player_id']]);
            $rowId = $existing->fetchColumn();
            if ($rowId) {
                $update->execute([...$values, $rowId]);
            } else {
                $insert->execute([$sessionId, $submission['player_id'], ...$values]);
            }
            $markApproved->execute([$sessionId, $submission['id']]);
        }
        recalculate_session_points($pdo, $sessionId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return $sessionId;
}

function reject_stat_submission(PDO $pdo, int $submissionId): void
{
    $pdo->prepare("UPDATE stat_submissions SET status = 'rejected', reviewed_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'pending'")->execute([$submissionId]);
}

function pending_approval_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT (SELECT COUNT(*) FROM player_registrations) + (SELECT COUNT(*) FROM stat_submissions WHERE status = 'pending')")->fetchColumn();
}

/** @return array{batting: ?string, bowling: ?string, fielding: ?string} */
function stat_summary(array $row): array
{
    $value = static fn(string $key): ?int => isset($row[$key]) && $row[$key] !== null ? (int) $row[$key] : null;
    $batting = null;
    if ($value('runs') !== null || $value('balls_faced') !== null || $value('is_out') !== null) {
        $parts = [($value('runs') ?? '?') . ' runs' . ($value('balls_faced') !== null ? ' (' . $value('balls_faced') . ' balls)' : '')];
        if ($value('fours')) {
            $parts[] = $value('fours') . '×4';
        }
        if ($value('sixes')) {
            $parts[] = $value('sixes') . '×6';
        }
        if ($value('is_out') !== null) {
            $parts[] = $value('is_out') ? 'out' : 'not out';
        }
        $batting = implode(' · ', $parts);
    }
    $bowling = null;
    if ($value('wickets') !== null || $value('balls_bowled') !== null || $value('runs_conceded') !== null) {
        $bowling = ($value('wickets') ?? 0) . ($value('wickets') === 1 ? ' wkt' : ' wkts');
        if ($value('runs_conceded') !== null) {
            $bowling .= ' · ' . $value('runs_conceded') . ' runs';
        }
        if ($value('balls_bowled') !== null) {
            $bowling .= ' · ' . balls_to_overs($value('balls_bowled')) . ' ov';
        }
    }
    $fielding = [];
    foreach (['catches' => 'ct', 'run_outs' => 'run out', 'stumpings' => 'st'] as $key => $label) {
        if ($value($key)) {
            $fielding[] = $value($key) . ' ' . $label;
        }
    }
    return ['batting' => $batting, 'bowling' => $bowling, 'fielding' => $fielding ? implode(' · ', $fielding) : null];
}

function format_utc_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('j M Y');
}
