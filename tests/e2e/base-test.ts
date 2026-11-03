import { expect, test as base } from '@playwright/test';
import { isLocalRequest } from './external-requests';

/**
 * The `test` every spec imports instead of `@playwright/test` (TE-029 AC1). Its auto-fixture
 * watches the whole browser context (pages, popups, workers, service workers) and fails the test
 * when any request goes to a non-localhost origin, including raw IP addresses. e2e-rules
 * (external-requests.test.ts) checks that no spec imports `test` from Playwright directly, and
 * external-requests.spec.ts proves the fixture fails a test on a hostname and on a raw IP.
 */
export const test = base.extend<{ externalRequests: undefined }>({
    externalRequests: [
        async ({ context }, use) => {
            const offenders: string[] = [];
            context.on('request', (request) => {
                if (!isLocalRequest(request.url())) {
                    offenders.push(request.url());
                }
            });

            await use(undefined);

            expect(
                offenders,
                'the test made requests to non-localhost origins',
            ).toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
