---
id: TE-010
title: i18n key-completeness check (fails on missing or unused keys)
status: done
priority: must
epic: M0 i18n infrastructure
agents: [frontend-developer, devops-engineer]
testers: [qa-engineer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer, I want a check that fails when a key exists in only one language, so that no screen ships half
translated.

## Context
- `docs/plan.md` § 4 M0 ("a CI check that fails on keys missing in either language"). Principle 5 in `CLAUDE.md`.
- **Depends on:** TE-009. **Feeds:** TE-013 (CI runs it).

## Acceptance criteria
- [x] AC1: Given a key in `lang/nl.json` but not in `lang/en.json` (or the reverse), when `npm run check` (or the named
      script `npm run i18n:check`) runs, then it exits non-zero and lists each missing key with the file it is missing from.
- [x] AC2: Given a key whose value is empty or equal to the key itself in either file, when the check runs, then it fails.
- [x] AC3: Given both files contain the same placeholder names per key (`:name`), when a placeholder differs between nl and
      en, then the check fails and names the key.
- [x] AC4: Given a static `t('literal.key')` call in `resources/js` or `__('literal.key')` in `app/`/`resources/views`
      that has no entry in the files, when the check runs, then it fails with file and line.
- [x] AC5: Given a key that no source file references, when the check runs, then it reports it as a warning (not a
      failure), because dynamic keys exist; dynamic key prefixes can be allow-listed in one config file.
- [x] AC6: Given both files are consistent, when the check runs, then it exits 0 in under 5 seconds.

## Quality criteria
- [x] Tests: Vitest fixtures for AC1 to AC5 (temp lang dirs); the real files pass.
- [x] Accessibility: n/a.
- [x] Security/privacy: the script only reads files; no network.
- [x] i18n: this story is the guard for the criterion in every other story.
- [x] Performance: AC6.
- [x] Docs updated: `docs/testing.md` (command, how to allow-list a dynamic prefix), `CLAUDE.md` Checks row.

## Out of scope
- Machine translation, translation-management tools, pluralisation beyond what TE-009 supports.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
Done 2026-11-02 (qa-engineer PASS; commit aac3053 on `feat/m0-batch-3`).
- **Added:** `npm run i18n:check` (`scripts/i18n-check.ts`, config `i18n-check.config.json` with the dynamic-prefix allow-list,
  runs in about 0.9 s); fixtures in `i18n-check.test.ts`. It is part of `npm run check`. TE-013 can call `npm run i18n:check`.
- **Changed:** `docs/testing.md` and the CLAUDE.md Checks row describe the command.
- **Removed:** nothing. **Migrations:** none. **New env vars:** none. **Docker rebuild:** not needed.
- **Follow-ups (README, "Follow-ups for M1"):** real keys for `Profile updated.` / `Password updated.`; remove or use `nav.settings`.
