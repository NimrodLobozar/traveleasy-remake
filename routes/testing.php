<?php

declare(strict_types=1);

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;

/*
| TE-019 AC2/AC10: deterministic error pages for Pest and the e2e CSP check (E2E_CSP_500_PATH=/_test/error/500).
| Loaded by bootstrap/app.php only when APP_ENV is `local` or `testing`, never in production
| (tests/Arch/TestingRoutesTest.php proves it).
*/

Route::get('/_test/error/{status}', function (string $status): never {
    // Always the production page, also in a dev container with APP_DEBUG=true (whose debug page has no CSP nonces).
    config(['app.debug' => false]);

    match ($status) {
        '429' => throw new ThrottleRequestsException('Too Many Attempts.', headers: ['Retry-After' => '42']),
        // AC4: a message with SQL, an e-mail address and a file path, none of which may reach the page.
        '500' => throw new RuntimeException(
            'SQLSTATE[42P01]: select * from "users" where "email" = \'leak@example.test\' (/var/www/html/app/Leak.php:42)',
        ),
        default => abort((int) $status),
    };
})->whereIn('status', ['403', '404', '419', '429', '500', '503'])->name('testing.error');

// TE-022: the component showcase (axe and Playwright scan every component and state here).
// @phpstan-ignore method.nonObject (inertia-laravel registers the Route::inertia() macro without a return type; it returns the Route.)
Route::inertia('/design', 'design')->name('testing.design');
