import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { placeholderAllowList } from './a11y-allow-list';
import { expectNoA11yViolations } from './axe';

// Smoke journey on the running dev app (TE-005 AC6, AC7).

/** Fails the test on a page error or a failed request (e.g. the Vite assets not loading). */
function watchForBrokenLoads(page: Page): string[] {
    const problems: string[] = [];
    page.on('pageerror', (error) =>
        problems.push(`page error: ${error.message}`),
    );
    page.on('requestfailed', (request) =>
        problems.push(
            `request failed: ${request.url()} (${request.failure()?.errorText ?? 'unknown'})`,
        ),
    );

    return problems;
}

test('home page renders with React and passes axe', async ({ page }) => {
    const problems = watchForBrokenLoads(page);

    const response = await page.goto('/');

    expect(response?.status()).toBe(200);
    // The Inertia root is empty in the server HTML; this link only exists once React rendered.
    await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible();
    await expectNoA11yViolations(page, { allow: placeholderAllowList });
    expect(problems).toEqual([]);
});

test('login page renders the form with React and passes axe', async ({
    page,
}) => {
    const problems = watchForBrokenLoads(page);

    const response = await page.goto('/login');

    expect(response?.status()).toBe(200);
    await expect(page.getByLabel('Email address')).toBeVisible();
    await expectNoA11yViolations(page, { allow: placeholderAllowList });
    expect(problems).toEqual([]);
});

test('password reset is not offered while mail is off (TE-001 AC12)', async ({
    request,
}) => {
    const response = await request.get('/forgot-password');

    expect(response.status()).toBe(404);
});
