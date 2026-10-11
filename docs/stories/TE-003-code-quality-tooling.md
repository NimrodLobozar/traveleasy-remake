---
id: TE-003
title: Code quality tooling (Pint, Larastan, ESLint, Prettier, tsc)
status: ready
priority: must
epic: M0 Project scaffold and tooling
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
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
- [ ] AC1 (Pint): `docker compose exec app composer lint` runs Pint in check mode (`pint --test`) and exits 0 on the
      codebase; `composer format` applies fixes. The preset is `laravel` plus `declare_strict_types` on `app/`,
      `database/`, `routes/` and `tests/` (or a documented reason for not using it).
- [ ] AC2 (Larastan): `docker compose exec app composer analyse` runs Larastan at **level 8 or higher** over `app/`,
      `config/`, `database/`, `routes/` and `tests/`, and exits 0 **without a baseline file**. The level is stated in
      the handoff; if it isn't `max`, say why.
- [ ] AC3 (ESLint): `docker compose exec vite npm run lint` runs ESLint (flat config) with typescript-eslint
      type-aware `strict` rules, `react`, `react-hooks` and `jsx-a11y` (recommended) over `resources/js`, and exits 0.
      `@typescript-eslint/no-explicit-any` and `no-console` (allowing none) are errors. Generated files (Wayfinder,
      typescript-transformer output) are ignored.
- [ ] AC4 (Prettier): `docker compose exec vite npm run format:check` exits 0; `npm run format` writes fixes. The
      Tailwind class-sorting plugin is enabled. ESLint and Prettier don't fight (eslint-config-prettier or
      equivalent).
- [ ] AC5 (types): `docker compose exec vite npm run types` runs generation if needed (TE-001 AC7) and then
      `tsc --noEmit`, and exits 0.
- [ ] AC6 (generated types up to date): TE-001 decided that generated output is **git-ignored** and produced by
      `npm run generate`. So instead of a diff check, every type/lint entry point (AC5, AC7) runs `npm run generate`
      first, and a fresh clone passes `npm run types` without any manual step.
- [ ] AC10 (generated folders ignored): ESLint and the formatter both ignore `resources/js/actions`,
      `resources/js/routes`, `resources/js/wayfinder` and `resources/js/types/generated`. Running `npm run format`
      leaves those folders untouched.
- [ ] AC11 (formatter scope, Markdown and JSON): `vp check` (vite-plus) / `npm run check` currently fails only on Markdown
      and JSON formatting in `docs/**`, `.claude/**` and `CLAUDE.md` (30 files, confirmed in the TE-002 re-test). Decision: **exclude** `docs/`, `.claude/`, `CLAUDE.md` and
      root `*.md` from the code formatter. They are hand-written prose maintained by the agents, and reflowing them
      would create noisy diffs. After the change, `vp check` / `npm run check` exits 0 on a clean tree, and a
      deliberately mis-formatted `.tsx` file still fails it. If the developer prefers to format them once instead,
      that's acceptable, provided `check` is green and the choice is stated in the handoff.
- [ ] AC12 (unused dev dependency): `laravel/sail` is removed from `require-dev` (we use our own compose files);
      `composer.lock` is updated and nothing references Sail.
      Also, `symfony/yaml` is required **directly** in `require-dev`. QA's
      `tests/Unit/DockerDevEnvironmentContractTest.php` (TE-002) parses the compose files with it, and today it is only
      a transitive dependency. (Added after TE-002 round 1.)
- [ ] AC14 (parallel tests; found in the TE-002 re-test): `php artisan test --parallel` currently crashes with
      `ArgumentCountError` in `ParaTest\WrapperRunner\WrapperWorker::__construct()`, caused by a Pest/Paratest version
      mismatch in `composer.lock`. Either align the versions so that `docker compose exec app php artisan test --parallel`
      passes with the same result as a serial run (including the TE-002 AC17 database guard, with per-process test
      databases still matching the guard's rule), or remove the parallel option from the scripts and state in the
      handoff (for `docs/testing.md`) that parallel testing isn't supported yet and why. No command may crash.
- [ ] AC13 (Vite advisory gap): because `vite` is an npm alias for `@voidzero-dev/vite-plus-core@0.3.3` (TE-001),
      `npm audit` can't see advisories for Vite. This is documented in a comment next to the alias in `package.json`
      or in `docs/testing.md` (via TE-006) and in the handoff for the CI/CD epic, together with:
      - the manual check: review the vite-plus-core release notes and the Vite security advisories on every dependency
        update;
      - the intent to move to vite-plus 1.x, with a plain `vite` dependency, when the starter kit does.
- [ ] AC7 (one entry point): `docker compose exec app composer check` and `docker compose exec vite npm run check`
      (or one documented script) run all of the above in sequence and stop with a non-zero exit at the first failure.
- [ ] AC8 (violations fail): for each tool, QA introduces one deliberate violation (bad formatting in a PHP file, a
      type error that Larastan catches, an `any` in a `.tsx` file, unformatted TS, a TS type error, an `<img>` without
      `alt`) and the matching command exits non-zero with a message naming the file and line. The violations are not
      committed.
- [ ] AC9 (Windows friendliness): all commands work from PowerShell on Windows through `docker compose exec`, and none
      of them modifies line endings (files stay LF).

## Quality criteria
- [ ] Tests: AC8 is the test; QA records the command output in the verdict.
- [ ] Accessibility: `jsx-a11y` is on (AC3) as an early, cheap safety net; no UI change, so no auditor.
- [ ] Security/privacy: new packages are dev-only (`require-dev` / `devDependencies`) and pinned through the lockfiles;
      `composer audit` and `npm audit --audit-level=high` show no new High/Critical advisories.
- [ ] i18n: not applicable.
- [ ] Performance: the full `check` runs in under 2 minutes on the dev PC (reported, not gated).
- [ ] Docs updated: the handoff lists every command for TE-006 (`docs/testing.md`, `docs/deploy/dev.md`, `CLAUDE.md`).
- [ ] Docker rebuild: yes, `docker compose up -d --build -V` after the `composer.json`/`package.json` changes.

## Out of scope
- Running the tools in CI (CI/CD epic), pre-commit hooks (could be a later chore), the SSR-safety lint rule (SSR epic),
  an i18n missing-key check (i18n epic).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
