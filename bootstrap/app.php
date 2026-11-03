<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use App\Support\Errors\ErrorPageRenderer;
use App\Support\Health\HealthCheckFailed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // TE-028 AC3: our own plain-text `/up` (no external URLs), outside the `web` group; the framework's
        // `health:` page references fonts.bunny.net and jsdelivr.
        then: function (): void {
            Route::get('/up', HealthController::class)->name('health');

            // TE-019 AC10: deterministic error pages for tests and the e2e CSP check; never registered in production.
            if (app()->environment(['local', 'testing'])) {
                Route::middleware('web')->group(base_path('routes/testing.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // prepend() puts a middleware first, so the last call below is the outermost.
        // TE-019 AC3: the request ID for the log context, the X-Request-Id header and the error pages.
        $middleware->prepend(AssignRequestId::class);
        // TE-015: outermost global middleware, so every response, including rendered exceptions, gets the CSP
        // and the other security headers, and the nonce exists before anything renders.
        $middleware->prepend(AddSecurityHeaders::class);

        // TE-012 AC12: trust X-Forwarded-For/-Proto/-Port only from TRUSTED_PROXIES (never `*`, never -Host).
        $middleware->replace(FrameworkTrustProxies::class, TrustProxies::class);

        // Plain, allow-listed UI preferences; never personal data. `locale` is validated by LocaleResolver (TE-009).
        $middleware->encryptCookies(except: ['appearance', 'locale', 'sidebar_state']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // TE-012 AC5: the health check logs the cause itself, without connection details.
        $exceptions->dontReport(HealthCheckFailed::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // TE-019: HTML and Inertia errors render the localised error page in the shell (JSON stays JSON).
        $exceptions->respond(
            fn (Response $response, Throwable $exception, Request $request): Response => app(ErrorPageRenderer::class)
                ->render($response, $exception, $request),
        );
    })->create();
