# TravelEasy (remake)

A solo rebuild of **TravelEasy**, a travel-agency web app first built as a team school project at
MBO Utrecht (original: `C:/Users/nimro/OneDrive/Bureaublad/MBO-Utrecht/TravelEasy`, also on GitHub at
`NimrodLobozar/TravelEasy`). The aim is to beat the original on every front: design,
functionality, accessibility, privacy, security and code quality. It runs in Docker and is self-hosted on
a **CasaOS home server via Portainer**.

Before planning or building anything, read `docs/legacy-analysis.md`. It lists every feature the original
had (all of them must come back, in better form) and every mistake that must not come back.

## Status

| Phase | State |
|---|---|
| Legacy analysis | Done (`docs/legacy-analysis.md`) |
| Agent team + workflow | Done (`.claude/agents/`, see § How work flows) |
| Tech-stack decision | Done: `docs/adr/0001-tech-stack.md` (Accepted) |
| Product plan / roadmap | Done: `docs/plan.md` (Accepted); ADR-0006 demo mode Proposed, open points in § 10 |
| Design system | Not started (`docs/design/`, links in `docs/design/previews.md`) |
| Scaffolding + Docker dev env | Not started |

Update this table when a phase changes.

## Decisions so far

- 2026-10-11: The **Orchestrator** is the default main agent (`.claude/settings.json`); all work flows through it.
- 2026-10-11: The server is an HP ProDesk office PC (x86-64), so images are built for `linux/amd64`.
- 2026-10-11: Public access later goes through the existing **Nginx Proxy Manager** on CasaOS.
- 2026-10-11: CI builds images and pushes them to **GHCR**; Portainer pulls a pinned tag.
- 2026-10-11: The AI chatbot is **nice-to-have and built last** (`docs/backlog/ideas.md`).
- 2026-10-11: Stack = **Laravel + Inertia + React + TypeScript + PostgreSQL** (ADR-0001).
- 2026-10-11: Public repo `NimrodLobozar/traveleasy-remake`; project lives at `C:\dev\traveleasy-remake` (outside OneDrive).
- 2026-10-11: The live site is a **public portfolio demo** (open customer registration, published demo accounts); first internet exposure at the end of M6 (`docs/plan.md`).
- 2026-10-11: **Demo mode** (`DEMO_MODE`, off by default) with locked demo accounts and a nightly reset is an owner-approved, config-driven exception to "no demo-only hacks" (`docs/adr/0006-demo-mode.md`).
- 2026-10-11: **Real flight + hotel packages** are in scope, as milestone M9 (`docs/plan.md`).
- 2026-10-11: **Inertia SSR** is on for SEO: an `ssr` Node process in dev, CI and production from M0 (`docs/plan.md` M0).
- 2026-10-11: The live demo sends **real e-mail** through a provider on the owner's subdomain (SPF/DKIM/DMARC; provider ADR in M6); Mailpit stays in dev.
- 2026-10-11: **ADR-0006 (demo mode) accepted**, with server-side masking of visitor data for all staff except super_admin (`docs/adr/0006-demo-mode.md`).
- 2026-10-11: Fifth role **`super_admin`**: the owner's private account, above `admin`; created only by an artisan command or from env, never published, never a demo account (`docs/plan.md` § 7).
- 2026-10-11: Mail provider = **Gmail SMTP** with an app password from env; the From address is the Gmail address, so there's no custom-domain SPF/DKIM/DMARC (this replaces the subdomain detail in the line above) (`docs/plan.md` M6, ADR-0005 in M6).
- 2026-10-11: The demo's **nightly reset runs at 03:45 Europe/Amsterdam**; the DB dump follows without overlapping the owner's 04:00/04:15 jobs (ADR-0006 D10).

## Tech stack

Accepted in `docs/adr/0001-tech-stack.md` (reasoning in `docs/stack-options.md`):
- **Backend:** Laravel (latest stable), started from the official React starter kit; Fortify auth, Policies,
  Form Requests, queues (database driver), scheduler.
