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

test('reflows at 400 % zoom (320 CSS px) with text-spacing overrides (AC10)', async ({
    page,
}) => {
    // 400 % zoom of a 1280 px window is a 320 CSS px viewport (WCAG 1.4.10).
    await page.setViewportSize({ width: 320, height: 256 });
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    // Constructed stylesheets are not blocked by the CSP (addStyleTag would be).
    await page.evaluate(() => {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(
            '*{line-height:1.5 !important;letter-spacing:.12em !important;word-spacing:.16em !important}p{margin-bottom:2em !important}',
        );
        document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    });
    const result = await page.evaluate(() => ({
        overflow:
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
        clipped: [...document.querySelectorAll<HTMLElement>('main *')]
            .filter(
                (el) =>
                    el.offsetParent !== null &&
                    !el.closest('.sr-only') &&
                    el.scrollWidth > el.clientWidth + 1 &&
                    ['hidden', 'clip'].includes(getComputedStyle(el).overflowX),
            )
            .map((el) => el.tagName + '.' + el.className)
            .slice(0, 5),
    }));
    expect(result.overflow).toBe(false);
    expect(result.clipped).toEqual([]);
});

test('field border and focus ring stay visible in forced colours (AC7)', async ({
    page,
}) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    const input = page.getByLabel('City (blur-validated)');
    const border = await input.evaluate((el) => {
        const style = getComputedStyle(el);

        return { width: style.borderTopWidth, style: style.borderTopStyle };
    });
    expect(border.width).toBe('2px');
    expect(border.style).not.toBe('none');
    await input.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    const ring = await input.evaluate((el) => {
        const style = getComputedStyle(el);

        return { width: style.outlineWidth, style: style.outlineStyle };
    });
    expect(parseFloat(ring.width)).toBeGreaterThanOrEqual(2);
    expect(ring.style).not.toBe('none');
});

test('a blur error is written into the polite live region (4.1.3)', async ({
    page,
}) => {
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('output[data-validation-announcer]')).toHaveCount(
        1,
    );
    await page.getByLabel('City (blur-validated)').focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('output[data-validation-announcer]')).toHaveText(
        'City (blur-validated): Enter a city.',
    );
});

test('number field says so when the typed value is out of range (AC5)', async ({
    page,
}) => {
    await page.goto('/design');
    await page.waitForLoadState('networkidle');
    const adults = page.getByRole('textbox', { name: 'Adults' });
    await adults.fill('12');
    await page.keyboard.press('Tab');
    await expect(
        page.getByText('Kies een getal van 1 tot 9.').last(),
    ).toBeVisible();
    await expect(adults).toHaveAttribute('aria-invalid', 'true');
    await expect(
        page.locator('output[data-validation-announcer]'),
    ).toContainText('Adults: Kies een getal van 1 tot 9.');
});
