---
id: TE-051
title: Restore procedure hardening (app.env cleanup, users_id_seq reset, doc corrections)
status: ready
priority: must
epic: Privacy before go-live (alongside TE-039); follow-up to TE-048
agents: [devops-engineer, database-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner restoring a database dump, I want the restore procedure to leave no secrets behind and to keep user IDs unique across restores, so that an erased person cannot be missed by the ID diff and APP_KEY and DB_PASSWORD never linger beside the dumps.

## Context
Docs-only follow-up to TE-048 (security review findings: 2 Low, 1 Info). The devops-engineer writes the changes; the database-engineer reviews the sequence reset (`pg_restore` resets `users_id_seq` to the dump's value). Section: "Restore and erasures" and Restore step 6 in `docs/deploy/casaos-portainer.md`; claim "IDs are never reused" also in `docs/privacy/data-inventory.md` ("Re-erasure after a restore").

## Acceptance criteria
- [ ] AC1: Given erasure step 3/4, when the erasure run fails and the owner stops at step 4, then no `app.env` (APP_KEY, DB_PASSWORD) remains beside the dumps: the docs use `--env-file <(docker inspect ...)` or run `rm -f app.env` right after the `docker run`, with the exact command lines.
- [ ] AC2: Given the restore finished (RESTORE OK), when the procedure continues, then it runs `select setval('users_id_seq', greatest((select max(id) from users), <max of ids-before.txt>))` before the diff/erasure, with the exact command line and how to take the max from `ids-before.txt`.
- [ ] AC3: Given the docs, when read, then the claim "IDs are never reused" is corrected to state what actually holds (IDs are not reused only because of the sequence reset) in both `docs/deploy/casaos-portainer.md` and `docs/privacy/data-inventory.md`, and a sentence explains the collision risk when restoring an older dump.
- [ ] AC4: Given step 4, when read, then "check the container logs" is replaced by: errors and the refused/failed counts are printed on the terminal (stderr, because of `--rm`); no container log exists.
- [ ] AC5: Given Restore step 6, when read, then it states that `db` and `ssr` run while `app` stays stopped (not only `db`).
- [ ] AC6: Given the changed procedure, when a dry run is done on a scratch database in the dev stack (as in TE-048 AC7), then the erased test account is gone before `app` starts, no `app.env` remains afterwards, and the sequence value is at least the highest ID ever listed; the result is noted in the changelog.

## Quality criteria
- [ ] Tests: dry run of the changed commands (AC6); `bash scripts/check.sh static` still green.
- [ ] Accessibility: not applicable (docs only).
- [ ] Security/privacy: no secrets or personal data left on disk after any exit path of the procedure; reviewer confirms an erased ID cannot collide with a newer live user.
- [ ] i18n: docs in English.
- [ ] Performance: not applicable.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/privacy/data-inventory.md`.

## Files to touch
- `docs/deploy/casaos-portainer.md`: section "Restore and erasures" (steps 3 to 5) and Restore step 6 (`grep -n "Restore and erasures"`).
- `docs/privacy/data-inventory.md`: "Re-erasure after a restore".
- Reference: `app/Console/Commands/EraseUsers.php` for output channel and exit codes.

## Out of scope
- Command and service code (TE-047).
- Automating restores or the `backup` service (TE-026/TE-031).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
