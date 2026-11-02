---
id: TE-011
title: ADR-0002 production runtime (web server, SSR packaging, migrate-on-start)
status: done
priority: must
epic: M0 CI/CD to GHCR
agents: [devops-engineer, architect]
testers: [security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner and maintainer, I want the production runtime decided and written down, so that the image, compose file and
pipeline are built on a reviewed decision instead of guesses.

## Context
- `docs/plan.md` § 4 M0 (ADR-0002: FrankenPHP or Nginx + PHP-FPM; Node in the app image or a separate SSR image; entrypoint
  waits for DB, migrates, seeds `reference` on an empty DB only) and § 7 (services `app`, `ssr`, `worker`, `scheduler`,
  `db`, `backup`; only `app` and `ssr` on the NPM network).
- Board notes "Notes for the production / deploy epics": migrate-on-start decision incl. rollback compatibility, DB role
  split, TrustProxies limited to NPM, outbound egress for the HIBP range API.
- devops-engineer drafts, architect reviews. The ADR is a docs-only story: no application code.
- **Blocks:** TE-012, TE-024.

## Acceptance criteria
- [x] AC1: Given `docs/adr/0002-production-runtime.md`, when read, then it follows the format of the existing ADRs (Status,
      Date, Decided by, Context, Decision, Consequences) with Status `Proposed` until the owner or architect accepts it.
- [x] AC2: It compares at least FrankenPHP and Nginx + PHP-FPM on image size, RAM on the HP ProDesk, hardening
      (read-only rootfs, non-root, port > 1024), SSE/long-poll needs (support chat polling, M8), build complexity and
      Laravel support, and states one choice with the reasons.
- [x] AC3: It decides SSR packaging (Node inside the app image vs a separate SSR image) and states the effect on image count,
      size, digest pinning, and the resource limit for `ssr` (default 256 MB).
- [x] AC4: It decides **migrate-on-start**: who runs migrations (entrypoint of `app` only, never of `worker`/`scheduler`),
      what happens on a failed migration (container stays unhealthy, previous tag still usable), and the rule that migrations
      must be backward compatible with the previous release so rollback by tag works. Seeds: `reference` on an empty DB only;
      `demo` never from the entrypoint.
- [x] AC5: It decides the DB roles: a non-superuser app role (used by `app`, `worker`, `scheduler`) and a separate
      privileged role used only for initialisation, and how the role is created on first run.
- [x] AC6: It states the reverse-proxy contract with Nginx Proxy Manager: which port `app` exposes on the proxy network,
      TrustProxies limited to NPM (address or network, never `*`), HTTPS terminated at NPM, HSTS set by the app only when
      `APP_URL` is https.
- [x] AC7: It states the outbound-egress stance (HIBP range API allowed or the M1 flag fails open) and that no other egress is
      needed in M0.
- [x] AC8: It lists the follow-up stories it creates or constrains (TE-012, TE-024) and any open question for the owner.

## Quality criteria
- [x] Tests: n/a (docs). A reviewer checks the sizes and RAM numbers by actually building the two candidates once and noting
      the measured values in the ADR.
- [x] Accessibility: n/a.
- [x] Security/privacy: `security-privacy-reviewer` reviews the ADR (privilege model, egress, proxy trust, DB roles).
- [x] i18n: n/a.
- [x] Docs updated: `docs/adr/0002-production-runtime.md`; one line in `CLAUDE.md` § Decisions once Accepted
      (written by the orchestrator/architect, not the developer).

## Out of scope
- Building the production image (TE-012) and the compose file (TE-024).

## Open questions
- (none; the choice is a technical default for devops and architect)

## Rework log
(empty)

## Changelog
Closed 2026-11-02. security-privacy-reviewer: PASS. Owner accepted ADR-0002 on 2026-11-02.
- Added: `docs/adr/0002-production-runtime.md` (Accepted): FrankenPHP; one image that includes Node (`app` and `ssr` share it);
  `db-init` one-shot service with `DB_SUPERUSER_PASSWORD`; `default` network internal; only `app` joins `npm_shared`.
- Changed: `docs/plan.md` (M0 and § 7 aligned with the ADR); `CLAUDE.md` § Decisions (one line); `docs/privacy/data-inventory.md`
  (production-runtime section by the reviewer).
- Removed: nothing. Migrations: none. New env vars: none yet (`DB_SUPERUSER_PASSWORD`, `TRUSTED_PROXIES` etc. arrive with TE-024/TE-025).
- Docker rebuild: not needed (docs only).
- Reviewer follow-ups turned into criteria: proxy trust in TE-012 AC12, TE-025 AC3, TE-026 AC11; migration order and secrets in
  TE-024 AC15 and AC13; `db-init` logging in TE-024 AC5; log privacy in TE-024 AC10 and TE-026 AC12; M1 notes in the board.
