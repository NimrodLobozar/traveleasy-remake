<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC5: env() is only read in config/. Everything else reads config(), so `config:cache` works and the
| env surface stays in one reviewed place.
| Source: docs/plan.md § 4 M0; CLAUDE.md "secrets only from env" (read once, in config/).
| Also catches getenv(), putenv(), Env::get() and $_ENV / $_SERVER, which read the same environment.
*/

foreach (ArchRules::ARCH_NAMESPACES as $namespace) {
    arch("AC5 env() only in config: not in {$namespace} (arch)")
        ->expect($namespace)
        ->not->toUse(['env', 'getenv', 'putenv', 'Illuminate\Support\Env']);
}

test('AC5 env() only in config: app, bootstrap, database, routes and views (file scan)', function () {
    $violations = ArchRules::check(
        ArchRules::envOutsideConfig(...),
        SourceTree::files([...ArchRules::PHP_ROOTS, ArchRules::VIEW_ROOT], exclude: ['config/']),
    );

    expect($violations)->toBe([], ArchRules::report('AC5 env() only in config/', $violations));
});
