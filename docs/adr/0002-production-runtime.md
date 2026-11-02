# ADR-0002: Production runtime

- **Status:** Accepted (2026-11-02, decided by the owner; answers in § Owner answers)
- **Date:** 2026-11-02
- **Decided by:** owner. Drafted by the architect for story TE-011; reviewed by the security-privacy-reviewer
  (privilege model, egress, proxy trust, DB roles) as part of TE-011.
- **Includes an owner decision (2026-11-02):** production publishes **no host ports**. Nginx Proxy Manager (NPM)
  reaches the web-facing service over a shared external Docker network `npm_shared`. Only that service joins it; the
  owner does all Portainer and NPM steps himself (D6).
- **Constrains:** TE-012 (production image), TE-024 (production compose), TE-025 (env file + secrets helper), TE-026
  (first deploy); see § Follow-ups.

## Context

`docs/plan.md` § 4 M0 leaves two questions open: the PHP web server (FrankenPHP or Nginx + PHP-FPM), and how the Inertia
SSR Node process is packaged. § 7 fixes the production services: `app`, `ssr`, `worker`, `scheduler`, `db` and `backup`.
The TE-002 security review added more questions: migrate-on-start with rollback compatibility, the DB role split, proxy
trust and outbound egress.

Constraints:
- **Host:** an HP ProDesk office PC (amd64) running CasaOS + Portainer. It already runs NPM and the owner's other demo
  sites, so RAM and CPU are shared.
- **Deploy:** one-file Portainer deploy (an env file plus generated secrets, no repository checkout, no bind mounts).
  Update by bumping a tag; roll back by setting the previous tag (§ 8).
- **Hardening:** non-root, read-only root filesystem, `cap_drop: [ALL]`, `no-new-privileges`, images pinned by tag and
  digest, health checks everywhere.
- **Workload:** a low-traffic portfolio demo. The support chat (M8) uses short polling (about 10 s with the panel open,
  60 s for the badge), not SSE or long polling.
- **Maintainer:** one developer, so prefer boring, documented technology.

Facts checked on 2026-11-02:
- FrankenPHP **1.13.4** (26 Oct 2026, Caddy 2.11.7, PHP 8.5.12 ZTS) is maintained under the PHP Foundation (`github.com/php/frankenphp`).
  1.13.2 to 1.13.4 were security or bug-fix releases, mostly for **worker mode**.
- PHP **8.5.12** (22 Oct 2026) fixes two core security advisories (GHSA-w8hw-w9fx-j79w, GHSA-6wmf-4rv5-p3rp). The dev
  image still pins 8.5.11.
- Inertia v3 production SSR = `vite build --ssr` plus a long-running Node server with `/render`, `/health` and an
  **unauthenticated `/shutdown`** route. The Laravel adapter falls back to client-side rendering when the server is down
  (`config/inertia.php`: `throw_on_error=false`, 2 s timeout).
- Laravel's `Password::uncompromised()` (`NotPwnedVerifier`) **fails open** when the HIBP API can't be reached, but its
  default HTTP timeout is **30 s**.

### Measurements (dev machine, Docker 29.9.2, 2026-11-02)

These cover the bare runtimes, hardened exactly as production will be: non-root, `read_only`, `cap_drop: [ALL]`,
`no-new-privileges`, tmpfs for writable paths. Each served one PHP page that allocates about 2 MB. Size =
`docker image inspect .Size` (uncompressed). RAM = `docker stats` at idle and after 300 requests (10 concurrent). The
full-app numbers are measured again in TE-012 (AC11) with the real image.

| Candidate | Base image(s) | Size | RAM idle | RAM after load |
|---|---|---|---|---|
| FrankenPHP classic mode, 4 threads | `dunglas/frankenphp:1.13.4-php8.5.12-trixie` | 631 MB | 24 MiB | 29 MiB |
| Nginx + PHP-FPM (`pm=ondemand`, 8 children max) | `php:8.5.12-fpm-trixie` + `nginxinc/nginx-unprivileged:stable-alpine` | 517 MB + 60 MB | 10 + 13 MiB | 9–13 + 15 MiB |
| (reference) dev base | `php:8.5.12-cli-trixie` | 571 MB | n/a | n/a |
| Node runtime for SSR | `node:24.21.0-trixie-slim`: `node` binary only | +121 MB (npm +19 MB, not needed) | 42 MiB (bare `node`) | n/a |

