---
id: TE-047
title: Admin/artisan account erasure and ID-based re-erasure after restore
status: ready
priority: must
epic: Privacy before go-live (alongside TE-039)
agents: [backend-developer, database-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner/admin, I want to erase a user account by ID with a command, and re-apply erasures from a list of IDs after a database restore, so that an erased person never comes back with a restored dump.

## Context
From the TE-042 security review (R1). Self-service account deletion is the only erasure today; there is no admin or artisan erasure. The AuditLog cannot drive re-erasure because a restore rolls it back too. Re-erasure must therefore work from user IDs taken from the pre-restore state or from the ID diff described in `docs/deploy/casaos-portainer.md` ("Restore and erasures", which carries an interim manual SQL step that this story replaces). User IDs are bigint sequence values and are never reused. Related: TE-042 (doc), TE-039 (also before go-live). No existing story covers erasure (board checked).

**From TE-042 re-review** (all in `docs/deploy/casaos-portainer.md`; fix them when the restore procedure is rewritten):
- Restore order (about :384/:398): `app` is started before re-erasure, so erased accounts can log in for a few minutes after the restore. Do the ID diff and the erasure before starting `app`.
- Interim SQL cleanup (about :404): `sessions.user_id` has no foreign key (`create_users_table` migration, about :27), so the cleanup must name `sessions` explicitly. `passkeys` cascades.
- Step 2 (about :398/:401): `comm` uses relative paths, and step 2 does not repeat `umask 077`. Add `cd ~/traveleasy-backups` and `umask 077` to the step, and show the "after" ID listing command (`psql ... "select id from users order by id" > ids-after-restore.txt`).

Also needed before go-live, and not part of this story's code: `docs/privacy/retention.md` and `docs/privacy/privacy-statement.md` (security-privacy-reviewer); this story only links to them and updates the erasure wording once they exist.

## Acceptance criteria
- [ ] AC1: Given an existing customer, when an admin runs the erasure artisan command with that user ID (e.g. `php artisan users:erase <id>`), then the account and its personal data (person, passport/ID data, travellers, messages) are removed or anonymised exactly as self-service deletion does, via one shared service, and the command prints the ID and `ERASED`.
- [ ] AC2: Given an ID that does not exist or is already erased, when the command runs, then it exits 0 with `NOT FOUND (already erased)` and changes nothing (idempotent, safe to re-run after a restore).
- [ ] AC3: Given a file or stdin with one user ID per line (`php artisan users:erase --from-file=<path>`), when run, then each ID is erased in its own transaction, a failure on one ID does not stop the others, and the summary lists counts of erased, not found and failed with a non-zero exit code if any failed.
- [ ] AC4: Given the `super_admin` account or a user with retained invoices, when erasure is requested, then `super_admin` is refused (never erasable through this command) and invoices are kept as legally required (7 years) with personal fields anonymised, never deleted.
- [ ] AC5: Given an erasure, when it completes, then an AuditLog entry records the user ID, the actor (`cli` or admin ID) and the time, and no personal data (name, e-mail) is written to the log or the command output.
- [ ] AC6: Given a signed-in admin in the UI-less scope, when anyone other than the CLI operator tries to trigger erasure of another user via HTTP, then no such route exists (404); a manager, employee and customer cannot erase another user (policy test, 403 where a route exists).
- [ ] AC7: Given the restore procedure in `docs/deploy/casaos-portainer.md`, when read after this story, then the interim SQL step is replaced by the command with the `--from-file` usage on the diffed IDs, and `docs/privacy/data-inventory.md` is consistent.
- [ ] AC8: Given a database restored from a dump taken before an erasure, when the ID list from the diff is passed with `--from-file`, then the erased accounts are gone again (feature test that simulates restore by re-inserting the user rows).
- [ ] AC9: Given the restore procedure in `docs/deploy/casaos-portainer.md`, when read after this story, then it covers the three TE-042 re-review points (ID diff and erasure before `app` starts; `sessions` named explicitly in the cleanup; `cd ~/traveleasy-backups` and `umask 077` in the "after" step, with the "after" ID listing command shown).

## Quality criteria
- [ ] Tests: unit for the erasure service; feature tests for the command (single ID, file, idempotent, super_admin refused, invoice retention); authorization test (AC6). PostgreSQL only.
- [ ] Accessibility: not applicable (no UI).
- [ ] Security/privacy: no personal data in output or logs; `super_admin` protection; the command is not reachable over HTTP; reviewer confirms re-erasure covers every table with personal data in `docs/privacy/data-inventory.md`.
- [ ] i18n: command output is English (operator tooling); no UI strings.
- [ ] Performance: erasure of one user completes in one transaction; file mode streams the file.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md` (restore and erasures), `docs/privacy/data-inventory.md`, `docs/decisions.md` (one line).

## Files to touch
Fill from the code index when the story starts (the code index was unavailable when this story was written). Expected:
- The existing self-service delete-account action/controller (find with `search_code "delete"` in the Fortify/settings code): extract the logic into a shared erasure service.
- New artisan command `app/Console/Commands/EraseUsers.php` (or equivalent) and the service.
- Tests: `tests/Feature/Console/EraseUsersTest.php`, `tests/Unit/` for the service.
- `docs/deploy/casaos-portainer.md`, `docs/privacy/data-inventory.md`.

## Out of scope
- An admin UI for erasure and data-subject requests (separate story if wanted).
- Writing `docs/privacy/retention.md` and `docs/privacy/privacy-statement.md` (needed before go-live, owned by the security-privacy-reviewer).
- The scheduled `backup` service and its restore test (TE-026/TE-031).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