- **Frontend:** Inertia + React + TypeScript (strict) + Tailwind CSS; **React Aria Components** for complex
  accessible widgets; types generated with spatie/laravel-data + typescript-transformer, routes with Wayfinder.
- **Database:** PostgreSQL.
- **Tests and quality:** Pest, Vitest + Testing Library, Playwright + @axe-core/playwright, Pint, Larastan,
  ESLint, Prettier, `tsc --noEmit`.
- **Dev extras:** Mailpit.

Exact versions and commands (install, dev, test, lint, build, migrate, seed) are added here when the project is scaffolded.

Fixed principles:
- Everything runs in Docker. `compose.yaml` deploys as a Portainer stack; `compose.dev.yaml` adds dev overrides.
- Relational database with versioned migrations. No business logic in stored procedures.
- TypeScript (strict) for frontend code.
- No runtime CDNs, third-party fonts or trackers; everything is bundled and self-hosted.
- Lint, typecheck and tests run in CI before an image is published.

When ADR-0001 is accepted, replace this section with the concrete stack, versions and commands.

## How work flows

The owner talks only to the **Orchestrator** (`.claude/agents/orchestrator.md`, which runs as the main
thread). Subagents can't launch other agents, so every handoff goes back through the orchestrator.

```
Owner → Orchestrator → task-writer → Orchestrator → [ux-ui-designer] → developers → Orchestrator
      → testers ──FAIL──▶ Orchestrator → task-writer (rework entry) → Orchestrator → developer → testers …
               └─PASS──▶ Orchestrator → task-writer (close story) → devops-engineer (Docker action if needed)
                         → Orchestrator → report to owner
```
- Stories live in `docs/stories/TE-NNN-*.md` (template in `task-writer.md`); the board is `docs/stories/README.md`.
- Testers: `qa-engineer` always; `accessibility-auditor` for UI changes; `security-privacy-reviewer` for auth,
  personal data, config or dependencies. Done means **all** of them return PASS.
- After 3 failed rework rounds on one story, the orchestrator stops and asks the owner.
- Design flow: Orchestrator → `ux-ui-designer` → Claude Design artifact → owner feedback via the orchestrator →
  revisions at the same link → approved. Every link is kept in `docs/design/previews.md`.

| Agent | Owns |
|---|---|
| `orchestrator` | Intake, routing, rework loop, Docker trigger, final report |
| `task-writer` | User stories, acceptance and quality criteria, rework log, changelog |
| `architect` | Stack choice, ADRs, roadmap, cross-cutting architecture |
| `ux-ui-designer` | Flows, design system, Claude Design previews, UX copy |
| `database-engineer` | Schema, migrations, seeders, indexes, retention jobs |
| `backend-developer` | Server logic, auth, authorization, business rules, mail |
| `frontend-developer` | UI, components, forms, i18n |
| `devops-engineer` | Docker, dev environment, CI/CD, GHCR, Portainer/CasaOS, backups |
| `qa-engineer` | Tests and the PASS/FAIL verdict |
| `accessibility-auditor` | WCAG 2.2 AA verdict |
| `security-privacy-reviewer` | Security/GDPR verdict and `docs/privacy/` |

## Docker dev workflow

Goal: **code changes appear in the browser without a rebuild.**
- Source is bind-mounted and the dev servers hot-reload (frontend HMR, backend auto-reload).
- `node_modules` / `vendor` live in **anonymous volumes** from the image, never on Windows/OneDrive.
- The database lives in a **named volume** and survives rebuilds.

| What changed | What to do |
|---|---|
| Source files (components, controllers, styles, …) | Nothing; just refresh, or let HMR do it |
| A migration | Run the migrate command in the running app container |
| `package.json`, lockfile, `composer.json`/lock, Dockerfile, compose, env vars | `docker compose up -d --build -V` (rebuild + fresh dependency volumes; DB kept) |
| Want an empty database | `docker compose down -v` (**deletes all data**; ask the owner first) |

