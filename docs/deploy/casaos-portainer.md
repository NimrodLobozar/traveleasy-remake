# Deploying on CasaOS with Portainer

Production runs as one Portainer stack made from [`compose.yaml`](../../compose.yaml) and a set of environment variables
([`deploy/portainer.env.example`](../../deploy/portainer.env.example)). The image comes from GHCR (pinned tag, built by CI).
Public access goes through the existing Nginx Proxy Manager (NPM); the stack publishes **no host port**.

Status: the stack, the env file and the secrets helper are finished and tested (TE-024, TE-025); the first real deploy is
TE-026 (owner-gated); CI builds and publishes the image (TE-014, see "Releases" below). Until `v0.1.0` exists the tag in the env file is a placeholder.

## What runs

| Service | Role | Network | Limits (default) | Notes |
|---|---|---|---|---|
| `db-init` | one-shot, runs on every deploy | `default` | 128 MB, 0.5 CPU | creates/updates the restricted DB role `DB_USERNAME`; exits 0, so `Exited (0)` is normal |
| `db` | PostgreSQL 18 | `default` | 1 GB, 2 CPU | data in volume `pgdata`; no port on the host |
| `ssr` | Inertia server-side rendering | `default` | 256 MB, 1 CPU | no secrets, no DB access; if it is down the site still works (client-side rendering) |
| `app` | web (FrankenPHP on :8080) | `default` + `npm_shared` as `traveleasy-app` | 768 MB, 2 CPU | the only service that migrates; always **1 replica** |

Every service has a read-only root filesystem, no Linux capabilities, `no-new-privileges`, no swap and capped logs
(10 MB x 3). `default` is an **internal** network: `db`, `db-init` and `ssr` cannot reach the internet. Only `app` is on
`npm_shared`, and `ssr` must never be (its `/shutdown` endpoint has no authentication).

**Later services** (`worker`, `scheduler`, `backup`, from M1): they use the same image and the same `default` network. Each
MUST declare `depends_on: app: condition: service_healthy`, so they never run new code while `app` is still migrating.
`scripts/ci/compose-guard.mjs` fails CI if they do not.

## Before the first deploy

1. **The network `npm_shared` must exist.** It is created by your NPM stack. Check in a CasaOS terminal (or Portainer ->
   Networks): `docker network ls | grep npm_shared`. If the stack deploys without it, Docker stops with
   `network npm_shared declared as external, but could not be found`; fix the network, then redeploy.
