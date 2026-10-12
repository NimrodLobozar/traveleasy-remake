---
id: TE-005
title: Frontend test harness (Vitest + Testing Library, Playwright + axe)
status: done
priority: must
epic: M0 Project scaffold and tooling
agents: [frontend-developer, devops-engineer]
testers: [qa-engineer, security-privacy-reviewer, accessibility-auditor]
design: not-needed
rework_rounds: 1
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
- [x] AC1 (Vitest): `docker compose exec vite npm run test` runs Vitest once (`vitest run`) in a jsdom (or happy-dom)
      environment, with `@testing-library/react`, `@testing-library/user-event` and `@testing-library/jest-dom`
      matchers set up globally and the `@/` path alias resolved the same way as in Vite. `npm run test:watch` runs
      watch mode (with polling on Windows).
- [x] AC2 (React Aria proof): a Vitest test renders a `react-aria-components` `Button` with an `onPress` handler, focuses
      it with `userEvent.tab()`, presses Enter and then Space, and asserts the handler ran twice.
- [x] AC3 (component axe in Vitest): an axe matcher (e.g. `vitest-axe`) is set up; a test proves it passes on
      accessible markup (a labelled input) and fails on a fixture with a known violation (an `<img>` without `alt`)
      (the failing case is asserted with `expect(...).rejects` or equivalent, so the suite stays green).
      *Clarified in rework round 1 (accessibility F1, Serious):* jsdom has no layout, so the matcher must not imply
      checks it can't do.
      - **(a) Disabled rules.** The jsdom matcher explicitly disables `color-contrast`, `target-size` and
        `scrollable-region-focusable`. They must not run in jsdom, because they give false passes or are inapplicable
        there. Those rules are covered in Playwright (real Chromium) by the design-system and App shell epics.
      - **(b) Accurate comment.** The comment in `resources/js/test/axe.ts` names exactly what the jsdom matcher does
        **not** check: the disabled rules above and any other layout-dependent check. It may not claim that
        layout-dependent rules "come back incomplete".
      - **(c) Incomplete results are visible.**
        - The matcher fails on **incomplete** results for rules that can be judged without layout, at least
          `aria-hidden-focus`.
        - For every other incomplete result, it lists the rule ids in the failure output, or in a console note when
          the test passes.
        - A test proves that a focusable `<button>` inside an `aria-hidden="true"` container makes the matcher fail.
      - **(d) Message detail (F2).** The failure message includes, per node, axe's `failureSummary` and a shortened
        `html` snippet (at most ~200 characters). This is the same format as AC5.
- [x] AC4 (Playwright runs in a container): `docker compose run --rm e2e npx playwright test` (a service under an `e2e`
      compose profile in `compose.dev.yaml`, or an equivalent single command stated in the handoff) runs the e2e suite
      in Chromium. The image is the official Playwright image whose version **equals** the `@playwright/test` version
      in `package-lock.json`, and the handoff says how to keep them in step.
