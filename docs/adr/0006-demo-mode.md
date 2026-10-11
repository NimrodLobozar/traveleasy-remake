# ADR-0006: Demo mode for the public portfolio demo

- **Status:** Accepted (2026-10-11). The owner approved the feature in principle (`docs/plan.md` § 9, Q8 and Q16)
  and accepted this change list with amendments (`docs/plan.md` § 10, N1–N3, N6): the `super_admin` role,
  server-side masking instead of hiding visitor records, staff creation for super_admin only, and a 03:45 reset.
- **Amended 2026-10-11** (`docs/plan.md` § 11):
  - no outgoing e-mail at all (ADR-0005), so the mail-related items are gone;
  - staff onboarding by setup code instead of an invitation e-mail;
  - guest support chat is masked and wiped by the reset;
  - there are never real staff on the demo;
  - the super_admin is provisioned from env, re-created after every reset, and hidden from all other roles;
  - reset + dump must finish in under ~10 minutes, with an alert.
- **Date:** 2026-10-11
- **Author:** architect
- **Numbering:** ADRs are numbered in the order they're written. When this ADR was written, 0002–0005 were reserved
  for decisions planned in `docs/plan.md` § 7, so this one is 0006. (0005 has since been used for "No outgoing e-mail".)

## Context

The live TravelEasy site is a **public portfolio demo** (Q2). Visitors must be able to try every role, so the
owner publishes credentials for a demo admin, manager, employee and customer. Customer registration stays open, and
guests can use the support chat without an account. The site runs on demo data that **resets every night** (Q16).
The app sends no e-mail (ADR-0005). Apart from the owner's own `super_admin`, there are **never real staff** on the
demo: every other staff account is a demo account.

That collides with three rules:
1. `CLAUDE.md` § Conventions: "No demo-only hacks in production code". The original had `dev_connection` /
   `simulate_error` checkboxes in production forms (`legacy-analysis.md` §3).
2. Q8: 2FA is **required** for manager and admin. Published accounts can't have a shared TOTP secret.
3. `legacy-analysis.md` §3: "demo credentials published in the README". In the original, they were real admin
   accounts with no limits.

There are also new risks from publishing an admin login on the internet:
- anyone can change the demo accounts' passwords and lock everyone else out;
- anyone logged in as the demo admin can read the personal data of every visitor who registered or chatted;
- anyone logged in as the demo admin can create staff accounts;
- a reset job that wipes the database is dangerous if it ever runs on a non-demo install;
- the reset shares the server with the owner's other demo sites, whose own resets run at 04:00 and 04:15.

The owner needs one account that is never public and can do what the demo accounts can't. That's the
`super_admin` role (`docs/plan.md` § 7). It exists in every install and is provisioned from env. It isn't a
demo-mode feature, but demo mode depends on it.

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
| D1 | **Demo accounts.** The `demo` seeder creates four accounts (customer, employee, manager, admin) with `users.is_demo = true`. Their login names use a reserved domain (e.g. `@demo.traveleasy.invalid`), so they can never collide with a visitor's address. The password comes from env (`DEMO_ACCOUNT_PASSWORD`, required in demo mode). A `super_admin` can never be a demo account (DB CHECK `role <> 'super_admin' OR is_demo = false`). | M1 |
| D2 | **2FA exemption.** Only `is_demo` accounts are exempt from mandatory 2FA for manager/admin. The super_admin always uses 2FA. | M1 |
| D3 | **Locked demo accounts.** `is_demo` accounts can't change their password, login name or 2FA settings, and can't delete themselves. They have no recovery code, and the recovery-code reset isn't available for them. No one can change their role, deactivate them or delete them. The UI explains why (nl/en); the server refuses with 403. | M1 |
| D4 | **Fail-safe when off.** If `DEMO_MODE` is off: the demo seeder doesn't create demo accounts, any existing `is_demo` account is refused at login, and D2/D3/D5–D10 are inert. | M1 |
| D5 | **Banner and warnings.** A site-wide banner (nl/en): this is a demo, data resets every night, don't enter real personal data. Extra warnings on the registration form, the booking travellers step and the support-chat panel. | M6, M8 |
| D6 | **Server-side masking of visitor personal data.** For every staff account **except `super_admin`** (on the demo that means every demo staff account, including the demo admin and demo manager), personal data of self-registered (non-demo) customers **and of guests in the support chat** is masked. It applies to every visitor, whatever their login name; there is no attempt to tell test addresses from real ones. Details below. | M6, extended in M7 (back office), M8 (support chat), M10 (passport) |
| D7 | **Passport warning.** A prominent "don't enter real passport data" warning on the passport fields. | M10 |
| D8 | **Staff account creation: super_admin only.** No account except `super_admin` can create staff accounts or issue setup codes (`docs/plan.md` M1). Because there are never real staff on the demo, this is in practice unused there. | M6 (M1 builds the check) |
| D9 | **Demo accounts on the login page.** The login page lists the four demo accounts with role, login name and the password from config. The super_admin is never listed or hinted at. | M6 |
| D10 | **Nightly reset.** See below. | M6 (chat data from M8) |

**D10 nightly reset, in detail.**
- **When:** the `demo:reset` command runs nightly at **03:45 Europe/Amsterdam**, the server's time zone. The scheduler
  runs in that zone and stored times stay UTC. 03:45 is outside the DST switch hours.
- **Steps:**
  1. Maintenance mode on.
  2. Wipe **all** application data: accounts, bookings, invoices, support conversations including guest
     conversations, all sessions (everyone is logged out), queued jobs and private files (exports, PDFs).
  3. Run the `reference` and `demo` seeders (demo trips etc. dated relative to today).
  4. Re-provision the super_admin from env (see the super_admin section below).
  5. Maintenance mode off.
  6. Run the DB dump of the clean state, chained.
  7. Write an audit/log line with durations.
