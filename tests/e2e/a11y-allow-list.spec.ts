import { expect, test } from '@playwright/test';
import { placeholderAllowList } from './a11y-allow-list';
import { expectNoA11yViolations } from './axe';

// Keeps the allow-list honest (TE-005 AC8b): every page it names must still exist and still have
// each allowed violation. expectNoA11yViolations fails on entries that no longer match.

const pages = [...new Set(placeholderAllowList.map((entry) => entry.page))];

test('every allow entry names a page and rule only once', () => {
    const keys = placeholderAllowList.map(
        (entry) => `${entry.page} ${entry.rule}`,
    );

    expect(new Set(keys).size).toBe(keys.length);
});

for (const path of pages) {
    test(`allow entries for ${path} still match a violation`, async ({
        page,
    }) => {
        const response = await page.goto(path);

        expect(response?.status(), `${path} must still exist`).toBe(200);
        // Wait for React: the Inertia root (#app) is empty in the server HTML.
        await expect(page.locator('#app > *').first()).toBeAttached();
        await expectNoA11yViolations(page, { allow: placeholderAllowList });
    });
}
