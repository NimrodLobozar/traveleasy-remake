---
id: TE-003
title: Code quality tooling (Pint, Larastan, ESLint, Prettier, tsc)
status: done
priority: must
epic: M0 Project scaffold and tooling
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
---

## User story
As the developer, I want formatters, linters, static analysis and a type check that each run with one command in the
containers, so that style and type errors are caught before review and CI can later gate on the same commands.

## Context
- `docs/plan.md` § 4 M0 scope ("Quality tooling"), § 2 milestone DoD row 2 (the CI gates list),
  `docs/legacy-analysis.md` § 3 Engineering ("no linting enforced").
- **Depends on:** TE-001 (code), TE-002 (commands run in the containers). **Can run in parallel with** TE-004.
  TE-005 should start after this story's ESLint config exists (both touch `package.json` and the ESLint config).
- CI wiring is out of scope; this story makes sure the commands exist, are deterministic and fail on violations, so
  the CI epic only has to call them.
- The SSR-safety ESLint rule (no `window`/`document`/`localStorage` at module level or in render) belongs to the
  SSR epic; leave room for it in the config.
- **From TE-001:**
  - The kit uses vite-plus (`vp`), and `vite` is aliased to `@voidzero-dev/vite-plus-core@0.3.3`.
  - npm is the package manager.
  - Generated Wayfinder/TS output is git-ignored and produced by `npm run generate`.
  - Where vite-plus already bundles a linter/formatter (`vp check`), the developer may build on it instead of adding
    a separate ESLint/Prettier setup, provided AC3, AC4 and AC8 still hold. State the choice in the handoff.

## Acceptance criteria
- [x] AC1 (Pint): `docker compose exec app composer lint` runs Pint in check mode (`pint --test`) and exits 0 on the
      codebase; `composer format` applies fixes. The preset is `laravel` plus `declare_strict_types` on `app/`,
      `database/`, `routes/` and `tests/` (or a documented reason for not using it).
- [x] AC2 (Larastan): `docker compose exec app composer analyse` runs Larastan at **level 8 or higher** over `app/`,
      `config/`, `database/`, `routes/` and `tests/`, and exits 0 **without a baseline file**. The level is stated in
      the handoff; if it isn't `max`, say why.
- [x] AC3 (ESLint): `docker compose exec vite npm run lint` runs ESLint (flat config) with typescript-eslint
      type-aware `strict` rules, `react`, `react-hooks` and `jsx-a11y` (recommended) over `resources/js`, and exits 0.
      `@typescript-eslint/no-explicit-any` and `no-console` (allowing none) are errors. Generated files (Wayfinder,
      typescript-transformer output) are ignored.
- [x] AC4 (Prettier): `docker compose exec vite npm run format:check` exits 0; `npm run format` writes fixes. The
      Tailwind class-sorting plugin is enabled. ESLint and Prettier don't fight (eslint-config-prettier or
      equivalent).
- [x] AC5 (types): `docker compose exec vite npm run types` runs generation if needed (TE-001 AC7) and then
      `tsc --noEmit`, and exits 0.
- [x] AC6 (generated types up to date): TE-001 decided that generated output is **git-ignored** and produced by
      `npm run generate`. So instead of a diff check, every type/lint entry point (AC5, AC7) runs `npm run generate`
      first, and a fresh clone passes `npm run types` without any manual step.
- [x] AC10 (generated folders ignored): ESLint and the formatter both ignore `resources/js/actions`,
      `resources/js/routes`, `resources/js/wayfinder` and `resources/js/types/generated`. Running `npm run format`
      leaves those folders untouched.
- [x] AC11 (formatter scope, Markdown and JSON): `vp check` (vite-plus) / `npm run check` currently fails only on Markdown
      and JSON formatting in `docs/**`, `.claude/**` and `CLAUDE.md` (30 files, confirmed in the TE-002 re-test). Decision: **exclude** `docs/`, `.claude/`, `CLAUDE.md` and
      root `*.md` from the code formatter. They are hand-written prose maintained by the agents, and reflowing them
      would create noisy diffs. After the change, `vp check` / `npm run check` exits 0 on a clean tree, and a
      deliberately mis-formatted `.tsx` file still fails it. If the developer prefers to format them once instead,
      that's acceptable, provided `check` is green and the choice is stated in the handoff.
- [x] AC12 (unused dev dependency): `laravel/sail` is removed from `require-dev` (we use our own compose files);
      `composer.lock` is updated and nothing references Sail.
      Also, `symfony/yaml` is required **directly** in `require-dev`. QA's
      `tests/Unit/DockerDevEnvironmentContractTest.php` (TE-002) parses the compose files with it, and today it is only
      a transitive dependency. (Added after TE-002 round 1.)
