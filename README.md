# Leipzig Gully Cricket

**Casual Cricket. Serious Rankings.**

A lightweight PHP 8 + MariaDB/MySQL cricket statistics platform built for a small, player-first gully cricket community. There is no framework, package manager, build step, or command-line installation.

## Hostinger installation

1. In Hostinger hPanel, create a new MariaDB/MySQL database and database user. Save the host, database name, username, and password.
2. Open phpMyAdmin for that database and import `database.sql`.
3. Open `includes/config.php` in Hostinger File Manager and replace `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` with the database details from hPanel.
4. If the site will live in a folder instead of a domain or subdomain root, set `BASE_URL` in the same file (for example, `/lgc`). Otherwise leave it empty.
5. Upload the entire project to the chosen domain/subdomain directory. Keep the folder structure unchanged.
6. Visit `https://your-domain.example/setup.php`, create the first administrator, and use a unique password of at least 12 characters. Setup locks automatically after the account is created.
7. Visit `/admin/`, log in, and add the players.
8. Choose **Add session**, select the participants, enter their statistics, choose an optional Player of the Day, and save.

The upload directory `assets/uploads/players/` must be writable by PHP. Hostinger normally handles this automatically; if not, set that directory to permission `755` in File Manager.

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
- **Monthly winners:** completed past months appear automatically in the Hall of Fame. No manual winner selection is needed.

## Backups and updates

Before changing historical data or updating the files, export the database through phpMyAdmin and download `assets/uploads/players/`. Never place real database credentials in a public repository.

## Security notes

Database access uses PDO prepared statements. Admin forms use CSRF tokens; passwords use PHP's password hashing API; login attempts are rate-limited per browser session; uploads are MIME-checked, size-limited, renamed, and blocked from script execution. Destructive actions are POST-only and session deletion asks for confirmation.
