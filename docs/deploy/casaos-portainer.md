# Deploying on CasaOS with Portainer

Production runs as one Portainer stack made from [`compose.yaml`](../../compose.yaml) and a set of environment variables
([`deploy/portainer.env.example`](../../deploy/portainer.env.example)). The image comes from GHCR (pinned tag, built by CI).
Public access goes through the existing Nginx Proxy Manager (NPM); the stack publishes **no host port**.

Status: v1 of this guide (TE-040). The stack, the env file and the secrets helper are tested (TE-024, TE-025); the commands in
this guide were proven on a throwaway stack in TE-041 (NPM 2.16.0); the real first deploy is TE-034 (owner-gated). Until the first release
tag is deployed, the tag in the env file is a placeholder.

**Who does what.** You (the owner) do every server, Portainer and NPM step yourself. Agents never access the server,
Portainer or NPM. Where this guide says "evidence", copy the command output (never a secret) back to the orchestrator; TE-034
uses it to close the checks.

**Warnings, read once.**
- Never run `docker compose down -v`, `docker volume prune`, `docker system prune` or `docker image prune -a` on the server.
  `-v` and the prunes delete `pgdata` (the whole database) and `storage`, and other stacks' volumes and images.
- In Portainer never tick **Remove associated volumes** when removing the stack.
- Never use the tag `latest`, and never a `ci-*` tag (see "Releases and tags").
- Never paste secrets into chat, tickets or git. Never put the database on a host port.

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

0. **Prerequisites.** Portainer runs (CasaOS app store) and you can sign in; Nginx Proxy Manager (NPM) runs and you can sign in;
   the CasaOS terminal (or SSH) works. GHCR pull access: the package `ghcr.io/nimrodlobozar/traveleasy` is public, so no
   registry login is needed. Prove it in the terminal: `docker pull ghcr.io/nimrodlobozar/traveleasy:<tag>`. Expect
   `Status: Downloaded newer image ...` (or `Image is up to date`). If you get `denied`, the package is private: in Portainer
   add GHCR under **Registries** -> **Add registry** -> **Custom registry** (`ghcr.io`, your GitHub user, a token with only
   `read:packages`), and tell the orchestrator.
1. **The network `npm_shared` must exist** before you deploy. It is created by your NPM stack. Check in the terminal:
   `docker network ls | grep npm_shared`. Expect one line. If nothing is printed, do not continue: fix NPM's stack first (it must
   declare a network named `npm_shared`). Without it Docker stops with
   `network npm_shared declared as external, but could not be found`.
2. **Give NPM a fixed IP on `npm_shared`**, then read it. `TRUSTED_PROXIES` is that **single IP**.
   Why not the whole network range: the range contains Docker's gateway and every neighbouring container. Anything in it could
   then send `X-Forwarded-For` and be believed, so a neighbour could fake any client address (and dodge rate limits). Never
   `*`, never empty, never the CIDR; the app refuses to start in production with `*` or a range.

   a. Read the range: `docker network inspect npm_shared --format '{{(index .IPAM.Config 0).Subnet}}'`. Expect something like
      `172.20.0.0/16`. If it prints nothing or an error, the network has no explicit subnet; add one to NPM's stack
      (`networks: npm_shared: ipam: config: - subnet: 172.20.0.0/16`) and redeploy NPM.
   b. Pick one free address inside it, away from the start (the gateway is `.1`), for example `172.20.0.2`. List used ones:
      `docker network inspect npm_shared --format '{{range .Containers}}{{.Name}} {{.IPv4Address}} {{end}}'`.
   c. Portainer -> **Stacks** -> NPM's stack -> **Editor**. Under NPM's service, set
      `networks: { npm_shared: { ipv4_address: 172.20.0.2 } }` (use your address; keep any other networks NPM already has). Click
      **Update the stack**. Expect NPM to restart and come back.
   d. Read the result: `docker inspect <npm container name> --format '{{(index .NetworkSettings.Networks "npm_shared").IPAddress}}'`.
      Expect exactly your address. This is your `TRUSTED_PROXIES` value. Write it down.
