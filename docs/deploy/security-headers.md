# Security headers and CSP

What the app sends on every response (TE-015), where it's configured, and how to change it safely.

## Where

- Builder: `app/Support/Security/SecurityHeaders.php` (pure, unit-tested).
- Middleware: `app/Http/Middleware/AddSecurityHeaders.php`, the **outermost global** middleware (`bootstrap/app.php`),
  so error responses rendered from exceptions (403/404/419/429/500/503), JSON errors, file downloads and `/up` get the
  same headers.
- Config: `config/security.php`; env switches in `.env.example` § Security headers.

## Headers

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'nonce-…'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` (older browsers; `frame-ancestors` is the real rule) |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Strict-Transport-Security` | `max-age=15552000`, **only when `APP_URL` starts with `https://`** |
| `X-Powered-By` | removed (also set `expose_php=Off` in the production image, TE-012) |

No `'unsafe-inline'` and no `'unsafe-eval'`, for scripts **and** styles, and no external origin in any directive.

## The nonce

- One per request: 16 random bytes, base64 (`SecurityHeaders::nonce()`), set before anything renders and handed to
  `Vite::useCspNonce()`, so every `@vite` / `@viteReactRefresh` tag carries it.
- Blade: `nonce="{{ Vite::cspNonce() }}"` on every inline `<script>`/`<style>` (theme script and style in
  `resources/views/app.blade.php`, the error layout `resources/views/errors/minimal.blade.php`).
- Browser: `<meta property="csp-nonce" nonce="…">` is the first tag in `<head>`. Vite's dev client reads it itself;
  the app reads it with `readCspNonce()` (`resources/js/lib/csp-nonce.ts`) and passes it to `createInertiaApp({ nonce })`
  for Inertia's progress bar and error dialog. It is not an Inertia prop.
- The Inertia page object (`<script data-page type="application/json">`) is data, never executed, so CSP doesn't apply.
- `style="…"` **attributes** are blocked (nonces don't cover attributes). React `style` props are fine when set in the
  browser, but under SSR they become attributes: use classes (e.g. Tailwind `[--sidebar-width:16rem]`) instead.
- Rule (arch test `tests/Arch/InlineScriptTest.php`): no inline event handlers (`onclick="…"`), no `javascript:` URLs,
  and no raw-HTML sink (`dangerouslySetInnerHTML`, `innerHTML`, `insertAdjacentHTML`, `document.write`) outside the
  reviewed allow-list in `tests/Support/MarkupRules.php`.

## Dev server (local only)

With `APP_ENV=local` the CSP also allows the Vite dev server: `http://localhost:<VITE_PORT>` in `script-src`,
`style-src`, `img-src`, `font-src`, `connect-src`, and `ws://localhost:<VITE_PORT>` in `connect-src`. Any other
environment never includes it. Laravel's debug error page (`APP_DEBUG=true`, dev only) uses inline scripts and styles
without the nonce, so it logs CSP violations in the browser console; production runs with `APP_DEBUG=false`.

## HSTS

Sent only when `APP_URL` is `https://…` (production behind Nginx Proxy Manager); never on `http://` (dev, LAN).

| Variable | Default | Meaning |
|---|---|---|
| `HSTS_MAX_AGE` | `15552000` (180 days) | seconds browsers stay HTTPS-only |
| `HSTS_INCLUDE_SUBDOMAINS` | `false` | adds `includeSubDomains` |
| `HSTS_PRELOAD` | `false` | adds `preload` |

How to raise it: run a few weeks on the default without HTTPS problems, then `HSTS_MAX_AGE=31536000` (1 year). Add
`includeSubDomains` only when **every** subdomain of the domain is HTTPS-only (the owner's other demo sites too), and
`preload` only after submitting to hstspreload.org on purpose: removal takes months. Rolling back means a short
`max-age` that browsers only pick up on their next visit.

## Reporting

None by default: no `report-uri`, `report-to` or `Reporting-Endpoints`. `CSP_REPORT_PATH` may set a **same-origin path**
(e.g. `/csp-report`); anything else (a URL, `//host`, a path with spaces or `;`) is ignored, so reports never leave the
site. Nothing collects reports yet (out of scope for M0).

## Static files

The PHP app only adds headers to responses it serves. Files served directly by the web server (`public/build/*`,
favicons; `php artisan serve` in dev, FrankenPHP/Caddy in production) bypass Laravel: the production web server config
(TE-012) must add at least `X-Content-Type-Options: nosniff` and remove `Server`/`X-Powered-By` there.
