---
id: TE-013
title: GitHub Actions pipeline, part 1: quality gates
status: ready
priority: must
epic: M0 CI/CD to GHCR
agents: [devops-engineer, qa-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want every push and pull request checked by the same gates I use locally, so that `main` stays
deployable.

## Context
- `docs/plan.md` § 2 milestone DoD row 2 and § 4 M0 "CI on GitHub Actions". Use the wording oxlint/oxfmt (not
  ESLint/Prettier). Principle in `CLAUDE.md`: lint, typecheck and tests run in CI before an image is published.
- Board "Notes for the CI/CD epic" (TE-005 security L3): pin the Playwright image by digest, image scan on the dev and
  Playwright images, artifacts on failure only, optional non-localhost request guard.
- **Depends on:** TE-008 (SSR smoke test), TE-010 (i18n check), TE-001 to TE-006. **Blocks:** TE-014.
- CI is the Docker flow, not a second toolchain: use the same compose files and commands as `docs/testing.md`.

## Acceptance criteria
- [ ] AC1: Given a push to any branch or a PR to `main`, when the workflow `ci.yml` runs, then it executes, as separate
      named jobs or steps: Pint check, Larastan (max, no baseline), `composer test` (Pest on PostgreSQL), oxlint, oxfmt check,
      `tsc --noEmit` (via `npm run check`), Vitest, i18n check, client + SSR build, `php artisan optimize`, SSR smoke test
      (TE-008), Playwright + axe against the SSR build, `docker compose config --quiet`, `composer audit`, `npm audit`.
- [ ] AC2: Given any gate fails, when the workflow ends, then the run is red and the failing step name appears in the
      summary; no later publishing job runs (needs: quality).
- [ ] AC3: Given the compose guard step, when `compose.yaml` contains an `adminer` service, a `ports:` entry on `db`, or an
      initdb mount, then the job fails (script under `scripts/ci/`, with its own test).
- [ ] AC4: Given the workflow, when read, then every third-party action is pinned to a commit SHA, `permissions:` defaults to
      `contents: read` at the top, and no job uses `pull_request_target`.
- [ ] AC5: Given Playwright artifacts (traces, reports, screenshots), when a job fails, then they are uploaded only on failure,
      with 1 to 3 days retention; on success nothing is uploaded. They are never published as release assets.
- [ ] AC6: Given the Playwright image in CI, when read, then it is pinned by digest, and Dependabot (or Renovate) is
      configured for GitHub Actions, Composer, npm and Docker base images, grouped weekly.
- [ ] AC7: Given a run from a fork PR, when it executes, then no secrets are available and no publishing happens.
- [ ] AC8: Given the Playwright job, when a spec makes a request to a non-localhost origin, then the guard fails the test
      (no runtime CDNs; allow-list is empty).
- [ ] AC9: Given a warm cache, when CI runs on an unchanged dependency set, then the pipeline finishes in under 15 minutes;
      cache keys use lockfile hashes.
- [ ] AC10: Given `composer audit` or `npm audit` finds a High/Critical advisory with no fix, when the job runs, then it
      fails unless the advisory ID is in a reviewed, dated ignore file with a reason (and the check for the `vite` alias
      advisories is a documented manual step, see `CLAUDE.md`).

## Quality criteria
- [ ] Tests: the workflow is validated with `actionlint` in CI itself; scripts under `scripts/ci/` have Vitest or Pest
      tests; a deliberately failing branch is run once and the evidence is noted in the handoff.
- [ ] Accessibility: axe runs through the existing Playwright job.
- [ ] Security/privacy: least-privilege tokens, SHA-pinned actions, no secret echoed, `GITHUB_TOKEN` read-only here.
- [ ] i18n: key check runs (TE-010).
- [ ] Performance: AC9.
- [ ] Docs updated: `docs/testing.md` (CI gates table), `README.md` badge optional, `CLAUDE.md` Status row.

## Out of scope
- Image build, scan and publish (TE-014); deploys.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
