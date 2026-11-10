---
id: TE-043
title: Fix actionlint SC2086 in the weekly scan workflow (CI red on dev and PR #17)
status: in-progress
priority: must
epic: none
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the "Workflow lint (actionlint)" CI job to pass on `dev` and on PRs, so that a red lint job means a real problem and not a shellcheck info message.

## Context
- Bug. The CI job "Workflow lint (actionlint)" fails on `dev` since at least commits bef4866, 82d0b4a and 4149674, and on PR #17.
- actionlint's shellcheck reports `SC2086:info:8:88: Double quote to prevent globbing and word splitting` for `.github/workflows/scan-weekly.yml`, the `run: |` block of the Trivy scan step (file line 43; script line 8, col 88). That is `$ignore` on the `docker run` continuation line.
- Likely cause: a newer actionlint/shellcheck in the pinned image, or the TE-036 edit that added the per-image `$ignore` variable (`.trivyignore.yaml` applies to the dev image only).
- Quoting `"$ignore"` would be wrong: an empty value would pass an empty argument to Trivy. The proper fix is a bash array, so the flag is added only when set.
- Related: TE-036 (scoped Trivy ignores), TE-014 AC5 (weekly scan).

## Acceptance criteria
- [ ] AC1: Given the pinned actionlint image that CI uses (same image and digest as the CI job), when it runs on `.github/workflows/scan-weekly.yml` before the fix, then it reports SC2086 at line 43; the developer records the command and output in the handoff.
- [ ] AC2: Given the fix, when actionlint runs on all files in `.github/workflows/`, then it reports no findings (exit 0, no shellcheck info, warning or error).
- [ ] AC3: Given the fix, when the scan step runs, then the dev image is scanned with `--ignorefile .trivyignore.yaml` and the Playwright image without an ignore file, both with `--severity HIGH,CRITICAL --exit-code 1`, and the step's exit status is unchanged (any High/Critical finding fails the run). The developer shows the final `docker run` command line in the handoff.
- [ ] AC4: Given the PR that contains the fix, when CI runs, then the "Workflow lint (actionlint)" job is green on the PR.

## Quality criteria
- [ ] Tests: actionlint run locally with the CI image (AC1, AC2). No new code tests; the workflow is verified by AC4 and by the next scheduled or manual run of "Weekly scan" (`workflow_dispatch`).
- [ ] Accessibility: n/a.
- [ ] Security/privacy: reviewer confirms the Trivy flags and the exit-code logic are unchanged, that the ignore file is still used only for the dev image, and that no permission or secret handling changed (`permissions: contents: read`, `persist-credentials: false`).
- [ ] i18n: n/a.
- [ ] Performance: n/a.
- [ ] Docs updated: none expected; if `docs/testing.md` lists the actionlint command, keep it accurate.

## Files to touch
- `.github/workflows/scan-weekly.yml` (scan step, `run: |` block, the `ignore` variable around file lines 47-52): change `ignore=""` to an array (`ignore=()`, `ignore=(--ignorefile .trivyignore.yaml)`) and pass `"${ignore[@]}"` to `docker run`.
- Tests: none (CI lint job is the check).

## Out of scope
- The "Release" job skipping on `pull_request`: expected, it runs only on `main` and tags.
- Changing the Trivy version, severities or ignore contents.
- Other actionlint findings, if any appear after this fix (raise a new story).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