- [x] AC5 (shared axe helper): `tests/e2e` has one helper (e.g. `expectNoA11yViolations(page, { allow })`) that runs
      `@axe-core/playwright` with the tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`, and on failure prints
      each violation's rule id, impact, help URL and target selectors.
      *Clarified in rework round 1:*
      - **(a) Message detail (F2).** Per node, the failure output also includes axe's `failureSummary` (e.g. the
        measured contrast ratio and the foreground and background colours) and a shortened `html` snippet (at most ~200
        characters). The shared formatter in `resources/js/test/axe-config.ts` serves both the Vitest and the e2e
        helper.
      - **(b) Needs-review results (F3).** Incomplete results are never dropped silently. The helper attaches them to
        the test as an attachment (e.g. `a11y-needs-review.txt`, via `testInfo.attach`) or as an annotation, with rule
        id, page URL, selectors and `failureSummary`. They don't fail the test. A test proves that the attachment
        appears when a fixture produces an incomplete result.
- [x] AC6 (smoke journey, JS really loaded): a smoke test opens `/` and `/login` on the running dev app, asserts status
      200, asserts an element that only exists after React has rendered (e.g. the login form's email field found by its
      label with `getByLabel`), and runs the axe helper on both pages.
- [x] AC7 (mail-free check): the smoke test asserts that `GET /forgot-password` returns 404 (guards TE-001 AC12).
- [x] AC8 (allow-list for placeholders): if axe finds violations on starter-kit placeholder pages, they may only be
      skipped through the helper's explicit `allow` list, with one entry per rule id **and** page, each with a comment
      "starter-kit placeholder, remove in <epic>". The entries are listed in the handoff. A violation not on the list
      fails the test. (The App shell epic must end with an empty list.)
      *Clarified in rework round 1:*
      - **(a) One central list (F5).** The central list (`placeholderAllowList`) is the **only** way to allow
        anything. A lint rule or an arch/file-scan test fails if any file under `tests/e2e/` passes `allow:` with
        anything other than `placeholderAllowList`. The single exception is `axe-helper.spec.ts`, which proves the
        mechanism.
      - **(b) No stale entries (F4).** A spec visits **every** page named in `placeholderAllowList` and runs the
        helper on it. It fails if an entry's page doesn't exist or no longer produces that rule's violation. This
        keeps the list from silently outliving its reason.
      - **(c) No empty reason (F6).** The reason must name a real epic, e.g. "starter-kit placeholder, remove in App
        shell". A type (union of epic names) or a runtime check rejects an empty or whitespace-only epic name, and a
        test proves the check.
- [x] AC11 (e2e container write access; security L1, added in rework round 1):
  - **Mounts.** The `e2e` service mounts the source **read-only** (e.g. `volumes_from: ['vite:ro']`). The only
    writable bind mounts are `playwright-report/` and `test-results/`, so the container can't write to the repo or
    `.git`.
  - **Contract test.** The compose contract test (`tests/Unit/DockerDevEnvironmentContractTest.php`) asserts this.
  - **Proof.** QA confirms that `touch /var/www/html/.git/hooks/x` (or the mounted repo path) fails inside the e2e
    container.
- [x] AC12 (secret masking in the e2e container; security L2, added in rework round 1):
  - **`bootstrap/cache`.** The `e2e` service mounts a `tmpfs` over `bootstrap/cache`.
  - **`.env*` files.** Every `.env*` file in the repo root except `.env.example` is masked in the container.
  - **Contract test.** A test fails if any such file present in the repo root lacks a mask on the `e2e` service.
- [x] AC9 (helper proven): a Playwright test loads a static fixture page with a known violation and asserts that the
      helper reports it, so a broken axe setup can't pass silently.
- [x] AC10 (reports): Playwright writes its HTML report to `playwright-report/` and traces/screenshots on failure to
      `test-results/` in the bind-mounted repo, so they can be opened on Windows. Both folders are already in
      `.gitignore`.

## Quality criteria
- [x] Tests: QA runs `npm run test` and the e2e command twice each on Windows; both runs pass with identical results.
      QA breaks AC2 and AC6 on purpose once to confirm they can fail.
- [x] Accessibility: the accessibility-auditor confirms the axe tags, the reporting format and the allow-list rule
      (AC3, AC5, AC8, including F1–F6 from rework round 1). No page audit.
- [x] Security/privacy: test-only dependencies in `devDependencies`; the Playwright image is official and pinned;
      `npm audit --audit-level=high` shows no new High/Critical; the e2e container has no access to `.env` secrets it
      doesn't need (it only needs the base URL).
- [x] i18n: not applicable (no UI strings added).
- [x] Performance: Vitest suite under 30 s and the e2e smoke under 2 minutes after the image is pulled (reported).
- [x] Docs updated: the handoff lists the commands, the localhost solution and the allow-list for TE-006.
- [x] Docker rebuild: yes, `docker compose up -d --build -V` after the `package.json` changes; the first e2e run pulls
      the Playwright image.

## Out of scope
- Playwright against the SSR build or the production image, Firefox/WebKit projects, Lighthouse (CI, SSR and later
  milestones).
- Fixing accessibility issues in starter-kit placeholder pages (App shell / design-system epics).
- Visual regression testing.

## Open questions
- (none)

## Rework log

### Round 1 (2026-10-12)
- **Verdicts:**
  - qa-engineer: PASS. All 10 ACs pass: Vitest 5/5 twice, e2e 6/6 twice. Deliberate breaks of AC2, AC3, AC6 and AC8
    failed as expected, and the version-sync test was proven to fail.
  - security-privacy-reviewer: PASS, with Lows L1–L3.
  - **accessibility-auditor: FAIL.**
- **Failed criterion:** AC3 (F1, Serious). This also undermines the reliability of the whole Vitest + axe setup that
  the design-system epic depends on.
- **Evidence:** `resources/js/test/axe.ts:11-13` claims that layout-dependent rules come back "incomplete, not as
  passes". A probe shows that this is false in jsdom:
  - `target-size` **passes** on 10x10 px adjacent buttons (a false pass);
  - `aria-hidden-focus` is only incomplete, so a focusable button inside `aria-hidden` goes unreported (a WCAG 4.1.2
    failure);
  - `scrollable-region-focusable` is inapplicable;
  - `color-contrast` is incomplete.

  The matcher fails only on violations and never surfaces incomplete results. In real Chromium all four are
  violations.
- **Cause:** jsdom has no layout engine, and the matcher neither disables the rules that need layout nor reports
  incomplete results.
- **Expected fix (frontend-developer):**
  1. Disable `color-contrast`, `target-size` and `scrollable-region-focusable` in the jsdom matcher.
  2. Rewrite the comment to name exactly what the matcher doesn't check.
  3. Fail on incomplete results for rules that can be judged without layout (at least `aria-hidden-focus`), and list
     the other incomplete rule ids.

  Also in this round (Moderate/Minor, re-checked by the auditor):
  - **F2:** `failureSummary` and a shortened `html` snippet per node in both helpers
    (`resources/js/test/axe-config.ts:19-24, 37-48`).
  - **F3:** attach e2e needs-review results instead of dropping them (`tests/e2e/axe.ts:49-58`).
  - **F4:** detect stale allow-list entries.
  - **F5:** block inline `allow` arrays outside `axe-helper.spec.ts`.
  - **F6:** reject an empty epic name in the allow reason.

  Security Lows for the devops-engineer:
  - **L1:** read-only source in the e2e container; writable only `playwright-report/` and `test-results/`.
  - **L2:** `tmpfs` on `bootstrap/cache` and a mask for every root `.env*` except `.env.example`, both with contract
    tests.
- **Criteria clarified/added:**
  - AC3 (a)–(d): disabled rules, accurate comment, incomplete results visible, message detail.
  - AC5 (a)–(b): `failureSummary`/html per node, needs-review attachment.
  - AC8 (a)–(c): one central list, no stale entries, no empty reason.
  - New AC11 (L1) and AC12 (L2).
- **Follow-ups filed elsewhere (not part of this rework):**
  - L3, on the board under "Notes for the CI/CD epic".
  - The e2e data rule, on the board under "Follow-ups for M1".
  - The App shell and design-system a11y notes, on the board.
  - TE-006 AC13b: e2e docs and the jsdom limits.

## Changelog
Closed 2026-10-12 after 1 rework round.

**Verdicts (round 1)**
- **qa-engineer: PASS** on all 12 ACs.
  - Vitest 15/15 (10 component tests, 5 e2e-rule tests) and e2e 9/9, each run 3 times with identical results.
  - Deliberate breaks proven for AC2, AC3 (a/c/d), AC6, AC8 (a/b), AC11 and AC12.
  - Timings: Vitest about 2.5 s, e2e 7–9 s.
- **accessibility-auditor: PASS.** F1–F6 are fixed. The auditor endorsed also disabling `link-in-text-block` in
  jsdom.
- **security-privacy-reviewer: PASS.** L1 and L2 are closed:
  - the source is read-only to the container (uid 1001 versus volumes owned by 1000, 0 writable paths);
  - the masks and tmpfs cover the secrets.

**Commits**

| Commit | Content |
|---|---|
| 98312ca | Harness |
| 5cadaeb | Rework entry |
| c97f90e | e2e lockdown |
| 183a073 | axe fixes |

**Added: Vitest**
- **Runner:** vite-plus (`vp test`), so there is no separate `vitest` dependency.
- **Environment:** jsdom with Testing Library and jest-dom set up globally.
- **Matcher:** the custom `toHaveNoA11yViolations`, on axe-core 4.13.
  - Disabled in jsdom: `color-contrast`, `link-in-text-block`, `target-size`, `scrollable-region-focusable`.
  - Fails on incomplete results for `aria-hidden-focus` and `aria-valid-attr-value`.
  - Writes console notes for other incomplete results.
- **Shared formatter:** prints `summary:` (`failureSummary`) and a shortened `html:` per node.

**Added: Playwright**
- **Version:** 1.64.0, exact pin, in `mcr.microsoft.com/playwright:v1.64.0-noble`. A Pest version-sync test keeps the
  image and the npm package in step.
- **`e2e` compose service** (profile `e2e`):
  - `network_mode: service:vite`, with a global-setup forward `8000 → app:8000`;
  - hardening: read-only source, uid 1001, `cap_drop ALL`, `read_only` rootfs;
  - tmpfs on `storage`, `bootstrap/cache` and `/tmp`;
  - `.env` masked with `/dev/null`.
- **Reports:** `playwright-report/html` and `test-results/e2e`.

**Added: axe helper and allow-list**
- `expectNoA11yViolations`, with a needs-review attachment (`a11y-needs-review.txt`).
- The central `placeholderAllowList`. It has one entry: `/` color-contrast, "remove in App shell". `REMOVAL_EPICS`
  is enforced, so an empty epic name is rejected.
- A spec that checks for stale pages, and an AST scan against inline `allow` lists.

**Added: other**
- Compose contract tests for AC11 and AC12.
- A "Browser tests" section in `docs/deploy/dev.md`.

**Changed**
- `vite.config.ts`: Vitest projects, and the watcher ignores the report folders.
- `compose.dev.yaml`: the `e2e` service.

**Removed:** nothing.

**Migrations:** none.

**New env vars:** none for the app.

**Docker**
- Only `vite` was recreated, on image `be49e2a8364a`.
- `app` still runs the old image `4983283171d2`. The orchestrator recreates it after TE-004.
- The first e2e run pulls the Playwright image.

**Follow-ups (filed)**
- TE-006 AC13d: `docs/testing.md` content, L4, the redundant `--output`, the `vp fmt` note.
- Board, "Notes for the App shell epic": allow-entry node counts.
- Board, "Follow-ups for the frontend harness": the AST scan gaps.
- Board, "Follow-ups for M1": L5, reports are sensitive even after green runs.
- Board, "Devops items": the vite-plus restart race and healthcheck.
