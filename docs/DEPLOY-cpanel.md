# Deploying Nexi ERP on cPanel

cPanel hosting **can** run Nexi ERP. This guide covers both the good case
(cPanel with SSH / a host that lets you set the document root) and the locked
down case (no SSH, forced `public_html`). Plan roughly **20-40 minutes**.

## Target layout

The cleanest setup uses a subdomain for the API:

| Piece | Address | Document root |
|---|---|---|
| SPA (static build) | `https://example.com` | `public_html` |
| API (Laravel) | `https://api.example.com` | `/home/USER/nexi-erp/api/public` |

The SPA is a static bundle, so it needs no PHP and no Node on the server - you
build it on your computer and upload the files.

---

## 1. Prerequisites

From cPanel, confirm you have:

- **PHP 8.2 or newer** (MultiPHP Manager).
- **Extensions**: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
  `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `zip`, `curl`.
- **MySQL database + user** (MySQL Databases).
- Ideally **SSH access** and the cPanel **Composer** tool. If you have neither,
  see [Step 8](#8-no-ssh-composer-or-symlinks).

---

## 2. Upload the backend

Upload the `api/` folder **outside** `public_html`, e.g. to
`/home/USER/nexi-erp/api`. Uploading a zip and extracting it in File Manager is
usually fastest. Do **not** upload `node_modules/` (the API has none) and do not
upload `vendor/` if you will run Composer on the server.

## 3. Create the database

In **MySQL Databases**, create a database and a user, grant that user full
privileges. Note the database name, user and password - cPanel prefixes them,
e.g. `myaccount_nexi`.

## 4. Configure `.env`

Copy `api/.env.example` to `api/.env` and set:

```dotenv
APP_NAME="Nexi ERP"
APP_ENV=production
APP_KEY=            # run: php artisan key:generate  (see step 6)
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myaccount_nexi
DB_USERNAME=myaccount_nexi
DB_PASSWORD=your-password

# Shared hosting usually has no Redis - use the database driver.
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

FILESYSTEM_DISK=public
MAIL_MAILER=smtp          # fill MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD
WEBHOOK_DRIVER=queue
```

## 5. Install Composer dependencies

With SSH:

```bash
cd ~/nexi-erp/api
composer install --no-dev --optimize-autoloader
```

Or use cPanel's **Composer** app: point it at `/home/USER/nexi-erp/api` and run
`composer install --no-dev`.

## 5b. Point the document root at `public/`

This is the single most important step. Laravel must not be served from the
project root.

**If you can set the document root** (Domains -> the domain/subdomain ->
Document Root, or a `cPanel > Domains` field): set it to
`/home/USER/nexi-erp/api/public`.

If your plan **forces `public_html`**, jump to
[Step 7](#7-forced-public_html-workaround).

## 6. Generate keys and migrate

With SSH (from `api/`):

```bash
php artisan key:generate
php artisan migrate --force
php artisan passport:keys
php artisan passport:client --password --name="Nexi ERP Password Grant"
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

Put the printed Passport client id/secret into `.env`
(`PASSPORT_PASSWORD_CLIENT_ID` / `PASSPORT_PASSWORD_CLIENT_SECRET`), then re-run
`php artisan config:cache`.

## 7. Schedule the cron jobs

The app ships a scheduler (`invoices:mark-overdue`, `stock:alerts`,
`webhooks:retry`, `webhooks:prune`, `advisor:snapshot`) and expects a queue
worker. On shared hosting neither can run as a daemon, so drive both from cron.

In **cPanel → Cron Jobs**, add (adjust the PHP path - use the one from
`MultiPHP Manager`, e.g. `/usr/local/bin/php` or
`/opt/cpanel/ea-php82/root/usr/bin/php`):

```cron
* * * * * /usr/local/bin/php /home/USER/nexi-erp/api/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/bin/php /home/USER/nexi-erp/api/artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

The second line drains the queue every minute (it exits when empty, so it never
runs forever). If you set `WEBHOOK_DRIVER=sync`, the second line is optional.

## 8. Build and upload the SPA

On your computer, in the repo root:

```bash
# point the SPA at the API subdomain
echo "VITE_API_URL=https://api.example.com" > .env.production
npm ci
npm run build
```

Upload the contents of `dist/` into `public_html` (the SPA host). Since the
router is client-side, add a `public_html/.htaccess` so deep links work:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>
```

Because the SPA and API are on different origins here, CORS matters: the API's
`config/cors.php` allows `FRONTEND_URL`, which you set to `https://example.com`
in step 4. `supports_credentials` is already `true`.

---

## 9. No SSH, Composer or symlinks

Some shared hosts refuse SSH, the Composer tool, or `symlink()`. Workarounds:

**No Composer on server** - run `composer install --no-dev --optimize-autoloader`
locally, then upload `api/` *including* `vendor/`.

**No `key:generate` / `passport:keys`** - generate them locally and upload:

```bash
php artisan key:generate --show          # copy into APP_KEY
php artisan passport:keys                # upload storage/oauth-*.key
```

**Document root forced to `public_html`** - use the "public as root" trick:

1. Upload the Laravel app to `/home/USER/nexi-erp/api`.
2. Copy everything from `api/public/` into `public_html/`.
   (`index.php`, `.htaccess`, `robots.txt`, ...)
**Then edit `public_html/index.php` so it points at the app outside the root:**

```php
require __DIR__.'/../nexi-erp/api/vendor/autoload.php';

$app = require_once __DIR__.'/../nexi-erp/api/bootstrap/app.php';
```

**No `storage:link` (symlink disabled)** - either:

- ask the host to enable `symlink`, or
- serve uploads from the API domain instead: set
  `FILESYSTEM_DISK=public` and use the returned absolute `url` from the
  upload/attachment endpoints (they include `APP_URL`), or
- manually copy `api/storage/app/public/*` into `public_html/storage` after
  uploads (not ideal for a live app).

---

## 10. Verify

- `https://api.example.com/up` returns the Laravel health response.
- `POST https://api.example.com/api/v1/login` with a seeded user returns a token.
- `https://example.com` loads the SPA and can log in.
- `php artisan schedule:list` shows the scheduled commands.

Seed a first admin if needed:

```bash
php artisan db:seed --force
```

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| 500 on the API, blank page | Check `api/storage/logs/laravel.log`; usually `.env`/`APP_KEY` or file permissions |
| "Missing APP_KEY" | `php artisan key:generate`, then `config:cache` |
| 404 on all routes except `/` | Document root is not `api/public` (see step 6) |
| Uploaded files 404 | `storage:link` failed - see step 9 |
| Scheduler/queue never runs | Cron PHP path is wrong, or the every-minute lines are missing |
| CORS error in the browser | `FRONTEND_URL` in `api/.env` must equal the SPA origin, then `config:cache` |
| Class not found after update | Re-run `composer install --no-dev` and `php artisan optimize:clear && php artisan optimize` |

For hosts that give you full CLI/daemons (cPanel **VPS or dedicated**), you can
instead run `php artisan queue:work` and `schedule:work` under supervisor, and
enable Redis - see [DEPLOY.md](DEPLOY.md).
