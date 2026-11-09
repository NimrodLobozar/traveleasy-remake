---
id: TE-038
title: Fix symfony/serializer security advisories (composer audit gate)
status: done
priority: must
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
---

## User story
As the maintainer, I want the vulnerable `symfony/serializer` (and `phpunit/phpunit`, if a patch exists) updated to patched versions through the lockfile, so that the `composer audit` CI gate passes without ignore entries.

## Context
CI on PR #12 (TE-037) fails the `composer audit` gate with 3 BLOCKING advisories on `symfony/serializer`, all new and unrelated to TE-037:
- CVE-2026-121623 (PKSA-nyd8-k74r-fsf2): DataUriNormalizer opens data URIs with a non-lowercase scheme as local files.
- CVE-2026-122053 (PKSA-y18k-qjc6-knrv): ObjectNormalizer dotted-key property-path traversal bypasses `#[Ignore]`.
- CVE-2026-118515 (PKSA-grh8-hvfp-h9pf): Serializer builds UploadedFile objects from request data.

A non-blocking MEDIUM advisory exists on `phpunit/phpunit` (CVE-2026-119193); in scope only if a patched version exists within the current constraint.

Owner decision: separate fix story on its own `fix/…` branch off `dev`; merge into `dev` first, then PR #12 is rerun. No ignore entries for these advisories. Related: TE-032 (dependency updates), TE-037.

## Acceptance criteria
- [x] AC1: Given the branch `fix/te-038-symfony-serializer-advisories` off `dev`, when `composer update symfony/serializer --with-dependencies` runs in Docker, then `composer.lock` pins a `symfony/serializer` version that is patched for all three advisories.
- [x] AC2: Given the updated lockfile, when `composer audit` runs in the app container, then it reports no advisory for `symfony/serializer` and no blocking advisory at all.
- [x] AC3: Given a patched `phpunit/phpunit` exists within the current `composer.json` constraint, when the lockfile is updated, then it is pinned to that version; if none exists, the story notes this and CVE-2026-119193 stays as the existing non-blocking finding.
- [x] AC4: Given the change, when the diff is reviewed, then no new ignore entry for these advisories exists in `ci.yml`, `composer.json` or any audit config, and `composer.json` constraints change only if a patched version requires it.
- [x] AC5: Given the update, when `bash scripts/check.sh all` runs, then PHP tests (Pest, PostgreSQL), Pint, Larastan (level max, no baseline) and the frontend checks pass.
- [x] AC6: Given the update, when `docs/tech-stack.md` is read, then the pins for `symfony/serializer` (and `phpunit/phpunit` if changed) match the lockfile. If a package is not listed in `docs/tech-stack.md`, this criterion is N/A for that package (clarified in rework round 1).
- [x] AC7: Given the dev stack is rebuilt with fresh dependency volumes, when the app starts, then it is healthy and `http://localhost:8000` responds.
- [ ] AC8: Given the PR into `dev` is merged, when PR #12 is rerun, then its `composer audit` gate passes.

## Quality criteria
- [ ] Tests: existing suite green; no new tests needed (dependency-only change).
- [ ] Accessibility: not applicable (no UI change).
- [ ] Security/privacy: security-privacy-reviewer confirms all three advisories are resolved by the locked version, no ignore entries were added, and no unrelated packages were upgraded beyond the transitive set.
- [ ] i18n: not applicable.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/tech-stack.md` pins.

## Files to touch
- `composer.lock`: updated `symfony/serializer` (+ transitive Symfony packages), `phpunit/phpunit` if patched.
- `composer.json`: only if a constraint blocks the patched version.
- `docs/tech-stack.md`: update pins.
- CI audit step: `.github/workflows/ci.yml` (~line 148) is read-only context; do not add ignores.
- Tests: none new; run `bash scripts/check.sh all`.

## Out of scope
- Upgrading unrelated packages or major versions.
- Adding audit ignore entries.
- The TE-037 changes (PR #12).

## Open questions
- (none)

## Rework log

### Round 1 (2026-11-09), failed by qa-engineer
- **Branch under test:** `chore/te-037-vite-plus-1x`, merge commit `f0e5f24` (TE-038 stacked under TE-037).
- **Failed criteria:** AC5 (full gate not green).
- **Evidence:**
  - e2e: 134 passed, 2 skipped, 10 failed. Log: `storage/logs/check/e2e-20261109-220126.log`.
  - `base-components` "axe at 320/1280px, light/dark (AC8)": 4 failures.
  - `complex-components` axe at 320px: 5 failures (calendar open, toasts, combobox open, every component and state).
  - `complex-components.spec.ts:295` "combobox async": `aria-busy` expected true, got empty.
  - Passing: PHP 759/759, front 323/323, Pint, Larastan, oxlint, tsc, i18n. Audit gate passed ("0 ignored, 1 below high"; only the medium phpunit advisory remains, non-blocking).
- **Likely cause:** not the serializer bump. Suspects are the TE-037 side (Playwright 1.65.0 / vite-plus 1.5.1) or flakiness. Not yet confirmed.
- **Clarified criteria:**
  - AC6: N/A for any package not listed in `docs/tech-stack.md`. Neither `symfony/serializer` nor `phpunit/phpunit` is listed there today, so AC6 is N/A unless the developer adds them.
  - AC8: can only be checked after the push and the PR rerun. It is not verifiable before then.
- **Expected fix:**
  1. Rerun the e2e to confirm the failures reproduce (not flaky).
  2. Compare against the TE-037 commit alone (`e49bd99`). If the same failures occur there, the cause is TE-037's stack, and the fix belongs under TE-037 or a separate story.
  3. Fix the real cause. Do not add axe exclusions or skip tests to pass the gate unless the owner approves it.
  4. Rerun `bash scripts/check.sh all` and confirm AC5 is green.

## Changelog
- **Added:** nothing (no new files or tests).
- **Changed:**
  - `composer.lock` only: `symfony/serializer` v8.1.8 → v8.1.11, clearing CVE-2026-121623, CVE-2026-122053 and CVE-2026-118515. No `composer.json` changes and no audit ignores.
  - Rework round 1: the `complex-components.spec.ts` async loading assertion is now independent of load (paused clock + `runFor(300)`).
- **Removed:** nothing.
- **Migrations:** none.
- **New env vars:** none.
- **Docker rebuild:** needed for the lockfile change (`docker compose rm -s -f -v app vite` then `docker compose up -d --build`).
- **Testers:** qa-engineer PASS (php 759/759, front 23 files, static OK, e2e 143/144 with one unrelated flake), security-privacy-reviewer PASS.
- **AC3 note:** `phpunit/phpunit` stays at 13.4.1 with CVE-2026-119193 (MEDIUM, dev-only, not in the `--no-dev` production image). The patched phpunit 13.4.4+ needs pest ≥5.3.2, which needs paratest ≥7.26.1. That is blocked by the hard-rule pin `brianium/paratest` 7.26.0.
- **AC6:** N/A, neither package is listed in `docs/tech-stack.md`.
- **AC8:** not yet verified; it is checked when PR #12 is rerun after the merge into `dev`.
- **Follow-ups:**
  1. Once the paratest pin is lifted, update pest, paratest and phpunit together.
  2. Flaky e2e `switches.spec.ts:86` ("html lang" stays "nl" under nprogress-busy when the run is under load; passes when run alone).