3. **Generate the secrets** with the helper in the published image. It needs only Docker (no PHP, no database, no network),
   writes nothing to disk and prints ready-to-paste lines. Run it in the CasaOS terminal or on your PC:

   ```
   docker run --rm -it --log-driver none ghcr.io/nimrodlobozar/traveleasy:<tag> php artisan traveleasy:generate-secrets --without-super-admin
   ```

   - `--log-driver none` keeps Docker from storing the printed secrets in the container log.
   - `-it` is needed for the super_admin password prompt only (passwords are never read from arguments): without a terminal that prompt refuses. With `--without-super-admin` the secrets print either way, but keep `-it`.
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
   from your PC (download it from the repository first; it holds no secrets) (or switch to the advanced mode and paste its lines). The file already holds working defaults for everything
   else. Then edit only these values (they are marked `EDIT` or `SECRET` in the file):
   - `APP_IMAGE_TAG`: the released tag you deploy, for example `v0.1.0` (never `latest`).
   - `APP_URL`: the public https address NPM serves, for example `https://traveleasy.example.com`.
   - `TRUSTED_PROXIES`: NPM's fixed IP from step 2 above, one address and nothing else.
   - `SESSION_SECURE_COOKIE`: leave `true`, except for the LAN-over-HTTP exception in "Nginx Proxy Manager host".
   - `DB_SUPERUSER_PASSWORD`, `DB_PASSWORD`, `APP_KEY`: paste the three lines from step 3 of "Before the first deploy".
4. Click **Deploy the stack**. Expect the stack to appear in the list. Portainer reports an error naming the variable if a required one is empty.
5. Open **Containers**. Within about 2 minutes `db`, `ssr` and `app` are `healthy` and `db-init` is `exited (0)`.
   `app` needs the longest (migrations on the first start).
