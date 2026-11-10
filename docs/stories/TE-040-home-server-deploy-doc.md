---
id: TE-040
title: Home-server deploy doc (CasaOS, Portainer, NPM)
status: done
priority: must
epic: M0 First home-server deploy (split from TE-033; order: TE-040, TE-041, then TE-034)
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 2
---

## User story
As the owner, I want an exact step-by-step deploy doc for my CasaOS server, so that the first deploy needs no guessing.

## Context
- Split from TE-033 (2026-11-09, owner-approved, size limits). Doc criteria AC1, AC9, AC10, AC11 (doc part a and b), AC12 and AC14
  moved here; the dry run that proves the doc is **TE-041**; the real deploy is TE-034.
- `docs/plan.md` § 4 M0 exit criteria, § 2 DoD row 3. `CLAUDE.md` Deployment target. ADR-0002 D6.
- **OWNER-GATED:** none here; this story touches no server. Agents never access the server, Portainer or NPM.
- **Depends on:** TE-014, TE-024, TE-025 (all done).
- Doc: `docs/deploy/casaos-portainer.md` (exists; extend to v1).

## Acceptance criteria
- [ ] AC1: Given `docs/deploy/casaos-portainer.md`, when read, then it walks through, in numbered steps with the exact commands:
      prerequisites (Portainer, NPM, GHCR pull access), generate secrets (TE-025), create a stack (web editor), load the env
      file, paste the secrets, deploy, verify, update (bump tag), roll back (previous tag), and the "never use `-v`/prune" warnings.
- [ ] AC2: Given the doc, when read, then every Portainer and NPM step (create the proxy host, the access list, the stack, the env
      file, the redeploy) is an exact click-by-click or command instruction for the owner with the expected result after each step;
      the doc states that agents never access the server, Portainer or NPM and that the owner pastes back the evidence for the
      TE-034 checks. (Was TE-033 AC9.)
- [ ] AC3: Given the doc, when read, then it tells the owner to (a) read the CIDR of `npm_shared` with `docker network inspect
      npm_shared`, pick the fixed NPM IP inside it and put only that IP in `TRUSTED_PROXIES` (never `*`, never empty, never the
      whole CIDR), (b) create the NPM proxy host to `http://traveleasy-app:8080` with a LAN-only access list, (c) turn "Force SSL"
      on once HTTPS exists and leave NPM's HSTS off (the app sets HSTS), (d) make a manual backup before any data-destroying
      migration, and (e) the LAN-over-HTTP `SESSION_SECURE_COOKIE` exception for M0. (Was TE-033 AC10; ADR-0002 D6. NPM's HSTS stays
      off because the app sets HSTS; trusting the whole `npm_shared` CIDR was rejected in favour of one fixed NPM IP.)
- [ ] AC4: Given NPM must have a fixed IP on `npm_shared`, when the doc is read, then it gives the exact steps to fix it, says the
      `npm_shared` network must exist beforehand, and explains why the app must not trust the CIDR (gateway and neighbouring
      containers). (Was TE-033 AC11 a and b.)
- [ ] AC5: Given the owner must prove real client IPs arrive, when the doc is read, then it contains the check with exact commands
      and expected output: a request from a LAN device shows its LAN IP in NPM's access log plus the TrustProxies probe (the app
      logs no client IP, TE-024 AC10); a request through NPM with `X-Forwarded-For: 203.0.113.9` or `X-Real-IP: 203.0.113.9`
      shows the real client IP or NPM's rewrite, never the spoofed one (the proxy host sets `set_real_ip_from 127.0.0.1/32;`);
      the same header sent from a neighbouring container or the Docker gateway is ignored. (Was TE-033 AC11c; the owner runs it in TE-034, the neighbouring
      container variant is also run in TE-041.)
- [ ] AC6: Given the doc, when read, then it states that no host port is published (`docker ps` shows none on any TravelEasy
      container), that access is LAN-only (NPM access list, router has no port forward), and how the owner confirms it with a phone
      on mobile data. (Doc part of TE-033 AC4.)
- [ ] AC7 (privacy, logs): Given NPM keeps access logs with client IPs, when the doc is read, then it states NPM's log location and
      retention (14 days or less, or how to set it), and that the app's own request logs carry no client IP (TE-024 AC10). (Was
      TE-033 AC12.)
- [ ] AC8 (from TE-019 AC9, optional owner step): Given the web server's static 502/504 page cannot be produced by FrankenPHP's
      `php_server`, and NPM serves its own 502 when `app` is down, when the doc is read, then it records the decision whether to
      configure an NPM custom error page (owner step, optional) and, if yes, gives the exact steps. (Was TE-033 AC14.)

