---
id: TE-041
title: Dry run of the deploy doc on a throwaway stack
status: rework
priority: must
epic: M0 First home-server deploy (split from TE-033; order: TE-040, TE-041, then TE-034)
agents: [qa-engineer, devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 1
---

## User story
As the owner, I want the deploy doc followed literally on a throwaway stack, so that the first deploy on my server hits no surprise.

## Context
- Split from TE-033 (2026-11-09, owner-approved, size limits). Dry-run criteria AC2, AC3, AC4 (checkable part) and AC11c (neighbour
  variant) moved here. The doc itself is TE-040.
- **Throwaway rule:** the dry run uses its own compose project name (for example `te-dryrun`) and its own network, volumes and
  names. It never uses the project `traveleasy-remake` or any `traveleasy_*` resource, never runs a global prune, and is torn down
  with project-scoped commands only. Agents get no access to the home server, Portainer or NPM.
- **Depends on:** TE-040 (the doc), TE-014 (published image), TE-024, TE-025. Does not need TE-031.
- The NPM-specific and phone-on-mobile-data checks cannot run on a throwaway host; they are owner evidence in TE-034.

## Acceptance criteria
- [ ] AC1: Given a throwaway project-scoped stack with only the doc, the env file and generated secrets, when the qa-engineer
      follows the steps literally, then the stack comes up healthy with no extra steps; every deviation found is written back into
      `docs/deploy/casaos-portainer.md` before the story can pass. (Was TE-033 AC2.)
- [ ] AC2: Given the same stack with one secret left out, when deployed, then compose shows the `${VAR:?}` message naming the
      missing secret and nothing starts; repeated for at least two different secrets. (Was TE-033 AC3.)
- [ ] AC3: Given the running stack, when `docker ps` is checked, then no TravelEasy container publishes a host port, the database
      is not reachable from the host, and the doc's statement that `npm_shared` must exist beforehand is verified (stack fails
      clearly without it). (Checkable part of TE-033 AC4.)
- [ ] AC4: Given a stand-in proxy container with a fixed IP on the throwaway network, when `TRUSTED_PROXIES` holds only that IP,
      then a request with `X-Forwarded-For: 203.0.113.9` from the stand-in is honoured as documented and the same header from a
      neighbouring container or the gateway is ignored, as shown by the app's resolved client IP in the doc's probe 5b (the app has no
      request log): stand-in -> 203.0.113.9, neighbour and gateway -> their own IPs. This includes the doc's neighbour-container
      variant of probe 5b. (Neighbour variant of TE-033 AC11c.)
- [ ] AC5: Given the stack, when `scripts/ci/verify-production-stack.sh` runs against it, then all its read-only checks pass.
- [ ] AC6 (NPM log rules, from TE-040 round 2): Given a throwaway container of the pinned NPM image (own name, own volumes), when the
      doc's logrotate file is mounted read-write (NPM chmods it at start, so `:ro` breaks NPM) and the exact NPM version tag is pinned in
      the doc, then NPM's logrotate (run every 2 days) keeps 14 days and accepts it (`logrotate -d` and a forced run), the `postrotate`
      reload works with pid path `/run/nginx/nginx.pid` and no `|| true`, `docker run --rm --entrypoint cat` works on the NPM image
      (as the doc uses it), the log lines use the `[Client x]` format, and check 5d (spoofed `X-Real-IP: 203.0.113.9` ignored) shows
      the real client IP; any wrong path or flag is corrected in the doc.
- [ ] AC7 (backup and restore, from TE-040 round 2): Given the running stack with some data, when the doc's dump, verify and restore
      procedure is run once in full (dump with `--single-transaction`, verify, safety dump with its distinct name, restore), then every
      command works as written, the data is back, and the dump file is mode 0600 inside a 0700 folder.
- [ ] AC8: Given the dry run is finished, when the evidence is read, then the commands and results are summarised in the story
      Changelog (no secrets), and the throwaway project, its volumes and network are removed with project-scoped commands only; no
      `traveleasy-remake` or `traveleasy_*` resource was touched.

