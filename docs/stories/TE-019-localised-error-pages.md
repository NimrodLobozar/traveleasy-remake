---
id: TE-019
title: Localised error pages (403, 404, 419, 429, 500, 503) with a request ID
status: done
priority: must
epic: M0 App shell
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, accessibility-auditor, security-privacy-reviewer]
design: approved:https://claude.ai/artifact/Ja7zyTDu9EznHuxPEu2dDD
rework_rounds: 0
---

## User story
As a visitor, I want a clear, friendly and translated page when something goes wrong, so that I know what to do next
and can quote a reference to the owner.

## Context
- `docs/plan.md` § 4 M0 App shell ("localised 403/404/419/429/500/503 pages") and § 7 Error handling (request ID, no stack
  traces or SQL in responses, `APP_DEBUG=false` in production). Legacy §3 Design/UX.
- Design: copy and layout are part of the TE-016 artifact (nl/en text for each status, next-step actions).
- **Depends on:** TE-009, TE-016. The JSON log with request ID is part of production logging (TE-012 handles log format).

## Acceptance criteria
- [x] AC1: Given a request to an unknown URL, when rendered, then HTTP status is 404 and the shell page shows an `<h1>` "Deze pagina
      bestaat niet" / "Page not found" (Dutch heading reworded at close to match the approved design; the design wins), a short explanation, a link home and a link to the search/trips placeholder if it
      exists; no duplicate landmarks.
- [x] AC2: Given each of 403, 419, 429, 500 and 503, when triggered (test routes registered only in the test environment), then
      the status code is preserved and the localised copy matches the design: 403 "Geen toegang" / "Access denied", 419
      "Sessie verlopen, probeer opnieuw" / "Session expired, please try again" (with a reload/continue action), 429 "Te veel
      verzoeken" / "Too many requests" (shows the `Retry-After` seconds when present), 500 "Er ging iets mis" / "Something went
      wrong", 503 "We zijn zo terug" / "We'll be right back".
- [x] AC3: Given any error page, when rendered, then it shows a request ID (the same value as the `X-Request-Id` response
      header and the log line) in a copyable text, e.g. "Referentie: 3f8a…" / "Reference: 3f8a…".
- [x] AC4: Given `APP_DEBUG=false` and an exception whose message contains a SQL statement or file path, when the 500 page
      renders, then neither the message, SQL, path nor stack trace appears in the HTML or headers.
- [x] AC5: Given a visitor who has chosen `en` (cookie) or dark theme, when an error page renders, then it is in that language
      and theme, including for 503 maintenance mode (`php artisan down` uses the same rendered template).
- [x] AC6: Given an Inertia (XHR) request that fails with 419 or 403, when the response arrives, then the client shows the
      same error page content (modal or full page per design) rather than a raw JSON or a blank screen.
- [x] AC7: Given the error pages, when scanned with axe in both themes at 320 px, then there are zero violations; the status is
      conveyed in text (not colour only), the title is "<Status> | TravelEasy", and focus moves to the `<h1>` on load.
- [x] AC8: Given a 500 error caused by SSR being unavailable, when the app falls back to client rendering, then it does not
      produce a 500 (covered in TE-007 AC4); a genuine render error renders the 500 page.
- [x] AC9: Given the security headers of TE-015, when an error page is served (including from the web server for 502/504
      upstream errors if the web server serves a static page per ADR-0002), then the headers are present.
      *Close note (2026-11-03):* the real `docker/prod/Caddyfile` validates, but FrankenPHP's classic `php_server` returns 500 on a
      PHP timeout and never produces 502/504, so the static page was proven only against a modified Caddyfile. When the `app`
      container itself is down, **NPM** serves its own 502 (see TE-026 AC14).
- [x] AC10 (moved from TE-015 AC3, 2026-11-02): Given a deterministic way to render the 500 page in tests (for example a
      testing-only route registered only when `APP_ENV` is `testing` or `local`, never in production), when an arch test runs,
      then it proves the route cannot exist in production.
- [x] AC11 (moved from TE-015 AC3): Given the e2e compose service, when it runs, then the `E2E_CSP_500_PATH` variable is passed
      through to it (devops), and the 500 check in `tests/e2e/csp.spec.ts` is un-skipped and shows zero CSP violations in
      Chromium, with and without SSR.

## Quality criteria
- [x] Tests: Pest feature test per status (code, localisation both locales, request ID, no leak); Vitest for the component;
      Playwright + axe per status in both themes; one test with `APP_DEBUG=true` in a dedicated environment is **not** run in prod
      config (assert the boot check from TE-012 instead).
- [x] Accessibility: WCAG 2.2 AA; heading structure; the request ID is selectable text and has no auto-copy side effects.
- [x] Security/privacy: request ID is random (not sequential, not derived from user/IP); no user data on the page; 429 does not
      reveal which limiter fired.
- [x] i18n: all strings nl + en.
- [x] Performance: error pages need no extra network requests beyond the app bundle.
- [x] Docs updated: `docs/design/`, `docs/deploy/` (maintenance mode and how the 503 page is served), `docs/testing.md`.

## Out of scope
- Error monitoring or alerting (none, plan § 12); per-feature validation errors (inline, each feature story).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-11-03 on branch `feat/m0-batch-5`.
- **Added:** localised 403/404/419/429/500/503 pages with a request ID, test-only error routes (arch test proves they cannot exist in
  production), `E2E_CSP_500_PATH` passthrough and the un-skipped CSP 500 check, static 502/504 page in `docker/prod/Caddyfile`,
  maintenance-mode docs (da1b714).
- **Changed:** 404 heading is "Deze pagina bestaat niet" (design wins). Stray `strict_types` line dropped from bootstrap (6be9874).
- **Removed:** nothing. **Migrations:** none.
- **New env vars:** `E2E_CSP_500_PATH` (dev/e2e only), optional `CI`. **Docker rebuild:** not needed.
- **Commits (backend):** 22ca5d0, e9c650a, 20d0fed, e63bca7; **devops:** 9a0065c, d718798, fac03b4, da1b714; frontend per-story commits.
- **Known limits:** the static 502/504 page was proven only against a modified Caddyfile (see AC9 note); NPM's own 502 when `app` is down
  goes to TE-026 AC14. Polish findings go to TE-030.
- **Testers:** qa-engineer PASS (Vitest 249, Pest 758, e2e 86 passed + 2 skipped, SSR e2e 88 passed + 0 skipped); accessibility-auditor PASS
  (one Minor to TE-030); security-privacy-reviewer PASS.
