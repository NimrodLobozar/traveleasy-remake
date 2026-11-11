# Data inventory

Owner: security-privacy-reviewer. Status: **draft**. It covers the scaffold (TE-001, Laravel React starter kit), the Docker dev environment (TE-002) and the production runtime targets (ADR-0002, TE-011).
Every story that adds a personal-data field, table, log line, export or external call updates this file in the same PR.
The retention rules become `retention.md` (implemented as jobs by the database-engineer). The public
`privacy-statement.md` (nl + en) is written in M1, when the account features it describes exist.

Legal bases (GDPR Art. 6(1)): **(b) contract**, meaning needed to provide the account or booking the person asked for;
**(c) legal obligation**, for example Dutch tax law requiring invoices to be kept for 7 years; **(f) legitimate interest**,
here security and abuse prevention.

## Personal data in the scaffold (TE-001)

| Where | Field(s) | Purpose | Legal basis | Who can access | Retention (target) | Notes |
|---|---|---|---|---|---|---|
| `users` | `name` | Display name in the app | (b) | The user; staff with a need (M1 roles) | Until account deletion | Free text, max 255 |
| `users` | `email` | **Login name only** (ADR-0005): never verified, never mailed | (b) | The user; staff with a need | Until account deletion | Must become case-insensitive unique (M1) |
| `users` | `password` | Authentication | (b) | Nobody (bcrypt hash, `#[Hidden]`) | Until account deletion | Never in Inertia props (`SharedUserData`) |
| `users` | `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` | 2FA | (b), (f) | Nobody (Fortify encrypts the secret and codes with `APP_KEY`; hidden) | Until 2FA is disabled or the account is deleted | Never in Inertia props |
| `users` | `remember_token` | "Remember me" login | (b) | Nobody (hidden) | Until logout or account deletion | |
| `passkeys` | `name`, `credential_id`, `credential` (public key JSON), `last_used_at` | Passkey login (WebAuthn) | (b) | The user (name, dates); nobody else | Until the user removes the passkey; cascade on account deletion | Public key material only; no biometrics leave the device |
| `sessions` | `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` | Keeping the user logged in; security | (b), (f) | Nobody in the UI; DB admins | `SESSION_LIFETIME` (120 min) plus garbage collection (2% lottery). M1: a scheduled prune so expired rows don't linger | IP + UA are personal data. Not encrypted at rest (`SESSION_ENCRYPT=false`, M1 follow-up) |
| `audit_logs` (TE-047) | `subject_user_id`, `event`, `actor`, `created_at` | Accountability for erasures (and later audited actions) | (c), (f) | DB admins; M1: admins through an audit view | `account_erased` rows: **2 years** after `created_at` (target), then deleted by a scheduled prune (M1, `retention.md`); later events get their own period when they are added | **Pseudonymous**: the user ID is not a foreign key and survives the erasure; never a name, e-mail or other personal data. After an erasure the ID can only be linked back to a person through a dump or backup made before it (≤ 30 days, see below), so the row is pseudonymous while those exist and effectively anonymous afterwards. Actor is `cli`, `self` (self-service deletion) or, later, an admin's user ID |
| `cache`, `cache_locks` | Rate-limiter keys (`email|ip` for login) | Brute-force protection | (f) | Nobody | Limiter decay (1 minute); the database store does not sweep expired rows, so they can linger until the key is hit again. M1: scheduled prune of expired `cache` rows | The cache key is an MD5 hash of the limiter name plus `email|ip` (Laravel default), so it is pseudonymous, not anonymous. Not removed by account erasure (no user ID to match on); expiry covers it |
| `jobs`, `failed_jobs` | Serialized job payloads | Background work (none yet) | Depends on the job | DB admins | M1: prune `failed_jobs` (for example 7 days) | Jobs must reference IDs, never embed personal data |
| `storage/logs/laravel.log` | Exception context, may include request data | Debugging | (f) | Server admin | M1: daily rotation with a short retention | No personal data or secrets in log lines (see the logging rules) |
| `storage/inertia-devtools/` | Recorded Inertia page props, request bodies (password-like keys redacted) and redacted headers per request | Dev tooling only (enabled only when `APP_ENV=local`) | n/a (dev only) | Anyone who can reach the dev server (no auth in `local`) | 24 h TTL, max 100 entries | Must never be enabled in production. TE-002 binds the dev ports to 127.0.0.1; the app does not yet check the `Host` header, so a DNS-rebinding page could still read these entries (follow-up: trusted hosts) |

