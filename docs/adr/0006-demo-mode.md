# ADR-0006: Demo mode for the public portfolio demo

- **Status:** Proposed. The owner approved the feature in principle on 2026-10-11 (`docs/plan.md` § 9, Q8 and Q16).
  The exact change list below needs the owner's acceptance (`docs/plan.md` § 10, N1).
- **Date:** 2026-10-11
- **Author:** architect
- **Numbering:** ADRs are numbered in the order they're written. 0002–0005 are reserved for the decisions planned
  in `docs/plan.md` § 7, so this one is 0006.

## Context

The live TravelEasy site is a **public portfolio demo** (Q2). Visitors must be able to try every role, so the
owner publishes credentials for a demo admin, manager, employee and customer. The site runs on demo data that
**resets every night** (Q16).

That collides with three rules:
1. `CLAUDE.md` § Conventions: "No demo-only hacks in production code". The original had `dev_connection` /
   `simulate_error` checkboxes in production forms (`legacy-analysis.md` §3).
2. Q8: 2FA is **required** for manager and admin. Published accounts can't have a shared TOTP secret.
3. `legacy-analysis.md` §3: "demo credentials published in the README". In the original, they were real admin
   accounts with no limits.

There are also new risks from publishing an admin login on the internet:
- anyone can change the demo accounts' passwords and lock everyone else out;
- anyone logged in as the demo admin can read the personal data of every visitor who registered;
- anyone can use admin features that send mail (staff invitations) to send mail to arbitrary addresses;
- a reset job that wipes the database is dangerous if it ever runs on a non-demo install.

## Options

| Option | Pros | Cons |
|---|---|---|
| A. No demo mode; publish normal accounts | No extra code | Accounts get hijacked; 2FA can't be required; visitor data exposed to the public; no clean-up |
| B. A separate demo branch or build | Production code stays "pure" | Two codebases drift; the demo stops showing the real product; double CI |
| C. **One config flag, `DEMO_MODE`, with a closed list of changes in one service** | One codebase; behaviour is explicit, documented and testable; off by default | Some conditional code; every change must be tested in both modes |

## Decision

**Option C.** `DEMO_MODE` is an env flag read once in `config/traveleasy.php` (`traveleasy.demo.enabled`),
**default `false`**, and turned on only in the live demo stack. All behaviour that depends on it goes through one
`App\Support\DemoMode` service. A Pest arch test forbids reading the flag anywhere else. This is an
**owner-approved exception** to "no demo-only hacks", for these reasons: it is config-driven, not a UI switch; it's
listed here in full; it's tested on and off; and it never weakens security.

### What demo mode changes (the complete list)

| ID | Change when `DEMO_MODE=true` | Milestone |
|---|---|---|
| D1 | **Demo accounts.** The `demo` seeder creates four accounts (customer, employee, manager, admin) with `users.is_demo = true`. Their e-mails use a reserved, undeliverable domain (e.g. `@demo.traveleasy.invalid`), so no mail can ever be sent to them. The password comes from env (`DEMO_ACCOUNT_PASSWORD`, required in demo mode). | M1 |
| D2 | **2FA exemption.** Only `is_demo` accounts are exempt from mandatory 2FA for manager/admin. Every other manager/admin, including the owner's own admin, still must use 2FA. | M1 |
| D3 | **Locked demo accounts.** `is_demo` accounts can't change their password, e-mail or 2FA settings, and can't delete themselves. Password reset isn't offered for them. No one can change their role, deactivate them or delete them. The UI explains why (nl/en); the server refuses with 403. | M1 |
| D4 | **Fail-safe when off.** If `DEMO_MODE` is off: the demo seeder doesn't create demo accounts, any existing `is_demo` account is refused at login, and D2/D3/D5–D10 are inert. | M1 |
| D5 | **Banner and warnings.** A site-wide banner (nl/en): this is a demo, data resets every night, don't enter real personal data. Extra warnings on the registration form and the booking travellers step. | M6 |
| D6 | **Visitor-data isolation.** `is_demo` staff accounts don't see records owned by self-registered (non-demo) customers: their accounts, bookings, invoices, conversations and travellers. They do see all demo-seeded data and anything created through demo accounts. Implemented as a policy + query scope in the `DemoMode` service, and tested on every staff list and detail. | M6, extended in M7–M10 |
| D7 | **Passport warning.** A prominent "don't enter real passport data" warning on the passport fields. | M10 |
| D8 | **No staff invitations.** Staff invitations are disabled (the public demo admin could otherwise mail any address). Real staff accounts are created by the owner with the artisan command. | M6 |
| D9 | **Demo accounts on the login page.** The login page lists the four demo accounts with role, e-mail and the password from config. | M6 |
| D10 | **Nightly reset.** The `demo:reset` command, scheduled nightly (proposed 04:00 Europe/Amsterdam): maintenance mode on → wipe all application data, sessions, queued jobs and private files (exports, PDFs) → run the `reference` and `demo` seeders (demo trips etc. dated relative to today) → keep non-demo staff accounts (the owner's real admin; § 10 N6) → run the DB backup of the clean state → maintenance mode off → write an audit/log line. It's registered in the scheduler **only** when demo mode is on, and the command itself refuses to run if demo mode is off. | M6 |

### What demo mode never changes

- Authorization: demo accounts have **exactly** their role's rights, and demo mode only takes rights away (D3, D6, D8).
- CSRF, security headers, CSP, cookies, session handling, encryption, validation, rate limits, bot protection,
  the mail budget, the audit log and logging rules.
- 2FA for any account that isn't `is_demo`.
- Payment behaviour: the payment provider is selected by `PAYMENT_PROVIDER`, independently of demo mode.

### Tests

- Every change D1–D10 has feature tests with the flag **on and off**.
- An arch test makes sure only `DemoMode` reads `traveleasy.demo.enabled`.
- Reset tests: visitor data is gone, demo data and the owner's staff accounts are back, and the command refuses
  (and changes nothing) when demo mode is off. The scheduler doesn't list it when demo mode is off.
- CI runs the full suite with demo mode off (the default), plus the demo-mode tests with it on.

## Consequences

- The live demo is safe to publish credentials for. Accounts can't be hijacked, visitor data isn't exposed to
  the public, mail can't be abused through admin features, and everything visitors enter is gone within 24 hours.
- A normal install (`DEMO_MODE` unset) behaves as if demo mode didn't exist.
- Every story that adds a staff view of customer data must apply D6, and every story that adds data must be covered
  by the reset test. Both are in `docs/plan.md` § 6.
- `CLAUDE.md` says the `demo` seeder is dev only. It now also runs on the live demo, but only behind `DEMO_MODE`.
- `.env.example` documents `DEMO_MODE` (default `false`) and `DEMO_ACCOUNT_PASSWORD`.
- Backups on the demo contain only freshly seeded data, so they never keep visitor data past the 24-hour window.
