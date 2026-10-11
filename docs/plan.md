# TravelEasy remake: roadmap

- **Owner of this file:** `architect` (stories are written from it by the `task-writer`)
- **Status:** Accepted (2026-10-11). The owner's answers are recorded in § 9 (first round), § 10 (follow-up round)
  and § 11 (third round: **no outgoing e-mail**, recovery codes, support chat). Open questions are in § 12.
- **Inputs:** `CLAUDE.md`, `docs/legacy-analysis.md`, `docs/adr/0001-tech-stack.md`, `docs/stack-options.md`,
  `docs/adr/0005-no-outgoing-email.md`, `docs/adr/0006-demo-mode.md`, `docs/backlog/ideas.md`

## 1. Goal

Rebuild TravelEasy as a solo project that does better than the original on every front. The live site is a
**public portfolio demo** of a fictional travel agency, linked from the owner's portfolio. Visitors can register as
customers, or log in with published demo accounts (customer, employee, manager, admin), and try everything. The
data is demo data that **resets every night**.

Customers can **actually book** flights and **flight + hotel packages**: search, pick, add travellers, apply an
offer code, review, pay (simulated, later Mollie test mode), confirm and get an invoice. Anyone, including guests,
can ask questions through a support-chat widget. Staff run the agency from one consistent back office. The app
sends **no e-mail at all** (ADR-0005): accounts use the e-mail address only as a login name, and everything else
happens in the app. The app is accessible (WCAG 2.2 AA), bilingual (nl/en), private by design (AVG/GDPR),
secure (OWASP ASVS L2), server-rendered for SEO (Inertia SSR), and self-hosted on the CasaOS home server from a
pinned GHCR image.

How we get there:
- **A vertical slice first.** Search, then book, then invoice, end to end (M3 to M5), before the back office
  is broadened (M7+).
- **Deploy early and often.** The first home-server deploy is at the end of **M0** (LAN only). The site goes
  **public on the internet at the end of M6**: right after the booking slice works, and only once the security
  baseline, demo mode, nightly reset, bot protection and privacy statement are in place.
- **Quality is part of every story.** Security, privacy, accessibility, i18n and tests are acceptance
  criteria, not phases. Personal data is protected from the moment it is first stored (see § 6).
- There is **no deadline or time budget** (Q1). Milestones are ordered and sized, not scheduled.

## 2. Definitions of done

### A story is done when
- Every acceptance and quality criterion is ticked, and the story's testers all returned **PASS**:
  `qa-engineer` always, `accessibility-auditor` when UI changed, and `security-privacy-reviewer` when auth,
  permissions, personal data, pricing, payments, uploads, external calls, config, demo mode or dependencies changed.