The kit's `DatabaseSeeder` creates `test@example.com` with the factory password `password`. That is dev-only fake
data and must never run in production (M1/M2 `reference` vs `demo` seeder split). The TE-002 dev environment never
seeds automatically (image, entrypoint and compose files checked).

## Development environment (TE-002)

The dev stack should hold **fake data only**. Never import a production dump into it: the dev tools below have weaker
access control than production.

| Where | What | Who can access | Retention | Notes |
|---|---|---|---|---|
| Named volume `traveleasy-remake_pgdata` | The dev databases `traveleasy` and `traveleasy_test` | The developer (DB superuser credentials from `.env`); the `app` container | Until `docker compose down -v` | DB has no published port; reachable only on the internal `backend` network |
| Adminer (`tools` profile, `127.0.0.1:8081`) | Nothing stored; shows DB rows after login | Whoever knows `DB_USERNAME`/`DB_PASSWORD` and is on the dev PC | n/a | Opt-in, localhost only, no stored credentials; `version-noverify` stops its call to adminer.org. Dev only, never in `compose.yaml` |
| Vite dev server (`127.0.0.1:5173`) | Serves project files from the bind mount | Local processes on the dev PC | n/a | Vite's CORS allow-list and host check block other origins and DNS rebinding; `.env*` files are denied |
| `.env` on the developer's disk | `APP_KEY`, `DB_PASSWORD` | The developer | n/a | Git-ignored and excluded from the Docker build context (`.dockerignore`) |

## Production runtime (ADR-0002, reviewed in TE-011)

Targets for TE-012/TE-024/TE-026; verified when those stories are tested.

