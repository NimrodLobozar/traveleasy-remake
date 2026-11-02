# Deploying on CasaOS with Portainer

Production runs as one Portainer stack made from [`compose.yaml`](../../compose.yaml) and a set of environment variables
([`deploy/portainer.env.example`](../../deploy/portainer.env.example)). The image comes from GHCR (pinned tag, built by CI).
Public access goes through the existing Nginx Proxy Manager (NPM); the stack publishes **no host port**.

Status: the file layout and the stack are finished and tested (TE-024). The secrets helper and the final env file are
TE-025; the first real deploy is TE-026 (owner-gated); the image build and publish is TE-014. Until then the tag in the env
file is a placeholder and the interim secret commands below apply.

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
3. **Generate the secrets** (interim commands; TE-025 replaces them with one helper). Run them anywhere with OpenSSL, for
   example in Git Bash, and keep the output for the next step only:
   - `DB_SUPERUSER_PASSWORD`: `openssl rand -hex 24`
   - `DB_PASSWORD`: `openssl rand -hex 24` (a different value)
   - `APP_KEY`: `echo "base64:$(openssl rand -base64 32)"`

## First deploy (Portainer UI)

1. Portainer -> **Stacks** -> **Add stack**. Name: `traveleasy` (the volumes are then named after the stack).
2. Build method **Web editor**. Paste the full content of `compose.yaml`.
3. Under **Environment variables** choose **Load variables from .env file** and pick `deploy/portainer.env.example`
   from your PC (or switch to the advanced mode and paste its lines). Then edit these values:
   - `APP_IMAGE_TAG`: the released tag you deploy, for example `v0.1.0` (never `latest`).
   - `APP_URL`: the public https address NPM serves, for example `https://traveleasy.example.com`.
   - `TRUSTED_PROXIES`: NPM's fixed IP from step 2 above.
   - `DB_SUPERUSER_PASSWORD`, `DB_PASSWORD`, `APP_KEY`: the three secrets.
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

- Dump (CasaOS terminal): `docker exec <stack>-db-1 pg_dump -U postgres -Fc traveleasy > traveleasy-$(date +%F).dump`
  (enter the superuser password when asked is not possible here: run `docker exec -e PGPASSWORD='<DB_SUPERUSER_PASSWORD>' ...`
  and clear your shell history afterwards).
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
