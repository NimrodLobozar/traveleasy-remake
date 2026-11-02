import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { expectNoA11yViolations } from './axe';

// QA additions for TE-016: English shell (AC1, AC2, AC5, AC9), 400% zoom (AC6), live region
// announcing once (AC3) and scroll restore on back navigation (AC4).

async function openHome(page: Page): Promise<void> {
    await page.goto('/');
    await expect(page.locator('h1')).toBeVisible();
    await page.waitForLoadState('networkidle');
}

test.describe('English shell', () => {
    test.use({ locale: 'en-GB' });

    test('has English landmarks, skip link and passes both axe passes', async ({
        page,
    }) => {
        await openHome(page);
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(
            page.getByRole('navigation', { name: 'Main menu' }),
        ).toHaveCount(1);
        await expectNoA11yViolations(page);
        await expectNoA11yViolations(page, { bestPractice: true });
        await page.setViewportSize({ width: 320, height: 700 });
        await expectNoA11yViolations(page, { bestPractice: true });
        await page.keyboard.press('Tab');
        await expect(
            page.getByRole('link', { name: 'Skip to main content' }),
        ).toBeFocused();
    });
});

test('400% zoom (320 CSS px wide) has no horizontal scrolling with the menu open', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 256 });
    await openHome(page);
    await page.getByRole('button', { name: 'Menu' }).click();
    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);
});

test('announces the new title exactly once and restores scroll on back', async ({
    page,
}) => {
    await openHome(page);
    await page.evaluate(() => {
        const w = window as unknown as { __texts: string[] };
        w.__texts = [];
        const out = document.querySelector('[data-testid="route-announcer"]');
        new MutationObserver(() => {
            if (out?.textContent) w.__texts.push(out.textContent);
        }).observe(out as Node, {
            childList: true,
            characterData: true,
            subtree: true,
        });
        window.scrollTo(0, 250);
    });
    const before = await page.evaluate(() => window.scrollY);
    expect(before).toBeGreaterThan(0);
    // dispatchEvent, not click(): click() first scrolls the (non-sticky) header back into view,
    // which would reset scrollY to 0 before the page is left.
    await page
        .getByRole('link', { name: 'Inloggen' })
        .first()
        .dispatchEvent('click');
    await expect(page).toHaveURL(/\/login$/);
    await page.waitForTimeout(400);
    // Rework 1: after a forward visit only the focus move announces; the live region stays empty.
    expect(
        await page.evaluate(
            () => (window as unknown as { __texts: string[] }).__texts,
        ),
    ).toHaveLength(0);

    await page.goBack();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.locator('main#main')).toBeFocused();
    // After history navigation the title is announced exactly once.
    await expect(page.getByTestId('route-announcer')).toHaveText(/TravelEasy/);
    await page.waitForTimeout(400);
    expect(
        await page.evaluate(
            () => (window as unknown as { __texts: string[] }).__texts,
        ),
    ).toHaveLength(1);
    await expect
        .poll(() => page.evaluate(() => window.scrollY))
        .toBeGreaterThan(0);
});
