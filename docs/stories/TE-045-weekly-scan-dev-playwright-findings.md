---
id: TE-045
title: Weekly scan green: dev and Playwright image findings
status: ready
priority: must
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the weekly Trivy scan of the dev and Playwright images to pass, so that the scheduled security signal is green again and I can trust it.

## Context
Run 42789826447 (on the TE-044 branch, `scan-weekly.yml`) found 77 findings in the dev image (`traveleasy-remake-dev:local`, php:8.5-cli base): 1 Critical and about 60 High in `linux-libc-dev` (CVE-2026-99083, fixed in 6.12.115-2), `apt` / `libapt-pkg` 3.0.3 (CVE-2026-109518, fixed in 3.0.4), plus several findings with no fix yet. The Playwright image also has several High findings. These probably predate TE-044, which only covers the prod image. The weekly scan has been red since 2026-11-09.

Related: TE-044 (prod image, moved the weekly-scan part here), TE-036 (per-image Trivy ignore scoping), TE-043 (last weekly-scan workflow fix).

## Acceptance criteria
- [ ] AC1: Given the dev image and the Playwright image, when the triage runs with the same Trivy image and flags as `scan-weekly.yml`, then every finding is listed per image with one of: fixed by a base or package bump, fixed by a re-pin, or no fix yet (with the upstream reference).
- [ ] AC2: Given the findings, when the fixable ones are handled, then the php:cli base for the dev image and the Playwright image pin are bumped or re-pinned to a digest that clears them, or the dev build upgrades `linux-libc-dev` and `apt`/`libapt-pkg` explicitly (cleaned apt lists afterwards); the resulting Trivy scan has no High or Critical finding that is fixable.
- [ ] AC3: Given the no-fix findings, when an ignore is needed, then it is a time-boxed reviewed entry in `.trivyignore*`, scoped to the dev or the Playwright image only (never the prod image or the PHP/Node CI scans), with the exact purl, a `statement`, and an `expired_at` no later than 2026-12-15.
- [ ] AC4: Given the branch with the changes merged to `dev`, when `gh workflow run scan-weekly.yml` is dispatched, then the run is green.
- [ ] AC5: Given `docs/tech-stack.md`, when it is read, then the dev image base, the Playwright image pin and any new ignores with their expiry dates match the Dockerfile and ignore files.
- [ ] AC6: Given the Dockerfile and compose changes, when the devops-engineer rebuilds the dev stack (`docker compose rm -s -f -v app vite` then `docker compose up -d --build`), then the app and vite services are healthy and `/up` returns 200.

## Quality criteria
- [ ] Tests: no new unit or feature tests (infrastructure change). `bash scripts/check.sh static` is green; the dev stack starts healthy after the rebuild.
- [ ] Accessibility: not applicable (no UI change).
- [ ] Security/privacy: `security-privacy-reviewer` re-reviews the ignore diff. Each ignore has exact purl and dev or Playwright scope only, the statement is true for that image, no ignore is broader than its findings, expiry is not extended, and no base image is pinned to an unverified digest.
- [ ] i18n: not applicable.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/tech-stack.md` (dev base, Playwright pin, ignores and expiry).

## Files to touch
- `Dockerfile`: dev stages (php:cli base, `ARG` pins, any explicit apt upgrade for `linux-libc-dev`, `apt`, `libapt-pkg`).
- Playwright image pin: `compose.dev.yaml` (or wherever the Playwright image is set; confirm with `search_code` for `playwright` before editing).
- `.trivyignore*` (dev and Playwright scoped entries only; format per TE-036).
- `.github/workflows/scan-weekly.yml`: read-only unless the per-image scope needs a change (then note it in the changelog).
- `docs/tech-stack.md`.
- Tests: none new.

## Out of scope
- The prod image (TE-044).
- Upgrading Debian or the PHP major version.
- Fixing upstream packages that have no fix yet; those get time-boxed ignores only.
- Removing existing ignores that expire on their own date (TE-036).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
