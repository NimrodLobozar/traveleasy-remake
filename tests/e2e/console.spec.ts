import { expect, test } from './base-test';

// TE-007 AC3: loading the home page logs no console error, which includes React's hydration
// warnings (React reports a server/client markup mismatch through console.error).
//
// Against the dev app (Vite HMR) SSR is skipped, so the first test proves the client render is clean
// and the second one skips. After `bash scripts/dev-ssr.sh on` (production-style build, `ssr` service
// up; TE-008) the page is server-rendered and the second test really checks hydration. With
// E2E_REQUIRE_SSR=1 (`docker compose run --rm -e E2E_REQUIRE_SSR=1 e2e`) it fails instead of skipping,
// so a silently missing SSR can't pass.

test('the home page logs no console errors or page errors', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(`console.error: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) =>
        errors.push(`page error: ${error.message}`),
    );

    const response = await page.goto('/');

    expect(response?.status()).toBe(200);
    await expect(page.getByRole('link', { name: 'Inloggen' })).toBeVisible();
    // Give hydration and late effects a moment to log.
    await page.waitForLoadState('networkidle');

    expect(errors).toEqual([]);
});

test('a server-rendered home page hydrates without warnings', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const serverRendered = await page
        .locator('#app[data-server-rendered]')
        .count();
    const requireSsr = (process.env.E2E_REQUIRE_SSR ?? '') !== '';
    expect(
        requireSsr && serverRendered === 0,
        'E2E_REQUIRE_SSR is set but the page is not server-rendered',
    ).toBe(false);
    test.skip(
        serverRendered === 0,
        'SSR is off in this environment (dev server); run `bash scripts/dev-ssr.sh on` first',
    );

    expect(errors).toEqual([]);
});
