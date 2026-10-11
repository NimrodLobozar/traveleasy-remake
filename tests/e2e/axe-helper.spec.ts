import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { expectNoA11yViolations } from './axe';
import type { A11yAllowEntry } from './axe';

// Proves the axe helper reports violations (TE-005 AC9), so a broken axe setup can't pass
// silently. The fixture is served through route interception on a made-up path, so no server
// is needed and the allow-list sees a normal page path.
const fixturePath = '/__e2e-fixture/a11y-violations';
const fixtureFile = fileURLToPath(
    new URL('fixtures/a11y-violations.html', import.meta.url),
);

function allowOn(page: A11yAllowEntry['page'], rule: string): A11yAllowEntry {
    return {
        page,
        rule,
        reason: 'starter-kit placeholder, remove in TE-005 (fixture)',
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
    await page.route(`**${fixturePath}`, (route) =>
        route.fulfill({ path: fixtureFile, contentType: 'text/html' }),
    );
    await page.goto(fixturePath);
});

test('reports rule id, impact, help URL and targets of each violation', async ({
    page,
}) => {
    const message = await failureOf(expectNoA11yViolations(page));

    expect(message).toContain(`2 accessibility violation(s) on ${fixturePath}`);
    expect(message).toMatch(/\[critical\] image-alt: /);
    expect(message).toMatch(/\[critical\] label: /);
    expect(message).toMatch(
        /help: https:\/\/dequeuniversity\.com\/rules\/axe\/\S+\/image-alt/,
    );
    expect(message).toContain('- #no-alt');
    expect(message).toContain('- #no-label');
});

test('skips an allowed rule only on its own page', async ({ page }) => {
    // image-alt allowed here: the label violation still fails the check.
    const partly = await failureOf(
        expectNoA11yViolations(page, {
            allow: [allowOn(fixturePath, 'image-alt')],
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
                allowOn(fixturePath, 'image-alt'),
                allowOn(fixturePath, 'label'),
            ],
        }),
    );
    expect(none).toBeNull();
});

test('fails on stale and duplicate allow entries', async ({ page }) => {
    const stale = await failureOf(
        expectNoA11yViolations(page, {
            allow: [
                allowOn(fixturePath, 'image-alt'),
                allowOn(fixturePath, 'label'),
                allowOn(fixturePath, 'color-contrast'),
            ],
        }),
    );
    expect(stale).toContain(
        'no longer match a violation (remove them): color-contrast',
    );

    const duplicate = await failureOf(
        expectNoA11yViolations(page, {
            allow: [
                allowOn(fixturePath, 'label'),
                allowOn(fixturePath, 'label'),
            ],
        }),
    );
    expect(duplicate).toContain('Duplicate a11y allow entries');
});
