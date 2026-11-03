import { expect, test } from './base-test';
import type { Page } from '@playwright/test';

// TE-015 AC3: opening a page in Chromium, loading it and letting it hydrate produces zero CSP
// violations. Two sources are checked, because browsers report in both: the DOM event
// `securitypolicyviolation` (installed before any page script runs) and console messages that
// mention the policy. Against the dev app (Vite HMR, SSR off) this covers the client render and
// the Vite dev client's own style injection; with SSR on (`bash scripts/dev-ssr.sh on`) it covers
// the server-rendered HTML and hydration as well.

type Violation = {
    directive: string;
    blockedURI: string;
    sourceFile: string;
    line: number;
};

declare global {
    interface Window {
        __cspViolations?: Violation[];
    }
}

async function watchCsp(page: Page): Promise<() => Promise<string[]>> {
    const consoleMessages: string[] = [];
    page.on('console', (message) => {
        if (/content security policy/i.test(message.text())) {
            consoleMessages.push(`console: ${message.text()}`);
        }
    });
    await page.addInitScript(() => {
        window.__cspViolations = [];
        document.addEventListener('securitypolicyviolation', (event) => {
            window.__cspViolations?.push({
                directive: event.violatedDirective,
                blockedURI: event.blockedURI,
                sourceFile: event.sourceFile,
                line: event.lineNumber,
            });
        });
    });

    return async () => {
        const events = await page.evaluate(() => window.__cspViolations ?? []);

        return [
            ...events.map(
                (v) =>
                    `violation: ${v.directive} blocked "${v.blockedURI}" (${v.sourceFile}:${String(v.line)})`,
            ),
            ...consoleMessages,
        ];
    };
}

async function expectNoViolations(
    page: Page,
    path: string,
    expectedStatus: number,
) {
    const violations = await watchCsp(page);

    const response = await page.goto(path);

    expect(response?.status()).toBe(expectedStatus);
    // Without a policy there is nothing to violate, so the test would pass for the wrong reason.
    expect(
        response?.headers()['content-security-policy'],
        'the response has no Content-Security-Policy header',
    ).toContain("script-src 'self' 'nonce-");
    // Hydration, fonts, late effects and Vite's CSS injection all happen after the first paint.
    await page.waitForLoadState('networkidle');
    // Toasts and dialogs mount lazily; give React a tick to settle.
    await page.waitForTimeout(250);

    expect(await violations()).toEqual([]);
}

test('the home page has zero CSP violations', async ({ page }) => {
    await expectNoViolations(page, '/', 200);
});

test('the login page (form, passkey button) has zero CSP violations', async ({
    page,
}) => {
    await expectNoViolations(page, '/login', 200);
});

test('a 404 page has zero CSP violations', async ({ page }) => {
    await expectNoViolations(page, '/csp-spec-this-page-does-not-exist', 404);
});

// A deterministic 500 needs a route that throws; there is none in the app on purpose (no test-only
// routes in production code). Point E2E_CSP_500_PATH at one when the environment has it.
const serverErrorPath = process.env.E2E_CSP_500_PATH ?? '';

test('a 500 page has zero CSP violations', async ({ page }) => {
    test.skip(
        serverErrorPath === '',
        'E2E_CSP_500_PATH is not set; no deterministic 500 route in this environment',
    );

    await expectNoViolations(page, serverErrorPath, 500);
});

test('the nonce differs per request and is exposed to the client for Inertia and Vite', async ({
    page,
}) => {
    const nonces: string[] = [];

    for (let i = 0; i < 2; i++) {
        await page.goto('/');
        nonces.push(
            await page.evaluate(
                () =>
                    document.querySelector<HTMLMetaElement>(
                        'meta[property="csp-nonce"]',
                    )?.nonce ?? '',
            ),
        );
    }

    expect(nonces[0]).not.toBe('');
    expect(nonces[1]).not.toBe('');
    expect(nonces[0]).not.toBe(nonces[1]);
});
