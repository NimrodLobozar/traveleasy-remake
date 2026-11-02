<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\I18n\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the request from the LocaleResolver chain (TE-009).
 *
 * The locale drives `<html lang>`, the shared `locale`/`translations` Inertia props and `__()`. The response says
 * which language it is in and that it varies by Accept-Language, so shared caches never mix languages.
 */
final readonly class SetLocale
{
    public function __construct(private LocaleResolver $resolver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolver->resolve($request);

        App::setLocale($locale->value);

        $response = $next($request);

        $response->headers->set('Content-Language', $locale->value);
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
