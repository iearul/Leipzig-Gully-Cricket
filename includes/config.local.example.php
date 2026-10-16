<?php
declare(strict_types=1);

// Copy this file to includes/config.local.php and fill in the values for your hosting account.
// config.local.php is listed in .gitignore: never commit it, never share it.

// Database details from Hostinger hPanel → Databases.
const DB_HOST = 'localhost';
const DB_NAME = 'u123456789_lgc_db';
const DB_USER = 'u123456789_lgc_user';
const DB_PASS = 'replace-with-the-database-password';

// Leave empty when installed at a domain/subdomain root. Example for a folder: '/lgc'
const BASE_URL = '';

// Player password emails. SITE_URL is the public address without a folder or trailing slash,
// e.g. 'https://lgc.example.com'. MAIL_FROM should be an address on that domain, e.g. 'noreply@lgc.example.com'.
// While either is empty, players cannot reset passwords themselves; admins can still copy password links.
const SITE_URL = '';
const MAIL_FROM = '';
