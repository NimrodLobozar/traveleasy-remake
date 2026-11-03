---
id: TE-032
title: Safe dependency updates from Dependabot PRs #1-#3 (2026-11)
status: done
priority: should
epic: Maintenance
agents: [devops-engineer]
testers: [qa-engineer, security-privacy-reviewer, accessibility-auditor]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the safe dependency updates from Dependabot PRs #1 to #3 applied and tested, so that the stack stays
patched without breaking anything; risky major upgrades get their own story.

## Context
Dependabot opened three grouped PRs in November 2026: #1 Docker images, #2 Composer, #3 npm. This story takes only the
patch and minor updates, applies them on our own branch `chore/deps-2026-11`, and runs every suite. Majors and breaking
0.x minors are deferred (AC4). Related: TE-013 (Dependabot, pinned Playwright image), TE-014 (image scans),
TE-006 (exact stack versions in `CLAUDE.md`), `docs/testing.md` (paratest and Playwright sync rules),
`package.json` `"//"` note (manual Vite advisory check).

## Acceptance criteria
- [x] AC1: Given PR #1 (Docker images), when the change is applied, then the Playwright image is `v1.64.1-noble`, pinned by
      digest, **together with** `@playwright/test` 1.64.1 (exact pin), so the Pest sync test passes; and Adminer is 6.2.0
      in the dev compose (profile `tools`).
- [x] AC2: Given PR #2 (Composer), when the change is applied, then `composer.lock` contains inertia-laravel 3.6.0,
      laravel/framework 13.39.0, laravel/wayfinder 0.1.23, spatie/laravel-data 4.23.1, larastan 3.13.2 and
      symfony/yaml 8.1.10; Larastan at level max reports zero errors with no baseline file added.
- [x] AC3: Given PR #3 (npm, patch and minor only), when the change is applied, then `package-lock.json` resolves:
      @inertiajs/react 3.10.0, vite (alias of `@voidzero-dev/vite-plus-core`) 3.10.0, @laravel/vite-plugin-wayfinder 0.1.11,
      the Radix packages to their latest 1.x/2.x minor in the PR, react and react-dom 19.3.1, axe-core and
      @axe-core/playwright 4.14.0, @testing-library/user-event 14.6.8, jsdom 30.1.3, the rollup native packages, and
      @types/node within the 24.x line only. The `vite-plus` pin stays at exact 0.3.3.
- [x] AC4: Given the deferred items, then none is in `package.json` or the lockfiles, and each is listed under
      "Follow-ups (2026-11 dependency updates)" in `docs/stories/README.md` with its reason: TypeScript 7 (major),
      vite-plus 1.x (major, exact pin stays 0.3.3), lucide-react 1.x (major), @laravel/passkeys 0.4 (0.x minor is
      breaking), @rolldown/plugin-babel 0.3 (0.x minor), @types/node 26 (not Node 24). Each follow-up names the story it
      needs.
- [x] AC5: Given the change is on `chore/deps-2026-11`, when the dev containers are rebuilt with fresh dependency volumes
      (`docker compose rm -s -f -v app vite` then `docker compose up -d --build`, DB volume kept), then all of these are
      green: `docker compose exec app composer check`, `docker compose exec vite npm run check`,
      `docker compose exec vite npm run test`, `docker compose exec app composer test:parallel`,
      `docker compose run --rm e2e` (full run, axe included), and `bash scripts/dev-ssr.sh smoke`. The production image
      builds, and `scripts/ci/verify-production-stack.sh` passes.
- [x] AC6: Given the versions are final, then the CLAUDE.md versions table matches the lockfiles (PHP, Node and
      PostgreSQL rows unchanged unless touched), and the manual Vite advisory check from the `package.json` `"//"` note is
      done and recorded in the Changelog (advisory IDs checked, result, date).
- [x] AC7: Given the work is complete, when it is merged to `main` by the owner, then PRs #1, #2 and #3 are closed with a
      comment naming the merge commit, the deferred items from AC4 are noted on PR #3, and no Dependabot branch is left
      open for these groups. The `npm audit` dev-only items (undici via jsdom, vitest / @vitest/mocker) are resolved if a
      non-major update allows it; if not, the remaining items are listed in the Changelog with the reason. (orchestrator, at merge)
- [x] AC8: Given the Trivy scan of the production image runs in CI, then no new HIGH or CRITICAL finding is introduced
      compared with `main`, and no entry is added to `.trivyignore` without a written reason in the Changelog.

