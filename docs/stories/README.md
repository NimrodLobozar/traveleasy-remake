# Story board

Maintained by the `task-writer`; the orchestrator updates the status during the flow.
Statuses: `draft` → `ready` → `in-progress` → `in-test` → (`rework` → `in-progress` …) → `done`. `split` = superseded by smaller stories (history only).
Priority (MoSCoW): `must` · `should` · `could` · `nice-to-have`.

| ID | Title | Priority | Status | Rework rounds | Design |
|---|---|---|---|---|---|
| [TE-001](TE-001-scaffold-laravel-react-starter-kit.md) | Scaffold the Laravel React starter kit (strict TS, PostgreSQL, no mail) | must | done | 0 | not-needed |
| [TE-002](TE-002-docker-dev-environment.md) | Docker dev environment with hot reload and a PostgreSQL named volume | must | done | 1 | not-needed |
| [TE-003](TE-003-code-quality-tooling.md) | Code quality tooling (Pint, Larastan, ESLint, Prettier, tsc) | must | done | 1 | not-needed |
| [TE-004](TE-004-pest-arch-tests.md) | Pest on PostgreSQL with arch tests that forbid the legacy anti-patterns | must | done | 3 | not-needed |
| [TE-005](TE-005-vitest-playwright-axe-harness.md) | Frontend test harness (Vitest + Testing Library, Playwright + axe) | must | done | 1 | not-needed |
| [TE-006](TE-006-dev-and-testing-docs.md) | Developer docs (dev.md, testing.md) and exact stack versions in CLAUDE.md | must | done | 1 | not-needed |
| [TE-007](TE-007-inertia-ssr-entry-point.md) | Inertia SSR entry point, SSR build and SSR-safe code rules | must | done | 0 | not-needed |
| [TE-008](TE-008-ssr-service-and-smoke-test.md) | SSR service in compose (dev profile) and an SSR smoke test | must | done | 0 | not-needed |
| [TE-009](TE-009-i18n-infrastructure.md) | i18n infrastructure (nl/en JSON lang files, SSR-safe t(), Intl formatting) | must | done | 0 | not-needed |
| [TE-010](TE-010-i18n-key-completeness-check.md) | i18n key-completeness check | must | done | 0 | not-needed |
| [TE-011](TE-011-adr-0002-production-runtime.md) | ADR-0002 production runtime (web server, SSR packaging, migrate-on-start) | must | done | 0 | not-needed |
| [TE-012](TE-012-production-image.md) | Production multi-stage image with entrypoint, health check and hardening | must | done | 0 | not-needed |
| [TE-013](TE-013-ci-quality-gates.md) | GitHub Actions pipeline, part 1: quality gates | must | done | 0 | not-needed |
| [TE-014](TE-014-ci-image-build-scan-publish.md) | GitHub Actions pipeline, part 2: amd64 build, Trivy, publish to GHCR | must | done | 0 | not-needed |
| [TE-015](TE-015-security-headers-and-csp-baseline.md) | Security headers and CSP baseline (nonces) | must | done | 0 | not-needed |
| [TE-016](TE-016-accessible-base-layout.md) | Accessible base layout (landmarks, skip link, route-change focus, empty axe allow-list) | must | done | 1 | required |
| [TE-017](TE-017-language-switch.md) | Language switch nl/en with persisted preference | must | done | 0 | required (TE-016 artifact) |
| [TE-018](TE-018-theme-light-dark-no-flash.md) | Light/dark theme with a manual override and no flash under SSR | must | done | 0 | required (TE-016 artifact) |
| [TE-019](TE-019-localised-error-pages.md) | Localised error pages (403, 404, 419, 429, 500, 503) with a request ID | must | done | 0 | required (TE-016 artifact) |
| [TE-020](TE-020-design-tokens-and-type-scale.md) | Design tokens and type scale (artifact, token file, contrast tests) | must | done | 1 | required |
| [TE-021](TE-021-base-component-specs.md) | Base component specs (states, keyboard, ARIA) | must | done | 0 | required |
| [TE-022](TE-022-base-components-forms-and-feedback.md) | Base components I: button, link, field, select, number field, alert, banner | must | done | 1 | approved (TE-021 artifact) |
| [TE-023](TE-023-base-components-complex-widgets.md) | Base components II: combobox, date picker, table, dialog, toast, pagination | must | done | 0 | approved (TE-021 artifact) |
| [TE-024](TE-024-production-compose-for-portainer.md) | Production compose.yaml for Portainer (pinned tag, health checks, limits, ssr) | must | done | 0 | not-needed |
| [TE-025](TE-025-portainer-env-file-and-secrets-helper.md) | One-file Portainer env file and the secrets helper command | must | done | 0 | not-needed |
| [TE-026](TE-026-first-lan-only-deploy.md) | First LAN-only deploy to the home server (**owner-gated**); split into TE-033 and TE-034 | must | split | 0 | not-needed |
| [TE-033](TE-033-deploy-doc-and-dry-run.md) | Home-server deploy doc and dry run on a throwaway stack; split into TE-040 and TE-041 | must | split | 0 | not-needed |
| [TE-040](TE-040-home-server-deploy-doc.md) | Home-server deploy doc (CasaOS, Portainer, NPM) | must | done | 2 | not-needed |
| [TE-041](TE-041-deploy-dry-run-throwaway-stack.md) | Dry run of the deploy doc on a throwaway stack (after TE-040) | must | done | 1 | not-needed |
| [TE-042](TE-042-manual-dump-retention-and-umask.md) | Manual dump retention rule, restore re-erasure note, umask order (TE-040 follow-ups; before TE-034) | should | done | 0 | not-needed |
| [TE-034](TE-034-owner-lan-deploy-and-post-deploy-checks.md) | First LAN-only deploy and post-deploy checks (**owner-gated**; after TE-041) | must | ready | 0 | not-needed |
| [TE-027](TE-027-bump-php-8-5-12-dev-image.md) | Bump PHP to 8.5.12 in the dev image (chore) | must | done | 0 | not-needed |
| [TE-028](TE-028-production-image-hardening-follow-ups.md) | Production image hardening follow-ups (chore) | must | done | 0 | not-needed |
| [TE-029](TE-029-ci-and-deploy-hardening-follow-ups.md) | CI and deploy hardening follow-ups (chore) | should | done | 0 | not-needed |
| [TE-030](TE-030-deploy-and-error-page-polish.md) | Deploy and error-page polish (chore) | should | done | 0 | not-needed |
| [TE-031](TE-031-release-push-the-scanned-image.md) | Release pushes the scanned image by digest, and Trivy ignores are scoped per image; split into TE-035 and TE-036 | should | split | 0 | not-needed |
| [TE-035](TE-035-release-push-by-digest.md) | Release pushes the scanned image by digest and gates the tags | should | done | 1 | not-needed |
| [TE-039](TE-039-signed-attestations-ghcr-retention.md) | Signed attestations on the pushed digest and `ci-*` staging tag cleanup (before go-live) | should | ready | 0 | not-needed |
| [TE-036](TE-036-trivy-ignores-scoped-per-image.md) | Trivy ignores are scoped per image (migrate the 20 entries, before 2026-12-15) | should | done | 1 | not-needed |
| [TE-043](TE-043-actionlint-sc2086-scan-weekly.md) | Fix actionlint SC2086 in the weekly scan workflow (CI red on dev and PR #17) | must | done | 0 | not-needed |
| [TE-044](TE-044-trivy-high-findings-debian-13-7.md) | Clear the 9 High Trivy findings in the prod image (FrankenPHP 1.13.5, apt 3.0.4, scoped ignores for no-fix krb5/sqlite) | must | done | 0 | not-needed |
| [TE-045](TE-045-weekly-scan-dev-playwright-findings.md) | Weekly scan green: dev and Playwright image findings (split from TE-044) | must | done | 0 | not-needed |
| [TE-046](TE-046-weekly-scan-ignore-hygiene.md) | Weekly-scan ignore hygiene (follow-up to TE-045) | should | ready | 0 | not-needed |
| [TE-047](TE-047-admin-account-erasure-and-id-based-re-erasure.md) | Admin/artisan account erasure and ID-based re-erasure (code; TE-042 review; before go-live, with TE-039) | must | done | 0 | not-needed |
| [TE-048](TE-048-restore-procedure-re-erasure-docs.md) | Restore procedure rewrite with ID-based re-erasure (docs; split from TE-047, depends on it) | must | done | 0 | not-needed |
| [TE-049](TE-049-normalise-e-mail-case.md) | Normalise e-mail case so the protected super_admin address cannot be claimed (follow-up to TE-047; before go-live) | must | done | 0 | not-needed |
| [TE-050](TE-050-stabilise-timing-flaky-locale-tests.md) | Stabilise timing-flaky locale tests (switches e2e, LocaleResolutionTest) | should | ready | 0 | not-needed |
| [TE-051](TE-051-restore-procedure-hardening.md) | Restore procedure hardening: app.env cleanup, users_id_seq reset, doc corrections (docs; follow-up to TE-048; before go-live) | must | ready | 0 | not-needed |
| [TE-052](TE-052-rate-limit-register.md) | Rate-limit POST /register by IP (security review of TE-049; before go-live) | must | done | 0 | not-needed |
| [TE-053](TE-053-email-whitespace-parity.md) | Whitespace parity between PostgreSQL btrim and PHP trim for e-mail normalisation (follow-up to TE-049) | could | ready | 0 | not-needed |
| [TE-037](TE-037-vite-plus-1x-upgrade.md) | Upgrade vite-plus to 1.x (due before 2026-12-15) | should | done | 1 | not-needed |
| [TE-032](TE-032-dependency-updates-2026-11.md) | Safe dependency updates from Dependabot PRs #1-#3 (2026-11) | should | done | 0 | not-needed |
| [TE-038](TE-038-symfony-serializer-advisories.md) | Fix symfony/serializer advisories blocking the composer audit gate | must | done | 1 | not-needed |

### Epics
- **M0 Project scaffold and tooling:** TE-001, TE-003, TE-004, TE-005 (docs part in TE-006).
- **M0 Docker dev environment:** TE-002, TE-006.
- **M0 Server-side rendering:** TE-007, TE-008.
- **M0 i18n infrastructure:** TE-009, TE-010.
- **M0 CI/CD to GHCR:** TE-011 (ADR-0002), TE-012, TE-013, TE-014, TE-028, TE-029, TE-030, TE-031 (split), TE-035, TE-036, TE-039.
- **M0 App shell:** TE-015, TE-016, TE-017, TE-018, TE-019.
- **M0 Design system:** TE-020, TE-021, TE-022, TE-023.
- **M0 First home-server deploy:** TE-024, TE-025, TE-026 (split), TE-033 (split), TE-040, TE-041, TE-034 (order: TE-040, TE-041, TE-034).
- **Maintenance:** TE-032, TE-037, TE-038.

### Follow-ups (2026-11 dependency updates)
Deferred from TE-032 (major or breaking; each needs its own story before it is applied):
- TypeScript 7 (major): needs a dedicated compatibility story (tsc, oxlint type-aware rules, SSR build).
- **phpunit advisory PKSA-qhjm-rpqc-qkn6 (dev-only, medium, from TE-032):** phpunit 13.4.1 stays until Pest allows phpunit ≥ 13.4.4; then move it (`composer.json`, `composer audit` must be clean).
- lucide-react 1.x (major): icon API changes need a UI story.
- @laravel/passkeys 0.4 (0.x minor, breaking): needs its own auth story (passkeys are not in M0).
- @rolldown/plugin-babel 0.3 (0.x minor, breaking): needs a build-pipeline story.
- @types/node 26 (major, not Node 24): waits until the runtime moves to Node 26.

### Moved into the M0 stories (2026-11-02)
The notes that used to sit here are now acceptance or quality criteria:
- "Notes for the production / deploy epics" (TE-002 security review): TE-011 (migrate-on-start, DB roles, egress, proxy trust),
  TE-012 (production image stage, production build, config, devtools check, env documentation), TE-013 (CI guards, audits),
  TE-014 (image scan), TE-024 (compose hardening, DB role, digest pins).
- "Notes for the CI/CD epic" (TE-005 security L3): TE-013 (Playwright image digest, Dependabot, artifacts on failure only,
  non-localhost request guard) and TE-014 (scans of the dev and Playwright images).
- "Notes for the App shell epic" (TE-005 a11y review): TE-016 (empty allow-list, scan coverage, best-practice pass); the optional
  "allow entries hide every node" item is moot once the list is empty. The `REMOVAL_EPICS` alignment from "Follow-ups from
  TE-006" is also in TE-016.
- "Follow-ups for the frontend harness" (AST scan for computed `allow` keys and `.js`/`.mjs`): TE-016 AC10.
- "Notes for the design-system epic": TE-020 (contrast token pairs), TE-022 and TE-023 (target size, focus, reduced motion, zoom and
  text spacing, live regions in real Chromium).

### Devops items
Not tied to a story yet. Pick up at the next devops touch, or as a small `chore` story if it keeps hurting.
- **vite-plus restart race** (hit several agents):
  - **Symptom:** after a change to `vite.config.ts` or an `.env*` file, the dev server can end up serving 504 on
    `/@vite/client` ("Port 5173 is already in use"), while the container healthcheck stays healthy.
  - **Fix:** make the `vite` healthcheck actually request `/@vite/client` and expect 200. Then look for a restart fix,
    or document the workaround (`docker compose restart vite`) in `dev.md`.
  - **Resolved (TE-006, 2026-10-12):**
    - the healthcheck already requests `/@vite/client`, which was proven by pausing the server with SIGSTOP;
    - the workaround is documented in the `dev.md` troubleshooting section.

    Reopen only if an upstream restart fix becomes available.
- **Dependency audit triage (2026-11-02, from TE-007):** re-check at TE-027 and in TE-013's audit gate.
  - `composer audit`: symfony/mime CVE-2026-45070 (not exploitable, the app sends no mail); phpunit (dev only).
  - `npm audit`: vitest/mocker and undici (dev only; `npm audit --omit=dev` finds 0).
  - Bumps to plan (2026-11-02, from TE-008/TE-015 reviews): `undici` (via jsdom, test only), `@vitest/mocker`, phpunit and
    symfony/mime; re-check at TE-027.
  - **Re-checked at TE-027 (2026-11-02, after the PHP 8.5.12 rebuild):** unchanged. `composer audit`: phpunit CVE-2026-119193
    (dev only) and symfony/mime CVE-2026-45070 (no mail sent); `npm audit`: 6 (4 moderate, 2 high: undici via jsdom, @vitest/mocker; dev/test only). No bump taken here.
  - **Bumps still open after TE-027 closed (2026-11-02):** symfony/mime to 7.4.22/8.1.10 or later, phpunit to 13.4.4 or later, and the
    vite-plus/jsdom update (undici, @vitest/mocker) at TE-013's audit gate.
- **npm audit dev-only issues (from TE-023 close, 2026-11-03):** `npm audit` shows 6 dev-only issues (undici via jsdom, vitest/@vitest/mocker). Check whether Dependabot PR #3 resolves them.
- **Lint and dev `ssr` hardening (2026-11-02, security Low, from TE-008):**
  - the `no-node-process` lint rule can be bypassed via `globalThis.process`, `global.process`, `import('node:process')`,
    `require('process')` or a re-export, and it only checks `*.ts`/`*.tsx`; close these gaps with fixtures;
  - the dev `ssr` service masks only `.env`; copy the e2e service's masking of all `.env*` files except `.env.example`, plus its
    contract test.
- **e2e passthrough:** `E2E_CSP_500_PATH` on the e2e compose service (needed by TE-019 AC11).

### General accessibility note
Content in another language than the page (for example English place names or flight data on a Dutch page) gets a `lang`
attribute (WCAG 3.1.2); use the helper from TE-009. Every story that shows such content checks it.

### Follow-up for M1 (or the first story that shows these fields)
- The server-made texts `created_at_diff` and `last_used_at_diff` are not localised yet (from TE-009).

### Follow-ups from TE-006
From the TE-006 close (2026-10-12).

**Devops** (next devops touch, or a small `chore` story):
- **L6-a:** a Vitest check that Vite's default `server.fs.deny` list is contained in ours, so upgrade drift is caught.
- **L6-b:** add `**/playwright-report/**` and `**/test-results/**` to `server.fs.deny`.
- **L6-c** (`dev.md`): the "Changed `DB_PASSWORD`" fix should use psql's `\password traveleasy` instead of
  `ALTER USER … PASSWORD '…'`, which lands in psql history.

**Docs** (`dev.md` / `testing.md`):
- fix the line about `npx vp --version`, which also prints the Vite version;
- align the test timings between `dev.md` and `testing.md`.

**Frontend, App shell epic:** moved to TE-016 AC7.

**Backend, next arch-rule hardening pass:**
- the TE-006 AC13e spawned-process recommendation needs an exemption for `TestDatabaseIsolationTest`'s
  `Process(['php', 'artisan', 'config:cache'])`, or that helper moves to `tests/Support/`.

**QA, watch item:**
- serial Pest timing is creeping (31–51 s against the 60 s budget). Re-check at the end of M0 and consider
  `--parallel` as the default or a split suite.

**Architect:**
- `docs/plan.md` (and the TE-003 title) still say "ESLint, Prettier", but the tooling is vite-plus oxlint/oxfmt.
  Update the wording the next time the plan is touched.

**Owner-pending:**
- 5 dangling `traveleasy-remake-dev` images (about 6.9 GB) can be removed with the project-filtered prune in
  `docs/deploy/dev.md`. It needs the owner's OK.

### Follow-ups for the frontend harness
- **Welcome page (from TE-020 close, 2026-11-02):** `resources/js/pages/welcome.tsx` still has hard-coded colours and `text-white`.
  Replace it when the public home is built (TE-016 or M3), then remove it from the hex-scan allow-list.
- **Scroll-restore and title timers (from TE-016 rework, 2026-11-03, Minor, a11y):** the scroll-restore retry (150 ms) and the
  title write (80 ms) use loose timers, so rapid back/forward presses could briefly overlap announcements.
- **`aria-current` forced-colours check (from TE-016, 2026-11-03):** the check in the nav can't run until a nav item points to a
  real page; enable it when the first real page exists.
- **`ScaffoldContractTest` flake (from TE-016 close, 2026-11-03, QA watch item):** failed once in `composer check` and passed on
  the rerun; investigate the flakiness.
- **(a) e2e waits (from TE-022 close, 2026-11-03):** e2e specs use `waitForLoadState('networkidle')`, which times out under parallel load on the Vite dev server. Replace it with a wait on a hydrated element.
- **(b) One e2e run per working tree (from TE-022 close, 2026-11-03):** only one e2e run at a time per working tree; concurrent runs cause `EADDRINUSE` and false axe failures.
- **(c) Owner manual screen-reader checklist for the base components (from TE-022 close, 2026-11-03, owner, manual):** NVDA and VoiceOver checks for password reveal `aria-pressed`, error summary focus, NumberField announcements, Select touch fallback, and banner dismiss focus.
- **(d) Owner manual screen-reader checklist for the complex widgets (from TE-023 close, 2026-11-03, owner, manual):** combobox count announcement and double Escape; calendar day names; toast polite vs assertive and F6 reach; table sort announcement; dialog title and focus return.

## Follow-ups for M1
Found while TE-001 was tested (2026-10-11; security-privacy-reviewer and qa-engineer). There are no M1 story files
yet. When M1 stories are written, put each item below into the matching story as a criterion, then remove it here.

- **Breached-password check (ADR-0002 D7, accepted 2026-11-02):** keep HIBP `Password::uncompromised()`, behind a config flag in
  `config/traveleasy.php`. Bind the verifier with an HTTP timeout of about 3 s (not Laravel's 30 s default) and **fail open**:
  if the API is unreachable, registration succeeds quickly. A test proves it. Egress: `app` only, via `npm_shared`
  (`api.pwnedpasswords.com`, 5-char SHA-1 prefix with `Add-Padding`); `worker`, `scheduler`, `ssr`, `db` stay on the internal network.
  (This is an external call, so the reviewer checks it against the "no personal data to external services" rule:
  only the k-anonymity hash prefix is sent.)
- **i18n keys (from TE-010 close, 2026-11-02):** `ProfileController.php:40` and `SecurityController.php:67` use `__('Profile updated.')` / `__('Password updated.')` with no key; give them real keys in the first story that touches them. Remove the unused key `nav.settings` or use it in TE-016.
- **Sessions:** `SESSION_ENCRYPT=true` everywhere; `SESSION_SECURE_COOKIE=true` in production.
- **Appearance cookie and CSP:** moved to M0 (cookie allow-list in TE-018, nonce for the inline theme script in TE-015/TE-018).
- **Trusted proxies:** moved to M0 (TE-011 decision, TE-012 AC12, TE-025 AC3, TE-026 AC11).
- **Rate limits never rely on IP alone** (reviewer, 2026-11-02; client IPs can be spoofed through `X-Forwarded-For` until TE-026
  AC11 is proven): every limiter in the rate-limit story also keys per account (or per session/username), not just per IP.
- **Request ID in logs + later services (from TE-024 close, 2026-11-03):** JSON logs carry a request ID (TE-024 AC10, not done; no
  client IPs are logged because the Caddy access log is off), and the `worker`, `scheduler` and `backup` services are added with
  `depends_on: app: condition: service_healthy`.
- **Worker/scheduler start order:** the `worker` and `scheduler` service stories add `depends_on: app: condition: service_healthy`,
  so they never run new code while `app` is still migrating (TE-024 AC15 does this for the services that exist in M0).
- **ADR-0002 requirements for M1 stories:**
  - `worker`/`scheduler` health checks via `php artisan traveleasy:healthcheck worker|scheduler` (heartbeat job every minute
    processed within 3 min; scheduled closure heartbeat under 3 min old; interval 60 s, timeout 10 s, retries 3);
  - `SUPER_ADMIN_PASSWORD_HASH` and `SUPER_ADMIN_TOTP_SECRET` join the Portainer env file as secrets 4 and 5 (helper from TE-025;
    it prints 5 secrets by default and 3 with `--without-super-admin`). The hash is `base64:`-encoded so compose does not expand
    `$`; the M1 super_admin provisioning must **decode** it before use;
  - the `app` role does DDL and DML (one role); migrations follow expand/contract (no drops or renames in the same release);
  - `worker` memory 256M PHP `memory_limit`, container limit 384 MB; no endpoint may hold a request open for more than a few seconds.
- **Trusted hosts** (from the TE-002 security review; ADR-0002 D6 completes the proxy contract with it): `$middleware->trustHosts()` derived from the `APP_URL` host. Health checks send that Host header.
  Today DNS rebinding can reach `/_inertia/devtools/*` on `127.0.0.1:8000` while `APP_ENV=local`.
- **Rate limits:**
  - per-account login throttling across IPs, plus limits on register, confirm-password and delete-account;
  - **per-IP login limit across usernames** (TE-003 security F1);
  - check the two-factor limiter, whose key `session('login.id')` can be null (TE-003 security F1/F2).
- **Passkeys (owner decides in M1):**
  - assumed **off** until `laravel/passkeys` 1.0, because a passkey login skips TOTP, which breaks mandatory 2FA for
    manager, admin and super_admin;
  - if they stay:
    - add a per-IP cap on the passkeys limiter, which today is keyed by an attacker-chosen credential id
      (TE-003 security F2);
    - use a separate `PASSKEYS_USER_HANDLE_SECRET` (today it falls back to `APP_KEY`), documented in `.env.example`.
- **Auth-UI accessibility** (found during TE-003; owner frontend-developer, checked by the accessibility-auditor):
  - `resources/js/components/password-input.tsx:29`: the show-password toggle has `tabIndex={-1}`, so keyboard users
    can't reach it. Make it focusable with an accessible name and pressed state (nl/en).
  - The "copy" button on the 2FA setup page is icon-only, with no accessible name and no `type`. Give it an
    accessible name (nl/en) and `type="button"`, and announce the copy result to screen readers.
  - Review the 7 `no-autofocus` lint suppressions on the auth pages. Keep autofocus only where it helps (e.g. the
    first field of a single-purpose form), and remove the suppression everywhere else.
- **Case-insensitive unique e-mail on PostgreSQL:** a unique index on `lower(email)` (or `citext`), and lowercase
  the address in the register and `ProfileUpdateRequest` validation.
- **Seeders:** split into `reference` and `demo`. The kit's `DatabaseSeeder` test user (`test@example.com` /
  `password`) must never run in production. Add a test for it.
- **Inertia history:** `INERTIA_ENCRYPT_HISTORY=true`, and `clearHistory()` on logout.
- **Security-event logging:** feeds the M1 audit-log story.
- **CSRF coverage in demo mode** (TE-004 security L-d): once `DemoMode` exists, run `CsrfRuntimeCoverageTest` with
  `DEMO_MODE` on **and** off (ADR-0006 on/off rule).
- **Rule for auth and booking e2e tests** (from the TE-005 security review; applies from M1 on):
  - fake users only, and never real 2FA secrets;
  - traces and reports contain cookies and typed passwords, so treat `test-results/` and `playwright-report/` as
    sensitive (git-ignored, never shared or uploaded publicly);
  - Playwright `storageState` files only under `test-results/` or `/tmp`;
  - `playwright-report/` is sensitive **even after green runs**, because it holds page HTML and URLs
    (TE-005 security L5).
- **Clean-up:**
  - remove the `password_reset_tokens` broker from `config/auth.php`;
  - remove the unused `status` prop in `ProfileController::edit`;
  - set the `filesystems` local disk to `serve => false` until a story needs temporary URLs.
    - QA observation from TE-004: today `serve => true` registers signed `GET` and `PUT` `storage/{path}` routes.
    - The security reviewer decides in M1 whether the private disk is served at all. The default is no, until the M5
      invoice and M6 export downloads need it, and then only through policy-checked routes.
