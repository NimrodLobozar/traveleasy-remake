<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Typed helpers for test code that reads files, YAML or raw query results. Each one fails the test with a
| clear message instead of passing a `false` or `mixed` along, so Larastan (level max) can follow the types.
|
*/

function fileContents(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Could not read [{$path}].");
    }

    return $contents;
}

/** @return list<string> */
function fileLines(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        throw new RuntimeException("Could not read [{$path}].");
    }

    return $lines;
}

/** @return array<array-key, mixed> */
function asArray(mixed $value): array
{
    if (! is_array($value)) {
        throw new UnexpectedValueException('Expected an array, got '.get_debug_type($value).'.');
    }

    return $value;
}

function asString(mixed $value): string
{
    if (! is_string($value)) {
        throw new UnexpectedValueException('Expected a string, got '.get_debug_type($value).'.');
    }

    return $value;
}

/** @return array<string, string> */
function asStringMap(mixed $value): array
{
    $map = [];

    foreach (asArray($value) as $key => $item) {
        $map[asString($key)] = asString($item);
    }

    return $map;
}

/** Accepts an int or an integer string (PDO may return either for integer columns). */
function asInt(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }

    throw new UnexpectedValueException('Expected an integer, got '.get_debug_type($value).'.');
}
