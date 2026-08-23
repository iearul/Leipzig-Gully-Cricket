<?php
declare(strict_types=1);

// Leipzig Gully Cricket — edit only the database values for your hosting account.
const DB_HOST = 'localhost';
const DB_NAME = 'u742141420_lgc_db';
const DB_USER = 'u742141420_lgc_user';
const DB_PASS = 'g:ac#+8R';

// Leave empty when installed at a domain/subdomain root. Example for a folder: '/lgc'
const BASE_URL = '';
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
