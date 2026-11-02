import createServer from '@inertiajs/react/server';

// Helpers for the Node SSR entry (ssr.tsx), kept out of that file so a test can start the real
// server on a free port. Node-only: never import this from a component.

/** The one line the SSR process prints when a render fails. */
export const SCRUBBED_ERROR_LINE =
    'SSR render failed; details withheld (they can contain page URLs, user data and stack traces).';

/** What the render throws instead of the original error; carries no message or stack. */
export class SsrRenderError extends Error {
    constructor() {
        super('SSR render failed');
        this.name = 'SsrRenderError';
        // Nothing to show: the stack of a render failure points into app code and can quote
        // props. `undefined` makes @inertiajs/core print and answer without a stack.
        this.stack = undefined;
    }
}

/**
 * `createServer` (@inertiajs/core, `formatErrors: true`) catches a failed render and prints the
 * page URL, the error message and the stack with `console.error`. In production those land in
 * the container log, which can hold a visitor's URL (query string, ids) and quoted props
 * (TE-008 AC9). `formatErrors: false` would rethrow instead, and an unhandled rejection crashes
 * the process. So keep the library's flow and replace what it prints: every `console.error` in
 * the SSR process becomes the single fixed line above. Returns a function that restores the
 * original (tests only).
 */
export function scrubConsoleError(
    target: Pick<Console, 'error'> = console,
): () => void {
    const original = target.error;

    target.error = () => {
        original.call(target, SCRUBBED_ERROR_LINE);
    };

    return () => {
        target.error = original;
    };
}

export type PageRenderer<Page, Result> = (page: Page) => Promise<Result>;

/**
 * Wrap the page renderer. A failure, including a failed start-up of `createInertiaApp`, becomes a
 * `SsrRenderError`, so the 500 answer and the log carry neither the original message nor its
 * stack. The start-up promise gets a no-op catch so a start-up error is not an unhandled
 * rejection (that would take the whole server down); every render still awaits it and reports it.
 */
export function guardRenderer<Page, Result, Renderer>(
    renderer: Promise<Renderer>,
    render: (renderer: Renderer, page: Page) => Promise<Result>,
): PageRenderer<Page, Result> {
    renderer.catch(() => undefined);

    return async (page) => {
        try {
            return await render(await renderer, page);
        } catch {
            throw new SsrRenderError();
        }
    };
}

export type SsrServerOptions = { host: string; port: number };

/**
 * Start the Inertia SSR HTTP server with scrubbed error output. The server has no
 * authentication, so the caller passes the bind address (loopback unless the environment says
 * otherwise).
 */
export function startSsrServer(
    render: Parameters<typeof createServer>[0],
    options: SsrServerOptions,
): void {
    scrubConsoleError();
    createServer(render, options);
}
