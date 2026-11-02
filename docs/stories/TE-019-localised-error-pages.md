---
id: TE-019
title: Localised error pages (403, 404, 419, 429, 500, 503) with a request ID
status: ready
priority: must
epic: M0 App shell
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, accessibility-auditor, security-privacy-reviewer]
design: required
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
- [ ] AC1: Given a request to an unknown URL, when rendered, then HTTP status is 404 and the shell page shows an `<h1>` "Pagina
      niet gevonden" / "Page not found", a short explanation, a link home and a link to the search/trips placeholder if it
      exists; no duplicate landmarks.
- [ ] AC2: Given each of 403, 419, 429, 500 and 503, when triggered (test routes registered only in the test environment), then
      the status code is preserved and the localised copy matches the design: 403 "Geen toegang" / "Access denied", 419
      "Sessie verlopen, probeer opnieuw" / "Session expired, please try again" (with a reload/continue action), 429 "Te veel
      verzoeken" / "Too many requests" (shows the `Retry-After` seconds when present), 500 "Er ging iets mis" / "Something went
      wrong", 503 "We zijn zo terug" / "We'll be right back".
- [ ] AC3: Given any error page, when rendered, then it shows a request ID (the same value as the `X-Request-Id` response
      header and the log line) in a copyable text, e.g. "Referentie: 3f8a…" / "Reference: 3f8a…".
- [ ] AC4: Given `APP_DEBUG=false` and an exception whose message contains a SQL statement or file path, when the 500 page
      renders, then neither the message, SQL, path nor stack trace appears in the HTML or headers.
- [ ] AC5: Given a visitor who has chosen `en` (cookie) or dark theme, when an error page renders, then it is in that language
      and theme, including for 503 maintenance mode (`php artisan down` uses the same rendered template).
- [ ] AC6: Given an Inertia (XHR) request that fails with 419 or 403, when the response arrives, then the client shows the
      same error page content (modal or full page per design) rather than a raw JSON or a blank screen.
- [ ] AC7: Given the error pages, when scanned with axe in both themes at 320 px, then there are zero violations; the status is
      conveyed in text (not colour only), the title is "<Status> | TravelEasy", and focus moves to the `<h1>` on load.
- [ ] AC8: Given a 500 error caused by SSR being unavailable, when the app falls back to client rendering, then it does not
      produce a 500 (covered in TE-007 AC4); a genuine render error renders the 500 page.
- [ ] AC9: Given the security headers of TE-015, when an error page is served (including from the web server for 502/504
      upstream errors if the web server serves a static page per ADR-0002), then the headers are present.

## Quality criteria
- [ ] Tests: Pest feature test per status (code, localisation both locales, request ID, no leak); Vitest for the component;
      Playwright + axe per status in both themes; one test with `APP_DEBUG=true` in a dedicated environment is **not** run in prod
      config (assert the boot check from TE-012 instead).
- [ ] Accessibility: WCAG 2.2 AA; heading structure; the request ID is selectable text and has no auto-copy side effects.
- [ ] Security/privacy: request ID is random (not sequential, not derived from user/IP); no user data on the page; 429 does not
      reveal which limiter fired.
- [ ] i18n: all strings nl + en.
- [ ] Performance: error pages need no extra network requests beyond the app bundle.
- [ ] Docs updated: `docs/design/`, `docs/deploy/` (maintenance mode and how the 503 page is served), `docs/testing.md`.

## Out of scope
- Error monitoring or alerting (none, plan § 12); per-feature validation errors (inline, each feature story).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
