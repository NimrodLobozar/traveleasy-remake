<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC7: no CSRF exceptions.
| Source: docs/legacy-analysis.md § 3 Security ("CSRF disabled for the chatbot endpoint").
| Laravel 13 names the middleware PreventRequestForgery (ValidateCsrfToken / VerifyCsrfToken are subclasses), and
| validateCsrfTokens() is a deprecated alias of preventRequestForgery(). Both are covered:
|  - any argument other than an empty `except` to validateCsrfTokens() / preventRequestForgery();
|  - any reference to the CSRF middleware other than `extends` (withoutMiddleware, web(remove: ...), ::except());
|  - withoutMiddleware('web') / withoutMiddleware('csrf');
|  - in a subclass: a non-empty $except, except() calls, or overriding the exemption methods.
*/

test('AC7 no CSRF exceptions in app, bootstrap, config, database and routes (file scan)', function () {
    $violations = ArchRules::check(ArchRules::csrfExceptions(...), SourceTree::files(ArchRules::PHP_ROOTS));

    expect($violations)->toBe([], ArchRules::report('AC7 no CSRF exceptions (legacy § 3)', $violations));
});

test('AC7 no CSRF middleware subclass in App has a non-empty $except (reflection)', function () {
    $violations = [];

    foreach (SourceTree::files(['app']) as $file) {
        foreach ($file->declaredClasses as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, PreventRequestForgery::class)) {
                continue;
            }

            $except = (new ReflectionClass($class))->getDefaultProperties()['except'] ?? [];

            if ($except !== []) {
                $violations[] = "{$file->path} {$class} has a non-empty \$except";
            }
        }
    }

    expect($violations)->toBe([], ArchRules::report('AC7 no CSRF exceptions (legacy § 3)', $violations));
});
