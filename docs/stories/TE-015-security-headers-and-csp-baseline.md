---
id: TE-015
title: Security headers and CSP baseline (nonces, incl. the SSR theme script)
status: done
priority: must
epic: M0 App shell
agents: [backend-developer, frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As a visitor, I want the site to send strict browser security headers, so that injected scripts and framing attacks are
blocked.

## Context
- `docs/plan.md` § 4 M0 App shell ("CSP with nonces, HSTS-ready, frame-ancestors, Referrer-Policy, Permissions-Policy"),
  § 7. Principle 3 (secure by default; CSP). No runtime CDNs or third-party fonts (CLAUDE.md).
- Board "Follow-ups for M1": "Appearance cookie and CSP" item. The CSP nonce part for the inline theme script is **moved
  here** (the cookie allow-list is in TE-018).
- **Depends on:** TE-007. **Blocks:** TE-018 (theme script uses the nonce), TE-016.
- Under SSR the nonce must be on the server-rendered `<script>`/`<style>` tags and match the header.

## Acceptance criteria
- [x] AC1: Given any HTML response, when its headers are read, then `Content-Security-Policy` is present with
      `default-src 'self'`, `script-src 'self' 'nonce-<random>'`, `style-src 'self' 'nonce-<random>'`, `img-src 'self' data:`,
      `font-src 'self'`, `connect-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
      `frame-ancestors 'none'`; no `unsafe-inline` or `unsafe-eval` for scripts.
- [x] AC2: Given two requests, when the nonces are compared, then they differ (per request, 128-bit random), and every
      inline `<script>` and `<style>` in the HTML (including the theme script and Vite tags) carries the matching nonce.
- [x] AC3: Given the page is opened in Chromium via Playwright, when it loads and hydrates under SSR, then there are zero CSP
      violation reports in the console on `/`, a 404 page and a 500 page.
      **Clarification (orchestrator decision, 2026-11-02):** the Chromium zero-violation check of a **500 page** moves to
      TE-019 (localised error pages). AC3 is met by (a) zero CSP violations on `/`, `/login` and a 404 in Chromium, both with
      SSR and without, and (b) the Pest coverage of the security headers on 403/404/419/429/500/503
      (`tests/Feature/Security/SecurityHeadersTest.php`).
- [x] AC4: Given the response headers, then `Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options:
      nosniff`, `Permissions-Policy` denying camera, microphone, geolocation, payment and usb by default, and
      `Cross-Origin-Opener-Policy: same-origin` are present.
- [x] AC5: Given `APP_URL` starts with `https://` (production), when a response is sent, then `Strict-Transport-Security:
      max-age=15552000` is added (no `preload`, no `includeSubDomains` by default; both are env switches); given `http://`
      (dev/LAN), then HSTS is not sent.
- [x] AC6: Given the dev environment (Vite HMR), when `APP_ENV=local`, then the CSP additionally allows the Vite dev server
      origin (`http://localhost:5173`, `ws://localhost:5173`) and nothing else; production never includes it (Pest test).
- [x] AC7: Given a CSP violation is reported, when `report-uri`/`report-to` is configured, then reports are not sent anywhere
      external; the default is no reporting endpoint (documented).
- [x] AC8: Given static assets and error responses (403/404/419/429/500/503), when requested, then the same security headers
      are present (middleware applies to all web responses, including exceptions).
- [x] AC9: Given the arch tests, when a Blade view or React component adds an inline event handler attribute or
      `dangerouslySetInnerHTML` with non-constant input, then the arch/lint rule fails.

## Quality criteria
- [x] Tests: Pest feature tests for AC1, AC2, AC4, AC5, AC6, AC8 (HTTPS via `APP_URL` config); Playwright test for AC3;
      a unit test of the header builder.
- [x] Accessibility: n/a (no visible change); confirm CSP does not break assistive features (no extension reliance).
- [x] Security/privacy: no external origins in any directive; no `Server`/`X-Powered-By` version leak (`expose_php` off,
      header removed at the web server); the security reviewer scans with an online-grade checker offline (e.g. a local
      header lint) and records the result.
- [x] i18n: n/a.
- [x] Performance: header middleware adds under 1 ms; nonce is generated once per request.
- [x] Docs updated: `docs/deploy/` (header list, how to raise HSTS), `docs/testing.md`, `.env.example` (HSTS switches).

## Out of scope
- Trusted hosts (stays in M1 follow-ups), report-uri collection, subresource integrity.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Done 2026-11-02. Testers: qa-engineer PASS, security-privacy-reviewer PASS, accessibility-auditor PASS.
- **Added:** per-request CSP nonce and security headers (CSP, Referrer-Policy, nosniff, Permissions-Policy, COOP, optional HSTS)
  on all web responses including 403/404/419/429/500/503; Vite dev origin allowed only in `APP_ENV=local`; no report endpoint by
  default; lint/arch rule against inline handlers and non-constant `dangerouslySetInnerHTML`; Pest and Playwright coverage.
- **Changed:** AC3 clarified (500-page browser check moved to TE-019, see AC3 note).
- **Removed:** nothing.
- **Migrations:** none.
- **New env vars:** `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD`, `CSP_REPORT_PATH` (in `.env.example`; also go
  into the Portainer env file, see TE-025).
- **Docker rebuild:** not needed beyond the normal env refresh (`docker compose up -d`).
- **Commits** on `feat/m0-ssr-i18n-csp`: ca55122, 80e1278, 7999e73, e98f363, 11b35fc, 5d05f3d, 689fee1, 6ce21ea, f9d7e25,
  12a8b6e, plus QA's lint fix (shared with TE-008 and TE-009).
- **Follow-ups:** TE-012, TE-019, TE-025 (criteria added), README Devops items.