Finding from the bench: the official FrankenPHP binary has a **file capability** (`cap_net_bind_service`) set. Under
`cap_drop: [ALL]` + `no-new-privileges`, `exec` fails ("Operation not permitted"). After `setcap -r` it starts and
serves normally as uid 1000 on port 8080.

## Options

### Web server

| | A. FrankenPHP, classic mode (no Octane) | B. Nginx + PHP-FPM | C. FrankenPHP + Octane worker mode |
|---|---|---|---|
| Processes | **One** process per container (Caddy + PHP in one binary) | Two (nginx, php-fpm): a supervisor (s6/supervisord) in one container, or two services | One |
| Image size | ~110 MB more than B | Smallest | As A, plus Octane |
| RAM | Same as B at idle and light load (measured); threads are pre-allocated and bounded by `num_threads` | Lowest at idle with `pm=ondemand` | Highest: the framework stays resident per worker |
| Hardening | Works non-root, read-only, no caps, on :8080 after `setcap -r`; needs tmpfs at `/data` + `/config` (Caddy XDG dirs) | Works (measured). A supervisor in one container complicates non-root/read-only (`/run`, pid files); two services avoid that but add a service, config and health check | As A |
| SSE / long polling | Each open request holds a PHP thread. Way out: Caddy's built-in **Mercure** hub (no extra container) | Each open request holds an FPM child. Way out: Reverb (extra process) | As A, but cheaper per request |
| Build complexity | One Caddyfile (~15 lines), `setcap -r`, extension install via `docker-php-ext-install` | nginx conf, FPM pool conf, FastCGI health probe, plus a supervisor or a second service | As A, plus state-leak discipline (singletons, static state) across requests |
| Laravel support | Official Octane driver gives a later upgrade path; documented by Laravel | The canonical Laravel deployment (Forge, docs) | Official, but worker-mode CVEs in Oct 2026 |
| Risks | Younger stack with frequent security releases; PHP is **ZTS** in production vs NTS in dev/tests | Most mature; more config to own | Request-state bugs; most of the recent FrankenPHP CVEs |

### SSR packaging

| | D. Node in the app image | E. Separate SSR image |
|---|---|---|
| Images to build, scan, pin | **One**; one tag/digest for every service | Two; two digests in the env file, kept in lock-step |
| Size | App image +121 MB (just the `node` binary) | App image unchanged; SSR image ~250 MB, mostly shared base |
| Version skew | Impossible: SSR bundle, client manifest and PHP adapter come from one build | Possible: a mismatched pair silently breaks hydration |
| Attack surface | `node` present in PHP containers (marginal for an attacker who already has RCE) | Smaller per image |
| One-file deploy | One `APP_IMAGE_TAG` value | Two values to bump together |

## Decision

### D1. Web server: FrankenPHP in classic mode (option A)

- **Image:** the production image is built `FROM dunglas/frankenphp:<frankenphp>-php<php>-trixie@sha256:…`, pinned by
  version **and** digest. It starts at FrankenPHP 1.13.4 / PHP 8.5.12 or newer.
- **Classic mode only.** No worker mode and no Octane. Switching to either needs a new ADR backed by measurements.
- **Committed `docker/prod/Caddyfile`:**
  - `auto_https off` (TLS ends at NPM), `admin off` (no admin API on :2019), `skip_install_trust`;
  - listen on **`:8080`**, `root public/`, `php_server`, `encode zstd br gzip`;
  - request body capped at 2 MB (there are no uploads in v1);
  - Caddy access log **off** (IP + URL are personal data; the app logs requests itself, and NPM has its own log);
  - `frankenphp { num_threads ${FRANKENPHP_NUM_THREADS:-4} }`.
- **`setcap -r /usr/local/bin/frankenphp`** at build time, so the binary runs under `cap_drop: [ALL]`.
- **PHP extensions** (`pdo_pgsql intl bcmath pcntl zip`) are listed once, in a build ARG or script that the `dev` and
  `production` stages share, so the two lists can't drift.

