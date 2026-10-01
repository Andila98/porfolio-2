# Portfolio

Personal portfolio in plain **HTML, CSS, JavaScript and PHP**, styled as a live
system's architecture blueprint. It includes a CLI console, versioned project
releases, CV downloads, a private admin page, and **Buy Me a Tea** via M-Pesa
STK Push with an append-only payment ledger.

- No framework and no frontend build step. Composer is only needed for PHPMailer (email) and PHPUnit (tests).
- Content lives in JSON files in `storage/content/` (outside git) and is edited through `/admin`. `data/` holds the starting copy that is seeded in on first run, so deploys never overwrite your edits.
- MySQL/MariaDB stores tips, messages, CV download counts and rate limits.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for how the pieces fit together.

---

## Run it locally with XAMPP

1. **Get the code** into XAMPP's `htdocs` folder:
   ```bash
   cd C:\xampp\htdocs        # macOS: /Applications/XAMPP/htdocs
   git clone https://github.com/Andila98/porfolio-2.git
   ```
2. **Install PHP packages** (optional, but needed for email): `composer install`.
   Without Composer the site still runs; the contact form then only saves messages.
3. **Create the database.** Open phpMyAdmin (`http://localhost/phpmyadmin`),
   create a database named `portfolio` (collation `utf8mb4_unicode_ci`),
   select it, then **Import** `database/schema.sql`.
   Optionally import `database/seed-dev.sql` for sample rows.
   (Imported an earlier version of the schema? Drop the four tables and import
   again; `tip_ledger` gained an `environment` column before go-live.)
4. **Configure.** Copy `.env.example` to `.env`. The XAMPP defaults (`root`, empty
   password) already match. Set `APP_URL=http://localhost/porfolio-2` and
   `APP_SECRET` to a long random string:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
5. **Create your admin password:**
   ```bash
   php bin/hash-password.php
   ```
   Paste the printed `ADMIN_PASSWORD_HASH='…'` line into `.env`.
6. Start Apache and MySQL in the XAMPP control panel and open
   **http://localhost/porfolio-2/**. Admin: **http://localhost/porfolio-2/admin**.

> The repository-root `.htaccess` routes everything into `public/`, so `.env`,
> `src/`, `data/` and `storage/` can never be downloaded. Make sure Apache's `mod_rewrite`
> is enabled (it is by default in XAMPP).

**Without XAMPP:** point any PHP 8.2+ at MySQL/MariaDB and run
`php -S 127.0.0.1:8000 -t public public/index.php`.

## Buy Me a Tea (M-Pesa)

`MPESA_ENV` picks the driver:

| Value | What happens |
| --- | --- |
| `fake` (default) | No network. The STK Push "succeeds" and a simulated Safaricom callback arrives ~5 s later. Amount **13** simulates a cancel, **14** a failed payment. |
| `sandbox` | Real Daraja sandbox. Needs consumer key/secret, passkey and a **public HTTPS** `MPESA_CALLBACK_BASE` (use a tunnel such as ngrok locally). |
| `production` | Live payments. Needs an approved Till or Paybill (`MPESA_TYPE=till` + `MPESA_TILL_NUMBER`, or `paybill`). |

How payments are recorded:

- Every step is a new row in `tip_ledger`: `REQUESTED`, then `SENT` or `FAILED`, then `COMPLETED`, `CANCELLED` or `FAILED`.
- Rows are never edited. Database triggers reject `UPDATE`/`DELETE`, and in production the app's DB user only has `SELECT, INSERT` on the table.
- A tip can settle only once. Repeat callbacks are stored as `DUPLICATE_CALLBACK` and never counted.
- Phone numbers are stored as an HMAC hash plus the last 3 digits.
- Callbacks go to `/api/mpesa/callback/<token>`. The token is derived from `APP_SECRET`, so keep that secret stable.
- `bin/reconcile-tips.php` asks Daraja about tips still pending after 5 minutes (run it from cron). Only final STK Query codes settle a tip; "still processing" answers are retried on the next run.
- Every row records `MPESA_ENV` in its `environment` column. Totals and the dashboard count **production** rows only, so sandbox testing on the live site never inflates the numbers.
- In production the app refuses to start with `MPESA_ENV=fake` or a weak `APP_SECRET`, so a misconfigured deploy fails its health check instead of taking fake payments.

