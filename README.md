# Pantry Keeper

A multilingual food inventory and expiry tracker built with PHP, SQLite, and vanilla JavaScript.

Keep track of food in your refrigerator, freezer, and cupboard. See what needs to be used first, record consumed or discarded items, and configure browser push reminders before expiry.

**Project status:** working inventory MVP. Background push is implemented but requires configuration and a recurring worker. Real device delivery has not yet been verified. Recipe suggestions, shopping lists, and administration are not implemented.

## Features

- Email/password registration and sign-in.
- Private inventory: each account can access only its own products.
- Add and edit product names, food categories, storage locations, and expiry dates.
- Products sorted by expiry, with text and color status indicators.
- Filter products by refrigerator, freezer, or cupboard.
- Mark products as consumed or discarded; restore them from history.
- Ukrainian, English, and German interfaces and validation messages.
- Persistent SQLite storage.
- Optional Web Push subscriptions, reminder worker, and delivery bookkeeping.
- Responsive, server-rendered pages. Inventory operations work without JavaScript; push requires JavaScript.

## Technology

| Layer | Implementation |
| --- | --- |
| Backend | PHP 8.2+ without an application framework |
| Database | SQLite through PDO |
| Interface | Semantic HTML, CSS, vanilla JavaScript |
| Notifications | Service worker and minishlink/web-push |
| Dependencies | Composer, pinned by composer.lock |
| Tests | PHP scripts and Python standard-library HTTP tests |

There is no frontend framework, bundler, CDN dependency, or Node.js application server. Node.js is optional for JavaScript syntax checks.

## Requirements

- A supported PHP 8.2+ installation with `pdo_sqlite`, `mbstring`, `openssl`, and `curl` enabled.
- Composer.
- A writable local directory for SQLite data.
- For notifications: a compatible browser, HTTPS outside localhost, outbound access to browser push services, and a scheduler.
- For development checks: Python 3 and, optionally, Node.js.

The current workspace was verified with PHP 8.4.27. Run `composer check-platform-reqs` on the deployment machine to verify the exact requirements of the locked dependency versions.

## Quick start

Download the repository, open a terminal in its root, and run:

```sh
composer install --no-dev --prefer-dist
composer check-platform-reqs
php -S 127.0.0.1:8765 -t public
```

