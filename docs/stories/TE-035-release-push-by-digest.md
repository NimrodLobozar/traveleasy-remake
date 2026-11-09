---
id: TE-035
title: Release pushes the scanned image by digest and gates the tags
status: done
priority: should
epic: M0 CI/CD to GHCR (split from TE-031; order: TE-035, then TE-036)
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
---

## User story
As the owner, I want the image that GHCR receives to be exactly the image that was scanned and verified, so that a published
`vX.Y.Z` is what the pipeline checked.

## Context
Split from TE-031 (2026-11-09, size limits): AC1-4, AC8, AC9 here, word for word; AC5-7 in TE-036. Found in the TE-014 security
review (2026-11-03). Related: TE-014 AC4, AC6, AC3; `docs/plan.md` § 4 M0 CI bullet.

- **(Medium) Push rebuilds the image.** `.github/workflows/ci.yml`, the push step (~line 304), rebuilds the image instead of
  pushing the one that was scanned and verified. The published bytes can differ from the scanned bytes.
- **Depends on:** nothing. TE-036 is independent (a different file, `scan-weekly.yml` plus the ignore file); either order works.
  TE-034 (owner deploy) should preferably use an image released by this pipeline, but does not require it.

## Acceptance criteria
- [x] AC1: Given the release job built and scanned the `production` image locally, when it pushes, then it pushes by digest
      (no second build), and the pushed digest is the one that was scanned and verified.
- [x] AC2: Given the pushed image, when the job compares the rootfs `diff_ids` of the pushed image with those of the scanned local
      image, then they are equal; if they differ, the job fails and nothing is tagged.
- [x] AC3: Given the digest push succeeded and the comparison passed, when the tags `sha-<7 chars>` and (on a `v*.*.*` tag)
      `vX.Y.Z` are added, then each tag resolves to exactly the pushed digest at top level (the manifest itself, not a new
      index that wraps it), and no tag is `latest`. Allowed routes: `docker buildx imagetools create --prefer-index=false` if the
      buildx version supports it, a plain registry retag (`crane tag` or `oras tag`), or `docker tag` + `docker push` per tag.
      Whatever the route, the job asserts that every tag's digest equals the pushed digest.
      (Clarified 2026-11-09, round 1: `imagetools create --tag` on a single manifest creates a new manifest list, so the tag
      digest changes even though the content is the same. That does not meet this criterion.)
- [x] AC4: Given a tag push fails or the comparison fails, when the job ends, then no `sha-` or `vX.Y.Z` tag points at a
      different digest than the scanned one. Checked with `docker buildx imagetools inspect` (or `crane digest`) on each tag,
      compared with the pushed digest. The staging `ci-<sha7>` tag is outside this criterion (acceptable as is).
- [x] AC8: Given the workflow permissions after the change, when read, then only the release job has `packages: write` and
      `id-token`/`attestations` as before (TE-014 AC8 still holds).
- [~] AC9 (partly met): Given `actionlint` and the verify script, when the workflow runs on a branch, then both pass and the pushed digest is noted
      in the handoff.
      Met: actionlint clean and the verify logic passes (local registry test). Deferred by owner decision (2026-11-09): the real
      GHCR run and the noted digest happen at the next real release (dev to main, or the first vX.Y.Z).

## Quality criteria
- [ ] Tests: workflow linted with `actionlint`; a branch run where the comparison is forced to fail shows the job stops before any tag
      (evidence: run link, step name); a tagged test release (or a dry run) shows the digest match.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no personal access token; the `diff_ids` comparison is logged without secrets.
- [ ] i18n: n/a.
- [ ] Performance: no second image build on release; release job still under 12 minutes warm (TE-014).
- [ ] Docs updated: `docs/deploy/` (how a release is cut, how the digest is checked).

## Files to touch
- `.github/workflows/ci.yml` (release job, push step ~line 304): push by digest, `diff_ids` compare, tag with `imagetools create`.
- `scripts/ci/` : the verify script (`scripts/ci/verify-production-stack.sh` or a new `scripts/ci/compare-diff-ids.sh`).
- `docs/deploy/production-image.md`: how a release is cut and the digest checked.
- Tests: `actionlint` on the workflow; branch run with a forced mismatch.

## Out of scope
- Multi-arch images (server is amd64 only). Cosign signing (optional, TE-014).
- Changing the Trivy scan policy (severity threshold, scanners). Per-image ignore scoping (TE-036).

## Open questions
- (none)

## Notes
- From TE-036 (2026-11-09): the Playwright (Ubuntu) scan in `scan-weekly.yml` must pass an explicit empty `--ignorefile` (e.g. `/dev/null`), so a future `.trivyignore` cannot apply silently.

## Rework log
### Round 1 (2026-11-09), QA FAIL on commit 83ede7c
- **Tester:** qa-engineer.
- **Failed criteria:** AC3, AC4, and the "Verify every tag points at the scanned digest" step (`.github/workflows/ci.yml` ~line 340).
- **Passed, keep:** AC1, AC2, AC8, actionlint clean, `compare-diff-ids.sh` exit 0/1 behaviour, static gate PASS.
- **Evidence:** local `registry:2` test (Docker 29.9.2). `docker push` gave digest `sha256:266f…77d7` (a single Docker v2 manifest).
  After `docker buildx imagetools create --tag … repo@<digest>`, both `sha-abc1234` and `v0.0.1` resolved to `sha256:315e…56b5`,
  a NEW manifest list that wraps the scanned manifest. The final verify step would fail on a real release.
- **Likely cause:** `imagetools create` always writes an index, so the tags never point at the scanned digest.
- **Expected fix:** retag without creating an index (see the routes in AC3). The final verify step must compare each tag's digest
  with the pushed digest and fail on a mismatch.
- **Criteria clarified:** AC3 and AC4 (wording above). The staging `ci-<sha7>` tag is accepted and not covered by AC4.
- **Docs:** `docs/deploy/production-image.md` must state that SBOM and provenance attestations are no longer attached to the
  image (QA judgement; the push-by-digest route does not carry them). Keep that note in the Changelog too.
- **Still open (owner):** AC9 needs a real branch/main run with a forced mismatch, which needs a real GHCR push.

## Changelog
Closed 2026-11-09 (rework rounds: 1).
- **Added:** commit 83ede7c: release job pushes the scanned image by digest, `scripts/ci/compare-diff-ids.sh` (rootfs `diff_ids` gate),
  tags added with `docker buildx imagetools create`, per-tag verify step.
- **Changed:** commit 24475b8 (rework 1): `--prefer-index=false`, so `sha-` and `vX.Y.Z` keep the top-level pushed digest.
  Docs commit on `docs/deploy/casaos-portainer.md` and `docs/deploy/production-image.md` (attestation wording; never deploy `ci-*` tags).
- **Removed:** SBOM and provenance attestations are no longer attached to the image.
- **Migrations / env vars:** none. **Docker rebuild:** not needed (CI only).
- **Tests:** QA PASS round 2 (local `registry:2`: pushed digest equals tag digests; mismatch and unresolvable tag fail; actionlint
  clean; static gate pint/larastan/front-static PASS). Security PASS (the TE-014 "push rebuilds the image" finding is closed).
- **Known limitations:** attestations dropped for M0 (owner accepted; signed ones follow in TE-039). A staging `ci-<sha7>` tag exists
  on GHCR (cleanup in TE-039). AC9 real GHCR run deferred to the next real release (owner decision).
