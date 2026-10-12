<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC7: no CSRF exceptions.
| Source: docs/legacy-analysis.md § 3 Security ("CSRF disabled for the chatbot endpoint").
| Laravel 13 names the middleware PreventRequestForgery (ValidateCsrfToken / VerifyCsrfToken are subclasses), and
| validateCsrfTokens() is a deprecated alias of preventRequestForgery(). Covered (rework round 1, security M1):
|  - any argument other than an empty `except` to validateCsrfTokens() / preventRequestForgery();
|  - no App class extends the CSRF middleware, directly or through another class (Laravel 13 needs no subclass,
|    so this also covers $except, overridden handle()/getTokenFromRequest()/inExceptArray() and array_push on
|    $this->except);
|  - any other reference to the CSRF middleware (withoutMiddleware, web(remove:/replace:), replaceInGroup,
|    ::except()), in every string form;
|  - a redefined `web` group, a custom group with StartSession but without CSRF, withoutMiddleware('web'/'csrf').
| The runtime half is tests/Feature/CsrfRuntimeCoverageTest.php (every state-changing route runs CSRF).
*/

test('AC7 no CSRF exceptions in app, bootstrap, config, database and routes (file scan)', function () {
    $violations = ArchRules::check(ArchRules::csrfExceptions(...), SourceTree::files(ArchRules::PHP_ROOTS));

    expect($violations)->toBe([], ArchRules::report('AC7 no CSRF exceptions (legacy § 3)', $violations));
});

test('AC7 no class in app or database extends the CSRF middleware indirectly (file scan)', function () {
    $violations = ArchRules::csrfSubclasses(SourceTree::files(['app', 'database']));

    expect($violations)->toBe([], ArchRules::report('AC7 no CSRF exceptions (legacy § 3)', $violations));
});

test('AC7 no App class is a subclass of the CSRF middleware (reflection)', function () {
    $violations = [];

    foreach (SourceTree::files(['app']) as $file) {
        foreach ($file->declaredClasses as $class) {
            if (class_exists($class) && is_subclass_of($class, PreventRequestForgery::class)) {
                $violations[] = "{$file->path} {$class} extends the CSRF middleware";
            }
        }
    }

    expect($violations)->toBe([], ArchRules::report('AC7 no CSRF exceptions (legacy § 3)', $violations));
});
