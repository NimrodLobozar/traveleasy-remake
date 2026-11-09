---
id: TE-039
title: Signed attestations on the pushed digest and ci-* staging tag cleanup
status: ready
priority: should
epic: M0 CI/CD to GHCR
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want signed provenance and SBOM attestations attached to the released image digest, and old `ci-*` staging tags
cleaned up, so that I can verify where a deployed image came from and GHCR does not fill with staging tags.

## Context
TE-035 made releases push the scanned image by digest; as a result the SBOM and provenance attestations of TE-014 are no longer
attached (owner accepted this for M0, decision 2026-11-09). This story restores them as signed GitHub attestations bound to the
pushed digest. Due before go-live (TE-034 should preferably deploy a released image). Updates TE-014 AC8 (the permissions): the
release job gets `id-token: write` and `attestations: write` in addition to `packages: write`; no other job does.

## Acceptance criteria
- [ ] AC1: Given the release job, when permissions are read, then only the release job has `id-token: write` and
      `attestations: write` (plus `packages: write`); workflow-level permissions stay read-only.
- [ ] AC2: Given the verify step passed (every tag equals the pushed digest), when the job continues, then it runs the SHA-pinned
      `actions/attest-build-provenance` with `subject-name: $IMAGE`, `subject-digest: $DIGEST` and `push-to-registry: true`.
- [ ] AC3: Given the scanned image, when the job continues, then Trivy writes a CycloneDX SBOM of that scanned image and the
      SHA-pinned `actions/attest-sbom` attaches it with the same `subject-name`, `subject-digest` and `push-to-registry: true`.
- [ ] AC4: Given the attestation steps ran, when the tags `sha-<7>` and `vX.Y.Z` are inspected, then they still resolve to the
      pushed digest (unchanged top-level digest); the job fails otherwise.
- [ ] AC5: Given a released `vX.Y.Z`, when the owner runs
      `gh attestation verify oci://ghcr.io/nimrodlobozar/traveleasy:vX.Y.Z --owner NimrodLobozar`, then it succeeds for provenance
      and SBOM. The command is documented in `docs/deploy/production-image.md`.
- [ ] AC6: Given a failed verify step or a failed digest comparison, when the job ends, then no attestation step ran.
- [ ] AC7: Given GHCR, when the retention rule runs (scheduled workflow or documented policy, devops chooses), then `ci-*` staging
      tags older than 14 days are removed, and `sha-*`, `vX.Y.Z` and the digests they point at are never removed.
- [ ] AC8: Given the workflow files, when linted, then `actionlint` is clean and all new actions are pinned to a full commit SHA.

## Quality criteria
- [ ] Tests: `actionlint`; dry-run of the retention rule listing what it would delete; proof at the next real release that
      `gh attestation verify` passes and the tag digests are unchanged (note the digest in the handoff).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no personal access token; OIDC only; permissions minimal and scoped to the release job; no secrets in logs.
- [ ] i18n: n/a.
- [ ] Performance: release job stays under 12 minutes warm.
- [ ] Docs updated: `docs/deploy/production-image.md` (verify command, attestation wording), `docs/stories/TE-014-*.md` AC8 note,
      `docs/decisions.md`.

## Files to touch
- `.github/workflows/ci.yml` (release job): permissions, Trivy SBOM step, two attest steps after the verify step.
- `.github/workflows/` new or existing scheduled workflow for `ci-*` cleanup (e.g. `actions/delete-package-versions`, SHA-pinned).
- `docs/deploy/production-image.md`: verify command, retention rule.
- Tests: `actionlint`; dry-run output of the cleanup.

## Out of scope
- Cosign keys or a separate signing service; multi-arch images; changing the Trivy scan policy.
- Enforcing attestation verification on the server (the owner verifies manually).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
