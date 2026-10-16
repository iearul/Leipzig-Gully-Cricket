-- Leipzig Gully Cricket: match-day draw (two teams with a toss, or a batting order).
-- Import this file ONCE in phpMyAdmin on an existing LGC database, before uploading the new PHP files.
-- Fresh installs do not need it: database.sql already contains this table.
SET NAMES utf8mb4;

CREATE TABLE draws (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    draw_date DATE NOT NULL,
    mode ENUM('teams', 'order') NOT NULL,
    result TEXT NOT NULL,
    toss_choice ENUM('bat', 'bowl') NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
