---
id: TE-001
title: Scaffold the Laravel React starter kit (strict TS, PostgreSQL, no mail)
status: done
priority: must
epic: M0 Project scaffold and tooling
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer of TravelEasy, I want a clean Laravel + Inertia + React + TypeScript codebase that is wired to
PostgreSQL and has no mail-based features, so that every later story starts from the accepted stack instead of
the legacy mistakes.

## Context
- First story of milestone M0 (`docs/plan.md` § 4 M0, epic "Project scaffold and tooling"). Stack: ADR-0001.
- Legacy mistakes this prevents (`docs/legacy-analysis.md` § 3 Engineering): `minimum-stability: dev`, no type
  safety across the PHP/TS boundary, hard-coded URLs.
- ADR-0005 (no outgoing e-mail): the starter kit's e-mail verification and mail-based password reset are removed
  here. The replacement (recovery codes) is M1, not this story.
- **Depends on:** nothing. **Blocks:** TE-002 (the dev image installs these dependencies), and through it TE-003,
  TE-004, TE-005, TE-006.
- **No host PHP/Node needed.** Generate the project with throwaway containers (e.g. the official `composer` /
  `php` and `node` images with the repo bind-mounted). The Docker dev environment itself is TE-002, so the
  runtime checks (app boots against PostgreSQL, starter tests green against PostgreSQL) are verified in TE-002.
- **The repo is not empty.** `composer create-project` / `laravel new` can't target it directly: scaffold into a
  temporary folder and merge. The existing `.gitignore`, `.gitattributes`, `CLAUDE.md`, `docs/` and `.claude/` must
  be kept (merge the starter kit's ignore rules into ours, don't overwrite).
- **Starter-kit pages are temporary placeholders.** Welcome, login, register, dashboard and settings pages are
  kept as shipped (apart from the removals below). There is no design gate and no accessibility polish on them;
  the app shell, design system and i18n epics replace them later.
- Starter-kit auth: recent kit versions use Fortify. If the installed version uses its own auth controllers
  instead, the "Fortify feature off" criteria mean "the equivalent routes, controllers, pages and tests are
  deleted". Record which variant you found in the handoff.

## Acceptance criteria
- [x] AC1: Given the repo after this story, then it contains the official Laravel React starter kit (latest stable
      Laravel and kit at scaffold time) at the repo root, and `CLAUDE.md`, `docs/`, `.claude/`, `.gitattributes`
      and the original `.gitignore` rules are unchanged or only extended.
- [x] AC2: `composer.json` has `"minimum-stability": "stable"` and `"prefer-stable": true`, and
      `composer validate --strict` exits 0.
- [x] AC3: `tsconfig.json` has `"strict": true`, `"noUncheckedIndexedAccess": true` and
      `"noFallthroughCasesInSwitch": true`, and `npx tsc --noEmit` exits 0 on the scaffold.
