import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests with Playwright + axe (TE-005). They run in the official Playwright image (same
 * version as @playwright/test in package-lock.json), never in the vite/app containers.
 *
 * Env (only these; the e2e container needs no app secrets):
 * - E2E_BASE_URL: the URL the browser opens. Default http://localhost:8000, the same origin as
 *   APP_URL in dev, so the Vite CORS allow-list and the asset URLs in public/hot
 *   (http://localhost:5173) work unchanged.
 * - E2E_FORWARD: optional TCP forwards started before the tests, `<localPort>=<host>:<port>`,
 *   comma-separated (see tests/e2e/global-setup.ts). Used in dev, where the e2e container shares
 *   the vite container's network namespace (so localhost:5173 is the real Vite server) and
 *   forwards localhost:8000 to the app container: `E2E_FORWARD=8000=app:8000`.
 * - CI: set by CI runners; forbids `test.only` and enables one retry.
 */
const isCi = process.env.CI !== undefined && process.env.CI !== '';

export default defineConfig({
    testDir: '.',
    // Only `*.spec.ts`: the `*.test.ts` files here are static Vitest checks (allow-usage.test.ts).
    testMatch: '**/*.spec.ts',
    globalSetup: './global-setup.ts',
    fullyParallel: true,
    forbidOnly: isCi,
    retries: isCi ? 1 : 0,
    // Paths are relative to this file. Output goes one level below the repo's report folders: the
    // e2e service mounts only those two folders writable, and Playwright can't clear a mount point.
    // They are bind-mounted (open them on Windows) and git-ignored.
    outputDir: '../../test-results/e2e',
    reporter: [
        ['list'],
        [
            'html',
            { outputFolder: '../../playwright-report/html', open: 'never' },
        ],
    ],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        locale: 'nl-NL',
        timezoneId: 'Europe/Amsterdam',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
