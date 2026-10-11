# Tech-stack options for the TravelEasy remake

Status: **decided**: option 1 was accepted on 2026-10-11 (see `docs/adr/0001-tech-stack.md`). This document keeps the reasoning.
in `docs/adr/0001-tech-stack.md` and `CLAUDE.md` gets the concrete stack and commands.
Exact versions are pinned at scaffolding time, to whatever is current and stable then.

## TL;DR

| Rank | Option | Verdict |
|---|---|---|
| 🥇 1 | **Laravel + Inertia + React + TypeScript + PostgreSQL** | **Recommended.** Best fit for this project and for you. |
| 🥈 2 | Next.js (full-stack TypeScript) + PostgreSQL | The strongest alternative if you want to learn the JS/TS ecosystem; you'd assemble more yourself. |
| 3 | Laravel + Livewire | Fastest to build in, but weaker for the complex accessible widgets this app needs. |
| 4 | Laravel API + separate React SPA | Two apps for no gain unless a mobile app is planned. |
| 5 | NestJS API + React SPA | Most boilerplate; everything a framework would give you, you build yourself. |

## What the project needs (the criteria used below)

From `CLAUDE.md` and `docs/legacy-analysis.md`:
- **Solo developer** who already knows Laravel; maintainable for years by one person.
- **Lots of "boring" features**: authentication with e-mail verification, password reset and 2FA for staff;
  role-based **authorization on every record**; validation; transactional **mail** in two languages; **queues**;
  **scheduled jobs** (retention/anonymisation, backups); **PDF invoices**; file uploads; audit log.
- **Complex, accessible UI widgets**: dependent search (departure → destination → date), date picker,
  traveller counter, sortable/filterable staff tables, a messaging thread, all at WCAG 2.2 AA in nl + en.
- **Booking integrity**: transactions and row locks against overbooking; money in cents.
- **Docker with hot reload** on Windows, and a small production footprint on an older office PC.
- **Security and privacy by default.**
- Easy for the **agent team** to work in: conventional, well-documented structure.

## Option 1: Laravel + Inertia + React + TypeScript + PostgreSQL ⭐ recommended

**What it is:** one Laravel application. Controllers return `Inertia::render('Bookings/Show', [...])`
instead of Blade views, and the pages are React + TypeScript components. There's no separate API to design,
version or secure: routing, auth, sessions, CSRF and validation stay in Laravel, while the UI is a modern
SPA-like React app. Laravel's official React starter kit (Inertia + React + TypeScript + Tailwind) is the starting point.

**Why it's the best fit**
- **Everything on the requirements list is built in:** auth (Fortify: verification, reset, **2FA**),
  **Policies** for per-record authorization (the original's biggest hole), Form Requests, Mail + Markdown mail,
  Queues, the Scheduler, the Encrypter / `encrypted` casts (passport data), Notifications, Storage, rate limiting,
  and localisation. None of that has to be chosen, glued together or kept in sync.
- **Your Laravel knowledge carries over**, so the learning goes into doing it *properly* (policies, services,
  tests, a11y) instead of into relearning auth and ORMs. That's the stated goal: "as good as possible".
- **React gives access to the best accessible component libraries.** Recommended: **React Aria Components**
  (Adobe), the most thorough WCAG implementation available. It includes a fully accessible, localised date picker,
  combobox, number field and table with sorting, and handles i18n of dates and numbers. That covers exactly the
  hard widgets in this app.
- **End-to-end typing:** generate TypeScript types from PHP data objects (`spatie/laravel-data` +
  `typescript-transformer`) and typed routes (Laravel Wayfinder), so the agents can't drift between backend and frontend.
- **Testing is first-class:** Pest (unit/feature, `actingAs`, factories, `Mail::fake()`), Vitest for components,
  Playwright + axe for e2e/a11y.
- **Docker dev with hot reload is simple:** PHP picks up changes per request (no restart), and Vite gives
  instant HMR for React. Production is one app image (FrankenPHP, or Nginx + PHP-FPM) plus a queue worker and a
  scheduler from the same image. That's light enough for an office PC.
- **Agent-friendly:** strong conventions mean every agent knows where things go.

**Downsides**
- Two languages (PHP + TypeScript). You already know both.
- SEO for public pages needs Inertia SSR (an extra Node process) if search-engine ranking matters. That's
  optional; it can be added later.
- Real-time chat needs Laravel Reverb (self-hosted websockets) or polling. Start with polling and add Reverb later if wanted.

## Option 2: Next.js (full-stack TypeScript) + PostgreSQL 🥈

**What it is:** one TypeScript codebase for frontend and backend (React Server Components, Server Actions,
Route Handlers), with an ORM such as Drizzle or Prisma and an auth library such as Better Auth or Auth.js.

**Strengths:** a single language; the biggest frontend ecosystem and job market; React Aria works here too;
excellent SSR/SEO out of the box; very good learning value if you want to grow in TypeScript.

**Why it ranks below Laravel**
- **You assemble the framework yourself:** auth + 2FA, authorization (no policy system, so you write your own
  checks everywhere, which is exactly what went wrong in the original), validation (Zod), mail, **queues** (BullMQ
  + Redis, an extra container), **scheduling** (separate cron worker), encryption helpers, PDF. Every piece is
  a choice, an integration and a thing to keep updated.
- **The mental model is harder:** the server/client component boundary, caching and revalidation semantics, and
  Server Actions as public endpoints that must each be authorized. It's easy for a solo dev (and for agents) to get subtly wrong.
- **Self-hosting works** (standalone output in Docker) but the framework is optimised for Vercel. Some features
  and defaults assume that platform.
- Dev hot reload in Docker on Windows works, but needs polling and is heavier on RAM than Vite + PHP.

**Choose this instead if** learning modern TypeScript full-stack matters more to you than delivery speed and
built-in robustness.

## Option 3: Laravel + Livewire (+ Alpine)

**Strengths:** fastest way to build CRUD in Laravel; mostly PHP; no API and no TypeScript build complexity;
very good for the staff back office.

**Why it's lower:** the hard parts of this app are **complex accessible widgets** (dependent comboboxes, date
picker, traveller counter, live messaging). In Livewire these are hand-built with Alpine, or bought (Flux Pro), and
it's much harder to reach the WCAG 2.2 AA bar than with React Aria. The "TypeScript strict" principle mostly
doesn't apply. Network round-trips per interaction feel slower on mobile. And it's closest to the original, so
you'd learn the least.

