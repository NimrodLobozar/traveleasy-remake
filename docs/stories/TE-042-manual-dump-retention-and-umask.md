---
id: TE-042
title: Manual dump retention rule, restore re-erasure note, umask order
status: ready
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
- Doc: `docs/deploy/casaos-portainer.md` (dump section, about lines 325-360); inventory row for manual dumps in `docs/privacy/data-inventory.md`.

## Acceptance criteria
- [ ] AC1 (Medium): Given the doc's manual dumps in `~/traveleasy-backups`, when read, then it gives a deletion rule: delete an older dump once a newer one is verified, and never keep a manual dump longer than 30 days, with the exact command to list and delete them.
- [ ] AC2 (Medium): Given a restore from a dump, when read, then the doc states that erasures (account deletions, data-subject requests) made after the dump was taken must be re-applied after the restore, and how the owner finds them.
- [ ] AC3 (Low): Given the dump steps (about `casaos-portainer.md:337`), when read, then `umask 077` comes before `mkdir -p ~/traveleasy-backups`, so the folder is 0700 and dumps are 0600.
- [ ] AC4: Given the changes, when `docs/privacy/data-inventory.md` is read, then the manual-dump row states the 30-day maximum and matches the doc.

## Quality criteria
- [ ] Tests: doc-only; the commands are exercised in TE-041 AC7 (or a note if TE-041 is already done).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: reviewer confirms retention, permissions and the erasure re-apply note.
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/privacy/data-inventory.md`.

## Files to touch
- `docs/deploy/casaos-portainer.md`: dump section (umask order, retention, restore note).
- `docs/privacy/data-inventory.md`: manual-dump row.

## Out of scope
- The scheduled backup service (TE-026/TE-031).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