Open [http://127.0.0.1:8765](http://127.0.0.1:8765), create an account, and add your first product. No default administrator or shared login is created.

The database and tables are created automatically on first use. Data is stored at `var/pantry.sqlite` by default. A fresh installation starts with an empty inventory.

The PHP development server is for local development only. Stop it with Ctrl+C. If another instance is already listening on port 8765, use that instance or choose another port consistently.

### Existing Windows workspace

This workspace includes an ignored local PHP and Composer installation under `.tools/`. These binaries are not part of the GitHub repository. From the project root in PowerShell:

```powershell
.\.tools\php\php.exe .\.tools\composer.phar install --no-dev --prefer-dist
.\.tools\php\php.exe .\.tools\composer.phar check-platform-reqs
.\.tools\php\php.exe -S 127.0.0.1:8765 -t public
```

The local database may contain a clearly labeled browser-test account and product from verification. They are not seeded into new installations and are visible only to that test account.

## Using the application

1. Register with your name, email address, and password.
2. Select the desired interface language in the header.
3. Choose **Add food**, enter its name, select a food category and storage location, and provide its expiry date.
4. Review the inventory in expiry order. Use the storage filter when needed.
5. Edit a product or mark it consumed/discarded. Find completed items in **History**, where they can be restored.
6. If server push is configured, choose **Enable on this device** and grant browser notification permission.

Accounts and inventories are stored on the server. A local installation is accessible only on that machine by default. Access from multiple devices requires deployment to a shared server; no cloud hosting is included.

## Expiry and validation rules

A product is considered within its date through the end of the selected calendar day, using the configured server timezone.

| Status | Rule |
| --- | --- |
| Fresh | More than two calendar days remain |
| Use soon | The expiry date is today or within the next two calendar days |
| Expired | The expiry date is before today |

These indicators are based only on dates. They are not a guarantee that food is safe; users must also consider its condition and storage.

- Product names: 1–100 Unicode characters.
- Account names: 1–80 Unicode characters.
- Passwords: at least 12 characters and no more than 72 bytes.
- Email addresses: validated, with a maximum length of 254 bytes.
- Expiry dates: valid calendar dates from 2000-01-01 through 2100-12-31.
- Historical dates are allowed and displayed as expired.
- Food category and storage location are separate fields.

## Configuration

Defaults are defined in `config.example.php`. To override them, create `config.local.php` in the project root; it is ignored by Git. Do not overwrite an existing file containing VAPID keys.

Example configuration without push keys:

```php
<?php
return [
    'timezone' => 'Europe/Kyiv',
    'database' => __DIR__ . '/var/pantry.sqlite',
    'secure_cookies' => false,
];
```

| Setting | Purpose |
| --- | --- |
| `timezone` | Calendar calculations and reminder timing |
| `database` | SQLite file path, outside the web root |
| `secure_cookies` | Set to `true` for an HTTPS deployment |
| `vapid.subject` | Contact URI used for Web Push authentication |
| `vapid.publicKey` | Public VAPID key shared with the browser |
| `vapid.privateKey` | Secret VAPID key; keep private |

The `PANTRY_DB` environment variable overrides the database path. Tests use this to isolate their data.

## Browser push reminders

Inventory tracking works without push configuration. The interface explicitly reports when server push is unavailable.

### 1. Generate VAPID keys

Replace the example contact address with an email address you control:

```sh
php bin/setup-push.php mailto:you@your-domain.example
```

The command saves keys in `config.local.php` and refuses to replace existing configured keys. The contact address is supplied to the browser push service as part of authentication. Back up the private configuration securely. Replacing keys requires browser subscriptions to be recreated.

On the existing Windows workspace, use `.\.tools\php\php.exe` instead of `php`.

### 2. Subscribe in a browser

Sign in, choose **Enable on this device**, and allow notifications. Use localhost for local testing or HTTPS for a deployed site. If an embedded browser reports push as unavailable, try a compatible standalone browser.

Subscriptions are device/browser-specific. Enabling reminders on one browser does not subscribe another device automatically.

### 3. Run the worker

Preview pending reminders without sending or recording deliveries:

```sh
php bin/remind.php --dry-run
```

Send currently due reminders:

```sh
php bin/remind.php
```

For a local Windows loop, keep a separate PowerShell terminal open:

```powershell
.\bin\watch.ps1
```

This defaults to `.tools/php/php.exe`. To use system PHP:

```powershell
.\bin\watch.ps1 -Php php
```

The loop runs every minute and stops with Ctrl+C. If execution policy prevents running the script, invoke the PHP worker manually or use an approved scheduler; the application does not require changing system execution policy.

For Linux deployment, configure cron with the actual executable, project, and writable log paths:

```cron
* * * * * /usr/bin/php /srv/pantry-keeper/bin/remind.php >> /var/log/pantry-reminders.log 2>&1
```

No recurring OS task is installed automatically.

### Timing and delivery behavior

- Reminders become due at 09:00 on the day before expiry, in the configured timezone; the default is Europe/Kyiv.
- This is a calendar-day reminder, not an exact 24-hour countdown, because products have dates rather than expiry times.
- Due products added later are included in the next worker run.
- Consumed, discarded, and already expired products are excluded.
- Unsuccessful sends remain retryable through the end of the expiry date.
- Accepted sends are recorded per product, subscription, and expiry date.
- Expired subscriptions are removed. A local lock prevents overlapping workers using the same database directory.
- Disabling reminders removes all subscriptions for the account. Signing out removes the subscription associated with the current login session.

Transport acceptance does not prove that a device displayed the notification. Browser permissions, connectivity, and the OS affect delivery. Messages already accepted by a push service cannot be recalled. A crash between acceptance and recording can cause a repeated send; notification tags reduce repeated visible notifications.

## Deployment and backups

1. Install PHP and the locked Composer dependencies, then run `composer check-platform-reqs`.
2. Configure an HTTPS-capable PHP web server with **only `public/` as its document root**. Do not expose the repository root, configuration, database, or dependencies.
3. Configure `config.local.php`, enable secure cookies, and select a persistent database location.
4. Give the web-server and worker accounts the necessary database-directory permissions. Restrict private configuration and data to the required accounts; use appropriate NTFS ACLs on Windows.
5. Configure and monitor the reminder scheduler if push is enabled.
6. Keep production error display disabled and retain logs privately.

This MVP uses a single server and local SQLite storage. Do not place its WAL files on a shared network filesystem or assume that its worker lock coordinates multiple servers.

For a simple file-copy backup, stop both the web server and worker before copying `var/`. For live backups, use SQLite's online backup mechanism. Back up VAPID configuration separately and securely. Restore both data and configuration before accepting traffic.

## Tests and verification

From a fresh checkout with PHP in PATH:

```sh
php tests/domain.php
python tests/integration.py php
php tests/push.php
node --check public/assets/app.js
node --check public/sw.js
composer validate --no-check-publish
composer audit --locked --no-interaction
```

Use `python3` if that is the Python executable on your system. On the existing Windows workspace:

```powershell
.\.tools\php\php.exe tests/domain.php
python tests/integration.py .tools/php/php.exe
.\.tools\php\php.exe tests/push.php
Get-ChildItem app,public,bin,tests -Recurse -Filter *.php | ForEach-Object { & .\.tools\php\php.exe -l $_.FullName }
```

| Check | Latest recorded result |
| --- | --- |
| Domain rules | 44 checks passed |
| HTTP integration | 32 checks passed |
| Web Push library with mocked transport | 5 checks passed |
| PHP and JavaScript syntax | Passed |
| Dependency platform requirements | Passed on PHP 8.4.27 |
| Browser workflow | Registration, add, edit, language switch, history, and restore exercised |
| Responsive views | Inspected at 320, 390, and 1440 px |
| Real background push to a device | Not yet verified |

The domain tests use in-memory SQLite. HTTP tests start a temporary PHP server and database, exercise two accounts, and stop the server afterward. Push tests use mocked HTTP responses and send no external notifications. See [VERIFICATION.md](VERIFICATION.md) for coverage and limitations.

## Security

The application uses PHP password hashing, prepared SQL statements, per-owner access checks, CSRF tokens, session-ID rotation at login/logout, contextual HTML escaping, and login throttling. Browser push destinations are restricted, and the sender does not follow HTTP redirects.

These controls and focused tests do not constitute a complete penetration test. Public deployment still needs appropriate operational monitoring, request limits, backups, and security review. Do not post credentials, private keys, database files, or personal records in public issues.

## Repository layout

```text
app/                 Configuration loading, domain rules, form actions, translations, schema
bin/                 Push setup and reminder worker commands
public/              Web document root, pages, assets, service worker
tests/              Domain, HTTP integration, and Web Push tests
composer.json        Direct dependency requirements
composer.lock        Locked dependency versions
config.example.php   Documented configuration defaults
PROJECT_BRIEF.md     Product scope and implementation assumptions
VERIFICATION.md      Verification results and remaining gaps
```

Local-only files and directories are excluded by `.gitignore`: `.tools/`, `vendor/`, `var/`, `config.local.php`, test screenshots, and Python caches. Commit `composer.lock`; install `vendor/` with Composer on each machine. Review the files selected for publication before pushing to GitHub.

## Roadmap and known limitations

Not implemented in this MVP:

- Recipe matching based on available or soon-expiring ingredients.
- Automatic shopping lists.
- Administrator interface and role provisioning.
- Shared household inventories.
- Google/Apple sign-in, email verification, and password recovery.
- Hosted deployment or cloud account provisioning.

Full screen-reader coverage, text zoom, every notification permission/network failure path, and multiple browser engines have not been comprehensively tested. Real push delivery and unattended scheduling remain to be verified after configuration. This project is not presented as production-ready solely because automated checks pass.

## Contributing

Keep the PHP/HTML/CSS/vanilla JavaScript stack unless a change has a concrete reason for a dependency. Add focused tests for business-rule or authorization changes, preserve translation-key parity, and run the checks above. For UI changes, inspect narrow and wide layouts and keyboard behavior. Report bugs with reproduction steps and sanitized examples.

## License

No license has been selected for the application yet. Choose and add a `LICENSE` file before offering it under an open-source license. Third-party dependencies retain their own licenses in their distributed packages.
