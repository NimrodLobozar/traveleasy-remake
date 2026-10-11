import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { expectNoA11yViolations, NEEDS_REVIEW_ATTACHMENT } from './axe';
import type { A11yAllowEntry } from './axe';

// Proves the axe helper (TE-005 AC5, AC8, AC9), so a broken axe setup can't pass silently.
// Fixtures are served through route interception on made-up paths, so no server is needed and
// the allow-list sees a normal page path. This is the only spec that may pass its own `allow`
// entries (allow-usage.test.ts).
const violationsPath = '/__e2e-fixture/a11y-violations';
const needsReviewPath = '/__e2e-fixture/a11y-needs-review';

function fixture(name: string): string {
    return fileURLToPath(new URL(`fixtures/${name}.html`, import.meta.url));
}

function allowOn(page: A11yAllowEntry['page'], rule: string): A11yAllowEntry {
    return {
        page,
        rule,
        reason: 'starter-kit placeholder, remove in App shell',
    };
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

    test('skips an allowed rule only on its own page', async ({ page }) => {
        // image-alt allowed here: the label violation still fails the check.
        const partly = await failureOf(
            expectNoA11yViolations(page, {
                allow: [allowOn(violationsPath, 'image-alt')],
            }),
        );
        expect(partly).toContain('1 accessibility violation(s)');
        expect(partly).toContain('label: ');
        expect(partly).not.toContain('image-alt');

        // Entries for another page don't apply here.
        const elsewhere = await failureOf(
            expectNoA11yViolations(page, {
                allow: [
                    allowOn('/elsewhere', 'image-alt'),
                    allowOn('/elsewhere', 'label'),
                ],
            }),
        );
        expect(elsewhere).toContain('2 accessibility violation(s)');

        // Both allowed on this page: passes.
        const none = await failureOf(
            expectNoA11yViolations(page, {
                allow: [
                    allowOn(violationsPath, 'image-alt'),
                    allowOn(violationsPath, 'label'),
                ],
            }),
        );
        expect(none).toBeNull();
    });

    test('fails on stale, duplicate and invalid allow entries', async ({
        page,
    }) => {
        const stale = await failureOf(
            expectNoA11yViolations(page, {
                allow: [
                    allowOn(violationsPath, 'image-alt'),
                    allowOn(violationsPath, 'label'),
                    allowOn(violationsPath, 'color-contrast'),
                ],
            }),
        );
        expect(stale).toContain(
            'no longer match a violation (remove them): color-contrast',
        );

        const duplicate = await failureOf(
            expectNoA11yViolations(page, {
                allow: [
                    allowOn(violationsPath, 'label'),
                    allowOn(violationsPath, 'label'),
                ],
            }),
        );
        expect(duplicate).toContain('Duplicate a11y allow entries');

        const blankEpic = {
            ...allowOn(violationsPath, 'image-alt'),
            reason: 'starter-kit placeholder, remove in  ',
        } as unknown as A11yAllowEntry;
        const invalid = await failureOf(
            expectNoA11yViolations(page, { allow: [blankEpic] }),
        );
        expect(invalid).toContain('Invalid a11y allow entry');
    });
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