| Where | What | Who can access | Retention (target) | Notes |
|---|---|---|---|---|
| Named volume `pgdata` (prod) | All application tables above | `db`; app role `traveleasy_app` (`app`, `worker`, `scheduler`, `backup`); superuser only in `db`/`db-init` | Per table (this file, `retention.md`) | No published port; `default` network is `internal: true` |
| Named volume `storage` (`storage/app`) | Private files: exports, invoice PDFs (later) | uid 10001 in `app`/`worker` | Per feature (M5, M6) | Never served directly; only via policy-checked routes |
| Manual DB dumps (`~/traveleasy-backups/*.dump` on the server, until the `backup` service exists) | Full copy of `pgdata` (encrypted columns stay ciphertext; `APP_KEY` is not in the dump) | The owner; files `0600` via `umask 077` | Delete an older dump once a newer verified dump exists and the update it guarded is confirmed good; **never longer than 30 days**, which wins once the update the dump guarded is confirmed good (`find -mtime +29`, see `docs/deploy/casaos-portainer.md`). Erasures made after a dump's date are re-applied after restoring it, found by diffing user IDs saved before the restore against the restored IDs (temporary ID files deleted afterwards) | Off-server copies follow the same rule; scheduled backups and their retention belong to the `backup` story |
| Container logs (Docker `json-file`, stderr JSON) | App log lines; any request log line with IP/URL is personal data | Whoever has Docker/Portainer access on the host | Size cap (`max-size`/`max-file`) only, so **no time bound** on a low-traffic site. Target: no client IP in request log lines (or truncated), or a time bound of ≤ 14 days | Caddy access log is off (ADR-0002 D1). Replaces `storage/logs/laravel.log` in production |
| Nginx Proxy Manager access and error logs (owner's NPM, outside the stack; `/data/logs/proxy-host-<id>_{access,error}.log`) | Client IP, URL, user agent (error log: client IP, request) | The owner | **14 days** for both files (`maxage 14`; target), at most **≈ 18 days** for the oldest line: NPM 2.16.0 runs logrotate only at start and every 2 days, so a line can wait up to 2 days in the live log and a rotated file up to 2 days past `maxage` (verified in TE-041). NPM's defaults (access weekly × 4, error weekly × 10) keep more, so the owner overrides them persistently (mounted logrotate rule, not an edit inside the container); steps in `docs/deploy/casaos-portainer.md` § Logs and privacy | Not under this stack's control; stays on the server, never sent anywhere. Evidence pasted to the orchestrator carries only the IP field, never whole log lines |
| `ssr` process memory | Page props of each rendered page (may include the logged-in user's data) | Nothing persisted | Per request | Internal network only; no secrets, no DB credentials; no props in logs |
| tmpfs config cache (`php artisan optimize`) | Secrets (`APP_KEY`, `DB_PASSWORD`), no personal data | uid 10001 | Container lifetime | Never in an image layer or a volume (ADR-0002 D3) |

## Preference cookies (M0 app shell)

First-party, functional cookies without personal data; no consent needed (strictly necessary for the visitor's own
choice). Their values are checked against an allow-list on every request, never echoed into HTML and never logged.

| Cookie | Set by | Value | Attributes | Lifetime | Notes |
|---|---|---|---|---|---|
| `locale` | Server, `POST /locale` (TE-017) | `nl` or `en` | Host-only, `Path=/`, `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS; not encrypted | 1 year | Read by `LocaleResolver`; any other value is ignored. React reads the locale from the Inertia props, not the cookie |
| `appearance` | Browser, the theme control (TE-018, `use-appearance`) | `system`, `light` or `dark` | Host-only, `Path=/`, `SameSite=Lax`, `Secure` over HTTPS; readable by JavaScript (it is written there); not encrypted | 1 year | The server checks it against `App\Enums\Appearance` and renders only the enum value; anything else is `system` |

## External processors and outgoing requests

| Service | When | Data it receives | Personal data? | Status |
|---|---|---|---|---|
| Have I Been Pwned Pwned Passwords API (`api.pwnedpasswords.com`) | Production only: registration and password change, through `Password::defaults()->uncompromised()` | The first 5 hex characters of the password's SHA-1 hash (k-anonymity) and the server's IP | No: no e-mail, name or user IP is sent | Server-side, not from the browser, from `app` only via `npm_shared` (ADR-0002 D7); ≈ 3 s timeout, fails open. To be confirmed in M1 (opt-out env flag, or an offline list) |
| E-mail | Never (ADR-0005) | none | none | `mail.default` is pinned to `array` |
| Fonts, CDNs, analytics | Never | none | none | Verified in the TE-001 build output: no runtime third-party hosts. The external links on the placeholder welcome page are only followed when a user clicks them |

## Data subject rights (status)

| Right | Status in the scaffold | Target |
|---|---|---|
| Access/export (machine-readable) | Not yet | M6: in-app JSON download after re-entering the password (ADR-0005) |
| Rectification | Name and e-mail in Settings > Profile | Kept |
| Erasure | Self-service: Settings > Profile > Delete account (password confirmed). Admin/owner: `php artisan users:erase <id>` or `--from-file=<path>` / `-` (stdin), also for re-erasure after a restore. Both go through `App\Services\AccountEraser` (TE-047): one transaction per user removes `users`, its `sessions` rows (no FK, deleted by `user_id`) and `passkeys` (there is no `password_reset_tokens` table, ADR-0005), and writes an `account_erased` row to `audit_logs` (user ID + actor only). The protected `super_admin` (env `SUPER_ADMIN_EMAIL` until M1 roles) is refused. No HTTP route erases another user | Each later table with personal data (persons, travellers, messages, invoices) registers an eraser with the service (container tag `account.erasers`). Invoices are never deleted: kept 7 years (tax law) with the personal fields anonymised by their eraser |
| Objection to optional processing | Nothing optional yet | Each optional feature gets an opt-in |
