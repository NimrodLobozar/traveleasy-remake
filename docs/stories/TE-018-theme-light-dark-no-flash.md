---
id: TE-018
title: Light/dark theme with a manual override and no flash under SSR
status: done
priority: must
epic: M0 App shell
agents: [frontend-developer, backend-developer]
testers: [qa-engineer, accessibility-auditor, security-privacy-reviewer]
design: approved:https://claude.ai/artifact/Ja7zyTDu9EznHuxPEu2dDD
rework_rounds: 0
---

## User story
As a visitor, I want the site to follow my system's light or dark setting and let me override it, so that it is
comfortable to read and never flashes the wrong colours.

## Context
- `docs/plan.md` § 4 M0 App shell ("Light/dark theme with `prefers-color-scheme` and a manual override, without a flash under
  SSR"); SSR rules (theme resolved server-side).
- Board "Follow-ups for M1" item "Appearance cookie and CSP" is **moved here** (cookie allow-list and nonce use). The existing
  starter-kit pieces are `app/Http/Middleware/HandleAppearance.php` and `resources/js/hooks/use-appearance.tsx`.
- Design: part of the TE-016 shell artifact (control, three states: system, light, dark); colour values come from TE-020
  (until then, shell uses interim accessible palettes that TE-020 replaces).
- **Depends on:** TE-007, TE-015 (nonce), TE-016. Colour contrast pairs are verified fully in TE-020.

## Acceptance criteria
- [x] AC1: Given no `appearance` cookie, when a page is requested, then the server renders with `system` and a tiny inline
      script (with the CSP nonce) sets `class="dark"` before first paint from `prefers-color-scheme`; no flash of the wrong
      theme is visible in a Playwright screenshot sequence at load (checked with emulated `dark` colour scheme).
- [x] AC2: Given cookie `appearance=dark` or `light`, when the page is requested, then the server-rendered `<html>` already has
      the right class and `color-scheme` meta, and no script is needed for first paint.
- [x] AC3: Given the cookie holds any value other than `light`, `dark`, `system` (e.g. `foo`, a script fragment, 5 KB of text),
      when the page is requested, then it is treated as `system`, never echoed into HTML, and the request does not fail.
- [x] AC4: Given the theme control (three options: Systeem/System, Licht/Light, Donker/Dark) in the shell, when the visitor
      picks one with keyboard or mouse, then the theme changes immediately without a reload, the choice is stored in the
      `appearance` cookie (first-party, `SameSite=Lax`, 1 year) and the selection is exposed (`aria-pressed`/radio semantics).
- [x] AC5: Given the inline theme script, when the response is checked, then it carries the request's CSP nonce (TE-015) and no
      CSP violation occurs; there is no `unsafe-inline`.
- [x] AC6: Given the user's system theme changes while on `system`, when the page is open, then the theme follows without a reload.
- [x] AC7: Given `prefers-reduced-motion: reduce`, when the theme changes, then no transition animates.
- [x] AC8: Given both themes, when the shell pages are scanned with axe at 320 px and 1280 px, then there are zero violations
      in each theme; text and UI-component contrast meet 4.5:1 and 3:1 (token pairs are unit-tested in TE-020).
- [x] AC9: Given forced-colours mode (`forced-colors: active`), when the shell is viewed, then it stays usable (borders and
      focus rings remain visible; Playwright emulation).

## Quality criteria
- [x] Tests: Pest feature tests for AC2, AC3, AC5; Vitest for the hook (SSR-safe: no `window` at render); Playwright for AC1, AC4,
      AC6, AC9 and the axe scans in both themes (adds the dark-theme scan required by TE-016 AC8).
- [x] Accessibility: control is operable by keyboard with a visible focus indicator; state announced; contrast per AC8.
- [x] Security/privacy: cookie holds no personal data; value validated against the allow-list server-side; documented in
      `docs/privacy/data-inventory.md`.
- [x] i18n: control labels in nl + en.
- [x] Performance: no layout shift on load (CLS 0 caused by theme); script under 1 KB.
- [x] Docs updated: `docs/design/` (theme spec), `docs/privacy/data-inventory.md`, `docs/testing.md`.

## Out of scope
- Final colour tokens (TE-020); saving the theme in the user profile (M1).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-11-03 on branch `feat/m0-batch-5`.
- **Added:** `appearance` cookie allow-list (`light`/`dark`/`system`, anything else treated as `system`), nonce-protected inline theme
  script, three-state theme control, system-theme following, reduced-motion and forced-colours handling.
- **Changed:** header controls wrap at large text sizes (2a9de70); e2e tab order and targets follow the new controls.
- **Removed:** nothing. **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed.
- **Commits (backend):** 22ca5d0, e9c650a, 20d0fed, e63bca7 (shared with TE-017/TE-019); frontend per-story commits and 2a9de70.
- **Testers:** qa-engineer PASS (Vitest 249, Pest 758, e2e 86 passed + 2 skipped, SSR e2e 88 passed + 0 skipped); accessibility-auditor PASS;
  security-privacy-reviewer PASS.
