#!/usr/bin/env node
// SSR smoke test (TE-008). Plain Node, no Docker or Compose specifics, so CI can call it too:
//
//   node scripts/ssr-smoke.mjs [--mode on|down] [--url http://127.0.0.1:8000] [--path /] [--text "Heading"]
//
// --mode on   (default) The page must be server-rendered: HTTP 200, <html lang="..">, the Inertia root
//             marked data-server-rendered and holding real markup (an <h1>, or --text if given).
// --mode down Run with the SSR server stopped: the page must still answer 200 with <html lang> and fall
//             back to client-side rendering (no server-rendered markup, page data still embedded).
// Env equivalents: SSR_SMOKE_URL, SSR_SMOKE_PATH, SSR_SMOKE_TEXT. Exit code 0 = pass, 1 = fail, 2 = usage.
import process from 'node:process';

const args = process.argv.slice(2);
const option = (name, fallback) => {
    const index = args.indexOf(`--${name}`);
    const value = index === -1 ? undefined : args[index + 1];
    return value ?? fallback;
};

const mode = option('mode', 'on');
const base = option(
    'url',
    process.env.SSR_SMOKE_URL ?? 'http://127.0.0.1:8000',
);
const path = option('path', process.env.SSR_SMOKE_PATH ?? '/');
const text = option('text', process.env.SSR_SMOKE_TEXT ?? '');

if (mode !== 'on' && mode !== 'down') {
    console.error(`Unknown --mode "${mode}"; use "on" or "down".`);
    process.exit(2);
}

const fail = (message) => {
    console.error(`SSR smoke (${mode}) FAILED for ${path}: ${message}`);
    process.exit(1);
};

let response;
try {
    response = await fetch(new URL(path, base), {
        headers: { accept: 'text/html' },
        signal: AbortSignal.timeout(15_000),
    });
} catch (error) {
    fail(
        `could not fetch ${base}${path} (${error instanceof Error ? error.message : error}).`,
    );
}

if (response.status !== 200) {
    fail(`expected HTTP 200, got ${response.status}.`);
}

const html = await response.text();

if (!/<html[^>]*\slang="[^"]+"/i.test(html)) {
    fail('<html> has no lang attribute.');
}

// The attribute order is up to Inertia, so match the root tag as a whole.
const rootTag = /<div\b(?=[^>]*\sid="app")[^>]*>/i.exec(html);
const rendered =
    rootTag !== null && /\sdata-server-rendered\b/i.test(rootTag[0]);

if (mode === 'down') {
    if (rendered) {
        fail(
            'SSR is supposed to be down, but the page is server-rendered. Is the ssr service stopped?',
        );
    }
    if (!/data-page=/.test(html)) {
        fail(
            'no embedded Inertia page data; the client-side fallback cannot start.',
        );
    }
    console.log('SSR down: client-side fallback OK');
    process.exit(0);
}

if (!rendered && html.includes('/@vite/client')) {
    fail(
        'the page loads assets from the Vite dev server (public/hot exists), and Laravel skips SSR then. ' +
            'In Docker dev run `bash scripts/dev-ssr.sh on` (editing vite.config.ts or a file it imports restarts Vite, which writes public/hot again).',
    );
}

if (!rendered) {
    fail(
        'the Inertia root has no data-server-rendered marker, so the page fell back to client-side rendering. ' +
            'Check that the ssr server is up and that no code uses browser globals (window, document, ...) while rendering: ' +
            'look at the ssr container logs and the "Inertia SSR render failed" warning in storage/logs/laravel.log.',
    );
}

const root = html.slice(rootTag?.index ?? 0);
if (text === '' ? !/<h1[\s>]/i.test(root) : !root.includes(text)) {
    fail(
        text === ''
            ? 'the server-rendered root contains no <h1>.'
            : `the server-rendered root does not contain "${text}".`,
    );
}

console.log('SSR on: server-rendered content OK');
