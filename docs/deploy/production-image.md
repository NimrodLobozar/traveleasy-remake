# Production image

Built from `Dockerfile`, target `production` (TE-012, ADR-0002). One image serves every service; the first argument of the
entrypoint selects the role.

```
docker build --platform linux/amd64 --target production -t traveleasy:local \
  --build-arg VERSION=<semver> --build-arg REVISION=<git sha> .
docker/prod/verify.sh traveleasy:local      # asserts the acceptance criteria against the built image
```

`verify.sh` creates a throwaway network, PostgreSQL, SSR and app container (all named `te012-verify-<pid>`), runs the app
hardened like production and removes everything it made, also on failure. It never prunes anything.

## Contents

| Part | Detail |
|---|---|
| Base | `dunglas/frankenphp:1.13.4-php8.5.12-trixie`, pinned by tag and digest (digests of Node, Composer and the dev PHP image too) |
| Web server | FrankenPHP classic mode on `:8080`, `docker/prod/Caddyfile`; `setcap -r` applied so it runs under `cap_drop: [ALL]` |
| PHP | extensions `pdo_pgsql intl bcmath pcntl zip` (one `PHP_EXTENSIONS` ARG shared with the dev image), `docker/prod/php.ini` on top of `php.ini-production` |
| Node | the `node` binary only (SSR role). The SSR bundle is self-contained (`ssr.noExternal: true`); there is no npm or `node_modules` |
| Code | `composer install --no-dev --classmap-authoritative`; built client + SSR bundles; no `tests/`, `docs/`, `.git`, `.env`, `resources/js` |
| User | uid/gid 10001. Code is root-owned and not writable by it. Writable: `storage/app` (volume), tmpfs paths below |
| Env defaults | `APP_ENV=production`, `APP_DEBUG=false`, `INERTIA_DEVTOOLS_ENABLED=false`, `LOG_CHANNEL=stderr` |
| Health check | `php healthcheck.php`: `GET 127.0.0.1:8080/up` with `Host:` of `APP_URL`; 15 s interval, 90 s start period |
| Labels | OCI source, revision, version, created, licence (MIT) |

## Entrypoint roles (`traveleasy-entrypoint <role>`)

| Role | Does |
|---|---|
| `app` (default) | waits for the DB (`DB_WAIT_TIMEOUT`, default 60 s), `migrate --force`, `traveleasy:seed-reference --if-empty`, `optimize`, then FrankenPHP. Exits non-zero on any failure. Only this role migrates |
| `ssr` | `node bootstrap/ssr/ssr.js` (no PHP, no DB, no secrets); set `INERTIA_SSR_HOST=0.0.0.0` |
| `worker`, `scheduler` | wait for the DB, `optimize`, then `queue:work` / `schedule:work`; never migrate |
| anything else | `exec "$@"` unchanged: no DB wait, no side effects (e.g. the secrets helper of TE-025) |

Config, route and event caches are written at start into `/tmp/laravel` (`APP_*_CACHE`), never into a layer: the cached
config contains secrets. The build only proves that `optimize` works and discards the output.

## Running it hardened (what compose must provide, TE-024)

`read_only: true`, `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]`, and tmpfs (owned by uid 10001) at `/tmp`,
`/var/www/html/storage/framework`, `/data` and `/config`; a named volume at `/var/www/html/storage/app`.

## Size (AC11)

| Date | Image | Uncompressed (`docker image inspect .Size`) |
|---|---|---|
| 2026-11-02 | FrankenPHP 1.13.4 / PHP 8.5.12, amd64 | **1.017 GB** (ADR estimate 860 MB, limit 1.07 GB) |

Largest layers: FrankenPHP base 631 MB, `node` 127 MB, PHP extensions 99 MB, `vendor` 148 MB (the estimate was 80 MB).

## Rollback rule

Roll back by deploying the previous tag. Migrations are expand/contract (ADR-0002 D4), so `previous tag + new schema` works.