Note: `-V` (capital, on `up`) renews the dependency volumes. `-v` (lowercase, on `down`) deletes **all**
volumes, including the database.

## Product principles

1. **Privacy by design (AVG/GDPR).** Collect the minimum. Passport/ID data is optional, encrypted at rest,
   visible only to staff who need it, and deleted after the trip. Users can export and delete their data.
   External services are opt-in and get no personal data.
2. **Accessible by default: WCAG 2.2 AA.** Semantic HTML, full keyboard support, visible focus, contrast,
   reduced motion, labelled forms with linked errors, works at 400% zoom. Part of "done", not a later pass.
3. **Secure by default.** Server-side authorization on every route and record (deny by default). Roles:
   `customer`, `employee`, `manager`, `admin`, `super_admin` (the owner's private account; never published, never
   created through the UI). Target OWASP ASVS L2. Rate limiting, CSP, secrets only from env.
4. **Customers can actually book.** Search → trip → travellers → (offer code) → review → confirm → invoice.
   Prices are computed server-side; the VAT rate lives in one place.
5. **Bilingual.** UI in Dutch (default) and English via i18n keys. Code, comments, commits and docs in English.
6. **Mobile-first, fast.** Responsive images, Lighthouse ≥ 90 in every category.

## Domain (target model, to be refined by the database-engineer)

`User` (email, password, role) · `Person` · `Customer` · `Employee` · `Airport` (IATA, name, city, country,
time zone; reference data, never duplicated) · `Trip` (flight number, from/to airport, departure/arrival
timestamps, capacity, base price, status) · `Offer` · `Booking` + `BookingTraveller` · `Invoice` +
`InvoiceLine` · `Conversation` + `Message` · `AuditLog`.
Money is stored as integer cents with a currency code; times are stored in UTC and shown in the airport's time zone.

## Repository layout

```
CLAUDE.md                   this file
.claude/agents/             the agent team
.claude/settings.json       makes the orchestrator the default agent
docs/legacy-analysis.md     what the original did and got wrong
docs/stack-options.md       stack comparison and recommendation
docs/adr/                   architecture decision records
docs/plan.md                roadmap and milestones (architect)
docs/stories/               user stories + board (task-writer)
docs/design/                specs, flows; previews.md = all design links
docs/backlog/ideas.md       nice-to-haves not yet planned (chatbot)
docs/privacy/               data inventory, retention, privacy statement
docs/deploy/                dev.md, casaos-portainer.md
```

## Conventions

- Git: `main` is always deployable; work on `feat/…`, `fix/…`, `chore/…` branches; use Conventional Commits.
- Every feature ships with tests; a bug fix starts with a failing test.
- No hidden test or demo toggles in production code (the original had `dev_connection` / `simulate_error`
  checkboxes in its forms). The one sanctioned exception is **demo mode** (`docs/adr/0006-demo-mode.md`): a
  documented, config-driven `DEMO_MODE` env flag, off by default, read only through the `DemoMode` service, tested
  on and off, and never weakening security.
- Seeders: `reference` (airports, roles; also used in production) and `demo` (fake data). `demo` runs in dev, and in
  production only when `DEMO_MODE` is on (the live demo, together with the nightly reset; ADR-0006).
- `.env.example` documents every variable; `.env` is never committed.
- When a decision is made, add one line to § Decisions so far and link the ADR.

## Deployment target

- CasaOS on an HP ProDesk (amd64), managed with Portainer. Stack = `compose.yaml` using a pinned GHCR image tag.
- Nginx Proxy Manager terminates TLS and forwards to the web container; the DB is never exposed on the host.
- Persistent named volumes for the DB and uploads, a scheduled DB backup, health checks on every service.
- Update = bump the tag in Portainer and redeploy; rollback = set the previous tag.