6. Check that no port is published: in the terminal `docker ps --filter name=traveleasy --format '{{.Names}}  {{.Ports}}'`.
   Expect `app` and `ssr` to list `80/tcp, 443/tcp, 2019/tcp, 8080/tcp, 443/udp` (the image's EXPOSE list; normal) and `db` to list
   `5432/tcp`. The check is that **no entry contains `->`** (no `0.0.0.0:` or `:::`). Any `->` entry is a mistake: stop and report it.
   Always deploy through the Portainer stack (or pass `-p <stack name>` on the command line); a bare
   `docker compose -f compose.yaml up` would run under the project name written in the file.
7. Add the proxy host in NPM (next section). Then run "Verify the deploy".

If `app` keeps restarting, open its **Logs**: the entrypoint says why (`Refusing to start: ...` names the wrong variable,
never its value).

## Nginx Proxy Manager host

Do the access list first, so the site is never open to everyone, even for a minute.

**A. LAN-only access list** (NPM -> **Access Lists** -> **Add Access List**)
1. Name: `lan-only`. Leave **Satisfy Any** off and **Pass Auth to Upstream** off.
2. Open the **Rules** tab: add **Allow** with your LAN range (for example `192.168.1.0/24`; use your router's range), then
   **Deny** `all`. Order matters: Allow first, Deny all last.
3. **Save**. Expect `lan-only` in the list.

**B. Proxy host** (NPM -> **Hosts** -> **Proxy Hosts** -> **Add Proxy Host**)

| Field | Value |
|---|---|
| Domain Names | your domain (the host part of `APP_URL`) |
| Scheme / Forward Hostname / Port | `http` / `traveleasy-app` / `8080` |
| Cache Assets | off |
| Block Common Exploits | on |
| Websockets Support | off (not needed) |
| Access List | `lan-only` |
| SSL tab | see C below |

Use the alias `traveleasy-app`, not `app`: other stacks on `npm_shared` may also have a service called `app`.

Then open the **Advanced** tab and put this single line in **Custom Nginx Configuration**:

```
set_real_ip_from 127.0.0.1/32;
```

Why: by default NPM believes an `X-Real-IP` header sent by any private or CDN address, so a LAN device could pretend to be
another IP (slipping past per-IP login throttles and, later, the `lan-only` list). This line replaces that list for this host
with loopback only, so NPM's `[Client ...]` address is always the real peer. Check 5d proves it works.
Click **Save**. Expect the host with status **Online**.

**C. HTTPS and HSTS.** Once a certificate exists (M0 may have none yet: see D), open the **SSL** tab, choose the certificate,
switch **Force SSL** on and **HTTP/2 Support** on, and leave **HSTS Enabled** **off**. The app sends HSTS itself (only on https,
with a safe max-age); two sources would conflict.

**D. LAN over HTTP (M0 exception).** With no certificate yet, the site can be tested on the LAN over plain http only:
1. In the stack's environment variables set `APP_URL` to the `http://` address and `SESSION_SECURE_COOKIE` to `false`, then
   redeploy (Stacks -> `traveleasy` -> Editor -> **Update the stack**). Cookies are normally https-only; with `true` over http you
   cannot log in (login loops, "page expired").
2. Keep the access list on. Never use this setup beyond the LAN.
3. When HTTPS works: put `APP_URL` back to the `https://` address and `SESSION_SECURE_COOKIE` to `true`, redeploy, then do C.

**Router.** Do not add a port forward to NPM or the server for this site. Public exposure is a later milestone (M6).

## Verify the deploy

Evidence to paste back to the orchestrator for TE-034: the outputs of steps 1, 2, 4 and 5 (no secrets in any of them). For
step 5 paste **only** the `[Client ...]` fragments that the commands below print and the two lines of 5b; never paste whole NPM
log lines (they hold more than the IP).

1. **Health.** `docker ps --filter name=traveleasy --format '{{.Names}}  {{.Status}}'` (or Portainer -> Containers). Expect `db`,
   `ssr`, `app` `Up ... (healthy)` and `db-init` `Exited (0)`.
2. **No published port.** `docker ps --filter name=traveleasy --format '{{.Names}}  {{.Ports}}'`: no `0.0.0.0:` or `:::` anywhere.
   `docker port traveleasy-db-1` prints nothing.
3. **Open the site** from a LAN device at the `APP_URL`. Expect the TravelEasy home page (Dutch by default).
4. **LAN-only, from outside.** Switch a phone to mobile data (Wi-Fi off) and open the same address. Expect NPM's
   `403 Forbidden`, or no connection at all (the router has no port forward). Any TravelEasy page here is a failure: stop and report.
5. **Real client IPs (trust check).** The app writes no request log and no client IP (TE-024 AC10, by design), so the proof has
   two parts. Replace `<npm-ip>` with your `TRUSTED_PROXIES` value; the app container is `traveleasy-app-1`.
   a. *A LAN device is seen as itself.* Open the site from a LAN device (for example `192.168.1.50`), then on the server print the
      client of the last request (replace `<npm>` with NPM's container name and `<id>` with the number from
      `docker exec <npm> ls /data/logs`):
      `docker exec <npm> tail -n 1 /data/logs/proxy-host-<id>_access.log | grep -o '\[Client [^]]*\]'`.
      Expect `[Client 192.168.1.50]` (your device's LAN IP). Verified in TE-041 (NPM 2.16.0): the line looks like `[Client 10.0.0.5]`.
   b. *The trusted proxy is believed, anyone else is ignored.* This boots the app and asks which address it assigns to a request
      carrying `X-Forwarded-For: 203.0.113.9`, once from NPM and once from a neighbour:

      ```
      docker exec traveleasy-app-1 php -r '
      require "/var/www/html/vendor/autoload.php";
      $app = require "/var/www/html/bootstrap/app.php";
      $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      foreach ([$argv[1], "<a-neighbour-ip>"] as $peer) {
          $r = Illuminate\Http\Request::create("/", "GET", [], [], [], ["REMOTE_ADDR" => $peer, "HTTP_X_FORWARDED_FOR" => "203.0.113.9"]);
          $app->make(App\Http\Middleware\TrustProxies::class)->handle($r, fn ($r) => print($peer . " -> " . $r->ip() . PHP_EOL));
      }' <npm-ip>
      ```

      Expect two lines: `<npm-ip> -> 203.0.113.9` (the trusted proxy is believed) and `<a-neighbour-ip> -> <a-neighbour-ip>` (a neighbour
      or the gateway is **not** believed). If the second line shows `203.0.113.9`, `TRUSTED_PROXIES` is too wide: fix it at once.
   c. *Through NPM a forged `X-Forwarded-For` does not take over.* From a LAN device:
      `curl -sk -H "X-Forwarded-For: 203.0.113.9" https://<your domain>/ -o /dev/null -w '%{http_code}\n'` (`http://` in the
      LAN-over-HTTP setup). Expect `200`. Then on the server run the 5a `tail ... | grep -o` command again: expect
      `[Client <your LAN IP>]`, never `203.0.113.9`.
   d. *Through NPM a forged `X-Real-IP` does not take over.* NPM trusts this header from private and CDN addresses unless the
      proxy host has `set_real_ip_from 127.0.0.1/32;` (section B), so this check proves that line works. From a LAN device:
      `curl -sk -H "X-Real-IP: 203.0.113.9" https://<your domain>/ -o /dev/null -w '%{http_code}\n'` (`http://` in the LAN-over-HTTP
      setup). Expect `200`; then the 5a `tail ... | grep -o` command prints `[Client <your LAN IP>]`. If it prints
      `[Client 203.0.113.9]`, the line is missing or in the wrong place: fix it in the Advanced tab, **Save**, and repeat.
      Repeat once with `-H "X-Real-IP: 127.0.0.1"`: expect the same result (your LAN IP, and the `lan-only` list still applies).
   The variant with a neighbouring container as sender was run in the TE-041 dry run (NPM 2.16.0): the neighbour is not believed.

## Logs and privacy

- The app's own logs (Portainer -> Containers -> `app` -> **Logs**) are JSON on stderr, level `warning`, with **no client IP and
  no request line** (the web server's access log is off on purpose). Docker caps them at 3 files of 10 MB.
- **NPM keeps access and error logs with client IPs.** Location: NPM's data volume, `/data/logs/proxy-host-<id>_access.log` and
  `proxy-host-<id>_error.log`; list them with `docker exec <npm> ls /data/logs`. NPM's own rule (weekly, 4 to 10 rotations) keeps
  more than the 14 days we allow, and a change made inside the container is lost when NPM is recreated. So we keep NPM's own
  rule **exactly as it is**, change only how often and how many times it rotates, and mount that copy from a file
  that lives **outside** the container. Pin NPM's image to the tag these steps were verified on, `jc21/nginx-proxy-manager:2.16.0`
  (not `latest`), and redo step 8 whenever you change it. The IPs stay on your server and are never sent anywhere.

**Set the 14-day log retention (once; again only if NPM's stack is rebuilt from scratch)**

1. Do this before the mount in step 4 exists, so the running container still holds NPM's upstream file. Show it:
   `docker exec <npm> cat /etc/logrotate.d/nginx-proxy-manager`. Expect NPM's blocks with `su npm npm`, `create 0644`, `weekly`,
   `rotate <n>`, the `/data/logs/*_access.log`, `/data/logs/*/access.log`, `*_error.log` and `*/error.log` patterns, and any
   other log such as `backend.log`. Do not retype or shorten it; step 3 copies it whole.
2. On the server, make a folder that is not inside NPM's data volume (CasaOS example):
   `sudo mkdir -p /DATA/AppData/npm-extra && cd /DATA/AppData/npm-extra`.
3. Copy the upstream file, then apply only these edits: every `weekly` becomes `daily`, every `rotate <n>` becomes `rotate 13` followed by a new line `maxage 14` (in every block,
   `backend.log` too), and ` || true` is removed from the end of the `postrotate` command, so a failing reload shows as an error
   instead of being hidden. Everything else stays byte for byte:

   ```
   docker exec <npm> cat /etc/logrotate.d/nginx-proxy-manager > ~/npm-logrotate.upstream
   sudo cp ~/npm-logrotate.upstream logrotate-nginx-proxy-manager
   sudo sed -i -E \
     -e 's/^([[:space:]]*)weekly[[:space:]]*$/\1daily/' \
     -e 's/^([[:space:]]*)rotate [0-9]+[[:space:]]*$/\1rotate 13\n\1maxage 14/' \
     -e 's/[[:space:]]*\|\|[[:space:]]*true[[:space:]]*$//' \
     logrotate-nginx-proxy-manager
   sudo chown root:root logrotate-nginx-proxy-manager && sudo chmod 644 logrotate-nginx-proxy-manager
   diff ~/npm-logrotate.upstream logrotate-nginx-proxy-manager
   ```

   The `diff` must show only `weekly` -> `daily`, `rotate <n>` -> `rotate 13` plus an added `maxage 14` line, and the `postrotate` line(s) losing
   ` || true`. If it shows any other change, or no `weekly` line was changed (the upstream file differs from what this guide
   expects), stop and do not mount the file. The `postrotate` line reads the nginx pid from `/run/nginx/nginx.pid`; that this
   path is right: verified in TE-041 (NPM 2.16.0).

   Why `maxage 14`: NPM has no cron; its backend runs logrotate once when it starts and then **every 2 days**, so `daily` and
   `rotate 13` alone would keep about 26 days. `maxage 14` deletes every rotated log older than 14 days at each run, so
   the target retention is 14 days and the real maximum is about 18 days (a line can sit up to 2 days in the live log, plus up to 2 days past `maxage 14` until the next run). The file must be owned by root and not writable by others
   (mode 0644), or logrotate refuses to read it.
4. In Portainer open **NPM's own stack** (Stacks -> the NPM stack -> **Editor**) and add one line under the NPM service's
   `volumes:`, keeping the existing ones:
   `- /DATA/AppData/npm-extra/logrotate-nginx-proxy-manager:/etc/logrotate.d/nginx-proxy-manager`
   Mount it read-write (no `:ro`): NPM runs `chmod 644` on this file at every start, and on a read-only mount that fails and NPM
   does not start. The host file is already root:root 0644, so the chmod changes nothing.
   (If NPM was installed as a CasaOS app instead, add the same path in the app's settings under **Volumes**, also without read-only.)
5. Click **Update the stack**. This recreates NPM and takes every site behind it offline for a few seconds. The fixed IP from
   "Before the first deploy" must still be in the stack, otherwise `TRUSTED_PROXIES` no longer matches.
6. Verify with a dry run (it changes nothing): `docker exec <npm> logrotate -d /etc/logrotate.d/nginx-proxy-manager`. Expect
   two `rotating pattern: ... after 1 days (13 rotations)` lines (the access and error blocks), one size-based line for
   `/data/logs/backend.log` (`10485760 bytes ... (13 rotations)`), and no `error:` line. Then
   `docker exec <npm> grep -nE 'weekly|rotate [0-9]|maxage|\|\| true' /etc/logrotate.d/nginx-proxy-manager`; expect only
   `rotate 13` and `maxage 14` lines (no `weekly`, no other number, no `|| true`).
7. Delete files already older than the limit. First list them:
   `docker exec <npm> find /data/logs -name '*.log*' -mtime +14 -print`. If the list shows only old log files, run the same
   command with `-delete` instead of `-print`.
8. Note the date. After every NPM image update, repeat step 6, and compare your file with the new image's upstream rule
   (`docker run --rm --entrypoint cat <npm image>:<new tag> /etc/logrotate.d/nginx-proxy-manager`). If the upstream rule
   changed beyond `weekly`, `rotate` and `|| true`, redo steps 3, 5 and 6 with that new upstream file as the base. Because the
   running container now shows your mounted copy, the first line of step 3 then becomes
   `docker run --rm --entrypoint cat <npm image>:<new tag> /etc/logrotate.d/nginx-proxy-manager > ~/npm-logrotate.upstream`
   (delete the old `~/npm-logrotate.upstream` first).

Verified in TE-041 on NPM 2.16.0: NPM starts with the read-write mount, accepts this file (a group- or world-writable file is
ignored with a warning), a forced run rotates, and the `postrotate` reload (pid path `/run/nginx/nginx.pid`, without
`|| true`) works: nginx writes to the new log afterwards.

## Optional: NPM custom error page (502/504)

The web server cannot show its own static 502/504 page when PHP is down (FrankenPHP's `php_server` cannot produce it), and NPM
shows its own plain "502 Bad Gateway" when `app` is stopped or restarting. **Decision for M0: do not customise it** (the page is
only seen during updates, on a LAN). If you want a friendlier page: NPM -> your proxy host -> **Edit** -> **Advanced** tab ->
**Custom Nginx Configuration**, paste (no personal data in it):

```
error_page 502 504 /te-down.html;
location = /te-down.html {
    internal;
    default_type text/html;
    return 502 '<!doctype html><html lang="nl"><meta charset="utf-8"><title>TravelEasy</title><p>TravelEasy is zo weer terug. Back in a moment.</p></html>';
}
```

Click **Save**. Expect the host to stay Online. Test: stop `app` in Portainer, reload the page (the message appears), start `app`
again. To undo, empty the field and save.

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

0. If the release deletes or rewrites data (its notes say so), make a manual backup first ("Backup and restore"). Note the
   current `APP_IMAGE_TAG` value: it is your rollback target.
1. Portainer -> **Stacks** -> `traveleasy` -> **Editor**.
2. In **Environment variables** change `APP_IMAGE_TAG` to the new tag.
3. Click **Update the stack** with **Re-pull image and redeploy** on.
4. Watch `app` Logs for `migrating`, then wait until it is `healthy`. The data in `pgdata` and `storage` stays.
5. Run "Verify the deploy" steps 1 and 3.

Migrations are backward compatible with the previous release (expand/contract, ADR-0002 D4). A release that deletes data
needs a manual backup first (see below).

## Roll back

Set `APP_IMAGE_TAG` to the previous tag (the one you noted) and update the stack the same way (Editor -> change the value ->
**Update the stack** with **Re-pull image and redeploy** on). Expect `app` `healthy` again and the site up. Because migrations are expand/contract, the
previous release runs on the newer schema. Never delete the volumes to "roll back".

## Backup and restore (manual until the `backup` service exists)

The scheduled dump service is a later story. Until then, take a dump before every update that deletes data:

- Dump (CasaOS terminal). `umask 077` makes the dump readable by you only; the database talks over the container's local
  socket, so no password is needed on the command line or in your shell history. Every dump gets its own name down to the
  second, and `set -o noclobber` makes the shell refuse to overwrite a file that already exists, so a dump can never replace
  an earlier one (invoices must be kept 7 years):

  ```
  umask 077
  mkdir -p ~/traveleasy-backups && cd ~/traveleasy-backups
  set -o noclobber
  f=traveleasy-$(date +%F-%H%M%S).dump
  docker exec traveleasy-db-1 pg_dump -U postgres -Fc traveleasy > "$f" \
    && docker exec -i traveleasy-db-1 pg_restore -l < "$f" > /dev/null \
    && echo "DUMP OK: $f" || echo "DUMP FAILED: $f is missing or incomplete, do not continue"
  ```

  Expect `DUMP OK: traveleasy-<date>-<time>.dump` (the second command reads the file back to prove it is a complete
  archive). On `DUMP FAILED`, stop and read the error above it. If the error is `cannot overwrite existing file`, nothing was
  written: wait a second and run the block again; never delete or rename that existing file.
- Restore. `--clean` **drops and overwrites** what is in the live database. Follow these steps in order:
  1. Write down the exact name of the dump you restore **from** (for example `traveleasy-2026-11-01-093000.dump`).
  2. Stop `app` in Portainer (Containers -> `app` -> **Stop**), so nothing writes to the database during the restore.
  3. Make a safety dump of the current state with this block (it uses its own `pre-restore` name):

     ```
     cd ~/traveleasy-backups
     umask 077
     set -o noclobber
     f=traveleasy-pre-restore-$(date +%F-%H%M%S).dump
     docker exec traveleasy-db-1 pg_dump -U postgres -Fc traveleasy > "$f" \
       && docker exec -i traveleasy-db-1 pg_restore -l < "$f" > /dev/null \
       && echo "DUMP OK: $f" || echo "DUMP FAILED: $f is missing or incomplete, do not continue"
     ```

     Continue only after `DUMP OK`. Keep this file until the restore is confirmed good.
  4. **Warning: never write to the file you restore from.** It is only ever read with `<`. Never put it after a `>` and never
     reuse its name for the safety dump. Check with `ls -l` that the file from step 1 and the `pre-restore` file from step 3
     are two different files, each with a non-zero size.
  5. Restore (replace `<file from step 1>`; `--single-transaction` means a failed restore changes nothing):

     ```
     docker exec -i traveleasy-db-1 pg_restore -U postgres -d traveleasy --clean --if-exists --single-transaction \
       < <file from step 1> && echo "RESTORE OK" || echo "RESTORE FAILED: database unchanged, read the error above"
     ```

  6. Only after `RESTORE OK`, start `app` and run "Verify the deploy" steps 1 and 3. On `RESTORE FAILED`, the database still
     holds the state from step 3; start `app` and read the error before trying again. `db-init` re-applies the app role on the
     next deploy.
- Restore and erasures. A dump holds the data as it was on the day it was taken. Any account deletion or data-subject
  erasure request handled **after** that date comes back with the restore, and must be erased again right after
  `RESTORE OK`. To find them:
  1. List the erasure requests received since the dump's date from your own request log (mailbox, notes). The pre-restore
     safety dump (restore step 3) still holds the newer state, so it is the place to check who was deleted since.
  2. Compare that list with the restored users; every person on it that is present again is erased again the same way as the
     first time (Settings > Profile > Delete account, or the admin erasure).
  3. Write the re-erasure and the date in your request log.
- Retention of manual dumps. A manual dump is a full copy of personal data, so it is not kept: delete an older dump as soon
  as a newer one is verified (`DUMP OK`) and the update it guarded is confirmed good, and never keep any manual dump longer
  than 30 days. Invoices are kept 7 years in the database, not in these dumps. List the dumps with their age, then delete
  the ones older than 30 days (check the list first):

  ```
  cd ~/traveleasy-backups
  ls -lt --time-style=long-iso
  find . -maxdepth 1 -name 'traveleasy-*.dump' -mtime +30 -print      # what would go
  find . -maxdepth 1 -name 'traveleasy-*.dump' -mtime +30 -delete     # delete them
  ```

  To delete one older dump by name: `rm traveleasy-<date>-<time>.dump`. Never delete the newest verified dump or a
  `pre-restore` file whose restore is not yet confirmed good. Copies kept off the server follow the same 30 days.
- Keep dumps off the server too. This procedure (dump, verify, safety dump, restore) was run once on the throwaway stack in TE-041 and works as written. A scheduled restore test is
  part of the `backup` story.

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

To take the site offline briefly, run `docker exec traveleasy-app-1 php artisan down` and later `... php artisan up`. Use
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
revision and source; SBOM and provenance attestations only on `v0.1.0`, later releases have none until the signed-attestations follow-up, see `docs/deploy/production-image.md`). In Portainer you only set `APP_IMAGE_TAG`. Roll back by setting it
to the previous tag (see "Roll back").

Trivy fails the release on any High or Critical finding (OS, libraries, secrets, misconfiguration) unless it is listed in
[`.trivyignore.yaml`](../../.trivyignore.yaml) (scoped per image with `purls`; used by this production scan and by the weekly scan of the dev image, which has the same Debian base; the Playwright image scan does not use it) with an expiry date and a reviewed reason. The report is a workflow artifact for 3 days.
A weekly run (`.github/workflows/scan-weekly.yml`, Mondays) scans the dev and Playwright images; a red run means new findings.
