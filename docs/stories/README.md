# Story board

Maintained by the `task-writer`; the orchestrator updates the status during the flow.
Statuses: `draft` → `ready` → `in-progress` → `in-test` → (`rework` → `in-progress` …) → `done`.
Priority (MoSCoW): `must` · `should` · `could` · `nice-to-have`.

| ID | Title | Priority | Status | Rework rounds | Design |
|---|---|---|---|---|---|
| [TE-001](TE-001-scaffold-laravel-react-starter-kit.md) | Scaffold the Laravel React starter kit (strict TS, PostgreSQL, no mail) | must | done | 0 | not-needed |
| [TE-002](TE-002-docker-dev-environment.md) | Docker dev environment with hot reload and a PostgreSQL named volume | must | rework | 1 | not-needed |
| [TE-003](TE-003-code-quality-tooling.md) | Code quality tooling (Pint, Larastan, ESLint, Prettier, tsc) | must | ready | 0 | not-needed |
| [TE-004](TE-004-pest-arch-tests.md) | Pest on PostgreSQL with arch tests that forbid the legacy anti-patterns | must | ready | 0 | not-needed |
| [TE-005](TE-005-vitest-playwright-axe-harness.md) | Frontend test harness (Vitest + Testing Library, Playwright + axe) | must | ready | 0 | not-needed |
| [TE-006](TE-006-dev-and-testing-docs.md) | Developer docs (dev.md, testing.md) and exact stack versions in CLAUDE.md | must | ready | 0 | not-needed |

### Epics
- **M0 Project scaffold and tooling:** TE-001, TE-003, TE-004, TE-005 (docs part in TE-006).
- **M0 Docker dev environment:** TE-002, TE-006.
- Not yet written (later M0 epics): Server-side rendering, CI/CD to GHCR (incl. ADR-0002), App shell, Design system,
  i18n infrastructure, First home-server deploy.

### Notes for the production / deploy epics
Filed from the TE-002 security review (round 1, Low findings, 2026-10-11). Turn them into criteria when the CI/CD
and First home-server deploy stories are written.

- **Database role:** the app connects with a non-superuser DB role. The superuser is only used for initialisation.
- **Production image stage** (separate from dev):
  - no dev ini, `display_errors=Off`, `opcache.validate_timestamps=0`;
  - `/var/www/html` 0755, owned by the app user;
  - `cap_drop: [ALL]`, `no-new-privileges`, read-only root filesystem;
  - images pinned by digest;
  - a `HEALTHCHECK`.
- **Production config:** `APP_DEBUG=false`, `INERTIA_DEVTOOLS_ENABLED=false`, `SESSION_SECURE_COOKIE=true`,
  TrustProxies limited to NPM.
- **CI guards:**
  - `compose.yaml` has no `adminer` service, no `ports:` on `db` and no initdb mount;
  - image scan;
  - `composer audit` / `npm audit`;
  - `docker compose config --quiet`.
- **Migrate-on-start:** make a deliberate decision (ADR-0002 or the deploy story), including rollback compatibility.
- **Outbound egress:** allow it for the HIBP range API, or confirm the M1 flag fails open.
- **Env documentation:** `.env.example` and the Portainer env list include `SESSION_SECURE_COOKIE`,
  `PASSKEYS_USER_HANDLE_SECRET` and `INERTIA_DEVTOOLS_ENABLED`.

## Follow-ups for M1
Found while TE-001 was tested (2026-10-11; security-privacy-reviewer and qa-engineer). There are no M1 story files
yet. When M1 stories are written, put each item below into the matching story as a criterion, then remove it here.

- **Breached-password check:** keep HIBP `Password::uncompromised()`, behind a config flag in
  `config/traveleasy.php` with a short HTTP timeout. If the API is unreachable, registration must not hang.
  (This is an external call, so the reviewer checks it against the "no personal data to external services" rule:
  only the k-anonymity hash prefix is sent.)
- **Sessions:** `SESSION_ENCRYPT=true` everywhere; `SESSION_SECURE_COOKIE=true` in production.
- **Appearance cookie and CSP:**
  - validate the `appearance` cookie against an allowlist (`light`, `dark`, `system`);
  - give the inline theme script and style a CSP nonce via `Vite::useCspNonce` (ties in with the App shell CSP story).
- **Trusted proxies:** limited to Nginx Proxy Manager (its address or network), never `*`.
- **Trusted hosts** (from the TE-002 security review): `$middleware->trustHosts()` derived from the `APP_URL` host.
  Today DNS rebinding can reach `/_inertia/devtools/*` on `127.0.0.1:8000` while `APP_ENV=local`.
- **Rate limits:** per-account login throttling across IPs, plus limits on register, confirm-password and
  delete-account.
- **Passkeys (owner decides in M1):**
  - assumed **off** until `laravel/passkeys` 1.0, because a passkey login skips TOTP, which breaks mandatory 2FA for
    manager, admin and super_admin;
  - if they stay, use a separate `PASSKEYS_USER_HANDLE_SECRET`.
- **Case-insensitive unique e-mail on PostgreSQL:** a unique index on `lower(email)` (or `citext`), and lowercase
  the address in the register and `ProfileUpdateRequest` validation.
- **Seeders:** split into `reference` and `demo`. The kit's `DatabaseSeeder` test user (`test@example.com` /
  `password`) must never run in production. Add a test for it.
- **Inertia history:** `INERTIA_ENCRYPT_HISTORY=true`, and `clearHistory()` on logout.
- **Security-event logging:** feeds the M1 audit-log story.
- **Clean-up:**
  - remove the `password_reset_tokens` broker from `config/auth.php`;
  - remove the unused `status` prop in `ProfileController::edit`;
  - set the `filesystems` local disk to `serve => false` until a story needs temporary URLs.
