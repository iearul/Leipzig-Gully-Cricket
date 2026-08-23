SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    nickname VARCHAR(80) NULL,
    photo VARCHAR(255) NULL,
    player_number VARCHAR(12) NULL,
    batting_style VARCHAR(80) NULL,
    bowling_style VARCHAR(80) NULL,
    nationality_code CHAR(2) NULL,
    bio TEXT NULL,
    joined_date DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_players_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_date DATE NOT NULL,
    location VARCHAR(160) NOT NULL DEFAULT 'Grünau, Leipzig',
    title VARCHAR(160) NULL,
    notes TEXT NULL,
    team_info TEXT NULL,
    status ENUM('draft', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
    player_of_day_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_pod FOREIGN KEY (player_of_day_id) REFERENCES players(id) ON DELETE SET NULL,
    INDEX idx_sessions_date_status (session_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE session_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
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
    bonus_points INT UNSIGNED NOT NULL DEFAULT 0,
    penalty_points INT UNSIGNED NOT NULL DEFAULT 0,
    excluded TINYINT(1) NOT NULL DEFAULT 0,
    admin_note VARCHAR(255) NULL,
    calculated_points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_players_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_players_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_session_player (session_id, player_id),
    INDEX idx_session_players_player (player_id),
    INDEX idx_session_players_eligible (session_id, excluded)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE point_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value DECIMAL(10,2) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO point_settings (setting_key, setting_value) VALUES
('run_point', 1), ('four_bonus', 1), ('six_bonus', 2),
('thirty_bonus', 5), ('fifty_bonus', 10), ('hundred_bonus', 25),
('wicket_point', 15), ('three_wicket_bonus', 10), ('five_wicket_bonus', 25),
('catch_point', 5), ('run_out_point', 7), ('stumping_point', 7),
('player_of_day_bonus', 10);
