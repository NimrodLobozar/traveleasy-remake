---
id: TE-049
title: Normalise e-mail case so the protected super_admin address cannot be claimed
status: ready            # draft | ready | in-progress | in-test | rework | done
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
- [ ] AC1: Given a user `admin@example.com` exists, when a guest registers with `Admin@Example.com`, then the form shows the e-mail-taken error on the e-mail field (linked, in NL and EN) and no row is created.
- [ ] AC2: Given `SUPER_ADMIN_EMAIL=Owner@Example.com` and no account yet, when a guest registers with `owner@example.com`, then registration is refused with the same e-mail-taken error, and when the owner account is provisioned it is stored as `owner@example.com`.
- [ ] AC3: Given a customer whose stored address is `jan@example.com`, when they save their profile with `  Jan@Example.COM ` (own address, other case or spaces), then the save succeeds, the stored value is `jan@example.com` (trimmed, lowercase), and no unique-error is shown.
- [ ] AC4: Given a guest with the account `jan@example.com`, when they log in with `Jan@Example.com`, then they are signed in to that account only, and no second account is created.
- [ ] AC5: Given the database, when a row whose e-mail differs only by case from an existing row is inserted directly (seeder, tinker or a second code path), then PostgreSQL rejects it (unique index on `lower(email)`), and the existing unique index on `email` is dropped only if it becomes redundant.
- [ ] AC6: Given the migration that normalises existing data, when `users` contains mixed-case e-mails, then they are lowercased and trimmed in one transaction; if two rows collide after normalisation, the migration aborts with a message naming no e-mail address, changes nothing, and the owner resolves the duplicate first.
- [ ] AC7: Given `ProtectedAccountGuard`, when it checks an address, then it applies the same normalisation as storage (trim, lowercase), and a unit test covers mixed case, surrounding spaces and an unset `SUPER_ADMIN_EMAIL` (protects nothing, no error).
- [ ] AC8: Given `users:erase <id>` or self-service deletion, when the target is the account whose normalised e-mail equals `SUPER_ADMIN_EMAIL`, then it is refused (`REFUSED`, nothing changed), and a feature test proves this for an account stored as `Owner@Example.com` (set by a case-variant insert before normalisation was in place).

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
(filled when done)
