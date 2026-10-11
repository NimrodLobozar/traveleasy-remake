import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';
import { lintConfig } from './lint.rules';

// Vite dev-server port, inside the container and on 127.0.0.1 of the host (VITE_PORT in .env).
const devServerPort = Number(process.env.VITE_PORT ?? 5173);

// No `fonts` option: the kit's bunny() helper downloads fonts from a third-party host at build
// time. We use a system font stack (resources/css/app.css) until the design system picks a
// self-hosted font (CLAUDE.md: no runtime CDNs or third-party fonts).
// Vitest (`vp test`) sets VITEST. Component tests only need the plugins that change how
// components compile or resolve: laravel (the `@/` alias, exactly as in dev; it skips its
// hot-file and server hooks under Vitest), React and the React Compiler (so components behave
// as in production). Left out under Vitest: inertia (page resolution in app.tsx), tailwindcss
// (Vitest doesn't process CSS) and wayfinder (would run `php artisan` on every test run).
const isVitest = process.env.VITEST !== undefined;

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        !isVitest && inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        !isVitest && tailwindcss(),
        !isVitest &&
            wayfinder({
                formVariants: true,
            }),
    ]),
    // Component tests (TE-005): `npm run test` = `vp test run`, `npm run test:watch` = `vp test watch`.
    // Watch mode reuses `server.watch` below, so it polls in the container like the dev server.
    // Browser tests (Playwright + axe) live in tests/e2e and have their own playwright.config.ts.
    test: {
        restoreMocks: true,
        unstubEnvs: true,
        unstubGlobals: true,
        projects: [
            {
                extends: true,
                test: {
                    name: 'components',
                    environment: 'jsdom',
                    include: ['resources/js/**/*.test.{ts,tsx}'],
                    setupFiles: ['resources/js/test/setup.ts'],
                },
            },
            {
                // Static checks on the browser-test code (no browser, no app); the Playwright
                // specs themselves are `*.spec.ts` and only run in the e2e container.
                extends: true,
                test: {
                    name: 'e2e-rules',
                    environment: 'node',
                    include: ['tests/e2e/**/*.test.ts'],
                },
            },
        ],
    },
    // Dev server settings come from env (set by compose.dev.yaml; documented in .env.example).
    // In Docker, Vite listens on 0.0.0.0 inside the container, but the browser reaches it through
    // the 127.0.0.1-only published port, so the HMR host is `localhost`; laravel-vite-plugin derives
    // the dev-server URL in public/hot (and its CORS allow-list from APP_URL) from it.
    // Polling: file events from a Windows (NTFS) bind mount don't reach the Linux container, so the
    // watcher polls. Turn VITE_USE_POLLING off when the repo lives in the WSL file system.
    server: {
        host: process.env.VITE_HOST ?? 'localhost',
        port: devServerPort,
        strictPort: true,
        hmr: {
            host: 'localhost',
            clientPort: devServerPort,
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            interval: Number(process.env.VITE_POLLING_INTERVAL ?? 1000),
            binaryInterval: Number(process.env.VITE_POLLING_INTERVAL ?? 1000),
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/.git/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
                '**/public/hot',
                '**/docs/**',
                // Playwright output (TE-005): not source, and rewritten on every e2e run.
                '**/playwright-report/**',
                '**/test-results/**',
            ],
        },
    },
    // Lint rules and their reasoning live in lint.rules.ts (TE-003 AC3).
    lint: lintConfig,
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        // Always LF, also when run from Windows (TE-003 AC9; matches .gitattributes).
        endOfLine: 'lf',
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        // Besides these, the formatter skips everything in .gitignore (vendor, node_modules, …).
        ignorePatterns: [
            '.github/**',
            'composer.json',
            // Generated code (TE-003 AC10): regenerated by `npm run generate`, never hand-edited.
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/types/generated/**',
            'resources/js/wayfinder/**',
            'resources/views/mail/*',
            // Hand-written prose maintained by the agents; reflowing it only makes noisy diffs
            // (TE-003 AC11). `/*.md` is root-level Markdown only.
            'docs/**',
            '.claude/**',
            '/*.md',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
