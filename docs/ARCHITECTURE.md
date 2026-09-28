# Architecture

## Request flow

```
Browser ─▶ Apache (.htaccess) ─▶ public/index.php
                                   │
                                   ├─ src/bootstrap.php   env, autoload, base path
                                   ├─ Security::startSession
                                   └─ App::handle(Request)
                                        ├─ Router::match   (src/routes.php)
                                        ├─ CSRF check for every POST except the M-Pesa callback
                                        ├─ Controller → View (templates/*.php) or JSON
                                        └─ Security::apply headers (CSP with nonce, HSTS on HTTPS, …)
```

`App` is a small container. Services (PDO, content repository, tip service)
are created lazily, so pages that never touch MySQL never open a connection.

## Content vs. database

| Kind | Where | Why |
| --- | --- | --- |
| Site settings, projects, skills, experience, achievements, CV list | `data/*.json` | Versioned in git, readable, edited through `/admin` with history + restore |
| Tips, messages, CV download counts, rate limits | MySQL | Transactional, append-only guarantees, and many small writes |

`CollectionSchema` defines every JSON collection's fields once. The admin
form, the validator and the writer all read from it, so adding a field is a
one-line change.

## Buy Me a Tea

```
tea.js ──POST /api/tips──▶ SupportController::start
                             ├─ validate phone + amount (no DB writes on bad input)
                             ├─ rate limit (per IP and per phone)
                             └─ TipService::start
                                  ├─ ledger: REQUESTED
                                  ├─ DarajaClient::stkPush
                                  └─ ledger: SENT (or FAILED)
Safaricom ──POST /api/mpesa/callback/{token}──▶ TipService::handleCallback
                                  └─ ledger: COMPLETED | CANCELLED | FAILED
                                     (unique dedupe_key: a duplicate becomes DUPLICATE_CALLBACK)
tea.js ──GET /api/tips/{id} every 3 s──▶ TipService::status
cron ──bin/reconcile-tips.php──▶ TipService::reconcile (stkQuery for tips pending > 5 min)
```

`DarajaClient` has two implementations: `HttpDarajaClient` (sandbox/production)
and `FakeDarajaClient` (offline). The fake one still drives the real callback
handler, so local testing covers the same code path as production.

## Security notes

- **CSP:** `default-src 'self'`. The only inline script (the theme bootstrap) carries a per-request nonce, and no inline styles or handlers are used.
- **CSRF:** a session token sent as the `_csrf` form field or the `X-CSRF-Token` header.
- **Admin:** a single user, with the password hash in `.env`. Sessions are regenerated on login and expire after 2 hours idle. Login is rate limited.
- **Uploads:** checked by MIME type and size. File names are rebuilt server-side. CV PDFs are stored outside the web root and streamed by `CvController`.
- **Private data:** phone numbers and IPs are stored only as HMACs (`Security::hash`).
- **Ledger:** triggers reject `UPDATE`/`DELETE`, and in production the DB user is granted only `SELECT, INSERT` on `tip_ledger`.

## Frontend

- `main.css`: design tokens on `:root`, with the dark blueprint as the default and a light "paper" theme under `[data-theme="light"]`.
- JS modules, all progressive enhancement:
  - `theme.js`
  - `terminal.js` (the CLI console, which reads `/api/content`)
  - `tea.js`
  - `filter.js`
  - `admin.js`
- Without JS, terminal chips are anchor links, tag filters are `?tag=` links, and the tea form posts to a status page that refreshes itself.
