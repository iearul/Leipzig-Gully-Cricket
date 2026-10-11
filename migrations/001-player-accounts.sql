-- Leipzig Gully Cricket: player logins, sign-up approval, password links and player-submitted stats.
-- Import this file ONCE in phpMyAdmin on an existing LGC database, before uploading the new PHP files.
-- Fresh installs do not need it: database.sql already contains these changes.
SET NAMES utf8mb4;

ALTER TABLE players
    ADD COLUMN email VARCHAR(190) NULL AFTER name,
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN last_login_at DATETIME NULL AFTER password_hash,
    ADD UNIQUE KEY uq_players_email (email);

CREATE TABLE player_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    nickname VARCHAR(80) NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    photo VARCHAR(255) NULL,
    batting_style VARCHAR(80) NULL,
    bowling_style VARCHAR(80) NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_registrations_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_password_resets_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY uq_password_resets_token (token_hash),
    INDEX idx_password_resets_player (player_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stat_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    runs INT UNSIGNED NULL,
    balls_faced INT UNSIGNED NULL,
    fours INT UNSIGNED NULL,
    sixes INT UNSIGNED NULL,
    is_out TINYINT(1) NULL,
    wickets INT UNSIGNED NULL,
    balls_bowled INT UNSIGNED NULL,
    runs_conceded INT UNSIGNED NULL,
    catches INT UNSIGNED NULL,
    run_outs INT UNSIGNED NULL,
    stumpings INT UNSIGNED NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    session_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stat_submissions_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_stat_submissions_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    INDEX idx_stat_submissions_status (status, session_date),
    INDEX idx_stat_submissions_player (player_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
