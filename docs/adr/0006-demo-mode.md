# ADR-0006: Demo mode for the public portfolio demo

- **Status:** Accepted (2026-10-11). The owner approved the feature in principle (`docs/plan.md` § 9, Q8 and Q16)
  and accepted this change list with amendments (`docs/plan.md` § 10, N1–N3, N6): the `super_admin` role,
  server-side masking instead of hiding visitor records, invitations for super_admin only, and a 03:45 reset.
- **Date:** 2026-10-11
- **Author:** architect
- **Numbering:** ADRs are numbered in the order they're written. 0002–0005 are reserved for the decisions planned
  in `docs/plan.md` § 7, so this one is 0006.

## Context

The live TravelEasy site is a **public portfolio demo** (Q2). Visitors must be able to try every role, so the
owner publishes credentials for a demo admin, manager, employee and customer. Customer registration stays open.
The site runs on demo data that **resets every night** (Q16).

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

The owner also needs one account that is never public and can do what the demo accounts can't. That's the
`super_admin` role (`docs/plan.md` § 7). It's a normal role that exists in every install, not a demo-mode feature,
but demo mode depends on it.

## Options

| Option | Pros | Cons |
|---|---|---|
| A. No demo mode; publish normal accounts | No extra code | Accounts get hijacked; 2FA can't be required; visitor data exposed to the public; no clean-up |
| B. A separate demo branch or build | Production code stays "pure" | Two codebases drift; the demo stops showing the real product; double CI |
| C. **One config flag, `DEMO_MODE`, with a closed list of changes in one service** | One codebase; behaviour is explicit, documented and testable; off by default | Some conditional code; every change must be tested in both modes |

## Decision

**Option C.** `DEMO_MODE` is an env flag read once in `config/traveleasy.php` (`traveleasy.demo.enabled`),
**default `false`**, and turned on only in the live demo stack. All behaviour that depends on it goes through one
`App\Support\DemoMode` service. A Pest arch test forbids reading the flag anywhere else. This is the **one
owner-approved exception** to "no demo-only hacks", for these reasons: it is config-driven, not a UI switch; it's
listed here in full; it's tested on and off; and it never weakens security.

### What demo mode changes (the complete list)

