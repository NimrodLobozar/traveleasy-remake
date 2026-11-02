import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import process from 'node:process';
import { renderToString } from 'react-dom/server';
import { layoutFor } from '@/lib/inertia-layout';
import { pageTitle } from '@/lib/page-title';
import { withProviders } from '@/lib/with-providers';

// Server entry (TE-007): `vp build --ssr` bundles this into bootstrap/ssr/ssr.js, and
// `php artisan inertia:start-ssr` runs it. Keep the createInertiaApp options in step with app.tsx,
// minus anything that needs the browser (initializeTheme, the progress bar); the markup must
// match or React warns about hydration. Page components are resolved by the @inertiajs/vite plugin.
const renderer = createInertiaApp({
    title: pageTitle,
    layout: layoutFor,
    strictMode: true,
    withApp: withProviders,
});

// The first render surfaces a startup error; keep it from crashing the process as an unhandled
// rejection (every render awaits the same promise and reports it).
renderer.catch(() => undefined);

// The SSR server has no authentication, so it listens on loopback unless the environment says
// otherwise (a dedicated container on the compose-internal network sets 0.0.0.0; never publish
// the port). Same variables and defaults as config/inertia.php.
createServer(
    async (page) => {
        const render = await renderer;

        if (!render) {
            throw new Error('createInertiaApp did not return a page renderer');
        }

        // createServer types the page with the bare PageProps; the shared props are the app's own,
        // and Laravel sends exactly those (HandleInertiaRequests).
        return render(page as Parameters<typeof render>[0], renderToString);
    },
    {
        host: process.env.INERTIA_SSR_HOST || '127.0.0.1',
        port: Number(process.env.INERTIA_SSR_PORT || 13714),
    },
);