## Option 4: Laravel API + separate React SPA

**Strengths:** a clean split, and the API is reusable if a mobile app is ever built.

**Why it's lower:** two applications, two deployments and two routers. Sanctum SPA auth and CORS
configuration, validation duplicated client-side, and an API contract to version. All of that is complexity
Inertia removes, with no benefit unless a mobile app is actually planned. You can still add API routes to
Option 1 later if that happens.

## Option 5: NestJS API + React SPA

**Strengths:** structured TypeScript backend (modules, DI, guards), one language.

**Why it's lowest:** it has the downsides of Option 4 (two apps) and of Option 2 (you assemble mail, queues,
scheduling and auth), with the most boilerplate of all. It's good at large teams and microservices; that's not this project.

## Considered and dropped
Django, Rails, ASP.NET and SvelteKit are all capable, but they mean a new language or ecosystem with no specific
advantage here, and fewer mature accessible component libraries (SvelteKit). They'd cost learning time
without making the product better.

## Scorecard (1–5, higher is better)

| Criterion | 1 Laravel+Inertia+React | 2 Next.js | 3 Livewire | 4 Laravel API+SPA | 5 NestJS+SPA |
|---|---|---|---|---|---|
| Fits your experience | 5 | 2 | 5 | 4 | 2 |
| Built-in auth/authz/mail/queue/scheduler | 5 | 2 | 5 | 5 | 2 |
| Accessible complex widgets | 5 | 5 | 2 | 5 | 5 |
| End-to-end type safety | 4 | 5 | 2 | 3 | 4 |
| Docker hot-reload dev experience | 5 | 3 | 5 | 4 | 3 |
| Light on a home server | 4 | 3 | 5 | 4 | 3 |
| Secure by default | 5 | 3 | 5 | 4 | 3 |
| Solo maintainability | 5 | 3 | 4 | 3 | 2 |
| Learning value | 4 | 5 | 2 | 3 | 4 |
| SEO for public pages | 3 (SSR optional) | 5 | 5 | 2 | 2 |
| **Total** | **45** | **36** | **40** | **37** | **30** |

Livewire scores well on paper, but it fails the criterion that matters most for this product's quality bar
(accessible complex widgets). That's why it's ranked third, not second.

## Supporting choices (with Option 1)

| Topic | Recommendation | Why / alternative |
|---|---|---|
| Database | **PostgreSQL** | Strict types, real `CHECK` constraints, `timestamptz`, partial indexes, good row locking for overbooking. MySQL 8/MariaDB would also work; Postgres is stricter, which suits "integrity in the DB". |
| UI components | **React Aria Components** + Tailwind CSS | Best-in-class a11y and i18n for date pickers and comboboxes. Alternative: shadcn/ui (Radix), which ships with the starter kit but has a weaker date picker and less i18n. They can be mixed. |
| Design tokens | CSS variables generated from the token file | One source for light/dark themes, used by Tailwind. |
| Types across the boundary | spatie/laravel-data + typescript-transformer, Laravel Wayfinder | No hand-copied types. |
| i18n | Laravel lang JSON files shared with React (e.g. a small `t()` helper or `laravel-react-i18n`) | One set of translation keys for nl/en. |
| Auth | Fortify (via starter kit) + 2FA for staff | Built in. |
| PDF invoices | spatie/laravel-pdf (Chromium) or dompdf | Chromium gives better layout but a bigger image; dompdf is lighter. Decide in the invoice story. |
| Mail (dev) | Mailpit container | Catches every mail locally. |
| Queue / scheduler | Database queue driver; worker + scheduler containers from the app image | No Redis needed at this scale; add Redis later if necessary. |
| Real-time messages | Polling first; Laravel Reverb later | Keeps v1 simple. |
| Tests | Pest, Vitest + Testing Library, Playwright + @axe-core/playwright | Covers unit to a11y. |
| Code quality | Pint, Larastan (PHPStan), ESLint, Prettier, `tsc --noEmit` | All enforced in CI. |
| Production web server | FrankenPHP (single container, built-in worker mode) or Nginx + PHP-FPM | The devops-engineer decides in an ADR; both sit fine behind Nginx Proxy Manager. |

## Decision needed from you
1. Which option: **1 (recommended)**, 2, or another?
2. Database: PostgreSQL (recommended) or MySQL/MariaDB?