| ID | Change when `DEMO_MODE=true` | Milestone |
|---|---|---|
| D1 | **Demo accounts.** The `demo` seeder creates four accounts (customer, employee, manager, admin) with `users.is_demo = true`. Their e-mails use a reserved, undeliverable domain (e.g. `@demo.traveleasy.invalid`), so no mail can ever be sent to them. The password comes from env (`DEMO_ACCOUNT_PASSWORD`, required in demo mode). A `super_admin` can never be a demo account (DB CHECK `role <> 'super_admin' OR is_demo = false`). | M1 |
| D2 | **2FA exemption.** Only `is_demo` accounts are exempt from mandatory 2FA for manager/admin. Every other manager/admin, and the super_admin, still must use 2FA. | M1 |
| D3 | **Locked demo accounts.** `is_demo` accounts can't change their password, e-mail or 2FA settings, and can't delete themselves. Password reset isn't offered for them. No one can change their role, deactivate them or delete them. The UI explains why (nl/en); the server refuses with 403. | M1 |
| D4 | **Fail-safe when off.** If `DEMO_MODE` is off: the demo seeder doesn't create demo accounts, any existing `is_demo` account is refused at login, and D2/D3/D5–D10 are inert. | M1 |
| D5 | **Banner and warnings.** A site-wide banner (nl/en): this is a demo, data resets every night, don't enter real personal data. Extra warnings on the registration form and the booking travellers step. | M6 |
| D6 | **Server-side masking of visitor personal data.** For every staff account **except `super_admin`** (so including the demo admin and demo manager), personal data of self-registered (non-demo) customers is masked. It applies to every visitor, whatever their e-mail address; there is no attempt to tell test addresses from real ones. Details below. | M6, extended in M7 (back office), M8 (messages), M10 (passport) |
| D7 | **Passport warning.** A prominent "don't enter real passport data" warning on the passport fields. | M10 |
| D8 | **Invitations: super_admin only.** Staff invitations are disabled for every account except `super_admin` (the public demo admin could otherwise mail any address). The super_admin can still invite in demo mode. | M6 (M1 builds the check) |
| D9 | **Demo accounts on the login page.** The login page lists the four demo accounts with role, e-mail and the password from config. | M6 |
| D10 | **Nightly reset.** The `demo:reset` command runs nightly at **03:45 Europe/Amsterdam** (the server's time zone; the scheduler runs in that zone and stored times stay UTC; 03:45 is outside the DST switch hours). Steps: maintenance mode on → wipe all application data, **all sessions (everyone is logged out)**, queued jobs and private files (exports, PDFs) → run the `reference` and `demo` seeders (demo trips etc. dated relative to today) → **keep the `super_admin` account(s)** with their 2FA → maintenance mode off → write an audit/log line → trigger the DB dump of the clean state. It's registered in the scheduler **only** when demo mode is on, and the command itself refuses to run if demo mode is off. The dump must not run at the same time as the owner's existing jobs at 04:00 and 04:15; devops sets its exact time (e.g. 03:50, or chained after the reset) after measuring how long it takes. | M6 |

### D6 masking: how it works

- **Where:** in the server-side data layer (spatie/laravel-data objects and any PDF/CSV/JSON/mail rendering for staff),
  through one `DemoMode::mask()` policy. The real value is replaced **before** it's serialised, so it never reaches
  the browser, the Inertia page props, the SSR HTML or a download. Masking in React is not allowed.
- **Who sees masked values:** every authenticated staff user except `super_admin`, while demo mode is on. This
  includes any non-demo employee/manager/admin, not only the four demo accounts (confirmation pending:
  `docs/plan.md` § 11 Q-C). Customers only ever see their own data, so masking doesn't apply to them.
- **Whose data is masked:** every customer and traveller record owned by a non-demo (self-registered) user, and
  everything those users wrote. Demo-seeded data and data created through the demo customer account are shown normally.
- **Fields:** name → pseudonym ("Bezoeker #1234" / "Visitor #1234", stable per record, not derived from the real
  name); e-mail, phone, address, date of birth → "verborgen in demo" / "hidden in demo"; traveller names → pseudonym;
  free text written by visitors (special requests, message content) → "hidden in demo"; passport data (M10) →
  hidden. No partial values (no first letters, no domains).
- **Search and sort:** staff search and sorting don't match masked visitor fields, so search can't be used to
  confirm whether an address is registered. Filters on non-personal fields (dates, trip, status) still work.
- **Audit log viewer:** user references to visitors are shown as pseudonyms.

### What demo mode never changes

- Authorization: every account has **exactly** its role's rights, and demo mode only takes rights away (D3, D6,
  D8). It never grants anything, not even to super_admin. Super_admin's extra rights are normal role abilities
  that exist in every install (`docs/plan.md` § 7).
- CSRF, security headers, CSP, cookies, session handling, encryption, validation, rate limits, bot protection,
  the mail budget, the audit log and logging rules.
- 2FA for any account that isn't `is_demo`.
- Payment behaviour: the payment provider is selected by `PAYMENT_PROVIDER`, independently of demo mode.

### Tests

- Every change D1–D10 has feature tests with the flag **on and off**.
- An arch test makes sure only `DemoMode` reads `traveleasy.demo.enabled`.
- Masking: a test logs in as each staff role (demo and non-demo) and as super_admin, crawls every staff route and
  download, and checks that a seeded visitor's real name and e-mail appear **only** for super_admin, and appear for
  everyone when demo mode is off.
- Reset tests: visitor data is gone, demo data is back, super_admin is kept with its 2FA, all sessions are invalid,
  and the command refuses (and changes nothing) when demo mode is off. The scheduler doesn't list it when demo
  mode is off.
- CI runs the full suite with demo mode off (the default), plus the demo-mode tests with it on.

## Consequences

- The live demo is safe to publish credentials for. Accounts can't be hijacked, visitor data isn't exposed to the
  public (not even to the demo admin), mail can't be abused through admin features, and everything visitors enter
  is gone from the database within 24 hours. Copies of outgoing mail in the Gmail account are a separate issue,
  tracked in `docs/plan.md` § 11 Q-A.
- A normal install (`DEMO_MODE` unset) behaves as if demo mode didn't exist.
- Every story that adds a staff view, download or search of customer data must apply D6, and every story that adds
  data must be covered by the reset test. Both are in `docs/plan.md` § 6.
- `CLAUDE.md` § Conventions points to this ADR as the one sanctioned, config-driven exception: the `demo` seeder and
  the nightly reset run on the live demo only when `DEMO_MODE` is on.
- `.env.example` documents `DEMO_MODE` (default `false`) and `DEMO_ACCOUNT_PASSWORD`.
- Backups on the demo contain only freshly seeded data plus the super_admin, so they never keep visitor data past the
  24-hour window.
