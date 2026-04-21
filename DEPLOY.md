# Production Deployment Runbook — Apna Invoice

This is a hand-off checklist for taking the app from local dev to a live
production server. Follow top-to-bottom on a fresh box.

## Server prerequisites

- **PHP 8.2+** with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- **Composer 2.x**.
- **Node 20+** and `npm` for the Vite build.
- **MySQL 8.0+** (or MariaDB 10.6+). SQLite works but is not recommended for production.
- **A cron-capable host** (any Linux VM is fine).
- **SMTP credentials** from a provider whose `MAIL_FROM_ADDRESS` domain is verified (AWS SES, Mailgun, SendGrid, Google Workspace SMTP, Zoho Mail, etc.).

## First deploy

### 1. Clone and install dependencies

```bash
git clone <repo> /var/www/apna-invoice
cd /var/www/apna-invoice
composer install --optimize-autoloader --no-dev
npm ci
npm run build
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate        # sets APP_KEY; mandatory, breaks signed URLs + sessions without it
```

Edit `.env`:

| Setting                       | Production value                                           |
|-------------------------------|------------------------------------------------------------|
| `APP_ENV`                     | `production`                                               |
| `APP_DEBUG`                   | `false`                                                    |
| `APP_URL`                     | `https://your-domain.com`                                  |
| `APP_TIMEZONE`                | `Asia/Kolkata` (already defaulted)                         |
| `SESSION_SECURE_COOKIE`       | `true`                                                     |
| `LOG_STACK`                   | `daily`                                                    |
| `LOG_LEVEL`                   | `warning` (reduces log noise; keep `debug` while testing)  |
| `DB_CONNECTION`               | `mysql`                                                    |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | your MySQL creds                |
| `MAIL_MAILER`                 | `smtp` (not `log`)                                         |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | your SMTP creds                |
| `MAIL_FROM_ADDRESS`           | a sender address verified with your provider               |
| `MAIL_FROM_NAME`              | `"Apna Invoice"` (or company-appropriate)                  |
| `CONTACT_TO_EMAIL`            | inbox that receives /contact form submissions (optional)   |
| `TURNSTILE_SITE_KEY`          | from https://dash.cloudflare.com/?to=/:account/turnstile   |
| `TURNSTILE_SECRET_KEY`        | same dashboard                                             |
| `REMINDERS_ENABLED`           | `true` (or `false` to mute auto-reminders temporarily)     |
| `REMINDERS_SEND_HOUR`         | `8` (8am IST default)                                      |

> ⚠️ **Turnstile is mandatory in production.** The captcha rule fails closed
> when `APP_ENV=production` and either key is blank, which blocks all
> login / register / forgot-password attempts.

### 3. Database

```bash
php artisan migrate --force     # runs all 26 migrations, including the legacy paid_amount back-fill
```

### 4. Storage symlink

```bash
php artisan storage:link        # required — logos + signatures render via public/storage
```

### 5. Build caches

Run these after every deploy — they're fast and materially reduce per-request latency:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> If you ever edit a route / view / config and don't see the change, run
> `php artisan optimize:clear` to flush the caches.

### 6. File-system permissions

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### 7. Bootstrap the first super-admin

```bash
php artisan app:make-super-admin you@yourcompany.com
```

The named user must have already signed up through `/register`. Use
`--revoke` to undo. Super-admins see the `/admin` panel, platform-wide
stats, and can impersonate any user (there's a visible banner for that).

### 8. Cron — drives reminders and backups

Add to the `www-data` user's crontab (`sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/apna-invoice && php artisan schedule:run >> /dev/null 2>&1
```

Just that one line — Laravel's internal scheduler dispatches every
defined job (`invoices:send-reminders` daily at `REMINDERS_SEND_HOUR`,
`backups:send-weekly` on Sundays at 07:00 IST).

Verify after 1 minute:

```bash
php artisan schedule:list       # shows every scheduled task + next run time
```

### 9. Queue worker (optional today, required later)

Today's mailables run synchronously (they `use Queueable` but don't
`implements ShouldQueue`), so no worker is strictly needed. If any
mailable is upgraded to `ShouldQueue` — or you want scheduled backups
to not block the scheduler — add a supervisord / systemd service:

```ini
[program:apna-invoice-queue]
command=/usr/bin/php /var/www/apna-invoice/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
```

### 10. Reverse-proxy & HTTPS

The app trusts all proxies by default (see `bootstrap/app.php`). If you
front with Cloudflare, Nginx, or a load balancer, make sure it forwards
`X-Forwarded-Proto: https` and `X-Forwarded-For: <client-ip>` so signed
invoice-share URLs validate and rate-limit buckets work per client.

For hardened deployments, replace the `'*'` in
`bootstrap/app.php` → `$middleware->trustProxies(at: '*')` with an
explicit list of your proxy IPs.

## Daily operations

### Monitoring

| What to watch                         | Where                                           |
|---------------------------------------|-------------------------------------------------|
| Errors                                | `storage/logs/laravel-YYYY-MM-DD.log`           |
| Queued / failed jobs                  | `jobs` and `failed_jobs` tables                 |
| Scheduled task runs                   | `schedule:list` + cron mail / log output        |
| Bounced outbound email                | Your SMTP provider dashboard                    |
| Unhealthy app                         | Hit `GET /up` (Laravel's built-in health route) |

Consider wiring an exception tracker — Sentry, Bugsnag, or Flare — to
`config/logging.php`; a Slack channel for `error`-level logs is a
5-minute win.

### Backups

- **App data**: users can opt into the weekly auto-backup in
  `Profile → Backups`, or download on demand. That backup is a CSV ZIP of
  their own account — not a full DB dump.
- **Platform backups**: take nightly MySQL dumps + snapshot the
  `storage/app/public` directory (logos + signatures) to off-host
  storage. Restore drill: `mysql < dump.sql && tar -xf storage.tar.gz`.

### Emergency switches

| Situation                                  | Action                                               |
|--------------------------------------------|------------------------------------------------------|
| Reminder flood going out                   | Set `REMINDERS_ENABLED=false` and re-cache config    |
| Mail provider down                         | Set `MAIL_MAILER=log` temporarily so auth still works |
| Need to take the site offline              | `php artisan down --render="errors::503"`           |
| Bring it back                              | `php artisan up`                                     |

## Subsequent deploys

```bash
git pull
composer install --optimize-autoloader --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

If you have a zero-downtime setup (Envoy / Deployer / Forge), point at a
new release dir, run the above, and swap the symlink. Otherwise a ~1s
window during `optimize:clear`/`cache` is expected.

## Troubleshooting quick-refs

- **"Page expired (419)"** after login: session cookie domain mismatch.
  Check `SESSION_DOMAIN` matches your actual domain, and `APP_URL` is set.
- **Signed URL 403**: APP_KEY changed between link creation and visit, or
  proxy isn't forwarding `X-Forwarded-Proto: https`. The URL is bound to
  the exact hostname it was generated against.
- **Captcha blocks every login**: `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`
  missing in production .env — by design (fails closed).
- **No logos on invoice PDF**: `php artisan storage:link` wasn't run.
- **Reminders / backups never fire**: cron isn't installed, or `schedule:run`
  is running as a user without write access to `storage/framework`.
- **Invoice PDF spills to 2 pages**: we already trimmed the meta strip;
  remaining long invoices may need fewer line items or shorter terms.