**Why A over B:**
- One process per container, with no supervisor.
- **One image serves every service** (`app`, `ssr`, `worker`, `scheduler`).
- Hardened operation is measured and works.
- RAM matched B in the bench.
- Static assets are served precompressed by the same process.
- If real-time is ever needed, Mercure is an option without an extra container.

**Accepted costs:**
- About 110 MB more image than B.
- A faster security-release cadence to follow (D8: Dependabot on the digest, Trivy, scheduled rebuild).
- **ZTS PHP in production vs NTS in dev/tests.** Mitigation: CI runs the smoke suite against the production image
  (TE-024 CI job). Moving dev onto the same base is a later option, not a requirement.

**Fallback:** if FrankenPHP causes trouble, option B as two services from the same image (nginx installed in the image).
That change is limited to the Dockerfile and the compose file; application code doesn't change.

### D2. SSR packaging: Node inside the app image (option D)

- The production stage copies **only `/usr/local/bin/node`** from the pinned `node:<24.x>-trixie-slim@sha256:…`. There's
  no npm/npx and no `node_modules` at runtime.
- The SSR bundle must be **self-contained** (Vite `ssr.noExternal: true`). TE-012 proves that `node bootstrap/ssr/ssr.js`
  starts in the final image without `node_modules`. If one dependency can't be bundled, only that package's production
  files are copied, never a full `npm ci`.
- **The `ssr` service runs `node bootstrap/ssr/ssr.js` directly**, not `php artisan inertia:start-ssr`.
  - It then needs no PHP boot, no `APP_KEY`, no DB variables and no secrets in its environment (TE-008 QC).
  - The production boot check (TE-012 AC8) can't block it.
  - It listens on `0.0.0.0:13714` (`INERTIA_SSR_HOST`, `INERTIA_SSR_PORT`, read by `resources/js/ssr.tsx`).
  - `app` reaches it at `INERTIA_SSR_URL=http://ssr:13714`.
- **Effect:**
  - one image and one tag/digest for all services;
  - about 121 MB uncompressed added;
  - Trivy scans Node CVEs as part of the one image;
  - `ensure_bundle_exists=true` keeps working because `app` has the bundle.
- **`ssr` limits:**
  - memory limit 256 MB, with `NODE_OPTIONS=--max-old-space-size=160`, so V8 collects garbage before the cgroup kills it;
  - 1 CPU, no clustering.
- **Health check:** `node -e "fetch('http://127.0.0.1:13714/health').then(r=>process.exit(r.ok?0:1),()=>process.exit(1))"`.
- `/shutdown` is unauthenticated, so `ssr` must never be on `npm_shared` and never publish a port (D6).

### D3. Processes and roles

- **One image, one entrypoint**, `traveleasy-entrypoint <role>`, with an explicit role per compose service:

| Role (`command:`) | Process | Migrates | Notes |
|---|---|---|---|
| `app` | `frankenphp run --config /etc/frankenphp/Caddyfile` | **yes** (D4) | the only web-facing service; exactly **1 replica** |
| `ssr` | `node bootstrap/ssr/ssr.js` | no | no PHP, no DB, no secrets |
| `worker` | `php artisan queue:work --tries=3 --backoff=10,60 --max-time=3600 --max-jobs=500 --memory=256` | **never** | exits periodically on purpose; the restart policy brings it back with fresh code and memory |
| `scheduler` | `php artisan schedule:work` | **never** | exactly **1 replica**; schedule time zone Europe/Amsterdam from config (containers stay UTC) |
| anything else | `exec "$@"` unchanged | no | for one-off commands, e.g. the secrets helper (TE-025), with no DB wait and no boot side effects |

- `worker`, `scheduler` and `backup` arrive with their features (M1 onwards; TE-024 out of scope). M0 ships `app`, `ssr`
  and `db`.
