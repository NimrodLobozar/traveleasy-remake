<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Enums\Appearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Http\RequestId;
use App\Support\I18n\LocaleResolver;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Renders HTML and Inertia error responses as the localised `error` page inside the shell (TE-019).
 *
 * - The HTTP status is kept; the page shows one of the six designed variants (403, 404, 419, 429, 500, 503): 401
 *   shows 403, 405 shows 404, any other 4xx/5xx shows 500.
 * - Props are only `status`, `requestId` and `retryAfter`: never the exception message, SQL, paths or a stack trace.
 * - Works without the web middleware (unmatched URLs, maintenance mode): it resolves the locale and theme itself and
 *   shares the app props without touching the database or the session store.
 * - Untouched: JSON responses (API, `expectsJson`), redirects, deliberate HttpResponseException responses, and 5xx
 *   while APP_DEBUG is on (the local debug page). If rendering fails, the framework's plain page is served.
 */
final readonly class ErrorPageRenderer
{
    /** The variants of the design (docs/design/system.md § 3, error.<code>.* keys). */
    public const array VARIANTS = [403, 404, 419, 429, 500, 503];

    /** The Inertia page component (resources/js/pages/error.tsx). */
    public const string COMPONENT = 'error';

    public function __construct(private LocaleResolver $locales) {}

    public function render(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if ($status < 400
            || $response instanceof JsonResponse
            || $exception instanceof HttpResponseException
            || ($status >= 500 && config()->boolean('app.debug'))) {
            return $response;
        }

        try {
            return $this->page($request, $response, $status);
        } catch (Throwable $failure) {
            // Class and location only: the message of a rendering failure can echo request data.
            Log::error('Error page rendering failed; served the plain fallback page.', [
                'failure' => $failure::class,
                'source_location' => $failure->getFile().':'.$failure->getLine(),
                'status' => $status,
            ]);

            return $response;
        }
    }

    public static function variant(int $status): int
    {
        return match (true) {
            in_array($status, self::VARIANTS, true) => $status,
            $status === 401 => 403,
            $status === 405 => 404,
            default => 500,
        };
    }

    private function page(Request $request, Response $original, int $status): Response
    {
        $locale = $this->locales->resolve($request);
        App::setLocale($locale->value);
        View::share('appearance', Appearance::fromRequest($request));

        // Only a user the request already loaded: no database query while rendering an error (the DB may be the cause).
        $user = Auth::hasUser() ? Auth::user() : null;
        Inertia::share([
            'errors' => (object) [],
            ...HandleInertiaRequests::appProps($request, $user),
        ]);

        $variant = self::variant($status);
        $retryAfter = $this->retryAfter($original);

        $response = Inertia::render(self::COMPONENT, [
            'status' => $variant,
            'requestId' => RequestId::of($request),
            'retryAfter' => in_array($variant, [429, 503], true) ? $retryAfter : null,
        ])->toResponse($request);

        $response->setStatusCode($status);
        $response->headers->set('Content-Language', $locale->value);
        $response->setVary(['X-Inertia', 'Accept-Language'], false);

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Seconds from a numeric `Retry-After` header (throttling, maintenance), else null.
     */
    private function retryAfter(Response $response): ?int
    {
        $value = $response->headers->get('Retry-After');

        return is_string($value) && ctype_digit($value) && strlen($value) <= 9 ? (int) $value : null;
    }
}
