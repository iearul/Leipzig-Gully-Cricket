# Leipzig Gully Cricket

**Casual Cricket. Serious Rankings.**

A lightweight PHP 8 + MariaDB/MySQL cricket statistics platform built for a small, player-first gully cricket community. There is no framework, package manager, build step, or command-line installation.

## Hostinger installation

1. In Hostinger hPanel, create a new MariaDB/MySQL database and database user. Save the host, database name, username, and password.
2. Open phpMyAdmin for that database and import `database.sql`.
3. Open `includes/config.php` in Hostinger File Manager and replace `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` with the database details from hPanel.
4. If the site will live in a folder instead of a domain or subdomain root, set `BASE_URL` in the same file (for example, `/lgc`). Otherwise leave it empty.
5. For player password emails, set `SITE_URL` (for example, `https://lgc.example.com`, no folder, no trailing slash) and `MAIL_FROM` (an address on the same domain, for example `noreply@lgc.example.com`). Create that mailbox in hPanel if Hostinger asks for a real sender.
6. Upload the entire project to the chosen domain/subdomain directory. Keep the folder structure unchanged.
7. Visit `https://your-domain.example/setup.php`, create the first administrator, and use a unique password of at least 12 characters. Setup locks automatically after the account is created.
8. Visit `/admin/`, log in, and add the players.
9. Choose **Add session**, select the participants, enter their statistics, choose an optional Player of the Day, and save.

The upload directory `assets/uploads/players/` must be writable by PHP. Hostinger normally handles this automatically; if not, set that directory to permission `755` in File Manager.

## Updating an existing site to player logins

1. Export the database in phpMyAdmin as a backup.
2. Import `migrations/001-player-accounts.sql` once. Do this **before** uploading the new files; the new admin pages need the new tables.
3. Upload the new files and set `SITE_URL` and `MAIL_FROM` in `includes/config.php`.

## Updating an existing site to the match-day draw

1. Export the database in phpMyAdmin as a backup.
2. Import `migrations/002-draws.sql` once, **before** uploading the new files.
3. Upload the new files.

## Server requirements

- PHP 8.0 or newer
- MariaDB 10.4+ or MySQL 8+
- PHP extensions: PDO MySQL, Fileinfo
- GD is optional but recommended for automatic image resizing and compression
- Apache with `.htaccess` support (standard on Hostinger shared hosting)

Set the site to PHP 8+ and enable HTTPS in hPanel. Secure session cookies switch on automatically under HTTPS.

## Optional demo data

`database.sql` is production-clean. For a private preview on a fresh, empty database, import `demo-data.sql` after `database.sql`. Do not import it on the production site. The demo file contains players and sessions but no administrator account.

## Everyday administration

- **Players:** add, edit, upload a photo, or mark a player inactive. Players with history are preserved.
- **Sessions:** create, edit, or delete scorecards. Editing old data updates all affected totals and rankings.
- **Exclude:** an excluded participant remains visible on that scorecard but contributes no session, statistics, points, or ranking data.
- **Point settings:** changing a value immediately recalculates all historical session points.
- **Player logins:** enter a player's email on their admin profile, then choose **Create password link**. The link is emailed and also shown for copying or sharing on WhatsApp; it works once, for 7 days. Players can also use **Forgot password** on the player login page once their email is saved. Removing the email removes the login.
- **New players:** anyone can sign up at `/account/register.php`. Sign-ups appear under **Approvals**; approve them as a new player, link them to an existing player who has no login yet (avoids duplicates), or reject them.
- **Player stats:** logged-in players submit their own numbers for a match date (last 30 days) at `/account/stats.php`. Under **Approvals**, entries are grouped by date: choose the scorecard (or a new session on that date) and approve one entry or all of them. Approved numbers replace that player's numbers on the scorecard; bonus, penalty, exclusion and Player of the Day stay with the admin. Nothing counts before approval.
- **Draw:** on match day, open **Draw**, tick who is playing and type any guests (one name per line; guests are for that draw only and get no stats). **Draw two teams** splits everyone randomly (with an odd count one team gets the extra player) and tosses a coin; record whether the toss winner bats or bowls first. **Draw batting order** gives everyone a random number, for days with too few players for two teams. Drawing again replaces the draw. Today's draw shows on the homepage; **Copy for WhatsApp** copies it for the group, and **Start session** opens a new scorecard with the drawn players ticked and the teams in the team information.
- **Monthly winners:** completed past months appear automatically in the Hall of Fame. No manual winner selection is needed.

## Backups and updates

Before changing historical data or updating the files, export the database through phpMyAdmin and download `assets/uploads/players/`. Never place real database credentials in a public repository.

## Security notes

Player password links are random, stored only as SHA-256 hashes, single-use and time-limited; emailed links always use `SITE_URL`, never the request's host. A player's session ends when their password or email login changes. Sign-ups have a hidden spam trap, a per-browser limit, and a cap of 30 waiting sign-ups.

Database access uses PDO prepared statements. Admin forms use CSRF tokens; passwords use PHP's password hashing API; login attempts are rate-limited per browser session; uploads are MIME-checked, size-limited, renamed, and blocked from script execution. Destructive actions are POST-only and session deletion asks for confirmation.
