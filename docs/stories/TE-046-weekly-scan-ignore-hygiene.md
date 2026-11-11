---
id: TE-046
title: Weekly-scan ignore hygiene
status: ready
priority: should
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the weekly-scan ignore handling to be robust and narrowly scoped, and the ignores re-checked before they expire, so that the scheduled security signal stays trustworthy and no finding is hidden by accident.

## Context
Follow-up to TE-045 (dev and Playwright ignore files, expiry 2026-12-15). Review notes: the ignore merge in `scan-weekly.yml` is fragile, some Go purls are broader than needed, and all ignores expire on one date. Related: TE-036, TE-045.

## Acceptance criteria
- [ ] AC1: Given `scan-weekly.yml` (line ~46), when the ignore files are merged, then it uses `yq` (or an equivalent structural merge), or a guard step fails the job with a clear message if `.trivyignore.yaml` has any top-level key other than `vulnerabilities:`; no `cat`+`sed` merge remains unguarded.
- [ ] AC2: Given a test branch where `.trivyignore.yaml` gets an extra top-level key after `vulnerabilities:`, when the merge step runs, then the merged file still contains every ignore (or the job fails with the guard message); the result is noted in the PR.
- [ ] AC3: Given the Go `stdlib@v1.26.4` purls in `.trivyignore.dev.yaml`, when each is reviewed, then it is narrowed to the specific binary/path or package where Trivy's purl or `paths` allows it, or the entry's `statement` records why it cannot be narrowed.
- [ ] AC4: Given the weekly scan after AC1 and AC3, when `gh workflow run scan-weekly.yml` is dispatched on `dev`, then the run is green and no ignore became unused-and-hiding (every kept entry still matches a finding).
- [ ] AC5: Given the dev and Playwright ignore files, when re-checked before 2026-12-15, then each entry is removed (fixed upstream), kept with a renewed time-boxed `expired_at` and updated `statement`, or documented as no-fix with the upstream reference.
- [ ] AC6: Given Playwright `v1.65.0-noble`, when checked for a Microsoft rebuild, then the apt CVE-2026-109518 ignore is removed if the rebuilt image clears it; otherwise it is kept with a reason and new expiry.
- [ ] AC7: Given `docs/tech-stack.md`, when read, then ignores and expiry dates match the ignore files.

## Quality criteria
- [ ] Tests: no new unit or feature tests (infrastructure change). `bash scripts/check.sh static` is green (includes actionlint).
- [ ] Accessibility: not applicable.
- [ ] Security/privacy: `security-privacy-reviewer` re-reviews the ignore diff: exact purls, per-image scope only, no extension without a reason, prod image and PHP/Node CI scans unaffected.
- [ ] i18n: not applicable.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/tech-stack.md`.

## Files to touch
- `.github/workflows/scan-weekly.yml` (merge step ~line 46): `yq` merge or guard.
- `.trivyignore.dev.yaml`: narrow Go `stdlib@v1.26.4` purls; re-check entries.
- `.trivyignore.playwright.yaml`: re-check entries, apt CVE-2026-109518.
- `docs/tech-stack.md`.
- Tests: none new.

## Out of scope
- The prod image ignores and scans.
- Changing Trivy flags or the 20m timeout.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
