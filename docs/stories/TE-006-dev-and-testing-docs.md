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
- [ ] AC5 (safe reset): a clearly marked warning (a `> **Warning**` block) that `docker compose down -v` **deletes all
      volumes including the database**, that the owner is asked first, and the safer alternatives
      (`php artisan migrate:fresh --seed` in the container for an empty schema; `down` without `-v` to stop).
- [ ] AC6 (test database): explains that `traveleasy_test` is created only on a fresh DB volume and gives the command to
      create it on an existing one.
- [ ] AC7 (troubleshooting): covers at least: HMR not updating (polling env vars), port 8000/5173 already in use
      (`APP_PORT`/`VITE_PORT`), slow file access on bind mounts and the option of cloning into the WSL file system
      (with its trade-offs), CRLF line-ending errors in scripts, and the Playwright image/version mismatch.
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
- [ ] AC14 (CLAUDE.md): `CLAUDE.md` § Tech stack lists the exact installed versions (PHP, Laravel, Inertia, React,
      TypeScript, Tailwind, React Aria Components, laravel-data, typescript-transformer, Wayfinder, Pest, Vitest,
      Playwright, Node, PostgreSQL) and the install, dev, test, lint, build, migrate and seed commands; versions match
      `composer.lock`, `package-lock.json` and the compose image tags. The § Status row "Scaffolding + Docker dev env" is
      updated (e.g. "Dev env done; CI/SSR/deploy pending"). Written by the orchestrator/architect from the
      devops-engineer's report.
- [ ] AC15 (links): `docs/deploy/dev.md` and `docs/testing.md` link each other and are linked from a root `README.md`
      (created if missing: one paragraph about the project and a "Getting started" link to `dev.md`).

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