- [x] AC4: Tailwind CSS (the kit's version), `react-aria-components`, `spatie/laravel-data`,
      `spatie/laravel-typescript-transformer` and Laravel Wayfinder (`laravel/wayfinder` + its Vite plugin) are
      installed as regular (non-dev) dependencies, at stable versions.
- [x] AC5: Type generation works end to end: the shared Inertia prop `auth.user` is built from a laravel-data
      object (e.g. `App\Data\SharedUserData`, exposing only the fields the placeholder pages use: id, name, email,
      plus avatar if the kit uses it), the TypeScript type for it is **generated** by typescript-transformer (one
      documented command, e.g. `php artisan typescript:transform`), and the hand-written `User` interface in
      `resources/js/types` is replaced by the generated type. The password hash, remember token and 2FA columns
      are never in the generated type or the Inertia payload.
- [x] AC6: Wayfinder is used for routes: no Ziggy dependency, and the placeholder pages build links and form
      actions from Wayfinder imports, not hard-coded URL strings. (A grep for `href="/` and `route(` in
      `resources/js` finds no route URLs, apart from external links.)
- [x] AC7: Whether the Wayfinder and typescript-transformer output is committed or generated is decided once and
      documented in the handoff. If it is generated, a single command (e.g. `npm run generate`) produces all of it,
      so `tsc --noEmit` passes on a fresh clone without starting the Vite dev server. Generated folders that aren't
      committed are in `.gitignore`.
- [x] AC8: The database default is PostgreSQL: `config/database.php` default and `.env.example` use
      `DB_CONNECTION=pgsql` with `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=traveleasy`, `DB_USERNAME=traveleasy`
      and an **empty** `DB_PASSWORD=`. `database/database.sqlite` is deleted and no config or script creates it.
      The starter kit's migrations contain no SQLite- or MySQL-specific SQL.
- [x] AC9: The application time zone stays UTC (`config('app.timezone') === 'UTC'`).
- [x] AC10 (mail off, ADR-0005): `config/mail.php` has `'default' => 'array'` as a **fixed value, not read from
      env**, and its `mailers` array contains only `array`. `.env.example` has no `MAIL_*` variables. Setting
      `MAIL_MAILER=smtp` in `.env` still gives `config('mail.default') === 'array'`.
- [x] AC11 (no e-mail verification): Fortify's `emailVerification` feature is off; `User` does not implement
      `MustVerifyEmail`; no route uses the `verified` middleware; the verify-e-mail page, routes, controller,
      notification and tests are deleted. Given a user who just registered, when they open `/dashboard`, then they
      see the placeholder dashboard (no redirect to a verification notice).
- [x] AC12 (no mail-based password reset): Fortify's `resetPasswords` feature is off; the forgot-password and
      reset-password pages, routes, controllers and tests are deleted; the login page has no "Forgot password?" /
      "Wachtwoord vergeten?" link. `GET /forgot-password` and `GET /reset-password/{token}` return 404.
      The `password_reset_tokens` table migration is removed (M1 adds its own recovery-code storage).
- [x] AC13: The other starter-kit auth features (register, login, logout, profile edit, password change, account
      deletion, and 2FA if the kit ships it) are left as shipped. M1 reworks them.
- [x] AC14: In non-production environments Eloquent is strict: `Model::shouldBeStrict(! app()->isProduction())`
      (or the equivalent `preventLazyLoading` + `preventSilentlyDiscardingAttributes` + `preventAccessingMissingAttributes`)
      is set in a service provider (`docs/plan.md` § 6 Performance).
- [x] AC15: `npm run build` exits 0 (client bundle). The starter kit's SSR entry (`resources/js/ssr.tsx`) and
      `build:ssr` script are **kept untouched** for the SSR epic; they don't have to work yet.
      *Clarified at close:* the current kit ships no `ssr.tsx`; with `@inertiajs/vite` v3, `resources/js/app.tsx`
      is also the SSR entry. That entry and the kit's build scripts were left untouched.

## Quality criteria
- [x] Tests: the remaining starter-kit tests (converted to Pest if the kit offers a Pest option) pass. Running them
      against PostgreSQL is verified in TE-002/TE-004; here, run them however is quickest and say how in the handoff.
- [x] Accessibility: not assessed in this story; placeholder pages only (see Context).
- [x] Security/privacy:
  - `.env` is git-ignored; `.env.example` contains **no secrets and no default passwords** (`APP_KEY=` and
    `DB_PASSWORD=` empty), and every variable in it has a one-line comment.
  - `APP_DEBUG=true` appears only as the dev default in `.env.example`, with a comment that production sets
    `false`.
  - No `MAIL_*`, SMTP, Mailpit or third-party API variables anywhere.
  - `composer audit` and `npm audit --audit-level=high` report no High/Critical advisories, or each one is listed in
    the handoff with a reason.
  - No runtime CDN: the kit's font link (e.g. `fonts.bunny.net`) is removed from the Blade root template; system
    fonts are fine until the design system picks one (CLAUDE.md: no third-party fonts).
- [x] i18n: not in scope (i18n infrastructure is a later M0 epic); no new UI strings are added.
- [x] Performance: not relevant.
- [x] Docs updated: the handoff lists the exact versions installed (PHP, Laravel, Inertia, React, TypeScript,
      Tailwind, React Aria Components, laravel-data, typescript-transformer, Wayfinder, Node used) and the generation
      command(s). TE-006 puts them in the docs and `CLAUDE.md`.
- [x] Docker rebuild: n/a (no Docker environment yet).

## Out of scope
- Docker files and compose (TE-002); linters and static analysis (TE-003); Pest arch tests and the PostgreSQL test
  database (TE-004); Vitest and Playwright (TE-005); docs (TE-006).
- Recovery codes, roles, 2FA policy, demo mode, `config/traveleasy.php` (M1).
- SSR working end to end (SSR epic), app shell, design system, i18n, CI, production image.
- Restyling or translating the starter-kit pages.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-10-11. qa-engineer: PASS (67/67 Pest tests, 234 assertions, every AC verified, including a fresh-clone
run). security-privacy-reviewer: PASS (no finding at Medium or above). Commits: 35dd478, 7825e8b, ae055a9.

**Added**
- Laravel 13.35 React starter kit (Fortify 1.41, Inertia 3) at the repo root, on PostgreSQL.
- `App\Data\SharedUserData` for the shared `auth.user` prop. Its TS type is generated by typescript-transformer.
- `npm run generate` produces the Wayfinder and typescript-transformer output. The generated output is git-ignored.
- Wayfinder route helpers are used in the placeholder pages.
- `react-aria-components`, `spatie/laravel-data`, `spatie/laravel-typescript-transformer`, `laravel/wayfinder`.
- Tests: `NoMailFlowsTest`, `SharedUserDataTest`, `ConfigDefaultsTest`, and QA's `ScaffoldContractTest`.
- `docs/privacy/data-inventory.md`, a draft by the security-privacy-reviewer.

**Changed**
- Mailer fixed to `array` in `config/mail.php`, with a runtime pin, so env can't override it (ADR-0005).
- TypeScript `strict` plus `noUncheckedIndexedAccess` (and `noFallthroughCasesInSwitch`).
- npm is the package manager.
- `vite` is aliased to `@voidzero-dev/vite-plus-core@0.3.3`, because vite-plus 0.3.3 needs it. Side effect:
  `npm audit` can't see Vite advisories (follow-up in TE-003).
- `/settings` changed from `Route::redirect` to `Route::get`, to work around a Wayfinder/QUERY-verb bug.
- Font stack: the bunny font plugin is removed, so the system font stack is used (no third-party fonts).

**Removed**
- E-mail verification: the Fortify feature, `MustVerifyEmail`, the `verified` middleware, the pages, routes and tests,
  and the `email_verified_at` column.
- Mail-based password reset: the Fortify feature, the forgot/reset pages, routes and tests, and the
  `password_reset_tokens` migration. The broker entry in `config/auth.php` is still there (M1 follow-up on the board).
- `pnpm-workspace.yaml`, `database/database.sqlite`, and the font CDN plugin.

**Migrations**
- The kit's base migrations, adapted: no `email_verified_at` and no `password_reset_tokens`. No production data
  exists yet.

**Env vars**
- `.env.example`:
  - `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=traveleasy`, `DB_USERNAME=traveleasy`
  - `DB_PASSWORD=` and `APP_KEY=` are left empty
  - no `MAIL_*` variables

**Versions**

| Component | Version |
|---|---|
| PHP | 8.5.11 (the lock was resolved on 8.5) |
| Laravel | 13.35.0 |
| Fortify | 1.41.0 |
| laravel/passkeys | 0.2.1 |
| inertia-laravel | 3.5.1 |
| laravel-data | 4.23.0 |
| laravel-typescript-transformer | 3.3.0 |
| Wayfinder | 0.1.21 (PHP), 0.1.10 (Vite plugin) |
| Pest | 5.3.1 |
| Node | 24.21.0 |
| npm | 11.19.0 |
| React | 19.3.0 |
| TypeScript | 5.9.3 |
| Tailwind | 4.3.3 |
| react-aria-components | 1.22.1 |
| @inertiajs/react | 3.9.1 |
| vite-plus | 0.3.3 |

**Docker rebuild:** not applicable, because there is no Docker environment yet. TE-002 builds the dev image from these
lockfiles.

**Follow-ups filed**
- TE-002: the dev image, APP_KEY, no auto-seed, clean-up of the throwaway volumes and image.
- TE-003: formatter scope, `laravel/sail`, generated-folder ignores, the Vite audit gap.
- TE-004: a broader mail rule, and the AWS/SQS config leftovers.
- TE-006: the MIT `LICENSE`.
- M1: the items in the "Follow-ups for M1" section of `docs/stories/README.md`.
