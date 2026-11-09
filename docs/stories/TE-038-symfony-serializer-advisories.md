---
id: TE-038
title: Fix symfony/serializer security advisories (composer audit gate)
status: in-progress
priority: must
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
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
- [ ] AC1: Given the branch `fix/te-038-symfony-serializer-advisories` off `dev`, when `composer update symfony/serializer --with-dependencies` runs in Docker, then `composer.lock` pins a `symfony/serializer` version that is patched for all three advisories.
- [ ] AC2: Given the updated lockfile, when `composer audit` runs in the app container, then it reports no advisory for `symfony/serializer` and no blocking advisory at all.
- [ ] AC3: Given a patched `phpunit/phpunit` exists within the current `composer.json` constraint, when the lockfile is updated, then it is pinned to that version; if none exists, the story notes this and CVE-2026-119193 stays as the existing non-blocking finding.
- [ ] AC4: Given the change, when the diff is reviewed, then no new ignore entry for these advisories exists in `ci.yml`, `composer.json` or any audit config, and `composer.json` constraints change only if a patched version requires it.
- [ ] AC5: Given the update, when `bash scripts/check.sh all` runs, then PHP tests (Pest, PostgreSQL), Pint, Larastan (level max, no baseline) and the frontend checks pass.
- [ ] AC6: Given the update, when `docs/tech-stack.md` is read, then the pins for `symfony/serializer` (and `phpunit/phpunit` if changed) match the lockfile.
- [ ] AC7: Given the dev stack is rebuilt with fresh dependency volumes, when the app starts, then it is healthy and `http://localhost:8000` responds.
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
(empty)

## Changelog
(filled when done)
