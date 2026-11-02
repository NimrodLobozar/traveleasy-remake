<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Security\SecurityHeaders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the CSP and the other security headers to every response (TE-015).
 *
 * Global and outermost, so responses rendered from exceptions (403/404/419/429/500/503) get the same headers. The
 * nonce is made once per request, before anything renders, and handed to Vite (`@vite`, `@viteReactRefresh`) and the
 * Blade views (Vite::cspNonce()). The client reads it from `<meta property="csp-nonce">` (docs/deploy/security-headers.md).
 */
final class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = SecurityHeaders::nonce();
        Vite::useCspNonce($nonce);

        $response = $next($request);

        foreach (SecurityHeaders::fromConfig()->headers($nonce) as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