## Quality criteria
- [ ] Tests: doc-only; a link/anchor check of the doc if the repo has one; the commands are proven in TE-041.
- [ ] Accessibility: n/a (documentation only).
- [ ] Security/privacy: no secrets or real values in the doc or the env file in git; the reviewer confirms LAN-only exposure, DB
      not published, `TRUSTED_PROXIES` a single IP.
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`; `docs/decisions.md` only if a decision changes.

## Files to touch
- `docs/deploy/casaos-portainer.md`: write/finish v1.
- `docs/deploy/production-image.md`, `docs/deploy/security-headers.md`: link only, if needed.
- Read-only reference: `compose.yaml`, the Portainer env file (TE-025), `app/Support/Security/TrustedProxies.php`,
  `app/Http/Middleware/TrustProxies.php`, `tests/Feature/Security/TrustProxiesTest.php`.

## Out of scope
- Running the doc on any stack (TE-041) and the real deploy (TE-034).
- Public internet exposure, domain and TLS for the public (M6); demo mode and super_admin (M1).

## Open questions
- (none)

## Rework log
### Round 1 (2026-11-09)
Tester: security-privacy-reviewer FAIL (qa-engineer PASS, check.sh green). Responsible: devops-engineer (doc only). All three
findings are must-fix this round. Reviewer already updated `docs/privacy/data-inventory.md:53`.
1. **AC7 failed (Medium, blocker)**, `docs/deploy/casaos-portainer.md` lines 186-189. Upstream NPM rotates access logs weekly
   (rotate 4) and error logs weekly (rotate 10). The doc's "weekly + rotate 2" keeps about 21 days (limit is 14), and
   `_error.log` (also holds client IPs) is not covered. An edit inside the container's `/etc/logrotate.d` is lost when NPM is
   recreated (step 2c does that), and no exact command is given.
   Fix: mount a read-only logrotate rule from NPM's own stack, `daily` + `rotate 13`, for both access and error logs; give the
   exact stack snippet and verify with `logrotate -d`.
2. **Security (Medium)**, lines 177-180. NPM trusts `X-Real-IP` from private and CDN ranges, so a LAN device can spoof its IP past
   per-IP login throttles, and later a request via the CDN could pass `lan-only`. Step 5c tests only XFF, which NPM ignores.
   Fix: add `set_real_ip_from 127.0.0.1/32;` to the proxy host's Advanced config, and add check 5d with
   `-H "X-Real-IP: 203.0.113.9"` and the expected output (spoofed IP never shown).
3. **Low, must-fix**: (a) line ~262, `pg_restore --clean` overwrites the live DB; the doc must say to dump the current DB first.
   (b) lines 148 and 160: the evidence the owner pastes includes whole NPM log lines; ask for the IP only.
4. **Criterion clarified (AC5 was wrong)**: the app logs no client IP (TE-024 AC10), so "the app's request log" cannot show it.
   AC5 now reads: NPM's access log plus the TrustProxies probe. Wording updated above.

### Round 2 (2026-11-09)
Tester: security-privacy-reviewer FAIL (qa-engineer PASS, check.sh all green). Round 1 findings resolved. Responsible:
devops-engineer (doc only, `docs/deploy/casaos-portainer.md`).
1. **High, must-fix (data loss risk)**, lines 330-331 with 328. The safety dump taken before a restore reuses the pre-update dump
   name `traveleasy-$(date +%F).dump` with a `>` redirect. Restoring on the same day as an update overwrites the only good backup
   (invoices must be kept 7 years).
   Fix: give the safety dump a distinct name, e.g. `traveleasy-pre-restore-$(date +%F-%H%M%S).dump`; add `set -o noclobber`;
   add an explicit warning never to write the safety dump to the file being restored from.
2. **Low, must-fix this round (avoids TE-041 rework)**, lines 216-238. The mounted logrotate file replaces NPM's whole upstream
   rule and drops `su npm npm`, `create 0644 npm npm`, the `/data/logs/*/access.log` and `*/error.log` patterns, and the
   `backend.log` rotation. The fallback postrotate pid path `/run/nginx/nginx.pid` may be wrong, and `|| true` hides the failure.
   Fix: keep the upstream rule unchanged except `weekly` to `daily` and `rotate` to 13; mark the pid path "verify in TE-041";
   remove `|| true` so failures show.

## Changelog
Closed 2026-11-10 after 2 rework rounds; all testers PASS (QA: check.sh all green, php 759/759, front 23 files, e2e 144 passed / 2 skipped; security-privacy-reviewer PASS round 2).
- Added: deploy guide v1 `docs/deploy/casaos-portainer.md` (prerequisites, secrets, stack, NPM proxy host and access list, fixed NPM IP and `TRUSTED_PROXIES`, checks 5a-5d, no-host-port proof, NPM log retention via mounted `:ro` logrotate rule, dump/verify/restore, update, rollback, optional NPM error page). Commits on `docs/te-040-deploy-doc`: 23c2a8e, c09106d, beb15f9.
- Changed: `docs/privacy/data-inventory.md` (rows for NPM logs and manual dumps, added by the reviewer).
- Removed: none. Migrations: none. New env vars: none. Docker rebuild: not needed (documentation only).
- Follow-ups: commands proven in TE-041 (extended with NPM log, 5d and restore criteria); dump retention and `umask` order in TE-042.
