# Development environment (Docker)

> **Draft from TE-002 (devops-engineer).** TE-006 owns this file and completes it (Vitest/Playwright commands,
> links to `docs/testing.md`, README link). Everything below was verified on Windows 11 + Docker Desktop (WSL 2).

Everything runs in Docker. You don't need PHP, Composer, Node or PostgreSQL on Windows. Code changes show up in the
browser without a rebuild. The app sends no e-mail, so there is no mail catcher (`docs/adr/0005-no-outgoing-email.md`).

## Prerequisites
- Windows 11 with **Docker Desktop** on the WSL 2 backend, and Git.
- The repo outside OneDrive (e.g. `C:\dev\traveleasy-remake`).

## Services

| Service | What it runs | URL / port | Profile |
|---|---|---|---|
| `app` | `php artisan serve` (PHP 8.5), migrates on start | http://localhost:8000 (`APP_PORT`) | default |
| `vite` | Vite dev server with HMR (Node 24, plus PHP for Wayfinder) | http://localhost:5173 (`VITE_PORT`) | default |
| `db` | PostgreSQL 18.6, named volume `traveleasy-remake_pgdata` | not published; host `db` inside Docker | default |
| `adminer` | Adminer 6.1.1, database GUI | http://localhost:8081 (`ADMINER_PORT`) | `tools` |

All ports are bound to `127.0.0.1` only, so nothing is reachable from the LAN. `app` and `vite` use the same dev
image (`traveleasy-remake-dev:local`, built from the `dev` stage of `Dockerfile`) and run as a non-root user.

## First run
1. Copy the env template: `Copy-Item .env.example .env` (PowerShell) or `cp .env.example .env` (Git Bash).
2. Set `DB_PASSWORD` in `.env` to a strong value. One way, in Git Bash: `openssl rand -hex 24`.
   Leave `APP_KEY` empty: the `app` container generates it into `.env` on its first start (it never overwrites an
   existing key).
3. Start everything: `docker compose up -d --build` (the first build takes about 3 minutes).
4. Wait until `docker compose ps` shows `app`, `vite` and `db` as `healthy` (about 1 to 2 minutes).
5. Open http://localhost:8000.

`.env` sets `COMPOSE_FILE=compose.yaml:compose.dev.yaml` (with `COMPOSE_PATH_SEPARATOR=:`), so plain
`docker compose …` loads both files in PowerShell, cmd and Git Bash. Migrations run automatically when `app` starts
(`MIGRATE_ON_START=true`); running `docker compose exec app php artisan migrate` again is harmless.

The database is **not** seeded automatically. The starter kit's seeder creates `test@example.com` / `password`; run it
only by hand if you want that account: `docker compose exec app php artisan db:seed`.

## Daily commands

| Task | Command |
|---|---|
| Start | `docker compose up -d` |
| Stop (keeps all data) | `docker compose down` |
| Status / health | `docker compose ps` |
| Logs | `docker compose logs -f app` (or `vite`, `db`) |
| Artisan | `docker compose exec app php artisan <command>` |
| Migrate | `docker compose exec app php artisan migrate` |
| Composer | `docker compose exec app composer <command>` |
| npm | `docker compose exec vite npm <command>` |
| Regenerate Wayfinder routes + TS types | `docker compose exec vite npm run generate` |
| Tests (Pest, on PostgreSQL `traveleasy_test`) | `docker compose exec app php artisan test` |
| Lint / format / type-aware checks | `docker compose exec vite npm run check` |
| TypeScript | `docker compose exec vite npm run types:check` |
| psql into the dev DB | `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'` |
| Restart one service | `docker compose restart <service>` |

Add packages inside the containers so the lockfiles are updated there:
`docker compose exec app composer require <pkg>` / `docker compose exec vite npm install <pkg>`, then rebuild (below).

## When to rebuild

