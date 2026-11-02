---
id: TE-009
title: i18n infrastructure (nl/en JSON lang files, SSR-safe t(), Intl formatting)
status: done
priority: must
epic: M0 i18n infrastructure
agents: [backend-developer, frontend-developer]
testers: [qa-engineer]
design: not-needed
rework_rounds: 0
---

## User story
As a visitor, I want the site in Dutch by default or in English, with correct dates, numbers and money, so that I can
use it in my own language.

## Context
- `docs/plan.md` § 4 M0 "i18n infrastructure" and § 7 "i18n" (locale from user preference, then cookie, then
  `Accept-Language`, default nl; no locale prefix in URLs). Legacy §3 Design: mixed NL/EN.
- **Depends on:** TE-007. **Blocks:** TE-010, TE-016 to TE-019.
- The user-preference step of the locale chain is added in M1 (no users with preferences yet); the resolver must make
  that a one-line addition.

## Acceptance criteria
- [x] AC1: Given `lang/nl.json` and `lang/en.json`, when the app boots, then `nl` is the default and `en` the fallback
      locale is configured in `config/app.php` via env (`APP_LOCALE=nl`, `APP_FALLBACK_LOCALE=en`).
- [x] AC2: Given a request with cookie `locale=en`, when any page loads, then the Inertia shared props carry
      `locale: "en"` and the translations for that locale, and `<html lang="en">` is server-rendered.
- [x] AC3: Given no cookie and header `Accept-Language: en-GB,en;q=0.9`, when a page loads, then the locale is `en`; given
      `Accept-Language: fr`, then it is `nl`.
- [x] AC4: Given a cookie value outside the allow-list (`nl`, `en`), e.g. `locale=xx` or `../../etc`, when a page loads,
      then it is ignored and the chain continues (no error, no echo of the value).
- [x] AC5: Given a React component calls `t('key')`, when rendered on the server (SSR) and in the browser, then the
      output is identical (no hydration mismatch), and `t('key', { count: 2 })` supports named placeholders.
- [x] AC6: Given a missing key, when `t()` is called in production, then it returns the key's English fallback or the key,
      never throws; in tests a missing key fails the test.
- [x] AC7: Given helpers `formatDate`, `formatDateTime`, `formatMoney` and `formatNumber` built on `Intl`, when called with
      `nl` and `en`, then dates and money are formatted for that locale (`€ 1.234,50` vs `€1,234.50`) and take the time
      zone as an explicit argument (default `Europe/Amsterdam`), so server and client agree.
- [x] AC8: Given only the keys the shell and starter-kit pages use today, when both files are inspected, then every UI
      string on the existing pages is moved to keys in both files (no hard-coded English or Dutch left in
      `resources/js/pages` and `layouts`).
- [x] AC9: Given a user-supplied value is interpolated into a translation, when rendered, then it is escaped (no
      `dangerouslySetInnerHTML` in the `t()` path).

## Quality criteria
- [x] Tests: Pest feature tests for AC2 to AC4; Vitest for `t()`, placeholders, formatting (both locales, DST edge
      date); an SSR render test for AC5 (uses TE-008 once available).
- [x] Accessibility: `<html lang>` correct; a language change inside content (e.g. an English term in Dutch text) can be
      marked with `lang` (helper documented).
- [x] Security/privacy: locale cookie is first-party, `SameSite=Lax`, no personal data, set only when the visitor
      switches language (TE-016); allow-list validated server-side.
- [x] i18n: key naming convention (`area.thing.action`) documented; all strings nl + en.
- [x] Performance: only the active locale's strings are sent to the client.
- [x] Docs updated: `docs/testing.md` (i18n tests), a short "i18n" section in `docs/design/` or `docs/deploy/dev.md`.

## Out of scope
- Language switch UI (TE-016), CI completeness check (TE-010), user-profile locale (M1), URL locale prefix.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Done 2026-11-02. Testers: qa-engineer PASS (incl. reflow at 320 px and 200 % text in Dutch), security-privacy-reviewer PASS,
accessibility-auditor PASS.
- **Added:** `lang/nl.json` and `lang/en.json`; locale resolver (cookie, then `Accept-Language`, default nl; allow-list); shared
  Inertia props `locale` and active-locale translations; SSR-safe `t()` with placeholders; `Intl` helpers `formatDate`,
  `formatDateTime`, `formatMoney`, `formatNumber`; `lang` helper for foreign-language content; Pest, Vitest and e2e reflow tests;
  docs in `testing.md` and the i18n developer note.
- **Changed:** all UI strings on existing pages moved to keys.
- **Removed:** nothing.
- **Migrations:** none. **New env vars:** `APP_LOCALE`, `APP_FALLBACK_LOCALE` (already documented in `.env.example`).
- **Docker rebuild:** not needed.
- **Commits** on `feat/m0-ssr-i18n-csp`: see TE-015 changelog (shared branch).
- **Follow-ups:** `created_at_diff`/`last_used_at_diff` server texts are not localised yet (M1, or the first story that shows
  them); content in another language gets a `lang` attribute (README a11y note).
