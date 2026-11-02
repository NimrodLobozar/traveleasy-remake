import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { expectNoA11yViolations } from './axe';

// The app shell on the home page (TE-016): landmarks, skip link, route-change focus, mobile menu,
// pointer targets and axe in several states. Language and theme switches are TE-017/TE-018.

async function openHome(page: Page): Promise<void> {
    await page.goto('/');
    // The Inertia root is rendered by React (or the SSR output); wait for hydration to finish.
    await expect(page.locator('h1')).toBeVisible();
    await page.waitForLoadState('networkidle');
}

test.describe('structure (AC1, AC5, AC9)', () => {
    test('has the landmarks, one h1 and passes both axe passes', async ({
        page,
    }) => {
        await openHome(page);

        await expect(page.locator('header')).toHaveCount(1);
        await expect(page.locator('main#main')).toHaveCount(1);
        await expect(page.locator('main#main')).toHaveAttribute(
            'tabindex',
            '-1',
        );
        await expect(page.locator('footer')).toHaveCount(1);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(
            page.getByRole('navigation', { name: 'Hoofdmenu' }),
        ).toHaveCount(1);

        const names = await page
            .locator('nav')
            .evaluateAll((navs) =>
                navs.map((nav) => nav.getAttribute('aria-label')),
            );
        expect(new Set(names).size).toBe(names.length);
        expect(names).not.toContain(null);

        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });
    });

    test('is scanned in the dark theme and at 320 px', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await openHome(page);
        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });

        await page.emulateMedia({ colorScheme: 'light' });
        await page.setViewportSize({ width: 320, height: 700 });
        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });
    });
});

test.describe('skip link and focus (AC2, AC11)', () => {
    test('is the first Tab stop, shows on focus and moves focus to main', async ({
        page,
    }) => {
        await openHome(page);
        const skip = page.getByRole('link', { name: 'Naar de hoofdinhoud' });

        await expect(skip).not.toBeInViewport();
        await page.keyboard.press('Tab');
        await expect(skip).toBeFocused();
        await expect(skip).toBeInViewport();
        await page.keyboard.press('Enter');
        await expect(page.locator('main#main')).toBeFocused();
    });

    test('Tab order: skip link, brand, nav, login, main content, footer', async ({
        page,
    }) => {
        await openHome(page);
        const order: string[] = [];
        for (let step = 0; step < 24; step++) {
            await page.keyboard.press('Tab');
            order.push(
                await page.evaluate(() => {
                    const el = document.activeElement;
                    return el
                        ? `${el.closest('header') ? 'header' : el.closest('main') ? 'main' : el.closest('footer') ? 'footer' : 'other'}:${(el.textContent || '').trim()}`
                        : '';
                }),
            );
        }
        expect(order[0]).toBe('other:Naar de hoofdinhoud');
        expect(order[1]).toBe('header:TravelEasy');
        expect(order.slice(2, 6)).toEqual([
            'header:Zoeken',
            'header:Aanbiedingen',
            'header:Mijn boekingen',
            'header:Help',
        ]);
        expect(order[6]).toBe('header:Inloggen');
        expect(order[7]?.startsWith('main:')).toBe(true);
        expect(order.some((entry) => entry.startsWith('footer:'))).toBe(true);
        expect(order.lastIndexOf('header:Inloggen')).toBeLessThan(
            order.findIndex((entry) => entry.startsWith('main:')),
        );
    });

    test('a focused control has a 2 px+ outline and is not covered', async ({
        page,
    }) => {
        await openHome(page);
        const link = page
            .getByRole('link', { name: 'Zoeken', exact: true })
            .first();
        await link.focus();
        const outline = await link.evaluate((el) => {
            const style = getComputedStyle(el);
            return {
                width: parseFloat(style.outlineWidth),
                style: style.outlineStyle,
            };
        });
        expect(outline.style).not.toBe('none');
        expect(outline.width).toBeGreaterThanOrEqual(2);

        const box = await link.boundingBox();
        expect(box).not.toBeNull();
        const covered = await link.evaluate((el) => {
            const rect = el.getBoundingClientRect();
            const top = document.elementFromPoint(
                rect.left + rect.width / 2,
                rect.top + rect.height / 2,
            );
            return !el.contains(top);
        });
        expect(covered).toBe(false);
    });
});

