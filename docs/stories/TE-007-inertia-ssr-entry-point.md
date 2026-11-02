---
id: TE-007
title: Inertia SSR entry point, SSR build and SSR-safe code rules
status: in-test
priority: must
epic: M0 Server-side rendering
agents: [frontend-developer, backend-developer, devops-engineer]
testers: [qa-engineer]
design: not-needed
rework_rounds: 0
---

## User story
As a visitor (and a search engine), I want every page to arrive as server-rendered HTML, so that the first paint is
fast and the content is indexable; as a developer, I want rules that stop browser-only code from breaking SSR.

## Context
- `docs/plan.md` § 4 M0 "Inertia SSR (Q15)" and "SSR impact"; Decisions in `CLAUDE.md` (SSR on from M0).
- **Depends on:** TE-001 to TE-006 (done). **Blocks:** TE-008, TE-009, TE-018 (theme), TE-012.
- Dev keeps client-side Vite HMR; SSR is checked against a build (TE-008). If the SSR process is down, Inertia falls back
  to client-side rendering and the site stays usable.

## Acceptance criteria
- [ ] AC1: Given the repo, when `npm run build` runs in the `vite` container, then it produces both the client bundle and
      the SSR bundle (`vite build --ssr`, entry `resources/js/ssr.tsx`), and `npm run build` fails if either fails.
- [ ] AC2: Given the SSR server is running (`php artisan inertia:start-ssr`) against that build, when `GET /` is
      requested without JavaScript, then the HTML body contains the rendered page content (not only
      `<div id="app" data-page=...>`), and `<html lang>` is `nl`.
- [ ] AC3: Given the page is hydrated in a browser, when the page loads, then there is no React hydration warning in the
      console (Playwright asserts zero `console.error` on `/`).
- [ ] AC4: Given the SSR process is stopped, when `GET /` is requested, then the page still renders client-side with HTTP
      200 (fallback), and no stack trace or SSR error text appears in the response.
- [ ] AC5 (SSR-safe rule): Given a component uses `window`, `document`, `localStorage`, `sessionStorage` or
      `navigator` at module level or during render, when `npm run check` runs, then oxlint fails with a message that
      says to move it into an effect. The rule lives in `lint.rules.ts`; a fixture proves it fails, and usage inside
      `useEffect` or an event handler passes.
- [ ] AC6: Given the existing starter-kit code, when AC5 is enabled, then all current violations are fixed (not
      suppressed); any remaining `oxlint-disable` for this rule carries a one-line reason.
- [ ] AC7: Given the SSR config, when the SSR server starts, then it listens only on the compose-internal network
      (bind address and port from `config/inertia.php` / env), never on a host port.

## Quality criteria
- [ ] Tests: a Vitest test renders the app root with `renderToString` and finds expected text; the lint-rule fixture
      test (AC5); Pest feature test that the Inertia SSR config points at the configured URL.
- [ ] Accessibility: no UI change; `<html lang>` is server-rendered (feeds TE-016).
- [ ] Security/privacy: SSR receives only the Inertia page object that the browser would get anyway (no extra props);
      the SSR port is not published to the host.
- [ ] i18n: none new (locale handled in TE-009).
- [ ] Performance: SSR adds no more than 50 ms to the median response of `/` on the dev machine (record the number).
- [ ] Docs updated: `docs/deploy/dev.md` (build and SSR commands), `docs/testing.md` (SSR-safety rule), `CLAUDE.md`
      commands table if a command is added.

## Out of scope
- The `ssr` compose service and CI smoke test (TE-008). Theme and locale resolution (TE-009, TE-018).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
