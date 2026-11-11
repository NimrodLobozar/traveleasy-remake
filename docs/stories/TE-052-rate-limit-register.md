---
id: TE-052
title: Rate-limit POST /register by IP
status: done                 # draft | ready | in-progress | in-test | rework | done
priority: must           # must | should | could | nice-to-have (MoSCoW)
epic: Security before go-live (follow-up to TE-047 and TE-049)
agents: [backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed       # required | approved:<link> | not-needed
rework_rounds: 0
---

## User story
As the owner, I want registration throttled per IP address, so that a script cannot create accounts in bulk or probe which e-mail addresses exist at full speed.

## Context
Found in the TE-049 security review (Medium, existed before this story). `app/Providers/FortifyServiceProvider.php` (lines 71-85) limits login, two-factor and passkeys only. `POST /register` has no limiter, so it can be hit without a cap. Registration also reveals whether an address is taken (the e-mail-taken error from TE-049), so an unlimited register endpoint is an account-enumeration path. Related: TE-049 (e-mail-taken error), ADR-0005 (no e-mail is sent, so there is no mail-based abuse control to rely on), TE-015 (rate limiting is part of the security baseline).

## Acceptance criteria
- [x] AC1: Given a guest on one IP address, when they send `POST /register` more times than the limit in the window (same limit as login, set in `FortifyServiceProvider`), then the next request gets HTTP 429 with the localised error page (NL and EN), and no `users` row is created.
- [x] AC2: Given the limit was hit, when the window passes, then the same IP can register again and the account is created as before.
- [x] AC3: Given two different IP addresses, when one of them is throttled, then the other can still register (the counter is per IP, not global).
- [x] AC4: Given a valid registration under the limit, when the form is submitted, then the account is created and the redirect is unchanged (no regression on the happy path).
- [x] AC5: Given a request that sends a spoofed `X-Forwarded-For` header from an untrusted peer, when the limit is counted, then the spoofed address does not change the key (the key is the socket or trusted-proxy address only).
- [x] AC6: Given a throttled request, when it is logged or returned, then neither the response nor the log contains the submitted e-mail address (the counter key is the IP only).
- [x] AC7: Given a signed-in customer, when they send `POST /register`, then the guest middleware still refuses the request and the throttle does not change that result.
- [x] AC8: Given the limiter is configured, when the Fortify routes are listed, then `register` carries the throttle middleware and no other route loses its limiter.

## Quality criteria
- [x] Tests: feature test for AC1, AC2, AC3 and AC5 in a new `tests/Feature/Auth/RegisterRateLimitTest.php` (PostgreSQL only); AC4 and AC7 added to `tests/Feature/Auth/RegistrationTest.php`; AC8 as a route assertion next to the test.
- [x] Accessibility: not applicable (no UI change); the 429 page keeps its existing accessible structure (TE-019).
- [x] Security/privacy: the reviewer confirms the key uses the trusted-proxy address (AC5), that nothing logs the e-mail (AC6), and that login, two-factor and passkey limits are unchanged.
- [x] i18n: any new message in `nl` and `en`; prefer the existing 429 page text (no new string if it fits).
- [x] Performance: the limiter uses the existing cache driver; no extra query per request.
- [x] Docs updated: `docs/decisions.md` (one line: register is rate-limited by IP, same limit as login).

## Files to touch
- `app/Providers/FortifyServiceProvider.php` (`boot`, lines 31-36; limiter block, lines 71-85): add a `register` limiter next to the login limiter and apply it to the `POST /register` route.
- `config/fortify.php`: read only, unless the route middleware is set here (check how Fortify registers the register route).
- `lang/nl.json`, `lang/en.json`: only if a new message is needed.
- `tests/Feature/Auth/RegisterRateLimitTest.php`: new.
- `tests/Feature/Auth/RegistrationTest.php`: add AC4 and AC7 cases.

## Out of scope
- CAPTCHA or e-mail verification (ADR-0005 forbids e-mail, and CAPTCHA would send data to an external service).
- Changing the login, two-factor or passkey limits.
- Per-account lockout beyond the existing login throttle.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
2026-11-12, commit 58c1831 (`fix(auth): rate-limit register by IP (TE-052)`)

- **Added:** `register` limiter `Limit::perMinute(5)`, keyed by IP only, in `app/Providers/FortifyServiceProvider.php`; `throttle:register` on `register.store`. New `tests/Feature/Auth/RegisterRateLimitTest.php`; AC4/AC7 cases in `tests/Feature/Auth/RegistrationTest.php`; one line in `docs/decisions.md`.
- **Changed:** `bootstrap/app.php` now runs `guest` before any throttle, so a signed-in user gets the guest redirect instead of a 429. This also applies to `POST /login`.
- **Removed:** none.
- **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed (PHP code only).
- **QA PASS:** php 849 passed; front 23 files passed; e2e 144 passed / 2 skipped; static clean.
- **Security PASS:** no findings; key checked against `TRUSTED_PROXIES`; `route:cache` order checked.
