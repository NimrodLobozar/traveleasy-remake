---
id: TE-042
title: Manual dump retention rule, restore re-erasure note, umask order
status: done
priority: should
epic: M0 First home-server deploy (follow-up of TE-040; before TE-034)
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the manual database dumps to have a deletion rule and safe permissions, so that personal data is not kept longer than needed and a restore does not bring back erased data.

## Context
- Non-blocking follow-ups from the TE-040 security review (round 2). No existing backup story covers them (the scheduled `backup` service is part of the split TE-026/TE-031).
- Note (2026-11-10): the umask order (AC3) was found in the TE-041 dry run (D4) and is fixed there in the doc; here only verify it stays correct. ACs unchanged.
- Doc: `docs/deploy/casaos-portainer.md` (dump section, about lines 325-360); inventory row for manual dumps in `docs/privacy/data-inventory.md`.

## Acceptance criteria
- [x] AC1 (Medium): Given the doc's manual dumps in `~/traveleasy-backups`, when read, then it gives a deletion rule: delete an older dump once a newer one is verified, and never keep a manual dump longer than 30 days, with the exact command to list and delete them.
- [x] AC2 (Medium): Given a restore from a dump, when read, then the doc states that erasures (account deletions, data-subject requests) made after the dump was taken must be re-applied after the restore, and how the owner finds them.
- [x] AC3 (Low): Given the dump steps (about `casaos-portainer.md:337`), when read, then `umask 077` comes before `mkdir -p ~/traveleasy-backups`, so the folder is 0700 and dumps are 0600.
- [x] AC4: Given the changes, when `docs/privacy/data-inventory.md` is read, then the manual-dump row states the 30-day maximum and matches the doc.

## Quality criteria
- [x] Tests: doc-only; the commands were exercised in the TE-041 dry run (which found the umask order, D4).
- [x] Accessibility: n/a.
- [x] Security/privacy: reviewer confirms retention, permissions and the erasure re-apply note (PASS, twice).
- [x] i18n: n/a.
- [x] Performance: n/a.
- [x] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/privacy/data-inventory.md`.

## Files to touch
- `docs/deploy/casaos-portainer.md`: dump section (umask order, retention, restore note).
- `docs/privacy/data-inventory.md`: manual-dump row.

## Out of scope
- The scheduled backup service (TE-026/TE-031).

## Open questions
- (none)

## Rework log

### Review follow-up (not a failed round; rework_rounds stays 0) - 2026-11-11
QA and security both PASSED; the owner wants the security review's findings fixed before closing. Clarified criteria:
- **R1 (Medium, `docs/deploy/casaos-portainer.md` re-erasure step):** the step could not be done: self-service deletions are not in the owner's request log, there is no admin or artisan erasure, and "check the safety dump" had no command. Required: before the restore, save current user IDs (`psql -Atc "select id from users"` into a file written under `umask 077`). After `RESTORE OK`, diff them against the restored IDs; any ID that is back was erased after the dump (bigint sequence IDs, never reused). Until an erasure command exists the doc tells the owner how to remove those accounts and flags it as interim (replaced by TE-047). The ID files are deleted afterwards.
- **R2 (Low, retention paragraph):** use `find -mtime +29` so the 30-day limit holds. Resolve the contradiction "never delete the newest verified dump" vs the 30-day maximum: 30 days wins once the update the dump guarded is confirmed good. `docs/privacy/data-inventory.md` (Manual DB dumps row) kept consistent.
- Fix applied by task-writer in the two docs; needs a re-check by security-privacy-reviewer (R1, R2). Status left for the orchestrator.

## Changelog
- **Added:** 30-day maximum for manual dumps with list/delete commands (`find -mtime +29`); restore re-erasure procedure by user-ID diff (interim, replaced by TE-047).
- **Changed:** `umask 077` now runs before `mkdir -p ~/traveleasy-backups` (folder 0700, dumps 0600); `docs/privacy/data-inventory.md` manual-dump row states the 30-day maximum.
- **Removed:** nothing.
- **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed (docs only).
- **Commits:** e2334b8, ee8a1ce, and the R1/R2 fix commit.
- **Testers:** qa-engineer PASS (static gate: pint, larastan, front-static green); security-privacy-reviewer PASS, initial and after the R1/R2 follow-up.
- **Follow-up:** TE-047 replaces the interim SQL erasure step and carries the three restore-procedure fixes.
