---
id: TE-029
title: CI and deploy hardening follow-ups (chore)
status: done
priority: should
epic: M0 CI/CD to GHCR
agents: [devops-engineer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the Low findings from the TE-013 and TE-024 security reviews fixed, so that CI, the docs and the
deploy files say and enforce the right things.

## Context
- Security reviews of TE-013 and TE-024 (2026-11-03), Lows #1 to #3, plus stale wording after TE-024 made `compose.dev.yaml`
  standalone and `TRUSTED_PROXIES` a single NPM IP.
- Related: TE-013 AC8 (non-localhost guard), TE-024 AC17, TE-026.

## Acceptance criteria
- [x] AC1 (security Low #1): Given the Playwright suite, when any test makes a request to a non-localhost origin, including a raw
      IP address, then an auto-fixture fails that test; a fixture test proves it (one hostname and one raw IP case fail, a
      `localhost`/`127.0.0.1` request passes).
- [x] AC2 (security Low #2): Given `docs/testing.md`, when read, then it states that e2e uses only fake data and no secrets,
      because failure artifacts in a public repo can be downloaded by any GitHub user.
- [x] AC3 (security Low #3): Given `docs/deploy/casaos-portainer.md` § Backup, when read, then the `pg_dump` example no longer sets
      `PGPASSWORD` (`docker exec <db> pg_dump -U postgres` works over the local socket) and sets `umask 077` before the dump files
      are written.
- [x] AC4 (stale wording): Given the repo, when read, then (a) `compose.yaml` lines 4-5 no longer say dev merges with it, (b)
      `app/Support/Security/TrustedProxies.php` line 13 no longer says "CIDR of npm_shared", and (c) `ci.yml`'s config-only steps use a
      single IP for `TRUSTED_PROXIES` instead of `172.30.0.0/16`.

## Quality criteria
- [x] Tests: the AC1 fixture test; existing Pest, Vitest and compose-guard suites stay green; `actionlint` clean after AC4c.
- [x] Accessibility: n/a.
- [x] Security/privacy: reviewer confirms Lows #1 to #3 are closed.
- [x] i18n: n/a.
- [x] Performance: the fixture adds no noticeable e2e time.
- [x] Docs updated: `docs/testing.md`, `docs/deploy/casaos-portainer.md`.

## Out of scope
- New CI gates; image build, scan and publish (TE-014).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-11-03 on branch `feat/m0-batch-5`.
- **Added:** non-localhost request guard fixture with its fixture test (71c3de9, documented in `docs/testing.md`).
- **Changed:** backup example in `docs/deploy/casaos-portainer.md` (no `PGPASSWORD`, `umask 077`); stale wording in `compose.yaml`,
  `TrustedProxies.php` and `ci.yml` (single NPM IP).
- **Removed:** nothing. **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed.
- **Testers:** qa-engineer PASS (Vitest 249, Pest 758, e2e 86 + 2 skipped, SSR e2e 88); security-privacy-reviewer PASS (Lows #1 to #3 closed).
