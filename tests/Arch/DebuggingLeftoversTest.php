<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC4: no debugging leftovers.
| Source: docs/legacy-analysis.md § 3 (the original shipped debugging leftovers and fake-error switches);
| docs/plan.md § 4 M0 (Pest arch tests).
*/

foreach (ArchRules::ARCH_NAMESPACES as $namespace) {
    arch("AC4 no debugging leftovers in {$namespace} (arch)")
        ->expect($namespace)
        ->not->toUse(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit']);
}

test('AC4 no debugging leftovers in app, bootstrap, config, database, routes and views (file scan)', function () {
    $violations = ArchRules::check(
        ArchRules::debuggingLeftovers(...),
        SourceTree::files([...ArchRules::PHP_ROOTS, ArchRules::VIEW_ROOT]),
    );

    expect($violations)->toBe([], ArchRules::report('AC4 no debugging leftovers (legacy § 3)', $violations));
});
