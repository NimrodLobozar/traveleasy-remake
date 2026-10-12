<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Http\Kernel;

/**
 * Test helper around the HTTP kernel of the already booted (and guarded) test application.
 *
 * TE-004 AC3 (security L4) forbids test files outside tests/TestCase.php and tests/Support/ from referencing the
 * kernel contracts, because they are a way to boot an app without the TE-002 database guard. Tests that only
 * need the kernel of the current, guarded app go through this helper instead.
 */
final class HttpKernel
{
    /** Resolves the HTTP kernel, which syncs the middleware groups from bootstrap/app.php into the router. */
    public static function syncMiddlewareGroups(): void
    {
        app(Kernel::class);
    }
}
