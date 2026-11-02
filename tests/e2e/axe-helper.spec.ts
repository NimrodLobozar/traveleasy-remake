import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { expectNoA11yViolations, NEEDS_REVIEW_ATTACHMENT } from './axe';

// Proves the axe helper (TE-005 AC5, AC8, AC9), so a broken axe setup can't pass silently.
// Fixtures are served through route interception on made-up paths, so no server is needed.
const violationsPath = '/__e2e-fixture/a11y-violations';
const needsReviewPath = '/__e2e-fixture/a11y-needs-review';

function fixture(name: string): string {
    return fileURLToPath(new URL(`fixtures/${name}.html`, import.meta.url));
}

/** The helper's error message, or null when it passed. */
async function failureOf(check: Promise<void>): Promise<string | null> {
    try {
        await check;
        return null;
    } catch (error) {
        return error instanceof Error ? error.message : String(error);
    }
}

test.beforeEach(async ({ page }) => {
    await page.route(`**${violationsPath}`, (route) =>
        route.fulfill({
            path: fixture('a11y-violations'),
            contentType: 'text/html',
        }),
    );
    await page.route(`**${needsReviewPath}`, (route) =>
        route.fulfill({
            path: fixture('a11y-needs-review'),
            contentType: 'text/html',
        }),
    );
});

test.describe('on a page with known violations', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(violationsPath);
    });

    test('reports rule id, impact, help URL, targets, summary and html', async ({
        page,
    }) => {
        const message = await failureOf(expectNoA11yViolations(page));

        expect(message).toContain(
            `2 accessibility violation(s) on ${violationsPath}`,
        );
        expect(message).toMatch(/\[critical\] image-alt: /);
        expect(message).toMatch(/\[critical\] label: /);
        expect(message).toMatch(
            /help: https:\/\/dequeuniversity\.com\/rules\/axe\/\S+\/image-alt/,
        );
        expect(message).toContain('- #no-alt');
        expect(message).toContain('- #no-label');
        expect(message).toMatch(/summary: Fix any of the following:/);
        expect(message).toContain('html: <img id="no-alt"');
        expect(message).toContain('html: <input id="no-label"');
    });
});

test('the best-practice pass reports structure rules the WCAG pass ignores', async ({
    page,
}) => {
    const path = '/__e2e-fixture/no-structure';
    await page.route(`**${path}`, (route) =>
        route.fulfill({
            contentType: 'text/html',
            body: '<!doctype html><html lang="nl"><head><title>x</title></head><body><div><h3>Skipped</h3><p>No landmarks.</p></div></body></html>',
        }),
    );
    await page.goto(path);

    expect(await failureOf(expectNoA11yViolations(page))).toBeNull();
    const message = await failureOf(
        expectNoA11yViolations(page, { bestPractice: true }),
    );
    expect(message).toContain('page-has-heading-one');
    expect(message).toContain('landmark-one-main');
    expect(message).toContain('region');
});

test('attaches incomplete results for review without failing', async ({
    page,
}, testInfo) => {
    await page.goto(needsReviewPath);

    // No violations, so the check passes ...
    await expectNoA11yViolations(page);

    // ... but what axe couldn't decide is attached to the test.
    const attachment = testInfo.attachments.find(
        (item) => item.name === NEEDS_REVIEW_ATTACHMENT,
    );
    expect(attachment?.contentType).toBe('text/plain');
    const body = attachment?.body?.toString('utf8') ?? '';
    expect(body).toContain(`on http://`);
    expect(body).toContain(needsReviewPath);
    expect(body).toMatch(/\] color-contrast: /);
    expect(body).toContain('- #over-gradient');
    expect(body).toMatch(/summary: .+/);
});
