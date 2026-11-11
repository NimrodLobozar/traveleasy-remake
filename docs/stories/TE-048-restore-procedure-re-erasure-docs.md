---
id: TE-048
title: Restore procedure rewrite with ID-based re-erasure (docs)
status: ready
priority: must
epic: Privacy before go-live (alongside TE-039); order TE-047 (code) then TE-048
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner restoring a database dump, I want a restore procedure that re-applies erasures before the app serves traffic, so that an erased person can never log in again after a restore.

## Context
Split from TE-047 (size gate). Depends on TE-047 (the `users:erase --from-file` command must exist). Rewrites "Restore and erasures" in `docs/deploy/casaos-portainer.md`, which carries an interim manual SQL step that the command replaces. TE-042 re-review points (line numbers approximate):
- Restore order (about :384/:398): `app` is started before re-erasure, so erased accounts can log in for a few minutes. Do the ID diff and the erasure before starting `app`.
- Interim SQL cleanup (about :404): `sessions.user_id` has no foreign key, so the cleanup must name `sessions`; with the command, `sessions` is covered by the service. `passkeys` cascades.
- Step 2 (about :398/:401): `comm` uses relative paths and the step lacks `umask 077`. Add `cd ~/traveleasy-backups` and `umask 077`, and show the "after" ID listing (`psql ... "select id from users order by id" > ids-after-restore.txt`).

## Acceptance criteria
- [ ] AC1: Given the restore procedure, when read, then the interim SQL step is replaced by `php artisan users:erase --from-file=<ids>` run on the diffed IDs, with the exact command lines for the production container.
- [ ] AC2: Given the restore procedure, when followed in order, then the ID diff and the erasure happen before `app` is started (the app stays stopped, only `db` runs), and a sentence states why (erased accounts could otherwise log in).
- [ ] AC3: Given the "after" step, when read, then it begins with `cd ~/traveleasy-backups` and `umask 077`, shows the "before" and "after" ID listing commands (`select id from users order by id` into `ids-before.txt` / `ids-after-restore.txt`), and the `comm` call uses those files.
- [ ] AC4: Given the procedure, when read, then it states that a failed or non-zero erasure run (refused/failed counts) blocks starting `app`, and what to do next (re-run, inspect, ask the owner).
- [ ] AC5: Given the procedure, when read, then the temporary ID files are deleted after the run (with the command) and contain only numeric IDs.
- [ ] AC6: Given `docs/privacy/data-inventory.md`, when compared with the procedure and the erasure service, then every table listed there with personal data names how re-erasure covers it, and the 30-day dump limit wording matches `docs/deploy/casaos-portainer.md`.
- [ ] AC7: Given the docs, when a dry run is done in the dev stack (restore an older dump into `traveleasy-remake` dev DB, run the documented steps), then the erased test account is gone before `app` starts; the result is noted in the story changelog.

## Quality criteria
- [ ] Tests: dry-run of the documented commands in the dev stack (AC7); `bash scripts/check.sh static` still green (markdown/link checks if any).
- [ ] Accessibility: not applicable (docs only).
- [ ] Security/privacy: no personal data in the ID files or examples; reviewer confirms erased users cannot authenticate at any point of the procedure.
- [ ] i18n: docs in English.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/privacy/data-inventory.md`; link `docs/privacy/retention.md` and `privacy-statement.md` once they exist.

## Files to touch
- `docs/deploy/casaos-portainer.md`: section "Restore and erasures" (about :380-:410; find with `grep -n "Restore and erasures"`).
- `docs/privacy/data-inventory.md`: re-erasure column per table.
- Reference: `app/Console/Commands/EraseUsers.php` (from TE-047) for the exact options and output.

## Out of scope
- The command and service code (TE-047).
- Automating restores or the scheduled `backup` service (TE-026/TE-031).
- Writing `docs/privacy/retention.md` and `privacy-statement.md`.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
