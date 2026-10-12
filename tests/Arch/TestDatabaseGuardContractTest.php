<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceFile;
use Tests\Support\SourceTree;

/*
| TE-004 AC3: the TE-002 AC17 database guard (tests/TestCase.php + tests/Support/TestDatabase.php) can't be
| bypassed by Pest's configuration or the arch suite. This file adds no second guard; it checks that every test
| that can reach a database boots through Tests\TestCase, which forces pgsql / traveleasy_test and aborts the run
| otherwise. The guard's own behaviour is proven in tests/Unit/TestDatabaseIsolationTest.php.
*/

/** Namespaces and classes that boot Laravel's database layer or a test-database trait. */
const TE004_DATABASE_REFERENCES = [
    'Illuminate\Foundation\Testing',
    'Illuminate\Database',
    'Illuminate\Support\Facades\DB',
    'Illuminate\Support\Facades\Schema',
    'Illuminate\Support\Facades\Artisan',
];

/** @return list<SourceFile> */
function testSourceFiles(): array
{
    return SourceTree::files(['tests'], exclude: ['tests/Support/', 'tests/TestCase.php', 'tests/Pest.php', 'tests/e2e/']);
}

/**
 * Class references written as code (imports, `::class`, `extends`), not class names inside string literals:
 * a string can't bind a test case, and this file's own rule lists are strings.
 *
 * @return list<array{name: string, line: int, kind: string}>
 */
function codeReferences(SourceFile $file): array
{
    return array_values(array_filter(
        $file->classReferences,
        fn (array $reference): bool => $reference['kind'] !== SourceFile::KIND_STRING,
    ));
}

/** True when the file binds Tests\TestCase in code, e.g. pest()->extend(TestCase::class). */
function bindsTestCase(SourceFile $file): bool
{
    foreach ($file->classReferences as $reference) {
        if ($reference['kind'] === SourceFile::KIND_CODE && strtolower($reference['name']) === 'tests\testcase') {
            return true;
        }
    }

    return false;
}

test('AC3 tests/Pest.php binds Tests\TestCase and the database trait to tests/Feature only', function () {
    $pest = SourceTree::load('tests/Pest.php');
    $folders = [];

    foreach ($pest->methodCalls as $call) {
        if ($call['name'] === 'in') {
            $folders[] = $pest->textBetween($call['index'] + 1, $pest->closingIndex($call['index'] + 1));
        }
    }

    $testCases = array_values(array_unique(array_map(
        fn (array $reference): string => $reference['name'],
        array_filter($pest->classReferences, fn (array $reference): bool => str_ends_with(strtolower($reference['name']), 'testcase')),
    )));

    expect($folders)->toBe(["'Feature'"])
        ->and($testCases)->toBe(['Tests\TestCase'])
        ->and(bindsTestCase($pest))->toBeTrue();
});

test('AC3 every test that can reach a database boots through Tests\TestCase', function () {
    $unguarded = [];

    foreach (testSourceFiles() as $file) {
        if (str_starts_with($file->path, 'tests/Feature/') || bindsTestCase($file)) {
            continue;
        }

        foreach (codeReferences($file) as $reference) {
            foreach (TE004_DATABASE_REFERENCES as $database) {
                if (SourceFile::isWithin($reference['name'], $database)) {
                    $unguarded[] = "{$file->path}:{$reference['line']} uses {$reference['name']} without Tests\\TestCase";
                }
            }
        }
    }

    expect($unguarded)->toBe([], "AC3: the database guard could be bypassed:\n  ".implode("\n  ", $unguarded));
});

test('AC3 no test replaces the guarded application bootstrap', function () {
    $bypasses = [];

    foreach (testSourceFiles() as $file) {
        foreach (codeReferences($file) as $reference) {
            if (SourceFile::isWithin($reference['name'], 'Illuminate\Foundation\Testing\TestCase')
                || SourceFile::isWithin($reference['name'], 'Orchestra\Testbench')) {
                $bypasses[] = "{$file->path}:{$reference['line']} uses {$reference['name']}";
            }
        }

        foreach ($file->functionDeclarations as $declaration) {
            if ($declaration['name'] === 'createapplication') {
                $bypasses[] = "{$file->path}:{$declaration['line']} overrides createApplication()";
            }
        }

        foreach ($file->methodCalls as $call) {
            if (in_array($call['name'], ['forceenvironment', 'forceconfiguration', 'setuptestcase'], true)) {
                $bypasses[] = "{$file->path}:{$call['line']} calls {$call['name']}() outside Tests\\TestCase";
            }
        }
    }

    expect($bypasses)->toBe([], "AC3: the guarded bootstrap is replaced:\n  ".implode("\n  ", $bypasses));
});

// Rework round 1 (security L4): kernels, Foundation\Application or bootstrap/app.php boot an app without the guard.
test('AC3 no test file outside tests/TestCase.php and tests/Support/ can boot the app without the guard', function () {
    $files = SourceTree::files(['tests'], exclude: ['tests/Support/', 'tests/TestCase.php', 'tests/e2e/']);
    $violations = ArchRules::check(ArchRules::guardBypasses(...), $files);

    expect($violations)->toBe([], ArchRules::report('AC3 the TE-002 database guard cannot be bypassed', $violations));
});

test('AC3/AC12 phpunit.xml runs the arch suite by default and keeps the forced test database', function () {
    $xml = simplexml_load_string(fileContents(SourceTree::root().'/phpunit.xml'));
    expect($xml)->not->toBeFalse();
    assert($xml instanceof SimpleXMLElement);

    $directories = array_map('strval', $xml->xpath('//testsuites/testsuite/directory') ?: []);
    $forced = [];

    foreach ($xml->xpath('//php/env[@force="true"]') ?: [] as $env) {
        $forced[(string) $env['name']] = (string) $env['value'];
    }

    expect($directories)->toContain('tests/Unit', 'tests/Feature', 'tests/Arch')
        ->and($xml->xpath('//groups'))->toBe([])
        ->and($forced)->toMatchArray(['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'traveleasy_test']);
});
