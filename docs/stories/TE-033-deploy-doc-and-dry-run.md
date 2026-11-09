---
id: TE-033
title: Home-server deploy doc and dry run on a throwaway stack
status: ready
priority: must
epic: M0 First home-server deploy (split from TE-026; order: TE-033, then TE-034)
agents: [devops-engineer, qa-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want a step-by-step deploy doc that has been proven on a throwaway stack, so that the first deploy on my CasaOS
server needs no guessing.

## Context
- Split from TE-026 (2026-11-09, size limits). Criteria AC1-4, AC9-12 and AC14 moved here word for word; AC5-8, AC13 and AC15 are in
  TE-034 (owner-gated deploy).
- **Size warning:** this story has 9 AC, one over the limit of 8, because the owner fixed the split. The owner decides whether to cut
  it (suggested cut: move AC12 (NPM log retention note) and AC14 (optional NPM error page) into a small doc-only follow-up, or merge
  AC9 into AC1 as one "doc is exact" criterion; agent may not do so without the owner).
- `docs/plan.md` § 4 M0 exit criteria, § 2 DoD row 3. `CLAUDE.md` Deployment target. ADR-0002 D6.
- **OWNER-GATED parts:** none here; this story touches no real server. Agents never access the server, Portainer or NPM.
- **Depends on:** TE-014 (published image, done), TE-024, TE-025 (done). Does not need TE-031: the deploy uses an image that already
  exists; TE-031 is recommended before the owner deploys (TE-034) but not required for this dry run.
- Doc: `docs/deploy/casaos-portainer.md` (v1, written/finished in this story).

## Acceptance criteria
- [ ] AC1: Given `docs/deploy/casaos-portainer.md`, when read, then it walks through, in numbered steps with the exact commands:
      prerequisites (Portainer, NPM, GHCR pull access), generate secrets (TE-025), create a stack (web editor), load the env
      file, paste the secrets, deploy, verify, update (bump tag), roll back (previous tag), and the "never use `-v`/prune" warnings.
- [ ] AC2: Given a dry run by the qa-engineer on a throwaway Docker host or project-scoped stack with only the doc, the env file and
      generated secrets, when the steps are followed literally, then the stack comes up healthy without extra steps; every
      deviation found is written back into the doc before the story can pass.
- [ ] AC3: Given the stack with one secret left out, when deployed, then Portainer shows the `${VAR:?}` message naming the missing
      secret and nothing starts.
- [ ] AC4: Given LAN-only access with no host port, when the NPM proxy host is configured as documented (target = the web
      container's name/internal address on the shared `npm_shared` network, with a LAN-only access list), then the app opens from a
      LAN device over the NPM URL and is not reachable from outside the LAN (the doc has a check: access list in NPM, router has no
      port forward; the owner confirms with a phone on mobile data). `docker ps` shows no published port on any TravelEasy
      container, and the doc says the `npm_shared` network must exist beforehand.
- [ ] AC9 (owner does the server steps): Given the doc, when read, then every Portainer and NPM step (create the proxy host, the
      access list, the stack, the env file, the redeploy) is written as an exact click-by-click/command instruction for the
      owner, with the expected result after each step; agents do not access the server, Portainer or NPM, and the evidence for
      AC5 to AC7 is pasted back by the owner. (Note: AC5 to AC7 now live in TE-034.)
- [ ] AC10 (ADR-0002 D6, accepted 2026-11-02): Given the doc, when read, then it tells the owner to (a) read the CIDR of `npm_shared`
      with `docker network inspect npm_shared` and pick the fixed NPM IP inside it and put only that IP in `TRUSTED_PROXIES` (never `*`, never empty, never the whole CIDR; see AC11), (b) create the NPM proxy
      host to `http://traveleasy-app:8080` with a LAN-only access list, (c) turn "Force SSL" on once HTTPS exists and leave NPM's
      HSTS off (the app sets HSTS), (d) make a manual backup before any data-destroying migration, and (e) the LAN-over-HTTP
      `SESSION_SECURE_COOKIE` exception for M0.
      Note (TE-012 review, 2026-11-02): NPM's HSTS stays off because the app sets HSTS. The reviewer also suggested setting
      `TRUSTED_PROXIES` to the `npm_shared` CIDR; that was rejected, because the accepted decision is a single fixed NPM IP (AC11).
- [ ] AC11 (proxy trust, reviewer Medium 2026-11-02): Given NPM trusts every private range and passes on the client's
      `X-Forwarded-For`, and the app must not trust the whole `npm_shared` CIDR (that includes its gateway and neighbouring
      containers), when the doc is followed, then (a) NPM has a fixed IP on `npm_shared`, (b) `TRUSTED_PROXIES` contains only that
      IP (AC10a is amended accordingly: not the CIDR), and (c) a check proves real source IPs arrive: a request from a LAN device
      shows its LAN IP in the app's request log, and a request with a spoofed `X-Forwarded-For: 203.0.113.9` sent through NPM
      shows the real client IP or NPM's rewrite, never the spoofed one; the same spoofed header sent from a neighbouring
      container or via the Docker gateway is ignored. Evidence is pasted by the owner.
- [ ] AC12 (privacy, logs): Given NPM keeps access logs with client IPs, when the doc is read, then it states NPM's log location
      and retention (14 days or less, or how to set it), and that the app's own request logs carry no client IP (TE-024 AC10).
- [ ] AC14 (carried over from TE-019 AC9, optional owner step): Given the web server's static 502/504 page cannot be produced by
      FrankenPHP's `php_server`, and NPM serves its own 502 when the `app` container is down, when the doc is read, then it records the
      decision whether to configure an NPM custom error page (owner step, optional) and, if yes, gives the exact steps.

## Quality criteria
- [ ] Tests: the dry run (AC2, AC3) is run by the qa-engineer on a project-scoped throwaway stack; the production smoke script from
      TE-024 runs against it (read-only checks).
- [ ] Accessibility: n/a (documentation only).
- [ ] Security/privacy: no secrets in the stack text in Portainer screenshots or docs; reviewer confirms the doc keeps LAN-only
      exposure, DB not published, `TRUSTED_PROXIES` single IP, and that the Portainer env file in git has no real values.
- [ ] i18n: n/a beyond the shell.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `CLAUDE.md` Decisions if the dry run changes one.

## Files to touch
- `docs/deploy/casaos-portainer.md`: write/finish v1 (exists already; extend).
- `docs/deploy/production-image.md`, `docs/deploy/security-headers.md`: link only, if needed.
- `compose.yaml`, the Portainer env file (from TE-025): read-only reference; change only if the dry run finds a deviation.
- `scripts/ci/verify-production-stack.sh`: reuse for the dry run (read-only checks).
- `app/Support/Security/TrustedProxies.php`, `app/Http/Middleware/TrustProxies.php`, `tests/Feature/Security/TrustProxiesTest.php`: reference for AC10/AC11 wording (no change expected).
- `docs/decisions.md`: only if a decision changes.

## Out of scope
- Public internet exposure, domain name and TLS certificate for the public (M6); demo mode and super_admin (M1); automated deploys.
- The real deploy on the home server and its post-deploy checks (TE-034).

## Open questions
- ANSWERED (2026-11-09, owner): keep all 9 AC in TE-033; do not split off AC12 or AC14.

## Rework log
(empty)

## Changelog
(filled when done)
