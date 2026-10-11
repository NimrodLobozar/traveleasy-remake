---
id: TE-002
title: Docker dev environment with hot reload and a PostgreSQL named volume
status: ready
priority: must
epic: M0 Docker dev environment
agents: [devops-engineer, backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer, I want to start the whole app with one `docker compose up` on my Windows PC and see code changes
in the browser without rebuilding, so that I never install PHP, Node or PostgreSQL on Windows and the dev setup
matches how the app runs on the home server.

## Context
- `CLAUDE.md` § Docker dev workflow and `docs/plan.md` § 4 M0 (scope, exit criterion "a code change shows up in the
  browser without a rebuild (checked on Windows)", risk "file watching on Windows bind mounts is unreliable").
- Legacy (`docs/legacy-analysis.md` § 3 Security): default DB passwords in compose. This story must not repeat it.
- ADR-0005: no mail catcher (no Mailpit).
- **Depends on:** TE-001 (the image installs its `composer.json` / `package.json`). **Blocks:** TE-003, TE-004,
  TE-005 (their commands run in these containers) and TE-006.
- The repo lives at `C:\dev\traveleasy-remake` on NTFS (outside OneDrive); Docker Desktop is assumed to use the
  WSL 2 backend (see Open questions). Bind mounts from NTFS don't deliver file events reliably, hence polling.
- **`compose.yaml` vs `compose.dev.yaml`.** `compose.yaml` holds what dev and production share: the `db` service
  (pinned image, named volume, health check, no host port, secrets from env) and the network. The production
  `app` definition (GHCR image, `ssr`, `worker`, `scheduler`, limits, NPM network) is added by the "First
  home-server deploy" and "CI/CD" epics. `compose.dev.yaml` adds the dev-only parts: the dev build of `app`, the
  `vite` service, bind mounts, anonymous dependency volumes and localhost ports.
- **SSR stays in the SSR epic.** No `ssr` service or profile here. Structure `compose.dev.yaml` so the SSR epic can
  add an `ssr` profile without restructuring (e.g. a shared Node service definition via a YAML anchor or
  `extends`).
- Starter-kit pages are placeholders; nothing visual is designed or audited here.

## Acceptance criteria
- [ ] AC1 (one command): Given `COMPOSE_FILE` in `.env` (documented in `.env.example`, with the path separator set so
      it works in PowerShell and Git Bash), when the developer runs plain `docker compose up -d --build` in the repo
      root, then both `compose.yaml` and `compose.dev.yaml` are used.
- [ ] AC2 (first run): Given a fresh clone on Windows 11 with Docker Desktop, `.env` copied from `.env.example` and
      only `DB_PASSWORD` filled in, when the developer runs `docker compose up -d --build`, then
      `docker compose ps` shows every service running and `db` as `healthy` within 5 minutes; after
      `docker compose exec app php artisan key:generate` and `docker compose exec app php artisan migrate`,
      `http://localhost:8000` shows the starter-kit welcome page with its CSS and JS loaded (no 404s or console
      errors for assets).
- [ ] AC3 (required secret): Given `DB_PASSWORD` is empty or missing, when the developer runs `docker compose up`,
      then compose refuses to start and the error names `DB_PASSWORD` (`${DB_PASSWORD:?...}`). No service has a
      default password anywhere in either compose file.
- [ ] AC4 (frontend HMR on Windows): Given the stack is running, when the developer edits and saves a `.tsx` page
      component or a Tailwind class on Windows, then the open browser tab shows the change **within 3 seconds**
      without a manual refresh, without a rebuild and without restarting a container. The page's asset URLs point
      to `http://localhost:5173` (not a container IP or hostname).
- [ ] AC5 (polling): Vite uses polling (`server.watch.usePolling`) controlled by env (e.g. `VITE_USE_POLLING=true`,
      `VITE_POLLING_INTERVAL`, both in `.env.example`); `vendor`, `node_modules`, `storage`, `public/build` and
      `.git` are excluded from watching. With the stack idle for 60 seconds, `docker stats` shows the `vite`
      container below 10 % CPU on average.
- [ ] AC6 (backend reload): Given the stack is running, when the developer edits a controller, a route file or a
      Blade template and refreshes the browser, then the change is visible on that refresh, without a container
      restart (OPcache, if enabled in dev, revalidates timestamps on every request).
- [ ] AC7 (dependency volumes): `vendor` and `node_modules` are installed in the image and mounted as **anonymous
      volumes** over the bind mount. `docker compose exec app ls vendor` and `docker compose exec vite ls node_modules`
      list packages, while the host folders `vendor/` and `node_modules/` contain no files (empty mount-point
      folders created by Docker are acceptable).
- [ ] AC8 (dependency change): Given a package is added to `composer.json` or `package.json` (with the lockfile
      updated inside the container), when the developer runs `docker compose up -d --build -V`, then the new package
      is available in the container, and the database data from before is still there.
- [ ] AC9 (database volume): PostgreSQL (pinned image tag, at least major.minor, e.g. `postgres:17.x-alpine`) stores
      its data in a **named** volume. Data created before `docker compose down` and before
      `docker compose up -d --build -V` is still there afterwards.
- [ ] AC10 (DB not exposed): `docker compose port db 5432` returns nothing and neither compose file has a `ports:`
      entry on `db`. The app reaches the DB over the internal network as host `db`.
- [ ] AC11 (test database): On first initialisation of the DB volume, a second database `traveleasy_test`, owned by
      the app's DB user, is created by an init script, so TE-004 can run tests against PostgreSQL. The handoff says how
      to create it on an existing volume (init scripts only run on an empty volume).
- [ ] AC12 (localhost only): The app (`APP_PORT`, default 8000) and Vite (`VITE_PORT`, default 5173) ports are
      published on `127.0.0.1` only, so the dev server isn't reachable from the LAN. Both are in `.env.example`.
- [ ] AC13 (in-container commands): these work from the repo root and are listed in the handoff:
      `docker compose exec app php artisan <cmd>`, `docker compose exec app composer <cmd>`,
      `docker compose exec vite npm <cmd>`, `docker compose exec app php artisan test`.
- [ ] AC14 (services): `docker compose config --services` lists exactly the dev services (`app`, `vite`, `db`, or
      equivalent names stated in the handoff). There is no mail catcher, no `ssr`, worker or scheduler service yet.
- [ ] AC15 (base file stands alone): `docker compose -f compose.yaml config` succeeds with the required env set,
      and `compose.yaml` contains no bind mounts of the source, no Vite service and no dev ports.
- [ ] AC16 (line endings): Given a clone made with `core.autocrlf=true`, the containers still start (shell scripts are
      LF via `.gitattributes`, which already forces LF by default; any entrypoint script is covered).
- [ ] AC17 (starter tests on PostgreSQL): `docker compose exec app php artisan test` runs the starter-kit tests
      against PostgreSQL and passes: `phpunit.xml` sets `DB_CONNECTION=pgsql` and `DB_DATABASE=traveleasy_test`
      (no SQLite in-memory). TE-004 then adds the safety guard and the arch tests.

## Quality criteria
- [ ] Tests: QA runs AC2–AC17 manually on Windows and records timings for AC4 and AC5 in the verdict.
- [ ] Accessibility: not applicable (no UI change).
- [ ] Security/privacy:
  - No default or example passwords in compose files, Dockerfiles or `.env.example`; secrets only from `.env`.
  - `.env.example` documents every variable used by the compose files and the app, each with a comment.
  - `app` and `vite` run as a non-root user; files they create (e.g. `php artisan make:model`) are editable from
    Windows.
  - All base images are pinned to at least major.minor (no `latest`), official images only.
  - No secrets are baked into images (`docker history` shows no `.env` content); `.dockerignore` excludes `.env`,
    `vendor`, `node_modules`, `.git`, `storage/logs`.
  - The DB health check doesn't print the password.
- [ ] i18n: not applicable.
- [ ] Performance: AC4 (≤ 3 s HMR) and AC5 (idle CPU) are the budget. First `up --build` time is reported, not gated.
- [ ] Docs updated: the handoff lists the commands, ports, service names, image versions and the "existing volume →
      create test DB" step for TE-006.
- [ ] Docker rebuild: n/a (this story creates the environment).

## Out of scope
- `docs/deploy/dev.md` and `docs/testing.md` (TE-006).
- The `ssr` dev profile (SSR epic), queue worker and scheduler services (M1), the production image and ADR-0002,
  production `compose.yaml` services and limits, CI/GHCR, the home-server deploy, backups.
- Running the dev environment from the WSL file system (documented as an option in TE-006, not built).

## Open questions
- Q1 (owner): Do you run Docker Desktop with the WSL 2 backend? *Assumption: yes.*
- Q2 (owner): Do you want the dev database reachable from Windows for a GUI client (e.g. DBeaver) on
  `127.0.0.1:5432` only, through an opt-in profile? *Assumption: no; use `docker compose exec db psql`. Easy to add
  later.*

## Rework log
(empty)

## Changelog
(filled when done)
