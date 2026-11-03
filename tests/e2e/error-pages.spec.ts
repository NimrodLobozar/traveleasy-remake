import type { Page } from '@playwright/test';
import { expectNoA11yViolations } from './axe';
import { expect, test } from './base-test';
import { skipOnDebugPage } from './debug-page';

// The localised error pages (TE-019). The testing-only routes /_test/error/{status} exist only
// when APP_ENV is local or testing.

const STATUSES = [403, 404, 419, 429, 500, 503] as const;

const titles = {
    nl: {
        403: 'Geen toegang',
        404: 'Deze pagina bestaat niet',
        419: 'Sessie verlopen, probeer opnieuw',
        429: 'Te veel verzoeken',
        500: 'Er ging iets mis',
        503: 'We zijn zo terug',
    },
    en: {
        403: 'Access denied',
        404: 'This page does not exist',
        419: 'Session expired, please try again',
        429: 'Too many requests',
        500: 'Something went wrong',
        503: "We'll be right back",
    },
} as const;

async function open(page: Page, status: number): Promise<void> {
    const response = await page.goto(`/_test/error/${String(status)}`);
    expect(response?.status()).toBe(status);
    if (status >= 500) {
        await skipOnDebugPage(page);
    }
    await page.waitForLoadState('networkidle');
}

for (const status of STATUSES) {
    test.describe(`status ${String(status)}`, () => {
        test('is localised, keeps the status, focuses the h1, shows a reference (AC2, AC3, AC7)', async ({
            page,
        }) => {
            await open(page, status);

            const heading = page.getByRole('heading', { level: 1 });
            await expect(heading).toHaveText(titles.nl[status]);
            await expect(heading).toBeFocused();
            await expect(page).toHaveTitle(`${titles.nl[status]} | TravelEasy`);
            await expect(
                page.getByText(/^Referentie: [0-9a-f]{32}$/),
            ).toBeVisible();
            await expect(page.locator('header')).toHaveCount(1);
            await expect(page.locator('main')).toHaveCount(1);
        });

        test('follows the English cookie and the dark theme (AC5)', async ({
            page,
        }) => {
            await page.context().addCookies([
                { name: 'locale', value: 'en', url: 'http://localhost:8000' },
                {
                    name: 'appearance',
                    value: 'dark',
                    url: 'http://localhost:8000',
                },
            ]);
            await open(page, status);

            await expect(page.getByRole('heading', { level: 1 })).toHaveText(
                titles.en[status],
            );
            await expect(page.locator('html')).toHaveClass(/dark/);
        });

        for (const scheme of ['light', 'dark'] as const) {
            test(`has no axe violations at 320 px in ${scheme} (AC7)`, async ({
                page,
            }) => {
                await page.emulateMedia({ colorScheme: scheme });
                await page.setViewportSize({ width: 320, height: 700 });
                await open(page, status);
                await expectNoA11yViolations(page);
                await expectNoA11yViolations(page, { bestPractice: true });
            });
        }
    });
}

test('429 shows the Retry-After seconds (AC2)', async ({ page }) => {
    await open(page, 429);

    await expect(
        page.getByText('Probeer het over 42 seconden opnieuw.'),
    ).toBeVisible();
});

test('an unknown URL is the 404 page inside the shell (AC1)', async ({
    page,
}) => {
    const response = await page.goto('/no-such-page-here');

    expect(response?.status()).toBe(404);
    await expect(
        page.getByRole('heading', { level: 1, name: titles.nl[404] }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Naar de startpagina' }),
    ).toBeVisible();
});

test('the 500 page leaks no SQL or path (AC4)', async ({ page }) => {
    const response = await page.goto('/_test/error/500');
    await skipOnDebugPage(page);
    // The server's own HTML and headers, not the DOM after the dev server injected its styles.
    const sent = [
        await response?.text(),
        JSON.stringify(await response?.allHeaders()),
    ].join(' ');

    expect(sent).not.toMatch(/SQLSTATE|\/var\/www|\.php/);
});
