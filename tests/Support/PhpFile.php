<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Reads the value a PHP data file returns (e.g. a cached config).
 *
 * TE-004 AC3 (rework round 2, L-c): test files outside tests/TestCase.php and tests/Support/ may not require or
 * include a non-literal path, because that could load bootstrap/app.php and boot an app without the TE-002
 * database guard. Tests that need to read a generated PHP data file use this helper instead.
 */
final class PhpFile
{
    public static function returnValue(string $path): mixed
    {
        return require $path;
    }
}
