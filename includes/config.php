<?php
declare(strict_types=1);

// Leipzig Gully Cricket — do not put passwords in this file; it is public on GitHub.
// Hosting values live in includes/config.local.php, which git ignores. Copy
// config.local.example.php to config.local.php on the server and fill it in.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Placeholders for anything config.local.php leaves out. With no database login the site shows
// "temporarily unavailable"; with no SITE_URL or MAIL_FROM players cannot reset passwords themselves.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', '');
defined('DB_USER') || define('DB_USER', '');
defined('DB_PASS') || define('DB_PASS', '');
defined('BASE_URL') || define('BASE_URL', '');
defined('SITE_URL') || define('SITE_URL', '');
defined('MAIL_FROM') || define('MAIL_FROM', '');

const APP_NAME = 'Leipzig Gully Cricket';
const APP_TAGLINE = 'Casual Cricket. Serious Rankings.';
const APP_TIMEZONE = 'Europe/Berlin';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    session_name('lgc_session');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
