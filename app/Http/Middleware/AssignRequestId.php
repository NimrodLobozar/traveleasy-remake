<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a random reference (TE-019 AC3): `request_id` in the log Context of every log line, the
 * `X-Request-Id` response header and the "Referentie" on error pages are the same value.
 *
 * Global, right after AddSecurityHeaders, so responses rendered from exceptions carry it too.
 */
final class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = RequestId::of($request);
        Context::add(RequestId::CONTEXT_KEY, $id);

        $response = $next($request);
        $response->headers->set(RequestId::HEADER, $id);

        return $response;
    }
}
