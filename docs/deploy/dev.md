# Development environment (Docker)

Everything runs in Docker: you don't need PHP, Composer, Node or PostgreSQL on Windows. Source code is bind-mounted
into the containers and the dev servers reload by themselves, so **a code change shows up in the browser without a
rebuild**. How we test (layers, rules, arch tests, accessibility) is in the [testing guide](../testing.md); the project
overview is in the [README](../../README.md).

Every command on this page was run on the reference setup: Windows 11, Docker Desktop 4.94.0 (WSL 2 backend,
Engine 29.8.2, Compose v5.5.1), in Windows PowerShell 5.1 and Git Bash. Where the two shells differ, both forms are
given. Run all commands from the repo root.

**E-mail:** the app sends no e-mail, so there is no mail catcher (Mailpit, MailHog, …) in the stack. See
[ADR-0005: no outgoing e-mail](../adr/0005-no-outgoing-email.md).

## Contents
- [Prerequisites](#prerequisites)
- [Services](#services)
- [First run](#first-run)
- [Daily commands](#daily-commands)
- [When to rebuild](#when-to-rebuild)
- [Cleaning up leftover volumes and images](#cleaning-up-leftover-volumes-and-images)
- [Browser tests (Playwright + axe)](#browser-tests-playwright--axe)
- [Test database](#test-database)
- [Database GUI (Adminer, dev only)](#database-gui-adminer-dev-only)
- [Resetting safely](#resetting-safely)
- [Security notes](#security-notes)
- [Troubleshooting](#troubleshooting)

## Prerequisites
- **Windows 11** with **Docker Desktop** using the **WSL 2 backend** (Settings → General → "Use the WSL 2 based
  engine").
- **Git** (Git for Windows also gives you Git Bash).
- The repo **outside OneDrive**, e.g. `C:\dev\traveleasy-remake`. OneDrive sync and file locking break bind mounts
  and dependency folders.
- **Not needed on the host:** PHP, Composer, Node, npm or PostgreSQL. They all run inside the containers.
- Disk space: the dev image is about 1.4 GB, plus about 0.5 GB of dependency volumes and the database.

## Services

| Service | What it runs | URL / port | Profile |
|---|---|---|---|
| `app` | `php artisan serve` (PHP 8.5.11), migrates on start | http://localhost:8000 (`APP_PORT`) | default |
| `vite` | Vite dev server with HMR (Node 24.21.0, plus PHP for Wayfinder) | http://localhost:5173 (`VITE_PORT`) | default |
| `db` | PostgreSQL 18.6, named volume `traveleasy-remake_pgdata` | not published; host `db` inside Docker | default |
| `adminer` | Adminer 6.1.1, database GUI | http://localhost:8081 (`ADMINER_PORT`) | `tools` |
| `e2e` | Playwright 1.64.0 + axe browser tests (Chromium), one-shot | none (shares `vite`'s network) | `e2e` |

- All ports are bound to `127.0.0.1` only, so nothing is reachable from the LAN.
- `app` and `vite` use the same dev image (`traveleasy-remake-dev:local`, built from the `dev` stage of `Dockerfile`)
  and run as a non-root user (uid 1000).
- Every service has a health check. `vite`'s requests `/@vite/client`, so a dead or hung dev server shows as
  `unhealthy` after about a minute.
- The Compose project name is **`traveleasy-remake`**. Docker resources named `traveleasy_*` (for example the
  volumes `traveleasy_db-data` and `traveleasy_app-storage`) belong to the **old school project** on the same PC.
  Never touch them.

## First run
1. Clone and enter the repo (outside OneDrive):
   ```
   git clone https://github.com/NimrodLobozar/traveleasy-remake.git C:\dev\traveleasy-remake
   cd C:\dev\traveleasy-remake
   ```
2. Copy the env template:
   - PowerShell: `Copy-Item .env.example .env`
   - Git Bash: `cp .env.example .env`
3. Set `DB_PASSWORD` in `.env` to a strong random value. Generate one with:
   - PowerShell: `$b = New-Object byte[] 24; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); ($b | ForEach-Object { $_.ToString('x2') }) -join ''`
   - Git Bash: `openssl rand -hex 24`

   Both print 48 hex characters. Paste the value after `DB_PASSWORD=`. Set it **before** the first start:
   PostgreSQL stores the password when the volume is first created (see "Changed `DB_PASSWORD`" under
   Troubleshooting).
4. Leave `APP_KEY=` empty. On its first start, the `app` container runs `php artisan key:generate` into `.env` for
   you. It never overwrites an existing key.
5. Build and start everything: `docker compose up -d --build`. The first build takes about 3 minutes.
6. Wait until `docker compose ps` shows `app`, `vite` and `db` as `(healthy)`, usually 1 to 2 minutes.
7. The database migrations run automatically when `app` starts (`MIGRATE_ON_START=true`). To check, or to run them
   by hand: `docker compose exec app php artisan migrate`. It is harmless when there is nothing to migrate.
8. Open http://localhost:8000. You should see the welcome page.

How plain `docker compose` finds both files: `.env` sets `COMPOSE_FILE=compose.yaml:compose.dev.yaml` with
`COMPOSE_PATH_SEPARATOR=:`, so `docker compose …` loads the dev overrides in PowerShell, cmd and Git Bash alike.

**The database is not seeded automatically.** The starter kit's seeder creates the well-known account
`test@example.com` / `password`. Run it by hand only if you want that account in your local dev DB:
`docker compose exec app php artisan db:seed`.

**Don't run `key:generate` by hand on a working setup.** It replaces the existing key, which logs everyone out and
makes encrypted data unreadable. Use it only if `APP_KEY` in `.env` is empty and the container didn't fill it.

## Daily commands
All commands work as-is in PowerShell and Git Bash.

| Task | Command |
|---|---|
| Start | `docker compose up -d` |
| Stop, keeping containers (nothing left behind) | `docker compose stop` |
| Stop and remove containers and network (data kept) | `docker compose down` (leaves dependency volumes behind, see [clean-up](#cleaning-up-leftover-volumes-and-images)) |
| Status / health | `docker compose ps` |
| Logs (follow) | `docker compose logs -f app` (or `vite`, `db`); last lines only: `docker compose logs --tail 50 vite` |
| Restart one service | `docker compose restart <service>` (e.g. `vite`) |
| Artisan | `docker compose exec app php artisan <command>` |
| Migrate | `docker compose exec app php artisan migrate` |
| Composer | `docker compose exec app composer <command>` |
| npm | `docker compose exec vite npm <command>` |
| Regenerate Wayfinder routes + TS types | `docker compose exec vite npm run generate` |
| PHP tests (Pest, on `traveleasy_test`) | `docker compose exec app composer test` (same as `php artisan test`) |
| PHP tests in parallel | `docker compose exec app composer test:parallel` (see [Test database](#test-database)) |
| Component tests (Vitest) | `docker compose exec vite npm run test` (watch mode: `npm run test:watch`) |
| Browser tests (Playwright + axe) | `docker compose run --rm e2e` (see [Browser tests](#browser-tests-playwright--axe)) |
| All PHP checks: Pint, Larastan, Pest | `docker compose exec app composer check` |
| All frontend checks: generate, lint, format check, types | `docker compose exec vite npm run check` |
| PHP style check / fix | `docker compose exec app composer lint` / `composer format` |
| PHP static analysis (Larastan) | `docker compose exec app composer analyse` |
| Frontend lint / auto-fix | `docker compose exec vite npm run lint` / `npm run lint:fix` |
| Frontend format check / fix (whole repo) | `docker compose exec vite npm run format:check` / `npm run format` |
| Format one file | `docker compose exec vite npx vp fmt resources/js/pages/welcome.tsx` (add `--check` to only check) |
| TypeScript | `docker compose exec vite npm run types:check` (`npm run types` regenerates first) |
| Fix lint + format in one go | `docker compose exec vite npm run check:fix` |
| psql into the dev DB | `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'` (`\q` to quit) |
| Validate the compose files | `docker compose config --quiet` (prints nothing when valid; see the warning below) |

Notes:
- Add packages **inside** the containers, so the lockfiles are updated with the right PHP/Node versions:
  `docker compose exec app composer require <pkg>` / `docker compose exec vite npm install <pkg>`. Then rebuild
  ([When to rebuild](#when-to-rebuild)).
- **`npx vp fmt` without paths reformats every file in the repo** that the formatter config covers, and
  `npm run format` is the same command. Use `npm run format` only when you mean "the whole repo". For one file, pass
  its path (row above). Always run the formatter in the container, so the repo config (LF line endings, Tailwind class
  sorting, ignore list) applies.
- **Formatter output names files, not always lines.** `composer lint` runs Pint with `-v`, so it shows diff hunks
  with line numbers. `npm run format:check` (oxfmt) names only the file. Run `npm run format` (or the one-file form)
  to fix it. Linters, Larastan and `tsc` always report file and line.
- **Vite advisories aren't in `npm audit`.** `vite` is an npm alias of `@voidzero-dev/vite-plus-core`, so
  `npm audit` can't see Vite security advisories. On every dependency update, check by hand (TE-003 AC13; the note is
  in `package.json` under `"//"`):
  1. Get the bundled Vite version:
     `docker compose exec vite node --input-type=module -e "import('vite').then((m) => console.log(m.version))"`
     (8.3.0 today; `npx vp --version` shows only the vite-plus version).
  2. Read the vite-plus release notes and the
     [Vite security advisories](https://github.com/vitejs/vite/security/advisories) for that version.
- In psql, run SQL interactively. Windows PowerShell 5.1 mangles a nested `"…"` SQL string inside `sh -c '…'`, so
  `psql -c "select …"` inside `sh -c` returns nothing there. Pipe the SQL in instead:
  `'select count(*) from users;' | docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'`.

> **Warning: `docker compose config` prints secrets.**
> Without `--quiet`, `docker compose config` prints the fully resolved configuration, including
> `POSTGRES_PASSWORD` (your `DB_PASSWORD`) in plain text. Never paste its output into docs, issues, chats or CI logs.
> To check that the files are valid, use `docker compose config --quiet`.

## When to rebuild

| What changed | What to do |
|---|---|
| Source files (components, controllers, routes, Blade, styles, tests) | Nothing. Vite HMR updates the page; PHP changes show on the next refresh |
| A migration | `docker compose exec app php artisan migrate` |
| `vite.config.ts` or `lint.rules.ts` | Nothing. Vite restarts itself; if it doesn't come back, see [Troubleshooting](#troubleshooting) |
| `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `Dockerfile`, `docker/dev/*`, compose files, new env vars | Rebuild, preferably the clean way below |
| Want an empty schema or database | See [Resetting safely](#resetting-safely) |

**Clean rebuild (preferred, leaves nothing behind).** Remove the two dev containers **together with their anonymous
dependency volumes**, then build and start. The database container and its named volume aren't touched:

```
docker compose rm -s -f -v app vite
docker compose up -d --build
```

**Quick rebuild:** `docker compose up -d --build -V`. Here `-V` (capital, on `up`) renews the anonymous `vendor` and
`node_modules` volumes from the new image, and the named database volume is kept. The catch is that the previous
dependency volumes stay behind (see the next section).

`-V` (capital, on `up`) and `-v` (lowercase, on `down`) are very different. `docker compose down -v` deletes **all**
volumes, including the database (see [Resetting safely](#resetting-safely)).

## Cleaning up leftover volumes and images
Dependencies (`vendor`, `node_modules`) live in **anonymous** volumes, one pair per container. They become
"dangling", meaning they exist but no container uses them, in these cases:
- `docker compose up -d --build -V`: the old pair stays behind, about 0.5 GB per rebuild.
- **A plain `docker compose down`** removes the containers but not their anonymous volumes. The next `up` creates a
  new pair, and the old pair is left dangling. For daily stopping, use `docker compose stop`, which leaves nothing
  behind. Before a `down`, run `docker compose rm -s -f -v app vite` if you don't want leftovers.

On top of that, **every rebuild leaves the previous `traveleasy-remake-dev:local` image dangling**, about 1.4 GB
each time.

> **Never use a global prune** (`docker system prune`, `docker volume prune`, `docker image prune -a`, or a
> `docker image prune` without the filter below). Other projects on this PC, including the old `traveleasy_*`
> project, have their own volumes and images, and a global prune deletes them too.

### Dangling images of this project
The dev image carries the Compose label `com.docker.compose.project=traveleasy-remake` (verified), and so do its
dangling predecessors. Images of other projects don't match.

1. Look first:
   ```
   docker images --filter dangling=true --filter label=com.docker.compose.project=traveleasy-remake
   ```
2. Remove exactly those (asks for confirmation):
   ```
   docker image prune --filter label=com.docker.compose.project=traveleasy-remake
   ```

Without `-a`, `image prune` only removes dangling images, never the image in use. Images can't contain database data.

### Dangling dependency volumes of this project
Docker doesn't put the project label on anonymous volumes. They only carry `com.docker.volume.anonymous`, the same as
every other project's, so **no label filter can select only ours** (verified). A filter on the project label matches
only `traveleasy-remake_pgdata`, so don't use one for volumes.

The best approach is not to create leftovers: use the clean rebuild and `docker compose stop`. To find leftovers
anyway, the commands below read each dangling anonymous volume **read-only** from the Windows side and list only those
whose `node_modules/.package-lock.json` says `"name": "traveleasy-remake"` or whose `vendor/composer/installed.php`
says `nimrodlobozar/traveleasy-remake`. The database volume is named and has neither file, so it never matches. The
commands only print volume IDs; they delete nothing. They use Docker Desktop's internal volume path
(`\\wsl.localhost\docker-desktop\mnt\docker-desktop-disk\data\docker\volumes`, verified on 4.94.0); if a future
Docker Desktop moves it, the list is simply empty.

PowerShell:
```
$root = '\\wsl.localhost\docker-desktop\mnt\docker-desktop-disk\data\docker\volumes'
docker volume ls -q --filter dangling=true --filter label=com.docker.volume.anonymous | Where-Object {
    $npm = "$root\$_\_data\.package-lock.json"; $php = "$root\$_\_data\composer\installed.php"
    ((Test-Path -LiteralPath $npm) -and (Select-String -Quiet -SimpleMatch '"name": "traveleasy-remake"' -LiteralPath $npm)) -or
    ((Test-Path -LiteralPath $php) -and (Select-String -Quiet -SimpleMatch 'nimrodlobozar/traveleasy-remake' -LiteralPath $php))
}
```

Git Bash:
```
D=//wsl.localhost/docker-desktop/mnt/docker-desktop-disk/data/docker/volumes
for v in $(docker volume ls -q --filter dangling=true --filter label=com.docker.volume.anonymous); do
  if grep -qs '"name": "traveleasy-remake"' "$D/$v/_data/.package-lock.json" \
     || grep -qs 'nimrodlobozar/traveleasy-remake' "$D/$v/_data/composer/installed.php"; then echo "$v"; fi
done
```

Check the list, then remove those volumes **by ID**: `docker volume rm <id> <id> …`. Docker refuses to remove a
volume that a container still uses, so this can't hit the running stack. Never remove `traveleasy-remake_pgdata` or
anything named `traveleasy_*`.

## Browser tests (Playwright + axe)
Test-writing rules, the axe helper and the allow-list are in the [testing guide](../testing.md). This section covers
running the tests.

- **Run everything:** `docker compose run --rm e2e`. It needs `app` and `vite` healthy, runs `npm run test:e2e` in
  Chromium (`playwright test --config tests/e2e/playwright.config.ts`), and removes its container afterwards. Plain
  `docker compose up -d` never starts it (profile `e2e`).
- **One file or extra flags:**
  `docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts smoke`. The `--config` is
  required, because a plain `npx playwright test` won't find the config.
- **One run at a time.** All runs share `vite`'s network namespace (and so its port 8000 forward) and the same report
  folders. A second run started while one is going fails with `EADDRINUSE 127.0.0.1:8000`, and two runs would also
  overwrite each other's `test-results/e2e` and `playwright-report/html`. Start the next run only when the previous one
  has finished. If a future setup does allow concurrent runs (e.g. separate CI jobs), give each run its own output
  folder with `--output`:
  `docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts --output /tmp/e2e-2`
  (`/tmp` is a tmpfs in this container, so those results disappear with it).
- **Where the reports are**, on Windows in the repo, one level down:
  - HTML report: `playwright-report/html/index.html` (open it in a browser);
  - on failure: screenshots, `error-context.md` and `trace.zip` in `test-results/e2e/`.

  Both folders are git-ignored and can be deleted from Windows at any time. Treat them as sensitive, even after
  green runs: they hold page HTML and URLs, and traces can hold cookies and typed passwords. Never upload them to
  online trace viewers or attach them publicly. Why one level down: the repo is mounted read-only, and Playwright
  deletes and recreates its output folders, which fails on a mount point (`EROFS`).
- **How it reaches the app:** the container shares `vite`'s network namespace, so `localhost:5173` is the real Vite
  server and the page's JavaScript really loads. Playwright forwards `localhost:8000` to `app:8000`.
- **Isolation:**
  - official image `mcr.microsoft.com/playwright:v1.64.0-noble`, uid 1001, read-only root file system, no
    capabilities, `no-new-privileges`;
  - it can't reach the database;
  - the repo is mounted **read-only**, so it can't change code or `.git`. `node_modules`/`vendor` come from `vite` and
    belong to uid 1000, so it can only read them. The only writable folders are `playwright-report/` and
    `test-results/`;
  - `.env` is masked (an empty read-only file), and `storage/` and `bootstrap/cache/` are empty tmpfs mounts. The only
    variables are the `E2E_*` ones, `HOME` and `PLAYWRIGHT_HTML_OUTPUT_DIR`.
- **After adding any `.env.*` file** to the repo root (other than `.env.example`, e.g. `.env.testing`), **run the Pest
  suite before running e2e:** `docker compose exec app composer test`. The e2e service mounts the whole repo, and
  each `.env*` file is hidden from it only by an explicit read-only mask in `compose.dev.yaml`. Masks can only cover
  files that exist. The compose contract test (`tests/Unit/DockerDevEnvironmentContractTest.php`) fails until you add
  a read-only `/dev/null` mask for the new file to the `e2e` service (copy the `.env` one). `docker compose run`
  reads the compose files fresh, so no rebuild is needed for that.
- **Upgrading Playwright.** The image tag must equal `@playwright/test`, which is pinned exactly (no `^`):
  1. Change the version in `package.json` and run `docker compose exec vite npm install`.
  2. Set the same version in the `e2e` image tag in `compose.dev.yaml`.
  3. Rebuild ([When to rebuild](#when-to-rebuild)).

  `tests/Unit/PlaywrightImageVersionSyncTest.php` fails while the two differ. A mismatch otherwise shows as
  "Executable doesn't exist" or "please update Playwright browsers".
- If `app` runs an older image than the latest build, `docker compose run --rm e2e` first recreates `app` (it brings
  dependencies up to date). Add `--no-deps` to leave running services alone.
- **`EACCES` on the report folders:** they were created by another user (e.g. uid 1000 from an older setup). Delete
  `playwright-report/` and `test-results/` from Windows and run again; Docker recreates them writable. In the WSL file
  system, create them yourself and make them writable for uid 1001.

## Test database
Tests run against PostgreSQL. They never use SQLite and never touch the dev database: `phpunit.xml` forces
`DB_CONNECTION=pgsql` and `DB_DATABASE=traveleasy_test`, and a guard (`tests/Support/TestDatabase.php`) checks on the
database server that the connection really is `traveleasy_test`, or the parallel worker's own database. Details are
in the [testing guide](../testing.md).

`traveleasy_test` (owned by the app's DB user) is created by `docker/postgres/initdb/10-create-test-database.sh`, but
**only when the DB volume is first initialised**. On an older volume, create it once:

```
docker compose exec db sh -c 'createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" traveleasy_test'
```

If it already exists, the command fails with `database "traveleasy_test" already exists`, which is harmless.

**Parallel runs** (`composer test:parallel` = `php artisan test --parallel`):
- Each worker gets its own database, `traveleasy_test_test_1`, `_2`, and so on, created on demand (16 workers on a
  16-thread CPU; the full suite runs in about 19 s instead of about 41 s).
- **Before `--parallel --recreate-databases` or `--drop-databases`, run
  `docker compose exec app php artisan config:clear`** (security review F4, TE-003). The test guard ignores a cached
  config inside the tests, but creating and dropping the worker databases is driven by the parallel runner. Clearing
  the cache makes sure that runner works from the real test configuration and not from a stale
  `bootstrap/cache/config.php`. For example:
  ```
  docker compose exec app php artisan config:clear
  docker compose exec app php artisan test --parallel --recreate-databases
  ```
- **paratest is pinned to exactly 7.26.0** (`composer.json`), because the current Pest release doesn't yet allow
  7.26.1. That means we miss the 7.26.1 hardening fix (PR #1148, which keeps worker files in a private per-run
  directory). Go back to `^7.26.1` once Pest allows it. Until then, **never run `--parallel` on a shared multi-user
  host** (a shared server or a shared CI runner). Inside your own dev container it is fine.

## Database GUI (Adminer, dev only)
Adminer is opt-in (profile `tools`) and exists only in `compose.dev.yaml`; production has no Adminer.
- **Start:** `docker compose --profile tools up -d`
- **Open** http://localhost:8081 (`ADMINER_PORT` in `.env`) and log in with:
  - System: **PostgreSQL**
  - Server: **db** (pre-filled)
  - Username / Password / Database: `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` from your `.env`. Use database
    `traveleasy_test` to look at the test DB.
- **Stop:** `docker compose --profile tools rm -s -f adminer`. This stops **and removes** it; Adminer keeps no data.
  Don't use `docker compose stop adminer`: a plain `docker compose down` doesn't remove profile containers, and a
  stopped Adminer container left behind fails to start once `down` has recreated the network. If that happens, run the
  stop command above and start it again.
- No credentials are stored anywhere. Adminer's version check is disabled (`version-noverify`), so it makes no
  external requests. Don't set `COMPOSE_PROFILES=tools` in `.env` unless you want Adminer to start every time.

## Resetting safely

> **Warning**
> `docker compose down -v` deletes **all** volumes of this project, **including the database** (`-v` on `down` means
> "volumes", unlike `-V` on `up`). Every row, account and test database is gone. **Ask the owner first**, every time.

Safer alternatives, from mild to strong:
- **Stop for the day:** `docker compose stop`, or `docker compose down` without `-v`. Both keep all data.
- **Empty schema, same volume:** `docker compose exec app php artisan migrate:fresh`. It drops all tables in the dev
  DB and migrates again; the volume and `traveleasy_test` stay. Add `--seed` only if you want the kit's test account
  (`php artisan migrate:fresh --seed`).
- **Fresh dependencies, same data:** the clean rebuild in [When to rebuild](#when-to-rebuild).

## Security notes
- **Ports on `127.0.0.1` only.** The Vite dev server and `/_inertia/devtools` have no authentication, so they must
  never be reachable from the LAN.
- **The Vite dev server doesn't serve server files** (TE-006 AC13c). Vite serves any file under the project root by
  URL, so `vite.config.ts` sets `server.fs.deny` for `**/*.php`, `**/storage/**`, `**/bootstrap/cache/**`,
  `**/phpunit.xml` and `**/.env*`, on top of Vite's own defaults (`.env`, `.env.*`, certificates and keys, `.npmrc`,
  `.yarnrc.yml`, `**/.git/**`). For example, `http://localhost:5173/config/app.php` and `/storage/logs/laravel.log`
  answer **403**. A user `deny` list **replaces** Vite's defaults instead of adding to them, so the config repeats
  them. Keep them when you edit the list or upgrade Vite.
- **The database has no host port.** It is reachable only as `db` on the internal Docker network (or through
  Adminer, when you start it).
- **`docker compose config` prints secrets**, see the warning under [Daily commands](#daily-commands).
- The e2e container is sandboxed; see [Browser tests](#browser-tests-playwright--axe).

## Troubleshooting
- **HMR doesn't update the page.** Windows bind mounts don't deliver file events to Linux containers, so Vite polls.
  The settings are `VITE_USE_POLLING=true` and `VITE_POLLING_INTERVAL=1000` (ms) in `.env`. A lower interval reacts
  faster but costs CPU. After changing them, run `docker compose up -d vite`. Measured: a `.tsx` edit shows in about
  1.5 to 4 s, and idle `vite` CPU is about 4 to 5 %.
- **"Port 5173 is already in use", `vite` unhealthy, or `/@vite/client` answers 504** after editing `vite.config.ts`
  or `lint.rules.ts` (or an `.env*` file), especially after several quick edits. This is a vite-plus restart race:
  the old server hasn't released the port when the new one starts. Fix: `docker compose restart vite`, then wait for
  `(healthy)` in `docker compose ps`. The `vite` health check requests `/@vite/client`, so after about a minute
  `docker compose ps` also shows `unhealthy`.
- **Port 8000, 5173 or 8081 already in use** by another program. Change `APP_PORT` (and `APP_URL` to match),
  `VITE_PORT` or `ADMINER_PORT` in `.env`, then run `docker compose up -d`.
- **Slow file access.** PHP and Tailwind read source files through the Windows bind mount, which is slower than a
  Linux file system. Tailwind only scans `resources/` for that reason. For more speed, clone the repo into the WSL file
  system (e.g. `\\wsl.localhost\Ubuntu\home\<you>\traveleasy-remake`, working from a WSL shell) and set
  `VITE_USE_POLLING=false` in `.env`. The trade-offs:
  - Windows editors and Git then work over a network path, which is slower for them (use VS Code's WSL mode instead);
  - Docker creates the report folders as root there (create them yourself, see "`EACCES`" under Browser tests);
  - you need Git and your SSH keys inside WSL.
- **CRLF errors** (`/bin/sh^M: bad interpreter`, `set: Illegal option -`). `.gitattributes` forces LF for `*.sh`
  and `Dockerfile`, and the formatter writes LF. If a script still has CRLF (e.g. it was created before the rule
  existed, or by an editor that ignores `.gitattributes`), convert that file to LF in your editor, then rebuild
  ([When to rebuild](#when-to-rebuild)). `git config core.autocrlf` doesn't matter for these files because of
  `.gitattributes`.
- **Playwright image/version mismatch** ("Executable doesn't exist at /ms-playwright/…", "Looks like Playwright was
  just updated", or `PlaywrightImageVersionSyncTest` failing). The `e2e` image tag and `@playwright/test` must be the
  same version; see "Upgrading Playwright" under [Browser tests](#browser-tests-playwright--axe).
- **`vite` stays `unhealthy`** even after a restart. Check `docker compose logs --tail 50 vite`. A failed
  `npm run generate` (Wayfinder needs PHP and a valid `.env`) is logged as a warning, and Vite starts anyway.
- **`Permission denied` in `storage/` or `bootstrap/cache`.** Files created by a container that ran as root keep root
  ownership on the bind mount, and the `app` container prints a warning naming them. Fix once:
  `docker compose run --rm --no-deps --user root --entrypoint chown app -R 1000:1000 storage bootstrap/cache`.
- **Git Bash turns `/dev/null` or `/var/www/...` arguments into Windows paths** for `docker` commands. Prefix the
  command with `MSYS_NO_PATHCONV=1` (e.g. `MSYS_NO_PATHCONV=1 docker compose exec app ls /var/www/html`), or use
  PowerShell.
- **Red `NativeCommandError` text in Windows PowerShell 5.1** around normal Docker progress lines (`Container …
  Started`). This happens when you redirect stderr (`2>&1`): Docker writes progress to stderr, and PowerShell 5.1 wraps
  each line as an error. It is harmless; check the exit code (`$LASTEXITCODE`) instead.
- **Changed `DB_PASSWORD` after the first start.** PostgreSQL keeps the password it was initialised with, so `app`
  can't connect. Change it in the database too: open psql (`docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d
  "$POSTGRES_DB"'`), then run `ALTER USER traveleasy WITH PASSWORD '<the new value from .env>';`.
