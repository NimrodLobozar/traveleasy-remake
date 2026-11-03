---
id: TE-031
title: Release pushes the scanned image by digest, and Trivy ignores are scoped per image
status: ready
priority: should
epic: M0 CI/CD to GHCR
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the image that GHCR receives to be exactly the image that was scanned and verified, and the Trivy
ignore list to apply only to the image it was reviewed for, so that a published `vX.Y.Z` is what the pipeline checked.

## Context
Found in the TE-014 security review (2026-11-03). Related: TE-014 AC4, AC6, AC3; `docs/plan.md` § 4 M0 CI bullet.

- **(Medium) Push rebuilds the image.** `.github/workflows/ci.yml`, the push step (~line 304), rebuilds the image instead of
  pushing the one that was scanned and verified. The published bytes can differ from the scanned bytes.
- **(Low) Shared ignore list.** The `.trivyignore` IDs also apply in `.github/workflows/scan-weekly.yml` (~line 49), where the
  Playwright (Ubuntu) image is scanned. A finding accepted for the production image can be silently accepted for another image.

## Acceptance criteria
- [ ] AC1: Given the release job built and scanned the `production` image locally, when it pushes, then it pushes by digest
      (no second build), and the pushed digest is the one that was scanned and verified.
- [ ] AC2: Given the pushed image, when the job compares the rootfs `diff_ids` of the pushed image with those of the scanned local
      image, then they are equal; if they differ, the job fails and nothing is tagged.
- [ ] AC3: Given the digest push succeeded and the comparison passed, when the tags `sha-<7 chars>` and (on a `v*.*.*` tag)
      `vX.Y.Z` are added, then they are added with `docker buildx imagetools create` pointing at that digest, and no tag is `latest`.
- [ ] AC4: Given a tag push fails or the comparison fails, when the job ends, then no `sha-` or `vX.Y.Z` tag points at a
      different digest than the scanned one (verified with `docker buildx imagetools inspect`).
- [ ] AC5: Given `.trivyignore.yaml` with per-image `purls` entries, when the production image is scanned, then only entries scoped to
      that image apply; the Playwright (Ubuntu) image in `scan-weekly.yml` does not use the production entries.
- [ ] AC6: Given an ignore entry scoped to the production image, when the same finding appears in the Playwright image scan, then the
      scan reports it (the ignore does not apply).
- [ ] AC7: Given the existing 21 ignore entries, when they are migrated, then each keeps its ID, reason and expiry date (2026-12-15),
      and the security reviewer re-accepts the migrated file.
- [ ] AC8: Given the workflow permissions after the change, when read, then only the release job has `packages: write` and
      `id-token`/`attestations` as before (TE-014 AC8 still holds).
- [ ] AC9: Given `actionlint` and the verify script, when the workflow runs on a branch, then both pass and the pushed digest is noted
      in the handoff.

## Quality criteria
- [ ] Tests: workflow linted with `actionlint`; a branch run where the comparison is forced to fail shows the job stops before any tag
      (evidence: run link, step name); a tagged test release (or a dry run) shows the digest match.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no personal access token; the `diff_ids` comparison is logged without secrets; the migrated ignore file is
      reviewed by the security reviewer.
- [ ] i18n: n/a.
- [ ] Performance: no second image build on release; release job still under 12 minutes warm (TE-014).
- [ ] Docs updated: `docs/deploy/` (how a release is cut, how the digest is checked), `.trivyignore.yaml` header comment.

## Out of scope
- Multi-arch images (server is amd64 only). Cosign signing (optional, TE-014).
- Changing the Trivy scan policy (severity threshold, scanners) beyond the per-image scoping.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
