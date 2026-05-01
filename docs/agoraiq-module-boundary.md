# Coinwink ↔ Agoraiq-Signals module boundary

Coinwink is a self-contained Laravel + Vue.js application. When it needs to
read data from the Agoraiq-Signals stack, it does so as a separate module
through a **strictly read-only** database connection. Coinwink owns its own
MySQL schema (`cw_*` tables) and never writes into Agoraiq-owned databases.

## What's separated

| Concern | Coinwink | Agoraiq-Signals |
| --- | --- | --- |
| Primary DB | MySQL (`cw_*` schema) | Postgres (`api/`), SQLite (`providers-api/`, `watchlist`) |
| Auth | Laravel Sanctum / Fortify | JWT + magic-link, JWT for Telegram bot |
| Billing | `routes/api_stripe.php` | `api/src/routes/billing.js` (richer: refunds, proration, public checkout) |
| Alert delivery | `public/cron_alerts_*.php` | `api/src/workers/push.js` (BullMQ + Telegram) |
| Code repo | `starfuryone/coinwink_aiq` | `starfuryone/agoraiq-signals` |

The two systems do not share runtime processes, queues, or write paths.

## Read-only contract

Any Coinwink code that reads from Agoraiq-Signals MUST go through the
`agoraiq` connection:

```php
DB::connection('agoraiq')->table('signals')->where(...)->get();
```

Three independent guards make writes impossible:

1. **DB role** — the Postgres user named in `AGORAIQ_DB_USERNAME` must be
   granted `SELECT` only. This is the only guard that survives outside the
   PHP application (e.g. `psql` shells, `php artisan tinker`).

   ```sql
   CREATE ROLE coinwink_ro LOGIN PASSWORD '...';
   GRANT CONNECT ON DATABASE agoraiq TO coinwink_ro;
   GRANT USAGE ON SCHEMA public TO coinwink_ro;
   GRANT SELECT ON ALL TABLES IN SCHEMA public TO coinwink_ro;
   ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO coinwink_ro;
   ```

2. **Session GUC** — on the first query of every PDO instance,
   `AgoraiqReadOnlyGuard` runs `SET SESSION default_transaction_read_only =
   on`. The Postgres server then rejects any DML with `25006 read-only SQL
   transaction`.

3. **Statement-level verb check** — a `beforeExecuting` callback inspects the
   SQL verb and throws a `RuntimeException` for anything outside
   `SELECT`, `WITH`, `SHOW`, `DESCRIBE`, `EXPLAIN`. This surfaces violations
   at the call site rather than as opaque DB errors.

The implementation is in `app/Database/AgoraiqReadOnlyGuard.php` and is wired
in `app/Providers/AppServiceProvider.php`.

## SMS gateway: Brevo

Coinwink's SMS alert path was previously Twilio; it now uses
[Brevo](https://developers.brevo.com/reference/sendtransacsms)'s Transactional
SMS API. Two paths exist:

- **Laravel** — `App\Services\BrevoSmsClient` reads from `config/services.brevo`
  and sends via `Http::post()`.
- **Standalone PHP cron** — `public/lib/php/brevo_sms.php` exposes
  `cw_send_brevo_sms($recipient, $content)` for the historical
  `public/cron_alerts_sms_{cur,per}.php` scripts. Credentials live in
  `public/coinwink_auth_brevo.php` (analogous to the other `coinwink_auth_*`
  files).

Required env vars: `BREVO_API_KEY`, `BREVO_SMS_SENDER`, optional
`BREVO_SMS_ENDPOINT`. The legacy Twilio library under
`public/lib/php/twilio/` is no longer loaded; you can delete it whenever it's
convenient.

## Customer-facing surface

Coinwink renders a read-only **AgoraIQ Signals** page so users see the live
Agoraiq feed alongside their existing Coinwink alerts:

- Page route: `/signals` (Vue) → `resources/js/views/AgoraiqSignals.vue`
- API route: `GET /api/agoraiq/signals?limit=20` (auth + verified)
- Controller: `App\Http\Controllers\AgoraiqSignalsController`

The controller mirrors Agoraiq's `toResolvedView` (api/src/models/signal.js):
public-tier-safe fields only — `symbol`, `direction`, `status`, `confidence`,
`result`, `created_at`. Entry, stop, and target prices are intentionally
omitted; if Coinwink ever needs to expose them to premium users, layer a
`cw_settings.subs == 1` check inside the controller before returning the
extra columns.

The query filters to `source IN ('scanner', 'provider')` so we only show
public/published signals, never user-private ones from Agoraiq's bot path.

## Adding a new Agoraiq read

1. Confirm the table is owned by Agoraiq and you only need to read it.
2. Use the `agoraiq` connection — never the default.
3. If you find yourself needing to write, stop: the cross-module direction is
   one-way. Go through Agoraiq's API (`/api/v1/...`) instead.