## Quality criteria
- [ ] Tests: the dry run itself is the test; the production smoke script (AC5) is reused unchanged.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: no real secrets in logs, screenshots or docs; the reviewer confirms the throwaway naming rule was kept.
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md` with every deviation found; `docs/decisions.md` only if a decision changes.

## Files to touch
- `docs/deploy/casaos-portainer.md`: fix deviations found.
- `compose.yaml`, the Portainer env file (TE-025): change only if the dry run finds a defect.
- `scripts/ci/verify-production-stack.sh`: reuse (read-only).
- Reference: `app/Support/Security/TrustedProxies.php`, `app/Http/Middleware/TrustProxies.php`.

## Out of scope
- The real deploy and NPM/phone checks (TE-034); writing the doc (TE-040); public exposure.

## Open questions
- (none)

## Rework log

### Round 1 - 2026-11-10 - qa-engineer (dry run, evidence: `storage/logs/check/te-041-deviations.md`, D1 to D12)
Result: AC1 to AC5 pass (AC1 with deviations), AC6 FAIL, AC7 pass with deviation D4, AC8 teardown done.
Failed / doc fix required (owner: devops-engineer; edit `docs/deploy/casaos-portainer.md`, `compose.yaml` only if needed):
- **AC6 D1 (blocker):** NPM 2.16.0 runs `chmod 644 /etc/logrotate.d/nginx-proxy-manager` at start (`20-paths.sh`); with the `:ro` mount
  it fails and NPM never starts. Fix: mount the file read-write (host file root:root 0644), explain why, drop `:ro` in step 4 and the
  CasaOS sentence.
- **AC6 D2 (blocker):** NPM runs logrotate at backend start and then every 2 days, so `daily` + `rotate 13` keeps about 26 days. Fix: add
  `maxage 14` to every block (or `rotate 6`, or an external daily `docker exec`); update the "daily"/"13" wording in steps 3 and 6 and
  the 14-day claim.
- **AC6 D3:** step 6 expected output is wrong: two blocks print `after 1 days (13 rotations)`, `backend.log` prints a size line; the sed
  also changes backend.log `rotate 5` to 13. Fix the wording (2 + 1 lines) and the sed note.
- **AC7 D4:** `mkdir -p ~/traveleasy-backups` runs before `umask 077`, giving a 0755 folder. Fix: `umask 077` first (or `chmod 700`).
  Same item as TE-042 AC3; TE-042 ACs unchanged, its context notes it is fixed here.
- **D5:** `-it` wording: only the super_admin password prompt refuses without a TTY; with `--without-super-admin` secrets print.
- **D6:** expected Ports for app and ssr are `80/tcp, 443/tcp, 2019/tcp, 8080/tcp, 443/udp` (image EXPOSE); the check is "no `->`".
- **D7:** add a one-line note: always deploy with the stack name / `-p`, never bare `docker compose -f compose.yaml up`.
- **D10:** pin an exact NPM version tag in the doc (verified on 2.16.0), not `latest`.
- Also from D3/D9: change "verify in TE-041" to "verified in TE-041 (NPM 2.16.0)"; in probe 5b use `<a-neighbour-ip>` instead of `172.20.0.99`.
No action: D8 (stack name is only a naming assumption), D11 (doc correct; 5a/5c/5d confirmed), D12 (owner evidence in TE-034).
Criteria clarified:
- AC1: all deviations above that need doc changes are applied to the doc before re-test.
- AC4: the app has no request log (D9); the evidence is the app's resolved client IP from the doc's probe 5b (stand-in proxy ->
  203.0.113.9; neighbour and gateway -> their own IPs).
- AC6: the logrotate file is mounted read-write (not `:ro`), and retention is checked against the 2-day run interval (D1, D2).
Re-test: qa-engineer re-runs AC1, AC4 and AC6 (and AC7 folder mode) against the corrected doc.

## Changelog
(filled when done)
