---
id: TE-034
title: First LAN-only deploy to the home server and post-deploy checks (owner-gated)
status: ready
priority: must
epic: M0 First home-server deploy (split from TE-026; order: TE-033, then TE-034)
agents: [devops-engineer, backend-developer, qa-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the server-rendered app shell running on my CasaOS server, reachable only from my home network, so that
I know the whole path from `git push` to a running stack works.

## Context
- Split from TE-026 (2026-11-09, size limits). Criteria AC5-8, AC13 and AC15 moved here word for word; the rest is in TE-033.
- `docs/plan.md` § 4 M0 exit criteria (v0.0.x on the home server, healthy services, no host-exposed DB port, fresh Portainer
  stack with only the env file plus generated secrets, a missing secret fails loudly), § 2 DoD row 3 (deploying needs the owner's OK
  each time). `CLAUDE.md` Deployment target.
- **OWNER-GATED:** the actual deploy on the home server is performed by or with the owner. Agents prepare and verify everything
  else; they never change the server or Portainer without the owner's explicit go-ahead in this conversation. The first public
  internet exposure is out of scope (end of M6).
- **Depends on:** TE-033 (deploy doc proven by a dry run; required), TE-014 (a published `v0.0.x` image), TE-024, TE-025,
  TE-015 to TE-019 (the shell), TE-020 to TE-023 (design system; the deploy of the shell can happen once TE-016 to TE-019 are done
  if the owner wants an earlier first deploy; decide at that time). TE-035 (push by digest) is recommended before this deploy so the
  running tag is the scanned image, but is not required.
- AC13 is backend work (boot check, Pest test); it sits here by the owner's split and can be done before the deploy.
- Evidence for AC5 to AC7 is pasted back by the owner (TE-033 AC9).

## Acceptance criteria
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
- [ ] AC13 (security Low #5, 2026-11-03): Given `APP_ENV=production` and `APP_URL` starts with `https://`, when
      `SESSION_SECURE_COOKIE=false` is set, then the production boot check refuses to start (clear message, nonzero exit), so the
      LAN-over-HTTP exception (AC10e) cannot stay on by accident after HTTPS arrives; with an `http://` `APP_URL` the exception is
      still accepted. A Pest test covers both cases. (AC10e is in TE-033.)
- [ ] AC15 (carried over from TE-025 AC3): Given the published image, when the owner runs `docker run --rm -it
      ghcr.io/nimrodlobozar/traveleasy:<tag> php artisan traveleasy:generate-secrets --without-super-admin` (3 secrets in M0), then it
      prints ready-to-paste lines and exits 0; the output is pasted into the env file without further edits.

## Quality criteria
- [ ] Tests: the production smoke script from TE-024 runs against the deployed URL (read-only checks); e2e + axe smoke against the LAN
      URL from a dev machine, results attached to the handoff. Pest test for AC13 (both URL cases).
- [ ] Accessibility: shell pages scanned by axe on the deployed URL; auditor's milestone audit at the end of M0 (report in `docs/a11y/`).
- [ ] Security/privacy: no secrets in the stack text in Portainer screenshots or docs; reviewer confirms exposure (LAN only), headers,
      DB not published, volumes, and that the Portainer env file in git has no real values.
- [ ] i18n: n/a beyond the shell.
- [ ] Performance: Lighthouse accessibility and best practices at least 90 on the shell page (mobile profile) on the deployed URL; the
      rest from M3.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/a11y/` report, `CLAUDE.md` Status and Decisions.

## Files to touch
- `app/Support/Runtime/ProductionBootCheck.php` (AC13): refuse `SESSION_SECURE_COOKIE=false` with an `https://` `APP_URL`.
- `app/Console/Commands/BootCheck.php`: only if the message or exit code needs a change.
- Tests: `tests/Feature/Runtime/BootCheckTest.php` (both cases for AC13).
- `docs/deploy/casaos-portainer.md`: record version and date (AC8).
- `CLAUDE.md` (Status), `docs/stories/README.md`: M0 deploy exit criterion (AC8).
- `scripts/ci/verify-production-stack.sh`: run against the deployed URL (no change expected).
- `docs/a11y/`: milestone audit report (new, auditor).

## Out of scope
- Public internet exposure, domain name and TLS certificate for the public (M6); demo mode and super_admin (M1); automated deploys.
- Writing and dry-running the deploy doc (TE-033).

## Open questions
- (none) Answered 2026-11-02: (1) no host port; NPM reaches the web container over the external Docker network `npm_shared`
  (TE-024 AC11), LAN-only access list for M0. (2) The owner does every Portainer and NPM step himself from exact step-by-step
  instructions written by the agents; agents get no access to the server, Portainer or NPM.
  The remaining choice of local host name for the NPM proxy host is made by the owner during the deploy.

## Rework log
(empty)

## Changelog
(filled when done)
