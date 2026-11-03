---
id: TE-017
title: Language switch nl/en with persisted preference
status: done
priority: must
epic: M0 App shell
agents: [frontend-developer, backend-developer]
testers: [qa-engineer, accessibility-auditor]
design: approved:https://claude.ai/artifact/Ja7zyTDu9EznHuxPEu2dDD
rework_rounds: 0
---

## User story
As a visitor, I want to switch between Dutch and English and have the site remember it, so that I read the site in my
language on every visit.

## Context
- `docs/plan.md` § 4 M0 "Language switch nl/en with persisted preference"; § 7 i18n (cookie after user preference; no URL
  prefix). Design: part of the TE-016 shell artifact (control placement, labels).
- **Depends on:** TE-009, TE-016. The per-user preference (profile) is M1; this story stores only a cookie.

## Acceptance criteria
- [x] AC1: Given the shell header (and the mobile navigation), when it is rendered in Dutch, then a language control offers
      "English" with `lang="en"` on that item, and in English offers "Nederlands" with `lang="nl"`; the current language is
      indicated programmatically (`aria-current="true"` or equivalent), not by colour alone.
- [x] AC2: Given the visitor activates the control (mouse, Enter or Space), when the request completes, then the page
      re-renders in the other language without a full reload, `<html lang>` and the document title change, and focus stays on
      the control; a polite announcement says "Taal gewijzigd naar Nederlands" / "Language changed to English".
- [x] AC3: Given the switch, when it posts to the server (`POST /locale`, CSRF-protected, throttled), then only `nl` or `en`
      is accepted; any other value leaves the locale unchanged. A **422 is returned to JSON requests only**; Inertia and plain
      form posts get a redirect back with an inline `errors.locale` message (clarified at close, 2026-11-03).
- [x] AC4: Given the switch succeeded, when the visitor returns in a new browser session, then a `locale` cookie
      (first-party, `Secure` in HTTPS, `SameSite=Lax`, `HttpOnly`, 1 year; React reads the locale from Inertia props, not from
      the cookie; decision 2026-11-02, replaces "HttpOnly not needed") keeps the language; without a cookie the `Accept-Language` chain of TE-009 applies.
- [x] AC5: Given JavaScript is disabled, when the visitor uses the control, then it still works as a normal form post and
      redirects back to the same page (progressive enhancement).
- [x] AC6: Given any page, when it is rendered under SSR with the `locale=en` cookie, then the server HTML is already in
      English (no flash of Dutch).
- [x] AC7: Given a screen reader, when the control is announced, then its accessible name is in the **target** language
      with the visible text (WCAG 2.5.3 label in name), and the group is labelled "Taal" / "Language".

## Quality criteria
- [x] Tests: Pest feature tests for AC3, AC4 (cookie attributes), AC6; Vitest for the component; Playwright journey for AC2,
      AC5 (JS disabled context) and keyboard use.
- [x] Accessibility: keyboard path Tab to control, Enter/Space; no focus loss; announcement checked manually.
- [x] Security/privacy: no personal data in the cookie; CSRF token required; rate limit 30/min per IP; redirect target is the
      same-origin referrer path only (no open redirect; tested with an external `Referer`).
- [x] i18n: all strings nl + en.
- [x] Performance: switching sends one request and only the shared props that changed.
- [x] Docs updated: `docs/privacy/data-inventory.md` (cookie `locale`), `docs/testing.md`.

## Out of scope
- Saving the preference on the user profile (M1); locale URL prefix.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Closed 2026-11-03 on branch `feat/m0-batch-5`.
- **Added:** `POST /locale` (CSRF, throttled, allow-list `nl`/`en`), `locale` cookie (HttpOnly, SameSite=Lax, 1 year), language control in
  the header and mobile navigation, polite announcement, SSR rendering from the cookie.
- **Changed:** 422 goes to JSON requests only; Inertia and form posts get a redirect back with an inline `errors.locale`.
  `hreflang` is not used (not valid on POST buttons); `lang` is set on each option instead. Header controls wrap at large text sizes
  (2a9de70); e2e tab order follows the new controls.
- **Removed:** nothing. **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed.
- **Commits (backend):** 22ca5d0, e9c650a, 20d0fed, e63bca7 (shared with TE-018/TE-019); frontend per-story commits and 2a9de70.
- **Testers:** qa-engineer PASS (Vitest 249, Pest 758, e2e 86 passed + 2 skipped, SSR e2e 88 passed + 0 skipped); accessibility-auditor PASS;
  security-privacy-reviewer PASS (21 open-redirect probes stayed same-origin).
