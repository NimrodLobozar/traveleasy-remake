---
id: TE-021
title: Base component specs (states, keyboard behaviour, ARIA patterns)
status: done
priority: must
epic: M0 Design system
agents: [ux-ui-designer]
testers: [accessibility-auditor]
design: approved:https://claude.ai/artifact/Dx7ZrtzrcD8eoPfwx7VDTL
rework_rounds: 0
---

## User story
As a developer, I want a precise spec for every base component, so that I can implement it accessibly without guessing.

## Context
- `docs/plan.md` § 4 M0 Design system: button, link, field, select, combobox, date picker, number field, table, dialog,
  alert/toast, banner, pagination. Principle 2 (WCAG 2.2 AA) and `docs/legacy-analysis.md` §3 Design.
- Design flow as in TE-020 (artifact + `docs/design/system.md` + `previews.md`, owner approval via the orchestrator).
- **Depends on:** TE-020 (tokens). **Blocks:** TE-022, TE-023.
- A docs/design story: the accessibility-auditor reviews the specs against WCAG 2.2 and the WAI-ARIA Authoring Practices
  before implementation to find problems cheaply.

## Acceptance criteria
- [ ] AC1: Given the Claude Design artifact, when opened, then it shows all 12 components (button, link, field with
      label/help/error, select, combobox, date picker, number field, table, dialog, alert/toast, banner, pagination) with
      every state: default, hover, focus, active, disabled, read-only where relevant, loading, invalid, success; in light and
      dark; in nl and en sample copy.
- [ ] AC2: Given `docs/design/system.md`, when read, then each component has: purpose, anatomy, variants and sizes, token usage,
      keyboard behaviour (keys and focus order), ARIA role/attributes and the React Aria Components primitive it maps to,
      announcement behaviour (live regions), and error-handling rules.
- [ ] AC3: Given forms, when specified, then the field spec requires a visible persistent label, help text linked by
      `aria-describedby`, errors linked and announced, an error summary at the top of a form on submit that moves focus to
      itself and links to each invalid field, `autocomplete` tokens where applicable, and no placeholder-as-label (WCAG
      1.3.5, 3.3.1, 3.3.2, 3.3.7, 3.3.8).
- [ ] AC4: Given pointer targets, when specified, then every interactive control is at least 24x24 CSS px (44x44 on touch layouts),
      with spacing rules (WCAG 2.5.8); drag-only interactions have a single-pointer alternative (2.5.7).
- [ ] AC5: Given the dialog, toast and banner, when specified, then the dialog traps and restores focus and closes with Escape;
      toasts do not disappear before they can be read (minimum 8 s or manual dismiss, pause on hover/focus) and use
      `role="status"` (errors `role="alert"` sparingly); banners are persistent and dismissal rules are stated.
- [ ] AC6: Given the table and pagination specs, when read, then they cover caption/`scope`, sortable column buttons with
      `aria-sort`, responsive behaviour at 320 px (reflow without losing headers), empty state ("Geen resultaten gevonden" /
      "No results found" plus a reset-filters button), loading state, and pagination as a `nav` with `aria-current="page"`.
- [ ] AC7: Given the date picker and combobox, when specified, then the date picker supports typing a date in the locale format
      as well as picking, with parse-error messages (nl/en), and the combobox defines how the list announces result counts.
- [ ] AC8: Given the specs, when the accessibility-auditor reviews them, then the written review (in the story handoff) has no
      Blocker or Serious findings left open; owner approval is recorded and `design:` becomes `approved:<link>`.

## Quality criteria
- [ ] Tests: n/a (docs and design); each spec lists the tests TE-022/TE-023 must write (keyboard, focus, axe, live region).
- [ ] Accessibility: reviewed per AC8.
- [ ] Security/privacy: sample data is fake.
- [ ] i18n: all sample copy in nl + en; text expansion for nl (about 30 % longer) is shown to not break layouts.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/design/system.md`, `docs/design/previews.md`.

## Out of scope
- Implementation (TE-022, TE-023); components beyond the 12 (added with the milestone that needs them).

## Open questions
- Answered 2026-11-03 (owner): toasts bottom-left on desktop; dense staff rows 40/36 px, 44 px under `pointer: coarse`; cancel dialog shows refund including VAT.

## Rework log
(empty)

## Changelog
(filled when done)
