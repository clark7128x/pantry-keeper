# MVP verification — 2026-10-30

## Executed checks

| Area | Status | Evidence |
| --- | --- | --- |
| PHP runtime | Passed | Local PHP 8.4.27; SQLite, mbstring, OpenSSL, curl loaded; Composer platform requirements pass. Official archive SHA-256 and Composer installer SHA-384 verified before execution. |
| PHP syntax | Passed | All application, command, configuration, and PHP test files checked using `php -l`. |
| Domain tests | Passed | 44 checks: Unicode/length bounds, category/storage allowlists, invalid/leap dates, date limits, status boundaries, reminder timing and DST, push destination restrictions, translation parity, notification opt-out, consumed-product exclusion, delivery deduplication. |
| HTTP integration | Passed | 32 checks against a running PHP server and isolated SQLite: registration/login/logout, password hashing, validation, CSRF, XSS escaping, idempotent add, edit, cross-account isolation, consumed/history/restore, all locales, invalid locale, push method/CSRF, rate limit, private configuration inaccessible. |
| Push library | Passed with mock transport | 5 checks: success, expired subscription, server failure, VAPID/encryption headers, encrypted payload. No external push was sent. |
| JavaScript syntax | Passed | `node --check` on app.js and sw.js. |
| Dependencies | Passed within advisory coverage | `composer audit --locked --no-interaction`: no known vulnerability advisories reported. This is not a penetration test. |
| Composer manifest | Valid with warning | `composer validate --no-check-publish`: valid; license metadata is unspecified. No license was selected on the owner's behalf. |
| Local worker script | Syntax passed | PowerShell parser returned no errors for bin/watch.ps1. The recurring loop was not started. |
| Reminder command | Passed, dry run only | `bin/remind.php --dry-run` exits successfully with zero subscribed recipients. No transport, device display, or installed scheduler is implied. |
| Browser workflow | Passed for exercised path | Real local browser registration, add, edit expiry, language switch, consume, history, restore. Test data is explicitly labeled. |
| Responsive rendering | Passed for inspected views | Screenshots inspected at 1440, 390, and 320 px; document width did not exceed viewport width. German and Ukrainian list views and mobile edit form exercised. |
| Browser console | Passed for exercised path | No warning/error entries returned after add/edit/language operations. |
| Web interface review | Performed | Read current Vercel guidelines; reviewed public/index.php, app.css, app.js, sw.js. Added history restore and unsaved-editor guard; native labeled controls, focus outlines, skip link, text status, error summary, reduced-motion-compatible static layout. |

Source review corrected numeric edit-ID rendering, and verification caught permissive NUL-date trimming, a Python filename collision, Windows SQLite cleanup handling, and unknown-path routing behavior. These were corrected before the passing runs. The browser also rejected an empty product form and focused the required name field. Passing PHP syntax alone was not used as evidence of workflow correctness.

## Security coverage

Focused HTTP tests verify CSRF, ownership, output escaping, authentication, and repeated submissions. Push endpoints are restricted to recognized browser push services and HTTP redirects are disabled in the sender. A full application penetration test was not performed. No external production host or user account was scanned.

## Post-cleanup regression check

On 2026-10-30, the 44 domain checks, 32 HTTP checks, five mocked Web Push checks, JavaScript syntax checks, and Composer platform requirements were rerun successfully. Documentation and development-instruction cleanup does not imply that real push delivery was verified.

## Remaining limitations / not run

- Real background push through a browser vendor and OS: **not run**. Contact email/VAPID configuration, browser permission, and the recurring worker are still setup steps. The local UI accurately reports push as unconfigured.
- Scheduled unattended execution: **not installed or tested**. `bin/watch.ps1` and deployment cron instructions are provided; no system scheduled task was silently created.
- Full keyboard-only workflow, screen reader, text zoom, denied-permission/network-failure browser branches, and multiple browser engines: **not fully exercised**. Native focus/labels and error semantics were reviewed in source; this is not equivalent to complete accessibility certification.
- Device receipt is best effort. A transport-accepted message can remain undisplayed; a crash between acceptance and recording can repeat delivery. Already accepted notifications cannot be recalled after logout/opt-out.
- Server/rendered data and VAPID keys require persistent storage and backups. No cloud deployment was created.
- Recipe matching, automatic shopping lists, administration, household sharing, OAuth, password recovery, and email verification are outside this first workflow.

## Review sources

- [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md)
- [Web Push PHP library](https://github.com/web-push-libs/web-push-php)
- [Official PHP downloads](https://www.php.net/downloads.php?os=windows&version=8.4)

Results describe this local MVP and the explicit tests above; they do not establish production readiness.
