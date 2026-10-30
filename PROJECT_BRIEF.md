# Food expiry tracker — Project Brief

## Confirmed product requirements

Help families, students, busy people, and environmentally conscious consumers reduce food waste. The full product includes food inventory, expiry reminders, recipe suggestions using soon-expiring ingredients, and automatic shopping lists for depleted products. Ordinary users access only their own products. An eventual administrator manages global categories and recipe hints and sees user counts. Shared family inventory is optional.

## First complete workflow — current MVP scope

Register or sign in with email/password, add a product with a food category, storage location, and expiry date, see it in a persisted private list with fresh/soon/expired text and color, and opt into browser push reminders. Allow editing and marking products consumed/discarded, with a history view. Support Ukrainian, English, and German for UI, validation, and notifications.

Local execution and deployment instructions are confirmed; no hosting or domain is supplied. Server persistence supports access from multiple devices once deployed, but this delivery does not provision cloud hosting.

## Data and access

Users: ID, email, password hash, name, language, notification preference. Products: ID, owner ID, name, category, creation timestamp, expiry date, storage, active/consumed/discarded status. Push subscriptions and delivery records are private. Category (e.g. dairy) is separate from storage (refrigerator/freezer/cupboard). Authentication and ownership checks apply to all inventory and notification operations. Email/password is the selected allowed login option.

## Implementation assumptions

- SQLite on persistent local storage; PHP 8.2+ with PDO SQLite, mbstring, OpenSSL, and curl.
- Date-only expiry is valid through the end of the selected day. Fresh: more than two calendar days remaining; soon: zero to two days; expired: before today. Indicators are inventory reminders, not food safety guarantees.
- Default timezone: Europe/Kyiv, configurable per deployment. Reminder: 09:00 on the preceding calendar day. An exact 24-hour guarantee is impossible without an expiry time; this is a calendar-day reminder.
- Retry unsuccessful notifications until the end of the expiry date. Exclude consumed, discarded, and expired products. Transport acceptance is not proof of device display.
- Product name: 1–100 Unicode characters; user name: 1–80; password: 12–72 bytes; email up to 254 bytes. Valid dates: 2000-01-01 through 2100-12-31. Historical dates are allowed and shown as expired.
- Mobile and desktop, system sans-serif font, white/pale green surfaces, dark green actions. Text accompanies color. No supplied assets. Working title: Запаси / Pantry / Vorräte.

## Technical constraints

PHP, server-rendered HTML, CSS, vanilla JavaScript; no frameworks or bundlers. minishlink/web-push is justified by background notifications and standards-compliant encryption/VAPID; it is the only direct application dependency. Project documentation is written in English.

## Acceptance criteria

- Server validation rejects invalid inputs with localized errors and preserves non-password values.
- Registration and login lead to private inventory; duplicate emails are rejected.
- Data survives reload/logout; a second user cannot read or mutate it.
- Mutations require CSRF protection; duplicate add submissions do not create duplicate records.
- Tests cover date boundaries, malformed input, ownership, and reminder selection.
- All locales have identical translation keys; switching language preserves data.
- Push permission is requested only by explicit user action. Denied permission, unsupported browsers, and network errors are handled.
- Successful sends are recorded per product, subscription, and expiry date; failures are retryable; expired subscriptions are removed.
- Document local startup, scheduling, HTTPS deployment, backups, and actual verification evidence.

## Later phases / outside this MVP

Recipe matching, automatic shopping lists, administrator interface and provisioning, shared households, OAuth, password recovery/email verification, production deployment, production scans. These broader requirements remain recorded above; no inactive controls will imply they exist.
