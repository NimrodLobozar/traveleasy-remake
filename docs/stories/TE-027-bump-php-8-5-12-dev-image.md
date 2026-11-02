---
id: TE-027
title: Bump PHP to 8.5.12 in the dev image (chore)
status: in-progress
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
- [ ] AC3: Given the rebuilt stack, when `composer check` (Pint, Larastan, Pest), `npm run check`, `npm run test` and the e2e + axe
      run execute, then all pass with no new deprecations or skipped tests.
- [x] AC4: Given `CLAUDE.md`, when read, then the versions table says PHP 8.5.12 and the date of the table is updated.

## Quality criteria
- [ ] Tests: the existing suites are the proof (AC3); no new test needed.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: security-privacy-reviewer confirms the advisories are fixed in 8.5.12 and that the new image has no new
      HIGH/CRITICAL fixable finding.
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: `CLAUDE.md` versions table; `docs/deploy/dev.md` if it names the version.

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
