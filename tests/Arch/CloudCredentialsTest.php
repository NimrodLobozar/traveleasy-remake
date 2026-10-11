<?php

declare(strict_types=1);

use Tests\Support\SourceTree;

/*
| TE-004 AC14: no unused cloud stores, so no cloud credentials in the env surface.
| Source: TE-001 security review (shrink the env surface the arch rules protect); CLAUDE.md "secrets only from env".
| The `sqs` queue connection and the `dynamodb` cache store (both read AWS_* / SQS_*) were removed from
| config/queue.php and config/cache.php; the database queue and cache drivers stay.
*/

test('AC14 config/ and .env.example read no AWS_* or SQS_* variables', function () {
    $root = SourceTree::root();
    $hits = [];

    foreach ([...glob($root.'/config/*.php') ?: [], $root.'/.env.example'] as $path) {
        foreach (fileLines($path) as $number => $line) {
            if (preg_match('/\b(AWS|SQS)_/', $line) === 1) {
                $hits[] = substr($path, strlen($root) + 1).':'.($number + 1).' '.trim($line);
            }
        }
    }

    expect($hits)->toBe([], "AC14: cloud credentials are read:\n  ".implode("\n  ", $hits));
});

test('AC14 the sqs queue connection and the dynamodb cache store are gone; database stays', function () {
    $connections = array_column(SourceTree::load('config/queue.php')->strings, 'value');
    $stores = array_column(SourceTree::load('config/cache.php')->strings, 'value');

    expect($connections)->not->toContain('sqs')->toContain('database')
        ->and($stores)->not->toContain('dynamodb')->toContain('database');
});
