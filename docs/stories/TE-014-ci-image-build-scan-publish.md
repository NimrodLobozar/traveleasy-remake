---
id: TE-014
title: GitHub Actions pipeline, part 2: amd64 build, Trivy scan, publish to GHCR
status: ready
priority: must
epic: M0 CI/CD to GHCR
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want a green `main` to publish a scanned, tagged image to GHCR, so that Portainer can pull a pinned
version.

## Context
- `docs/plan.md` § 2 DoD row 3 (`ghcr.io/nimrodlobozar/traveleasy:vX.Y.0`; milestone N gives `v0.N.0`; first is `v0.0.x`),
  § 4 M0 CI bullet (build `linux/amd64`, Trivy, push `vX.Y.Z` + `sha-…`).
- Board note: image scan, `composer audit`/`npm audit` (the audits are in TE-013).
- **Depends on:** TE-012, TE-013. **Blocks:** TE-025 (secrets helper runs from this image), TE-026.

## Acceptance criteria
- [ ] AC1: Given quality gates are green on `main`, when the `release` job runs, then it builds the `production` target for
      `linux/amd64` only and tags it `sha-<7 chars>` (every `main` build).
- [ ] AC2: Given a git tag `vX.Y.Z` is pushed, when the job runs, then the image is tagged `vX.Y.Z` as well and never `latest`
      (pinned tags only); a tag that does not match `v*.*.*` publishes nothing.
- [ ] AC3: Given the built image, when Trivy scans it (OS and library vulnerabilities, secrets, misconfiguration), then the
      job fails on any High or Critical finding that is not in a reviewed `.trivyignore` (each line has an ID, a reason and
      an expiry date); the report is attached as a workflow artifact for 3 days (failure only if it contains secrets). The secret scan is
      explicitly enabled (`--scanners vuln,secret,misconfig`), and the Trivy scanner image (or action) is pinned by digest (TE-012 review).
- [ ] AC4: Given the Trivy scan fails, when the job ends, then nothing was pushed to GHCR (scan runs before push, on the
      locally loaded image).
- [ ] AC5: Given the dev image and the Playwright image, when the weekly scheduled workflow runs, then both are scanned and a
      failing scan opens a visible failure (red scheduled run); results are not blocking for `main` pushes.
- [ ] AC6: Given the verify script from TE-012, when the release job runs, then it runs against the built image before the
      push and the push is skipped if it fails.
- [ ] AC7: Given the pushed image, when `docker buildx imagetools inspect` is run, then it shows a single `linux/amd64`
      manifest with OCI labels (revision, source), and an SBOM and provenance attestation are attached.
- [ ] AC8: Given the workflow permissions, when read, then only the release job has `packages: write` and `id-token`/`attestations`
      as needed; it authenticates with `GITHUB_TOKEN`, with no personal access token.
- [ ] AC9: Given the repository, when the package is first published, then the story notes in its handoff whether the GHCR
      package is public (repo is public) or private; the pull command for Portainer is documented.
- [ ] AC10: Given a PR from a fork, when the workflow runs, then the image is built but not pushed.
- [ ] AC11 (carried over from TE-013 AC9): Given the first real GitHub runs of `ci.yml`, when one runs cold and one warm on an
      unchanged dependency set, then both timings are recorded in the handoff and the warm run finishes in under 15 minutes.
- [ ] AC12 (carried over from TE-013 quality criteria): Given a deliberately failing branch, when `ci.yml` runs on it, then the run
      is red, the failing step name shows in the summary, no later job runs, and the evidence (run link, step name) is noted in the
      handoff.
- [ ] AC13 (carried over from TE-013 AC7): Given a PR from a fork, when `ci.yml` runs, then no secrets are available, the token is
      read-only, and nothing is published; the run link is noted in the handoff.

## Quality criteria
- [ ] Tests: workflow linted with `actionlint`; the verify script is exercised; one real tagged release `v0.0.1` is run
      end to end and the image pulled on a clean machine (evidence in handoff).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no secrets in the image or logs; cosign or GitHub attestation optional but provenance on; Trivy
      ignore file reviewed by the security reviewer.
- [ ] i18n: n/a.
- [ ] Performance: build uses layer caching (registry or GHA cache); release job under 12 minutes warm.
- [ ] Docs updated: `docs/deploy/` (tags, how to cut a release, how to roll back by tag), `CLAUDE.md` Status.

## Out of scope
- Deploying to the server (TE-026). Multi-arch images (server is amd64 only).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
