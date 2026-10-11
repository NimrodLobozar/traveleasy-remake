<?php

declare(strict_types=1);

/*
| TE-004 AC10: Pest's `php` and `security` arch presets.
| Source: docs/plan.md § 4 M0; docs/legacy-analysis.md § 3 (debugging leftovers, insecure functions).
| Pest runs presets over every namespace in composer.json's autoload (App, Database\Factories,
| Database\Seeders), which includes App. No expectation is excluded.
*/

arch('AC10 php preset')->preset()->php();

arch('AC10 security preset')->preset()->security();
