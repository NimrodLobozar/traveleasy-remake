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
| Pint | 1.32.1 | @playwright/test (**exact pin**) | 1.65.0 |
| PostgreSQL (`postgres:18.6-alpine`) | 18.6 | Playwright image | `mcr.microsoft.com/playwright:v1.65.0-noble` |
| Adminer (dev, profile `tools`) | 6.2.0 | axe-core / @axe-core/playwright | 4.14.0 / 4.14.0 |

The `vite` alias hides Vite advisories from `npm audit`; check them by hand on every dependency update (note in
`package.json` `"//"`). A Pest test keeps the Playwright image tag and the npm package in sync.

`server.fs.deny` in `vite.config.ts` (TE-037 AC5): the six Vite defaults copied there were compared with the bundled Vite 8.3.7 and are identical, so they stay; our Laravel entries follow them.

Production base (TE-044): FrankenPHP 1.13.5 (`dunglas/frankenphp:1.13.5-php8.5.12-trixie`, digest pinned in the Dockerfile), which brings `apt` / `libapt-pkg7.0` 3.0.4 and Go `x/net` v0.62.0. `.trivyignore.yaml` carries time-boxed ignores, all expiring 2026-12-15 (re-check before then): the earlier util-linux, curl, libacl1, perl, libsqlite3-0 (CVE-2026-119373), systemd, ncurses, libxml2 and go-jose entries, plus (TE-044) krb5 `libgssapi-krb5-2`/`libk5crypto3`/`libkrb5-3`/`libkrb5support0` for CVE-2026-112321, -112322, -112323, -119079 and `libsqlite3-0` for CVE-2026-119374, -119379.

Dev and Playwright images (TE-045): the dev image uses `php:8.5.12-cli-trixie` re-pinned to digest `sha256:50956f09...d781a9` (rebuilt 2026-11-05) and, in the shared `base` stage only (the production stage is a separate `FROM`), upgrades `apt`, `libapt-pkg7.0`, `linux-libc-dev`, `zlib1g` and `zlib1g-dev` explicitly (`--only-upgrade`). The Playwright pin stays `v1.65.0-noble` (no newer digest or stable release; tied to `@playwright/test` 1.65.0). Time-boxed ignores, all expiring 2026-12-15: `.trivyignore.dev.yaml` (dev image only: no-fix `linux-libc-dev` kernel CVEs, npm bundled in the Node image, Go binaries inside the TypeScript and tsgolint npm packages; the weekly scan appends it to `.trivyignore.yaml`) and `.trivyignore.playwright.yaml` (Playwright image only: `apt`/`libapt-pkg6.0t64` CVE-2026-109518 until Microsoft rebuilds the image, `gstreamer` CVE-2025-3887 with no fix, bundled npm `brace-expansion` and `undici`).
