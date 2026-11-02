<?php

/*
|--------------------------------------------------------------------------
| Security headers (TE-015)
|--------------------------------------------------------------------------
|
| Read by App\Support\Security\SecurityHeaders and applied to every response by the global
| App\Http\Middleware\AddSecurityHeaders middleware. Header list: docs/deploy/security-headers.md.
|
*/

return [

    /*
    | HSTS is sent only when APP_URL starts with https:// (production behind Nginx Proxy Manager), never on
    | http:// (dev, LAN). Start with 180 days; raise max_age, and add includeSubDomains/preload only when every
    | subdomain is HTTPS-only for good (preload is hard to undo).
    */
    'hsts' => [
        'max_age' => (int) env('HSTS_MAX_AGE', 15552000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('HSTS_PRELOAD', false),
    ],

    'csp' => [
        /*
        | Optional same-origin path for CSP violation reports (e.g. /csp-report), sent as `report-uri`. Off by
        | default. Only a local path is accepted; a URL or `//host` is ignored, so reports never leave the site.
        | Nothing collects reports yet (out of scope for M0).
        */
        'report_path' => env('CSP_REPORT_PATH'),

        /*
        | Port of the Vite dev server. Only with APP_ENV=local does the CSP also allow
        | http://localhost:<port> and ws://localhost:<port> (HMR); never in any other environment.
        */
        'vite_dev_port' => (int) env('VITE_PORT', 5173),
    ],

    /*
    | TE-012 AC12 (ADR-0002 D6): the reverse proxies whose X-Forwarded-For/-Proto/-Port headers are trusted, as a
    | comma-separated list of IP addresses or CIDR ranges (the `npm_shared` network or NPM's single IP). Empty = trust
    | no proxy. `*`, `**`, `REMOTE_ADDR` and /0 ranges are rejected (App\Support\Security\TrustedProxies).
    */
    'trusted_proxies' => (string) env('TRUSTED_PROXIES', ''),

];
