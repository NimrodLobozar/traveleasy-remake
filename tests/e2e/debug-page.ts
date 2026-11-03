import type { Page } from '@playwright/test';
import { expect, test } from './base-test';

/**
 * With APP_DEBUG=true (the dev default) Laravel answers a 5xx with its own debug page instead of
 * the app's error page (TE-019: "APP_DEBUG=true keeps Laravel's debug page for 5xx only"), and that
 * page uses inline script and style without a nonce. The 500/503 checks need an app running with
 * APP_DEBUG=false. Without it they skip, unless E2E_REQUIRE_NON_DEBUG is set (CI), which makes
 * them fail instead, so a silent skip can't hide the gap.
 */
export async function skipOnDebugPage(page: Page): Promise<void> {
    const appRendered = (await page.locator('#app').count()) > 0;
    const required = (process.env.E2E_REQUIRE_NON_DEBUG ?? '') !== '';

    expect(
        required && !appRendered,
        'E2E_REQUIRE_NON_DEBUG is set but the server answered with the framework debug page (APP_DEBUG=true)',
    ).toBe(false);
    test.skip(
        !appRendered,
        'the app runs with APP_DEBUG=true, so a 5xx is the framework debug page; run it with APP_DEBUG=false',
    );
}
