---
id: TE-053
title: Whitespace parity between PostgreSQL btrim and PHP trim for e-mail normalisation
status: ready            # draft | ready | in-progress | in-test | rework | done
priority: could          # must | should | could | nice-to-have (MoSCoW)
epic: Privacy before go-live (follow-up to TE-049)
agents: [database-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed       # required | approved:<link> | not-needed
rework_rounds: 0
---

## User story
As the owner, I want the database and the application to strip exactly the same whitespace from an e-mail address, so that a stored address and the protected `super_admin` address always compare equal.

## Context
Found in the TE-049 security review (Low). PostgreSQL `trim()` removes only spaces, while PHP `trim()` also removes tabs, carriage returns and line feeds (and NUL and vertical tab by default). So a row stored as `"\tadmin@example.com"` can be normalised differently by the migration (`2026_11_11_100000_normalise_user_emails.php`) and by `App\Support\EmailAddress::normalise`, and the guard could miss it. The fix is one explicit character set on both sides: `E' \t\r\n'` in SQL and `" \t\r\n"` in PHP. Check first whether the TE-049 migration has run on any server other than dev; if not, edit it in place; if yes, add a follow-up migration that re-runs the normalisation. Related: TE-049, TE-047 (erasure guard).

## Acceptance criteria
- [ ] AC1: Given a `users` row inserted with a leading tab and a trailing newline (`"\tJan@Example.com\n"`), when the normalisation runs, then the stored value is `jan@example.com`.
- [ ] AC2: Given the whitespace set `space, tab, CR, LF`, when the SQL normalisation and `EmailAddress::normalise` are run on the same fixture values, then both produce identical output for each character (a parity test asserts this).
- [ ] AC3: Given a fixture whose only difference is a trailing tab (`"jan@example.com\t"` and `"jan@example.com"`), when the migration runs, then it aborts with the collision message from TE-049 AC6, names no address, and changes nothing.
- [ ] AC4: Given the protected address stored as `"\towner@example.com"` (TE-049 AC8 style), when `users:erase <id>` targets that row, then it is refused (`REFUSED`) and nothing changes.
- [ ] AC5: Given a PHP string with NUL or vertical-tab characters around an address, when `EmailAddress::normalise` runs, then those characters are not stripped (the explicit set is the only set), and the test records this choice.
- [ ] AC6: Given the change, when the migration runs twice, then the second run changes nothing (idempotent).

## Quality criteria
- [ ] Tests: unit test for `EmailAddress::normalise` (AC2, AC5); migration feature test on PostgreSQL (AC1, AC3, AC6); erasure refusal test (AC4) next to the TE-049 test.
- [ ] Accessibility: not applicable (no UI change).
- [ ] Security/privacy: the reviewer confirms the protected-address check uses the same set on both sides and that no address appears in migration output.
- [ ] i18n: not applicable (no new strings).
- [ ] Performance: the migration keeps one transaction and one `UPDATE`.
- [ ] Docs updated: `docs/decisions.md` only if the decision line from TE-049 needs the whitespace set named.

## Files to touch
- `database/migrations/2026_11_11_100000_normalise_user_emails.php` (`up`): `btrim(email, E' \t\r\n')` (edit in place if not yet deployed, otherwise a follow-up migration).
- `app/Support/EmailAddress.php` (`normalise`, lines 16-19): `trim($email, " \t\r\n")`.
- `app/Services/Erasure/ProtectedAccountGuard.php` (`isProtectedAddress`, lines 28-37): read only; confirm it uses `EmailAddress`.
- Tests: `tests/Feature/Models/NormaliseUserEmailsMigrationTest.php` (AC1, AC3, AC6), `tests/Unit/Support/EmailAddressTest.php` (new or extend; AC2, AC5), and the erasure refusal test next to the TE-049 one (AC4).

## Out of scope
- Unicode whitespace (non-breaking space and similar): e-mail input is validated by the e-mail rule, so these are rejected rather than normalised.
- Changing the e-mail validation rules (ADR-0005).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