- **Guards:** it's registered in the scheduler **only** when demo mode is on, and the command itself refuses to run if
  demo mode is off.
- **Timing:** reset + dump must finish **well before 04:00**, with a target of **under ~10 minutes**, because the
  owner's other demo sites on the same server reset at 04:00 and 04:15. If the run goes over the configured threshold
  or is still running at 03:58, it logs an error, writes an audit entry and shows a warning on the super_admin
  dashboard. There is no e-mail (ADR-0005), so alerts go to the app and the logs.

### D6 masking: how it works

- **Where:** in the server-side data layer (spatie/laravel-data objects and any PDF/CSV/JSON rendering for staff),
  through one `DemoMode::mask()` policy. The real value is replaced **before** it's serialised, so it never reaches
  the browser, the Inertia page props, the SSR HTML, the chat polling responses or a download. Masking in React is
  not allowed.
- **Who sees masked values:** every authenticated staff user except `super_admin`, while demo mode is on.
  Customers and guests only ever see their own data, so masking doesn't apply to them.
- **Whose data is masked:** every customer and traveller record owned by a non-demo (self-registered) user, every
  guest support conversation, and everything those users and guests wrote. Demo-seeded data and data created
  through the demo customer account are shown normally.
- **Fields:**

  | Field | Shown as |
  |---|---|
  | name, guest display name, traveller names | a pseudonym ("Bezoeker #1234" / "Visitor #1234"), stable per record, not derived from the real name |
  | login name (e-mail), phone, address, date of birth | "verborgen in demo" / "hidden in demo" |
  | visitor- and guest-written free text (special requests, chat messages) | "verborgen in demo" / "hidden in demo" |
  | passport data (M10) | hidden |

  No partial values are shown (no first letters, no domains).
- **Search and sort:** staff search and sorting don't match masked visitor fields, so search can't be used to
  confirm whether an address is registered. Filters on non-personal fields (dates, trip, status) still work.
- **Audit log viewer:** user references to visitors and guests are shown as pseudonyms.

### The super_admin and demo mode

- These rules hold in every install, not only in demo mode; the plan has the full description (`docs/plan.md` M1).
- **Provisioning:** the super_admin is provisioned from env on every boot and after every reset:
  `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD_HASH` (a hash, never the plain password) and
  `SUPER_ADMIN_TOTP_SECRET`, so the owner's authenticator keeps working.
- **Without the env vars**, there is no super_admin, and the app works normally.
- **Hidden:** it is hidden from every other role. It's never listed, counted, searchable or shown in any UI, export,
  audit view or demo login hint. Where its actions must be attributed, other roles see "TravelEasy" (system).
- **Accepted trade-off:** the Portainer stack's env holds the password hash and the TOTP secret, so access to the
  stack equals access to this account. The owner accepts this for a demo, because Portainer access already means
  control of the server.

### What demo mode never changes

- Authorization: every account has **exactly** its role's rights, and demo mode only takes rights away (D3, D6,
  D8). It never grants anything, not even to super_admin. Super_admin's extra rights are normal role abilities
  that exist in every install (`docs/plan.md` § 7).
- CSRF, security headers, CSP, cookies, session handling, encryption, validation, rate limits, bot protection,
  the registration cap and chat limits, the audit log and logging rules.
- 2FA for any account that isn't `is_demo`.
- Payment behaviour: the payment provider is selected by `PAYMENT_PROVIDER`, independently of demo mode.
- E-mail: there is none, in any mode (ADR-0005).

### Tests

- Every change D1–D10 has feature tests with the flag **on and off**.
- An arch test makes sure only `DemoMode` reads `traveleasy.demo.enabled`.
- Masking: a test logs in as each staff role (demo) and as super_admin, crawls every staff route, chat endpoint and
  download, and checks that a seeded visitor's or guest's real name, login name and message text appear **only** for
  super_admin, and appear for everyone when demo mode is off.
- Super_admin visibility: a crawl as every other role (and the login page) proves the super_admin's name and login
  name never appear and that it's excluded from every count and search.
- Reset tests:
  - visitor and guest data are gone, demo data is back, and the super_admin is re-provisioned from env (or absent
    when the env vars are missing);
  - all sessions are invalid;
  - the command refuses (and changes nothing) when demo mode is off;
  - the scheduler doesn't list it when demo mode is off;
  - the overrun alert fires when the run is too slow.
- CI runs the full suite with demo mode off (the default), plus the demo-mode tests with it on.

## Consequences

- The live demo is safe to publish credentials for:
  - accounts can't be hijacked;
  - visitor and guest data isn't exposed to the public (not even to the demo admin);
  - the demo admin can't create staff;
  - no e-mail exists that could be abused;
  - everything visitors and guests enter is gone from the database within 24 hours.
- A normal install (`DEMO_MODE` unset) behaves as if demo mode didn't exist.
- Every story that adds a staff view, download, search or chat endpoint over customer or guest data must apply D6,
  and every story that adds data must be covered by the reset test. Both are in `docs/plan.md` § 6.
- `CLAUDE.md` § Conventions points to this ADR as the one sanctioned, config-driven exception: the `demo` seeder and
  the nightly reset run on the live demo only when `DEMO_MODE` is on.
- `.env.example` documents `DEMO_MODE` (default `false`), `DEMO_ACCOUNT_PASSWORD` and the `SUPER_ADMIN_*` variables.
- Backups on the demo contain only freshly seeded data plus the env-provisioned super_admin, so they never keep visitor
  data past the 24-hour window.
- The demo dataset must stay small enough for reset + dump to finish in under ~10 minutes on the shared server.
