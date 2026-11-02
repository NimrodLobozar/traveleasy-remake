---
id: TE-020
title: Design tokens and type scale (Claude Design artifact, token file, contrast tests)
status: ready
priority: must
epic: M0 Design system
agents: [ux-ui-designer, frontend-developer]
testers: [qa-engineer, accessibility-auditor]
design: required
rework_rounds: 0
---

## User story
As a designer and developer, I want one set of colour, type, spacing, radius, shadow and motion tokens in both themes, so
that every screen looks consistent and meets contrast rules by construction.

## Context
- `docs/plan.md` § 4 M0 Design system ("tokens → CSS variables → Tailwind … checked for contrast in both themes"); risk
  "design system can block everything": build tokens and base components first, design screens per milestone. Legacy §3
  Design: inconsistent style.
- Design flow: Orchestrator → `ux-ui-designer` → Claude Design artifact → owner feedback → approved; link in
  `docs/design/previews.md`; spec in `docs/design/system.md`.
- Board "Notes for the design-system epic": contrast as token pairs in both themes (unit test over the token file) is **moved
  here**.
- **Depends on:** TE-016 (shell artifact gives the first context; may run in parallel with it, tokens then replace interim
  colours). **Blocks:** TE-021 to TE-023.

## Acceptance criteria
- [ ] AC1: Given the Claude Design artifact, when the owner opens the link, then it shows palettes (neutral, brand, success,
      warning, danger, info) in light and dark, a type scale (font stack, sizes, line heights, weights), spacing scale, radii,
      elevation, motion durations, focus ring and breakpoints; the link is in `docs/design/previews.md` and `design:` here is
      set to `approved:<link>` after owner approval.
- [ ] AC2: Given `docs/design/system.md`, when read, then it documents every token with its name, value per theme, and its
      purpose, plus usage rules (e.g. which text/background pairs are allowed).
- [ ] AC3: Given a token source file (`resources/css/tokens.css` or `resources/js/design/tokens.ts`, one source of truth), when
      the build runs, then the tokens become CSS custom properties and Tailwind theme values (`bg-surface`, `text-muted`...),
      and no component uses raw hex values (oxlint or a Vitest scan fails on hex colours outside the token file).
- [ ] AC4: Given the token pairs declared as allowed text/background combinations, when a Vitest unit test computes WCAG
      contrast in light and dark, then normal text pairs are at least 4.5:1, large text and UI-component/focus-ring pairs are at
      least 3:1, and the test lists the ratio for each pair; adding a failing pair fails the test.
- [ ] AC5: Given the type scale, when body text is rendered, then it is at least 16 px, line height at least 1.5, and text uses
      `rem` so it scales with the browser setting; with the WCAG text-spacing overrides applied (Playwright, real Chromium)
      nothing is clipped or overlapping on the shell page.
- [ ] AC6: Given the fonts, when the page loads, then they are bundled and self-hosted (woff2, `font-display: swap`, preloaded
      for the primary face); there is no request to a font CDN (TE-013 AC8 guard stays green).
- [ ] AC7: Given `prefers-reduced-motion: reduce`, when motion tokens are used, then durations resolve to 0 (or near 0) through
      one CSS rule, tested in Playwright.
- [ ] AC8: Given the interim shell colours from TE-016/TE-018, when this story is done, then they are replaced by tokens and
      the shell axe scans in both themes still pass.
- [ ] AC9: Given the doc, when read, then it states the brand decision (name, mark, tone of voice) actually used and links the
      owner answer.

## Quality criteria
- [ ] Tests: Vitest contrast test (AC4), Vitest hex-colour scan (AC3), Playwright text-spacing and reduced-motion tests (AC5,
      AC7), axe in both themes.
- [ ] Accessibility: WCAG 2.2 AA; colour is never the only carrier of meaning (status tokens pair with icon/text per spec).
- [ ] Security/privacy: no external fonts or assets; artifact contains no real personal data.
- [ ] i18n: sample copy in nl and en in the artifact.
- [ ] Performance: total font payload under 120 KB for the primary subset; CSS size reported.
- [ ] Docs updated: `docs/design/system.md`, `docs/design/previews.md`, `docs/testing.md`.

## Out of scope
- Component specs and implementation (TE-021 to TE-023); screen designs for later milestones; illustration/photography.

## Open questions
- (none) Brand identity, answered 2026-11-02: the designer proposes a neutral wordmark, palette and a friendly, clear tone of
  voice; the owner approves in the Claude Design review (same as TE-016).

## Rework log
(empty)

## Changelog
(filled when done)
