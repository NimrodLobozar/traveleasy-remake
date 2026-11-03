import type { Page } from '@playwright/test';
import { expectNoA11yViolations } from './axe';
import { expect, test } from './base-test';

// The language switch (TE-017) and the theme menu (TE-018) in the shell header.

async function openHome(page: Page): Promise<void> {
    await page.goto('/');
    await expect(page.locator('h1')).toBeVisible();
    await page.waitForLoadState('networkidle');
}

async function cookie(page: Page, name: string): Promise<string | undefined> {
    return (await page.context().cookies()).find((c) => c.name === name)?.value;
}

test.describe('language switch (TE-017)', () => {
    test('switches without a reload, keeps focus, announces, sets lang and the cookie (AC1, AC2, AC4)', async ({
        page,
    }) => {
        await openHome(page);
        await page.evaluate(() => {
            (window as unknown as { __kept: boolean }).__kept = true;
        });
        const english = page
            .getByRole('group', { name: 'Taal' })
            .getByRole('button', { name: 'English' });

        await expect(english).toHaveAttribute('lang', 'en');
        await english.focus();
        await page.keyboard.press('Enter');

        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page).toHaveTitle(/\| TravelEasy$/);
        await expect(
            page.getByRole('group', { name: 'Language' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'English' }),
        ).toBeFocused();
        await expect(page.getByTestId('language-announcer')).toHaveText(
            'Language changed to English',
        );
        // Same document: no full page reload happened.
        expect(
            await page.evaluate(
                () => (window as unknown as { __kept?: boolean }).__kept,
            ),
        ).toBe(true);
        expect(await cookie(page, 'locale')).toBe('en');

        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(
            page.getByRole('button', { name: 'English' }),
        ).toHaveAttribute('aria-current', 'true');
    });

    test('works as a normal form post without JavaScript (AC5)', async ({
        browser,
    }) => {
        const context = await browser.newContext({ javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/');

        await page.getByRole('button', { name: 'English' }).click();

        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        expect(new URL(page.url()).pathname).toBe('/');
        await context.close();
    });

    test('has no axe violations in either language', async ({ page }) => {
        await openHome(page);
        await expectNoA11yViolations(page);
        await page.getByRole('button', { name: 'English' }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expectNoA11yViolations(page);
    });
});

test.describe('theme menu (TE-018)', () => {
    test('sets the dark class before first paint from prefers-color-scheme, with no app script (AC1)', async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        // Only the inline theme script may run: block every bundle served by Vite.
        await page.route(/:5173\//, (route) => route.abort());
        await page.goto('/');

        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.locator('html')).toHaveAttribute(
            'data-theme',
            'dark',
        );
    });

    test('opens by keyboard, picks Donker, stores the cookie and survives a reload (AC2, AC4)', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await openHome(page);
        const button = page.getByRole('button', { name: 'Thema' });

        await button.focus();
        await page.keyboard.press('Enter');
        await expect(button).toHaveAttribute('aria-expanded', 'true');
        await expect(
            page.getByRole('radio', { name: 'Systeem' }),
        ).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('ArrowDown');
        await expect(page.locator('html')).toHaveClass(/dark/);
        expect(await cookie(page, 'appearance')).toBe('dark');

        await page.keyboard.press('Escape');
        await expect(button).toHaveAttribute('aria-expanded', 'false');
        await expect(button).toBeFocused();

        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await button.click();
        await expect(page.getByRole('radio', { name: 'Donker' })).toBeChecked();
    });

    test('ignores a junk cookie value and follows the system theme (AC3, AC6)', async ({
        page,
    }) => {
        await page
            .context()
            .addCookies([
                {
                    name: 'appearance',
                    value: 'foo',
                    url: 'http://localhost:8000',
                },
            ]);
        await page.emulateMedia({ colorScheme: 'light' });
        await openHome(page);
        await expect(page.locator('html')).not.toHaveClass(/dark/);

        await page.emulateMedia({ colorScheme: 'dark' });
        await expect(page.locator('html')).toHaveClass(/dark/);
    });

    for (const scheme of ['light', 'dark'] as const) {
        for (const width of [320, 1280]) {
            test(`has no axe violations: ${scheme}, ${String(width)} px, menu open (AC8)`, async ({
                page,
            }) => {
                await page.emulateMedia({ colorScheme: scheme });
                await page.setViewportSize({ width, height: 900 });
                await openHome(page);
                const opener = page.getByRole('button', {
                    name: width < 768 ? 'Menu' : 'Thema',
                });
                await opener.click();
                await expectNoA11yViolations(page);
                await expectNoA11yViolations(page, { bestPractice: true });
            });
        }
    }

    test('stays usable in forced-colours mode (AC9)', async ({ page }) => {
        await page.emulateMedia({ forcedColors: 'active' });
        await page.setViewportSize({ width: 1280, height: 800 });
        await openHome(page);
        const button = page.getByRole('button', { name: 'Thema' });

        await button.focus();
        const outline = await button.evaluate(
            (element) => getComputedStyle(element).outlineStyle,
        );
        expect(outline).not.toBe('none');
        const border = await button.evaluate(
            (element) => getComputedStyle(element).borderTopWidth,
        );
        expect(parseFloat(border)).toBeGreaterThan(0);
    });
});
