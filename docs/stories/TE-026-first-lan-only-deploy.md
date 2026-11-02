---
id: TE-026
title: First LAN-only deploy to the home server (owner-gated)
status: ready
priority: must
epic: M0 First home-server deploy
agents: [devops-engineer, qa-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the server-rendered app shell running on my CasaOS server, reachable only from my home network, so that
I know the whole path from `git push` to a running stack works.

## Context
- `docs/plan.md` § 4 M0 exit criteria (v0.0.x on the home server, healthy services, no host-exposed DB port, fresh Portainer
  stack with only the env file plus generated secrets, a missing secret fails loudly), § 2 DoD row 3 (deploying needs the owner's OK
  each time). `CLAUDE.md` Deployment target.
- **OWNER-GATED:** the actual deploy on the home server is performed by or with the owner. Agents prepare and verify everything
  else; they never change the server or Portainer without the owner's explicit go-ahead in this conversation. The first public
  internet exposure is out of scope (end of M6).
- **Depends on:** TE-014 (a published `v0.0.x` image), TE-024, TE-025, TE-015 to TE-019 (the shell), TE-020 to TE-023 (design system;
  the milestone ends with all of them, but the deploy of the shell can happen once TE-016 to TE-019 are done if the owner wants an
  earlier first deploy; decide at that time).
- Needs `docs/deploy/casaos-portainer.md` written in this story (v1, step by step).

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
- [ ] AC5: Given the owner approves in the conversation, when the deploy is done on the home server, then all services show healthy in
      Portainer, `docker ps` on the host shows no published DB port, and the page source of `/` contains server-rendered HTML
      (view-source or `curl`) in both `nl` and `en`.
- [ ] AC6: Given the deployed app, when the checks of the shell run by hand (skip link, language switch, theme without flash, a 404 page,
      response headers incl. CSP and no `X-Powered-By`, `/_inertia/devtools/entries` is 404), then all pass; evidence is listed in the
      handoff.
- [ ] AC7: Given the deployed stack, when restarted (`docker compose restart` via Portainer) and when redeployed with the same tag, then
      data survive and health returns within 60 s; when redeployed with a previous tag, the app starts (rollback proven once).
- [ ] AC8: Given the first deploy is done, when the story closes, then `CLAUDE.md` Status and the board show the M0 deploy exit
      criterion as met, and the version in use (`v0.0.x`) and the date are recorded in `docs/deploy/casaos-portainer.md`.

- [ ] AC10 (ADR-0002 D6, accepted 2026-11-02): Given the doc, when read, then it tells the owner to (a) read the CIDR of `npm_shared`
      with `docker network inspect npm_shared` and pick the fixed NPM IP inside it and put only that IP in `TRUSTED_PROXIES` (never `*`, never empty, never the whole CIDR; see AC11), (b) create the NPM proxy
      host to `http://traveleasy-app:8080` with a LAN-only access list, (c) turn "Force SSL" on once HTTPS exists and leave NPM's
      HSTS off (the app sets HSTS), (d) make a manual backup before any data-destroying migration, and (e) the LAN-over-HTTP
      `SESSION_SECURE_COOKIE` exception for M0.
- [ ] AC11 (proxy trust, reviewer Medium 2026-11-02): Given NPM trusts every private range and passes on the client's
      `X-Forwarded-For`, and the app must not trust the whole `npm_shared` CIDR (that includes its gateway and neighbouring
      containers), when the doc is followed, then (a) NPM has a fixed IP on `npm_shared`, (b) `TRUSTED_PROXIES` contains only that
      IP (AC10a is amended accordingly: not the CIDR), and (c) a check proves real source IPs arrive: a request from a LAN device
      shows its LAN IP in the app's request log, and a request with a spoofed `X-Forwarded-For: 203.0.113.9` sent through NPM
      shows the real client IP or NPM's rewrite, never the spoofed one; the same spoofed header sent from a neighbouring
      container or via the Docker gateway is ignored. Evidence is pasted by the owner.
- [ ] AC12 (privacy, logs): Given NPM keeps access logs with client IPs, when the doc is read, then it states NPM's log location
      and retention (14 days or less, or how to set it), and that the app's own request logs carry no client IP (TE-024 AC10).
- [ ] AC9 (owner does the server steps): Given the doc, when read, then every Portainer and NPM step (create the proxy host, the
      access list, the stack, the env file, the redeploy) is written as an exact click-by-click/command instruction for the
      owner, with the expected result after each step; agents do not access the server, Portainer or NPM, and the evidence for
      AC5 to AC7 is pasted back by the owner.

## Quality criteria
- [ ] Tests: the production smoke script from TE-024 runs against the deployed URL (read-only checks); e2e + axe smoke against the LAN
      URL from a dev machine, results attached to the handoff.
- [ ] Accessibility: shell pages scanned by axe on the deployed URL; auditor's milestone audit at the end of M0 (report in `docs/a11y/`).
- [ ] Security/privacy: no secrets in the stack text in Portainer screenshots or docs; reviewer confirms exposure (LAN only), headers,
      DB not published, volumes, and that the Portainer env file in git has no real values.
- [ ] i18n: n/a beyond the shell.
- [ ] Performance: Lighthouse accessibility and best practices at least 90 on the shell page (mobile profile) on the deployed URL; the
      rest from M3.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/a11y/` report, `CLAUDE.md` Status and Decisions.

## Out of scope
- Public internet exposure, domain name and TLS certificate for the public (M6); demo mode and super_admin (M1); automated deploys.

## Open questions
- (none) Answered 2026-11-02: (1) no host port; NPM reaches the web container over the external Docker network `npm_shared`
  (TE-024 AC11), LAN-only access list for M0. (2) The owner does every Portainer and NPM step himself from exact step-by-step
  instructions written by the agents; agents get no access to the server, Portainer or NPM.
  The remaining choice of local host name for the NPM proxy host is made by the owner during the deploy.

## Rework log
(empty)

## Changelog
(filled when done)
