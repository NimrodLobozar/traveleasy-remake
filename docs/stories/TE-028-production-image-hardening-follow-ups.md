---
id: TE-028
title: Production image hardening follow-ups (chore)
status: in-progress
priority: must
epic: M0 CI/CD to GHCR
agents: [devops-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the last Low findings of the TE-012 security review closed, so that the production image has no root-writable
leftovers, no exposed hidden files and no third-party references before the first deploy.

## Context
- Source: the TE-012 security review (PASS, 2026-11-02), three Low findings. `docs/deploy/production-image.md` describes the image.
- **Depends on:** TE-012. **Should land before:** TE-014 (the verify script runs in CI) and TE-024.

## Acceptance criteria
- [x] AC1 (file ownership): Given the production `Dockerfile`, when read, then `COPY --from=build` of `public/` and
      `bootstrap/ssr/ssr.js` uses `--chown=0:0`; and `docker/prod/verify.sh` fails when any file outside the writable storage paths
      (`storage/app` volume and the tmpfs paths) is not owned by root (proven by a deliberate failing fixture or `docker run` check).
- [x] AC2 (hidden files): Given the running production image, when `GET /.htaccess`, `GET /.env` and `GET /.git/config` are requested,
      then each returns 404, including the kit's `public/.htaccess` (removed in the production stage, or denied by the Caddyfile for
      `/.*`); and `GET /.well-known/` paths keep working (a request for an existing file under `public/.well-known/` returns 200,
      a missing one 404). `verify.sh` checks both.
- [ ] AC3 (privacy): Given the `/up` route, when requested, then the response is a minimal plain-text or empty body with no external
      references (no `fonts.bunny.net` or `jsdelivr` preconnects, no `http(s)://` host at all); a feature test asserts that no external
      host appears in the body, and the health check of TE-012 still passes (200 only when the DB is reachable).

## Quality criteria
- [ ] Tests: `verify.sh` extended for AC1 and AC2; Pest feature test for AC3; no regression in the existing 40 verify checks.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: security-privacy-reviewer confirms the three findings are closed; the image size stays under 1.07 GB.
- [ ] i18n: n/a.
- [ ] Performance: image start to healthy stays under 30 s.
- [ ] Docs updated: `docs/deploy/production-image.md`.

## Out of scope
- Compose hardening (TE-024), Trivy and publishing (TE-014).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