- [x] AC14 (parallel tests; found in the TE-002 re-test): `php artisan test --parallel` currently crashes with
      `ArgumentCountError` in `ParaTest\WrapperRunner\WrapperWorker::__construct()`, caused by a Pest/Paratest version
      mismatch in `composer.lock`. Either align the versions so that `docker compose exec app php artisan test --parallel`
      passes with the same result as a serial run (including the TE-002 AC17 database guard, with per-process test
      databases still matching the guard's rule), or remove the parallel option from the scripts and state in the
      handoff (for `docs/testing.md`) that parallel testing isn't supported yet and why. No command may crash.
- [x] AC13 (Vite advisory gap): because `vite` is an npm alias for `@voidzero-dev/vite-plus-core@0.3.3` (TE-001),
      `npm audit` can't see advisories for Vite. This is documented in a comment next to the alias in `package.json`
      or in `docs/testing.md` (via TE-006) and in the handoff for the CI/CD epic, together with:
      - the manual check: review the vite-plus-core release notes and the Vite security advisories on every dependency
        update;
      - the intent to move to vite-plus 1.x, with a plain `vite` dependency, when the starter kit does.
- [x] AC7 (one entry point): `docker compose exec app composer check` and `docker compose exec vite npm run check`
      (or one documented script) run all of the above in sequence and stop with a non-zero exit at the first failure.
- [x] AC8 (violations fail): for each tool, QA introduces one deliberate violation (bad formatting in a PHP file, a
      type error that Larastan catches, an `any` in a `.tsx` file, unformatted TS, a TS type error, an `<img>` without
      `alt`) and the matching command exits non-zero. The violations are not committed.
      *Clarified in rework round 1:* what the message must contain depends on the kind of tool.
      - **Linters, static analysers and type checkers** (ESLint/oxlint, Larastan, `tsc`) must name the **file and
        line**.
      - **Formatters** must name the file, and the line wherever the tool can report one:
        - `composer lint` runs Pint with `-v` (`pint --test -v`), so it prints diff hunks with line numbers;
        - oxfmt (`npm run format:check` / `vp fmt`) has no line or diff option, so naming the file is enough.
- [x] AC9 (Windows friendliness): all commands work from PowerShell on Windows through `docker compose exec`, and none
      of them modifies line endings (files stay LF).

## Quality criteria
- [x] Tests: AC8 is the test; QA records the command output in the verdict.
- [x] Accessibility: `jsx-a11y` is on (AC3) as an early, cheap safety net; no UI change, so no auditor.
- [x] Security/privacy: new packages are dev-only (`require-dev` / `devDependencies`) and pinned through the lockfiles;
      `composer audit` and `npm audit --audit-level=high` show no new High/Critical advisories.
- [x] i18n: not applicable.
- [x] Performance: the full `check` runs in under 2 minutes on the dev PC (reported, not gated).
- [x] Docs updated: the handoff lists every command for TE-006 (`docs/testing.md`, `docs/deploy/dev.md`, `CLAUDE.md`).
- [x] Docker rebuild: yes, `docker compose up -d --build -V` after the `composer.json`/`package.json` changes.

## Out of scope
- Running the tools in CI (CI/CD epic), pre-commit hooks (could be a later chore), the SSR-safety lint rule (SSR epic),
  an i18n missing-key check (i18n epic).

## Open questions
- (none)

## Rework log

### Round 1 (2026-10-11)
- **Verdicts:**
  - **security-privacy-reviewer: PASS**, with one Low in scope (L1): paratest is pinned to 7.26.0 and misses the
    7.26.1 hardening fix (PR #1148, worker files in a private per-run directory). Move back to `^7.26.1` once Pest
    allows it, and never run `--parallel` on a shared multi-user host. This is documented through TE-006, not a
    blocker.
  - **qa-engineer: FAIL on AC8 only.** Everything else passes:
    - 100/100 tests, with identical serial and parallel results;
    - AC3: the type-aware rules fire, the rule list is proven real, and the 7 confirmed gaps are acceptable;
    - the full check takes about 56 s.
- **Failed criterion:** AC8, "message naming the file and line".
- **Evidence:**
  - **Pint:** `composer lint` exits 1 and names the file and the fixer rules, but gives no line, e.g.
    `⨯ app/QaPintViolation.php single_space_around_construct, …`. `pint --test -v` prints diff hunks with line
    numbers.
  - **oxfmt:** `npm run format:check` exits 1 and names only the file. `vp fmt` has no line or diff option. Prettier
    `--check`, which the AC was originally written for, doesn't print lines either.
- **Cause:**
  - Pint: the `-v` flag is missing from the `lint` script.
  - oxfmt: the criterion was ambiguous, because it asked formatters for something they can't report.
- **Criterion clarified:** AC8 now separates linters, analysers and type checkers (file + line) from formatters (file,
  and the line where the tool can report it; Pint with `-v` shows hunks; oxfmt reports the file only).
- **Expected fix (backend-developer):** add `-v` to **both** `pint --test` calls in the `composer.json` `lint` script.
  QA re-runs the Pint violation and checks that the output contains a line-numbered hunk. No other change is needed.
- **Follow-ups filed elsewhere:**
  - TE-006: parallel/paratest notes, formatter output, the Vite advisory check, the vite-plus restart race,
    `dev.md` lines 55–56, and the full command list.
  - Board, "Follow-ups for M1": security F1/F2, and the auth-UI accessibility items.
  - Board, "Notes for the production / deploy epics": security F3.

## Changelog
Closed 2026-10-11 after 1 rework round.

**Verdicts**
- **qa-engineer: PASS** on the re-test after rework 1. AC1–AC14 and all quality criteria pass.
  - **AC8** passes under the clarified wording: Pint shows `@@` hunks; oxlint, Larastan and `tsc` show file:line;
    oxfmt shows the file.
  - **Tests:** 100/100 (343 assertions), with identical serial and parallel results.
  - **Timings:** `composer check` 28 s, `npm run check` 23 s.
- **security-privacy-reviewer: PASS**, with Low L1 (the paratest pin). It's documented through TE-006 AC13a.

**Commits**

| Commit | Content |
|---|---|
| 5ab9017 | Tooling |
| eb24776 | PHP `^8.5`, the kit's `setup` script removed, npm package name |
| 91005f0 | Rework entry |
| 4dd21e0 | `pint -v` fix |

**Implementation note (AC3/AC4)**
- **Choice:** the story's own context allowed the developer to build on vite-plus instead of adding ESLint and
  Prettier. The developer chose vite-plus oxlint (type-aware) and oxfmt, and deliberately added **no** ESLint or
  Prettier.
- **Why:** eslint-plugin-react and jsx-a11y only support the deprecated ESLint 9.
- **Coverage:** the rule intent is covered by an explicit 238-rule list in `lint.rules.ts`: TS strict-type-checked,
  react, react-hooks and jsx-a11y, with `denyWarnings`. The 7 gaps are documented, and QA confirmed them as acceptable.
- **Title:** the story title still says "ESLint, Prettier" for traceability.

**Added**
- **PHP formatting:**
  - `pint.json`: the laravel preset plus `declare_strict_types` on `app/`, `database/`, `routes/` and `tests/`;
  - `pint.no-strict-types.json` for `bootstrap/`, `config/` and `public/`.
- **PHP static analysis:**
  - Larastan at level **max**, with no baseline, plus `pest-plugin-phpstan`;
  - `app/Support/CurrentUser`.
- **Parallel tests:** paratest pinned to 7.26.0, and a parallel worker-DB guard (per-worker
  `traveleasy_test_test_N`).
- **Composer scripts:** `lint` (Pint `--test -v`), `format`, `analyse`, `test`, `test:parallel`, `check`.
- **Frontend tooling:**
  - vite-plus oxlint with type-aware rules, configured in `lint.rules.ts`;
  - oxfmt with Tailwind class sorting.
- **npm scripts:** `check`, `lint`, `lint:fix`, `format`, `format:check`, `types` (runs `npm run generate` first),
  `types:check`, `check:fix`.
- `symfony/yaml` as a direct dev dependency.

**Changed**
- **Larastan fixes:** 239 errors fixed across the codebase, with 3 inline ignores for `Route::inertia()`.
- **Throttle keys:** they now handle non-string input.
- **Lint fixes in kit code:** 81 issues fixed, including:
  - the positive `tabIndex` removed on the login and register pages;
  - `rel="noreferrer"` on external links;
  - 10 justified suppressions.
- **Formatter scope:** `docs/`, `.claude/` and root `*.md` are excluded.
- **Generated folders:** ignored by the linter and the formatter.
- **Requirements:** PHP is required as `^8.5`, and the npm package name is set.

**Removed**
- `laravel/sail`, the kit's `setup` composer script, and `tests/Unit/ExampleTest.php`.

**Migrations:** none.

**New env vars:** none.

**Docker:** rebuilt after the `composer.json` changes, and the previous dev image was removed. Developers with an
older checkout need `docker compose up -d --build -V`, or the clean variant documented by TE-006.

**Follow-ups:** all already filed:
- TE-006 AC13a (paratest pin, parallel DBs, `config:clear`, formatter output, the Vite advisory check, the vite-plus
  restart race, the command list);
- the board's "Follow-ups for M1" (security F1/F2, auth-UI accessibility);
- the board's "Notes for the production / deploy epics" (F3).
