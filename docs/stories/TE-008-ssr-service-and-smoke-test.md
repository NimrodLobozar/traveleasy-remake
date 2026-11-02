---
id: TE-008
title: SSR service in compose (dev profile) and an SSR smoke test
status: ready
priority: must
epic: M0 Server-side rendering
agents: [devops-engineer, qa-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer, I want to run the app against a real SSR build with one command and have a repeatable smoke test, so
that SSR regressions are caught before an image is published.

## Context
- `docs/plan.md` § 4 M0 (optional `ssr` profile in `compose.dev.yaml`; "Playwright suite runs against the SSR build";
  failure mode). The production `ssr` service is TE-024.
- **Depends on:** TE-007. **Blocks:** TE-013 (CI runs the smoke test), TE-009/TE-018 (SSR tests).
- Compose rules: project name `traveleasy-remake`, ports on 127.0.0.1 only (TE-002), e2e container hardened (TE-005).

## Acceptance criteria
- [ ] AC1: Given `docker compose --profile ssr up -d`, when it settles, then an `ssr` service (Node, same image) is
      healthy, and `app` renders through it; without the profile nothing changes in the normal dev flow (HMR).
- [ ] AC2: Given the `ssr` profile, when `docker compose ps` is read, then `ssr` publishes no host port.
- [ ] AC3: Given a documented command (e.g. `docker compose run --rm e2e npm run test:ssr`), when it runs against the
      SSR build, then it fails if the HTML of `/` lacks server-rendered content (a known heading text) or if `<html lang>`
      is missing, and passes otherwise. The same script is reusable by CI (TE-013) without Docker Compose specifics.
- [ ] AC4: Given the `ssr` container is stopped, when the smoke test's fallback check runs, then `/` still returns 200 and
      the test reports "SSR down: client-side fallback OK".
- [ ] AC5: Given a page that uses `window` during render is added in a scratch branch, when the smoke test runs, then it
      fails with a readable message (proves the test catches what AC5 of TE-007 intends).
- [ ] AC6: Given the Playwright suite, when run with the SSR profile up, then the existing smoke and axe specs pass
      against the SSR-rendered pages.
- [ ] AC7 (follow-up TE-007): Given the production-style build and the `ssr` service up (`INERTIA_SSR_URL=http://ssr:13714` on
      `app`, `INERTIA_SSR_HOST=0.0.0.0` only on `ssr`, no published port, `ssr` never on `npm_shared`), when
      `tests/e2e/console.spec.ts` runs, then the hydration test runs (is not skipped) and passes with zero `console.error`.
- [ ] AC8 (follow-up TE-007): Given CI, when the smoke test runs, then it covers both SSR on (rendered content in the HTML) and
      SSR down (`/` returns 200 via client-side fallback).
- [ ] AC9 (security Low, TE-007): Given the Node SSR server hits a render error, when its stdout is read, then it contains no
      request URL, message or stack from `@inertiajs/core` `createServer`; the output is filtered in `resources/js/ssr.tsx`
      without `formatErrors: false` (that crashes on unhandled rejections). A test proves both the scrubbing and that an
      unhandled rejection does not crash the server.
- [ ] AC10 (security Low, TE-007): Given `tsconfig` `"types": ["node"]` makes `process.env` typecheck everywhere, when
      `npm run check` runs, then `process` in `resources/js/**` (except `ssr.tsx`) fails via a lint rule with a fixture, or the
      Node types are limited to `ssr.tsx`; either way a component cannot render `APP_KEY` into HTML.

## Quality criteria
- [ ] Tests: the SSR smoke test itself plus the fallback check; documented in `docs/testing.md`.
- [ ] Accessibility: n/a (no UI change).
- [ ] Security/privacy: `ssr` container hardened like the app (non-root, `cap_drop: [ALL]`, `no-new-privileges`,
      read-only rootfs with tmpfs where needed); it has no DB access and no secrets in its environment.
- [ ] i18n: none.
- [ ] Performance: the `ssr` service has a memory limit set (default 256 MB) and the value is documented.
- [ ] Docs updated: `docs/deploy/dev.md` (profile, command), `docs/testing.md`, `CLAUDE.md` commands table.

## Out of scope
- Production `ssr` service and limits (TE-024).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
