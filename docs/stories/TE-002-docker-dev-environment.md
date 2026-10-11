---
id: TE-002
title: Docker dev environment with hot reload and a PostgreSQL named volume
status: in-test
priority: must
epic: M0 Docker dev environment
agents: [devops-engineer, backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
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
- The repo lives at `C:\dev\traveleasy-remake` on NTFS (outside OneDrive); Docker Desktop uses the WSL 2 backend
  (confirmed 2026-10-11, see Answered questions). Bind mounts from NTFS don't deliver file events reliably, hence
  polling.
- **Database GUI (owner request 2026-10-11).** The owner wants to browse the dev database in a web GUI. phpMyAdmin is
  MySQL/MariaDB-only, so this story adds **Adminer** (supports PostgreSQL) as a dev-only, opt-in tool (AC23–AC27).
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
      `docker compose ps` shows every service running and `db` as `healthy`. *Timing (clarified 2026-10-11):* once the
      dev image has been built, `docker compose up -d` reaches "all services running, `db` healthy" **within
      5 minutes** (QA measured 149 s on a fresh clone). The image build itself (about 271 s without cache) is
      **reported, not gated** (see Performance). After that, and after
      `docker compose exec app php artisan migrate` (and the APP_KEY step from AC19),
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
- [ ] AC10 (DB not exposed): `docker inspect` of the running `db` container shows no host port binding
      (`HostConfig.PortBindings` empty), and neither compose file has a `ports:` entry on `db`. The app reaches the DB
      over the internal network as host `db`. *(Reworded in rework round 1: Compose v5.5.1 prints `invalid IP:0`
      with exit 0 for `docker compose port db 5432` on an unpublished port, so that command is not a reliable check.)*
- [ ] AC11 (test database): On first initialisation of the DB volume, a second database `traveleasy_test`, owned by
      the app's DB user, is created by an init script, so TE-004 can run tests against PostgreSQL. The handoff says how
      to create it on an existing volume (init scripts only run on an empty volume).
- [ ] AC12 (localhost only): The app (`APP_PORT`, default 8000) and Vite (`VITE_PORT`, default 5173) ports are
      published on `127.0.0.1` only, so the dev server isn't reachable from the LAN. Both are in `.env.example`.
      This also protects the Inertia DevTools endpoints (`/_inertia/devtools/*`), which have no authentication when
      `APP_ENV=local`. QA checks from another device on the LAN that port 8000 and port 5173 can't be reached.
- [ ] AC13 (in-container commands): these work from the repo root and are listed in the handoff:
      `docker compose exec app php artisan <cmd>`, `docker compose exec app composer <cmd>`,
      `docker compose exec vite npm <cmd>`, `docker compose exec app php artisan test`.
- [ ] AC14 (services): without a profile, `docker compose config --services` lists exactly the dev services (`app`,
      `vite`, `db`, or equivalent names stated in the handoff); with `--profile tools` it additionally lists `adminer`.
      There is no mail catcher, no `ssr`, worker or scheduler service yet.
- [ ] AC15 (base file stands alone): `docker compose -f compose.yaml config` succeeds with the required env set,
      and `compose.yaml` contains no bind mounts of the source, no Vite service, no `adminer` service and no dev ports.
- [ ] AC16 (line endings): Given a clone made with `core.autocrlf=true`, the containers still start (shell scripts are
      LF via `.gitattributes`, which already forces LF by default; any entrypoint script is covered).
- [ ] AC17 (starter tests on PostgreSQL, isolated from the dev DB; clarified in rework round 1):
      `docker compose exec app php artisan test` runs the starter-kit tests against PostgreSQL and passes.
      - **Forced target (reworded 2026-10-11, QA's wording, agreed by security).** `DB_CONNECTION`, `DB_DATABASE`
        and `DB_URL` are forced (to `pgsql`, `traveleasy_test` and empty), whatever is set in `$_SERVER`, `$_ENV`,
        `putenv` or the container environment. `DB_HOST`, `DB_PORT` and the credentials come from env. An unreachable
        server, or a database not named exactly `traveleasy_test`, aborts the run through the guard below. This also
        holds when a cached config exists (`php artisan config:cache`).
      - **Why `force="true"` isn't enough.** `force="true"` in `phpunit.xml` is not sufficient on its own: Laravel's
        env reader checks `$_SERVER` first, and container env vars live there in PHP CLI.
      - **Guard.** Before any migration or `RefreshDatabase` runs, a guard checks the **active** connection. It aborts
        the whole run with a clear message naming the connection and the database unless the driver is `pgsql` **and**
        the database name is exactly `traveleasy_test`. This guard lives in this story; TE-004 references it instead of
        building its own.
      - **No SQLite file.** No test run may create a SQLite file anywhere in the repo.
      - **Proof.** QA's `tests/Unit/DockerDevEnvironmentContractTest.php` (commit 6889cc8) passes. Its three nested-run
        cases must each end on `traveleasy_test` or abort through the guard, never touch the named target:
        1. `-e DB_CONNECTION=sqlite -e DB_DATABASE=qa_nonexistent_db`
        2. `-e DB_URL=pgsql://…/qa_nonexistent_db`
        3. `-e DB_DATABASE=qa_conflict_nonexistent_db`

        A fourth case runs with a cached config (`config:cache` in dev, with `DB_DATABASE=traveleasy`). It must not
        touch the dev DB, and the cache is cleared afterwards.
      - **Invariant.** Row counts in the dev DB `traveleasy` are identical before and after every case.
- [ ] AC18 (one dev image with PHP and Node): the dev image contains **PHP 8.5** (the lock was resolved on 8.5.11)
      and **Node 24** (24.21.x, with npm 11) together, on a glibc base (not Alpine/musl). Reason: `vp build` and the
      Vite dev server run `php artisan wayfinder:generate`, so the `vite` service needs PHP too. Both `app` and `vite`
      use this image (different commands). `docker compose exec vite php -v` and `docker compose exec app node -v`
      both work, and `docker compose exec vite npm run generate` succeeds.
- [ ] AC19 (APP_KEY on first run): given `APP_KEY=` is empty in `.env`, a first-run step generates it into `.env`.
      This is either automatic in the container entrypoint when the key is empty (it never overwrites an existing
      key) or one documented command. After that step, `docker compose exec app php artisan test` passes on a fresh
      clone (tests fail without a key). The handoff says which variant was chosen.
- [ ] AC20 (reproducible installs): Node dependencies are installed with `npm ci` from `package-lock.json` (never
      `npm install`), and the repo's `.npmrc` (`ignore-scripts=true`) is respected: no lifecycle scripts run, and any
      step the build really needs (e.g. `npm run generate`) is called explicitly. PHP dependencies are installed with
      `composer install` from `composer.lock`.
- [ ] AC21 (no automatic seeding): neither the image, the entrypoint nor any compose command runs `db:seed`,
      `migrate --seed` or `migrate:fresh --seed` automatically. The kit's `DatabaseSeeder` creates
      `test@example.com` / `password`. Seeding is only ever a manual, documented command (the seeder split comes in
      M1).
- [ ] AC22 (clean-up of TE-001 leftovers): once this environment works, the throwaway resources from TE-001 are
      removed: the Docker volumes `te-vendor`, `te-node-modules`, `qa-vendor` and `qa-node-modules`, and the image
      `te-toolbox:te001`. `docker volume ls` and `docker image ls` no longer show them. Only these named resources are
      removed (no `docker system prune`, no volume of this stack).
- [ ] AC23 (Adminer, dev-only and opt-in): an `adminer` service exists **only** in `compose.dev.yaml` (never in
      `compose.yaml` or any production file) and is assigned to the `tools` profile. Plain `docker compose up -d`
      does not start it (`docker compose ps` doesn't list it); `docker compose --profile tools up -d` starts it.
      `.env.example` does not set `COMPOSE_PROFILES=tools` (it may document it, commented out).
- [ ] AC24 (Adminer image): uses the official `adminer` image pinned to at least major.minor (e.g. `adminer:5.x`,
      no `latest`). `ADMINER_DESIGN` and plugins are used only if they ship inside that image; the login page loads
      no resource from outside `localhost` (browser network tab shows no external request).
- [ ] AC25 (Adminer, localhost only): published on `127.0.0.1` only (`ADMINER_PORT`, default 8081, in
      `.env.example`), never `0.0.0.0`; `docker compose --profile tools port adminer 8080` shows `127.0.0.1:8081`.
      From another device on the LAN, `http://<pc-ip>:8081` is not reachable.
- [ ] AC26 (Adminer → db internally): with the `tools` profile running, the developer opens `http://localhost:8081`,
      chooses system "PostgreSQL", server `db`, and the username, password and database from `.env`
      (`DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`), and can browse the tables and rows created by `migrate`
      (e.g. `users`, `migrations`). `traveleasy_test` is reachable the same way. AC10 still holds: `db` has no
      published port in either file, also with `--profile tools`.
- [ ] AC27 (no stored credentials): no username or password for Adminer or the DB is set in the `adminer` service
      definition, in an Adminer plugin/config file or in the image (e.g. no auto-login or `login-password-less`
      plugin); a wrong password shows Adminer's login error and grants no access. Pre-filling only the server name
      (`ADMINER_DEFAULT_SERVER=db`) is allowed.

## Quality criteria
- [ ] Tests: QA runs AC2–AC27 manually on Windows and records timings for AC4 and AC5 in the verdict.
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
  - Adminer (security-privacy-reviewer checks AC23–AC27, plus AC21 no automatic seeding and AC22 clean-up): dev-only file, opt-in `tools` profile, pinned official
    image, bound to `127.0.0.1`, no published DB port, no stored/default credentials, no auto-login plugin, no
    runtime CDN or external request, no bind mounts into the `adminer` container. The reviewer confirms nothing
    Adminer-related can reach `compose.yaml` or a production image.
- [ ] i18n: not applicable.
- [ ] Performance: AC4 (≤ 3 s HMR) and AC5 (idle CPU) are the budget. The dev image build time (no cache, about 271 s measured) is reported, not gated; `up -d` to healthy after the build is gated at 5 minutes by AC2.
- [ ] Docs updated: the handoff lists the commands, ports, service names, image versions and the "existing volume →
      create test DB" step for TE-006, plus the Adminer start/stop commands (`--profile tools`), URL, `ADMINER_PORT`
      and which `.env` values to enter on the login page.
- [ ] Docker rebuild: n/a (this story creates the environment).

## Out of scope
- `docs/deploy/dev.md` and `docs/testing.md` (TE-006).
- The `ssr` dev profile (SSR epic), queue worker and scheduler services (M1), the production image and ADR-0002,
  production `compose.yaml` services and limits, CI/GHCR, the home-server deploy, backups.
- Running the dev environment from the WSL file system (documented as an option in TE-006, not built).

## Open questions
- (none)

### Answered (2026-10-11)
- Q1 (owner): Do you run Docker Desktop with the WSL 2 backend? **Yes, confirmed.** `docker info` shows Docker
  Desktop with kernel `6.18.40.1-microsoft-standard-WSL2`; `wsl -l -v` shows `docker-desktop` at version 2; Docker
  29.8.2, Compose v5.5.1. The repo stays on the Windows file system (`C:\dev\traveleasy-remake`), so the
  polling-based HMR criteria (AC4, AC5) stay as written.
- Q2 (owner): Do you want a GUI for the dev database? **Yes, a web GUI.** phpMyAdmin is MySQL-only, so Adminer
  (PostgreSQL support) is added as a dev-only service behind the opt-in `tools` profile on `127.0.0.1:8081`,
  connecting to `db` over the internal network; the DB port stays unpublished. Added AC23–AC27 and the matching
  security/privacy and docs criteria; checked by the security-privacy-reviewer (already a tester on this story).

## Rework log

### Round 1 (2026-10-11)
- **Verdicts:**
  - security-privacy-reviewer: **PASS** (Low findings only; filed as follow-ups, see below).
  - qa-engineer: **FAIL** on AC17. Several ACs are BLOCKED because drive C: was full (126 MB free). That's an
    environment problem, not a code defect.
- **Failed criterion:** AC17. The test run is not isolated from the dev database.
- **Evidence:** `tests/Unit/DockerDevEnvironmentContractTest.php` (commit 6889cc8), three failing nested-run cases
  around line 62:
  - `-e DB_CONNECTION=sqlite -e DB_DATABASE=qa_nonexistent_db`: all 67 tests "passed" on SQLite, and
    `RefreshDatabase` created a 100 KB SQLite file in the repo root (since deleted);
  - `-e DB_URL=pgsql://…/qa_nonexistent_db`: the tests connected to that database;
  - `-e DB_DATABASE=qa_conflict_nonexistent_db`: the tests connected to that database.

  Related Low finding #4 from security: with a cached config (`php artisan config:cache` in dev), the tests load
  `DB_DATABASE=traveleasy`, and `RefreshDatabase` would wipe the dev DB.
- **Likely cause:**
  - `force="true"` in `phpunit.xml` sets putenv/`$_ENV`, but Laravel's env reader checks `$_SERVER` first, where
    container env vars live in PHP CLI.
  - `DB_URL` isn't forced at all.
  - A cached config bypasses env entirely.
- **Impact:** if a container env ever sets `DB_DATABASE=traveleasy`, `php artisan test` wipes the dev database.
- **Expected fix (backend-developer):**
  - Force the test connection to `pgsql` / `traveleasy_test` independently of `$_SERVER`/`$_ENV`/container env. Cover
    `DB_URL`, and avoid or clear a cached config for tests (e.g. override in the test bootstrap, or refuse to run
    while `bootstrap/cache/config.php` exists).
  - Add a guard that runs before any migration and aborts unless the active connection is `pgsql` and the database is
    exactly `traveleasy_test`.
  - Make QA's three cases pass, and add the config-cache case.
- **Criteria clarified in this round:**
  - AC17: forced target, exact-name guard, the four proof cases, and no SQLite file. The guard now lives here;
    TE-004 AC3 references it.
  - AC10: reworded to use `docker inspect` `PortBindings`, because Compose v5.5.1's `docker compose port` prints
    `invalid IP:0` with exit 0.
  - TE-006 AC7a: the Adminer stop command is now `docker compose --profile tools rm -s -f adminer`.
- **To verify in the re-test** (BLOCKED by disk space, not failed):
  - AC2: fresh clone.
  - AC8 and AC9: persistence across `-V`.
  - AC16: runtime clone with `core.autocrlf=true`.
  - AC19: fresh-clone run.
  - AC23–AC27: Adminer at runtime.
  - The CSS before/after build comparison for the `@import 'tailwindcss' source('../')` change.
  - ESLint, `tsc` and Larastan runs.
- **Follow-ups filed elsewhere (not part of this rework):**
  - M1 trusted hosts: on the board, "Follow-ups for M1".
  - Production hardening list: on the board, Epics notes.
  - TE-006: the `docker compose config` secret warning, and the rebuild without dangling volumes.
  - TE-003: require `symfony/yaml` directly as a dev dependency.

## Changelog
(filled when done)
