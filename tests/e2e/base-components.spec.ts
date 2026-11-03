import { expectNoA11yViolations } from './axe';
import { expect, test } from './base-test';

// TE-022: the base components on the dev showcase (/design): axe in both themes at 320 and 1280 px,
// target size, focus ring, reduced motion, no horizontal scroll.

for (const width of [320, 1280]) {
    for (const scheme of ['light', 'dark'] as const) {
        test(`axe at ${String(width)}px, ${scheme} (AC8)`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme: scheme });
            await page.goto('/design');
            await expect(page.locator('h1')).toBeVisible();
            await page.waitForLoadState('networkidle');
            await expectNoA11yViolations(page);
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBe(false);
        });
    }
}

test('targets are at least 24 px, 44 px for md controls (AC9)', async ({
    page,
}) => {
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    const small = await page.evaluate(
        () =>
            [
                ...document.querySelectorAll<HTMLElement>(
                    'main button, main input, main textarea',
                ),
            ]
                .filter((el) => el.offsetParent !== null)
                .map((el) => el.getBoundingClientRect())
                .filter((r) => r.width < 24 || r.height < 24).length,
    );
    expect(small).toBe(0);
    const button = await page
        .getByRole('button', { name: 'Primary' })
        .boundingBox();
    expect(button?.height ?? 0).toBeGreaterThanOrEqual(44);
});

test('focus ring is visible on keyboard focus (AC7)', async ({ page }) => {
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    const button = page.getByRole('button', { name: 'Primary' });
    await button.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    const outline = await button.evaluate(
        (el) => getComputedStyle(el).outlineWidth,
    );
    expect(outline).toBe('3px');
});

test('pending spinner does not animate under reduced motion (AC11)', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    const animation = await page
        .locator('button svg.animate-spin')
        .first()
        .evaluate((el) => getComputedStyle(el).animationName);
    expect(animation).toBe('none');
});