## Quality criteria
- [ ] Tests: the existing suites are the test; no test is skipped or weakened to pass. Pest sync test for the Playwright
      image and npm tag is green.
- [ ] Accessibility: axe e2e suite green with the axe-core 4.14.0 upgrade; no new allow-list entries.
- [ ] Security/privacy: `security-privacy-reviewer` checks the lockfile diffs (no new runtime CDN or tracker dependency,
      no new install scripts beyond the rollup native packages), the Playwright image digest, and the `composer audit` and
      `npm audit` output before and after.
- [ ] i18n: no UI strings change; `npm run i18n:check` stays green.
- [ ] Performance: production build size is not larger than `main` by more than 5 percent (report the numbers in the Changelog).
- [ ] Docs updated: `CLAUDE.md` versions table; `docs/stories/README.md` (row, Follow-ups section); `docs/testing.md` if the
      Playwright or paratest pins change.

## Out of scope
- TypeScript 7, vite-plus 1.x, lucide-react 1.x, @laravel/passkeys 0.4, @rolldown/plugin-babel 0.3, @types/node 26
  (listed as follow-ups, AC4).
- Any change to application code beyond what the updated packages strictly require (an API break needs its own story).
- Merging or deploying to the home server (TE-026 is owner-gated).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
- 2026-11-03 (devops): applied on `chore/deps-2026-11`. Composer: inertia-laravel 3.6.0, laravel/framework 13.39.0, wayfinder 0.1.23, laravel-data 4.23.1, larastan 3.13.2, symfony/yaml 8.1.10 (pinned to the story versions; newer inertia-laravel 3.7.0 / larastan 3.14.0 / @inertiajs 3.11.0 were not taken). npm: AC3 set; `@types/node` ^24.19.2; vite-plus and the vite alias stay 0.3.3 (the story's "vite 3.10.0" is not applied, it is the pinned alias); @playwright/test exactly 1.64.1; Playwright image v1.64.1-noble@sha256:2f05f8a7...; Adminer 6.2.0.
- Larastan: `routes/testing.php` needed the same `@phpstan-ignore method.nonObject` as `routes/web.php` for `Route::inertia()` (no baseline added).
- Suites: composer check green (759 tests, 4713 assertions; parallel 759 green), npm check green, vitest 323/323, e2e 144 passed / 2 skipped (a first run had 7 load-related failures in complex-components 320px dark; the spec passed alone and the full rerun was green), dev-ssr smoke + off OK, production image builds, verify-production-stack 31/0.
- Vite advisory check (2026-11-03), bundled Vite 8.3.0 (vite-plus 0.3.3): GHSA-rq7h-c2jc-7f22, GHSA-vfpm-58rq-9qcg (medium) and GHSA-9jrq-w75r-8gcw (low), all patched in 8.3.3, concern the dev server (`server.fs`, `?vite-wasm-instance`, custom middleware). Not exploitable in production (static build, no Vite server); dev ports bind to 127.0.0.1. GHSA-8g5g-mxqh-grgj is v6 only. Fixed only by a newer vite-plus (deferred, major pin).
- Remaining audits: `npm audit --omit=dev` 0 vulnerabilities. `npm audit` 5 (4 moderate, 1 high), all dev-only: @vitest/mocker, @vitest/browser(-preview) and vite-plus (bundled vitest); fix needs `--force` (vite-plus major), deferred. `composer audit`: phpunit 13.4.1 (PKSA-qhjm-rpqc-qkn6, medium, PHPT coverage temp dir; dev-only) cannot move because pest 5.3.1 conflicts with phpunit 13.4.4+; resolved when Pest allows it.
- Production build size comparison with `main` and the Trivy comparison (AC8) are not measured here; CI decides.
- 2026-11-03 **Closed**, done, 0 rework rounds. Testers:
  - qa-engineer PASS: Vitest 323/323, Pest 759/759, full e2e run twice at 144 passed + 2 skipped, no failures.
  - security-privacy-reviewer PASS: Trivy shows no new High or Critical; the npm/Vite dev-only advisories are accepted (Vite bound to 127.0.0.1); phpunit PKSA-qhjm-rpqc-qkn6 is accepted as a dev-only medium (blocked by Pest 5.3.1).
  - accessibility-auditor PASS: axe tags still include wcag22aa, no allow-list growth, base-components 12/12.
- Commit: d69dd9c. Branch CI: run 41645319706.
- Open follow-ups (in `docs/stories/README.md`): vite-plus 1.x (next, due before 2026-12-15); phpunit advisory (move once Pest allows phpunit ≥ 13.4.4).
