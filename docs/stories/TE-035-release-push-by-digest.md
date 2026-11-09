---
id: TE-035
title: Release pushes the scanned image by digest and gates the tags
status: ready
priority: should
epic: M0 CI/CD to GHCR (split from TE-031; order: TE-035, then TE-036)
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
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
- [ ] AC1: Given the release job built and scanned the `production` image locally, when it pushes, then it pushes by digest
      (no second build), and the pushed digest is the one that was scanned and verified.
- [ ] AC2: Given the pushed image, when the job compares the rootfs `diff_ids` of the pushed image with those of the scanned local
      image, then they are equal; if they differ, the job fails and nothing is tagged.
- [ ] AC3: Given the digest push succeeded and the comparison passed, when the tags `sha-<7 chars>` and (on a `v*.*.*` tag)
      `vX.Y.Z` are added, then they are added with `docker buildx imagetools create` pointing at that digest, and no tag is `latest`.
- [ ] AC4: Given a tag push fails or the comparison fails, when the job ends, then no `sha-` or `vX.Y.Z` tag points at a
      different digest than the scanned one (verified with `docker buildx imagetools inspect`).
- [ ] AC8: Given the workflow permissions after the change, when read, then only the release job has `packages: write` and
      `id-token`/`attestations` as before (TE-014 AC8 still holds).
- [ ] AC9: Given `actionlint` and the verify script, when the workflow runs on a branch, then both pass and the pushed digest is noted
      in the handoff.
- [ ] AC10: Given a push to `dev`, a same-repo PR or a fork PR, when `ci.yml` runs, then the `release` job (which runs
      `docker/prod/verify.sh` and `scripts/ci/verify-production-stack.sh`) has only `contents: read`, and login and push live in a
      separate `publish` job (`needs: release`) with a job-level `if:` that allows only a push to `refs/heads/main` or a `vX.Y.Z` tag
      and is the only job with `packages: write` (read together with AC8, `publish` is the "release job" meant there).
- [ ] AC11: Given a `vX.Y.Z` tag on a commit that is not reachable from `origin/main`, when the `publish` job runs, then it checks
      reachability (e.g. `git merge-base --is-ancestor <tag commit> origin/main` after a full-depth fetch) and fails before any login,
      push or tag, with a message naming the tag and commit; a tag on a `main` commit still publishes.

## Quality criteria
- [ ] Tests: workflow linted with `actionlint`; a branch run where the comparison is forced to fail shows the job stops before any tag
      (evidence: run link, step name); a tagged test release (or a dry run) shows the digest match.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no personal access token; the `diff_ids` comparison is logged without secrets.
- [ ] i18n: n/a.
- [ ] Performance: no second image build on release; release job still under 12 minutes warm (TE-014).
- [ ] Docs updated: `docs/deploy/` (how a release is cut, how the digest is checked).

## Files to touch
- `.github/workflows/ci.yml` (release job, push step ~line 304): push by digest, `diff_ids` compare, tag with `imagetools create`;
  split login and push into a `publish` job (`needs: release`, `if:` main push or `vX.Y.Z` tag, `packages: write` only there; `release`
  gets `contents: read`; permissions ~lines 186-188); add the tag-on-main check (~lines 211-215); fix the comment at lines 7-8 that
  says fork PRs behave exactly like same-repo runs (a fork's token is read-only).
- `scripts/ci/` : the verify script (`scripts/ci/verify-production-stack.sh` or a new `scripts/ci/compare-diff-ids.sh`).
- `docs/deploy/production-image.md`: how a release is cut and the digest checked.
- Tests: `actionlint` on the workflow; branch run with a forced mismatch.

## Out of scope
- Restricting `v*` tags with a repo ruleset (the owner does this in GitHub settings).
- Multi-arch images (server is amd64 only). Cosign signing (optional, TE-014).
- Changing the Trivy scan policy (severity threshold, scanners). Per-image ignore scoping (TE-036).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
