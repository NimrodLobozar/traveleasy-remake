---
id: TE-037
title: Upgrade vite-plus to 1.x (bundled Vite, Vitest, oxlint, oxfmt)
status: ready
priority: should
epic: Maintenance (due before 2026-12-15)
agents: [frontend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the owner, I want the frontend toolchain on vite-plus 1.x, so that the known dev-server and dev-dependency advisories are fixed
and the audit ignores can expire on time.

## Context
- Deferred from TE-032 (major). `docs/stories/README.md` § Follow-ups: due **before 2026-12-15**, when the undici ignores in
  `.github/audit-ignore.json` expire.
- Today (`docs/tech-stack.md`): `vite-plus` exact pin 0.3.3; `vite` = npm alias of `@voidzero-dev/vite-plus-core` 0.3.3 (bundles Vite
  8.3.0); Vitest 4.1.11; oxlint 1.83.0 / oxfmt 0.68.0 (bundled). Deviation from ADR-0001 recorded in `docs/decisions.md`
  (2026-10-11, TE-003).
- Why: Vite 8.3.0 dev-server advisories GHSA-rq7h-c2jc-7f22, GHSA-vfpm-58rq-9qcg, GHSA-9jrq-w75r-8gcw (fixed in 8.3.3; dev only, dev
  ports bind to 127.0.0.1); `npm audit` 5 dev-only issues (@vitest/mocker, @vitest/browser(-preview), vite-plus) need the major.
- Keep the pin exact (no caret). Rebuild needed only because `package.json` and the lockfile change (dev image; production image
  builds from the lockfile in CI, so devops is involved only if the Dockerfile or compose must change).

## Acceptance criteria
- [ ] AC1: Given `package.json`, when read, then `vite-plus` is pinned to an exact 1.x version (no range), the `vite` alias follows
      its core package, and `docs/tech-stack.md` lists the new exact versions of vite-plus, Vite, Vitest, oxlint and oxfmt.
- [ ] AC2: Given the upgrade, when `bash scripts/check.sh front` and `bash scripts/check.sh static` run, then oxlint (type-aware, rules
      in `lint.rules.ts`), oxfmt check, `tsc`, `i18n:check` and the Vitest suite pass with no new lint suppressions and no rule
      weakened.
- [ ] AC3: Given the upgrade, when `npm run build` and the SSR build run (and `bash scripts/dev-ssr.sh smoke`), then both succeed and
      the SSR smoke test passes; the dev server (HMR) starts healthy in Docker.
- [ ] AC4: Given the upgrade, when `bash scripts/check.sh e2e` runs, then the e2e + axe suite passes as before.
- [ ] AC5: Given the copied Vite `fs.deny` defaults in `vite.config.ts`, when compared with the defaults of the bundled Vite 1.x
      version, then they match or the new defaults are adopted, and the choice is noted in `docs/tech-stack.md`.
- [ ] AC6: Given `npm audit` and `npm audit --omit=dev`, when run after the upgrade, then production has 0 vulnerabilities and the
      dev-only issues (@vitest/mocker, @vitest/browser(-preview), vite-plus, undici via jsdom) are gone or listed with reason; the
      undici entries in `.github/audit-ignore.json` are removed once no longer needed.
- [ ] AC7: Given the Vite advisories GHSA-rq7h-c2jc-7f22, GHSA-vfpm-58rq-9qcg and GHSA-9jrq-w75r-8gcw, when the bundled Vite version is
      checked, then it is 8.3.3 or newer.

## Quality criteria
- [ ] Tests: no test is deleted or skipped to get green; frontend, static and e2e runs listed in the handoff (log paths in
      `storage/logs/check/`).
- [ ] Accessibility: axe e2e unchanged and passing (no UI change expected).
- [ ] Security/privacy: reviewer re-checks `npm audit`, the Vite advisories and `.github/audit-ignore.json`; no new ignore without
      reason and expiry.
- [ ] i18n: n/a.
- [ ] Performance: production build output size and build time not materially worse (note both in the handoff).
- [ ] Docs updated: `docs/tech-stack.md`, `docs/decisions.md` (one line), the README follow-up bullet removed.

## Files to touch
- `package.json`, `package-lock.json`: vite-plus pin and alias.
- `vite.config.ts`: `fs.deny` defaults, any config renames in 1.x.
- `lint.rules.ts`, oxlint/oxfmt config files: only if 1.x renames rules or options.
- `.github/audit-ignore.json`: remove expired undici entries.
- `docs/tech-stack.md`, `docs/decisions.md`, `docs/stories/README.md` (follow-ups section).
- Tests: `resources/js/**/*.test.ts(x)` and `e2e/` specs only if the Vitest/Playwright API changes.
- Devops only if needed: `Dockerfile`, `compose.dev.yaml` (Node version or install step).

## Out of scope
- TypeScript 7, lucide-react 1.x, @laravel/passkeys 0.4, @rolldown/plugin-babel 0.3, @types/node 26 (each its own story).
- The phpunit advisory (waits on Pest).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
