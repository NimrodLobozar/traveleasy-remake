---
id: TE-047
title: Admin/artisan account erasure and ID-based re-erasure (code)
status: done
priority: must
epic: Privacy before go-live (alongside TE-039); order TE-047 (code) then TE-048 (restore doc)
agents: [database-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner/admin, I want to erase a user account by ID with a command, and re-apply erasures from a list of IDs after a database restore, so that an erased person never comes back with a restored dump.

## Context
From the TE-042 security review (R1). Self-service account deletion (`ProfileController::destroy`, which only calls `$user->delete()`) is the only erasure today; there is no admin or artisan erasure. The AuditLog cannot drive re-erasure because a restore rolls it back too, so re-erasure works from user IDs (bigint sequence values, never reused) taken from the pre-restore state or the ID diff. Related: TE-042 (doc), TE-039 (also before go-live), TE-048 (restore-procedure rewrite, depends on this story). No existing story covers erasure (board checked).

**Personal-data tables that exist today** (checked in the migrations and code index): `users` (name, e-mail, password hash, two-factor secrets), `sessions` (`user_id` has no foreign key, so it must be deleted explicitly), `passkeys` (cascades on user delete), plus cache/jobs tables (no personal data by design). There is no `password_reset_tokens` table (no e-mail flows, ADR-0005). **Not present yet:** persons, passport/ID data, travellers, messages, invoices, `audit_logs`, roles/`super_admin`. Criteria below are worded against what exists. Tables added later (persons, travellers, messages, invoices) must hook into the shared erasure service (an eraser registry that each such story extends, with a test); invoices are then retained 7 years with personal fields anonymised, never deleted. Roles and `super_admin` do not exist yet: the service takes the protection check from one place (a `isProtected(User)` guard); until the roles story lands it is fed by the env-provisioned `super_admin` identifier, and that story must wire the real check.
Until the roles story, `super_admin` is recognised through the `SUPER_ADMIN_EMAIL` env identifier (owner decision 2026-11-11); the roles story must replace `ProtectedAccountGuard` with the real role check.

## Acceptance criteria
- [x] AC1: Given an existing user, when an admin runs `php artisan users:erase <id>`, then the user row, that user's `sessions` rows and passkeys are removed through one shared `AccountEraser` service, the command prints the ID and `ERASED`, and the self-service `ProfileController::destroy` calls the same service (its existing test still passes).
- [x] AC2: Given an ID that does not exist or is already erased, when the command runs, then it exits 0 with `NOT FOUND (already erased)` and changes nothing (idempotent, safe to re-run after a restore).
- [x] AC3: Given a file or stdin with one user ID per line (`--from-file=<path>` or `-`), when run, then each ID is erased in its own transaction, blank lines and non-numeric lines are counted as failed, a failure on one ID does not stop the others, the file is streamed, and the summary lists counts of erased, not found and failed with a non-zero exit code if any failed.
- [x] AC4: Given the protected `super_admin` account, when erasure is requested by the command (single or file), then it is refused (`REFUSED`, exit code non-zero, nothing changed), and self-service deletion of that account is also refused.
- [x] AC5: Given the service, when a later story adds a table with personal data, then it registers an eraser with the service (tested with a stub eraser that is invoked inside the same transaction, and rolled back if it throws); invoice retention/anonymisation (7 years, never deleted) is documented in the service docblock and `docs/privacy/data-inventory.md` for the story that adds invoices.
- [x] AC6: Given an erasure, when it completes, then a new `audit_logs` row records event `account_erased`, the erased user ID (no foreign key), the actor (`cli`, or the admin's user ID once an admin path exists) and the time, and no name or e-mail is written to the table, the application log or the command output.
- [x] AC7: Given any HTTP client (guest, customer, or any signed-in user), when it tries to erase another user, then no such route exists (404 on a probed `DELETE /users/{id}` and `/admin/users/{id}`), and `php artisan route:list` contains no erasure route other than the self-service profile deletion, which only ever acts on the signed-in user.
- [x] AC8: Given a database restored from a dump taken before an erasure, when the ID list from the diff is passed with `--from-file`, then the erased accounts (and their `sessions` rows) are gone again (feature test that simulates restore by re-inserting the user and session rows).

## Quality criteria
- [x] Tests: unit for `AccountEraser` (stub eraser, rollback on failure, protected guard); feature tests for the command (single ID, file, stdin, idempotent, super_admin refused, bad lines, audit row without personal data); route probe test (AC7); self-service deletion regression. PostgreSQL only.
- [x] Accessibility: not applicable (no UI).
- [x] Security/privacy: no personal data in output, logs or `audit_logs`; `super_admin` protection; the command is not reachable over HTTP; reviewer confirms the erasure covers every table with personal data in `docs/privacy/data-inventory.md` as it exists today.
- [x] i18n: command output is English (operator tooling); no UI strings.
- [x] Performance: erasure of one user completes in one transaction; file mode streams the file.
- [x] Docs updated: `docs/privacy/data-inventory.md` (erasure coverage per table, `audit_logs`), `docs/decisions.md` (one line). The restore procedure in `docs/deploy/casaos-portainer.md` is TE-048.

## Files to touch
- `app/Http/Controllers/Settings/ProfileController.php` (`destroy`, lines 48-60): call the shared service instead of `$user->delete()`; keep logout and session invalidation.
- `app/Services/AccountEraser.php`: new (shared service, eraser registry, protected guard, audit write).
- `app/Console/Commands/EraseUsers.php`: new (`users:erase {id?} {--from-file=}`).
- `database/migrations/<date>_create_audit_logs_table.php`, `app/Models/AuditLog.php`: new (id, event, subject_user_id nullable no FK, actor, created_at).
- Reference only: `database/migrations/0001_01_01_000000_create_users_table.php` (`sessions.user_id` has no FK), `2024_01_01_000000_create_passkeys_table.php` (cascades).
- Tests: `tests/Feature/Console/EraseUsersTest.php`, `tests/Feature/Settings/` (add a deletion regression next to `ProfileUpdateTest.php`), `tests/Unit/AccountEraserTest.php`.
- `docs/privacy/data-inventory.md`, `docs/decisions.md`.

## Out of scope
- The restore-procedure rewrite in `docs/deploy/casaos-portainer.md` (TE-048).
- An admin UI for erasure and data-subject requests (separate story if wanted).
- Writing `docs/privacy/retention.md` and `docs/privacy/privacy-statement.md` (needed before go-live, owned by the security-privacy-reviewer).
- Erasers for persons, travellers, messages and invoices (the stories that add those tables).
- The scheduled `backup` service and its restore test (TE-026/TE-031).

## Open questions
- Resolved 2026-11-11 (owner): protect `super_admin` via the env-provisioned `SUPER_ADMIN_EMAIL` until the roles story wires the real check (AC4).

## Rework log
(empty)

## Changelog
- **Added:** `app/Services/AccountEraser.php` (shared erasure service, eraser registry tagged `account.erasers`, locked transaction per user, audit row); `app/Services/Erasure/*` (eraser contract and the built-in erasers for `users`, `sessions`, passkeys); `app/Services/Erasure/ProtectedAccountGuard.php` (`isProtected(User)`, compares against `SUPER_ADMIN_EMAIL`, case-insensitive); `app/Console/Commands/EraseUsers.php` (`users:erase {id?} {--from-file=}`, `-` reads stdin); `app/Models/AuditLog.php`, `database/factories/AuditLogFactory.php`; tests in `tests/Unit/AccountEraserTest.php`, `tests/Feature/Console/`, `tests/Feature/Models/`, `tests/Feature/Security/NoErasureRouteTest.php`, `tests/Feature/Settings/ProfileDeletionTest.php`.
- **Changed:** `ProfileController::destroy` now calls `AccountEraser` (logout and session invalidation kept); `SUPER_ADMIN_EMAIL` documented in `.env.example`, `compose.yaml`, `deploy/portainer.env.example`, `docs/deploy/casaos-portainer.md`; `tests/Unit/ProductionComposeContractTest.php` updated for the new variable; `docs/privacy/data-inventory.md` (erasure coverage, `audit_logs` 2-year retention target, cache prune note); `docs/decisions.md` (one line).
- **Removed:** nothing.
- **Migrations:** `create_audit_logs_table` (id, event, subject_user_id nullable with no foreign key, actor, created_at). Run `php artisan migrate` in dev; applied on the next production deploy.
- **New env vars:** `SUPER_ADMIN_EMAIL` (required in production; protected account, refused by erasure and self-service deletion). Set it in the Portainer stack before redeploying.
- **Docker:** dev needs only `php artisan migrate` (no rebuild). Production needs a new image tag (normal release) plus the new env var set in Portainer.
- **Tests:** QA PASS (php 792/792, front 23 files, e2e 144 passed / 2 skipped, static clean). Security/privacy PASS (no Critical/High).
- **Known limitations:** `--from-file` rejects FIFOs (`<(…)`); use `-` (stdin) instead. `ProtectedAccountGuard` is env-based until the roles story replaces its body with the real role check. Erasers for persons, travellers, messages and invoices are added by their own stories.
