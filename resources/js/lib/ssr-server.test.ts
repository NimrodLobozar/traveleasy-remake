// @vitest-environment node
// TE-008 AC9: a failed render leaves no URL, message or stack in the SSR process output, and
// neither a render error nor a failed start-up takes the server down.
import { createServer as createNetServer } from 'node:net';
import { describe, expect, it, vi } from 'vite-plus/test';
import {
    SCRUBBED_ERROR_LINE,
    SsrRenderError,
    guardRenderer,
    scrubConsoleError,
    startSsrServer,
} from './ssr-server';

function freePort(): Promise<number> {
    return new Promise((resolve, reject) => {
        const probe = createNetServer();
        probe.once('error', reject);
        probe.listen(0, '127.0.0.1', () => {
            const address = probe.address();
            const port =
                typeof address === 'object' && address ? address.port : 0;
            probe.close(() => {
                resolve(port);
            });
        });
    });
}

async function waitForHealth(base: string): Promise<void> {
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            if ((await fetch(`${base}/health`)).ok) {
                return;
            }
        } catch {
            // not listening yet
        }
        await new Promise((resolve) => setTimeout(resolve, 20));
    }
    throw new Error('SSR test server did not start');
}

const secretPage = {
    component: 'bookings/show',
    props: { passport: 'NL1234567' },
    url: '/bookings/42?token=s3cret',
    version: 'test',
};

describe('scrubConsoleError', () => {
    it('replaces whatever is printed with one fixed line, and can be undone', () => {
        const sink = vi.fn();
        const fakeConsole = { error: sink } as Pick<Console, 'error'>;

        const restore = scrubConsoleError(fakeConsole);
        fakeConsole.error('URL: /secret', new Error('boom'));

        expect(sink).toHaveBeenCalledExactlyOnceWith(SCRUBBED_ERROR_LINE);

        restore();
        fakeConsole.error('visible again');

        expect(sink).toHaveBeenLastCalledWith('visible again');
    });
});

describe('guardRenderer', () => {
    it('turns any failure into a SsrRenderError without message or stack', async () => {
        const guarded = guardRenderer(Promise.resolve('renderer'), () =>
            Promise.reject(new Error('boom user@example.com')),
        );

        const error = await guarded(secretPage).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(SsrRenderError);
        expect((error as Error).message).toBe('SSR render failed');
        expect((error as Error).stack).toBeUndefined();
    });

    it('does not raise an unhandled rejection when start-up fails', async () => {
        const unhandled = vi.fn();
        process.on('unhandledRejection', unhandled);

        try {
            const guarded = guardRenderer(
                Promise.reject(new Error('startup secret')),
                () => Promise.resolve('never'),
            );
            // Let the event loop reach the point where Node reports unhandled rejections.
            await new Promise((resolve) => setTimeout(resolve, 50));

            expect(unhandled).not.toHaveBeenCalled();
            // Every render still reports the failure.
            await expect(guarded(secretPage)).rejects.toBeInstanceOf(
                SsrRenderError,
            );
        } finally {
            process.off('unhandledRejection', unhandled);
        }
    });
});

describe('startSsrServer', () => {
    it('prints no URL, message or stack on a render error and keeps serving', async () => {
        const printed = vi
            .spyOn(console, 'error')
            .mockImplementation(() => undefined);
        const port = await freePort();
        const base = `http://127.0.0.1:${String(port)}`;

        startSsrServer(
            guardRenderer(Promise.resolve('renderer'), () =>
                Promise.reject(new Error('boom user@example.com')),
            ),
            { host: '127.0.0.1', port },
        );
        await waitForHealth(base);

        const failed = await fetch(`${base}/render`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(secretPage),
        });

        expect(failed.status).toBe(500);

        // Everything the server printed for the failed render.
        const output = printed.mock.calls.flat().map(String).join('\n');

        expect(output).toContain(SCRUBBED_ERROR_LINE);
        expect(output).not.toMatch(
            /s3cret|bookings|NL1234567|boom|example\.com/,
        );
        expect(output).not.toMatch(/\bat .*\(.*:\d+:\d+\)/);

        // The server survived the failure.
        expect((await fetch(`${base}/health`)).status).toBe(200);
    });
});
