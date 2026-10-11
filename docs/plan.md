# TravelEasy remake: roadmap

- **Owner of this file:** `architect` (stories are written from it by the `task-writer`)
- **Status:** Accepted (2026-10-11). The owner answered the open questions; the answers are recorded in § 9
  (first round) and § 10 (follow-up round). New questions raised by those answers are in § 11.
- **Inputs:** `CLAUDE.md`, `docs/legacy-analysis.md`, `docs/adr/0001-tech-stack.md`, `docs/stack-options.md`,
  `docs/adr/0006-demo-mode.md`, `docs/backlog/ideas.md`

## 1. Goal

Rebuild TravelEasy as a solo project that does better than the original on every front. The live site is a
**public portfolio demo** of a fictional travel agency, linked from the owner's portfolio. Visitors can register as
customers, or log in with published demo accounts (customer, employee, manager, admin), and try everything. The
data is demo data that **resets every night**.

Customers can **actually book** flights and **flight + hotel packages**: search, pick, add travellers, apply an
offer code, review, pay (simulated, later Mollie test mode), confirm and get an invoice. Staff run the agency from
one consistent back office. The app is accessible (WCAG 2.2 AA), bilingual (nl/en), private by design (AVG/GDPR),
secure (OWASP ASVS L2), server-rendered for SEO (Inertia SSR), and self-hosted on the CasaOS home server from a
pinned GHCR image.

How we get there:
- **A vertical slice first.** Search, then book, then invoice, end to end (M3 to M5), before the back office
  is broadened (M7+).
- **Deploy early and often.** The first home-server deploy is at the end of **M0** (LAN only). The site goes
  **public on the internet at the end of M6**: right after the booking slice works, and only once the security
  baseline, demo mode, nightly reset, real e-mail, bot protection and privacy statement are in place.
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
| M1 | Accounts, authentication and roles | M | M0 | Register/login, 5 roles incl. **super_admin** + policies, 2FA (required for manager/admin/super_admin), staff invites, audit log, **demo-mode core** |
| M2 | Airports and trip catalogue | M | M1 | Airports reference data, staff manage trips, public trip list + detail |
| M3 | Search and public site | M | M2 | Dependent search on the home page, destinations, about, real content, SEO |
| M4 | Booking flow with offers | L | M3 | Customers book end to end; server-side pricing; offer codes; **payment-provider interface + simulated provider** |
| M5 | Invoices | M | M4 | Invoice issued on booking, PDF, VAT, mail; **vertical slice complete** |
| M6 | Public demo launch | M | M5 | **First internet exposure**: nightly reset, demo accounts, real e-mail, bot protection, privacy statement, export/erasure |
| M7 | Staff back office | L | M6 | Dashboard, one customer module, booking management, staff management, audit viewer |
| M8 | Messaging and contact | M | M7 | Customer ↔ staff conversations; contact form that really delivers |
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
  `dd`/`dump`, demo-mode checks only through the `DemoMode` service), Vitest + Testing Library, Playwright +
  @axe-core/playwright. *(legacy §3 Engineering, Security)*
