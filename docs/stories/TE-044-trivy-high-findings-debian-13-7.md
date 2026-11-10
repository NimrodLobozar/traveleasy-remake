---
id: TE-044
title: Clear the 9 High Trivy findings in the prod image (FrankenPHP 1.13.5, apt 3.0.4, scoped ignores for no-fix krb5/sqlite)
status: in-progress
priority: must
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the production image to pass the Trivy High gate again, so that the Release job and the weekly scan are green and images can be published.

## Context
Since 2026-11-09 the Release job's Trivy scan, and the weekly scan, fail on 9 High findings in the prod image (Debian 13.7). The last green Release was 2026-11-03 (run 41648168430). Triage by `security-privacy-reviewer` (report artifact of run 42780932582):
- **Fix available:**
  - CVE-2026-109518: `apt` and `libapt-pkg7.0` 3.0.3 → 3.0.4.
  - CVE-2026-78661: `golang.org/x/net` v0.61.0 → v0.62.0 inside `/usr/local/bin/frankenphp`. Fixed by FrankenPHP 1.13.5.
- **No fix yet** (time-boxed ignores, see AC3):
  - CVE-2026-112321, CVE-2026-112322, CVE-2026-112323, CVE-2026-119079: krb5 kadmind/KDC DoS in `libgssapi-krb5-2`, `libk5crypto3`, `libkrb5-3`, `libkrb5support0` 1.21.3-5+deb13u1.
  - CVE-2026-119374, CVE-2026-119379: `libsqlite3-0` 3.46.1-7+deb13u2.
  - Not exploitable here: only client libraries are present, the app uses PostgreSQL, the process runs as uid 10001 with `cap_drop: ALL`. The ignores are a stopgap, not a verdict.

Related: TE-036 (per-image Trivy ignore scoping, format to follow), TE-035 (release gate), TE-043 (last CI fix, branch base), TE-038 (similar earlier advisory story).

Branch: `fix/te-044-trivy-high-debian-13-7`, off `origin/dev` once PR #18 (TE-043) is merged. If #18 is not merged yet, stack it on `fix/te-043-actionlint-sc2086`.

## Acceptance criteria
- [ ] AC1: Given the Dockerfile, when the FrankenPHP base is bumped, then `FRANKENPHP_VERSION` is `1.13.5` (from `1.13.4`) and `FRANKENPHP_DIGEST` is re-pinned to the digest of the `1.13.5` tag for the same PHP and Debian variant, checked with `docker buildx imagetools inspect`.
- [ ] AC2: Given the rebuilt prod image, when `docker run --rm --entrypoint dpkg <prod-image> -s apt libapt-pkg7.0` runs, then both report `3.0.4` or later. If the new base already provides it, no extra `apt-get upgrade` is needed; otherwise the production stage runs an explicit `apt-get upgrade` for `apt` and `libapt-pkg7.0` only, with the apt lists cleaned afterwards.
- [ ] AC3: Given `.trivyignore.yaml`, when the six no-fix IDs are added, then each has the exact purl of the affected package (as Trivy reports it), a `statement` with the reason above, `expired_at: 2026-12-15`, and the per-image scope used by TE-036 (prod image only; the dev image and the PHP/Node CI scans are not covered). No ignore is added for CVE-2026-109518 or CVE-2026-78661, because they are fixed.
- [ ] AC4: Given the same pinned Trivy image and flags as the CI Release job, when the scan runs locally on the rebuilt prod image, then it reports 0 High or Critical findings that are not ignored. The log path is recorded in the changelog.
- [ ] AC5: Given the PR into `dev`, when CI runs, then the Release job (Trivy gate) is green on the PR, and after the merge a manual dispatch of the weekly scan workflow passes.
- [ ] AC6: Given the change, when `docs/tech-stack.md` is read, then the FrankenPHP pin (1.13.5) and the apt pin (3.0.4) match the Dockerfile, and the six ignores are listed with their expiry date 2026-12-15.

## Quality criteria
- [ ] Tests: no new unit or feature tests (infrastructure change). `bash scripts/check.sh static` is green, and the prod image builds and starts healthy (`/up` returns 200).
- [ ] Accessibility: not applicable (no UI change).
- [ ] Security/privacy: `security-privacy-reviewer` re-reviews the ignore diff. Each ignore has exact purl and scope, the statement is true for this image, no ignore is broader than the six IDs, and the expiry is not extended. The FrankenPHP digest is verified against the upstream tag.
- [ ] i18n: not applicable.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/tech-stack.md` (FrankenPHP and apt pins, ignore list and expiry).

## Files to touch
- `Dockerfile`: `ARG FRANKENPHP_VERSION` (line ~19) and `ARG FRANKENPHP_DIGEST` (line ~20); `production` stage (line ~147) only if AC2 needs an explicit apt upgrade.
- `.trivyignore.yaml`: six new entries (format of the existing util-linux entries, lines ~14-65, plus the TE-036 per-image scope).
- `docs/tech-stack.md`: FrankenPHP and apt pins, ignore list.
- `.github/workflows/ci.yml` and `.github/workflows/scan-weekly.yml`: read-only unless the ignore scoping needs a change (then note it in the changelog).
- Tests: none new.

## Out of scope
- The dev image and the PHP/Node base images (not in the failing scan).
- Upgrading Debian or the PHP version.
- Removing the existing util-linux ignores (they expire on their own date, TE-036 covers them).
- Fixing the krb5 or sqlite packages (no upstream fix yet). Revisit before 2026-12-15.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
