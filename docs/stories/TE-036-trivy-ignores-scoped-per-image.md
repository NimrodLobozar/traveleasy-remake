---
id: TE-036
title: Trivy ignores are scoped per image (migrate the 20 entries)
status: rework
priority: should
epic: M0 CI/CD to GHCR (split from TE-031; order: TE-035, then TE-036)
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
---

## User story
As the owner, I want the Trivy ignore list to apply only to the image it was reviewed for, so that a finding accepted for the
production image is not silently accepted for another image.

## Context
Split from TE-031 (2026-11-09, size limits): AC5-7 here, word for word; AC1-4, AC8, AC9 in TE-035. Found in the TE-014 security
review (2026-11-03). Related: TE-014 AC6.

- **(Low) Shared ignore list.** The `.trivyignore` IDs also apply in `.github/workflows/scan-weekly.yml` (~line 49), where the
  Playwright (Ubuntu) image is scanned. A finding accepted for the production image can be silently accepted for another image.
- The 20 entries expire 2026-12-15; migrating keeps that date. Do this before the expiry.
- **Depends on:** nothing (independent of TE-035; both edit `ci.yml` or its neighbours, so do not run them in parallel).

## Acceptance criteria
- [ ] AC5: Given `.trivyignore.yaml` with per-image `purls` entries, when the production image is scanned, then only entries scoped to
      that image apply (each Debian entry uses `pkg:deb/debian/<package>`, `pkg:deb/debian/*` only as a documented fallback; the go-jose golang purl also suppresses; verified locally with pinned Trivy 0.76.1 against Debian and Ubuntu targets); the Playwright (Ubuntu) image in `scan-weekly.yml` does not use the production entries.
- [ ] AC6: Given an ignore entry scoped to the production image, when the same finding appears in the Playwright image scan, then the
      scan reports it (the ignore does not apply).
- [ ] AC7: Given the existing 20 ignore entries, when they are migrated, then each keeps its ID, reason and expiry date (2026-12-15),
      and the security reviewer re-accepts the migrated file.

## Quality criteria
- [ ] Tests: workflow linted with `actionlint`; a scan run of each image proves AC5 and AC6 (evidence: run link, step name).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: the migrated ignore file is reviewed by the security reviewer.
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: `.trivyignore.yaml` header comment.

## Files to touch
- `.trivyignore` (replace by new `.trivyignore.yaml`): 20 entries with `purls`, ID, reason, expiry 2026-12-15.
- `.github/workflows/ci.yml` (production scan step): point Trivy at `.trivyignore.yaml`.
- `.github/workflows/scan-weekly.yml` (~line 49): scan the Playwright image without the production entries. The weekly scan's dev image (same Debian base as production) also uses `.trivyignore.yaml`; only the Playwright (Ubuntu) image is scanned without it. The Debian entries are also scoped by `purls: pkg:deb/debian`.
- `docs/deploy/production-image.md`: mention the per-image scoping if the scan section exists.

## Out of scope
- Changing the Trivy scan policy (severity threshold, scanners) beyond the per-image scoping. Push-by-digest (TE-035).

## Open questions
- (none)

## Rework log
### Round 1 (2026-11-09) - qa-engineer
- Failed: AC5, AC6. AC7 passed. Gate green (php 759, front 23 files, e2e 144 passed).
- Evidence (pinned Trivy 0.76.1, sbom mode, fake Debian 13 curl component): the namespace-only purl `pkg:deb/debian` suppresses nothing;
  CVE-2026-12064 and CVE-2026-8458 were still reported with the real `.trivyignore.yaml`. These forms do suppress: `pkg:deb/debian/curl`,
  `pkg:deb/debian/curl@<ver>`, `pkg:deb/debian/*`. An `ubuntu` purl does not suppress a Debian finding; the `?distro=` qualifier does not work.
  So today all 19 Debian entries do nothing.
- Clarified AC5: each Debian entry is scoped with its package purl `pkg:deb/debian/<package>` (preferred: the exact affected package;
  `pkg:deb/debian/*` only as a documented fallback). The go-jose golang purl must be shown to suppress too.
- Expected fix: rewrite the Debian purls to `pkg:deb/debian/<package>`.
- Required verification for AC5/AC6: a reproducible local check with pinned Trivy 0.76.1 showing (a) each entry suppresses its finding in a
  Debian target and (b) the same entry does not suppress it in an Ubuntu target (a fake SBOM is fine if it produces findings). A real Playwright
  image scan stays CI-verified after the push.

## Changelog
(filled when done)
