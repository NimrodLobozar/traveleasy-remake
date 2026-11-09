# Tech stack versions

Moved out of `CLAUDE.md` on 2026-11-09 (it's only needed for dependency work). Keep it current on every
dependency update. Stack choice and reasoning: `docs/adr/0001-tech-stack.md`.

**Versions** (2026-11-02; from `composer.lock`, `package-lock.json`, `Dockerfile` and the compose image tags):

| Component | Version | Component | Version |
|---|---|---|---|
| PHP (`php:8.5.12-cli-trixie`) | 8.5.12 | Node (`node:24.21.0-trixie-slim`) | 24.21.0 |
| Composer | 2.10.3 | npm | 11.19.0 |
| Laravel | 13.39.0 | React / react-dom | 19.3.1 |
| Fortify | 1.41.0 | @inertiajs/react | 3.10.0 |
| inertia-laravel | 3.6.0 | TypeScript (+ `@typescript/typescript6` 6.0.2 dev, JS API for the e2e-rules AST test) | 7.0.2 |
| spatie/laravel-data | 4.23.1 | Tailwind CSS | 4.3.3 |
| spatie/laravel-typescript-transformer | 3.3.0 (core 3.3.1) | react-aria-components | 1.22.1 |
| laravel/wayfinder | 0.1.23 (Vite plugin 0.1.11) | vite-plus (exact pin) | 1.5.1 |
| Pest / pest-plugin-laravel | 5.3.1 / 5.0.1 | `vite` = npm alias of `@voidzero-dev/vite-plus-core` | 1.5.1 (bundles Vite 8.3.7) |
| paratest (**exact pin**, see `docs/testing.md`) | 7.26.0 | Vitest (bundled in vite-plus, `vp test`) | 5.1.0 |
| Larastan (level max, no baseline) / PHPStan | 3.13.2 / 2.3.5 | oxlint / oxfmt (bundled) | 1.91.0 / 0.76.0 |
| Pint | 1.32.1 | @playwright/test (**exact pin**) | 1.64.1 |
| PostgreSQL (`postgres:18.6-alpine`) | 18.6 | Playwright image | `mcr.microsoft.com/playwright:v1.64.1-noble` |
| Adminer (dev, profile `tools`) | 6.2.0 | axe-core / @axe-core/playwright | 4.14.0 / 4.14.0 |

The `vite` alias hides Vite advisories from `npm audit`; check them by hand on every dependency update (note in
`package.json` `"//"`). A Pest test keeps the Playwright image tag and the npm package in sync.

`server.fs.deny` in `vite.config.ts` (TE-037 AC5): the six Vite defaults copied there were compared with the bundled Vite 8.3.7 and are identical, so they stay; our Laravel entries follow them.