- `init: true` on `worker`, `scheduler` and `ssr`, so zombie children (from `schedule:work`) are reaped.
- `stop_grace_period`: 60 s for `worker`, 30 s for the rest.
- Queue, cache and session stay on the **database** driver (ADR-0001), so there's no Redis.
- **Env-dependent caches are written at start, never at build.**
  - The `app`, `worker` and `scheduler` roles run `php artisan optimize` (config, routes, events, views) on start.
  - The output goes to tmpfs: `APP_CONFIG_CACHE`/`APP_ROUTES_CACHE`/`APP_EVENTS_CACHE` point into a tmpfs path, and
    `storage/framework` is tmpfs.
  - The cached config contains secrets, so it is never baked into a layer or written to a persistent volume.
  - The package/service manifests (`bootstrap/cache/packages.php`, `services.php`) are build artefacts and stay in the
    image.
  - CI still proves `optimize` succeeds (TE-013).

### D4. Migrate-on-start and rollback

- **Only the `app` role migrates**, as an explicit branch of the entrypoint. `worker`, `scheduler`, `ssr` and pass-through
  commands never do.
  - This is safe without `--isolated`, because `app` is a single replica. `--isolated` needs a cache lock table, which
    doesn't exist on an empty DB.
  - Scaling `app` beyond one replica would first need a separate one-shot migration service (new ADR).
- **Sequence for `app`:**
  1. Wait for the DB: a PDO connect loop with a timeout (`DB_WAIT_TIMEOUT`, default 60 s) and a clear message, on top of
     `depends_on: db (service_healthy)`.
  2. `php artisan migrate --force`.
  3. `php artisan traveleasy:seed-reference --if-empty`.
  4. `php artisan optimize`.
  5. `exec frankenphp`.
- **Reference seeding "on an empty DB only"** is per table: the command seeds each reference table it owns only when that
  table is empty. A first boot that failed half-way is therefore repaired on the next start, and staff edits are never
  overwritten. In M0 the command exists and is a no-op. `demo` is **never** seeded by the entrypoint (ADR-0006 runs it in
  demo mode only), and the kit's `test@example.com` user never exists in production.
- **Failed migration:**
  - The entrypoint exits non-zero with Laravel's error, never printing env values. No web server starts.
  - The container never becomes healthy, and Portainer shows it restarting or unhealthy.
  - On PostgreSQL each migration runs in its own transaction, so a failing migration leaves no partial DDL. Earlier
    migrations of the same release stay applied.
  - Recovery: set the previous tag (works, see the next rule) or ship a fix-forward tag.
- **Rollback compatibility rule (expand/contract):**
  - A release's migrations must be backward compatible with the **previous** release, so `previous tag + new schema`
    works. A release may add tables, nullable or defaulted columns and indexes.
  - Dropping, renaming or tightening anything the previous release uses (`dropColumn`, `renameColumn`, `->change()` to a
    stricter type, `drop`/`rename` of tables, new `NOT NULL` without a default) waits for a later release.
  - Rollback never needs `down()` migrations. An older release just ignores newer rows in `migrations`.
  - Data-destroying migrations need a manual backup first (deploy doc, TE-026).
  - Proposed CI guard (TE-013 follow-up): flag those calls in migrations added since the last tag, unless the migration
    carries an `// expand-contract: contract step` marker.

### D5. Database roles

- **Two roles:**
  1. the Postgres superuser (`postgres`, password `DB_SUPERUSER_PASSWORD`), used **only** by `db` itself and a one-shot
     `db-init` service;
  2. the app role `DB_USERNAME` (default `traveleasy_app`, password `DB_PASSWORD`), used by `app`, `worker`, `scheduler`
     and `backup`.
  The superuser credentials are never passed to those services.
- **App role:** `LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS`.
  - It holds `CONNECT, TEMP` on the database and **owns the `public` schema**, so it can migrate.
  - The **database is owned by the superuser**, so the app role can't `DROP DATABASE` or `CREATE ROLE` (TE-024 AC5 proves
    both).
- **Creation on first run, kept in sync on every deploy:**
  - `db-init` uses the **same digest-pinned `postgres` image** as `db`, with an inline `command:` script in `compose.yaml`
    (no bind-mounted init file, so it works when pasted into Portainer).
  - It runs idempotently on every deploy. It creates the role if missing, re-applies the attributes and password (this is
    how the DB password is rotated: change the env var, redeploy), creates the database if missing and applies grants.
  - Identifiers and the password are passed as psql variables and quoted with `format('%I')` / `:'var'`, never
    string-concatenated.
  - `app` depends on it with `condition: service_completed_successfully`.