- Docker dev env: `compose.yaml` + `compose.dev.yaml`, bind-mounted source, Vite HMR with polling,
  `vendor`/`node_modules` in anonymous volumes, PostgreSQL in a named volume, Mailpit. *(CLAUDE.md § Docker dev workflow)*
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
  - Dev compose with hot reload, Postgres named volume and Mailpit
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
**After this you can:** register as a customer, verify your e-mail, log in, reset your password, and edit or delete
your profile. As admin you can invite employees and managers; as super_admin (the owner's private account) you can
also manage admins. Managers, admins and the super_admin must use 2FA. With `DEMO_MODE` on, you can log in with the
four demo accounts, which skip 2FA and can't change their credentials.

**Scope**
- Fortify: register (open to anyone, Q5), verify e-mail, login, forgot/reset password, profile edit, password change,
  account deletion (extended to all modules in M6). E-mail is the login on `users`.
  *(legacy §2 Auth; §3 Security: login via contacts table)*
- **2FA (Q8):** TOTP **required** for `manager`, `admin` and `super_admin` (enrolment is forced at first login,
  and the staff area is blocked until it's done); **optional** for `employee` and `customer`. Recovery codes, plus
  an artisan reset command (server access only). Login throttling, session regeneration, Secure/HttpOnly/SameSite cookies.
- Roles `customer`, `employee`, `manager`, `admin`, `super_admin` as one enum on the user. Policies for every
  model, deny by default, and no blanket `Gate::before` bypass, not even for `super_admin` (its extra rights are
  explicit policy abilities). Authorization props are sent to Inertia for display only. Role matrix in § 7.
  *(legacy §3 Security: no authorization; role checked three ways)*
- **`super_admin` (§ 10):** the owner's private account. Created **only** by an artisan command that prompts for
  credentials, or by the `reference` seeder from env (`SUPER_ADMIN_EMAIL` + `SUPER_ADMIN_PASSWORD`, only when no
  super_admin exists, and it never overwrites one). Never created, promoted to, demoted, deactivated or deleted
  through the UI. Never a demo account: a DB CHECK enforces `role <> 'super_admin' OR is_demo = false`. Its
  credentials are never published. There are no other seeded production accounts. *(legacy §2 test accounts; §3 Security)*
- Staff management: admin lists staff, invites `employee`/`manager` accounts by e-mail (the invitee sets a password),
  changes their role between those two, deactivates them. Only super_admin can invite, promote, demote or
  deactivate `admin` accounts. In demo mode, only super_admin can invite at all (ADR-0006 D8).
- **Demo-mode core (ADR-0006, changes D1–D4):** the `DemoMode` service and `DEMO_MODE` config (off by default);
  `users.is_demo`; the `demo` seeder creates the four published demo accounts only when demo mode is on; demo
  accounts are exempt from mandatory 2FA and locked against credential and account changes; `is_demo` accounts
  can't log in when demo mode is off. It's built here because it touches the auth code and makes local dev
  convenient. The public-facing parts come in M6.
- Audit log foundation: `AuditLog` model + service; logs login, failed login, 2FA changes and role changes,
  without personal data in log lines. *(legacy §3 Security: no audit log)*
- Rate limiting on login, registration, password reset and verification-mail resend (per IP and per e-mail).
- Mail: queued, bilingual templates (verify, reset, invite). Queue worker + scheduler services in compose.
- Scheduled local DB backup with rotation, plus a tested restore procedure (Q13: local only).
- `docs/privacy/data-inventory.md` v1 (account data).

**Agents:** ux-ui-designer (auth screens), database-engineer, backend-developer, frontend-developer,
devops-engineer (worker, scheduler, backup), qa, a11y, security.
**Depends on:** M0.
**Exit criteria:** DoD; an authorization test matrix exists and runs in CI (every role against every staff
route); the demo-mode tests pass with the flag on and off; a restore from a backup has been tested once.

**Candidate epics / stories**
- Epic: Customer accounts
  - Customer registration with e-mail verification
  - Login and logout with throttling
  - Forgot and reset password
  - Profile: edit name, e-mail (re-verify) and language preference
  - Change password
  - Delete own account (basic)
- Epic: Roles and authorization
  - Role model and deny-by-default policy baseline
  - Role-based navigation and staff area shell
  - Authorization test matrix in CI
- Epic: Staff accounts
  - Create the super_admin via artisan command or reference seeder from env
  - Admin invites employees and managers and assigns their roles
  - Super_admin manages admin accounts
  - Admin deactivates and reactivates staff
  - TOTP 2FA: required for manager/admin/super_admin, optional for others
- Epic: Demo-mode core (ADR-0006)
  - `DemoMode` service, config flag and `is_demo` accounts
  - Demo accounts skip mandatory 2FA and can't change credentials
  - `is_demo` accounts are refused when demo mode is off
- Epic: Audit and operations
  - Audit log for security events
  - Queue worker and scheduler services
  - Scheduled local database backup and restore drill

**Risks**
- Starter-kit auth screens may not meet AA out of the box. Mitigation: the auditor reviews them as part of this milestone.
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
itemised price (incl. VAT and discount), pay with the simulated provider, receive a confirmation e-mail, and see
the booking under "My bookings". As manager, you can create offers, and everyone can browse current offers.

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
- Booking confirmation e-mail (queued, nl/en).
- Special requests field (free text, length-limited, flagged in the data inventory because it can contain health
  data). *(legacy §2 Bookings: special requests)*

**Agents:** ux-ui-designer (booking flow), database-engineer, backend-developer (lead), frontend-developer, qa,
a11y, security (pricing, payment state, IDOR, personal data).
**Depends on:** M3 (search as the entry point), M1 (accounts).
**Exit criteria:** DoD; e2e journey search → book → simulated payment → confirmation mail (Mailpit) passes against
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
  - Booking confirmation e-mail
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
**After this you can:** after booking, download your invoice as a PDF and receive it by e-mail. As staff, you can
see all invoices, mark them paid, and issue a credit note for a cancellation.

**Scope**
- `Invoice` + `InvoiceLine`: sequential number without gaps per year, issue date, lines copied from the booking
  items' price breakdown, excl./VAT/incl. totals computed from lines, status (open/paid/credited). Paid status
  follows the payment record; staff can also mark an invoice paid by hand. Immutable once issued; corrections are
  credit notes. *(legacy §2 Invoices; §3 Domain: VAT by hand)*
- **ADR-0004: PDF library** (dompdf vs spatie/laravel-pdf, weighing image size on the small server now that SSR
  already adds Node). PDFs are generated on demand or stored on the private `invoices` disk (decided in the ADR).
- Customer: own invoice list and download (policy-checked). Staff: invoice table with filters, mark paid,
  credit note (also triggered by a cancellation). Invoice e-mail (queued).
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
  - Invoice e-mail to the customer
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
(and really receive the verification e-mail) or log in with the published demo accounts, search, book, pay
(simulated) and download an invoice. A banner explains that it's a demo, that data resets every night, and that
they shouldn't enter real personal data. Every night everything visitors entered is wiped and the demo data is
restored.

**Why here:** the booking slice (M3–M5) is the first thing worth showing. The security baseline exists from M0–M1
(headers/CSP, deny-by-default policies, authorization matrix, 2FA for privileged real accounts, rate limits,
audit log). This milestone adds what public exposure itself needs. Everything after M6 ships to the public site.

**Scope**
- **Demo mode, public parts (ADR-0006, changes D5–D10):** site-wide demo banner (nl/en); warnings on the
  registration form and the booking travellers step (and the passport fields once they exist, M10);
  **server-side masking of visitor personal data** for every staff account except super_admin (D6, see below);
  staff invitations disabled for every demo account (only super_admin can invite); demo accounts listed on the
  login page; the **nightly reset** job at **03:45 Europe/Amsterdam** (the server's time zone) (`demo:reset`:
  maintenance mode → wipe, logging everyone out → `reference` + `demo` seeders → super_admin kept → maintenance
  mode off → trigger the DB dump of the clean state). It's registered in the scheduler only when demo mode is on,
  and the command refuses to run otherwise.
- **Visitor-data masking (N2, ADR-0006 D6).** Registration stays open. In demo mode, every staff account except
  `super_admin` gets visitor personal data **masked on the server**: the masked value replaces the real one before
  it leaves PHP (in the laravel-data objects, PDFs, exports and CSVs), so the real value never reaches the browser.
  This applies to every self-registered (non-demo) customer, whatever their address; there is no "test vs real"
  detection. Masked fields: name, e-mail, phone, address, date of birth, traveller names, special requests, and
  later message content (M8) and passport data (M10). Searching and sorting on masked fields doesn't match visitor
  records, so search can't be used to confirm whether an address is registered.
- **Real e-mail through Gmail (Q11, N7).** The provider is decided: **Gmail SMTP**, set up by the owner.
  ADR-0005 records the setup and its consequences at the start of M6:
  - SMTP (`smtp.gmail.com`, port 587, STARTTLS) with a Google **app password**. That needs 2-Step Verification on
    the Google account. The app password lives only in env (`MAIL_USERNAME`/`MAIL_PASSWORD`), never in Git.
  - **Sending limit:** a consumer Gmail account allows about 500 recipients per rolling 24 hours, and going over
    it blocks sending from the account for up to a day. The global mail budget defaults well below that (e.g.
    300/day and 50/hour, configurable).
  - **From address:** the Gmail address itself (recommended), or a verified "Send mail as" alias. Custom-domain
    DKIM needs Google Workspace, so with consumer Gmail the mail is authenticated as gmail.com: SPF/DKIM pass for
    gmail.com, and **no SPF/DKIM/DMARC records are added for the subdomain**. A custom-domain alias would fail
    DMARC alignment, so it isn't used.
  - **Recipients see the owner's Gmail address**, and replies and bounces go to that mailbox.
  - Google becomes a **processor** of recipient addresses and mail content (outside the EU). This goes in
    `docs/privacy/data-inventory.md` and the privacy statement.
  - Gmail keeps a copy of every sent mail in "Sent", so visitor addresses stay there after the nightly reset
    unless they are cleaned up (§ 11 Q-A).
  - Mailpit stays in dev; tests use `Mail::fake()`.
- **Abuse protection for open registration with real mail (Q11):**
  - Rate limits per IP, per e-mail address and per /24 (or /64 for IPv6) on registration, verification resend,
    password reset and the contact form (M8). These run in every mode, not only in demo mode.
  - A **global outgoing-mail budget** per hour/day (circuit breaker). When it's exhausted, new registrations are
    paused with a clear message, and the owner is alerted through the log/audit log.
  - **ADR-0007: bot protection:** self-hosted and privacy-friendly only. Candidates: honeypot + minimum
    form-fill time (always on), plus a self-hosted proof-of-work challenge (e.g. the open-source ALTCHA widget
    and a server-side verifier). No third-party captcha, no cookies or fingerprinting, and accessible (no visual
    puzzle).
  - Unverified accounts can't book or message, and are purged by the nightly reset (on non-demo installs:
    after the retention period, M10).
- **Privacy baseline before exposure (Q14):** privacy statement v1 (nl/en) at `/privacy`, which says plainly that
  this is a demo, that all visitor-entered data is deleted within 24 hours by the nightly reset, and that visitors
  must not enter real personal data, **especially passport data**. Data export (JSON) and full erasure across
  every module that exists so far (account, bookings, payments, invoices). Only functional cookies, so no
  cookie banner (the reviewer confirms this).
- **Go-live infrastructure:** an NPM public proxy host on the owner's subdomain with TLS; trusted-proxy config; HSTS
  on; https URLs. Devops checks whether the home IP is static or dynamic and sets up DDNS if needed (Q12). The SSR
  and web services are the only ones on the NPM network.
- Local scheduled DB dump with rotation (Q13). In demo mode the dump runs right after the nightly reset, so it
  contains seeded data only (plus the super_admin) and never keeps visitor data beyond 24 hours. **Timing (N6):**
  the dump must not run at the same time as the owner's existing jobs at 04:00 and 04:15. Devops sets the exact
  time after measuring how long the reset and the dump take (e.g. 03:50, or chained right after the reset with
  a check that it finishes before 04:00).
- **Exposure review:** a security-privacy-reviewer pass over the internet-facing surface (ASVS L2 chapters for
  authentication, session, access control, validation, configuration), and a rollback rehearsal (deploy
  `v0.6.0`, roll back to `v0.5.x`, roll forward).

**Agents:** devops-engineer (lead: NPM, DNS/DDNS, Gmail SMTP env, reset and dump schedule, backups),
backend-developer (demo mode, masking, reset, mail, rate limits, bot protection, export/erasure),
frontend-developer (banner, warnings, challenge widget), database-engineer (reset, export/erasure queries),
ux-ui-designer (banner and warning copy, masked-value display), security-privacy-reviewer (lead on the privacy
statement, masking and the exposure review), qa, a11y.
**Depends on:** M5.
**Exit criteria:** DoD; the site is reachable over HTTPS on the subdomain; a verification e-mail sent through Gmail
arrives at a real mailbox outside the spam folder; a reset test proves that visitor data is gone, demo data is back
and the super_admin is kept, and that the command refuses when `DEMO_MODE` is off; masking tests prove that, for
every demo staff account (incl. demo admin), no visitor's real name or e-mail appears in any Inertia response, PDF or
export, and that super_admin sees them unmasked; an erasure test seeds a customer with data in every module and asserts
that nothing identifying remains; the exposure review has no open High/Critical findings. **`v0.6.0` is the first
public release.**

**Candidate epics / stories**
- Epic: Demo mode (public parts, ADR-0006)
  - Demo banner and "don't enter real data" warnings
  - Server-side masking of visitor personal data for all staff except super_admin
  - Disable staff invitations for demo accounts (super_admin only)
  - Show the demo accounts on the login page
  - Nightly reset at 03:45, guarded by demo mode, keeping the super_admin
- Epic: Real e-mail and abuse protection
  - ADR-0005: Gmail SMTP as the transactional mail provider
  - Gmail SMTP with an app password from env, and mail-budget defaults below Gmail's limit
  - Rate limits on registration, resends and resets
  - Global outgoing-mail budget with alert
  - ADR-0007: self-hosted bot protection
  - Bot protection on registration (and later the contact form)
- Epic: Privacy baseline
  - Privacy statement v1 in nl and en, with the demo notice
  - Customer exports all personal data as JSON
  - Full account erasure across all modules
- Epic: Go-live
  - Public proxy host with TLS, trusted proxies and HSTS
  - Static IP check and DDNS fallback
  - Backup after nightly reset and restore drill
  - Exposure security review and rollback rehearsal

**Risks**
- Mail abuse, or Gmail blocking the account for spam or for going over the sending limit (which also blocks the
  owner's own mail if it's a personal account). Mitigation: rate limits, a global budget below Gmail's limit, bot
  protection, verified-only actions, and a dedicated Gmail account for the demo (§ 11 Q-B).
- Google may restrict app passwords further. Mitigation: the mailer is config-only, so switching provider is an env
  change plus an ADR update.
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
  - Customer detail with bookings, invoices and conversations
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

### M8: Messaging and contact (M)

**Goal:** customers and staff can talk to each other, privately and accessibly (Q17: after the back office).
**After this you can:** as a customer, start a conversation (optionally about a booking), read replies and get an
e-mail notification. As staff, you can work through a shared inbox with unread counts. Visitors can use the
contact form and someone actually receives it.

**Scope**
- `Conversation` + `Message`: start, reply, mark read (per participant), unread counts, close/archive. The
  customer sees only their own conversations. Messages are up to a sensible limit (e.g. 5,000 chars), not 25.
  *(legacy §2 Messages; §3 Domain: 25-char limit, unscoped list)*
- Delete semantics: a customer can delete (hide) their own conversation, and an author can retract their last
  message while it's unread (legacy behaviour, kept but scoped). Staff deletion is a soft delete with an audit entry.
- Updates by polling (Reverb later if wanted, ADR-0001). New messages are announced through a polite live region.
- Mail notification of new replies (queued, opt-out in profile, counted against the mail budget).
- Contact form: validated, rate-limited, bot-protected with the ADR-0007 measure. It creates a conversation for
  logged-in users, and a staff inbox item for guests. *(legacy §2 Contact: sent nothing)*
- Demo mode: visitors' names, e-mails and message content are masked server-side for every staff account except
  super_admin (D6). Demo staff see that a visitor conversation exists, but not what it says. The contact form never sends mail
  to the address a guest enters (only to the configured staff address), so it can't be used to mail third parties.

**Agents:** ux-ui-designer, database-engineer, backend-developer, frontend-developer, qa, a11y (thread + live
region), security (scoping, XSS in message content, mail abuse).
**Depends on:** M7 (the staff inbox lives in the back office).
**Exit criteria:** DoD; a test proves customer A can't read B's conversation by ID; the contact form delivers;
export and erasure include conversations.

**Candidate epics / stories**
- Epic: Conversations
  - Customer starts a conversation, optionally about a booking
  - Reply in a thread with read status and polling updates
  - Staff shared inbox with unread counts
  - Delete own conversation and retract last unread message
  - E-mail notification of new replies with opt-out
- Epic: Contact
  - Public contact form that delivers to the staff inbox

**Risks**
- Polling load on the small server. Mitigation: a sensible interval, only on open threads, and a cheap `unread` endpoint.

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
non-demo install, unverified accounts, old sessions and exports, and old audit entries are purged on schedule.

**Scope**
- Passport/ID data on `BookingTraveller`: optional, purpose explained in the UI, `encrypted` cast, hidden from
  serialisation and logs, policy-limited to the roles in § 7, every read audited. In demo mode the field shows a
  prominent "don't enter real passport data" warning (D7), and visitors' passport data is masked for every staff
  account except super_admin (D6). *(legacy §3 Security: passport in plain text)*
- **Retention (Q14):** the GDPR storage-limitation principle ("no longer than necessary"). Retention periods are
  config values in `config/traveleasy.php`, with defaults that the security-privacy-reviewer justifies in
  `docs/privacy/retention.md`. Scheduled jobs on every install: purge passport data after the trip, purge
  unverified accounts and stale sessions/exports/payment holds, prune the audit log. On the live demo, the nightly
  reset (M6) already deletes everything within 24 hours, and these jobs still run so they are exercised.
- `docs/privacy/` complete: data inventory (incl. the mail provider and, later, the chatbot), retention, privacy
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
  - Scheduled purge of unverified accounts, stale sessions, exports and holds
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
| Contact (`/contact`) | Contact form that delivers to the staff inbox, bot-protected | M8 |
| Chatbot (`/chat`) | Opt-in, privacy-safe AI assistant | M12 |
| Auth: register, login, forgot/reset, verify e-mail | Fortify flows + throttling + 2FA (required for manager/admin); real e-mail on the live demo | M1, M6 |
| Auth: profile edit/delete | Profile in M1; full export and erasure in M6, extended in later milestones | M1, M6 |
| Dashboard (`/dashboard`) | Role-based staff dashboard with statistics | M7 |
| Accounts (`/account`) | Merged into one customer module | M7 |
| Customers (`/customers`) | Same single customer module | M7 |
| Bookings (`/bookings`) | Customer self-booking with payment (M4); staff booking management with working search (M7); package bookings (M9) | M4, M7, M9 |
| Booking fields: seat, purchase date/time, status, price, quantity, special requests | Purchase timestamp, status, server-computed price, travellers as rows, special requests; **no seat selection** (Q6) | M4 |
| Invoices (`/invoice`) | Immutable invoices with lines, gapless numbers, PDF, credit notes | M5 |
| Trip overview (`/reisoverzicht`) | Staff trip management with price, capacity and status | M2 |
| Departures (`/departures`) | Airport reference data (staff view/manage) | M2 |
| Messages: start, reply, mark read | Scoped conversations with read status and polling | M8 |
| Messages: delete conversation, delete last message | Hide own conversation; retract last unread message | M8 |
| Data model: people/customers/contacts/employees | `User` + `Person` + `Customer` + `Employee`; e-mail on `users` | M1, M7 |
| Data model: `is_active` + `note` on every table | Explicit statuses/deactivation where needed; staff note on customers and bookings | M2, M7 |
| Employee types Manager / Administrator / Desk Employee | Roles `manager` / `admin` / `employee`, plus a new `super_admin` for the owner | M1 |
| Seeder test accounts + 300 fake users | `demo` seeder with fake data; **published demo accounts only in demo mode** (ADR-0006), locked and reset nightly; the owner's private `super_admin` via command or env seeder, with 2FA | M1, M6 |
| §4 kept: Compose + Portainer deploy | Portainer stack from `compose.yaml` with a pinned GHCR tag | M0, M6 |
| §4 kept: entrypoint waits for DB, migrates, seeds empty DB | Same, in the production entrypoint | M0 |
| §4 kept: dependent search idea | Core of the search | M3 |
| §4 kept: NL/EN awareness | Full nl/en i18n from M0; chatbot answers in the chosen language | M0, M12 |

### 5.2 Legacy mistakes (`legacy-analysis.md` §3) → where prevented

| Mistake | Prevented by | Where |
|---|---|---|
| No authorization; customers could see everything | Deny-by-default policies; authorization matrix test in CI; an IDOR test per resource in every story; visitor personal data masked server-side for all staff except super_admin in demo mode | M1, M6, then every milestone |
| Admin role checked three different ways | One role enum, policies only; Pest arch test forbids ad-hoc role string checks | M0 (arch tests), M1 |
| Passport details in plain text | Not collected before M10; then optional, `encrypted` cast, role-limited, audited, auto-purged; demo warning | M10 |
| Login e-mail in `contacts` with a custom UserProvider | E-mail on `users`; standard Fortify provider | M1 |
| CSRF disabled for the chatbot; TLS verification off | Arch tests forbid CSRF exceptions (one documented allow-list entry: the Mollie webhook) and `withoutVerifying()` | M0, M11, M12 |
| Prompts sent to a third-party AI without notice | Opt-in, notice, no personal data, feature flag off by default | M12 |
| Default DB passwords; demo credentials in the README | `${VAR:?}` secrets, no defaults. Demo credentials **are** published now, but deliberately: they exist only when `DEMO_MODE` is on, are locked (no credential changes), can't log in when demo mode is off, have only their role's rights, and the owner's `super_admin` is a separate, never-published account with 2FA (ADR-0006) | M0, M1, M6 |
| Developer checkboxes (`dev_connection`, `simulate_error`) in production | Error states tested with fakes, never with UI switches. Demo mode is the opposite of those checkboxes: one documented env flag, off by default, owner-approved, tested on and off, and it never weakens security (ADR-0006). The simulated payment provider is a configured adapter, not a form switch | M0, M1, M4, M6 |
| No rate limiting, security headers or audit log | Headers/CSP in M0; rate limits in M1 (auth), M6 (registration/mail budget/bot protection), M8 (contact); audit log from M1, viewer in M7 | M0, M1, M6, M7, M8 |
| No GDPR features (export, deletion, retention, privacy statement) | Privacy statement, export and erasure before public exposure; retention jobs and passport handling later; nightly wipe on the demo | M6, M10 |
| Airports duplicated for every trip | Airport reference data with unique IATA; trips reference it | M2 |
| Trips had no price; price typed by hand; offers never applied | Base price on trips; pricing service applies offers server-side | M2, M4, M9 |
| Accounts and Customers: two CRUDs over the same data | One customer module | M7 |
| Booking search on a column that doesn't exist | Feature tests for every filter; Larastan | M7 |
| Money as decimal; VAT by hand; no single VAT source | Integer cents + EUR; VAT 21 % in one config value; invoice totals from lines | M4, M5 |
| No booking flow for customers | Customer booking wizard with payment | M4 |
| Messages limited to 25 characters; list not scoped | Sensible limit; policy-scoped conversations | M8 |
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

All items in `legacy-analysis.md` §2, §3 and §4 are placed. Nothing is dropped.

## 6. Cross-cutting tracks (apply to every milestone)

| Track | Rule in every story | Owner of the standard | Gate |
|---|---|---|---|
| **i18n nl/en** | No hard-coded UI strings; nl is the default; dates/money via `Intl` (SSR-consistent); times in the airport's time zone with a label; mails in the recipient's language | frontend-developer | CI missing-key check; reviewers |
| **WCAG 2.2 AA** | Design approved before build; React Aria for complex widgets; keyboard path and screen-reader announcements in the ACs; 320 px reflow / 400 % zoom | accessibility-auditor | axe in Playwright (CI); auditor verdict per UI story and per milestone |
| **GDPR / privacy** | Minimum data; every new personal-data field in `data-inventory.md` with purpose, basis and retention; **every story that adds personal data extends the export, erasure and nightly-reset tests (from M6 on) and is protected from first storage** (policies, no logs, no serialisation by default); server-side masking of visitor data for all staff except super_admin in demo mode | security-privacy-reviewer | Reviewer verdict |
| **Security (ASVS L2)** | Authorization AC in every data story; Form Request validation; no mass assignment; rate limits on public writes; secrets only from env; audit sensitive actions; public from M6, so every milestone gets a security review | security-privacy-reviewer | Arch tests, `composer`/`npm audit`, Trivy, reviewer verdict |
| **Demo mode** | Any behaviour that differs in demo mode goes through the `DemoMode` service, is listed in ADR-0006, and is tested on **and** off | backend-developer | Arch test; qa |
| **Testing** | Unit for rules, feature per endpoint (happy, validation, unauthenticated, unauthorized, not found), e2e per journey with axe on the SSR build; deterministic (frozen clock, fakes, fake payment and mail providers); bug fixes start with a failing test | qa-engineer (`docs/testing.md`) | CI blocks the image publish |
| **Performance** | SSR-safe, code-split pages; responsive images; indexed queries checked with `EXPLAIN`; no N+1 (`preventLazyLoading` in dev/test) | frontend / database | Lighthouse gate on public pages |
| **Deployability** | `main` is always deployable; new env vars go into `.env.example` and the deploy docs; migrations are reversible and safe to run on start | devops-engineer | Milestone redeploy to the home server |

## 7. Cross-cutting architecture (baseline for all stories)

These are the default constraints stories refer to. Items marked *ADR* get their own record when decided.

- **Authentication:** Fortify, session cookies (Secure, HttpOnly, SameSite=Lax), e-mail verification required
  before booking or messaging, TOTP 2FA **required for manager, admin and super_admin** and optional for employee and
  customer (Q8), login throttling, and no "remember me" for staff accounts. Demo accounts: see ADR-0006.
- **Authorization:** one `role` enum on `users`; Laravel Policies for every model, Gates for non-model abilities;
  every controller action is authorized (a Pest arch/feature check). The client receives `can` flags for display
  only. Hiding a button is never the access control. Demo mode never grants extra rights; it only removes some
  (credential changes, invites, unmasked visitor data). Visitor-data masking is applied server-side in the
  data objects, so masked values never reach the browser (ADR-0006 D6).
- **Request/API shape:** Inertia pages, server-rendered (SSR); no public JSON API. A few typed JSON endpoints for
  dependent search options, with rate limits; the Mollie webhook (M11). Data to the client goes through
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
  periods, mail budget, `DEMO_MODE`, `PAYMENT_PROVIDER`, `chatbot.enabled`) in `config/traveleasy.php`;
  `.env.example` documents all of them.