2. **Give NPM a fixed IP on `npm_shared`** and read it. `TRUSTED_PROXIES` is that **single IP**, never the network range: trusting
   the whole range (including Docker's gateway) would let a neighbouring container or a request through the gateway spoof client IPs.
   In NPM's own stack, set the address under its service, for example
   `networks: { npm_shared: { ipv4_address: 172.20.0.2 } }` (pick a free address inside the subnet shown by
   `docker network inspect npm_shared --format '{{(index .IPAM.Config 0).Subnet}}'`; the network needs an explicit subnet), then redeploy NPM.
   Read the result: `docker inspect <npm container> --format '{{(index .NetworkSettings.Networks "npm_shared").IPAddress}}'`.
   The app refuses to start in production when `TRUSTED_PROXIES` is `*` or a range wider than a single address.
3. **Generate the secrets** with the helper in the published image. It needs only Docker (no PHP, no database, no network),
   writes nothing to disk and prints ready-to-paste lines. Run it in the CasaOS terminal or on your PC:

   ```
   docker run --rm -it --log-driver none ghcr.io/nimrodlobozar/traveleasy:<tag> php artisan traveleasy:generate-secrets --without-super-admin
   ```

   - `--log-driver none` keeps Docker from storing the printed secrets in the container log.
   - `-it` is required: without a terminal the command refuses to print anything (passwords are never read from arguments).
   - `--without-super-admin` is right for M0. The two super_admin variables (`SUPER_ADMIN_PASSWORD_HASH`,
     `SUPER_ADMIN_TOTP_SECRET`) only become used in M1, so they are not in the env file yet. Leave the flag out from M1 on:
     the helper then asks for the password (hidden prompt) and draws the TOTP QR code in the terminal.
   - On a light terminal background add `--light-terminal` so the QR code stays readable.
   - It prints `DB_SUPERUSER_PASSWORD=...`, `DB_PASSWORD=...` and `APP_KEY=base64:...` (two independent passwords and the
     Laravel key). The super_admin password hash is printed as `base64:...` on purpose: a raw bcrypt hash contains `$`
     signs, which compose would try to expand.
   - Copy the lines for the next step and close the terminal; run the command again for new values, never reuse old ones.
     Never share the output.

## First deploy (Portainer UI)

1. Portainer -> **Stacks** -> **Add stack**. Name: `traveleasy` (the volumes are then named after the stack).
2. Build method **Web editor**. Paste the full content of `compose.yaml`.
3. Under **Environment variables** choose **Load variables from .env file** and pick `deploy/portainer.env.example`
   from your PC (or switch to the advanced mode and paste its lines). The file already holds working defaults for everything
   else. Then edit only these values (they are marked `EDIT` or `SECRET` in the file):
   - `APP_IMAGE_TAG`: the released tag you deploy, for example `v0.1.0` (never `latest`).
   - `APP_URL`: the public https address NPM serves, for example `https://traveleasy.example.com`.
   - `TRUSTED_PROXIES`: NPM's fixed IP from step 2 above.
   - `DB_SUPERUSER_PASSWORD`, `DB_PASSWORD`, `APP_KEY`: paste the three lines from step 3 of the preparation.
4. Click **Deploy the stack**. Portainer reports an error naming the variable if a required one is empty.
5. Open **Containers**. Within about 2 minutes `db`, `ssr` and `app` are `healthy` and `db-init` is `exited (0)`.
   `app` needs the longest (migrations on the first start).
6. Add the proxy host in NPM (see below).

If `app` keeps restarting, open its **Logs**: the entrypoint says why (`Refusing to start: ...` names the wrong variable,
never its value).

## Nginx Proxy Manager host

In NPM: **Hosts -> Proxy Hosts -> Add Proxy Host**.

| Field | Value |
|---|---|
| Domain Names | your domain |
| Scheme / Forward Hostname / Port | `http` / `traveleasy-app` / `8080` |
| Block Common Exploits | on |
| Websockets Support | not needed |
| SSL | request or select a certificate, **Force SSL** on, **HSTS off** (the app sends HSTS itself, only for https) |
| Access List (while testing, M0) | a LAN-only list |

Use the alias `traveleasy-app`, not `app`: other stacks on `npm_shared` may also have a service called `app`.
For a short LAN-only check over plain http set `SESSION_SECURE_COOKIE=false` and an `http://` `APP_URL`, then put both back.

## Variables

All settings come from the stack's environment variables; no file on the server is needed. Required ones make Docker stop with
a message that names them.

| Variable | Required | Default | Meaning |
|---|---|---|---|
| `APP_IMAGE_TAG` | yes | `v0.1.0` (placeholder) | image tag of `app` and `ssr` (same image) |
| `APP_URL` | yes | placeholder | public https URL |
| `APP_KEY` | yes, secret | empty | Laravel key (`base64:...`) |
| `DB_SUPERUSER_PASSWORD` | yes, secret | empty | PostgreSQL superuser; only `db` and `db-init` get it |
| `DB_PASSWORD` | yes, secret | empty | password of the app role; rotate by changing it and redeploying (`db-init` applies it) |
| `TRUSTED_PROXIES` | yes | empty | the single fixed IP of NPM on `npm_shared` (no ranges in production) |
| `DB_USERNAME`, `DB_DATABASE` | no | `traveleasy_app`, `traveleasy` | app role and database name |
| `SESSION_SECURE_COOKIE` | no | `true` | keep `true` behind https |
| `LOG_LEVEL`, `APP_LOCALE`, `APP_NAME` | no | `warning`, `nl`, `TravelEasy` | |
| `FRANKENPHP_NUM_THREADS`, `DB_WAIT_TIMEOUT` | no | `4`, `60` | web threads; seconds `app` waits for the DB |
| `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL`, `INERTIA_SSR_TIMEOUT` | no | `true`, `http://ssr:13714`, `2` | server-side rendering |
| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD`, `CSP_REPORT_PATH` | no | see file | security headers |
| `APP_`, `SSR_`, `POSTGRES_`, `DBINIT_` + `MEM_LIMIT`, `CPUS`, `PIDS_LIMIT` | no | see file | resource limits per service |

## Update

1. Portainer -> **Stacks** -> `traveleasy` -> **Editor**.
2. In **Environment variables** change `APP_IMAGE_TAG` to the new tag.
3. Click **Update the stack** with **Re-pull image and redeploy** on.
4. Watch `app` Logs for `migrating`, then wait until it is `healthy`. The data in `pgdata` and `storage` stays.

Migrations are backward compatible with the previous release (expand/contract, ADR-0002 D4). A release that deletes data
needs a manual backup first (see below).

## Roll back

Set `APP_IMAGE_TAG` to the previous tag and update the stack the same way. Because migrations are expand/contract, the
previous release runs on the newer schema. Never delete the volumes to "roll back".

## Backup and restore (manual until the `backup` service exists)

The scheduled dump service is a later story. Until then, take a dump before every update that deletes data:

- Dump (CasaOS terminal). `umask 077` makes the dump readable by you only; the database talks over the container's local
  socket, so no password is needed on the command line or in your shell history:

  ```
  umask 077
  docker exec <stack>-db-1 pg_dump -U postgres -Fc traveleasy > traveleasy-$(date +%F).dump
  ```
- Restore into an empty database only, after stopping `app`: `docker exec -i <stack>-db-1 pg_restore -U postgres -d traveleasy --clean --if-exists < file.dump`,
  then start `app`; `db-init` re-applies the app role on the next deploy.
- Keep dumps off the server too. A restore test is part of the `backup` story.

## Removing the stack

In Portainer, **Remove** the stack and leave **Remove associated volumes** **off**. On the command line never use
`docker compose down -v`: it deletes `pgdata` and `storage` (all data).

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| Deploy error `required variable X is missing a value` | fill `X` in the stack's environment variables |
| `network npm_shared declared as external, but could not be found` | the NPM network has another name or is missing; see "Before the first deploy" |
| `db-init` exited with an error | `DB_SUPERUSER_PASSWORD` differs from the one the database was created with; use the original value (it is only applied on first creation) |
| `app` restarts with `Refusing to start: ...` | the message names the variable (`APP_KEY`, `TRUSTED_PROXIES` (a range or `*`), `APP_DEBUG`) |
| `app` healthy but NPM shows 502 | proxy host must target `traveleasy-app:8080` (http), and NPM must be on `npm_shared` |
| Login loops or "page expired" over http | `SESSION_SECURE_COOKIE=true` needs https end to end; see the NPM section |
| `ssr` unhealthy | the site still works (client-side rendering); read the `ssr` logs, then restart only that service |
| Wrong client addresses in logs | `TRUSTED_PROXIES` is not NPM's current IP (was NPM recreated without a fixed IP?) |

## Verifying the stack without Portainer

`scripts/ci/verify-production-stack.sh <image:tag>` brings the production file up with throwaway secrets on a throwaway
network (never `npm_shared`), runs about 30 checks (health, hardening, no ports, DB role limits, no secrets in logs, no
egress, server-side rendering, cookie flags, devtools 404, no client IP in logs, SSR-down fallback, missing-secret errors)
and removes everything it created. Build the image first: `docker build --target production -t traveleasy:local .`.

## Maintenance mode

To take the site offline briefly, run `docker exec <stack>-app-1 php artisan down` and later `... php artisan up`. Use
`down` **without** `--render`: the app renders its own localised 503 page through the error handler. A static page
(`--render`) would bypass the security headers and the translations. If PHP itself is unreachable (502/504), the web server
answers with a small static Dutch/English page that carries the same security headers.

## Releases and tags (TE-014)

CI (`.github/workflows/ci.yml`, job `release`) builds the `production` target for `linux/amd64`, runs the verify scripts and a
Trivy scan, and only then pushes to `ghcr.io/nimrodlobozar/traveleasy`. The package is public (the repository is public), so
Portainer needs no registry login. There is never a `latest` tag.

| Event | Tags pushed |
|---|---|
| Push to `main` (a `dev` release merge; all quality gates green) | `sha-<7 chars>` |
| Push of a git tag `vX.Y.Z` | `sha-<7 chars>` and `vX.Y.Z` |
| Push to `dev`, or a pull request into `dev`/`main` (also from a fork) | nothing (build, verify and scan only) |

Cut a release (merge `dev` into `main` through a PR, wait until `main` is green, then tag `main`): `git tag v0.1.0 && git push origin v0.1.0`. Milestone N gives `v0.N.0`; the first
release is `v0.1.0`. A tag that is not `vX.Y.Z` makes the run red and publishes nothing.

Pull and inspect: `docker pull ghcr.io/nimrodlobozar/traveleasy:v0.1.0` and
`docker buildx imagetools inspect ghcr.io/nimrodlobozar/traveleasy:v0.1.0` (one `linux/amd64` manifest, OCI labels
revision and source, SBOM and provenance attestations). In Portainer you only set `APP_IMAGE_TAG`. Roll back by setting it
to the previous tag (see "Roll back").

Trivy fails the release on any High or Critical finding (OS, libraries, secrets, misconfiguration) unless it is listed in
[`.trivyignore.yaml`](../../.trivyignore.yaml) (scoped per image with `purls`; used only by this scan) with an expiry date and a reviewed reason. The report is a workflow artifact for 3 days.
A weekly run (`.github/workflows/scan-weekly.yml`, Mondays) scans the dev and Playwright images; a red run means new findings.
