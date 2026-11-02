---
id: TE-024
title: Production compose.yaml for Portainer (pinned tag, health checks, limits, ssr service)
status: ready
priority: must
epic: M0 First home-server deploy
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want a production stack file that Portainer can deploy from a pinned image tag, so that updating means
bumping one tag and rolling back means setting the old one.

## Context
- `docs/plan.md` § 4 M0 (first deploy; secrets `${VAR:?}`; no default passwords; no host-exposed DB port), § 7 Services in
  production (`app`, `ssr`, `worker`, `scheduler`, `db`, `backup`), `CLAUDE.md` § Deployment target.
- Board "Notes for the production / deploy epics" (cap_drop, no-new-privileges, read-only rootfs, digest pins, DB role, TrustProxies,
  CI guards) are **moved here** and into TE-012/TE-013.
- **Depends on:** TE-011, TE-012, TE-008. **Blocks:** TE-025, TE-026.
- `compose.yaml` today is the base file used by dev together with `compose.dev.yaml` (dev overrides). The story decides, and
  documents, how production differs (e.g. `compose.yaml` as production base and dev-only parts moved into the dev override, or
  a separate `compose.prod.yaml` for Portainer); dev behaviour must not regress.
- The `worker`, `scheduler` and `backup` services arrive as their features arrive (M1 onwards); this story ships `app`, `ssr`, `db`
  and a minimal `backup` (scheduled `pg_dump` to a named volume, retention count in env) if ADR-0002 says so, otherwise
  `backup` is tracked as a follow-up and the milestone DoD notes it.

## Acceptance criteria
- [ ] AC1: Given the production file, when `docker compose --env-file deploy/portainer.env.example config --quiet` runs with the
      secrets filled in, then it validates; with any secret missing it fails with the `${VAR:?message}` text naming the variable.
- [ ] AC2: Given the services, when read, then `app` and `ssr` use the same pinned image reference
      (`ghcr.io/nimrodlobozar/traveleasy:${APP_IMAGE_TAG}`; the example file pins a real released tag, never `latest`), `db`
      uses a digest-pinned `postgres` image, and no service publishes a host port (see AC11). `ssr` runs
      `node bootstrap/ssr/ssr.js` (not artisan).
- [ ] AC3: Given every long-running service, when read, then it sets `restart: unless-stopped`, a working `healthcheck`
      (app health route, ssr HTTP check, `pg_isready`), memory limits (`ssr` 256 MB, `app` and `db` per ADR-0002),
      `cap_drop: [ALL]` (add back only what is needed, each with a comment), `security_opt: [no-new-privileges:true]`, and a
      read-only root filesystem with tmpfs/volumes for writable paths (db excepted if documented).
- [ ] AC4: Given the `app` service depends on `db` and `ssr`, when the stack starts from empty volumes, then `app` waits for `db`
      healthy and starts; if `ssr` is down the app still serves (client-side fallback) and `docker compose ps` shows `ssr`
      unhealthy.
- [ ] AC5 (ADR-0002 D5): Given the database, when the stack starts, then a one-shot `db-init` service (same digest-pinned
      `postgres` image as `db`, inline `command:` script, no bind-mounted init file) runs idempotently on every deploy: it creates
      the app role `DB_USERNAME` (default `traveleasy_app`; `LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS`)
      if missing, re-applies attributes and password (rotation = change the env var and redeploy), creates the database
      (owned by the superuser) if missing, grants `CONNECT, TEMP` and makes the role owner of the `public` schema. Identifiers and
      the password are passed as psql variables (`format('%I')` / `:'var'`), never concatenated. `app` depends on it with
      `condition: service_completed_successfully`. `DB_SUPERUSER_PASSWORD` goes only to `db` and `db-init`, never to `app`,
      `worker`, `scheduler` or `backup`. A script proves the app role cannot `CREATE ROLE` or `DROP DATABASE` but can migrate.
      The session that runs `ALTER ROLE ... PASSWORD` sets `log_min_error_statement=panic` first, and psql runs without echo
      (no `-e`, no `ECHO all`, no `-a`), so a failing statement never writes the password to the `db` logs; a test makes the
      statement fail on purpose and greps the `db` and `db-init` logs for the password (must be absent).
- [ ] AC13 (ADR-0002 D6, D8): Given `docker compose config`, when parsed, then the `default` network is `internal: true` (so
      `db`, `db-init`, `ssr` have no internet route; a test proves no egress from them); `app` is the only service on both
      `default` and `npm_network` (`name: npm_shared`, external) and carries the network alias `traveleasy-app` there, so NPM
      targets `http://traveleasy-app:8080`; `ssr` has no DB credentials.
      The `ssr` environment contains none of the five secrets (`APP_KEY`, `DB_SUPERUSER_PASSWORD`, `DB_PASSWORD`,
      `SUPER_ADMIN_PASSWORD_HASH`, `SUPER_ADMIN_TOTP_SECRET`), and no `env_file` / `x-` anchor shares them into it; a test parses the
      `docker compose config` output and fails if any of those names appears under `services.ssr.environment`.