- Every new route or record type has an authorization test (customer A can't reach customer B's data, and a
  customer can't reach staff routes).
- All UI strings exist in nl and en. Data that is new or changed is recorded in `docs/privacy/data-inventory.md`,
  and from M6 on, the export and erasure tests cover it.
- If the story touches anything demo mode changes (ADR-0006), it has tests with `DEMO_MODE` **on and off**.
- The task-writer has closed it with a changelog.

### A milestone is done when
| # | Criterion |
|---|---|
| 1 | All of the milestone's `must` stories are `done`. Any `should`/`could` stories that are left over are moved, with the move written down, to a later milestone or to `docs/backlog/ideas.md`. |
| 2 | CI is green on `main`: Pint, Larastan, ESLint, Prettier, `tsc --noEmit`, Pest, Vitest, Playwright + axe (against the SSR build), `composer audit`, `npm audit`, Trivy image scan (no unaccepted High/Critical). |
| 3 | The image is published to GHCR as `ghcr.io/nimrodlobozar/traveleasy:vX.Y.0` (milestone N gives `v0.N.0`; M11 gives `v1.0.0`) and is **deployed to the home server** (from M0 onwards) with every health check green. Deploying needs the owner's OK each time. |
| 4 | **A11y:** a milestone audit by the `accessibility-auditor` with no Blocker or Serious findings in the pages the milestone touched. The report goes in `docs/a11y/`. |
| 5 | **Security/privacy:** the `security-privacy-reviewer` gives a milestone PASS whenever the milestone touched auth, personal data, pricing, payments, config, demo mode, dependencies or deployment. Once the site is public (from M6), this applies to **every** milestone. |
| 6 | **Lighthouse ≥ 90** in every category, mobile profile, on the public pages the milestone touched (from M3), measured on the SSR build. Back-office pages: ≥ 90 for Accessibility and Best Practices. |
| 7 | The "after this you can …" outcome is covered by at least one Playwright journey that runs against the built image. |
| 8 | Docs are current: `CLAUDE.md` Status, `docs/deploy/*`, `docs/testing.md`, `docs/privacy/*`, and the ADRs for any decision made. |

## 3. Milestones at a glance

| # | Milestone | Size | Depends on | Ends with |
|---|---|---|---|---|
| M0 | Foundation and walking skeleton | L | none | Dev env, **SSR**, CI→GHCR, design system, **first home-server deploy (LAN only)** |
| M1 | Accounts, authentication and roles | M | M0 | Register/login, **recovery-code password reset**, 5 roles incl. **super_admin** + policies, 2FA (required for manager/admin/super_admin), **staff setup codes**, audit log, **demo-mode core** |
| M2 | Airports and trip catalogue | M | M1 | Airports reference data, staff manage trips, public trip list + detail |
| M3 | Search and public site | M | M2 | Dependent search on the home page, destinations, about, real content, SEO |
| M4 | Booking flow with offers | L | M3 | Customers book end to end; server-side pricing; offer codes; **payment-provider interface + simulated provider** |
| M5 | Invoices | M | M4 | Invoice issued on booking, in-app view + PDF, VAT; **vertical slice complete** |
| M6 | Public demo launch | M | M5 | **First internet exposure**: nightly reset, demo accounts, masking, bot protection, privacy statement, export/erasure |
| M7 | Staff back office | L | M6 | Dashboard, one customer module, booking management, staff management, audit viewer |
| M8 | Support chat | M | M7 | **Support-chat widget** on every public/customer page (guests too), staff support inbox, in-app unread badges |
| M9 | Packages (flight + hotel) | L | M7 (M5 for invoicing) | Hotels, packages with extras, package search and booking, staff management |
| M10 | Sensitive data and retention | M | M9 | Encrypted, optional passport data; retention jobs; full privacy docs |
| M11 | v1.0: Mollie adapter and final audits | M | M10 (M6 for public webhooks) | Optional Mollie test-mode adapter; ASVS L2 review; full WCAG audit; `v1.0.0` |
| M12 | AI travel assistant (nice-to-have) | M | M11 | Opt-in chatbot, off by default |

Sizes are relative (S < M < L). They aren't estimates in days, because there is no time budget (Q1).

## 4. Milestones

### M0: Foundation and walking skeleton (L)

**Goal:** a codebase, toolchain and pipeline that make every later story cheap and safe, plus an empty but real,
server-rendered app running on the home server.
**After this you can:** run `docker compose up`, edit a React page or a controller and see the change without
rebuilding. You can push to `main` and watch CI publish an image, and open the server-rendered app shell (nl/en
switch, skip link, light/dark theme, error pages) on the home server from your LAN.

**Scope**
- Scaffold from the official Laravel React starter kit. TypeScript strict, Tailwind, React Aria Components,
  spatie/laravel-data + typescript-transformer, Wayfinder. `minimum-stability: stable`. *(legacy §3 Engineering)*
- Quality tooling and test harness: Pint, Larastan (max level that's practical), ESLint, Prettier, `tsc`, Pest
  (including **Pest arch tests**: no `withoutVerifying()`, no CSRF exceptions, no `env()` outside config, no
  `dd`/`dump`, demo-mode checks only through the `DemoMode` service, no `Mail` facade / `Mailable` / `mail`
  notification channel), default mailer `array` in every environment (ADR-0005), Vitest + Testing Library, Playwright +
  @axe-core/playwright. *(legacy §3 Engineering, Security)*
- Docker dev env: `compose.yaml` + `compose.dev.yaml`, bind-mounted source, Vite HMR with polling,
  `vendor`/`node_modules` in anonymous volumes, PostgreSQL in a named volume. No mail catcher: the app sends no
  e-mail (ADR-0005). *(CLAUDE.md § Docker dev workflow)*
- **Inertia SSR (Q15).** The SSR entry point and build (`vite build --ssr`). An `ssr` service running
  `php artisan inertia:start-ssr` in the production compose. In dev, Vite HMR stays client-side; an optional
  `ssr` profile in `compose.dev.yaml` runs SSR against a build for checking. See "SSR impact" below.
- **ADR-0002: production runtime**: web server (FrankenPHP or Nginx + PHP-FPM) and how the SSR Node process is
  packaged (Node in the app image vs a separate SSR image). Owned by devops-engineer. Multi-stage, non-root
  production image(s) with health checks; the entrypoint waits for the DB, migrates, and seeds `reference` on an
  empty DB only. *(legacy §4 "worth keeping")*
- CI on GitHub Actions: lint, typecheck, tests, client + SSR build, `php artisan optimize` (proves `route:cache`
  works), an SSR smoke test (the HTML response contains server-rendered content), build `linux/amd64`, Trivy, push
  `vX.Y.Z` + `sha-…` to GHCR. *(legacy §3 Engineering: no CI; Domain: duplicate routes)*
- App shell: layout with landmarks, skip link, focus handling on Inertia navigation, language switch, theme
  switch, localised 403/404/419/429/500/503 pages. Baseline security headers (CSP with nonces, HSTS-ready,
  frame-ancestors, Referrer-Policy, Permissions-Policy). *(legacy §3 Design/UX, Security)*
- i18n infrastructure: shared Laravel JSON lang files for nl (default) and en, a `t()` helper in React that works
  in SSR, `Intl` formatting for dates/money, and a CI check that fails on keys missing in either language.
  *(legacy §3 Design: mixed NL/EN)*
- Design system by `ux-ui-designer`: Claude Design system artifact, `docs/design/system.md`, token file → CSS
  variables → Tailwind, base components (button, link, field, select, combobox, date picker, number field,
  table, dialog, alert/toast, banner, pagination) checked for contrast in both themes. *(legacy §3 Design: inconsistent style)*
- **First deploy to CasaOS/Portainer**, reachable on the LAN only (an NPM proxy host with a LAN access list, or
  a LAN port). Secrets come from env with `${VAR:?}` and there are no default passwords. `docs/deploy/dev.md`
  and `docs/deploy/casaos-portainer.md` v1. *(legacy §3 Security: default passwords; Engineering: image built on server)*

**SSR impact (Q15)**
- **One more long-running process** in production (`ssr`, Node), roughly 100–200 MB RAM. Resource limits are set
  in the compose file; ADR-0002 decides whether Node lives in the app image (one image, bigger) or in a separate SSR
  image (two images to build and pin).
- **Code rules:** components must be SSR-safe. No `window`/`document`/`localStorage` at module level or during
  render; browser-only code goes in effects. Theme and locale are resolved server-side (cookie/user preference) so
  the first paint is correct and there's no flash. An ESLint rule and the SSR smoke test enforce this.
- **CI:** builds both bundles, and the Playwright suite runs against the SSR build.
- **Failure mode:** if the SSR process is down, Inertia falls back to client-side rendering. The health check
  alerts on it, but the site stays usable.

**Agents:** devops-engineer (lead), ux-ui-designer, frontend-developer, backend-developer, qa-engineer
(`docs/testing.md`), accessibility-auditor (design system + shell), security-privacy-reviewer (headers, compose,
secrets).

**Exit criteria:** milestone DoD (§ 2), plus: a code change shows up in the browser without a rebuild (checked on
Windows); the page source of the shell contains server-rendered HTML; `v0.0.x` runs on the home server with
healthy services and no host-exposed DB port.

**Candidate epics / stories**
- Epic: Project scaffold and tooling
  - Scaffold Laravel React starter kit with strict TypeScript
  - Configure Pint, Larastan, ESLint, Prettier and type generation
  - Add Pest arch tests that forbid the legacy anti-patterns
  - Set up Vitest and Playwright with an axe check
  - Write `docs/testing.md` (strategy, commands, CI gates)
- Epic: Docker dev environment
  - Dev compose with hot reload and a Postgres named volume
  - `docs/deploy/dev.md`: daily commands, when to rebuild, safe reset
- Epic: Server-side rendering
  - Inertia SSR entry point, SSR-safe i18n and theme
  - SSR service in compose and an SSR smoke test in CI
- Epic: CI/CD to GHCR
  - ADR-0002: production runtime (web server + SSR packaging)
  - Production multi-stage image with entrypoint and health check
  - GitHub Actions pipeline: quality gates → amd64 build → Trivy → GHCR
- Epic: App shell
  - Accessible base layout (landmarks, skip link, route-change focus)
  - Language switch nl/en with persisted preference
  - Light/dark theme with `prefers-color-scheme` and a manual override, without a flash under SSR
  - Localised error pages
  - Security headers and CSP baseline
  - i18n key-completeness check in CI
- Epic: Design system
  - Design tokens and type scale (Claude Design artifact)
  - Base component specs with states, keyboard behaviour and ARIA patterns
  - Implement base components on React Aria with Vitest + axe tests
- Epic: First home-server deploy
  - Production `compose.yaml` for Portainer (pinned tag, health checks, limits, `ssr` service)
  - First LAN-only deploy and `docs/deploy/casaos-portainer.md`

**Risks**
- File-watching on Windows bind mounts is unreliable. Mitigation: polling, and documenting the WSL file-system option.
- SSR hydration mismatches (dates, time zones, locale). Mitigation: format on the server with the same locale and
  time zone, and add an SSR test for pages with dates.
- The design system can block everything after it. Mitigation: build tokens and base components first, and design
  screens per milestone.
- Too much yak-shaving. Mitigation: M0 stops at "one page, end to end, server-rendered, deployed"; anything else goes to
  the milestone that needs it.

### M1: Accounts, authentication and roles (M)

**Goal:** people can have accounts, and every request is authorized by one consistent model.
**After this you can:** register as a customer with your e-mail address as login name and save your one-time
recovery code, log in, set a new password with your e-mail + recovery code if you forget it, and edit or delete your
profile. As super_admin (the owner's private account) or admin, you can create a staff account and hand the new
colleague a one-time setup code. Managers, admins and the super_admin must use 2FA. With `DEMO_MODE` on, you can log
in with the four demo accounts, which skip 2FA and can't change their credentials.

**Scope**
- Fortify: register (open to anyone, Q5), login, profile edit, password change, account deletion (extended to all
  modules in M6). **No e-mail verification and no password reset by e-mail** (ADR-0005): Fortify's
  `emailVerification` and `resetPasswords` features are off, and the starter kit's verify/reset pages and mail
  templates are removed. The **e-mail address is only a login name** on `users`: unique (case-insensitive),
  format-checked, never verified, never mailed, and masked for demo staff (ADR-0006 D6). Changing it needs the
  current password. *(legacy §2 Auth; §3 Security: login via contacts table)*
- **Account recovery code (§ 11 R3).**
  - At registration (and at staff account setup) the user gets one **recovery code** ("herstelcode"): random,
    at least 100 bits (e.g. 20 base32 characters in groups), shown **once** on its own page with "copy" and
    "download as .txt" buttons and a required, not pre-ticked "I've saved my code" confirmation. Only a hash is
    stored (the password hasher). It's never logged.
  - **Forgot password:** one form with e-mail + recovery code + new password. On success the password is set, all
    of the user's sessions end, the old code stops working and a **new code is shown once**. On failure there is
    one generic message, the same whether or not the e-mail exists.
  - **Rate limits** per IP and per account (e.g. 5 attempts per hour per account, with escalating lock-out), and
    every attempt (success or failure) goes to the audit log. A reset doesn't switch off 2FA.
  - **Regenerate** the code from the profile after re-entering the current password; the old code stops working
    (audited).
  - Lose both password and code and the account is lost to the user. The documented last resort is the super_admin
    running `php artisan user:reset-password` on the server, after checking the person's identity out of band. It
    issues a one-time setup code (below) and is audited.
  - The UI calls it "herstelcode / recovery code" and explains how it differs from the 2FA recovery codes. Demo
    accounts don't get one (ADR-0006 D3).
- **2FA (Q8):** TOTP **required** for `manager`, `admin` and `super_admin` (enrolment is forced at first login,
  and the staff area is blocked until it's done); **optional** for `employee` and `customer`. 2FA recovery codes,
  plus an artisan 2FA reset command (server access only). Login throttling, session regeneration,
  Secure/HttpOnly/SameSite cookies.
- Roles `customer`, `employee`, `manager`, `admin`, `super_admin` as one enum on the user. Policies for every
  model, deny by default, and no blanket `Gate::before` bypass, not even for `super_admin` (its extra rights are
  explicit policy abilities). Authorization props are sent to Inertia for display only. Role matrix in § 7.
  *(legacy §3 Security: no authorization; role checked three ways)*
- **`super_admin`, provisioned from env and hidden (§ 10, § 11 Q-D).** The owner's private account and the
  only real account on the live demo.
  - **Env is the source of truth:** `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD_HASH` (a bcrypt/argon2id hash,
    never the plain password) and `SUPER_ADMIN_TOTP_SECRET`.
  - A `traveleasy:provision-super-admin` step creates or updates the account on every boot (entrypoint) and right
    after every nightly reset. It's idempotent and syncs the hash and the TOTP secret (stored encrypted, 2FA marked
    as confirmed), so the owner's authenticator app keeps working across resets.
  - Helper commands print a password hash (`traveleasy:hash-password`) and generate a TOTP secret with a terminal QR
    code (`traveleasy:totp-secret`) for the owner to paste into the Portainer stack.
  - If the env vars are missing, there is **no super_admin**: an existing super_admin row is disabled, and the app
    works normally without it. If the e-mail is already used by another account, provisioning fails loudly in the
    log and changes nothing. Registration rejects the reserved address with the normal "already taken" message.
  - Password and 2FA can't be changed in the UI; they change by updating env and redeploying. Lost authenticator =
    a new secret in env. It has no recovery code.
  - Never created, promoted to, demoted, deactivated or deleted through the UI. Never a demo account: a DB CHECK
    enforces `role <> 'super_admin' OR is_demo = false`. Its credentials are never published.
  - **Hidden everywhere** from every other role: never listed, counted, searchable or shown in any staff list,
    dashboard count, audit-log view, export or demo login hint. Where its actions must be attributed (a chat reply,
    a booking made on behalf, an audit entry seen by admin), it appears as "TravelEasy" (system), never by name or
    e-mail.
  - **Accepted trade-off (demo):** the stack's env holds the password hash and the 2FA secret, so access to the
    Portainer stack equals access to this account. That's acceptable for a portfolio demo, because Portainer access
    already means full control of the server. It's documented in `docs/deploy/casaos-portainer.md`.
  - There are no other seeded production accounts. *(legacy §2 test accounts; §3 Security)*
- **Staff onboarding with a one-time setup code (§ 11 R4).** A generic mechanism for non-demo installs. On the live
  demo there are **never real staff** (§ 11 Q-C): every staff account except super_admin is a demo account, and only
  super_admin could use this, so in practice it won't be used there.
  - An authorised user creates the staff account in the back office: name, e-mail (login name) and role. Who may
    create which role follows the role matrix (§ 7); in demo mode only super_admin can (ADR-0006 D8).
  - The account starts **without a password**. The creator is shown a one-time **setup code** once (same format
    and strength as a recovery code) and hands it over out of band: in person, by phone, or through a password
    manager.
  - The new colleague opens `/account/setup`, enters e-mail + setup code, sets a password, enrols 2FA if their
    role requires it, and receives their recovery code.
  - Setup codes are hashed, single-use, expire after 72 hours (configurable) and are rate-limited like recovery
    codes. Creating a new code revokes the old one. The whole lifecycle is audited: created, used, expired, revoked.
  - The code is **typed in, never put in a URL**, so it can't end up in proxy logs, browser history or referrers.
  - The same mechanism serves the super_admin's artisan password reset.
- Staff management: admin lists staff, creates `employee`/`manager` accounts, changes their role between those two,
  deactivates them, and issues a new setup code for an account that hasn't been set up yet. Only super_admin can do
  this for `admin` accounts.
- **Demo-mode core (ADR-0006, changes D1–D4):** the `DemoMode` service and `DEMO_MODE` config (off by default);
  `users.is_demo`; the `demo` seeder creates the four published demo accounts only when demo mode is on; demo
  accounts are exempt from mandatory 2FA and locked against credential and account changes; `is_demo` accounts
  can't log in when demo mode is off. It's built here because it touches the auth code and makes local dev
  convenient. The public-facing parts come in M6.
- Audit log foundation: `AuditLog` model + service; logs login, failed login, recovery-code use and regeneration,
  the setup-code lifecycle, 2FA changes and role changes, without personal data or codes in log lines.
  *(legacy §3 Security: no audit log)*
- Rate limiting on login, registration, recovery-code reset and setup-code entry (per IP and per account).
- Queue worker + scheduler services in compose (for PDFs, exports, retention and the demo reset; there's no mail).
- Scheduled local DB backup with rotation, plus a tested restore procedure (Q13: local only).
- `docs/privacy/data-inventory.md` v1 (account data; the e-mail address as login name).

**Agents:** ux-ui-designer (auth screens, the recovery-code and setup-code pages), database-engineer,
backend-developer, frontend-developer, devops-engineer (worker, scheduler, backup), qa, a11y, security.
**Depends on:** M0.
**Exit criteria:** DoD; an authorization test matrix exists and runs in CI (every role against every staff
route); the demo-mode tests pass with the flag on and off; provisioning tests cover create, update, missing env
(no super_admin, app still works) and an e-mail conflict; a crawl test as every other role proves the super_admin's
name and e-mail never appear and it's never counted; tests prove that an old recovery code and a used or
expired setup code are rejected, and that an unknown e-mail and a wrong code get the same response; the arch
test proves nothing can send mail; a restore from a backup has been tested once.

**Candidate epics / stories**
- Epic: Customer accounts
  - Customer registration with e-mail as login name and a one-time recovery code
  - Login and logout with throttling
  - Reset a forgotten password with e-mail + recovery code
  - Regenerate the recovery code from the profile
  - Profile: edit name, e-mail (login name) and language preference
  - Change password
  - Delete own account (basic)
- Epic: Roles and authorization
  - Role model and deny-by-default policy baseline
  - Role-based navigation and staff area shell
  - Authorization test matrix in CI
- Epic: Staff accounts
  - Provision the hidden super_admin from env (password hash + TOTP secret) on boot
  - Helper commands to hash a password and generate a TOTP secret
  - Hide the super_admin from every other role (lists, counts, search, exports, audit, login hints)
  - Create a staff account with a one-time setup code
  - Staff account setup page (password, 2FA, recovery code)
  - Super_admin manages admin accounts
  - Admin deactivates and reactivates staff
  - TOTP 2FA: required for manager/admin/super_admin, optional for others
  - Artisan commands: password reset (setup code) and 2FA reset
- Epic: Demo-mode core (ADR-0006)
  - `DemoMode` service, config flag and `is_demo` accounts
  - Demo accounts skip mandatory 2FA and can't change credentials
  - `is_demo` accounts are refused when demo mode is off
- Epic: Audit and operations
  - Audit log for security events
  - Queue worker and scheduler services
  - Scheduled local database backup and restore drill

**Risks**
- Starter-kit auth screens may not meet AA out of the box, and the starter kit's mail-based flows must be removed
  cleanly. Mitigation: the auditor reviews the screens; the arch test catches leftover mail code.
- Users lose their recovery code and lock themselves out. Mitigation: a clear "save this now" step with copy and
  download, regeneration from the profile, and the artisan last resort.
- Recovery and setup codes are guessable only by brute force. Mitigation: at least 100 bits of entropy, per-account
  and per-IP limits with lock-out, generic errors, and audit entries that make attacks visible.
- The env holds the super_admin's password hash and 2FA secret. Mitigation: hash only, never the plain password; it's
  an accepted, documented trade-off (Portainer access already means server control).
- Unverified login names: someone can register an address that isn't theirs. Impact is low because nothing is ever
  sent to it. Mitigation: the privacy statement explains it; super_admin can free up an address via artisan.
- Demo-mode branches spreading through the code. Mitigation: one `DemoMode` service, an arch test that forbids
  reading the flag anywhere else, and an on/off test for every change.

### M2: Airports and trip catalogue (M)

**Goal:** trips exist as clean, priced, capacity-aware data on top of airport reference data.
**After this you can:** as manager, create, edit, publish and cancel trips between real airports (with a price
and capacity, and times shown in each airport's time zone). As anyone, browse a public trip list with filters
and open a trip detail page.

**Scope**
- `Airport` reference data (IATA unique, name, city, country, time zone), loaded by the `reference` seeder. Staff can
  view it; manager/admin can add or edit it. *(legacy §2 Departures; §3 Domain: airports duplicated per trip)*
- `Trip`: flight number, from/to airport, departure/arrival in UTC, capacity, base price (cents, EUR), status with
  documented transitions (draft → published → departed/cancelled). DB CHECKs: arrival > departure, price ≥ 0,
  capacity ≥ 1. *(legacy §2 Trip overview `/reisoverzicht`; §3 Domain: trips had no price)*
- Staff trip management: a sortable, filterable, paginated table (the reusable staff table), create/edit forms,
  status changes.
- Public `/trips` list: filter by date range, departure and destination; pagination; empty state; trip detail
  page with local times labelled. *(legacy §2 Trips)*
- `demo` seeder with realistic, clearly fake trips. It's used in dev and, behind demo mode, on the live demo (Q16).
- ERD in `docs/design/erd.md`.

**Agents:** ux-ui-designer, database-engineer, backend-developer, frontend-developer, qa, a11y, security (staff
write access).
**Depends on:** M1 (roles for the staff side).
**Exit criteria:** DoD; trip search queries use indexes (`EXPLAIN` on demo-sized data); no airport can be duplicated.

**Candidate epics / stories**
- Epic: Airports
  - Airport model, migration and reference seeder (IATA, time zone)
  - Staff airport list; manager adds or edits an airport
- Epic: Trip management (staff)
  - Trip model with price, capacity and status transitions
  - Staff trip table with sorting, filtering and pagination
  - Manager creates and edits a trip
  - Manager publishes or cancels a trip
- Epic: Public trip catalogue
  - Public trip list with date and route filters
  - Trip detail page with local times and price incl. VAT
- Epic: Data
  - Demo seeder for trips (relative dates, so the demo always has future trips)
  - ERD document

**Risks**
- Time zones and DST bugs. Mitigation: store UTC, convert per airport, and unit-test DST boundaries.
- Demo trips go stale. Mitigation: the demo seeder generates dates relative to "today", so every nightly reset
  produces fresh future trips.

### M3: Search and public site (M)

**Goal:** the public front door: the dependent search (the best idea in the original) and real content that
search engines can index.
**After this you can:** on the home page, pick a departure country/airport, see only destinations reachable from
it, pick from dates that actually have trips, set adults and children, and get results. You can browse the
destinations and the about page, all with real text and optimised images, server-rendered.

**Scope**
- Dependent search: departure → reachable destinations → available dates (accessible date picker that marks
  unavailable dates) → travellers (adults 18+, children 2–17, number fields). Small typed JSON endpoints for
  the dependent options. The results page reuses the M2 list. The search has a "flight only" mode now, and
  "flight + hotel" is added in M9. *(legacy §2 Home/search; §4 dependent search)*
- Home page: hero with real copy and a licensed, responsive image (AVIF/WebP, `srcset`, width/height), and
  featured trips. *(legacy §2 Home: lorem ipsum hero; §3 Design: unoptimised images)*
- Destinations page **made data-driven**: destinations derived from airports with published trips, with a link
  into a pre-filled search. *(legacy §2 Destinations: static placeholder)*
- About page with real content, including a short "about this demo" section. *(legacy §2 About)*
- Image pipeline: build-time responsive variants, alt-text strategy in the design system.
- SEO (Q15): per-page titles and meta descriptions in the SSR head, `lang`, canonical URLs, `hreflang` for nl/en
  (same URL, so `x-default` + a language note; see § 7 i18n), sitemap, robots.txt, Open Graph tags.

**Agents:** ux-ui-designer (search flow, home), frontend-developer (lead), backend-developer, qa, a11y.
**Depends on:** M2.
**Exit criteria:** DoD; Lighthouse ≥ 90 (mobile, SSR build) on home, search results, destinations and about; the full
search journey works by keyboard only and with a screen reader (manual check by the auditor).

**Candidate epics / stories**
- Epic: Dependent search
  - Departure selection with destinations that are reachable from it
  - Date picker that shows only available dates
  - Traveller counter for adults and children
  - Search results page with sorting and empty state
- Epic: Public content
  - Home page with real hero content and featured trips
  - Data-driven destinations page
  - About page with an "about this demo" section
  - Responsive image pipeline and alt-text guideline
- Epic: SEO
  - Page titles, meta descriptions and Open Graph tags in the SSR head
  - Sitemap, robots.txt and canonical URLs

**Risks**
- An accessible date picker that marks unavailable dates is the hardest widget in the app. Mitigation:
  React Aria `DatePicker` with `isDateUnavailable`; the auditor checks it early.
- Image licences. Mitigation: own photos or CC0 only; licence noted per image.

### M4: Booking flow with offers (L)

**Goal:** the core business value: customers book on their own, at a price the server computes, through a
payment step that can later talk to a real provider.
**After this you can:** as a customer, pick a trip from search, enter travellers, apply an offer code, see an
itemised price (incl. VAT and discount), pay with the simulated provider, see a confirmation page, and find the
booking under "My bookings". As manager, you can create offers, and everyone can browse current offers.

**Scope**
- Booking wizard: trip → travellers (name, date of birth for the age category; **no passport data yet**, see M10)
  → offer code → review → payment → confirmation. Login/registration is required at the review step. State is
  kept server-side; the user's input is preserved on error. *(legacy §3 Domain: no customer booking flow)*
- `Booking` + `BookingTraveller` (Person-based travellers who don't need accounts). Status transitions
  (pending_payment → confirmed → cancelled/completed). The model is designed so that M9 can add package bookings
  (a booking has priced **items**, starting with a flight item) without a rewrite.
- **Pricing service:** base price × travellers (children pay the adult price; infants aren't bookable, Q6)
  − valid offer, **VAT 21 % from one config value** (Q9), integer cents, EUR. Unit-tested. The price is recomputed
  at confirm time. *(legacy §3 Domain: hand-typed price, offers never applied, decimal money, VAT by hand)*
- **Capacity:** checked in a transaction with a row lock on the trip; a concurrency test proves overbooking is impossible.
- **Payments (Q3):** **ADR-0003: payment-provider interface.** A `PaymentProvider` contract (create payment, get
  status, handle webhook/return, refund), a `Payment` record per attempt, idempotent status handling, and a
  **`SimulatedPaymentProvider`** (a clearly labelled test page where you choose "pay", "fail" or "cancel").
  The provider is selected in config (`PAYMENT_PROVIDER=simulated`). The Mollie adapter comes in M11.
- Offers: `Offer` (trip, code, discount 0–100 %, valid from/until, DB CHECKs). Manager CRUD. Public `/offers`
  page (nl slug `/aanbiedingen` as a localised route or redirect). *(legacy §2 Offers)*
- "My bookings" list + detail; **cancellation policy (Q10):** customers can cancel free of charge until 14 days
  before departure (full refund through the provider + credit note in M5); after that, only staff.
- Booking confirmation page (printable, nl/en). No e-mail (ADR-0005).
- Special requests field (free text, length-limited, flagged in the data inventory because it can contain health
  data). *(legacy §2 Bookings: special requests)*

**Agents:** ux-ui-designer (booking flow), database-engineer, backend-developer (lead), frontend-developer, qa,
a11y, security (pricing, payment state, IDOR, personal data).
**Depends on:** M3 (search as the entry point), M1 (accounts).
**Exit criteria:** DoD; e2e journey search → book → simulated payment → confirmation page → "My bookings" passes against
the built image; the pricing rules are covered by unit tests, including boundary dates for offers; there's an
overbooking concurrency test; a failed or cancelled simulated payment releases the seats.

**Candidate epics / stories**
- Epic: Booking model and rules
  - Booking, booking item and traveller schema with status transitions
  - Pricing service with VAT from config and integer cents
  - Capacity check with row lock against overbooking
- Epic: Booking wizard (customer)
  - Choose trip and enter travellers
  - Apply an offer code
  - Review itemised price
  - Sign in or register during checkout without losing input
  - Booking confirmation page
- Epic: Payments
  - ADR-0003: payment-provider interface
  - Payment contract, payment records and idempotent status handling
  - Simulated payment provider with pay/fail/cancel outcomes
  - Release held seats when a payment fails or expires
- Epic: My bookings
  - Customer views own bookings and booking detail
  - Customer cancels a booking within the 14-day policy
- Epic: Offers
  - Offer schema with validity window and discount checks
  - Manager creates, edits and ends an offer
  - Public offers page

**Risks**
- This is the biggest milestone and the easiest to over-scope. Mitigation: one trip per booking, no seat map
  (Q6); anything more goes to the backlog.
- A wizard can break focus and back-button behaviour. Mitigation: one URL per step, focus moved to the step
  heading, and the error summary pattern.
- Seats held by unpaid bookings. Mitigation: a hold expires after a configurable time (scheduled job).

### M5: Invoices (M)

**Goal:** close the vertical slice: every confirmed booking produces a correct, immutable invoice.
**After this you can:** after booking, view your invoice in the app and download it as a PDF. As staff, you can
see all invoices, mark them paid, and issue a credit note for a cancellation.

**Scope**
- `Invoice` + `InvoiceLine`: sequential number without gaps per year, issue date, lines copied from the booking
  items' price breakdown, excl./VAT/incl. totals computed from lines, status (open/paid/credited). Paid status
  follows the payment record; staff can also mark an invoice paid by hand. Immutable once issued; corrections are
  credit notes. *(legacy §2 Invoices; §3 Domain: VAT by hand)*
- **ADR-0004: PDF library** (dompdf vs spatie/laravel-pdf, weighing image size on the small server now that SSR
  already adds Node). PDFs are generated on demand or stored on the private `invoices` disk (decided in the ADR).
- Customer: own invoice list and download (policy-checked). Staff: invoice table with filters, mark paid,
  credit note (also triggered by a cancellation). Invoices are delivered in the app only (view + PDF download);
  there is no invoice e-mail (ADR-0005).
- Retention: invoices are kept 7 years on non-demo installs (Dutch tax law); this is documented in
  `docs/privacy/retention.md`. On the live demo, the nightly reset removes them too (they're fictional).

**Agents:** database-engineer, backend-developer (lead), frontend-developer, ux-ui-designer (invoice layout incl.
PDF), qa, a11y (the PDF needs an accessible alternative: the HTML invoice view), security.
**Depends on:** M4.
**Exit criteria:** DoD; e2e search → book → pay → invoice PDF downloaded; there's a test that an issued invoice
can't be edited and that numbering has no gaps under concurrency. **Release `v0.5.0` = vertical slice complete**,
deployed to the home server (LAN).

**Candidate epics / stories**
- Epic: Invoicing
  - ADR-0004: PDF library
  - Invoice and invoice-line schema with gapless numbering
  - Issue an invoice automatically on booking confirmation
  - Invoice PDF and accessible HTML invoice view
- Epic: Invoice management
  - Customer lists and downloads own invoices
  - Staff invoice table with filters
  - Staff marks an invoice as paid
  - Credit note for a cancelled booking

**Risks**
- A Chromium-based PDF engine makes the image much bigger. Mitigation: ADR-0004 weighs image size and RAM.

### M6: Public demo launch (M)

**Goal:** put the working slice on the internet as a safe, self-cleaning portfolio demo (Q2, Q8, Q11, Q14, Q16).
**After this you can:** open TravelEasy from anywhere over HTTPS on the owner's subdomain. Visitors can register
(e-mail as login name, with a recovery code) or log in with the published demo accounts, search, book, pay
(simulated) and download an invoice. A banner explains that it's a demo, that data resets every night, and that
they shouldn't enter real personal data. Every night everything visitors entered is wiped and the demo data is
restored.

**Why here:** the booking slice (M3–M5) is the first thing worth showing. The security baseline exists from M0–M1
(headers/CSP, deny-by-default policies, authorization matrix, 2FA for privileged real accounts, rate limits,
recovery-code and setup-code protections, audit log). This milestone adds what public exposure itself needs.
Everything after M6 ships to the public site.

**Scope**
- **Demo mode, public parts (ADR-0006, changes D5–D10):** site-wide demo banner (nl/en); warnings on the
  registration form and the booking travellers step (and the passport fields once they exist, M10);
  **server-side masking of visitor personal data** for every staff account except super_admin (D6, see below);
  staff account creation for super_admin only (D8); demo accounts listed on the login page; the **nightly reset**
  job at **03:45 Europe/Amsterdam** (the server's time zone) (`demo:reset`: maintenance mode → wipe everything,
  logging everyone out → `reference` + `demo` seeders → **re-provision the super_admin from env** → maintenance
  mode off → trigger the DB dump of the clean state). It's registered in the scheduler only when demo mode is on,
  and the command refuses to run otherwise. On the live demo, every staff account except super_admin is a demo
  account; there are no real staff (§ 11 Q-C).
- **Reset timing (§ 11 Q-E).** The owner's 04:00 and 04:15 jobs reset two other demo sites on the same server, so
  reset + dump must finish **well before 04:00**, with a target of **under about 10 minutes** in total, to avoid
  CPU/IO contention. The reset and the dump record start, end and duration. If they take longer than the configured
  threshold (default 10 min), or are still running at 03:58, they log an error and write an audit entry, and the
  super_admin dashboard shows a warning. There is no e-mail, so the alert is in the app and the logs. The demo
  dataset is sized to keep the reset short. `docs/deploy/casaos-portainer.md` documents the schedule and the shared
  machine's other jobs.
- **Visitor-data masking (N2, ADR-0006 D6).** Registration stays open. In demo mode, every staff account except
  `super_admin` gets visitor personal data **masked on the server**: the masked value replaces the real one before
  it leaves PHP (in the laravel-data objects, PDFs and exports), so the real value never reaches the browser.
  This applies to every self-registered (non-demo) customer, whatever their address; there is no "test vs real"
  detection. Masked fields: name, e-mail (login name), phone, address, date of birth, traveller names, special
  requests, and later chat content (M8) and passport data (M10). Searching and sorting on masked fields doesn't
  match visitor records, so search can't be used to confirm whether an address is registered.
- **No outgoing e-mail (ADR-0005).** There is nothing to set up for mail at go-live: no provider, no DNS records, no
  secrets. The privacy statement says the e-mail address is only a login name, never verified and never used to
  contact anyone.
- **Abuse protection for open registration:**
  - Rate limits per IP and per /24 (or /64 for IPv6) on registration, login, recovery-code reset and setup-code
    entry; later also guest chat (M8). These run in every mode, not only in demo mode.
  - A **global registration cap** per hour (circuit breaker). When it's reached, registration pauses with a clear
    message, and the event is audited so the owner sees it. This protects the small server from bot sign-ups.
  - **ADR-0007: bot protection:** self-hosted and privacy-friendly only. Candidates: honeypot + minimum
    form-fill time (always on), plus a self-hosted proof-of-work challenge (e.g. the open-source ALTCHA widget
    and a server-side verifier). No third-party captcha, no cookies or fingerprinting, and accessible (no visual
    puzzle). Used on registration now and on guest chat in M8.
- **Privacy baseline before exposure (Q14):** privacy statement v1 (nl/en) at `/privacy`, which says plainly that
  this is a demo, that all visitor-entered data is deleted within 24 hours by the nightly reset, that the e-mail
  address is only a login name, and that visitors must not enter real personal data, **especially passport data**.
  **Data export** (JSON) as an **in-app download**: requested on the profile page after re-entering the password,
  built by a queued job, offered there through a signed link that expires, and deleted afterwards. **Full erasure**
  across every module that exists so far (account, bookings, payments, invoices). Only functional cookies, so no
  cookie banner (the reviewer confirms this).
- **Go-live infrastructure:** an NPM public proxy host on the owner's subdomain with TLS; trusted-proxy config; HSTS
  on; https URLs. Devops checks whether the home IP is static or dynamic and sets up DDNS if needed (Q12). The SSR
  and web services are the only ones on the NPM network.
- Local scheduled DB dump with rotation (Q13). In demo mode the dump runs right after the nightly reset (chained,
  not on a separate clock), so it contains seeded data only (plus the env-provisioned super_admin) and never keeps
  visitor data beyond 24 hours. It's part of the 10-minute budget above.
- **Exposure review:** a security-privacy-reviewer pass over the internet-facing surface (ASVS L2 chapters for
  authentication, session, access control, validation, configuration), and a rollback rehearsal (deploy
  `v0.6.0`, roll back to `v0.5.x`, roll forward).

**Agents:** devops-engineer (lead: NPM, DNS/DDNS, reset and dump schedule, backups), backend-developer (demo mode,
masking, reset, rate limits, registration cap, bot protection, export/erasure), frontend-developer (banner,
warnings, challenge widget), database-engineer (reset, export/erasure queries), ux-ui-designer (banner and warning
copy, masked-value display), security-privacy-reviewer (lead on the privacy statement, masking and the exposure
review), qa, a11y.
**Depends on:** M5.
**Exit criteria:** DoD; the site is reachable over HTTPS on the subdomain; a reset test proves that visitor data is
gone, demo data is back and the super_admin is re-provisioned from env (and absent when the env vars are missing),
and that the command refuses when `DEMO_MODE` is off; on the server, three consecutive nightly runs of reset + dump
each finish in under 10 minutes (well before 04:00), and a test proves that the overrun alert fires; masking
tests prove that, for every demo staff account (incl. demo admin), no visitor's real name or e-mail appears in any
Inertia response, PDF or export, and that super_admin sees them unmasked; an erasure test seeds a customer with data
in every module and asserts that nothing identifying remains; the export download works only for its owner and
expires; the exposure review has no open High/Critical findings. **`v0.6.0` is the first public release.**

**Candidate epics / stories**
- Epic: Demo mode (public parts, ADR-0006)
  - Demo banner and "don't enter real data" warnings
  - Server-side masking of visitor personal data for all staff except super_admin
  - Staff account creation for super_admin only in demo mode
  - Show the demo accounts on the login page
  - Nightly reset at 03:45, guarded by demo mode, re-provisioning the super_admin
  - Reset and dump timing check with an in-app and log alert
- Epic: Abuse protection
  - Rate limits on registration, login, recovery and setup codes
  - Global registration cap with audit entry
  - ADR-0007: self-hosted bot protection
  - Bot protection on registration
- Epic: Privacy baseline
  - Privacy statement v1 in nl and en, with the demo notice and the login-name explanation
  - Customer exports all personal data as an in-app JSON download
  - Full account erasure across all modules
- Epic: Go-live
  - Public proxy host with TLS, trusted proxies and HSTS
  - Static IP check and DDNS fallback
  - Backup after nightly reset and restore drill
  - Exposure security review and rollback rehearsal

**Risks**
- Bot sign-ups filling the database or slowing the server. Mitigation: rate limits, the global registration cap,
  bot protection, and the nightly reset.
- Brute force against recovery codes on a public site. Mitigation: the M1 limits and lock-out, plus monitoring of
  the audit log.
- The reset or dump overrunning into the other demo sites' 04:00/04:15 jobs on the same machine. Mitigation: a small
  demo dataset, measured durations, the overrun alert, and the 10-minute target.
- The nightly reset running against the wrong database. Mitigation: two guards (scheduler registration + command
  check), a confirmation of `APP_ENV`/`DEMO_MODE` in the log, and on/off tests.
- Visitors entering real personal data despite warnings. Mitigation: warnings at every collection point,
  server-side masking for every staff account except super_admin, and deletion within 24 hours.
- A masking gap: one staff view or download that serialises an unmasked field. Mitigation: masking lives in the
  data objects (not in React), and a generic test logs in as each demo staff account, crawls every staff route and
  checks that no seeded visitor's name or e-mail appears in the response.
- The home connection's upload speed or IP changes. Mitigation: DDNS, the uptime check from M11.

### M7: Staff back office (L)

**Goal:** staff can run the agency: one coherent back office instead of the original's overlapping CRUDs.
**After this you can:** as employee, open a dashboard with booking statistics, find any customer, see their
bookings and invoices, create a booking on a customer's behalf, and change a booking's status. As admin, you can
manage staff and read the audit log.

**Scope**
- Dashboard: per-role widgets (today's departures, recent bookings, occupancy, revenue for managers).
  *(legacy §2 Dashboard)*
- **One customer module** (the legacy Accounts + Customers merged): search, detail (profile, bookings, invoices,
  conversations), edit contact data, deactivate, and an internal staff note. *(legacy §2 Accounts, Customers; §3 Domain: two CRUDs)*
- Booking management: table with search by date, destination and customer (tested, so it can't filter on a
  column that doesn't exist), detail, status changes, create on behalf of a customer through the same
  pricing/capacity/payment services as M4. No seat selection (Q6). *(legacy §2 Bookings; §3 Domain: broken search)*
- Employee profile (`Employee` record: job title, department); staff management extended from M1.
- Audit log viewer (admin), with staff reads of customer detail logged. *(legacy §3 Security: no audit of who viewed data)*
- Demo mode: every new staff list, detail, dashboard widget, audit log entry and download masks visitor personal
  data server-side for every staff account except super_admin (ADR-0006 D6).
- The legacy `is_active` + `note` on every table is replaced by explicit statuses / deactivation where the business
  needs it, and a staff note only on customers and bookings. *(legacy §2 Data model)*

**Agents:** ux-ui-designer (dense staff screens), database-engineer, backend-developer, frontend-developer, qa,
a11y, security.
**Depends on:** M6.
**Exit criteria:** DoD; the authorization matrix covers every back-office route for all five roles; the staff
journeys work keyboard-only (the auditor's staff walkthrough); the masking crawl test covers every new staff route.

**Candidate epics / stories**
- Epic: Dashboard
  - Staff dashboard with role-based booking statistics
- Epic: Customer management
  - Staff searches and lists customers
  - Customer detail with bookings, invoices and support conversations
  - Staff edits customer contact data and internal note
  - Manager deactivates a customer account
- Epic: Booking management
  - Staff booking table with search by date, destination and customer
  - Staff changes a booking's status
  - Staff creates a booking on behalf of a customer
- Epic: Staff and audit
  - Employee profile data and staff list
  - Audit log viewer with filters
  - Log staff views of customer records

**Risks**
- Scope creep into reporting. Mitigation: dashboard widgets are fixed in the story; anything else goes to the backlog.

### M8: Support chat (M)

**Goal:** anyone, including guests, can ask the agency a question from any page, and staff answer from one
support inbox, privately and accessibly (§ 11 R5). It replaces the legacy `/messages` inbox and the contact form.
It stays after the back office (Q17), because the support inbox lives there.
**After this you can:** on any public or customer page, open the chat button in the bottom-right corner, ask a
question as a guest or as a logged-in customer (optionally about one of your bookings), and see replies appear
with an unread badge, all without e-mail. As a guest you can keep the conversation when you register or log in.
As staff, you can work through a support inbox with unread counts and answer.

**Scope**
- **Widget:** a floating launcher button bottom-right on every public and customer page, opening a chat panel.
  Code-split and loaded after hydration, so it doesn't cost SSR or Lighthouse performance. In the back office,
  staff get an unread count in the navigation and the support inbox instead of the widget (§ 12 Q-F).
- **Conversations:** `Conversation` + `Message`; owner is a customer **or** a guest token; start, reply, mark
  read (per participant), unread counts, close. Messages are plain text up to 2,000 characters (not 25),
  rendered as text (no HTML, no auto-linked URLs). A customer can link a conversation to one of their own bookings.
  *(legacy §2 Messages; §3 Domain: 25-char limit, unscoped list)*
- **Guests:** chat without an account through an anonymous conversation bound to the browser by a random token
  in a functional, HttpOnly, Secure, SameSite=Lax cookie (stored hashed). It lives as long as the conversation
  is open, up to a configurable maximum (e.g. 7 days). A guest can give an optional display name and is never
  asked for an e-mail or phone number. The panel says replies can only be read in this browser.
- **Claiming:** when a guest registers or logs in in the same browser, the widget offers "Save this conversation
  to your account". Only after an explicit confirmation does the conversation move to the account and the guest
  token stop working (audited). Only the browser holding the token can claim.
- **Staff support inbox** (back office): open/closed/guest/customer filters, unread counts, reply, close, mark as
  spam / block a guest token. Staff deletion is a soft delete with an audit entry.
- **Delete semantics (legacy):** a customer can delete (hide) their own conversation, and an author can retract
  their last message while it's unread. A guest can end and delete their conversation.
- **Notifications are in-app only:** an unread badge on the launcher (count in its accessible name) and in the
  staff navigation. Polling: about every 10 s while the panel is open, about 60 s for the badge, paused while the
  tab is hidden, backing off on errors, against a cheap `since`-cursor endpoint. Reverb can come later (ADR-0001).
- **Abuse protection:** the ADR-0007 bot protection + honeypot on a guest's first message; rate limits per IP and
  per guest token (new conversations per hour, messages per minute and per day); request-size limits; a global
  cap on open guest conversations (circuit breaker: guests see "chat is temporarily unavailable; please log in or
  try later"); staff spam/block actions.
- **Privacy:**
  - Demo mode: the guest's display name and all guest- and visitor-entered chat content are masked server-side for
    every staff account except super_admin (D6). The nightly reset wipes all conversations, guest ones included (D10).
  - Non-demo installs: guest conversations are deleted a configurable time after their last message (proposed
    default 30 days), and customers' closed conversations after a configurable period. The defaults are justified by
    the security-privacy-reviewer in `docs/privacy/retention.md`. The jobs are built here; M10 completes the
    retention set.
  - Export and erasure include conversations; the privacy statement explains guest chat and the cookie.
- **Contact page:** `/contact` keeps the agency's details (fictional) and a "Start a chat" button that opens the
  widget. *(legacy §2 Contact: form sent nothing)*
- **Accessibility (WCAG 2.2 AA), a key risk; the design is audited before it's built:**
  - The launcher is a real `<button>` (≥ 44×44 px) with an accessible name that includes the unread count; it has
    no hover-only content.
  - The panel is a non-modal dialog with a heading. Opening it moves focus into the panel, Escape closes it and
    focus returns to the launcher, and there is no keyboard trap.
  - The message list is a log region; new messages are announced politely and in batches, not per poll.
  - **Not covering content:** the page reserves space (padding / `scroll-padding`) so the launcher never hides
    focused elements (2.4.11 Focus Not Obscured). On narrow viewports and at 400 % zoom (≤ 640 CSS px) the panel
    opens as a full-screen view with a clear close button, never as an overlay on top of the page (1.4.10 Reflow).
  - `prefers-reduced-motion`: no slide or bounce animation and no animated badge.
  - Unread status isn't conveyed by colour alone; contrast is checked in both themes; text spacing (1.4.12);
    status messages (4.1.3); `lang` on the panel.

**Agents:** ux-ui-designer (widget, panel, inbox, guest and claim copy), database-engineer (schema, retention
jobs), backend-developer (lead: conversations, guest tokens, claiming, polling endpoints, abuse limits, masking),
frontend-developer, qa, accessibility-auditor (design review before build, then the widget audit),
security-privacy-reviewer (guest tokens, IDOR, XSS, abuse, retention).
**Depends on:** M7 (the support inbox lives in the back office); M6 (bot protection, masking).
**Exit criteria:** DoD; a test proves customer A can't read B's conversation, and guest token A can't read guest
conversation B; a claim works only from the browser holding the token; rate limits and the guest cap are tested;
masking tests cover guest content for every staff account except super_admin; the reset test covers guest
conversations; export and erasure include conversations; the auditor's keyboard, screen-reader, 400 % zoom and
reduced-motion walkthrough of the widget passes.

**Candidate epics / stories**
- Epic: Support-chat widget
  - Accessible floating launcher with unread badge
  - Chat panel as a non-modal dialog, full-screen on narrow viewports
  - Customer starts a conversation, optionally about a booking
  - Guest starts an anonymous conversation bound to the browser
  - Guest claims the conversation after registering or logging in
  - Polling updates with a polite live region
  - Delete own conversation and retract last unread message
- Epic: Support inbox (staff)
  - Staff support inbox with filters and unread counts
  - Staff reply, close and mark as spam / block a guest
- Epic: Abuse and privacy
  - Bot protection and rate limits on guest chat
  - Global cap on open guest conversations
  - Masking of guest and visitor chat content in demo mode
  - Retention jobs for guest and closed conversations
- Epic: Contact
  - Contact page with agency details and a "Start a chat" button

**Risks**
- **Accessibility of the floating widget** (focus, obscured content, zoom, announcements) is the hardest a11y
  problem in this milestone. Mitigation: design-first with an auditor review, a full-screen mode on small
  viewports, and manual screen-reader and zoom tests in the exit criteria.
- Guest chat spam on a public site. Mitigation: bot protection, rate limits, the global cap, staff block, and the
  nightly reset on the demo.
- Polling load on the small server. Mitigation: a cheap cursor endpoint, longer intervals when the panel is closed,
  and no polling in hidden tabs.
- Guests lose their conversation when they clear cookies or switch devices. Mitigation: the panel says so, and
  offers registering to keep it.

### M9: Packages: flight + hotel (L)

**Goal (Q4):** real packages: bundles of flights and a hotel stay, with optional extras, that can be searched,
booked, invoiced and managed. This is new scope beyond the original.
**After this you can:** search for "flight + hotel", open a package with its hotel, flights and extras, pick a room
type and extras, book it for your group and get one invoice with itemised lines. As manager, you can manage hotels,
room allotments, extras and packages.

**Scope** *(legacy §2 Packages: static placeholder, now built for real)*
- **ADR-0008: package domain model** (written at the start of the milestone; the package definition was accepted in
  § 10 N4). Model:
  - `Hotel` (name, destination airport/city, address, star rating, description nl/en, images) with `RoomType`
    (occupancy, price per night in cents) and a per-night **allotment** (rooms available).
  - `Package`: outbound trip + return trip + hotel + nights, a package discount (optional), a status, and
    validity dates. `Extra` (e.g. airport transfer, extra luggage, excursion): priced per person or per booking.
  - A package booking is a `Booking` with several **items** (flight out, flight back, hotel stay, extras),
    reusing the M4 item model.
- **Pricing:** the package price is computed from its components (flights × travellers + room nights + extras −
  package discount − offer), with the single VAT rate (Q9). Unit-tested.
- **Capacity:** seats on both flights and the room allotment for every night are locked in one transaction.
- **Public:** `/packages` list and detail pages (SSR, SEO), "flight + hotel" mode in the dependent search, hotel
  detail with an accessible image gallery.
- **Booking flow:** the M4 wizard gets a room-and-extras step for packages; the review shows every component.
- **Invoices:** one invoice with a line per component.
- **Back office:** hotels, room types and allotments, extras and packages (manager); package bookings in the
  booking table.
- **Images:** hotel photos ship with the app as bundled static assets; there are no uploads (§ 10 N5).
- Offers can target a package as well as a trip.

**Agents:** architect (ADR-0008), ux-ui-designer, database-engineer, backend-developer (lead), frontend-developer,
qa, a11y, security.
**Depends on:** M7 (back office tables and booking management), M5 (invoicing).
**Exit criteria:** DoD; e2e search "flight + hotel" → book → pay → invoice with component lines; a concurrency test
for room allotments; the demo seeder includes hotels and packages on every reset.

**Candidate epics / stories**
- Epic: Package domain
  - ADR-0008: package domain model
  - Hotel, room type and allotment schema
  - Extras schema
  - Package schema with validity and discount
  - Package pricing service
  - Combined capacity check for flights and rooms
- Epic: Public packages
  - Public package list and detail pages
  - "Flight + hotel" mode in the dependent search
  - Hotel detail with an accessible image gallery
- Epic: Booking a package
  - Room and extras step in the booking wizard
  - Package invoice with a line per component
- Epic: Package management (staff)
  - Manager manages hotels, room types and allotments
  - Manager manages extras
  - Manager creates and publishes packages
- Epic: Demo data
  - Demo seeder for hotels and packages with licensed images

**Risks**
- This is the largest new-domain change. Mitigation: the M4 booking-items model is designed for it; the ADR comes
  first; package booking reuses the wizard, pricing and invoice code.
- Real-world package travel has extra legal and VAT rules (EU Package Travel Directive, the margin scheme). This
  is a fictional agency, so the plan keeps the single VAT rate; the about page and privacy statement say it's a demo.

### M10: Sensitive data and retention (M)

**Goal:** the most sensitive data (passport/ID) is handled exactly as the principles promise, and retention is
automatic on every install.
**After this you can:** optionally add passport details for each traveller (after a clear warning on the demo),
which only authorised staff can see (each view logged), and which are erased automatically after the trip. On a
non-demo install, old sessions, exports, payment holds and audit entries are purged on schedule, alongside the
chat retention jobs from M8.

**Scope**
- Passport/ID data on `BookingTraveller`: optional, purpose explained in the UI, `encrypted` cast, hidden from
  serialisation and logs, policy-limited to the roles in § 7, every read audited. In demo mode the field shows a
  prominent "don't enter real passport data" warning (D7), and visitors' passport data is masked for every staff
  account except super_admin (D6). *(legacy §3 Security: passport in plain text)*
- **Retention (Q14):** the GDPR storage-limitation principle ("no longer than necessary"). Retention periods are
  config values in `config/traveleasy.php`, with defaults that the security-privacy-reviewer justifies in
  `docs/privacy/retention.md`. Scheduled jobs on every install: purge passport data after the trip, purge stale
  sessions, exports, payment holds and expired setup codes, prune the audit log (the chat jobs come from M8). On the live demo, the nightly
  reset (M6) already deletes everything within 24 hours, and these jobs still run so they are exercised.
- `docs/privacy/` complete: data inventory (incl. Mollie in M11 and, later, the chatbot), retention, privacy
  statement v2 (passport data, retention table).
- Export and erasure extended to passport data (the erasure test proves the passport field is unreadable in a raw
  DB dump and gone after erasure).

**Agents:** security-privacy-reviewer (lead on docs), database-engineer (retention jobs), backend-developer,
frontend-developer, ux-ui-designer (consent and warning copy), qa, a11y.
**Depends on:** M9 (all modules that store personal data exist, incl. package travellers).
**Exit criteria:** DoD; retention jobs have time-travel tests; a raw-dump test proves passport data is encrypted.

**Candidate epics / stories**
- Epic: Sensitive data
  - Optional encrypted passport details per traveller
  - Staff access to passport data with audit logging
  - Demo warning on passport fields
- Epic: Retention
  - Retention policy document with justified defaults
  - Scheduled purge of passport data after the trip
  - Scheduled purge of stale sessions, exports, payment holds and expired setup codes
  - Audit log retention
- Epic: Transparency
  - Privacy statement v2 and data inventory complete
  - Purpose notes at every data collection point

**Risks**
- Erasure misses data added by later features. Mitigation: the erasure test is extended by every story that adds
  personal data (a rule in § 6).

### M11: v1.0: Mollie adapter and final audits (M)

**Goal:** a real payment-provider integration in test mode, and a final, full quality pass for `v1.0.0`.
**After this you can:** switch the live demo to Mollie test mode in config and pay with Mollie's test methods
(no real money). The whole site has passed a full WCAG 2.2 AA audit and an ASVS L2 review.

**Scope**
- **Mollie test-mode adapter (Q3):** an optional `MolliePaymentProvider` behind the ADR-0003 interface; API key from
  env; **webhook** endpoint (public, verified by fetching the payment from Mollie, idempotent, rate-limited, CSRF
  exempt only for this route with a documented reason and an arch-test allow-list entry); refunds for
  cancellations. It needs the owner's Mollie account and the public site from M6. The simulated provider stays the
  default; switching is one env var. Mollie is added to the data inventory as a processor.
- Uptime check (self-hosted, optional) and structured log review; resource limits tuned on the ProDesk; a load
  smoke test of search and booking with SSR.
- Full audits: ASVS L2 checklist review, a full WCAG 2.2 AA audit of public and staff areas, and Lighthouse ≥ 90 on
  all public pages.
- Local backup + restore drill repeated (Q13: no off-site backups).
- Release `v1.0.0` and a rollback rehearsal.

**Agents:** backend-developer (Mollie), devops-engineer (lead on ops), security-privacy-reviewer,
accessibility-auditor, qa-engineer, frontend-developer (audit fixes).
**Depends on:** M10; the Mollie adapter needs M6 (public webhook URL) and M4 (interface).
**Exit criteria:** DoD; the Mollie test payment, failure, cancellation and refund paths pass in an e2e run
against Mollie test mode (plus contract tests with recorded fixtures in CI); the ASVS L2 review has no open
High/Critical findings; `v1.0.0` is live.

**Candidate epics / stories**
- Epic: Mollie
  - Mollie test-mode payment adapter
  - Mollie webhook with verification and idempotency
  - Refunds through Mollie for cancellations
- Epic: Operations
  - Uptime check and log review
  - Resource limits and load smoke test
  - Backup restore drill and v1.0.0 rollback rehearsal
- Epic: Final audits
  - ASVS L2 review and fixes
  - Full WCAG 2.2 AA audit and fixes
  - Lighthouse pass on all public pages

**Risks**
- Webhooks can't reach the home server (NPM or DNS issues). Mitigation: a status poll fallback on the return URL.
- Audit findings pile up at the end. Mitigation: per-milestone audits (§ 2), so M11 finds only leftovers.

### M12: AI travel assistant (nice-to-have, M)

**Goal:** bring back the chatbot, safely, if the owner still wants it after v1.0.
**After this you can:** open an opt-in travel assistant that answers in nl or en, is clearly labelled as AI,
never receives your personal data, and can be switched off by config.

**Scope** *(legacy §2 Chatbot; §3 Security: CSRF disabled, TLS verification off, prompts sent without consent)*
- **ADR: chatbot provider** (self-hosted model vs API; data processing, cost, RAM on the ProDesk next to SSR).
- Opt-in with a notice before first use; personal data stripped from prompts; CSRF on; TLS verification on;
  timeouts, retries; rate limiting per user and per IP (public demo); feature flag defaulting to off.
- Answers grounded in TravelEasy's own trip/package/offer data where possible.
- Lives **inside the M8 support-chat widget** (e.g. an "assistant" option before a human is involved), so there is
  only one floating button and one accessible chat pattern.
- Provider listed in `docs/privacy/data-inventory.md` and the privacy statement.

**Agents:** architect (ADR), backend-developer, frontend-developer, ux-ui-designer, security-privacy-reviewer, qa, a11y.
**Depends on:** M11.
**Candidate epics / stories**
- Epic: Travel assistant
  - ADR: chatbot provider
  - Opt-in notice and feature flag
  - Chat endpoint with prompt sanitising and rate limits
  - Accessible chat interface with live region
  - Privacy documentation for the AI provider

**Risks:** a self-hosted model may not fit in the server's RAM; an API provider is a data processor and costs money
per public visitor. The ADR decides.

## 5. Traceability

### 5.1 Legacy features (`legacy-analysis.md` §2) → milestone

| Legacy feature (route) | What it becomes | Milestone |
|---|---|---|
| Home / search (`/`) | Dependent search with accessible date picker and traveller counter; real hero content; SSR | M3 (flight + hotel mode M9) |
| Trips (`/trips`) | Public trip list with date/route filters + trip detail | M2 |
| Offers (`/aanbiedingen`) | Offers with validity and discount, **applied to the price** at booking; public offers page | M4 (packages M9) |
| Destinations (`/destinations`) | Data-driven destinations page linking into search | M3 |
| Packages (`/packages`) | **Real flight + hotel packages with extras** (Q4): hotels, room allotments, package search, booking, invoices and management | M9 |
| About (`/about`) | About page with real content and an "about this demo" section | M3 |
| Contact (`/contact`) | **Replaced by the support-chat widget** (guests too, bot-protected); `/contact` keeps the agency details and a "Start a chat" button. No e-mail (ADR-0005) | M8 |
| Chatbot (`/chat`) | Opt-in, privacy-safe AI assistant | M12 |
| Auth: register, login | Fortify flows + throttling + 2FA (required for manager/admin/super_admin); the e-mail address is a login name only | M1 |
| Auth: forgot/reset password | **Recovery code** (one-time, hashed, rate-limited, audited) instead of a reset e-mail; super_admin artisan reset as last resort | M1 |
| Auth: verify e-mail | **Dropped on purpose**: the app sends no e-mail (ADR-0005), so the address is an unverified login name | (M1) |
| Auth: profile edit/delete | Profile in M1; full export and erasure in M6, extended in later milestones | M1, M6 |
| Dashboard (`/dashboard`) | Role-based staff dashboard with statistics | M7 |
| Accounts (`/account`) | Merged into one customer module | M7 |
| Customers (`/customers`) | Same single customer module | M7 |
| Bookings (`/bookings`) | Customer self-booking with payment (M4); staff booking management with working search (M7); package bookings (M9) | M4, M7, M9 |
| Booking fields: seat, purchase date/time, status, price, quantity, special requests | Purchase timestamp, status, server-computed price, travellers as rows, special requests; **no seat selection** (Q6) | M4 |
| Invoices (`/invoice`) | Immutable invoices with lines, gapless numbers, PDF, credit notes | M5 |
| Trip overview (`/reisoverzicht`) | Staff trip management with price, capacity and status | M2 |
| Departures (`/departures`) | Airport reference data (staff view/manage) | M2 |
| Messages: start, reply, mark read | **Support-chat widget** (customers and guests) + staff support inbox; read status; in-app unread badges with polling | M8 |
| Messages: delete conversation, delete last message | Hide own conversation; retract last unread message; guests can end and delete theirs | M8 |
| Data model: people/customers/contacts/employees | `User` + `Person` + `Customer` + `Employee`; e-mail on `users` as login name | M1, M7 |
| Data model: `is_active` + `note` on every table | Explicit statuses/deactivation where needed; staff note on customers and bookings | M2, M7 |
| Employee types Manager / Administrator / Desk Employee | Roles `manager` / `admin` / `employee`, plus a new `super_admin` for the owner | M1 |
| Seeder test accounts + 300 fake users | `demo` seeder with fake data; **published demo accounts only in demo mode** (ADR-0006), locked and reset nightly; the owner's private, hidden `super_admin` provisioned from env (password hash + TOTP secret) | M1, M6 |
| §4 kept: Compose + Portainer deploy | Portainer stack from `compose.yaml` with a pinned GHCR tag | M0, M6 |
| §4 kept: entrypoint waits for DB, migrates, seeds empty DB | Same, in the production entrypoint | M0 |
| §4 kept: dependent search idea | Core of the search | M3 |
| §4 kept: NL/EN awareness | Full nl/en i18n from M0; chatbot answers in the chosen language | M0, M12 |
| (Not legacy) Transactional e-mail planned earlier: booking/invoice mails, staff invitations, export links, notifications | No e-mail (ADR-0005): confirmation page, in-app invoice + PDF, staff setup codes, in-app export download, unread badges | M1, M4, M5, M6, M8 |

### 5.2 Legacy mistakes (`legacy-analysis.md` §3) → where prevented

| Mistake | Prevented by | Where |
|---|---|---|
| No authorization; customers could see everything | Deny-by-default policies; authorization matrix test in CI; an IDOR test per resource in every story; visitor personal data masked server-side for all staff except super_admin in demo mode | M1, M6, then every milestone |
| Admin role checked three different ways | One role enum, policies only; Pest arch test forbids ad-hoc role string checks | M0 (arch tests), M1 |
| Passport details in plain text | Not collected before M10; then optional, `encrypted` cast, role-limited, audited, auto-purged; demo warning | M10 |
| Login e-mail in `contacts` with a custom UserProvider | E-mail on `users` as the login name; standard Fortify provider | M1 |
| CSRF disabled for the chatbot; TLS verification off | Arch tests forbid CSRF exceptions (one documented allow-list entry: the Mollie webhook) and `withoutVerifying()` | M0, M11, M12 |
| Prompts sent to a third-party AI without notice | Opt-in, notice, no personal data, feature flag off by default | M12 |
| Default DB passwords; demo credentials in the README | `${VAR:?}` secrets, no defaults. Demo credentials **are** published now, but deliberately: they exist only when `DEMO_MODE` is on, are locked (no credential changes), can't log in when demo mode is off, have only their role's rights, and the owner's `super_admin` is a separate, never-published account with 2FA (ADR-0006) | M0, M1, M6 |
| Developer checkboxes (`dev_connection`, `simulate_error`) in production | Error states tested with fakes, never with UI switches. Demo mode is the opposite of those checkboxes: one documented env flag, off by default, owner-approved, tested on and off, and it never weakens security (ADR-0006). The simulated payment provider is a configured adapter, not a form switch | M0, M1, M4, M6 |
| No rate limiting, security headers or audit log | Headers/CSP in M0; rate limits in M1 (auth, recovery and setup codes), M6 (registration cap, bot protection), M8 (guest chat); audit log from M1, viewer in M7 | M0, M1, M6, M7, M8 |
| No GDPR features (export, deletion, retention, privacy statement) | Privacy statement, export and erasure before public exposure; retention jobs and passport handling later; nightly wipe on the demo | M6, M10 |
| Airports duplicated for every trip | Airport reference data with unique IATA; trips reference it | M2 |
| Trips had no price; price typed by hand; offers never applied | Base price on trips; pricing service applies offers server-side | M2, M4, M9 |
| Accounts and Customers: two CRUDs over the same data | One customer module | M7 |
| Booking search on a column that doesn't exist | Feature tests for every filter; Larastan | M7 |
| Money as decimal; VAT by hand; no single VAT source | Integer cents + EUR; VAT 21 % in one config value; invoice totals from lines | M4, M5 |
| No booking flow for customers | Customer booking wizard with payment | M4 |
| Messages limited to 25 characters; list not scoped | 2,000-character limit; conversations scoped by policy to their customer or guest token | M8 |
| Stored procedures mixed with Eloquent; collation hacks | No stored procedures (CLAUDE.md); all logic in tested PHP services; PostgreSQL | M0, every milestone |
| Duplicate routes; `route:cache` couldn't run | CI and image build run `php artisan optimize` | M0 |
| Inconsistent visual style; lorem ipsum | One design system for public and staff areas; real content | M0, M3 |
| Mixed Dutch/English UI, no i18n | i18n keys from M0; CI check for missing keys | M0 |
| Error states built for the assessment | Designed error pages and form error pattern; user-facing copy | M0, every milestone |
| Unoptimised images, no alt strategy | Responsive image pipeline; alt-text guideline; Lighthouse gate | M3, M9 |
| No skip links, focus management, contrast checks, ARIA state | App shell with skip link and focus handling; React Aria; axe in CI; per-milestone audits | M0, every milestone |
| Only Breeze's default tests | Tests are part of every story's DoD; testing track (§ 6) | every milestone |
| No CI, no enforced linting, no seed/demo separation, `minimum-stability: dev` | CI gates; `reference` vs `demo` seeders (demo on production only behind `DEMO_MODE`); stable dependencies | M0, M2, M6 |
| Image built on the server by Portainer | CI builds amd64 images to GHCR; Portainer pulls pinned tags | M0 |

All items in `legacy-analysis.md` §2, §3 and §4 are placed. The only deliberate drop is e-mail verification
(no e-mail at all, ADR-0005); the contact form and the messages inbox are replaced by the support chat.

## 6. Cross-cutting tracks (apply to every milestone)

| Track | Rule in every story | Owner of the standard | Gate |
|---|---|---|---|
| **i18n nl/en** | No hard-coded UI strings; nl is the default; dates/money via `Intl` (SSR-consistent); times in the airport's time zone with a label | frontend-developer | CI missing-key check; reviewers |
| **WCAG 2.2 AA** | Design approved before build; React Aria for complex widgets; keyboard path and screen-reader announcements in the ACs; 320 px reflow / 400 % zoom; floating UI (the chat launcher) never obscures focused content | accessibility-auditor | axe in Playwright (CI); auditor verdict per UI story and per milestone |
| **GDPR / privacy** | Minimum data; every new personal-data field in `data-inventory.md` with purpose, basis and retention; **every story that adds personal data extends the export, erasure and nightly-reset tests (from M6 on) and is protected from first storage** (policies, no logs, no serialisation by default); server-side masking of visitor data for all staff except super_admin in demo mode | security-privacy-reviewer | Reviewer verdict |
| **Security (ASVS L2)** | Authorization AC in every data story; Form Request validation; no mass assignment; rate limits on public writes; secrets only from env; audit sensitive actions; public from M6, so every milestone gets a security review | security-privacy-reviewer | Arch tests, `composer`/`npm audit`, Trivy, reviewer verdict |
| **Demo mode** | Any behaviour that differs in demo mode goes through the `DemoMode` service, is listed in ADR-0006, and is tested on **and** off | backend-developer | Arch test; qa |
| **Testing** | Unit for rules, feature per endpoint (happy, validation, unauthenticated, unauthorized, not found), e2e per journey with axe on the SSR build; deterministic (frozen clock, fakes, fake payment provider; no mail exists to fake); bug fixes start with a failing test | qa-engineer (`docs/testing.md`) | CI blocks the image publish |
| **Performance** | SSR-safe, code-split pages; responsive images; indexed queries checked with `EXPLAIN`; no N+1 (`preventLazyLoading` in dev/test) | frontend / database | Lighthouse gate on public pages |
| **Deployability** | `main` is always deployable; new env vars go into `.env.example` and the deploy docs; migrations are reversible and safe to run on start | devops-engineer | Milestone redeploy to the home server |

## 7. Cross-cutting architecture (baseline for all stories)

These are the default constraints stories refer to. Items marked *ADR* get their own record when decided.

- **Authentication:** Fortify, session cookies (Secure, HttpOnly, SameSite=Lax); the e-mail address is an
  unverified login name (ADR-0005); TOTP 2FA **required for manager, admin and super_admin** and optional for
  employee and customer (Q8); login throttling; no "remember me" for staff accounts. **One-time codes** (account
  recovery codes and staff setup codes) are ≥ 100-bit random values, shown once, stored hashed, never logged,
  rate-limited per IP and per account, audited, and typed in (never put in URLs). Demo accounts: see ADR-0006.
- **Authorization:** one `role` enum on `users`; Laravel Policies for every model, Gates for non-model abilities;
  every controller action is authorized (a Pest arch/feature check). The client receives `can` flags for display
  only. Hiding a button is never the access control. Demo mode never grants extra rights; it only removes some
  (credential changes, staff account creation, unmasked visitor data). Visitor-data masking is applied server-side in the
  data objects, so masked values never reach the browser (ADR-0006 D6).
- **Request/API shape:** Inertia pages, server-rendered (SSR); no public JSON API. A few typed JSON endpoints for
  dependent search options and the support-chat polling, with rate limits; the Mollie webhook (M11). Data to the client goes through
  spatie/laravel-data objects (generated TS types), never raw models. Routes are referenced via Wayfinder.
- **Domain layer:** business rules in `app/Domain/*` services/actions (pricing, booking, payments, invoicing,
  packages, retention, demo reset), called from thin controllers; money as a `Money` value object (int cents +
  EUR); time as UTC `timestamptz`.
- **Payments:** a `PaymentProvider` interface (ADR-0003), with a simulated provider (default) and Mollie test mode
  (M11), selected by `PAYMENT_PROVIDER`.
- **Error handling:** validation errors → inline + error summary (Inertia); exceptions → localised error pages
  with a request ID; no stack traces or SQL in responses; `APP_DEBUG=false` in production, enforced by a boot check.
- **i18n:** shared JSON lang files; locale from the user's preference, then a cookie, then `Accept-Language`,
  defaulting to nl; no locale prefix in URLs (accepted). SEO consequence: search engines index the nl version; the
  SSR head declares the language. Revisit with a URL prefix only if English SEO matters later.
- **Configuration:** everything from env via `config/*.php`; app-specific values (VAT rate, currency, retention
  periods, code expiry, registration cap, chat limits and polling intervals, `DEMO_MODE`, `PAYMENT_PROVIDER`,
  `chatbot.enabled`) in `config/traveleasy.php`; there are no mail settings (the mailer is `array`, ADR-0005);
  `.env.example` documents all of them.
- **Logging:** JSON to stdout in production, with a request ID; no personal data or secrets in log lines; security
  events go to the `AuditLog` table, not the app log.
- **Background jobs:** database queue driver; `worker` and `scheduler` services run from the same image; exports
  and PDFs are queued (there are no mails); jobs are idempotent and retried with backoff. The nightly demo reset is a
  scheduled command at 03:45 Europe/Amsterdam (the scheduler's time zone; stored times stay UTC), registered
  only in demo mode.
- **File storage:** a private local disk on a named volume (`storage`): invoice PDFs (if stored) and data exports.
  Downloads go through policy-checked routes or signed, expiring URLs. Public assets (incl. demo hotel images) are
  built into the image; no user uploads in v1 (accepted).
- **Services in production:** `app` (web), `ssr`, `worker`, `scheduler`, `db`, `backup`. Only `app` and `ssr` (if
  ADR-0002 needs it) are on the NPM network.
- **In-app notifications:** unread badges updated by polling (support chat); there are no e-mail or push
  notifications.
- **ADRs:** numbered in the order they're written, with 0002–0004 reserved for the decisions planned below.
  Written: 0001 tech stack, **0005 no outgoing e-mail** (Accepted; it took the number reserved for the mail
  provider), **0006 demo mode** (Accepted). Expected: 0002 production runtime + SSR packaging (M0), 0003
  payment-provider interface (M4), 0004 PDF library (M5), 0007 bot protection (M6), 0008 package domain model (M9),
  then the chatbot provider (M12).

### Role matrix (accepted, Q7)

Hierarchy: `employee` ⊂ `manager` ⊂ `admin` ⊂ `super_admin` (each has everything of the role before it). "Own" =
records linked to the user's customer profile. In demo mode, the published demo accounts have exactly their role's
rights, minus credential changes, staff account creation and unmasked visitor data (ADR-0006).

**`super_admin`** is the owner's private account and the only real account on the live demo: never published, never
a demo account, **provisioned from env** (e-mail, password hash, TOTP secret) on every boot and after every nightly
reset, never created or changed through the UI, and absent if the env vars are missing. It is **hidden from every
other role**: never listed, counted, searchable or shown in any UI, export, audit view or demo login hint; where its
actions must be attributed, other roles see "TravelEasy" (system). Server-side artisan commands (password reset via
setup code, 2FA reset for other users) are run by the owner. In the app it deliberately holds
only three things that `admin` doesn't:
1. managing `admin` accounts (create with a setup code, promote to or demote from admin, deactivate);
2. creating staff accounts while demo mode is on;
3. seeing visitor personal data unmasked while demo mode is on.

Everything else it can do, an admin can do too. No role (including super_admin) can create, promote to, demote, deactivate or delete a
super_admin through the UI.

| Resource / action | Guest | Customer | Employee | Manager | Admin | Super admin |
|---|---|---|---|---|---|---|
| Search, trips, packages, offers, destinations, about, contact | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Own profile, password, recovery code, 2FA, export | – | ✔ | ✔ | ✔ | ✔ | ✔ |
| Delete own account | – | ✔ | ✔ | ✔ | ✔ | – (server only) |
| Book a trip or package | – (account at checkout) | own | on behalf of a customer | on behalf | on behalf | on behalf |
| Bookings: view | – | own | all | all | all | all |
| Bookings: change status / cancel | – | own, within policy | ✔ | ✔ | ✔ | ✔ |
| Invoices: view / download | – | own | all | all | all | all |
| Invoices: mark paid, credit note | – | – | mark paid | ✔ | ✔ | ✔ |
| Customers: view / edit contact data / note | – | – | ✔ | ✔ | ✔ | ✔ |
| Customers: deactivate | – | – | – | ✔ | ✔ | ✔ |
| Passport data (M10): view | – | own travellers | ✔ (audited) | ✔ (audited) | ✔ (audited) | ✔ (audited) |
| Support chat (M8) | own (browser-bound guest conversation) | own | support inbox | support inbox | support inbox | support inbox |
| Trips, airports, hotels, extras, packages, offers: create / edit / status | – | – | view only | ✔ | ✔ | ✔ |
| Dashboard | – | – | basic | + revenue/occupancy | + revenue/occupancy | + revenue/occupancy |
| Staff accounts: create (setup code), role change, deactivate `employee`/`manager` | – | – | – | – | ✔ (not in demo mode) | ✔ (also in demo mode; in practice unused on the demo) |
| Staff accounts: manage `admin` accounts | – | – | – | – | – | ✔ |
| Visitor personal data in demo mode | – | – | masked | masked | masked | **unmasked** |
| See the super_admin account (lists, counts, search, exports, audit, login hints) | – | – | never | never | never | own account only |
| Audit log | – | – | – | – | ✔ | ✔ |
| 2FA | – | optional | optional | **required** | **required** | **required** (never exempt) |

## 8. Release and deploy cadence

| When | What runs on the home server | Reachable from |
|---|---|---|
| End of M0 | `v0.0.x` walking skeleton (SSR shell, error pages, health check) | **LAN only** (first deploy) |
| End of M1–M5 | `v0.N.0` per milestone; `v0.5.0` = full search → book → invoice slice | LAN only |
| End of M6 | `v0.6.0` public demo with demo mode, masking and nightly reset | **Public** on the owner's subdomain through NPM over HTTPS |
| End of M7–M10 | `v0.N.0` per milestone | Public |
| End of M11 | `v1.0.0` (Mollie test mode available) | Public |
| M12 | `v1.1.0` with the chatbot flag off by default | Public |

Every deploy needs the owner's OK (orchestrator rule). Rollback = redeploy the previous tag. Migrations must stay
compatible with the previous tag for one release, so a rollback doesn't need a down-migration.

## 9. Owner answers (2026-10-11)

| # | Question | Answer | Where it landed |
|---|---|---|---|
| Q1 | Time budget / deadline? | None. | § 1, § 3 (sizes only) |
| Q2 | Real business or demo? Public earlier? | A **public portfolio demo**, linked from the owner's portfolio. Visitors can register; demo admin/manager/employee/customer credentials are published. Going public earlier is welcome, but only after the security baseline. | M6 = first public release, right after the slice (M5) |
| Q3 | Payment? | A **payment-provider interface** with a **simulated provider** first; **Mollie test mode** later as an optional adapter, once the site is public (account + webhook needed). | M4 (interface + simulated, ADR-0003), M11 (Mollie) |
| Q4 | Packages? | **Build real packages:** flight + hotel bundles, possibly extras. | M9 (ADR-0008) |
| Q5 | Public registration? | Open customer self-registration; staff only by admin invite. *Invites are now setup codes (§ 11 R4).* | M1, M6 (abuse protection) |
| Q6 | Booking scope? | As assumed: one trip per booking, no seat selection, children pay the adult price, no infants. | M4 |
| Q7 | Role matrix? | As proposed. | § 7 |
| Q8 | 2FA? | Required for manager and admin, optional for employee and customer. **`DEMO_MODE`** (on for the live demo, off by default): only the published demo accounts are exempt from mandatory 2FA; they can't change password, e-mail or 2FA; the nightly reset restores them. An explicit, documented, owner-approved feature. | M1 (2FA, demo-mode core), M6 (public parts), ADR-0006 |
| Q9 | VAT? | 21 % in one config value. | M4 |
| Q10 | Cancellation policy? | As assumed: free self-cancel until 14 days before departure; after that, only staff. | M4, M5 |
| Q11 | Production mail? | A **real provider** on the live demo (owner's subdomain, SPF/DKIM) so verification and reset mails arrive; Mailpit in dev; provider chosen in an ADR before go-live. Plan rate limits and self-hosted/privacy-friendly bot protection against abuse. | **Superseded by § 11 R1: no outgoing e-mail (ADR-0005).** Bot protection (ADR-0007) stays, in M6 and M8. |
| Q12 | Domain/DNS? | A subdomain of the owner's domain. Static vs dynamic IP unknown; devops checks at deploy time, DDNS as fallback. | M6 |
| Q13 | Off-site backups? | None. A local scheduled DB dump is enough, because the demo resets nightly. | M1 (local backup), M6 (dump after reset), M11 (restore drill) |
| Q14 | Retention periods? | Follow GDPR storage limitation. On the live demo, the nightly reset wipes visitor data within 24 hours; the privacy statement says so and warns not to enter real personal data, especially passport data. Configurable retention for non-demo installs, defaults justified by the security-privacy-reviewer. | M6 (statement v1), M10 (retention jobs, statement v2) |
| Q15 | SEO / SSR? | **Yes to Inertia SSR.** | M0 (SSR process in dev/CI/prod), M3 (SEO) |
| Q16 | Demo data in production? | The live site runs on **demo data with a nightly reset** (reference + demo seeders), behind `DEMO_MODE` only; it must never run when demo mode is off. | M6, ADR-0006 |
| Q17 | Messaging order? | Messaging stays after the back office. | M8 after M7 |
| Other | Login to book, EUR only, no URL locale prefix, no user uploads in v1 | Accepted. | § 7 |

## 10. Owner answers, follow-up round (2026-10-11)

| # | Question | Answer | Where it landed |
|---|---|---|---|
| N1 | Accept the ADR-0006 change list? | **Accepted** (2026-10-11), with the changes below folded in. Plus a new fifth role, **`super_admin`**: the owner's private account, above `admin`, never published, never a demo account, created only by an artisan command or the `reference` seeder from env, never through the UI, kept by the nightly reset. Only super_admin can manage admin accounts, invite staff in demo mode and see unmasked visitor data. *Provisioning and visibility refined in § 11 Q-D: env-provisioned (hash + TOTP secret), re-created after every reset, hidden from all other roles.* | ADR-0006 (Accepted); M1; § 7 role matrix |
| N2 | Visitor data and the public demo staff accounts | Registration stays **open** in demo mode. Visitors' personal data (at least name and e-mail, plus any other visitor PII on staff screens) is **masked for all demo accounts**, including demo admin and demo manager, whatever the address. Only super_admin sees it unmasked. No "test vs real" address detection. Masking happens **server-side**; masked values never reach the browser. | ADR-0006 D6; M6, extended in M7, M8, M10 |
| N3 | Staff invitations in demo mode | **Disabled for every demo account.** Only super_admin can invite, also in demo mode. *Invitations are now staff accounts with setup codes (§ 11 R4); the rule is unchanged.* | ADR-0006 D8; M1; M6 |
| N4 | Package definition | Accepted as assumed: outbound + return flight always included, nights fixed by the package, price computed from the parts minus an optional package discount, extras = transfer, extra luggage, excursion. | M9, ADR-0008 |
| N5 | Hotel images | Hotel photos ship with the app; no uploads. | M9 |
| N6 | Nightly reset time and kept accounts | **03:45 server time** (Europe/Amsterdam). Not 04:00: the owner has other jobs at 04:00 and 04:15. The DB dump runs after the reset; it doesn't have to finish before 04:00, but it must not collide with the owner's jobs (devops sets the exact time with that constraint). Everyone is logged out; **super_admin is kept**. *Refined in § 11 Q-D (super_admin re-provisioned from env instead of kept) and Q-E (finish in under ~10 min).* | ADR-0006 D10; M6 |
| N7 | Mail provider | **Gmail**, set up by the owner: SMTP with an app password held only in env; the mail budget stays below Gmail's daily limit; From = the Gmail address (or a verified "send as" alias); custom-domain DKIM would need Workspace, so the subdomain SPF/DKIM/DMARC items are dropped; recipients see the owner's address. | **Superseded by § 11 R1: no outgoing e-mail (ADR-0005).** |

## 11. Owner decisions, third round (2026-10-11): no outgoing e-mail

| # | Decision | Details | Where it landed |
|---|---|---|---|
| R1 | **No outgoing e-mail at all, in any environment** | No verification, password-reset, notification, invitation or contact mail; no Gmail; no Mailpit. Reverses § 9 Q11 and § 10 N7. | ADR-0005 (Accepted); M0 (no Mailpit, mailer `array`, arch test); every milestone |
| R2 | **E-mail is a login name only** | Never verified, never mailed; still masked for demo staff (ADR-0006 D6). E-mail verification and "unverified accounts can't book" are removed. | M1; § 7 |
| R3 | **Forgot password via a recovery code** | One-time code shown at registration; e-mail + code sets a new password and issues a fresh code; regenerate from the profile after re-entering the password; hashed, rate-limited, audited; lose both and the account is lost; super_admin artisan reset as last resort. | M1; § 7 |
| R4 | **Staff onboarding without e-mail** | The creator gets a one-time, expiring **setup code** to hand over out of band; the new colleague enters e-mail + code on `/account/setup`. Codes are typed in, never put in URLs. In demo mode only super_admin can create staff. | M1; ADR-0006 D8 |
| R5 | **Support-chat widget** replaces `/messages` and the contact form | A floating button bottom-right; staff answer from a support inbox; in-app unread badges with polling; **guests can chat** with a browser-bound anonymous conversation they can claim after registering or logging in; spam/abuse protection; guest chats wiped nightly on the demo and retained for a limited time elsewhere; guest content masked for demo staff; WCAG 2.2 AA as a key risk. M8 stays after M7. | M8; ADR-0006 D6/D10; § 7 |
| R6 | **Export and invoices in the app only** | GDPR export is an in-app download; invoices are in-app views and PDF downloads. | M5, M6 |
| Q-C | Masking for non-demo staff? | There will **never be real staff**. The only real account is the owner's super_admin; every other staff account is a demo account. Masking applies to all non-super_admin staff. The setup-code onboarding stays as a generic mechanism for non-demo installs; on the demo only super_admin could use it, and in practice it won't be used. | M1, M6; ADR-0006 D6, D8 |
| Q-D | Real staff and the reset | The super_admin doesn't need to survive the reset as a row: it's **(re)created from env** on every boot and after every nightly reset. Env holds a **password hash** (`SUPER_ADMIN_PASSWORD_HASH`), never the plain password, with an artisan command to print a hash; the **TOTP secret** is in env too (`SUPER_ADMIN_TOTP_SECRET`), so the authenticator keeps working. Accepted risk: access to the Portainer stack equals access to this account (acceptable for a demo). The super_admin is **hidden everywhere** from other roles and from the demo login hints, proven by a test. Without the env vars there is no super_admin, and the app still works. | M1; M6; § 7; ADR-0006 D1, D9, D10 |
| Q-E | The owner's 04:00 and 04:15 jobs | They reset and reseed two other demo sites on the same CasaOS server. Unrelated, but they share the machine. The 03:45 reset + DB dump must finish well before 04:00 (**target under ~10 min**), with a timing check and alert. | M6 (exit criteria, alert); deploy docs |

Notes:
- Bot protection (ADR-0007) stays: on registration (M6) and on guest chat (M8).
- `/contact` stays as a page with the agency's details and a "Start a chat" button.
- Alerts can't be e-mailed, so operational alerts (e.g. a slow reset) are shown in the app to the super_admin and
  written to the logs.

## 12. Open questions for the owner

Each has a working assumption; stories will use it unless the owner says otherwise.

| # | Question | Needed by | Working assumption |
|---|---|---|---|
| Q-F | **Chat widget in the back office.** Should the floating chat button also appear on staff pages, or do staff only get the support inbox and an unread count in the navigation? | M8 | Hidden in the back office; staff use the inbox |
| Q-G | **How long can a guest come back to a conversation?** The guest's conversation is tied to a functional cookie in their browser. How long should it last? (On the demo, the nightly reset ends it anyway.) | M8 | Until the conversation is closed, at most 7 days (configurable) |
| Q-H | **Alerts without e-mail.** Is an in-app warning for the super_admin plus log entries enough for operational problems (a slow or failed reset, a failed backup)? Or do you want a self-hosted push channel (e.g. an ntfy server on CasaOS)? | M6 | In-app warning + logs only |

Things the owner should know (no decision needed):
- **Env holds the super_admin's keys.** `SUPER_ADMIN_PASSWORD_HASH` and `SUPER_ADMIN_TOTP_SECRET` in the Portainer
  stack mean that anyone with access to the stack can take over the super_admin account. This is the accepted
  trade-off from Q-D. Portainer access already means control of the server, so it adds little real risk. Keep
  Portainer behind your LAN or behind strong authentication.
- **A lost authenticator or password** is fixed by putting a new hash or TOTP secret in env and redeploying;
  there is no in-app recovery for the super_admin.
- **Unverified login names:** anyone can register with an e-mail address that isn't theirs. Nothing is ever sent to
  it, so the impact is low; the privacy statement explains this.
