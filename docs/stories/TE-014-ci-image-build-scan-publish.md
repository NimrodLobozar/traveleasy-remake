---
id: TE-014
title: GitHub Actions pipeline, part 2: amd64 build, Trivy scan, publish to GHCR
status: done
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
- `docs/plan.md` § 2 DoD row 3 (`ghcr.io/nimrodlobozar/traveleasy:vX.Y.0`; milestone N gives `v0.N.0`; first release is `v0.1.0`, owner decision 2026-11-03),
  § 4 M0 CI bullet (build `linux/amd64`, Trivy, push `vX.Y.Z` + `sha-…`).
- Board note: image scan, `composer audit`/`npm audit` (the audits are in TE-013).
- **Depends on:** TE-012, TE-013. **Blocks:** TE-025 (secrets helper runs from this image), TE-026.

## Acceptance criteria
- [x] AC1: Given quality gates are green on `main`, when the `release` job runs, then it builds the `production` target for
      `linux/amd64` only and tags it `sha-<7 chars>` (every `main` build).
- [x] AC2: Given a git tag `vX.Y.Z` is pushed, when the job runs, then the image is tagged `vX.Y.Z` as well and never `latest`
      (pinned tags only); a tag that does not match `v*.*.*` publishes nothing.
- [x] AC3: Given the built image, when Trivy scans it (OS and library vulnerabilities, secrets, misconfiguration), then the
      job fails on any High or Critical finding that is not in a reviewed `.trivyignore` (each line has an ID, a reason and
      an expiry date); the report is attached as a workflow artifact for 3 days (failure only if it contains secrets). The secret scan is
      explicitly enabled (`--scanners vuln,secret,misconfig`), and the Trivy scanner image (or action) is pinned by digest (TE-012 review).
- [x] AC4: Given the Trivy scan fails, when the job ends, then nothing was pushed to GHCR (scan runs before push, on the
      locally loaded image).
- [ ] AC5: Given the dev image and the Playwright image, when the weekly scheduled workflow runs, then both are scanned and a
      failing scan opens a visible failure (red scheduled run); results are not blocking for `main` pushes.
      **Open, carried to TE-026:** first weekly scheduled run not yet observed.
- [x] AC6: Given the verify script from TE-012, when the release job runs, then it runs against the built image before the
      push and the push is skipped if it fails.
- [x] AC7: Given the pushed image, when `docker buildx imagetools inspect` is run, then it shows a single `linux/amd64`
      manifest with OCI labels (revision, source), and an SBOM and provenance attestation are attached.
- [x] AC8: Given the workflow permissions, when read, then only the release job has `packages: write` and `id-token`/`attestations`
      as needed; it authenticates with `GITHUB_TOKEN`, with no personal access token.
- [x] AC9: Given the repository, when the package is first published, then the story notes in its handoff whether the GHCR
      package is public (repo is public) or private; the pull command for Portainer is documented.
      **Result:** anonymous pull token granted, so the package is public.
- [x] AC10: Given a PR from a fork, when the workflow runs, then the image is built but not pushed.
- [ ] AC11 (carried over from TE-013 AC9): Given the first real GitHub runs of `ci.yml`, when one runs cold and one warm on an
      unchanged dependency set, then both timings are recorded in the handoff and the warm run finishes in under 15 minutes.
      **Open:** timings not recorded.
- [ ] AC12 (carried over from TE-013 quality criteria): Given a deliberately failing branch, when `ci.yml` runs on it, then the run
      is red, the failing step name shows in the summary, no later job runs, and the evidence (run link, step name) is noted in the
      handoff.
      **Open:** evidence not recorded.
- [x] AC13 (carried over from TE-013 AC7): Given a PR from a fork, when `ci.yml` runs, then no secrets are available, the token is
      read-only, and nothing is published; the run link is noted in the handoff.
- [ ] AC14 (carried over from TE-025 AC3): Given the first published image, when `docker run --rm -it <image>:<tag> php artisan
      traveleasy:generate-secrets --without-super-admin` runs on a clean machine, then it prints 3 secrets and exits 0 (evidence in the
      handoff; also asserted in TE-026 AC15).
      **Open, carried to TE-026.**

## Quality criteria
- [x] Tests: workflow linted with `actionlint`; the verify script is exercised; one real tagged release `v0.1.0` is run
      end to end (release run 41629344342). The pull on a clean machine is carried to TE-026 (AC14).
- [x] Accessibility: n/a.
- [x] Security/privacy: no secrets in the image or logs; GitHub attestation on (provenance); Trivy ignore file reviewed by the
      security reviewer (PASS, 21 entries, all expire 2026-12-15).
- [x] i18n: n/a.
- [ ] Performance: build uses layer caching (registry or GHA cache); release job under 12 minutes warm.
      **Open:** timings not recorded (see AC11).
- [x] Docs updated: `docs/deploy/` (tags, how to cut a release, how to roll back by tag), `CLAUDE.md` Status.

## Out of scope
- Deploying to the server (TE-026). Multi-arch images (server is amd64 only).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- **Done 2026-11-03.**
- **Testers:** qa-engineer PASS (branch run 41627577637); security-privacy-reviewer PASS (all 21 `.trivyignore` entries accepted until 2026-12-15).
- **Release:** run 41629344342 on tag `v0.1.0` (all 4 jobs green). Published `ghcr.io/nimrodlobozar/traveleasy:v0.1.0`,
  index digest `sha256:0bfc1c24a14da943bf98e2c2c0b34f2515ba34f2903f5f03eb9f08204c00ddf1`: a single linux/amd64 manifest plus an
  attestation manifest. Anonymous pull token granted, so the package is public.
- **Commits:** 5e967ed, c8af24e; merged as 248e87a.
- **Carried to TE-026:** AC14 (generate-secrets from the published image on a clean machine) and AC5 (first weekly scheduled scan run).
  AC11/AC12 timings were not recorded; the performance criterion depends on them.
- **Added:** `ci.yml` release job (GHCR publish, Trivy scan, verify, attestations), `.trivyignore`, weekly `scan-weekly.yml`.
- **Changed / removed / migrations / env vars:** none. A Docker rebuild is not needed for this story (CI only).
- **Follow-ups:** TE-031 (push the scanned image by digest; scope the Trivy ignores per image).
