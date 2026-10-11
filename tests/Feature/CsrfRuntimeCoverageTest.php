<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Router;

/*
| TE-004 AC7, runtime half (added by qa-engineer). The file scan in tests/Arch/CsrfTest.php looks for CSRF
| exceptions in the source; this test checks the booted application instead, so it also catches forms the
| scanner can't see: a `web` group redefined without the CSRF middleware ($middleware->group('web', [...])),
| a route registered outside the `web` group, or an exception list built at runtime.
| Source: docs/legacy-analysis.md § 3 Security ("CSRF disabled for the chatbot endpoint").
*/

/** Route names that are protected by a signed URL instead of a session, so CSRF does not apply. */
const CSRF_RUNTIME_SIGNED_ROUTES = ['storage.local.upload'];

test('AC7 every state-changing route runs the CSRF middleware (runtime)', function () {
    // Resolving the HTTP kernel syncs the middleware groups from bootstrap/app.php into the router.
    app(HttpKernel::class);
    $router = app(Router::class);
    $checked = 0;
    $unprotected = [];

    foreach ($router->getRoutes()->getRoutes() as $route) {
        if (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) === []) {
            continue;
        }

        // Framework route of the local disk's `serve` option (config/filesystems.php): a session-less PUT that
        // ReceiveFile only accepts with a valid signed upload URL, so a CSRF token does not apply.
        if (in_array($route->getName(), CSRF_RUNTIME_SIGNED_ROUTES, true)) {
            continue;
        }

        $checked++;
        $protected = false;

        foreach ($router->gatherRouteMiddleware($route) as $middleware) {
            $class = is_string($middleware) ? explode(':', $middleware, 2)[0] : null;

            if ($class !== null && is_a($class, PreventRequestForgery::class, true)) {
                $protected = true;
            }
        }

        if (! $protected) {
            $unprotected[] = implode('|', $route->methods()).' /'.ltrim($route->uri(), '/');
        }
    }

    expect($checked)->toBeGreaterThan(0, 'No state-changing routes found; the check would pass vacuously.')
        ->and($unprotected)->toBe([], "AC7: state-changing routes without CSRF protection:\n  ".implode("\n  ", $unprotected));
});

test('AC7 the booted CSRF middleware has no excluded paths and no relaxed mode (runtime)', function () {
    $middleware = app(PreventRequestForgery::class);
    $static = new ReflectionClass(PreventRequestForgery::class);

    expect($middleware->getExcludedPaths())->toBe([])
        ->and($static->getStaticPropertyValue('originOnly'))->toBeFalse()
        ->and($static->getStaticPropertyValue('allowSameSite'))->toBeFalse();
});