- **Logging:** JSON to stdout in production, with a request ID; no personal data or secrets in log lines; security
  events go to the `AuditLog` table, not the app log.
- **Background jobs:** database queue driver; `worker` and `scheduler` services run from the same image; mails,
  exports and PDFs are queued; jobs are idempotent and retried with backoff. The nightly demo reset is a
  scheduled command at 03:45 Europe/Amsterdam (the scheduler's time zone; stored times stay UTC), registered
  only in demo mode.
- **File storage:** a private local disk on a named volume (`storage`): invoice PDFs (if stored) and data exports.
  Downloads go through policy-checked routes or signed, expiring URLs. Public assets (incl. demo hotel images) are
  built into the image; no user uploads in v1 (accepted).
- **Services in production:** `app` (web), `ssr`, `worker`, `scheduler`, `db`, `backup`. Only `app` and `ssr` (if
  ADR-0002 needs it) are on the NPM network.
- **ADRs:** numbered in the order they're written, with 0002–0005 reserved for the decisions planned below.
  Written: 0001 tech stack, **0006 demo mode** (the next free number after the reserved ones). Expected: 0002 production runtime + SSR packaging (M0), 0003 payment-provider
  interface (M4), 0004 PDF library (M5), 0005 transactional mail: **Gmail SMTP, decided by the owner** (written in M6 to record the setup), 0007 bot protection (M6), 0008
  package domain model (M9), then the chatbot provider (M12).

### Role matrix (accepted, Q7)

Hierarchy: `employee` ⊂ `manager` ⊂ `admin` ⊂ `super_admin` (each has everything of the role before it). "Own" =
records linked to the user's customer profile. In demo mode, the published demo accounts have exactly their role's
rights, minus credential changes, invitations and unmasked visitor data (ADR-0006).

**`super_admin`** is the owner's private account: never published, never a demo account, created only by the artisan
command or the `reference` seeder from env, never through the UI, and kept by the nightly reset. It deliberately holds
only three things that `admin` doesn't:
1. managing `admin` accounts (invite, promote to or demote from admin, deactivate);
2. inviting staff while demo mode is on;
3. seeing visitor personal data unmasked while demo mode is on.

Everything else it can do, an admin can do too. No role (including super_admin) can create, promote to, demote, deactivate or delete a
super_admin through the UI.

| Resource / action | Guest | Customer | Employee | Manager | Admin | Super admin |
|---|---|---|---|---|---|---|
| Search, trips, packages, offers, destinations, about, contact | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Own profile, password, 2FA, export | – | ✔ | ✔ | ✔ | ✔ | ✔ |
| Delete own account | – | ✔ | ✔ | ✔ | ✔ | – (server only) |
| Book a trip or package | – (account at checkout) | own | on behalf of a customer | on behalf | on behalf | on behalf |
| Bookings: view | – | own | all | all | all | all |
| Bookings: change status / cancel | – | own, within policy | ✔ | ✔ | ✔ | ✔ |
| Invoices: view / download | – | own | all | all | all | all |
| Invoices: mark paid, credit note | – | – | mark paid | ✔ | ✔ | ✔ |
| Customers: view / edit contact data / note | – | – | ✔ | ✔ | ✔ | ✔ |
| Customers: deactivate | – | – | – | ✔ | ✔ | ✔ |
| Passport data (M10): view | – | own travellers | ✔ (audited) | ✔ (audited) | ✔ (audited) | ✔ (audited) |
| Conversations | – | own | staff inbox | staff inbox | staff inbox | staff inbox |
| Trips, airports, hotels, extras, packages, offers: create / edit / status | – | – | view only | ✔ | ✔ | ✔ |
| Dashboard | – | – | basic | + revenue/occupancy | + revenue/occupancy | + revenue/occupancy |
| Staff accounts: invite, role change, deactivate `employee`/`manager` | – | – | – | – | ✔ (not in demo mode) | ✔ (also in demo mode) |
| Staff accounts: manage `admin` accounts | – | – | – | – | – | ✔ |
| Visitor personal data in demo mode | – | – | masked | masked | masked | **unmasked** |
| Audit log | – | – | – | – | ✔ | ✔ |
| 2FA | – | optional | optional | **required** | **required** | **required** (never exempt) |

## 8. Release and deploy cadence

| When | What runs on the home server | Reachable from |
|---|---|---|
| End of M0 | `v0.0.x` walking skeleton (SSR shell, error pages, health check) | **LAN only** (first deploy) |
| End of M1–M5 | `v0.N.0` per milestone; `v0.5.0` = full search → book → invoice slice | LAN only |
| End of M6 | `v0.6.0` public demo with demo mode, nightly reset and real e-mail | **Public** on the owner's subdomain through NPM over HTTPS |
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
| Q5 | Public registration? | Open customer self-registration; staff only by admin invite. | M1, M6 (abuse protection) |
| Q6 | Booking scope? | As assumed: one trip per booking, no seat selection, children pay the adult price, no infants. | M4 |
| Q7 | Role matrix? | As proposed. | § 7 |
| Q8 | 2FA? | Required for manager and admin, optional for employee and customer. **`DEMO_MODE`** (on for the live demo, off by default): only the published demo accounts are exempt from mandatory 2FA; they can't change password, e-mail or 2FA; the nightly reset restores them. An explicit, documented, owner-approved feature. | M1 (2FA, demo-mode core), M6 (public parts), ADR-0006 |
| Q9 | VAT? | 21 % in one config value. | M4 |
| Q10 | Cancellation policy? | As assumed: free self-cancel until 14 days before departure; after that, only staff. | M4, M5 |
| Q11 | Production mail? | A **real provider** on the live demo (owner's subdomain, SPF/DKIM) so verification and reset mails arrive; Mailpit in dev; provider chosen in an ADR before go-live. Plan rate limits and self-hosted/privacy-friendly bot protection against abuse. | M6 (ADR-0005 mail, ADR-0007 bot protection, mail budget). Refined by N7 in § 10: Gmail, no subdomain SPF/DKIM. |
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
| N1 | Accept the ADR-0006 change list? | **Accepted** (2026-10-11), with the changes below folded in. Plus a new fifth role, **`super_admin`**: the owner's private account, above `admin`, never published, never a demo account, created only by an artisan command or the `reference` seeder from env, never through the UI, kept by the nightly reset. Only super_admin can manage admin accounts, invite staff in demo mode and see unmasked visitor data. | ADR-0006 (Accepted); M1; § 7 role matrix |
| N2 | Visitor data and the public demo staff accounts | Registration stays **open** in demo mode. Visitors' personal data (at least name and e-mail, plus any other visitor PII on staff screens) is **masked for all demo accounts**, including demo admin and demo manager, whatever the address. Only super_admin sees it unmasked. No "test vs real" address detection. Masking happens **server-side**; masked values never reach the browser. | ADR-0006 D6; M6, extended in M7, M8, M10 |
| N3 | Staff invitations in demo mode | **Disabled for every demo account.** Only super_admin can invite, also in demo mode. | ADR-0006 D8; M1; M6 |
| N4 | Package definition | Accepted as assumed: outbound + return flight always included, nights fixed by the package, price computed from the parts minus an optional package discount, extras = transfer, extra luggage, excursion. | M9, ADR-0008 |
| N5 | Hotel images | Hotel photos ship with the app; no uploads. | M9 |
| N6 | Nightly reset time and kept accounts | **03:45 server time** (Europe/Amsterdam). Not 04:00: the owner has other jobs at 04:00 and 04:15. The DB dump runs after the reset; it doesn't have to finish before 04:00, but it must not collide with the owner's jobs (devops sets the exact time with that constraint). Everyone is logged out; **super_admin is kept**. | ADR-0006 D10; M6 |
| N7 | Mail provider | **Gmail**, set up by the owner: SMTP with an app password held only in env; the mail budget stays below Gmail's daily limit; From = the Gmail address (or a verified "send as" alias); custom-domain DKIM would need Workspace, so the subdomain SPF/DKIM/DMARC items are dropped; recipients see the owner's address. | M6 (ADR-0005 records the setup) |

## 11. New questions for the owner

These came out of the follow-up answers. Each has a working assumption; stories that depend on them will be written
with the assumption unless the owner says otherwise.

| # | Question | Needed by | Working assumption |
|---|---|---|---|
| Q-A | **Gmail keeps copies.** Every mail sent through Gmail SMTP is kept in the account's "Sent" folder, and bounces and replies land in the inbox. Those contain visitor e-mail addresses (and verification/reset links), and **they survive the nightly reset**, so "deleted within 24 hours" wouldn't be true for them. Options: (a) you set up a daily clean-up in the Google account (e.g. a Google Apps Script that deletes Sent/bounce mail older than 1 day); (b) you delete them by hand on a schedule; (c) the privacy statement says mail copies are kept in the mailbox for N days. Which? | M6 | (a) a daily clean-up script in the Google account, and the privacy statement mentions it |
| Q-B | **Dedicated Gmail account?** If the demo sends from your personal Gmail and Google throttles or suspends sending (limit exceeded, spam reports from abuse), your personal mail is affected too, and every visitor sees your personal address. A separate account (e.g. a TravelEasy demo address) avoids both. | M6 | A dedicated Gmail account for the demo, with 2-Step Verification on |
| Q-C | **Masking for non-demo staff.** You said only super_admin sees visitor data unmasked. I've applied that to **every** staff account other than super_admin in demo mode, including any real (non-demo) employee/manager/admin you might invite, not only the four demo accounts. OK? | M6 | Yes: in demo mode everyone except super_admin sees masked data |
| Q-D | **Other real staff on reset.** The reset keeps super_admin. If you invite real (non-demo) staff on the live demo, should they also survive the reset, or be wiped with everything else? | M6 | Wiped; only super_admin is kept |
| Q-E | **Your 04:00 and 04:15 jobs.** What are they (e.g. a server or volume backup, updates, a restart)? If they back up Docker volumes or restart containers, devops needs to know, to avoid contention and to make sure they capture the clean post-reset state. | M6 | Unrelated to TravelEasy; the reset and dump just must not overlap them |

Things the owner should know (no decision needed):
- A Google **app password needs 2-Step Verification** on the Google account. It gives full mail access to that
  account, so it must stay only in the server's env (Portainer stack variables), and it can be revoked in the Google
  account at any time.
- Google is a **processor outside the EU** for the demo's outgoing mail (recipient addresses and mail content). The
  privacy statement and data inventory will say so.
- Creating the super_admin **from env** means its password sits in the stack's env vars. The safer path is the
  artisan command; if the env seeder is used for the first boot, remove `SUPER_ADMIN_PASSWORD` from the stack
  afterwards and change the password at first login. The deploy docs will say this.
