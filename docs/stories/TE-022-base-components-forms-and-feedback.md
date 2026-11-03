---
id: TE-022
title: Base components I: button, link, field, select, number field, alert and banner
status: rework
priority: must
epic: M0 Design system
agents: [frontend-developer]
testers: [qa-engineer, accessibility-auditor]
design: approved:https://claude.ai/artifact/Dx7ZrtzrcD8eoPfwx7VDTL
rework_rounds: 1
---

## User story
As a developer, I want accessible, themed building blocks for forms and feedback, so that every later form behaves and
looks the same.

## Context
- `docs/plan.md` § 4 M0 Design system; implements the approved specs of TE-021 on React Aria Components with the tokens of
  TE-020. Design: `required` until TE-021 is approved, then set to `approved:<link>`.
- Board "Notes for the design-system epic" (real-Chromium tests for target size, focus, reduced motion, zoom/text spacing,
  live regions) is **moved to the quality criteria of this story and TE-023**.
- **Depends on:** TE-021, TE-009 (strings), TE-016. **Blocks:** M1 forms (login, register).
- Components live in `resources/js/components/ui/`; a `/design` showcase route exists only in the dev and test environments
  (not registered in production; Pest test).

## Acceptance criteria
- [ ] AC1: Given `Button`, when rendered, then variants (primary, secondary, danger, ghost), sizes, `isDisabled`, and a
      `isPending` state exist; a pending button keeps its width, sets `aria-disabled`/`isPending`, shows a spinner with text
      "Bezig…" / "Working…" (announced), and ignores repeated activation; `type` defaults to `button`.
- [ ] AC2: Given `Link`, when it points to an external origin, then it shows an icon plus visually hidden "(opent in nieuw
      tabblad)" / "(opens in a new tab)" and uses `rel="noopener noreferrer"`; internal links use Inertia navigation; link
      text never reads "klik hier"/"click here" in the showcase.
- [ ] AC3: Given `TextField` (and password/textarea variants), when rendered, then the label is programmatically associated,
      help and error text are linked with `aria-describedby`, `aria-invalid` is set on error, the error text includes an icon
      or prefix (not colour only), and `autocomplete` is a typed prop.
- [ ] AC4: Given the form error summary component, when a form submit fails validation, then focus moves to the summary, which
      lists each error as a link to its field; activating a link focuses the field (WCAG 3.3.1).
- [ ] AC5: Given `Select` (native-backed or React Aria Select per spec) and `NumberField`, when used by keyboard, then arrow
      keys, Home/End, type-ahead (select) and Page keys, increment/decrement buttons with accessible names (number field) behave
      per the WAI-ARIA pattern; number parsing and display use the active locale (`1.234,5` in nl) and min/max/step are enforced
      with a localised message.
- [ ] AC6: Given `Alert` (inline) and `Banner` (page-level), when rendered, then alerts use `role="status"` or `role="alert"`
      per severity prop; banners are landmarks-free `section` with a heading or label, are dismissible only when the spec says
      so, and dismissal returns focus to a sensible place.
- [ ] AC7: Given any component, when focused via keyboard, then the focus ring meets 3:1 and is not clipped by overflow; when
      in forced-colors mode, the border and ring remain visible.
- [ ] AC8: Given every component and state in the dev showcase page, when scanned by axe in light and dark at 320 px and
      1280 px, then there are zero violations.
- [ ] AC9: Given the hit-area of each interactive component, when measured in Chromium, then it is at least 24x24 CSS px (44x44
      for touch size variants).
- [ ] AC10: Given the showcase at 400 % zoom and with WCAG text-spacing overrides, when viewed, then content reflows, nothing
      is clipped, and no horizontal scroll occurs (except in explicitly specified data tables).
- [ ] AC11: Given `prefers-reduced-motion: reduce`, when components animate (spinner, banner), then animations are removed or
      reduced to a non-motion cue.
- [ ] AC12: Given SSR, when each component is rendered on the server, then there is no hydration warning and no `window` use at
      render.
- [ ] AC13 (a11y Moderate 1.4.11, 2026-11-02): Given the unchecked checkbox and the input border (`border-input`), when the
      TE-020 token contrast test runs, then both reach at least 3:1; the components use that token.
- [ ] AC14 (a11y Minor 2.5.8): Given the checkbox, when measured, then its box is `size-5` (20 px) or larger and the hit area
      meets AC9.

## Quality criteria
- [ ] Tests: Vitest + Testing Library + axe per component (roles, names, states, keyboard); Playwright (real Chromium) for target
      size, focus visibility and non-obscured focus, zoom/text spacing, reduced motion, forced colours, and live-region
      announcements (assert the live region text); one test per component for SSR render.
- [ ] Accessibility: WCAG 2.2 AA incl. 2.4.11, 2.5.8, 3.3.7, 3.3.8 (no cognitive tests); manual screen-reader pass by auditor.
- [ ] Security/privacy: no `dangerouslySetInnerHTML`; password field never logs or autofills incorrectly (`autocomplete`
      correct); the showcase route is absent in production (Pest test).
- [ ] i18n: all built-in strings (pending, external-link hint, increment/decrement, dismiss) nl + en.
- [ ] Performance: tree-shakable exports; component bundle growth recorded.
- [ ] Docs updated: `docs/design/system.md` (props and usage), `docs/testing.md` (component test recipe).

Bundle growth (2026-11-03, `npm run build`): the base components with React Aria are code-split into the `/design` chunk, 231 kB (73 kB gzip); the main `app` chunk stays at 179.6 kB (57.6 kB gzip). Pages that import the components will pay roughly that RAC cost once.

## Out of scope
- Combobox, date picker, table, dialog/toast, pagination (TE-023); auth pages restyle (M1).

## Open questions
- (none)

## Rework log

### Round 1 (2026-11-03)
- **Testers:** qa-engineer PASS (Vitest 273/273, Pest 759/759, e2e 97 passed + 2 skipped, check clean). accessibility-auditor FAIL.
- **Failed criteria:** AC5 (locale number parsing), WCAG 3.3.1 / 3.3.3.
- **Evidence:** `resources/js/components/ui/base/number-field.tsx:28`: `raw.replace(/./g, '')` has an unescaped dot. For `nl` it strips every character, so `Number('')` becomes 0. A valid "2" is read as 0 and gets a false out-of-range error when min > 0. A real out-of-range value (99) goes undetected whenever 0 is in range. The current e2e (`tests/e2e/base-components.spec.ts` ~line 150) covers only `en`.
- **Likely cause:** unescaped regex dot.
- **Expected fix:**
  - Escape the dot: `/\./g`.
  - Add unit tests for `nl` parsing (`1.234,5` → 1234.5; "2" → 2).
  - Add an `nl` e2e case for an out-of-range value.
- **Minor, same round:** `announcer.tsx`: setting an identical message twice causes no DOM change, so the second announcement is silent. Clear the text, then set it again in a timeout.
- **Clarified for the re-audit:** the auditor has not yet reviewed `select.tsx`, `button.tsx`, `link.tsx`, `alert.tsx` or `banner.tsx`, nor contrast, focus-not-obscured or reduced motion. The re-audit must cover them.
- **Accepted by QA (no change needed):** clamped value plus localised error; `<output>` as the announcer; bundle size recorded as the current chunk.

## Changelog
(filled when done)
