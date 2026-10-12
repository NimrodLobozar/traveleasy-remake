<?php

declare(strict_types=1);

use Tests\Support\SourceTree;
use Tests\TestCase;

/*
| TE-004 AC14, runtime half (rework round 1, security L5). Laravel merges its own default queue connections and
| cache stores (including `sqs` and `dynamodb`) back into the config at runtime, so removing them from config/
| only shrinks the documented env surface. This proves nothing selects a cloud driver and the AWS SDK that they
| would need is not installed.
| Source: TE-001 security review (unused third-party stores); CLAUDE.md "secrets only from env".
*/

pest()->extend(TestCase::class);

test('AC14 the default queue, cache and filesystem drivers are local ones at runtime', function () {
    expect(config('queue.default'))->toBeIn(['database', 'sync'], 'AC14: queue.default selects a non-local queue driver.')
        ->and(config('cache.default'))->toBeIn(['database', 'array'], 'AC14: cache.default selects a non-local cache store.')
        ->and(config('filesystems.default'))->toBeIn(['local', 'public'], 'AC14: filesystems.default selects a non-local disk.');
});

test('AC14 the AWS SDK is not installed (composer.lock)', function () {
    // Installed packages only: other packages mention aws/aws-sdk-php in their `suggest` / `require-dev` lists.
    $lock = asArray(json_decode(fileContents(SourceTree::root().'/composer.lock'), true, flags: JSON_THROW_ON_ERROR));
    $installed = [];

    foreach (['packages', 'packages-dev'] as $section) {
        foreach (asArray($lock[$section] ?? []) as $package) {
            $installed[] = asString(asArray($package)['name'] ?? '');
        }
    }

    expect($installed)->not->toBeEmpty()->not->toContain('aws/aws-sdk-php');
});