- **One app role does both DDL and DML** (pragmatic ASVS L2 baseline). A further split into a migrator role and a runtime
  role without DDL rights was considered and deferred: default-privilege management costs too much for a solo project.
- **Dev and CI are unchanged:** the dev role stays the superuser, because parallel tests create `traveleasy_test_test_N`
  databases.
- **Consequence for the one-file deploy (owner-accepted 2026-11-02):** the secret list grows from 4 to **5**: `DB_SUPERUSER_PASSWORD`,
  `DB_PASSWORD`, `APP_KEY`, and from M1 `SUPER_ADMIN_PASSWORD_HASH` and `SUPER_ADMIN_TOTP_SECRET`. The helper (TE-025)
  generates both DB passwords.

### D6. Networks and the reverse-proxy contract with NPM (owner decision 2026-11-02)

- **No service publishes a host port in production.** There's no `ports:` anywhere in the production file.
- **Networks:**
  - `app` (the only web-facing service) joins **`default`** and **`npm_network`**.
  - `npm_network` is declared at the bottom of `compose.yaml` as
    `networks: { npm_network: { name: npm_shared, external: true } }`.
  - `db`, `db-init`, `ssr`, `worker`, `scheduler` and `backup` are on `default` only.
  - **`default` is declared `internal: true`** (owner-confirmed 2026-11-02), so those services have no route to the internet. That's the egress
    stance in D7, and it keeps today's `backend` network behaviour.
  - `ssr` shares `default` with `db` but holds no DB credentials.
- **NPM target:** `http://traveleasy-app:8080`, plain HTTP.
  - `traveleasy-app` is a **network alias on `npm_network`**. Other stacks on `npm_shared` may also have a service called
    `app`, so the bare service name is ambiguous there.
  - The owner creates the proxy host in NPM: an access list for LAN-only in M0, public from M6.
- **HTTPS ends at NPM.** Inside the network it's HTTP on 8080.
  - NPM's "Force SSL" is on.
  - **HSTS is set by the app only**, and only when `APP_URL` is `https` (TE-015), so it's covered by tests. NPM's HSTS
    switch stays off to avoid a second, untested header.
  - `SESSION_SECURE_COOKIE=true` with `https`; the M0 LAN-over-HTTP exception is documented in TE-024.
- **TrustProxies:** `TRUSTED_PROXIES` is the **CIDR of `npm_shared`**, read by the owner with
  `docker network inspect npm_shared` (TE-026). It's never `*` and never empty-means-all. The app is reachable only through
  that network, so that's the full set of possible peers. Trusting the CIDR means every container on `npm_shared` (only
  the owner's own) can set `X-Forwarded-*`. Pinning NPM's single IP is tighter but breaks when NPM is recreated, so it's
  documented as an option, not the default. `trustHosts()` from the `APP_URL` host (M1 follow-up) completes the contract.
- **Health checks** call `127.0.0.1:8080` inside the container with `Host: <APP_URL host>`, so `trustHosts()` doesn't
  reject them.

### D7. Outbound egress

- **M0 needs no egress at all.** No service calls out: Caddy's ACME is off, and there's no mail (ADR-0005), no CDN and no
  telemetry.
- **From M1, the HIBP range API is allowed from `app` only.**
  - Endpoint: `api.pwnedpasswords.com`. Only the 5-character SHA-1 prefix is sent, with `Add-Padding`; no personal data.
  - The route goes through `npm_shared`, the only non-internal network of `app`.
  - There's no domain allow-list or egress proxy in v1; the cost isn't justified for one call. Revisit at the M6 public
    launch review.
- **The breached-password check fails open by design.** M1 must bind the verifier with a **short timeout** (≈ 3 s,
  `config/traveleasy.php`) instead of Laravel's 30 s default, behind the existing config flag. A test proves that
  registration succeeds quickly when the API is unreachable.
