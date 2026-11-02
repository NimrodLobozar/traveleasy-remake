---
id: TE-016
title: Accessible base layout (landmarks, skip link, route-change focus, axe allow-list ends empty)
status: in-test
priority: must
epic: M0 App shell
agents: [ux-ui-designer, frontend-developer]
testers: [qa-engineer, accessibility-auditor]
design: required
rework_rounds: 1
---

## User story
As a keyboard or screen-reader user, I want every page to have the same predictable structure and to move my focus when
I navigate, so that I can find the content and the navigation without a mouse.

## Context
- `docs/plan.md` § 4 M0 App shell ("layout with landmarks, skip link, focus handling on Inertia navigation"); Principle 2
  (WCAG 2.2 AA); legacy §3 Design/UX.
- **Design:** the ux-ui-designer delivers a Claude Design artifact for the shell (header with brand, main navigation,
  language and theme controls placement, footer, mobile navigation, welcome/placeholder page replacement) in nl and en. The
  same artifact covers TE-017 to TE-019. Link goes in `docs/design/previews.md`; owner approval via the orchestrator before
  build. Brand identity is a product choice (see open questions); the designer proposes a neutral default until answered.
- **Depends on:** TE-009 (strings), TE-015 (nonce/CSP for any inline script). **Blocks:** TE-017, TE-018, TE-019.
- Board "Notes for the App shell epic" (TE-005 a11y review) and "Follow-ups from TE-006" (`REMOVAL_EPICS`) are **moved
  here**. They are removed from the README.

## Acceptance criteria
- [ ] AC1: Given any page using the base layout, when the DOM is inspected, then it has exactly one `<header>` (banner),
      one `<nav aria-label>` for the main navigation, one `<main id="main">`, one `<footer>` (contentinfo), and every
      extra `nav`/`aside` has a unique accessible name (nl/en).
- [ ] AC2: Given the page just loaded, when the user presses Tab once, then focus lands on a visible skip link ("Naar de
      hoofdinhoud" / "Skip to main content"); activating it moves focus to `<main>` (which has `tabindex="-1"`), and the link
      is hidden when not focused only by moving off-screen (not `display:none`).
- [ ] AC3: Given an Inertia navigation to another page, when the new page renders, then focus moves to the page's `<h1>` (or
      `<main>` if absent), the document title updates to "<Page> | TravelEasy", and a polite live region announces the new
      page title once; the scroll position resets to the top (Playwright + Vitest).
- [ ] AC4: Given a back/forward navigation, when the previous page is restored, then focus handling does not trap or reset
      the scroll position unexpectedly (restores scroll; focus moves to `<main>`).
- [ ] AC5: Given every page, when scanned, then there is exactly one `<h1>` and heading levels do not skip (axe best-practice
      rules `page-has-heading-one`, `heading-order`, `landmark-one-main`, `landmark-unique`, `region`, `skip-link`,
      `empty-heading` pass).
- [ ] AC6: Given a 320 px wide viewport and 400 % zoom, when the layout is used, then there is no horizontal scrolling for
      the shell, and the mobile navigation (disclosure button with `aria-expanded`, closes with Escape, returns focus to the
      button) is operable by keyboard.
- [ ] AC7: Given the starter-kit placeholder welcome page, when this story is done, then it is replaced by a real shell page
      (nl/en) and `placeholderAllowList` is empty: `expect(placeholderAllowList).toHaveLength(0)` is asserted, the `allow` option
      is removed from the public `expectNoA11yViolations` helper, `a11y-allow-list.ts` and its spec are removed or reduced to
      the assertion, and `REMOVAL_EPICS` is narrowed to `'App shell'` (or removed) so the code and `docs/testing.md` § 5.4
      agree.
- [ ] AC8: Given the e2e axe helper, when the shell is scanned, then the scan runs in light **and** dark theme (once TE-018
      lands) and at 320 px width, and after state changes: mobile navigation open, a validation error shown, and after an
      Inertia navigation; all with zero violations.
- [ ] AC9: Given a second axe pass with the best-practice rules of AC5 plus `label-content-name-mismatch`, when it runs
      on all shell pages, then it has zero violations (document any consciously disabled rule with a reason).
