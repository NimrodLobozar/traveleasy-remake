---
id: TE-028
title: Production image hardening follow-ups (chore)
status: done
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
- [x] AC3 (privacy): Given the `/up` route, when requested, then the response is a minimal plain-text or empty body with no external
      references (no `fonts.bunny.net` or `jsdelivr` preconnects, no `http(s)://` host at all); a feature test asserts that no external
      host appears in the body, and the health check of TE-012 still passes (200 only when the DB is reachable).

## Quality criteria
- [x] Tests: `verify.sh` extended for AC1 and AC2; Pest feature test for AC3; no regression in the existing 40 verify checks.
- [x] Accessibility: n/a.
- [x] Security/privacy: security-privacy-reviewer confirms the three findings are closed; the image size stays under 1.07 GB.
- [x] i18n: n/a.
- [x] Performance: image start to healthy stays under 30 s.
- [x] Docs updated: `docs/deploy/production-image.md`.

## Out of scope
- Compose hardening (TE-024), Trivy and publishing (TE-014).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- **Date:** 2026-11-02
- **Testers:**
  - qa-engineer: PASS (image 1.02 GB, `verify.sh` all checks passed, healthy in 7 s, Pest 625, mutation proved the ownership check fails).
  - security-privacy-reviewer: PASS (no findings).
- **Commits:**
  - `2655be7`: root-owned copies via `--chown=0:0`, Caddyfile 404 for `/.*` except `/.well-known/*`, `.htaccess` removed.
  - `96a3a46`: plain-text `/up` (`OK` / `Unavailable`), `app/Http/Controllers/HealthController.php`.
- **Added:** `app/Http/Controllers/HealthController.php`; Caddyfile rule returning 404 for hidden top-level paths except `/.well-known/*`; `verify.sh` checks for AC1 and AC2.
- **Changed:** production `Dockerfile` copies `public/` and `bootstrap/ssr/ssr.js` with `--chown=0:0`; `/up` returns plain text without external references.
- **Removed:** kit `public/.htaccess` from the production image.
- **Migrations:** none.
- **New env vars:** none.
- **Docker rebuild:** yes, the production image must be rebuilt (Dockerfile and Caddyfile changed).
- **Note for later:** the Caddyfile rule covers top-level dotfiles only; `verify.sh` would catch a nested one in `public/`.

