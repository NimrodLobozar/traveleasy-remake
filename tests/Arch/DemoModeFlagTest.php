<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC9: the demo flag is read in exactly one place.
| Source: docs/adr/0006-demo-mode.md ("A Pest arch test forbids reading the flag anywhere else"):
|  - traveleasy.demo (or the whole `traveleasy` config array) is read only by App\Support\DemoMode
|    (app/Support/DemoMode.php), whether through config(), Config::, config()->get() or array access;
|  - DEMO_MODE is read only in config/traveleasy.php (env(), getenv(), $_ENV, $_SERVER, Env::get ...).
| Neither file exists yet (M1), so this passes trivially until then; the exemptions are exact paths.
*/

test('AC9 the demo flag is only read by App\Support\DemoMode and config/traveleasy.php (file scan)', function () {
    $violations = ArchRules::check(
        ArchRules::demoFlag(...),
        SourceTree::files([...ArchRules::PHP_ROOTS, ArchRules::VIEW_ROOT]),
    );

    expect($violations)->toBe([], ArchRules::report('AC9 demo flag only via App\Support\DemoMode (ADR-0006)', $violations));
});