- [ ] AC10: Given the `allow-usage` AST scan, when it runs, then it also catches computed keys (`['allow']`) and
      `.js`/`.mjs` files under `tests/e2e/`, each proven with a fixture (follow-up from TE-005 close).
- [ ] AC11: Given the focus indicator, when any interactive shell element is focused, then it has a visible outline of at
      least 2 CSS px with 3:1 contrast against adjacent colours, and sticky headers never cover the focused element
      (WCAG 2.4.11).
- [ ] AC12: Given pointer targets in the shell (nav links, buttons, switchers), when measured in Chromium, then each is at
      least 24x24 CSS px (WCAG 2.5.8; target 44x44 on mobile).

## Quality criteria
- [ ] Tests: Vitest + jest-axe style matcher for the layout component; Playwright for AC2, AC3, AC6, AC8, AC11, AC12 (real
      layout); keyboard-only path test (Tab order is logical: skip link, brand, nav, controls, main).
- [ ] Accessibility: WCAG 2.2 AA; screen-reader announcements of AC3 verified manually by the auditor (NVDA or VoiceOver)
      and noted in the report; respects `prefers-reduced-motion`.
- [ ] Security/privacy: no third-party resources (fonts and icons bundled); the live region never contains user data.
- [ ] i18n: all strings nl + en via TE-009 keys.
- [ ] Performance: layout adds no blocking JS beyond the bundle; Lighthouse accessibility 100 on the shell page.
- [ ] Docs updated: `docs/design/` (shell spec), `docs/design/previews.md`, `docs/testing.md` § 5.4, README board notes.

## Out of scope
- Language switch (TE-017), theme (TE-018), error pages (TE-019), full design tokens (TE-020), content pages (M2/M3).
- Role-aware navigation items (M1 and later).

## Open questions
- (none) Brand, answered 2026-11-02: the designer proposes a neutral wordmark, a palette and a friendly, clear tone of voice;
  the owner approves in the Claude Design review. Refined in TE-020.

## Rework log
### Round 1 - 2026-11-03
**qa-engineer: FAIL on AC4.** On `/` at scrollY 250, clicking "Inloggen" and going back leaves scrollY at 0 for 2 s.
`history.scrollRestoration` is `manual`, so Inertia/`RouteFocus` must restore it; focus correctly goes to `<main>`.
Evidence: `tests/e2e/shell-extra.spec.ts` "announces the new title exactly once and restores scroll on back" (commit 0389c1c).
Everything else passed (AC1-3, AC5-12; Vitest 197, Pest 638, e2e 40 + 2 skipped, SSR 40 + 1 skipped).
Expected fix: restore the saved scroll position on back/forward (history) navigation while keeping focus on `<main>`.

**accessibility-auditor: PASS**, with findings to fix in this round:
- [ ] (Moderate, 3.3.2/4.1.2) Error summary in `resources/js/pages/home.tsx:115-140` is a `<fieldset tabIndex=-1>` with an
      `<h3>` in the legend, announced as "group". Make it a `div`/`section` with `aria-labelledby` and a heading
      (`docs/design/system.md:212`).
- [ ] (Moderate, 4.1.3) Possible double announcement, `resources/js/components/route-focus.tsx:39-52`: title announced via
      focus on the `<h1>` and again via the polite live region about 80 ms later. QA's runtime test saw it once.
      **Criterion clarified (AC3/AC4):** after a **forward** navigation the only announcement is the focus move to the
      `<h1>` (live region stays empty); the live-region title message is used only after **back/forward** (history)
      navigation, where focus goes to `<main>`. The e2e test must assert both cases.
- [ ] (Minor, 3.3.2) The four search inputs get `required`/`aria-required`, and a field's error clears once corrected.
- [ ] (Minor, 1.4.1/1.4.11) Current-page indicator in `site-header.tsx:94-97` needs a non-colour cue (underline or weight)
      that also shows in forced colours.
- [ ] (Minor, 2.4.4/3.2.4) Nav "Help" and footer "Help and frequently asked questions" both point to `/help`; use
      consistent link text for the same target.

**Still open:** manual screen-reader check of AC3 (accessibility-auditor, next audit).

## Changelog
(filled when done)
