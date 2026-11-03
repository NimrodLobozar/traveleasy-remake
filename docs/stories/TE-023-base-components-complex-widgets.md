---
id: TE-023
title: Base components II: combobox, date picker, table, dialog, toast and pagination
status: done
priority: must
epic: M0 Design system
agents: [frontend-developer]
testers: [qa-engineer, accessibility-auditor]
design: approved:https://claude.ai/artifact/Dx7ZrtzrcD8eoPfwx7VDTL
rework_rounds: 0
---

## User story
As a developer, I want accessible complex widgets ready to use, so that search, booking and the back office can be built
without fighting accessibility.

## Context
- `docs/plan.md` § 4 M0 Design system; implements the approved specs of TE-021 on React Aria Components. Design: `required`
  until TE-021 is approved, then `approved:<link>`. The legacy site had mouse-only or unlabelled widgets (legacy §3 Design).
- Same test recipe as TE-022 (real-Chromium tests for the items in the board note "Notes for the design-system epic").
- **Depends on:** TE-022 (shared primitives, showcase page, test helpers). **Blocks:** M2 to M4 UI.

## Acceptance criteria
- [x] AC1: Given `Combobox`, when the user types, then a listbox of options appears with `aria-activedescendant` handling; Arrow
      keys move, Enter selects, Escape closes, and the result count is announced by React Aria's built-in polite live region (e.g. "2 opties beschikbaar" /
      "2 options available"), and "Geen resultaten" / "No results" is shown visibly (clarified at close, QA + a11y accepted); it works with a pointer and a keyboard; async loading shows a status and does not lose
      focus.
- [x] AC2: Given `DatePicker`, when used, then the field accepts typed dates in the locale format (`dd-mm-jjjj` in nl,
      `dd/mm/yyyy` or ISO in en per spec), the calendar popover is operable by keyboard (arrows, Page Up/Down, Home/End, Escape),
      min/max dates are enforced with localised errors, and the week starts on Monday in nl; time zone is explicit when a time
      is involved (no hydration mismatch under SSR).
- [x] AC3: Given `Table`, when rendered with data, then it has a caption (visible or hidden), `th scope`, sortable columns as
      buttons with `aria-sort` and an announced change, a horizontally scrollable region that is keyboard-focusable with an
      accessible name when it overflows, and a stacked/reflow layout at 320 px that keeps header-cell association.
- [x] AC4: Given a table with no rows, when rendered, then it shows "Geen resultaten gevonden" / "No results found" and a reset-
      filters button when filters are active; given loading, it shows a status message and `aria-busy`.
- [x] AC5: Given `Dialog`, when opened, then focus moves inside, Tab is trapped, Escape closes, background is inert, focus returns
      to the trigger, it has an accessible name and description, and long content scrolls inside without losing the title/actions
      (works at 400 % zoom).
- [x] AC6: Given `Toast`, when triggered, then it is announced via `role="status"` (errors `role="alert"`), stays at least 8 s, pauses
      on hover/focus, can be dismissed with the keyboard, and never takes focus; it can be reached by a documented shortcut or
      stays reachable in tab order.
- [x] AC7: Given `Pagination`, when rendered, then it is a `nav` with an accessible name, the current page has `aria-current="page"`,
      prev/next have accessible names and disabled states, page links are real links with `href` (SSR-friendly), and it collapses
      to previous/next plus a "Pagina 2 van 9" / "Page 2 of 9" text at 320 px.
- [x] AC8: Given every component and state in the dev showcase page, when scanned by axe in light and dark at 320 px and
      1280 px, then there are zero violations; target size (24 px minimum), focus visibility/non-obscured focus, zoom and text
      spacing, reduced motion and forced colours pass in Playwright (real Chromium).
- [x] AC9: Given SSR, when each component is rendered on the server, then there is no hydration warning and no browser global at
      render; popovers render client-side only where React Aria requires it, without layout shift.
- [x] AC10: Given an end-of-M0 check, when the full axe suite runs on every page and showcase state, then the allow-list is still
      empty (TE-016 AC7 assertion stays green).
- [x] AC11 (TE-021 audit re-check, 2026-11-02): Given the React Aria `ToastRegion`, when rendered, then the story verifies whether
      it can drop its `alertdialog` role (so it is not announced as a dialog) and records the result; if it can, it does, and a
      Testing Library test asserts the final role.

## Quality criteria
- [x] Tests: Vitest + Testing Library + axe per component (keyboard, roles, live regions); Playwright for AC8; i18n formats both
      locales (nl, en); date parsing edge cases (29 Feb, DST day, invalid input).
- [x] Accessibility: WCAG 2.2 AA incl. 2.5.7, 2.5.8, 3.3.7 (automated checks and auditor PASS).
- [ ] Accessibility, manual screen-reader pass of combobox, date picker, dialog, toast by the auditor with notes in the report (owner, manual).
- [x] Security/privacy: no unsanitised HTML in cells or options; the showcase route is absent in production.
- [x] i18n: all built-in strings nl + en.
- [x] Performance: date picker and calendar code-split (lazy) if over 30 KB gzipped; bundle impact recorded.
- [x] Docs updated: `docs/design/system.md`, `docs/testing.md`.

## Out of scope
- Domain-specific widgets (airport picker, seat/traveller forms) built per milestone on these primitives.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- **Done:** 2026-11-03, 0 rework rounds. Commit `6ba4909`.
- **Testers:**
  - qa-engineer PASS: Vitest 323/323, Pest 759/759, e2e 144 passed + 2 skipped, check clean.
  - security-privacy-reviewer PASS: the lockfile change is only 2 root entries, already installed.
  - accessibility-auditor PASS: complex-components e2e 46/46. Manual screen-reader pass still open (owner, manual).
- **Added:**
  - Combobox, DatePicker (lazy calendar chunk 73 kB / 23 kB gzip), Table with empty state, Dialog.
  - ToastRegion + `showToast`, built on `useToast`/`useToastRegion` because RAC Toast forces `alertdialog`
    (AC11: the built-in region cannot drop that role, so the custom one announces as a status or alert).
  - Pagination, the `overflow.ts` hook, `complex-showcase.tsx`, `complex.test.tsx` (46),
    `tests/e2e/complex-components.spec.ts`.
  - Docs: `docs/design/system.md` § 4.18 and `docs/testing.md`.
- **Changed:** the AC1 announcement wording (see the clarification in AC1).
- **Removed:** none.
- **Migrations:** none.
- **New env vars:** none.
- **New direct dependencies:** `react-aria` 3.53.1 and `@internationalized/date` 3.12.4.
- **Docker:** the dev containers need a rebuild (`docker compose up -d --build -V`).
- **Bundle:** the `/design` chunk went from 231 to 325 kB (73 → 98 kB gzip). Testing routes only.
