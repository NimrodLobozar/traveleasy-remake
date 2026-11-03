import type { Page } from '@playwright/test';
import { expect, test } from './base-test';

// Proof of the externalRequests auto-fixture (TE-029 AC1). The two `test.fail()` cases pass only
// when the fixture fails them; the third must pass. Requests are aborted before they leave the
// browser, so nothing here depends on the network (the 'request' event still fires).

test.beforeEach(async ({ context }) => {
    await context.route(/^https?:\/\/(?!localhost|127\.0\.0\.1)/, (route) =>
        route.abort(),
    );
});

async function request(page: Page, url: string): Promise<void> {
    await page.setContent('<p>probe</p>');
    await page.evaluate(
        (target) =>
            fetch(target).then(
                () => undefined,
                () => undefined,
            ),
        url,
    );
}

test('fails on a request to a hostname', async ({ page }) => {
    test.fail();
    await request(page, 'https://cdn.example.test/lib.js');
});

test('fails on a request to a raw IP address', async ({ page }) => {
    test.fail();
    await request(page, 'http://192.0.2.1/pixel.gif');
});

test('passes on a request to localhost and 127.0.0.1', async ({ page }) => {
    await page.setContent('<p>probe</p>');
    const outcome = await page.evaluate(async () => {
        const results = await Promise.all(
            ['http://localhost:9/x', 'http://127.0.0.1:9/x'].map((url) =>
                fetch(url).then(
                    () => 'ok',
                    () => 'refused',
                ),
            ),
        );

        return results;
    });

    expect(outcome).toHaveLength(2);
});