| What changed | What to do |
|---|---|
| Source files (components, controllers, routes, Blade, styles) | Nothing: Vite HMR updates the page; PHP changes show on the next refresh |
| A migration | `docker compose exec app php artisan migrate` |
| `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `Dockerfile`, compose files, new env vars | `docker compose up -d --build -V` |
| Want an empty database | See "Resetting safely" |

`-V` (capital, on `up`) renews the anonymous `vendor` and `node_modules` volumes from the new image. The named
database volume is kept.

**Leftover volumes.** Each `up -V` leaves the previous anonymous `vendor`/`node_modules` volumes behind as dangling
volumes (about 0.5 GB per rebuild). To rebuild without leftovers, remove the two containers together with their
anonymous volumes first; the database is not touched:

```
docker compose rm -s -f -v app vite
docker compose up -d --build
```

Don't use `docker volume prune` to clean up: it also deletes dangling volumes of other projects on this PC.

## Database GUI (Adminer, dev only)
- Start: `docker compose --profile tools up -d`
- Open http://localhost:8081 (`ADMINER_PORT`). Log in with system **PostgreSQL**, server **db** (pre-filled), and
  `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` from `.env`. Use database `traveleasy_test` to look at the test DB.
- Stop: `docker compose --profile tools rm -s -f adminer` (stops and removes it; Adminer keeps no data).
  A plain `docker compose down` doesn't remove profile containers, and a stopped Adminer container left behind can
  fail to start after the network was recreated. If that happens, run the stop command above and start it again.
- No credentials are stored anywhere; Adminer's version check is disabled (`version-noverify`), so it makes no
  external requests. Never set `COMPOSE_PROFILES=tools` permanently in `.env` unless you want Adminer always on.

## Test database
`traveleasy_test` (owned by the app's DB user) is created by `docker/postgres/initdb/10-create-test-database.sh`, but
only when the DB volume is first initialised. On an existing volume, create it once:

```
docker compose exec db sh -c 'createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" traveleasy_test'
```

`phpunit.xml` forces `DB_CONNECTION=pgsql` and `DB_DATABASE=traveleasy_test`; tests never touch the dev database.

## Resetting safely

> **Warning**
> `docker compose down -v` deletes **all** volumes of this project, **including the database**. Ask the owner first.

Safer alternatives:
- Stop without losing data: `docker compose down`.
- Empty schema, same volume: `docker compose exec app php artisan migrate:fresh` (add `--seed` only if you want the
  kit's test account).

## Troubleshooting
- **HMR doesn't update.** Windows bind mounts don't deliver file events to Linux containers, so Vite polls:
  `VITE_USE_POLLING=true`, `VITE_POLLING_INTERVAL=1000` (ms). A lower interval reacts faster but costs CPU. After
  changing them: `docker compose up -d vite`. Measured on TE-002: a `.tsx` edit shows in 1.4 to 1.8 s, idle `vite`
  CPU about 4 to 5 %.
- **Port already in use.** Change `APP_PORT` (and `APP_URL`), `VITE_PORT` or `ADMINER_PORT` in `.env`, then
  `docker compose up -d`.
- **Slow file access.** PHP and Tailwind read source files through the Windows bind mount, which is slower than a
  Linux file system. Tailwind only scans `resources/` for that reason. For more speed, clone the repo into the WSL
  file system (`\\wsl$\…`) and set `VITE_USE_POLLING=false`; the trade-off is that Windows tools then work over a
  network path.
- **`Permission denied` in `storage/` or `bootstrap/cache`.** Files created by a container that ran as root keep root
  ownership on the bind mount. The `app` container prints a warning naming them. Fix once:
  `docker compose run --rm --no-deps --user root --entrypoint chown app -R 1000:1000 storage bootstrap/cache`.
- **CRLF errors (`/bin/sh^M`, `set: Illegal option -`).** `.gitattributes` forces LF for `*.sh` and `Dockerfile`.
  If a script still has CRLF (e.g. it was created before the rule existed), convert that file to LF in your editor,
  then rebuild with `docker compose up -d --build`.
- **Git Bash turns `/dev/null` or `/var/www/...` arguments into Windows paths** for `docker` commands. Prefix the
  command with `MSYS_NO_PATHCONV=1`, or use PowerShell.
- **Changed `DB_PASSWORD` after the first start.** PostgreSQL keeps the password it was initialised with. Change it in
  the DB too: `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'` then
  `ALTER USER traveleasy WITH PASSWORD '…';`.