- `worker`, `scheduler`, `ssr`, `db` and `backup` have no egress (internal `default` network).
- Later outbound needs require an ADR line and egress review before they ship: Mollie (M11) and the chatbot provider (M12).

### D8. Hardening, health checks and resource limits

**Every service:**
- `restart: unless-stopped`;
- `read_only: true` plus tmpfs/volumes for writable paths (`db` excepted, documented);
- `cap_drop: [ALL]`, with nothing added back for app/ssr/worker/scheduler;
- `security_opt: [no-new-privileges:true]`;
- `pids_limit`;
- json-file logging capped with `max-size`/`max-file`.

**User and file ownership:**
- Production runs as a dedicated **uid/gid 10001** with no counterpart on the host. Dev keeps 1000 for bind mounts.
- **Application code is owned by root and read-only to the app user.** This deviates from TE-012 AC2's "owned by the app
  user" (defence in depth; see § Follow-ups).

**Writable paths:**
- tmpfs: `/tmp`, `storage/framework`, the config-cache path, and `/data` + `/config` (Caddy);
- named volume `storage` at `storage/app` for private files (exports, PDFs). It is pre-created in the image with the app
  uid, so Docker copies the ownership on the first mount.
- Logs go to stderr (JSON), never to files.

**Production PHP ini:** `display_errors=Off`, `expose_php=Off`, `opcache.validate_timestamps=0`, `memory_limit=128M`
for web (256M for worker), small `post_max_size`/`upload_max_filesize` matching the 2 MB body cap.

**Health checks** (standalone Docker reports health but doesn't restart on it; long-lived processes exit on their own
limits and come back through the restart policy):

| Service | Check | Timing |
|---|---|---|
| `app` | PHP one-liner `GET http://127.0.0.1:8080/up` (no curl in the image). `/up` returns 200 only if the DB answers (a `DiagnosingHealth` listener), with no details in the body | interval 15 s, timeout 5 s, retries 3, start_period 90 s (migrations), so unhealthy within ~60 s of the DB going away |
| `ssr` | `node` fetch of `/health` (D2) | 30 s / 5 s / 3 |
| `worker` (M1+) | `php artisan traveleasy:healthcheck worker`: a heartbeat job dispatched every minute by the scheduler must have been processed in the last 3 min, which proves the scheduler → queue → worker chain | 60 s / 10 s / 3 |
| `scheduler` (M1+) | `php artisan traveleasy:healthcheck scheduler`: heartbeat written by a scheduled closure, < 3 min old | 60 s / 10 s / 3 |
| `db` | `pg_isready` (as today) | as today |

**Resource limits** (service-level `mem_limit`/`memswap_limit` (= no swap)/`cpus`/`pids_limit`, each an env variable with
this default in the Portainer env file; TE-024 verifies with `docker stats` that Portainer applies them):

| Service | Memory | CPUs | Notes |
|---|---|---|---|
| `app` | 768 MB | 2.0 | 4 PHP threads × 128 MB worst case, plus runtime |
| `ssr` | 256 MB | 1.0 | V8 heap capped at 160 MB |
| `worker` | 384 MB | 1.0 | revisit with ADR-0004 (PDF library) |
| `scheduler` | 384 MB | 1.0 | the demo reset (M6) is measured here |
| `db` | 1 GB | 2.0 | default `shared_buffers`; tune only after measuring |
| `db-init` | 128 MB | 0.5 | one-shot |
| `backup` | 256 MB | 0.5 | when added |

The limits add up to ≈ 3.2 GB as a ceiling. Expected idle use is **< 1 GB** for the whole stack (TE-024 target). This
assumes the ProDesk has ≥ 8 GB RAM, which the owner confirmed on 2026-11-02.

**Image hygiene:**
- base images (FrankenPHP, Node, Postgres) pinned by tag **and** digest;
- OCI labels;
- Dependabot (or Renovate) watches the digests and the PHP/FrankenPHP versions;
- Trivy blocks publishing on fixable HIGH/CRITICAL;
- a **scheduled weekly rebuild** of the latest release branch picks up base-image patches (TE-014 follow-up).

### D9. Image size estimate (for TE-012 AC11)

