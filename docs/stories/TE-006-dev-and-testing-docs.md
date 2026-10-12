---
id: TE-006
title: Developer docs (dev.md, testing.md) and exact stack versions in CLAUDE.md
status: ready
priority: must
epic: M0 Docker dev environment (also closes the docs part of "Project scaffold and tooling")
agents: [devops-engineer, qa-engineer]
testers: [qa-engineer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer (and any future contributor), I want one page that tells me how to start, use, rebuild and safely reset
the dev environment, and one page that explains how we test, so that I never have to rediscover a command or lose my
database by accident.

## Context
- `CLAUDE.md` § Docker dev workflow (the rebuild table and the `-V` vs `-v` warning) and § Tech stack ("Exact versions
  and commands are added here when the project is scaffolded"); `docs/plan.md` § 2 milestone DoD row 8 (docs current),
  § 6 Testing track, § 4 M0 risk "file watching on Windows" (document the WSL file-system option).
- **Depends on:** TE-001 to TE-005 (it documents what they built; their handoffs list the commands and versions).
- **Authors.** devops-engineer writes `docs/deploy/dev.md`. qa-engineer owns `docs/testing.md` (`docs/plan.md` M0
  agents). The qa-engineer also verifies both pages as tester by following them on a fresh clone.
- **CLAUDE.md is not edited by the developers.** The devops-engineer reports the exact versions and commands; the
  orchestrator (or architect) writes them into `CLAUDE.md` § Tech stack and updates the § Status row. The story is
  done only when that's in.

## Acceptance criteria
- [ ] AC1 (`docs/deploy/dev.md`, prerequisites): lists Windows 11, Docker Desktop with the WSL 2 backend, Git, the repo
      location outside OneDrive, and that **no** host PHP, Composer, Node or PostgreSQL is needed.
- [ ] AC2 (first run): numbered steps from clone to a working `http://localhost:8000`: copy `.env.example` to `.env`,
      set `DB_PASSWORD` (with a one-line way to generate a strong one), `docker compose up -d --build`,
      `key:generate`, `migrate`. QA follows them on a fresh clone and reaches the welcome page without extra steps.
- [ ] AC3 (daily commands): a table with start, stop, logs, status, artisan, composer, npm, Pest, Vitest, Playwright,
      `check`/lint/format/types and `psql` into the dev DB, each as a copy-pasteable `docker compose …` command that
      works in PowerShell.
- [ ] AC4 (when to rebuild): the `CLAUDE.md` rebuild table (source change → nothing; migration → migrate in the
      container; `package.json`/lockfiles/`composer.json`/Dockerfile/compose/env vars → `docker compose up -d --build -V`)
      is present and explains that `-V` renews the dependency volumes and keeps the database.
      *Added after TE-002 round 1:* `up -d --build -V` leaves the old anonymous dependency volumes behind (about 0.5 GB
      per rebuild). `dev.md` documents the clean variant `docker compose rm -s -f -v app vite` followed by
      `docker compose up -d --build` (the DB volume is untouched), plus how to list and remove dangling anonymous
      volumes safely. In the same edit as AC14, the orchestrator/architect updates the `CLAUDE.md` § Docker dev
      workflow table to match.
- [ ] AC4b (dangling volumes and images; found in the TE-002 re-test):
  - **What to document.** `dev.md` explains that plain `docker compose down`, not only `up -V`, leaves the `app`/`vite`
    anonymous volumes dangling, and that every rebuild leaves the previous `traveleasy-remake-dev:local` image
    dangling (about 1.4 GB).
  - **Clean-up command.** It gives a clean-up that only targets this project: for example
    `docker image prune --filter label=com.docker.compose.project=traveleasy-remake`, if the image carries that label
    (verify it), and an equivalent project-filtered command for the anonymous volumes. Otherwise it documents a safe
    alternative that lists and removes only this project's resources by name or label.
  - **Never global.** It never suggests a global `docker system prune`, `docker volume prune` or `image prune -a`.
    The named DB volume is never matched by the commands.
  - **Check.** QA runs the commands and confirms the dev DB data survives and that resources of other projects are
    untouched.
- [ ] AC4a (secrets in `docker compose config`): `dev.md` warns that `docker compose config` prints resolved values
      including `POSTGRES_PASSWORD` in plain text. Its output must never be pasted into docs, issues or CI logs; use
      `docker compose config --quiet` to only validate.
- [ ] AC5 (safe reset): a clearly marked warning (a `> **Warning**` block) that `docker compose down -v` **deletes all
      volumes including the database**, that the owner is asked first, and the safer alternatives
      (`php artisan migrate:fresh --seed` in the container for an empty schema; `down` without `-v` to stop).
- [ ] AC6 (test database): explains that `traveleasy_test` is created only on a fresh DB volume and gives the command to
      create it on an existing one.
- [ ] AC7 (troubleshooting): covers at least: HMR not updating (polling env vars), port 8000/5173 already in use
      (`APP_PORT`/`VITE_PORT`), slow file access on bind mounts and the option of cloning into the WSL file system
      (with its trade-offs), CRLF line-ending errors in scripts, and the Playwright image/version mismatch.
- [ ] AC7a (database GUI): `dev.md` documents Adminer from TE-002 (AC23–AC27): that it is dev-only and opt-in, how to
      start and stop it (`docker compose --profile tools up -d` / `docker compose --profile tools rm -s -f adminer`;
      not `stop adminer`, because a stopped profile container fails to start after `down` recreates the network), the URL
      (`http://localhost:8081`, `ADMINER_PORT`), and the login values (system PostgreSQL, server `db`, and
      `DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE` from `.env`; no stored credentials).
- [ ] AC8 (no mail): states that there is no mail catcher because the app sends no e-mail (link ADR-0005).
- [ ] AC9 (`docs/testing.md`, strategy): describes the layers (Pest unit, Pest feature, Pest arch, Vitest component +
      axe, Playwright e2e + axe) with what belongs in each, where the files live, naming conventions, and the rules from
      `docs/plan.md` § 6: feature test per endpoint (happy, validation, unauthenticated, unauthorized, not found), an
      authorization test for every route/record, demo-mode on/off tests from M1, deterministic tests (frozen clock,
      fakes; no mail to fake), bug fixes start with a failing test.
- [ ] AC10 (testing.md, arch rules): lists every arch rule from TE-004 with the legacy mistake or ADR it prevents.
- [ ] AC11 (testing.md, databases and safety): explains the PostgreSQL test database, the `_test` guard, and that tests
      never use SQLite.
- [ ] AC12 (testing.md, accessibility): explains the axe helper, its WCAG tags, the placeholder allow-list rule (must be
      empty after the App shell epic) and that axe catches only part of WCAG, so the accessibility-auditor's manual
      review is still required.
- [ ] AC13 (testing.md, commands and gates): every test and quality command, and a "Planned CI gates" list copied from
      `docs/plan.md` § 2 row 2, marked as not yet wired (CI/CD epic).
- [ ] AC13a (from TE-003 round 1): `docs/testing.md` and/or `docs/deploy/dev.md` cover the following.
  - **Paratest pin (security L1):** paratest is pinned to 7.26.0, and why. Note to move back to `^7.26.1` (hardening
    fix PR #1148) once Pest allows it. Never run `--parallel` on a shared multi-user host.
  - **Parallel test databases:** each worker uses its own database, `traveleasy_test_test_N`.
  - **Config before parallel runs (security F4):** run `php artisan config:clear` before
    `--parallel --recreate-databases` or `--drop-databases`.
  - **Formatter output:** formatters report files, not always lines. Pint `-v` shows hunks; oxfmt shows the file
    only.
  - **Vite advisories:** the manual Vite advisory check from TE-003 AC13 (`npm audit` can't see the aliased `vite`).
  - **Troubleshooting:** rapid repeated edits of `vite.config.ts` or `lint.rules.ts` can hit a vite-plus restart race
    ("Port 5173 is already in use"). The fix is `docker compose restart vite`.
  - **Stale references:** `dev.md` lines 55–56 (current draft) point to `npm run check` / `npm run types`.
  - **Full command list:**
    - Composer: `composer lint`, `format`, `analyse`, `test`, `test:parallel`, `check`.
    - npm: `npm run check`, `lint`, `lint:fix`, `format`, `format:check`, `types`, `types:check`, `check:fix`.
- [ ] AC13c (from TE-004 round 1, security L6; devops-engineer, a small config change in this story):
  - **The problem.** The Vite dev server currently serves project files: `/config/app.php`, `/phpunit.xml` and
    `/bootstrap/cache/services.php` return 200 on `127.0.0.1:5173`.
  - **The change.** `vite.config.ts` adds `server.fs.deny` entries for at least `**/*.php`, `**/storage/**`,
    `**/bootstrap/cache/**`, `**/phpunit.xml` and `**/.env*`, keeping Vite's defaults.
  - **The check.** QA confirms that those three URLs return 403/404 and that HMR (TE-002 AC4) still works.
  - **The docs.** `dev.md` mentions it under security notes.
- [ ] AC13e (from TE-004 round 2): `docs/testing.md` has a "Known limits of the arch scans" list. Each item says how it
  is covered instead (runtime test, review) or that it is accepted:
  - names built at runtime (string concatenation, variables holding class names);
  - `fprintf` (not in the debug list);
  - a closure inside an allowed CSRF range that runs at request time;
  - `Route::group(['excluded_middleware' => …])`, which `CsrfRuntimeCoverageTest` covers at runtime;
  - the deliberate false positive `'verify' => storage_path(...)`: only a literal CA path is allowed.

  Added from TE-004 round 3 (security L-e, L-f, and QA). Each item is listed as a known limit with how it is covered,
  plus a recommendation for a later backend hardening pass:
  - **CSRF (L-e):** `call_user_func([PreventRequestForgery::class, 'except'], …)`, and a middleware group whose name
    isn't a literal. Covered at runtime by `CsrfRuntimeCoverageTest`.
  - **TLS (L-f):**
    - `Config::set` / `config()->set` / `config([...])` with a TLS key (there the key is at argument position 0);
    - `Arr::add`;
    - `stream_context_set_option` with PHP's real named parameters;
    - Symfony `HttpOptions->verifyPeer(false)`.

    Recommendation: extend the TLS rule in a later hardening pass.
  - **Global session middleware:** `$middleware->append(StartSession)` / `prepend(StartSession)` at the global
    level. Covered at runtime.
  - **Spawned processes:** a test that starts a new process (`Process(['php', 'artisan', …])` or similar) boots the
    app outside the guard. Recommendation: flag it in test files outside `tests/Support/`.

  Added at the TE-004 close (2026-10-12), with recommendations for a later backend hardening pass:
  - **Path normalisation (L-h):** the joined-path check on `bootstrap/app.php` doesn't normalise `//`, `/./` or
    `x/../`. Recommendation (cheap): normalise the joined path before matching, or forbid `require`/`include` in
    test files outside `tests/Support/` entirely.
  - **More guard forms:** `Filesystem->getRequire()` / `File::getRequire()` and `$app->handle($request)` aren't in
    AC3's forms. Recommendation: add them to the guard contract.
  - **More TLS forms:** `Config::set` / `config()->set` / `config([...])` with a dotted verify key (key at argument
    position 0), and `stream_context_set_option` with PHP's real named parameters (`option_name:`).
    Recommendation: extend the TLS rule.
- [ ] AC13d (from TE-005 close):
  - **(a) `docs/testing.md` coverage:**
    - the jsdom limits (`color-contrast`, `link-in-text-block`, `target-size`, `scrollable-region-focusable` aren't
      checked in Vitest);
    - the needs-review process: the accessibility-auditor reviews the `a11y-needs-review.txt` attachments in **every
      UI story**;
    - only one e2e run at a time, because runs share the output folder;
    - the report paths `playwright-report/html` and `test-results/e2e`;
    - the allow-list rules;
    - the exact e2e command (`docker compose --profile e2e run --rm e2e`, or whatever form is current).
  - **(b) `.env*` masks (security L4, devops-engineer):** the e2e masks only cover `.env*` files that exist when the
    Pest contract test runs. Either change the e2e service to mount only what it needs instead of the whole repo, or
    document in `dev.md` "after adding any `.env.*` file, run the Pest suite before running e2e". State which in the
    handoff.
  - **(c) Redundant `--output` (devops-engineer):** remove the `--output=test-results/e2e` flag and its comment from
    the e2e command in `compose.dev.yaml` (around lines 109 and 169), because the Playwright config already sets
    `outputDir`. e2e still writes to `test-results/e2e`.
  - **(d) `vp fmt` warning:** `dev.md` notes that `npx vp fmt` without paths reformats the whole repo, so always pass
    paths or use `npm run format`.
- [ ] AC13b (from TE-005 round 1):
  - **Parallel e2e output:** parallel or concurrent e2e runs share `test-results/`, so use `--output /tmp/...` for
    concurrent runs.
  - **AC4 command:** the e2e command needs `--config tests/e2e/playwright.config.ts`; the simplest form is
    `docker compose run --rm e2e`.
  - **What jsdom can't check** (F1), in `docs/testing.md`: the Vitest axe matcher does **not** check `color-contrast`,
    `target-size`, `scrollable-region-focusable`, or any other layout-dependent rule. Those are checked in Playwright
    and by the auditor. Also: how incomplete ("needs review") results are reported in Vitest and e2e.
  - **The allow-list rules:** the central list only, the stale-entry check, and a reason that names an epic.
- [ ] AC14 (CLAUDE.md): `CLAUDE.md` § Tech stack lists the exact installed versions (PHP, Laravel, Inertia, React,
      TypeScript, Tailwind, React Aria Components, laravel-data, typescript-transformer, Wayfinder, Pest, Vitest,
      Playwright, Node, PostgreSQL) and the install, dev, test, lint, build, migrate and seed commands; versions match
      `composer.lock`, `package-lock.json` and the compose image tags. The § Status row "Scaffolding + Docker dev env" is
      updated (e.g. "Dev env done; CI/SSR/deploy pending"). Written by the orchestrator/architect from the
      devops-engineer's report.
- [ ] AC15 (links): `docs/deploy/dev.md` and `docs/testing.md` link each other and are linked from a root `README.md`
      (created if missing: one paragraph about the project and a "Getting started" link to `dev.md`).
- [ ] AC16 (license, owner decision 2026-10-11): the repo root has a `LICENSE` file with the standard MIT license
      text, `Copyright (c) 2026 Nimród Lobozár`. `composer.json` has `"license": "MIT"`, `package.json` has
      `"license": "MIT"` (or `"private": true` with the license field, but no conflicting value), and the README
      mentions the license. The orchestrator/architect adds one line to `CLAUDE.md` § Decisions so far
      ("2026-10-11: The project is licensed under **MIT**.") in the same edit as the AC14 stack versions.
- [ ] AC17 (versions from TE-001): the versions recorded in the TE-001 changelog are the starting point for AC14
      (PHP 8.5.11, Laravel 13.35.0, Node 24.21.0, …). Update any that TE-002 to TE-005 changed.

## Quality criteria
- [ ] Tests: QA runs every command in both documents on a fresh clone on Windows and lists any that failed or needed an
      unstated step (each one is a FAIL).
- [ ] Accessibility: not applicable (docs only). Docs use real headings and descriptive link text.
- [ ] Security/privacy: no real passwords, keys or personal data in any example; the `DB_PASSWORD` example is a
      placeholder or a generator command.
- [ ] i18n: docs in English (`CLAUDE.md` rule).
- [ ] Performance: not applicable.
- [ ] Docs updated: this story is the docs.
- [ ] Docker rebuild: no.

## Out of scope
- `docs/deploy/casaos-portainer.md` (First home-server deploy epic), CI docs, SSR docs, design-system docs.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
