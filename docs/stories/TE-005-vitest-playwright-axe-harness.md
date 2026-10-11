---
id: TE-005
title: Frontend test harness (Vitest + Testing Library, Playwright + axe)
status: ready
priority: must
epic: M0 Project scaffold and tooling
agents: [frontend-developer, devops-engineer]
testers: [qa-engineer, security-privacy-reviewer, accessibility-auditor]
design: not-needed
rework_rounds: 0
---

## User story
As the developer, I want component tests and browser tests with an automatic accessibility scan that run in containers
with one command, so that every UI story from now on can prove its behaviour and its WCAG basics.

## Context
- `docs/plan.md` § 4 M0 scope (Vitest + Testing Library, Playwright + @axe-core/playwright), § 6 Testing and WCAG
  tracks. The design-system epic will rely on "Vitest + axe" tests for each base component.
- **Depends on:** TE-002 (containers), TE-003 (ESLint config must lint the new test files). Can overlap with TE-004.
- **Testers.** The accessibility-auditor reviews only the axe configuration (rule tags, how violations are reported,
  the allow-list rule in AC8). No designed UI changes in this story, so there is no page audit. The
  security-privacy-reviewer is included because new dependencies and a new container image are added.
- **Starter-kit pages are placeholders.** If axe finds violations on them, they are not fixed here (see AC8).
- **Known pitfall.** In dev, the page loads its JS from `http://localhost:5173` (Vite). Inside a Playwright container,
  `localhost` is the container itself, so the JS won't load and tests would pass on bare HTML. Pick a solution (e.g.
  share the network namespace with the `vite`/`app` service, or run e2e against `npm run build` output with
  `public/hot` absent) and state it in the handoff. AC6 checks for it.
- Running Playwright against the SSR build and in CI is the CI and SSR epics' job.

## Acceptance criteria
- [ ] AC1 (Vitest): `docker compose exec vite npm run test` runs Vitest once (`vitest run`) in a jsdom (or happy-dom)
      environment, with `@testing-library/react`, `@testing-library/user-event` and `@testing-library/jest-dom`
      matchers set up globally and the `@/` path alias resolved the same way as in Vite. `npm run test:watch` runs
      watch mode (with polling on Windows).
- [ ] AC2 (React Aria proof): a Vitest test renders a `react-aria-components` `Button` with an `onPress` handler, focuses
      it with `userEvent.tab()`, presses Enter and then Space, and asserts the handler ran twice.
- [ ] AC3 (component axe in Vitest): an axe matcher (e.g. `vitest-axe`) is set up; a test proves it passes on
      accessible markup (a labelled input) and fails on a fixture with a known violation (an `<img>` without `alt`)
      (the failing case is asserted with `expect(...).rejects` or equivalent, so the suite stays green).
- [ ] AC4 (Playwright runs in a container): `docker compose run --rm e2e npx playwright test` (a service under an `e2e`
      compose profile in `compose.dev.yaml`, or an equivalent single command stated in the handoff) runs the e2e suite
      in Chromium. The image is the official Playwright image whose version **equals** the `@playwright/test` version
      in `package-lock.json`, and the handoff says how to keep them in step.
- [ ] AC5 (shared axe helper): `tests/e2e` has one helper (e.g. `expectNoA11yViolations(page, { allow })`) that runs
      `@axe-core/playwright` with the tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`, and on failure prints
      each violation's rule id, impact, help URL and target selectors.
- [ ] AC6 (smoke journey, JS really loaded): a smoke test opens `/` and `/login` on the running dev app, asserts status
      200, asserts an element that only exists after React has rendered (e.g. the login form's email field found by its
      label with `getByLabel`), and runs the axe helper on both pages.
- [ ] AC7 (mail-free check): the smoke test asserts that `GET /forgot-password` returns 404 (guards TE-001 AC12).
- [ ] AC8 (allow-list for placeholders): if axe finds violations on starter-kit placeholder pages, they may only be
      skipped through the helper's explicit `allow` list, with one entry per rule id **and** page, each with a comment
      "starter-kit placeholder, remove in <epic>". The entries are listed in the handoff. A violation not on the list
      fails the test. (The App shell epic must end with an empty list.)
- [ ] AC9 (helper proven): a Playwright test loads a static fixture page with a known violation and asserts that the
      helper reports it, so a broken axe setup can't pass silently.
- [ ] AC10 (reports): Playwright writes its HTML report to `playwright-report/` and traces/screenshots on failure to
      `test-results/` in the bind-mounted repo, so they can be opened on Windows. Both folders are already in
      `.gitignore`.

## Quality criteria
- [ ] Tests: QA runs `npm run test` and the e2e command twice each on Windows; both runs pass with identical results.
      QA breaks AC2 and AC6 on purpose once to confirm they can fail.
- [ ] Accessibility: the accessibility-auditor confirms the axe tags, the reporting format and the allow-list rule
      (AC5, AC8). No page audit.
- [ ] Security/privacy: test-only dependencies in `devDependencies`; the Playwright image is official and pinned;
      `npm audit --audit-level=high` shows no new High/Critical; the e2e container has no access to `.env` secrets it
      doesn't need (it only needs the base URL).
- [ ] i18n: not applicable (no UI strings added).
- [ ] Performance: Vitest suite under 30 s and the e2e smoke under 2 minutes after the image is pulled (reported).
- [ ] Docs updated: the handoff lists the commands, the localhost solution and the allow-list for TE-006.
- [ ] Docker rebuild: yes, `docker compose up -d --build -V` after the `package.json` changes; the first e2e run pulls
      the Playwright image.

## Out of scope
- Playwright against the SSR build or the production image, Firefox/WebKit projects, Lighthouse (CI, SSR and later
  milestones).
- Fixing accessibility issues in starter-kit placeholder pages (App shell / design-system epics).
- Visual regression testing.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
