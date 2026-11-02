---
id: TE-012
title: Production multi-stage image with entrypoint, health check and hardening
status: in-progress
priority: must
epic: M0 CI/CD to GHCR
agents: [devops-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want a small, hardened, non-root production image that migrates and checks itself on start, so that a
deploy is repeatable and safe.

## Context
- `docs/plan.md` § 4 M0 (ADR-0002 scope, multi-stage non-root image with health checks, entrypoint) and § 7.
- Board "Notes for the production / deploy epics" (production image stage, production config, devtools smoke check,
  production build, migrate-on-start, env documentation).
- **Depends on:** TE-011 (accepted ADR-0002), TE-007 (SSR bundle). **Blocks:** TE-014, TE-024, TE-025.
- Build target is `linux/amd64`. The dev stage in `Dockerfile` stays untouched in behaviour.

## Acceptance criteria
- [x] AC1: Given `docker build --target production .`, when it finishes, then the image has no dev dependencies
      (`composer install --no-dev --optimize-autoloader`), no `tests/`, `node_modules`, `.git`, `.env` or docs, and contains
      the built client + SSR bundles and a framework cache created by `php artisan optimize` (proves `route:cache` works).
- [x] AC2 (ADR-0002 D8, accepted 2026-11-02): Given the image runs, when `id` is checked, then it is uid/gid 10001 (no host
      counterpart; dev keeps 1000); the application code (`/var/www/html`) is owned by root, 0755 and read-only to that user; the
      only writable paths are tmpfs (`/tmp`, `storage/framework`, the config-cache path, Caddy `/data` and `/config`) and the
      `storage/app` volume, pre-created in the image with uid 10001.
- [x] AC14 (ADR-0002 D1 to D4, D9): Given the Dockerfile, when read and built, then the base is FrankenPHP in classic mode on
      port 8080 (not worker mode) with `setcap -r` applied and the Caddyfile from D1; the image contains the `node` binary only,
      and the SSR bundle is self-contained (`node bootstrap/ssr/ssr.js` runs without `node_modules`); the entrypoint has the
      `app`/`ssr` role switch and a pass-through for one-off commands (no DB wait, no boot side effects, so the secrets helper
      of TE-025 is not blocked by AC8); `traveleasy:seed-reference --if-empty` seeds reference data; caches are written at start
      to tmpfs; the health check sends the `Host: <APP_URL host>` header to `127.0.0.1:8080/up`. Fallback (Nginx + PHP-FPM) is
      documented only, not built.
- [x] AC3: Given the image runs with `cap_drop: [ALL]`, `no-new-privileges` and a read-only root filesystem, when it starts
      and serves `/up`, then it works (writable paths on tmpfs or volumes).
- [x] AC4: Given the PHP ini of the production stage, when inspected, then `display_errors=Off`, `expose_php=Off`,
      `opcache.validate_timestamps=0`, and the dev ini is not present.
- [~] AC5: Given a running container, when `HEALTHCHECK` fires, then it requests the app's health route and reports healthy
      only when the app answers 200 and the database is reachable; it reports unhealthy within 60 s of the DB going away.
- [~] AC6: Given the entrypoint, when the `app` container starts against an empty database, then it waits for the DB (with a
      timeout and a clear message), migrates, seeds `reference` only, and starts serving; on a non-empty DB it only migrates; it
      never seeds `demo` or the kit's `test@example.com` user. `worker`/`scheduler` run the same image but skip migrations.
- [~] AC7: Given a failed migration, when the entrypoint runs, then the container exits non-zero with the migration error
      (without printing env values), so the stack shows unhealthy instead of serving a half-migrated app.
- [ ] AC8: Given `APP_ENV=production` and `APP_DEBUG=true` (or `APP_KEY` empty), when the app boots, then it refuses to start
      with a clear message (boot check from `docs/plan.md` § 7).
- [ ] AC9: Given `APP_ENV=production`, when `GET /_inertia/devtools/entries` is requested, then it returns 404; the image
      sets `INERTIA_DEVTOOLS_ENABLED=false` by default.
- [x] AC10: Given the base images (PHP, Node, web server), when the Dockerfile is read, then they are pinned by tag **and**
      digest, and the final image carries OCI labels (source, revision, version, licence MIT).
- [x] AC11: Given the image is built twice from the same commit, when sizes are compared, then the size is recorded in
      `docs/deploy/` and does not exceed the ADR-0002 D9 estimate (about 860 MB uncompressed) by more than 25 % (about 1.07 GB).
- [ ] AC12: Given the app container runs behind a proxy, when `X-Forwarded-*` headers arrive from outside the configured
      proxy network, then they are ignored (TrustProxies limited to the NPM address/network from env, never `*`). Only
      `X-Forwarded-For`, `-Proto` and `-Port` are trusted (not `-Host`, not `Forwarded`); a feature test sends each header from a
      trusted and an untrusted source and shows the client IP, scheme and port change only for the trusted one.
- [x] AC13: Given `.env.example`, when read, then it documents `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`,
      `INERTIA_DEVTOOLS_ENABLED`, `TRUSTED_PROXIES` and `PASSKEYS_USER_HANDLE_SECRET` (the last as "unused until M1 decides").
- [x] AC15 (follow-up TE-007, ADR-0002 D2): Given `vite.config.ts` sets `ssr.noExternal: true`, when the SSR bundle runs in the
      production image (no `node_modules`), then it starts and renders `/` server-side; record the proof in the story.
- [x] AC16 (follow-up TE-015, 2026-11-02): Given the production web server, when a static file (e.g. `/favicon.ico`) is
      requested, then it carries `X-Content-Type-Options: nosniff` plus the CSP and frame headers (`frame-ancestors 'none'`),
      set by the web server since Laravel middleware does not see static files; a verify-script check proves it.
- [x] AC17 (follow-up TE-015): Given the production image, when its config is inspected, then `expose_php=Off`,
      `APP_ENV=production`, `APP_DEBUG=false` and the Inertia devtools are off; `docker/prod/verify.sh` asserts each.
- [ ] AC18 (follow-up TE-015): Given the production `ssr` service definition, when read, then it has no `env_file`, no secrets, no
      published port and is not on `npm_shared`; a test fails if any of these appears.

## Quality criteria
- [ ] Tests: a Pest/shell test for the boot check (AC8); a script `docker/prod/verify.sh` that asserts AC1 to AC5, AC9
      against the built image and runs in CI (TE-014); feature test for TrustProxies config (AC12).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no secrets baked into layers (checked with `docker history` and Trivy secret scan); `.dockerignore`
      keeps `tests/`, `.env`, `.git`, `docs/` out of the context; image runs read-only.
- [ ] i18n: n/a.
- [ ] Performance: image start to healthy under 30 s on the dev machine with a warm DB.
- [ ] Docs updated: `docs/deploy/` (image contents, entrypoint behaviour, rollback rule), `.env.example`.

## Out of scope
- Compose file for Portainer (TE-024), registry publishing (TE-014).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- 2026-11-02 devops (partial, backend items open). Built `--target production` for linux/amd64: 1.017 GB. `docker/prod/verify.sh` passes 40 checks
  (AC1-4, AC6/7 DB wait and migrate, AC9, AC10, AC14-17). SSR proof (AC15): the `ssr` container runs `node bootstrap/ssr/ssr.js` in the image
  without node_modules, and `/` returns server-rendered markup (`<h1`) via `INERTIA_SSR_URL`. Docs: `docs/deploy/production-image.md`.
  Marked `[~]` = infra done, backend needed: AC5 (`/up` must also check the DB: DiagnosingHealth listener), AC6 (the
  `traveleasy:seed-reference` command is a no-op stub added by devops), AC7 (failed-migration path proven only for the DB wait),
  AC8 boot check (production + APP_DEBUG/APP_KEY), AC12 TrustProxies from `TRUSTED_PROXIES` (X-Forwarded-For/-Proto/-Port only) with
  feature test, https URL generation; AC9 devtools route returns 404 (proven). AC18 (ssr service definition test) waits for the
  production compose (TE-024). Trivy scan runs in CI (TE-014).