FrankenPHP base 631 MB + extensions ≈ 15 MB + `node` 121 MB + `vendor` (no-dev) ≈ 80 MB + built assets and SSR bundle
≈ 10 MB ≈ **860 MB uncompressed** (`docker image inspect .Size`). AC11 fails above **≈ 1.07 GB** (+25 %).

## Consequences

- One image, one tag, one optional digest (`APP_IMAGE_TAG` may be `vX.Y.Z@sha256:…`). All services always run the same
  release; SSR/client skew can't happen.
- The production base differs from dev (FrankenPHP ZTS vs `php:cli` NTS). The production image is therefore smoke-tested
  in CI, and the extension list is shared between the stages.
- Production starts at **PHP 8.5.12**. The dev image (8.5.11) should be bumped as well; that's a separate devops chore,
  not part of this ADR.
- A FrankenPHP security release means a base-image bump and a new tag. Classic mode keeps the recent worker-mode issues
  out of scope, but its releases still have to be followed.
- Migrations follow expand/contract, which costs an extra release for renames and drops. In exchange, rollback is always
  "set the previous tag".
- The env file gains `DB_SUPERUSER_PASSWORD` (5 secrets instead of 4) plus non-secret defaults: `DB_USERNAME`,
  `TRUSTED_PROXIES`, `INERTIA_SSR_URL`, `FRANKENPHP_NUM_THREADS` and the resource limits.
- No real-time push in v1. A long-lived request would block a PHP thread, so no endpoint may hold a request open for
  more than a few seconds. Real-time later means Mercure (built into FrankenPHP) or Reverb, decided in a new ADR.
- Everything on `npm_shared` is trusted for forwarded headers. If other, less-trusted containers ever join that network,
  switch `TRUSTED_PROXIES` to NPM's IP.

## Follow-ups

Stories this ADR constrains (the task-writer updates their wording now that it's Accepted):

- **TE-012 (production image):**
  - FrankenPHP base pinned by digest, `setcap -r`, Caddyfile from D1;
  - Node binary only and a self-contained SSR bundle (D2); role entrypoint (D3, D4);
  - `traveleasy:seed-reference --if-empty`; caches written at start to tmpfs; uid 10001;
  - **AC2 wording:** code owned by root and read-only to the app user, instead of "owned by the app user";
  - health check with the `Host` header; size estimate from D9.
- **TE-024 (production compose):**
  - networks per D6 (`default` internal, `npm_shared` external, `traveleasy-app` alias, no `ports:`);
  - `db-init` service and role split (D5); limits and health checks from D8;
  - `ssr` command `node bootstrap/ssr/ssr.js` (not artisan);
  - **AC2 wording:** "only `app` joins `npm_shared`; no service publishes a port".
- **TE-025 (env file + secrets helper):** the helper also generates `DB_SUPERUSER_PASSWORD`. It runs through the
  entrypoint's pass-through (no DB wait), so the production boot check (TE-012 AC8) must not block it.
- **TE-026 (first deploy):** read the `npm_shared` CIDR into `TRUSTED_PROXIES`; NPM proxy host to `traveleasy-app:8080`
  with a LAN access list; NPM HSTS off; manual-backup step before destructive migrations.
- **TE-013 / TE-014 (CI):** production-image smoke run (ZTS check), the expand/contract migration guard (proposed),
  Dependabot on digests, a weekly rebuild.
- **M1 stories:** HIBP verifier with a ≈ 3 s timeout and a fail-open test; `trustHosts()`; worker/scheduler heartbeat
  health checks.
- **Devops chore:** bump the dev image to PHP 8.5.12.

## Owner answers (2026-11-02)

1. **Web server:** FrankenPHP in classic mode is accepted. Nginx + PHP-FPM stays the documented fallback (D1).
2. **Capacity:** the ProDesk has ≥ 8 GB RAM, so the D8 limits stand as proposed.
3. **Fifth secret:** `DB_SUPERUSER_PASSWORD` is accepted. The secrets helper (TE-025) generates it with the others (D5).
4. **Egress:** `default: internal: true` is confirmed; only `app` has outbound access, through `npm_shared` (D6, D7).
