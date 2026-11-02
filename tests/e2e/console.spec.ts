import { expect, test } from '@playwright/test';

// TE-007 AC3: loading the home page logs no console error, which includes React's hydration
// warnings (React reports a server/client markup mismatch through console.error).
//
// Today the e2e run goes against the dev app (Vite HMR), where SSR is skipped, so this proves the
// client render is clean. TE-008 must run this same spec against the production-style build with
// the `ssr` service up, so hydration is exercised: that is when `data-server-rendered` is set and
// the second test stops skipping.

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
    await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible();
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
    test.skip(
        serverRendered === 0,
        'SSR is off in this environment (dev server); TE-008 enables it',
    );

    expect(errors).toEqual([]);
});
