<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering (TE-007)
    |--------------------------------------------------------------------------
    |
    | The first visit to a page is rendered to HTML by a separate Node process
    | (`php artisan inertia:start-ssr`, entry `resources/js/ssr.tsx`, bundle in
    | `bootstrap/ssr/`). Laravel POSTs the Inertia page object (exactly what the
    | browser receives in `data-page`, nothing more) to `{url}/render`.
    |
    | If the SSR process is down, slow or fails, the page is served without SSR
    | and React renders it client-side: same HTTP 200, no error text in the
    | response. `throw_on_error` is therefore fixed to false; SSR regressions
    | are caught by the SSR smoke test (TE-008), not by failing real requests.
    |
    | While the Vite dev server runs (public/hot exists), SSR is skipped and dev
    | stays client-side with HMR (AppServiceProvider::configureInertiaSsr()).
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),

        // Where Laravel reaches the SSR server. In Docker this is the compose-internal service name
        // (e.g. http://ssr:13714, TE-008); the SSR port is never published on the host (AC7).
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:'.env('INERTIA_SSR_PORT', '13714')),

        // Total time in seconds for one render call (connect included); on timeout the page falls
        // back to client-side rendering. No retries: a retry would only delay the fallback.
        'timeout' => (float) env('INERTIA_SSR_TIMEOUT', '2'),

        // Never throw on an SSR failure: the client-side fallback must always win (AC4).
        'throw_on_error' => false,

        // Only call the SSR server when a built bundle exists (bootstrap/ssr/ssr.js); the
        // bundle path itself is auto-detected by Inertia.
        'ensure_bundle_exists' => true,

        // Where the Node SSR server itself listens (AC7). The server reads the same env variables
        // (INERTIA_SSR_HOST, INERTIA_SSR_PORT) in resources/js/ssr.tsx with the same defaults.
        // Loopback by default; only a dedicated SSR container sets 0.0.0.0, and then only on the
        // compose-internal network, never with a published port. The SSR server has no
        // authentication (it even has a /shutdown endpoint), so it must never be reachable from outside.
        'server' => [
            'host' => env('INERTIA_SSR_HOST', '127.0.0.1'),
            'port' => (int) env('INERTIA_SSR_PORT', '13714'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