- [ ] AC14 (ADR-0002 D8, owner: ProDesk has 8 GB or more, limits kept): Given the services, when read, then each sets `mem_limit`,
      `memswap_limit` (= `mem_limit`, no swap), `cpus` and `pids_limit` from env variables with these defaults: `app` 768 MB / 2.0,
      `ssr` 256 MB / 1.0 (V8 heap capped at 160 MB), `db` 1 GB / 2.0, `db-init` 128 MB / 0.5 (`worker` 384 MB / 1.0, `scheduler`
      384 MB / 1.0, `backup` 256 MB / 0.5 arrive with their services). `docker stats` on a running stack shows Portainer/Compose
      applied them (limit column), and idle use is below 1 GB for the whole stack.
- [ ] AC6: Given named volumes (`pgdata`, `storage`, `backups` if used), when the stack is redeployed with another tag, then the
      data persist; `docker compose down` in the docs never uses `-v`.
- [ ] AC7: Given the CI guard script from TE-013, when run against the production file, then it passes (no `adminer`, no `ports:` on
      any service, no initdb mount with credentials, no `latest` tag, no `build:` section).
- [ ] AC8: Given `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE` (true when `APP_URL` is https; documented LAN-
      http exception), `SESSION_ENCRYPT=true`, `INERTIA_DEVTOOLS_ENABLED=false`, `TRUSTED_PROXIES`, when the stack runs, then the
      devtools URL returns 404 and cookies carry the expected flags (smoke test script).
- [ ] AC9: Given Portainer, when the file is pasted into a stack's web editor (no repository checkout, no bind mounts to host
      paths), then it works; all configuration comes from the env file or named volumes.
- [ ] AC10: Given logging, when containers run, then logs go to stdout in JSON with a request ID and the Docker log driver has a
      size cap (`max-size`, `max-file`) so the home server's disk cannot fill up. Request log lines carry no client IP (the
      request-ID field only); if an IP must be logged for some line, a retention of 14 days or less is enforced (size/time cap
      documented) and a test greps a sample request log for the test client's IP.
- [ ] AC15 (migration order, reviewer Low): Given `worker` and `scheduler` (when their stories add them), when read, then they
      declare `depends_on: app: condition: service_healthy`, so they never start new code while `app` is migrating. In M0
      (only `app`, `ssr`, `db`, `db-init`) a test asserts `ssr` is the only other service and that the rule is documented in
      `docs/deploy/casaos-portainer.md` for the later services.

- [ ] AC11 (no host port; owner decision 2026-11-02): Given the production `compose.yaml`, when read and when
      `docker compose config` is parsed, then (a) no service has a `ports:` entry; (b) only the web-facing service (the one NPM
      proxies to, `app`) is attached to both `default` and `npm_network`, while `db`, `ssr`, and later `worker`/`scheduler`/`backup`
      stay on `default` only; (c) the file ends, at the same level as `services:`, with
      `networks:` / `npm_network:` / `name: npm_shared` / `external: true`; (d) the DB is never exposed. A CI guard (TE-013 script)
      fails on any `ports:` in the production file and on any service other than the web-facing one joining `npm_network`.
- [ ] AC12 (missing network): Given the external network `npm_shared` does not exist on the host, when the stack is deployed, then
      Docker fails with a clear "network npm_shared declared as external, but could not be found" message, and
      `docs/deploy/casaos-portainer.md` states that `npm_shared` must already exist (created by the owner's NPM setup) and how to
      check it (`docker network ls`). In CI the guard test creates a throwaway `npm_shared` network, project-scoped cleanup only.
- [ ] AC16 (follow-up TE-007): Given the production file, when read, then `app` sets `INERTIA_SSR_URL=http://ssr:13714`,
      `INERTIA_SSR_HOST=0.0.0.0` is set only on `ssr`, and `ssr` has no `ports:` and is not on `npm_shared`, because the SSR
      `/shutdown` endpoint has no authentication.

## Quality criteria
- [ ] Tests: a CI job brings the production file up with throwaway secrets, waits for healthy, runs the smoke script (SSR HTML,
      devtools 404, headers from TE-015, DB port not published, app role limits) and tears down with project-scoped commands only.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: security-privacy-reviewer checks hardening flags, secret handling (no secrets in the file, none in the
      image), network exposure (`docker compose ps`, `ss` inside the host) and the DB role split.
- [ ] i18n: n/a.
- [ ] Performance: idle RAM of the stack recorded (target under 1 GB total on the ProDesk).
- [ ] Docs updated: `docs/deploy/casaos-portainer.md` (service table), `.env.example` / Portainer env file entries for each
      variable, ADR-0002 follow-ups, `CLAUDE.md` Deployment target if the file layout changes.

## Out of scope
- Worker, scheduler and demo-reset services for features that do not exist yet (M1+); NPM proxy host set-up (TE-026 doc), apart from the compose network wiring in AC11.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