### About `APP_SECRET`

Set it once and keep it. Changing it:

- changes the callback URL, so callbacks for payments already in flight get `403` (reconcile settles those tips within minutes);
- resets rate-limit counters;
- means new phone hashes no longer match old ones, so the same number looks like a new payer.

## Admin (`/admin`)

- Edit site settings, projects, achievements, skills, experience and CV versions.
- Every save keeps the previous file in `storage/content/history/`; **Restore** undoes a change.
- Upload CV PDFs (served at `/cv/<key>`) and project screenshots.
- Tip ledger with monthly totals and CSV export, plus the contact-form inbox.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Database tests use `DB_TEST_*` (defaults in `phpunit.xml`: `portfolio_test` on
`127.0.0.1` as `root` with no password, which suits XAMPP). Create that empty
database first. The tests rebuild the schema themselves. When no database is
reachable, the DB tests are skipped locally but **fail** in CI (`CI=true`).

## Deploy (DigitalOcean Droplet beside Ilado FMS)

The production stack (`docker-compose.yml`) runs two containers:

- `app`: PHP 8.3 + Apache, the same `.htaccess` setup as XAMPP.
- `db`: MySQL 8.4, tuned for about 150–200 MB of RAM.

It joins Ilado FMS's existing **Traefik** network, and Traefik routes the
domain and issues the Let's Encrypt certificate. No ports are published, and
Ilado's configuration is untouched.

1. Check free memory on the Droplet: `free -m` (the stack needs roughly 350 MB).
2. Clone the repo, e.g. to `/opt/portfolio`, and create `.env` from
   `.env.example`. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`,
   `SITE_DOMAIN`, `DB_USER`/`DB_PASS` (a new app user, **not** root),
   `DB_ROOT_PASS`, `APP_SECRET`, `ADMIN_PASSWORD_HASH`, SMTP and M-Pesa values,
   and the Traefik names used by Ilado's stack (`TRAEFIK_NETWORK`,
   `TRAEFIK_ENTRYPOINT`, `TRAEFIK_CERTRESOLVER`).
3. Point the domain's DNS `A` record at the Droplet, then run:
   `docker compose up -d --build`.
   On first start MySQL imports `database/schema.sql` and creates the
   least-privilege app user (`docker/mysql/02-grants.sh`).
4. Add the cron jobs (`crontab -e` on the Droplet):
   ```cron
   */5 * * * * cd /opt/portfolio && docker compose exec -T app php bin/reconcile-tips.php >> /var/log/portfolio-reconcile.log 2>&1
   15 2 * * *  /opt/portfolio/bin/backup-db.sh >> /var/log/portfolio-backup.log 2>&1
   ```

**Continuous deployment:** `.github/workflows/deploy.yml` deploys on every push
to `main` over SSH. Add these repository secrets: `DO_HOST`, `DO_USER`,
`DO_SSH_KEY`, `DO_APP_DIR`. `ci.yml` lints and runs the tests (against a MySQL
service) on every push and pull request.

**Schema changes** after go-live: see [`database/changes/README.md`](database/changes/README.md).

## Project layout

```
public/      web root: index.php (front controller), .htaccess, assets
src/         PHP classes (App\ namespace) + routes.php + bootstrap.php
templates/   PHP templates: layout, partials, pages, admin
data/        seed content, copied into storage/content on first run
database/    schema.sql, seed-dev.sql, changes/
bin/         CLI scripts: hash-password, reconcile-tips, backup-db
storage/     content (live JSON + history), cache, logs, CV PDFs (not in git)
docker/      Dockerfile, Apache/PHP/MySQL config
tests/       PHPUnit tests
```
