---
id: TE-010
title: i18n key-completeness check (fails on missing or unused keys)
status: ready
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
- [ ] AC1: Given a key in `lang/nl.json` but not in `lang/en.json` (or the reverse), when `npm run check` (or the named
      script `npm run i18n:check`) runs, then it exits non-zero and lists each missing key with the file it is missing from.
- [ ] AC2: Given a key whose value is empty or equal to the key itself in either file, when the check runs, then it fails.
- [ ] AC3: Given both files contain the same placeholder names per key (`:name`), when a placeholder differs between nl and
      en, then the check fails and names the key.
- [ ] AC4: Given a static `t('literal.key')` call in `resources/js` or `__('literal.key')` in `app/`/`resources/views`
      that has no entry in the files, when the check runs, then it fails with file and line.
- [ ] AC5: Given a key that no source file references, when the check runs, then it reports it as a warning (not a
      failure), because dynamic keys exist; dynamic key prefixes can be allow-listed in one config file.
- [ ] AC6: Given both files are consistent, when the check runs, then it exits 0 in under 5 seconds.

## Quality criteria
- [ ] Tests: Vitest fixtures for AC1 to AC5 (temp lang dirs); the real files pass.
- [ ] Accessibility: n/a.
- [ ] Security/privacy: the script only reads files; no network.
- [ ] i18n: this story is the guard for the criterion in every other story.
- [ ] Performance: AC6.
- [ ] Docs updated: `docs/testing.md` (command, how to allow-list a dynamic prefix), `CLAUDE.md` Checks row.

## Out of scope
- Machine translation, translation-management tools, pluralisation beyond what TE-009 supports.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
