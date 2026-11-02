---
id: TE-027
title: Bump PHP to 8.5.12 in the dev image (chore)
status: done
priority: must
epic: none
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the dev image on PHP 8.5.12, so that dev and CI do not run a PHP release with two known core security
advisories and match the production base of ADR-0002.

## Context
- PHP 8.5.12 fixes two core security advisories. The `Dockerfile` and the `CLAUDE.md` versions table say 8.5.11
  (`php:8.5.11-cli-trixie`).
- ADR-0002 (Accepted 2026-11-02) starts production on PHP 8.5.12 and lists this bump as a separate devops chore.
- **Requires a rebuild of `app` and `vite`** (both use the dev image): `docker compose rm -s -f -v app vite` then
  `docker compose up -d --build` (DB kept). Clean up dangling images only with the project-scoped commands in `docs/deploy/dev.md`.

## Acceptance criteria
- [x] AC1: Given the `Dockerfile`, when read, then the PHP base image is `php:8.5.12-cli-trixie` (tag, and digest if the file pins
      digests) and no other reference to 8.5.11 remains in the repo.
- [x] AC2: Given a rebuilt `app` container, when `docker compose exec app php -v` runs, then it prints `PHP 8.5.12`, and `php -m` shows
      the same extensions as before the bump.
- [x] AC3: Given the rebuilt stack, when `composer check` (Pint, Larastan, Pest), `npm run check`, `npm run test` and the e2e + axe
      run execute, then all pass with no new deprecations or skipped tests.
- [x] AC4: Given `CLAUDE.md`, when read, then the versions table says PHP 8.5.12 and the date of the table is updated.

## Quality criteria
- [x] Tests: the existing suites are the proof (AC3); no new test needed.
- [x] Accessibility: n/a.
- [x] Security/privacy: security-privacy-reviewer confirms the advisories are fixed in 8.5.12 and that the new image has no new
      HIGH/CRITICAL fixable finding.
- [x] i18n: n/a.
- [x] Performance: n/a.
- [x] Docs updated: `CLAUDE.md` versions table; `docs/deploy/dev.md` if it names the version.

## Out of scope
- The production image (TE-012) and moving dev onto the FrankenPHP base.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- 2026-11-02 devops: Dockerfile `PHP_VERSION=8.5.12`; CLAUDE.md versions table (dated 2026-11-02) and dev.md updated. Rebuilt with
  `docker compose rm -s -f -v app vite` + `up -d --build`; `php -v` = 8.5.12 (NTS), `php -m` identical to before; all healthy.
  Pint, Larastan, `npm run test` (168) pass. AC3 open: Pest has 6 failures in `InertiaSsrTest` (500, Vite manifest lacks
  `resources/fonts/figtree-latin-wght-normal.woff2`): caused by the uncommitted TE-020 font work with a stale `public/build`, not by
  the bump; needs `npm run build` after TE-020 lands. e2e not run (frontend work in progress).
  Audits unchanged (see board, Devops items). The `base` image digest is not pinned (the Dockerfile pins tags only); that is TE-012 for production.
- 2026-11-02 closed (commit 9292533 on `feat/m0-batch-3`). The AC3 failures were resolved once the TE-020 font work landed and the
  build was refreshed. Final run: Pest 621, Vitest 169, `npm run check` green, e2e 29 passed + 2 environment-conditional skips
  (in the repo since TE-015). qa-engineer PASS, security-privacy-reviewer PASS: 8.5.12 fixes the two unauthenticated RCE core
  advisories; production audit clean apart from symfony/mime, which is not reachable.
  Added: PHP 8.5.12 dev image. Changed: Dockerfile, CLAUDE.md versions table, dev.md. Removed: PHP 8.5.11 references.
  Migrations: none. New env vars: none. Docker rebuild of `app` and `vite`: done (needed on every machine that pulls this).
  Dependency bumps remain open on the board (Devops items): symfony/mime 7.4.22/8.1.10+, phpunit 13.4.4+, vite-plus/jsdom at TE-013's audit gate.
