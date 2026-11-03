<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the theme for the server-rendered `<html>` (TE-018): the allow-listed `appearance` cookie, else `system`.
 *
 * The root view gets an Appearance enum, never the raw cookie, so nothing from the cookie is echoed into the page.
 */
final class HandleAppearance
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', Appearance::fromRequest($request));

        return $next($request);
    }
}
