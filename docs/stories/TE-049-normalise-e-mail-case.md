---
id: TE-049
title: Normalise e-mail case so the protected super_admin address cannot be claimed
status: done                   # draft | ready | in-progress | in-test | rework | done
priority: must           # must | should | could | nice-to-have (MoSCoW)
epic: Privacy before go-live (follow-up to TE-047)
agents: [database-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed       # required | approved:<link> | not-needed
rework_rounds: 0
---

## User story
As the owner, I want e-mail addresses compared and stored in one normalised form, so that no other account can take the protected `super_admin` address in a different letter case and then escape erasure.

## Context
Found in the TE-047 review (Low, before go-live). Registration and profile validation use `Rule::unique` on `users.email`, which is case-sensitive on PostgreSQL (`app/Concerns/ProfileValidationRules.php`, `emailRules`, line 50). `ProtectedAccountGuard` compares `SUPER_ADMIN_EMAIL` case-insensitively. So `Owner@Example.com` can be registered while the owner's address is `owner@example.com`, or the exact address can be registered before the super_admin is provisioned, and then the guard and the stored row disagree. Login (Fortify) also looks up the e-mail case-sensitively. Related: TE-047 (erasure and guard), roles story (replaces the guard body). The e-mail is an unverified login name (ADR-0005), so this is about identity and erasure, not about sending mail.

## Acceptance criteria
- [x] AC1: Given a user `admin@example.com` exists, when a guest registers with `Admin@Example.com`, then the form shows the e-mail-taken error on the e-mail field (linked, in NL and EN) and no row is created.
- [x] AC2: Given `SUPER_ADMIN_EMAIL=Owner@Example.com` and no account yet, when a guest registers with `owner@example.com`, then registration is refused with the same e-mail-taken error, and when the owner account is provisioned it is stored as `owner@example.com`.
- [x] AC3: Given a customer whose stored address is `jan@example.com`, when they save their profile with `  Jan@Example.COM ` (own address, other case or spaces), then the save succeeds, the stored value is `jan@example.com` (trimmed, lowercase), and no unique-error is shown.
- [x] AC4: Given a guest with the account `jan@example.com`, when they log in with `Jan@Example.com`, then they are signed in to that account only, and no second account is created.
- [x] AC5: Given the database, when a row whose e-mail differs only by case from an existing row is inserted directly (seeder, tinker or a second code path), then PostgreSQL rejects it (unique index on `lower(email)`), and the existing unique index on `email` is dropped only if it becomes redundant.
- [x] AC6: Given the migration that normalises existing data, when `users` contains mixed-case e-mails, then they are lowercased and trimmed in one transaction; if two rows collide after normalisation, the migration aborts with a message naming no e-mail address, changes nothing, and the owner resolves the duplicate first.
- [x] AC7: Given `ProtectedAccountGuard`, when it checks an address, then it applies the same normalisation as storage (trim, lowercase), and a unit test covers mixed case, surrounding spaces and an unset `SUPER_ADMIN_EMAIL` (protects nothing, no error).
- [x] AC8: Given `users:erase <id>` or self-service deletion, when the target is the account whose normalised e-mail equals `SUPER_ADMIN_EMAIL`, then it is refused (`REFUSED`, nothing changed), and a feature test proves this for an account stored as `Owner@Example.com` (set by a case-variant insert before normalisation was in place).

## Quality criteria
- [ ] Tests: unit for the normalisation helper and the guard; feature tests for registration (AC1, AC2), profile (AC3), login (AC4), the migration (AC6, on a PostgreSQL test DB with seeded mixed-case rows) and the refusal path (AC8). PostgreSQL only.
- [ ] Accessibility: the e-mail-taken error on registration and profile is linked to the field (`aria-describedby` or the existing error component); no new UI.
- [ ] Security/privacy: `super_admin` cannot be claimed by a case variant; no e-mail address in logs, error messages or `audit_logs`; the reviewer confirms the unique index and migration cannot leave two accounts with one normalised address.
- [ ] i18n: the error message uses the existing validation keys in `nl` and `en`; no new strings unless the key is missing.
- [ ] Performance: the `lower(email)` index is created in the same migration; no full-table scan on login.
- [ ] Docs updated: `docs/decisions.md` (one line: e-mail stored lowercase, unique on `lower(email)`), `docs/privacy/data-inventory.md` if the stored form changes.

## Files to touch
- `app/Concerns/ProfileValidationRules.php` (`emailRules`, lines 42-53): normalised input and a case-insensitive unique rule.
- `app/Actions/Fortify/CreateNewUser.php` (`create`, lines 22-34): normalise before validation and storage.
- `app/Models/User.php`: new `email` set mutator (trim, lowercase) so every write path normalises.
- `app/Http/Requests/Settings/ProfileUpdateRequest.php` (`rules`, lines 21-24): `prepareForValidation` normalises.
- `app/Services/Erasure/ProtectedAccountGuard.php` (`class ProtectedAccountGuard`, lines 16-28): use the shared normalisation.
- `database/migrations/<date>_normalise_user_emails.php`: new (normalise rows, `lower(email)` unique index).
- Tests: `tests/Feature/Auth/` (registration and login cases; add next to the existing ones), `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Unit/AccountEraserTest.php`, `tests/Feature/Console/EraseUsersTest.php`, and a migration test next to `tests/Feature/Models/`.

## Out of scope
- Changing e-mail rules beyond case and surrounding spaces (no e-mail verification, ADR-0005).
- The roles story itself (it replaces the guard body; this story keeps the env-based check).
- Admin UI for e-mail changes.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- Branch: `fix/te-049-email-case`. Commits: 75c1cb2 (db), 99f34ef (auth), b3c959b (i18n).
- Added:
  - `database/migrations/2026_11_11_100000_normalise_user_emails.php`: trims and lowercases `users.email` in one transaction. If two rows collide after normalisation it aborts, changes nothing and names only a count, never an address. Adds unique index `users_email_lower_unique` on `lower(email)`.
  - `app/Support/EmailAddress.php` (trim and lowercase, one place), `app/Rules/UniqueEmail.php` (case-insensitive unique check; `SUPER_ADMIN_EMAIL` is reserved).
  - `tests/Feature/Models/NormaliseUserEmailsMigrationTest.php` (PostgreSQL, seeded mixed-case rows, collision abort).
  - Lang key `auth.email_taken` in `lang/nl.json` and `lang/en.json`.
- Changed:
  - `User` e-mail mutator (every write path normalises); `CreateNewUser`; `ProfileUpdateRequest`; `ProfileValidationRules`.
  - `ProtectedAccountGuard::isProtectedAddress` uses the shared normalisation (AC7, AC8).
  - Login throttle key is normalised. Login relies on Fortify `lowercase_usernames`.
  - `docs/decisions.md` (one line), `docs/privacy/data-inventory.md` (stored form).
- Removed: none. The old unique index on `email` is kept for exact-match lookups.
- Migrations: yes, `2026_11_11_100000_normalise_user_emails` (data plus index).
- New env vars: none.
- Docker rebuild: not needed for dev (migrate only). Production gets the code in the next release image; run `migrate` as part of that deploy.
- Gate: PHP 835/835, front 323/323, e2e 144 passed / 2 skipped. QA PASS, security-privacy PASS.
- Known gate noise: the first full gate had timeout flakes (3 front, 11 e2e). They passed on an isolated rerun; tracked in TE-050.
