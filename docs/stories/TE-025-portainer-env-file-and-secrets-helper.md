---
id: TE-025
title: One-file Portainer env file and the secrets helper command
status: done
priority: must
epic: M0 First home-server deploy
agents: [backend-developer, devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want to load one env file into Portainer and paste in a few generated secrets, so that deploying needs no
manual configuration hunt.

## Context
- `docs/plan.md` § 4 M0 "One-file Portainer deploy" (format, marked secrets, one helper command run from the published image,
  `${VAR:?}`), § 2 DoD (env file updated by every story that adds a production variable). `CLAUDE.md` Decisions (one-file deploy).
- **Depends on:** TE-012, TE-014 (published image), TE-024. **Blocks:** TE-026.
- The super_admin variables (`SUPER_ADMIN_*`) and `DEMO_MODE` arrive with M1. The helper is built so that M1 only adds fields; this
  story generates DB password and `APP_KEY` and has the plumbing (hidden password prompt, hash, TOTP + QR) for the rest, behind a flag
  enabled in M1 if the super_admin feature does not exist yet.

## Acceptance criteria
- [x] AC1: Given `deploy/portainer.env.example`, when read, then it is plain `KEY=value` lines with `#` comments (no `export`, no
      quotes-dependent or multi-line values, no trailing spaces), every production variable of `compose.yaml` appears with a
      working default and a one-line comment, and only the secrets have an empty value with a `# SECRET: generate with ...` comment.
- [x] AC2: Given the env file and `compose.yaml`, when a test diffs the variables, then the set of `${VAR}` names used in the compose
      file equals the set in the env file (plus documented exceptions); adding a variable to one without the other fails CI.
- [x] AC3 (ADR-0002 D5: five secrets in total): Given `docker run --rm -it ghcr.io/nimrodlobozar/traveleasy:<tag> php artisan
      traveleasy:generate-secrets`, when it runs without PHP on the host and through the entrypoint's pass-through (no DB wait,
      not blocked by the production boot check), then it prints ready-to-paste lines for two independent random DB passwords
      `DB_SUPERUSER_PASSWORD` and `DB_PASSWORD` (each at least 32 chars, URL-safe, no characters that break `.env`/Portainer) and
      an `APP_KEY` (`base64:` 32 bytes), and exits 0. The env file marks all five secrets (`DB_SUPERUSER_PASSWORD`, `DB_PASSWORD`,
      `APP_KEY`, and from M1 `SUPER_ADMIN_PASSWORD_HASH`, `SUPER_ADMIN_TOTP_SECRET`) and compose fails with `${VAR:?}` on any
      missing one. The env file also carries the non-secret defaults `DB_USERNAME`, `TRUSTED_PROXIES`, `INERTIA_SSR_URL`,
      `FRANKENPHP_NUM_THREADS` and the resource limits of TE-024 AC14. `TRUSTED_PROXIES` is **required** in compose
      (`${TRUSTED_PROXIES:?set it to the fixed NPM IP on npm_shared}`) and has no guessed default in the env file (empty, with a
      comment pointing to TE-026); a test proves compose fails when it is empty and that `*` and a whole-CIDR value are rejected
      by the config check.
- [x] AC4: Given the command, when run twice, then the values differ; nothing is written to disk, nothing is logged, and there is no
      network access (run with `--network none` in a test).
- [x] AC5: Given the command is run non-interactively (no TTY), when it needs a hidden prompt, then it fails with a clear message
      instead of reading a password from args or env; passwords are never accepted as command-line arguments.
- [x] AC6: Given the env file with the printed secrets pasted in, when the production stack from TE-024 is brought up in CI, then it
      starts healthy; with one secret left empty it fails naming the variable (the exit criterion of the milestone).
- [x] AC7: Given Portainer's "Load variables from .env file", when the file is loaded, then it parses (documented check with a screenshot
      or the exact steps in the handoff; a parser test with the same rules runs in CI).
- [x] AC8: Given the env file, when searched, then it contains no real secret, no personal e-mail address and no private host name; the
      image tag value is updated by the release process (documented) and a test fails if it points at a tag that does not exist in
      the release list or is `latest`.
- [x] AC9: Given `.env.example` (dev) and the Portainer file, when both are read, then every variable in one that exists in the other
      has the same meaning; production-only and dev-only variables are labelled.
- [x] AC10 (follow-up TE-015, 2026-11-02): Given the Portainer env file, when read, then it contains `HSTS_MAX_AGE`,
      `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD` and `CSP_REPORT_PATH` with safe documented defaults (no reporting endpoint, no
      preload), and the parser/consistency tests of AC7 and AC9 cover them.
- [x] AC11 (TE-012 security review, 2026-11-02): Given the Portainer env file, when read, then it does not set or override
      `INERTIA_DEVTOOLS_ENABLED` (the image default `false` stays), and it sets `SESSION_SECURE_COOKIE=true` (the LAN-over-HTTP
      exception for M0 is documented in TE-026 AC10e); a test fails if the file enables devtools or leaves the cookie flag unset.

## Quality criteria
- [x] Tests: Pest tests for the command (output format, entropy length, no files written via a fake filesystem, non-TTY failure);
      script test for AC2 and AC7; the CI job of TE-024 for AC6.
- [x] Accessibility: the terminal output of the command is plain text; the QR code (M1 flag) is accompanied by the text secret.
- [x] Security/privacy: secrets use `random_bytes`/CSPRNG; the command refuses to print inside CI logs when `CI=true` unless
      `--force-ci` is given; no secret ever reaches shell history (no args).
- [x] i18n: command output in English (operator tool); documented.
- [x] Performance: n/a.
- [x] Docs updated: `docs/deploy/casaos-portainer.md` (generate secrets, load file, paste, deploy), `.env.example`, `docs/plan.md` is not edited.

## Out of scope
- super_admin provisioning and `DEMO_MODE` behaviour (M1); changing secrets after first deploy (documented as a note only).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-11-03 on branch `feat/m0-batch-5`.
- **Added:** `php artisan traveleasy:generate-secrets` (1611291), `deploy/portainer.env.example` consistency and parser tests, secrets and
  maintenance docs in `docs/deploy/casaos-portainer.md` (da1b714). The contract test fails both ways when mutated.
- **Behaviour:** prints 5 secrets by default and 3 with `--without-super-admin` (M0). The super_admin hash is `base64:`-encoded so compose
  does not expand `$`; M1 provisioning must decode it (README "Follow-ups for M1").
- **Carried over:** the run from a **published** image (AC3 in its final form) moves to TE-014/TE-026 (criterion added in both).
- **Removed:** nothing. **Migrations:** none. **New env vars:** none for production (the env file itself is new); optional `CI` read by the
  helper. **Docker rebuild:** not needed.
- **Commits:** backend 1611291, e63bca7; devops da1b714.
- **Testers:** qa-engineer PASS (Pest 758 incl. the contract test); security-privacy-reviewer PASS (CSPRNG secrets, nothing written to disk,
  two Lows to TE-030).
