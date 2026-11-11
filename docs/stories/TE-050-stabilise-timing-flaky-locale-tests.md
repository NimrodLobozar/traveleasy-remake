---
id: TE-050
title: Stabilise timing-flaky locale tests (switches e2e and LocaleResolutionTest)
status: ready            # draft | ready | in-progress | in-test | rework | done
priority: should         # must | should | could | nice-to-have (MoSCoW)
epic: none
agents: [qa-engineer]
testers: [qa-engineer]
design: not-needed       # required | approved:<link> | not-needed
rework_rounds: 0
---

## User story
As the team, I want the locale tests to pass reliably under parallel load, so that a green gate means the language switch works and a red gate means a real regression.

## Context
Seen during the TE-047 gate. `tests/e2e/switches.spec.ts` (language switch) once left the language at `nl` after the switch under load. One case in `tests/Feature/I18n/LocaleResolutionTest.php` failed once under `composer test:parallel`. Both passed on re-run, so they are timing or shared-state flakes, not confirmed bugs. The fix must find the root cause; adding retries or sleeps does not count. Related: `app/Support/I18n/LocaleCookie.php`, `app/Support/I18n/LocaleResolver.php`, `app/Http/Controllers/LocaleController.php` (TE-017, TE-009).

## Acceptance criteria
- [ ] AC1: Given the switch test in `tests/e2e/switches.spec.ts`, when the language is switched, then the test waits on observable state with web-first assertions (the `locale` cookie value and the `lang` attribute both become `en`), and contains no fixed `waitForTimeout` or single immediate read.
- [ ] AC2: Given `LocaleResolutionTest`, when the failing case is re-run in isolation and in parallel, then it does not depend on test order, leftover cookies, shared cache or the wall clock, and each test sets up its own locale state.
- [ ] AC3: Given the full e2e run (`bash scripts/check.sh e2e`) repeated 10 times, or `--repeat-each=10` for the two specs, when run on the dev stack, then zero failures.
- [ ] AC4: Given `bash scripts/check.sh php --filter=LocaleResolution` run 10 times in parallel mode, then zero failures.
- [ ] AC5: Given the fix, when the root cause is found, then it is recorded in the Changelog (one or two sentences: for example, cookie set after the redirect, or state shared between tests), and no retries are added to `playwright.config.ts` or the PHP suite to hide it.
- [ ] AC6: Given a regression that breaks the switch (cookie not set, or `lang` stays `nl`), when the spec runs, then it fails within the normal assertion timeout and not after a retry, so a real break is still caught.

## Quality criteria
- [ ] Tests: the two changed specs pass AC3 and AC4; the rest of the suites stay green (`bash scripts/check.sh all`, once at the end).
- [ ] Accessibility: not applicable (no UI change); the switch keeps its accessible name.
- [ ] Security/privacy: not applicable.
- [ ] i18n: no new strings.
- [ ] Performance: no added wait time per test beyond the original run time plus 10%.
- [ ] Docs updated: `docs/testing.md` only if a rule changes (for example, the repeat-run recipe).

## Files to touch
- `tests/e2e/switches.spec.ts` (`openHome`, lines 7-11; `cookie`, lines 13-15; the switch test): web-first assertions, no fixed waits.
- `tests/Feature/I18n/LocaleResolutionTest.php`: isolate state per test; fix the failing case.
- `app/Support/I18n/LocaleResolver.php` and `app/Support/I18n/LocaleCookie.php`: only if the root cause is in the code (for example, cookie not read on the redirect).
- `playwright.config.ts`: read only; no retry change.

## Out of scope
- Other flaky tests not named here (log them as a note if found).
- Changing the locale feature itself or the cookie name.
- Adding retries, sleeps or test skips.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
