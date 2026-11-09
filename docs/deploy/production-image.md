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
| Health check | `php healthcheck.php`: `GET 127.0.0.1:8080/up` with `Host:` of `APP_URL`; 10 s interval, 5 s timeout, 3 retries, 90 s start period. `/up` (`App\Http\Controllers\HealthController`, TE-028) answers a plain-text `OK` (200) only when the database answers a query (`App\Support\Health\DatabaseHealthCheck`), otherwise a plain-text `Unavailable` (500), with no HTML, external URLs or cookies, after one connection attempt (no framework retries), so the container turns unhealthy within 60 s of the DB going away (38 s measured on Docker Desktop, where the failing DNS lookup alone takes about 4 s) |
| Labels | OCI source, revision, version, created, licence (MIT) |

## Entrypoint roles (`traveleasy-entrypoint <role>`)

| Role | Does |
|---|---|
| `app` (default) | boot check, waits for the DB (`DB_WAIT_TIMEOUT`, default 60 s), `migrate --force`, `traveleasy:seed-reference --if-empty`, `optimize`, then FrankenPHP. Exits non-zero on any failure. Only this role migrates |
| `ssr` | `node bootstrap/ssr/ssr.js` (no PHP, no DB, no secrets); set `INERTIA_SSR_HOST=0.0.0.0` |
| `worker`, `scheduler` | boot check, wait for the DB, `optimize`, then `queue:work` / `schedule:work`; never migrate |
| anything else | `exec "$@"` unchanged: no DB wait, no side effects (e.g. the secrets helper of TE-025) |

**Boot check** (`php artisan traveleasy:boot-check`, TE-012 AC8): the `app`, `worker` and `scheduler` roles exit 1 before
the DB wait, with a `Refusing to start: …` line per problem, when `APP_ENV=production` and `APP_DEBUG=true`, when `APP_KEY`
is empty or not a valid key for the cipher, or when `TRUSTED_PROXIES` is not a list of specific IPs (`*`, `**`,
`REMOTE_ADDR` and `/0` are refused everywhere; in production any network range is refused too). Messages name the variable, never its value. The `ssr` role and the pass-through never run it.

**Failed migration** (AC7): `migrate --force` failing stops the `app` role with exit 1 and the migration error before
FrankenPHP starts, so a half-migrated app never serves. `verify.sh` proves it against a database with a conflicting table.

Config, route and event caches are written at start into `/tmp/laravel` (`APP_*_CACHE`), never into a layer: the cached
config contains secrets. The build only proves that `optimize` works and discards the output.

## Running it hardened (what compose must provide, TE-024)

`read_only: true`, `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]`, and tmpfs (owned by uid 10001) at `/tmp`,
`/var/www/html/storage/framework`, `/data` and `/config`; a named volume at `/var/www/html/storage/app`.

## Behind Nginx Proxy Manager (AC12, ADR-0002 D6)

In production `TRUSTED_PROXIES` is NPM's single fixed IP on `npm_shared` (set `ipv4_address` in NPM's stack, read it with
`docker inspect`; see `casaos-portainer.md`), never the network CIDR. Dev and tests may still use ranges. Empty means no proxy is trusted. Only `X-Forwarded-For`,
`-Proto` and `-Port` are honoured, and only from those addresses (`App\Http\Middleware\TrustProxies`); `X-Forwarded-Host`,
`-Prefix` and `Forwarded` never are. With `X-Forwarded-Proto: https` from NPM, generated URLs (routes, `asset()`, Vite) are
https. A wildcard value makes the boot check refuse to start and the middleware refuse to serve.

## Size (AC11)

| Date | Image | Uncompressed (`docker image inspect .Size`) |
|---|---|---|
| 2026-11-02 | FrankenPHP 1.13.4 / PHP 8.5.12, amd64 | **1.017 GB** (ADR estimate 860 MB, limit 1.07 GB) |

Largest layers: FrankenPHP base 631 MB, `node` 127 MB, PHP extensions 99 MB, `vendor` 148 MB (the estimate was 80 MB).

## How a release is cut and the digest checked (TE-035)

The `release` job in `.github/workflows/ci.yml` builds `production` once (local daemon), verifies and Trivy-scans it, then:
1. pushes that same local image (no rebuild) under a staging tag `ci-<sha7>` and notes the digest from `docker push`;
2. `scripts/ci/compare-diff-ids.sh` compares the rootfs `diff_ids` of the pushed digest with the scanned local image; a
   mismatch fails the job before any release tag exists;
3. `docker buildx imagetools create --prefer-index=false` retags the single manifest registry-side: `sha-<7>` (and `vX.Y.Z`
   on a `v*.*.*` tag) get exactly that digest at top level; never `latest`. Without `--prefer-index=false` buildx wraps the
   manifest in a new index and the digest changes;
4. each tag is re-inspected and must resolve to the pushed digest, else the job fails.

The staging tag `ci-<sha7>` stays in GHCR (same digest, harmless, not a release tag; delete it from the package page if wanted).
Check by hand: `docker buildx imagetools inspect ghcr.io/nimrodlobozar/traveleasy:vX.Y.Z`. Note: SBOM/provenance
attestations are no longer attached to the image: the push-by-digest route pushes a locally loaded image, which cannot
carry them.

## Rollback rule

Roll back by deploying the previous tag. Migrations are expand/contract (ADR-0002 D4), so `previous tag + new schema` works.

## Hidden files (TE-028)

The Caddyfile answers 404 for every path starting with a dot (`/.env`, `/.git/...`, `/.htaccess`), except `/.well-known/`.
The kit's `public/.htaccess` is also removed from the image. Everything under `/var/www/html` is owned by root except the
`storage/app` volume; `verify.sh` checks both.

## Secrets helper and maintenance (TE-025, TE-019)

- `docker run --rm -it --log-driver none <image> php artisan traveleasy:generate-secrets [--without-super-admin] [--light-terminal]` goes
  through the pass-through role (no DB wait, no boot check). `-it` is required: without a terminal it exits 1 before
  printing. `--without-super-admin` prints only the three M0 secrets (works without a TTY, used by CI and `verify.sh`);
  the super_admin variables only become used in M1, so they are not in the env file yet. The password hash is printed as
  `base64:...` so compose never expands its `$` signs. `--force-ci` is needed when `CI=true`.
- `php artisan down` is used **without** `--render`: the 503 page comes from the error handler (localised, with headers).
- When PHP or the upstream fails (502/504), the Caddyfile `handle_errors` block serves a static nl/en page with the strict
  CSP (`default-src 'none'`), no script and no style, so it needs no nonce. `verify.sh` checks the block is present.
