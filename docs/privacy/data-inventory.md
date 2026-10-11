# Data inventory

Owner: security-privacy-reviewer. Status: **draft**. It covers the scaffold (TE-001, Laravel React starter kit) only.
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
| `cache`, `cache_locks` | Rate-limiter keys (`email|ip` for login) | Brute-force protection | (f) | Nobody | Limiter decay (1 minute) | The cache key is an MD5 hash of the limiter name plus `email|ip` (Laravel default), so it is pseudonymous, not anonymous |
| `jobs`, `failed_jobs` | Serialized job payloads | Background work (none yet) | Depends on the job | DB admins | M1: prune `failed_jobs` (for example 7 days) | Jobs must reference IDs, never embed personal data |
| `storage/logs/laravel.log` | Exception context, may include request data | Debugging | (f) | Server admin | M1: daily rotation with a short retention | No personal data or secrets in log lines (see the logging rules) |
| `storage/inertia-devtools/` | Recorded Inertia page props per request | Dev tooling only (enabled only when `APP_ENV=local`) | n/a (dev only) | Anyone who can reach the dev server (no auth in `local`) | 24 h TTL, max 100 entries | Must never be enabled in production; dev ports should bind to 127.0.0.1 |

The kit's `DatabaseSeeder` creates `test@example.com` with the factory password `password`. That is dev-only fake
data and must never run in production (M1/M2 `reference` vs `demo` seeder split).

## External processors and outgoing requests

| Service | When | Data it receives | Personal data? | Status |
|---|---|---|---|---|
| Have I Been Pwned Pwned Passwords API (`api.pwnedpasswords.com`) | Production only: registration and password change, through `Password::defaults()->uncompromised()` | The first 5 hex characters of the password's SHA-1 hash (k-anonymity) and the server's IP | No: no e-mail, name or user IP is sent | Server-side, not from the browser. To be confirmed in M1 (opt-out env flag, or an offline list) |
| E-mail | Never (ADR-0005) | none | none | `mail.default` is pinned to `array` |
| Fonts, CDNs, analytics | Never | none | none | Verified in the TE-001 build output: no runtime third-party hosts. The external links on the placeholder welcome page are only followed when a user clicks them |

## Data subject rights (status)

| Right | Status in the scaffold | Target |
|---|---|---|
| Access/export (machine-readable) | Not yet | M6: in-app JSON download after re-entering the password (ADR-0005) |
| Rectification | Name and e-mail in Settings > Profile | Kept |
| Erasure | Settings > Profile > Delete account (password confirmed); passkeys cascade; sessions are invalidated | M1+: also erase or anonymise related data; invoices kept 7 years (tax law), with the personal link minimised |
| Objection to optional processing | Nothing optional yet | Each optional feature gets an opt-in |