test.describe('navigation (AC3, AC4)', () => {
    test('moves focus to the h1, updates the title, announces it once and resets scroll', async ({
        page,
    }) => {
        await openHome(page);
        await page.mouse.wheel(0, 300);
        await page.getByRole('link', { name: 'Inloggen' }).first().click();
        await expect(page).toHaveURL(/\/login$/);

        await expect(page.locator('h1')).toBeFocused();
        await expect(page).toHaveTitle(/ \| TravelEasy$/);
        const title = await page.title();
        const announcer = page.getByTestId('route-announcer');
        await expect(announcer).toHaveText(title);
        expect(await page.evaluate(() => window.scrollY)).toBe(0);
    });

    test('back navigation moves focus to main', async ({ page }) => {
        await openHome(page);
        await page.getByRole('link', { name: 'Inloggen' }).first().click();
        await expect(page).toHaveURL(/\/login$/);
        await page.goBack();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('main#main')).toBeFocused();
    });

    test('is clean after an Inertia navigation', async ({ page }) => {
        await openHome(page);
        await page.getByRole('link', { name: 'Inloggen' }).first().click();
        await expect(page).toHaveURL(/\/login$/);
        await expectNoA11yViolations(page);
    });
});

test.describe('mobile (AC6, AC8, AC12)', () => {
    test.use({ viewport: { width: 320, height: 700 } });

    test('has no horizontal scrolling', async ({ page }) => {
        await openHome(page);
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(overflow).toBeLessThanOrEqual(0);
    });

    test('menu button opens, closes with Escape and returns focus; scanned open', async ({
        page,
    }) => {
        await openHome(page);
        const button = page.getByRole('button', { name: 'Menu' });
        await expect(button).toHaveAttribute('aria-expanded', 'false');
        await expect(
            page.getByRole('link', { name: 'Zoeken', exact: true }),
        ).toBeHidden();

        await button.focus();
        await page.keyboard.press('Enter');
        await expect(button).toHaveAttribute('aria-expanded', 'true');
        await expect(
            page.getByRole('link', { name: 'Zoeken', exact: true }),
        ).toBeVisible();
        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });

        await page
            .getByRole('link', { name: 'Help', exact: true })
            .first()
            .focus();
        await page.keyboard.press('Escape');
        await expect(button).toHaveAttribute('aria-expanded', 'false');
        await expect(button).toBeFocused();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(overflow).toBeLessThanOrEqual(0);
    });

    test('pointer targets are at least 44 px high on mobile', async ({
        page,
    }) => {
        await openHome(page);
        await page.getByRole('button', { name: 'Menu' }).click();
        const sizes = await page
            .locator('header a, header button, footer a')
            .evaluateAll((els) =>
                els.map((el) => {
                    const rect = el.getBoundingClientRect();
                    return {
                        name: (el.textContent || '').trim(),
                        w: rect.width,
                        h: rect.height,
                    };
                }),
            );
        expect(sizes.length).toBeGreaterThan(8);
        for (const size of sizes) {
            expect(size.h, size.name).toBeGreaterThanOrEqual(44);
            expect(size.w, size.name).toBeGreaterThanOrEqual(24);
        }
    });

    test('a validation error is shown, focused and scanned', async ({
        page,
    }) => {
        await openHome(page);
        await page.getByLabel('Van').fill('');
        await page.getByRole('button', { name: 'Zoek reizen' }).click();

        const summary = page.getByRole('group', {
            name: 'Er is iets niet ingevuld',
        });
        await expect(summary).toBeFocused();
        await expect(page.getByLabel('Van')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await expect(page.getByLabel('Van')).toHaveAccessibleDescription(
            'Vul in waar je vertrekt.',
        );
        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });
    });
});

test.describe('desktop targets (AC12)', () => {
    test('every interactive element in the shell is at least 24 x 24 px', async ({
        page,
    }) => {
        await openHome(page);
        const sizes = await page
            .locator(
                'header a, header button, footer a, main a, main button, main input',
            )
            .evaluateAll((els) =>
                els
                    .map((el) => {
                        const rect = el.getBoundingClientRect();
                        return {
                            name: (el.textContent || el.id).trim(),
                            w: rect.width,
                            h: rect.height,
                        };
                    })
                    .filter((size) => size.w > 0),
            );
        for (const size of sizes) {
            expect(size.h, size.name).toBeGreaterThanOrEqual(24);
            expect(size.w, size.name).toBeGreaterThanOrEqual(24);
        }
    });
});
