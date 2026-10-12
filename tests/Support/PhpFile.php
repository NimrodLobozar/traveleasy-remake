<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

/**
 * Reads the value a PHP data file returns (a config file, or a cached config a test generated).
 *
 * TE-004 AC3: test files outside tests/TestCase.php and tests/Support/ may not require or include a non-literal
 * path, because that could load bootstrap/app.php and boot an app without the TE-002 database guard. Tests that
 * need to read a PHP data file use this helper instead, and it only loads files below two allowed directories:
 *  - config/                     (the app's config files, e.g. ConfigDefaultsTest);
 *  - storage/framework/testing/  (files a test generated there, e.g. the cached config of the isolation test).
 * Anything else, including bootstrap/app.php, symlinks or `..` paths that resolve elsewhere, and missing files,
 * is refused before anything is loaded.
 */
final class PhpFile
{
    public const ALLOWED_DIRECTORIES = ['config', 'storage/framework/testing'];

    public static function returnValue(string $path): mixed
    {
        return require self::allowedPath($path);
    }

    /** The resolved path, or an exception when it is not a file below one of the allowed directories. */
    public static function allowedPath(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! str_ends_with($resolved, '.php')) {
            throw new InvalidArgumentException("PhpFile refuses [{$path}]: not an existing .php file.");
        }

        foreach (self::ALLOWED_DIRECTORIES as $directory) {
            $allowed = realpath(SourceTree::root().'/'.$directory);

            if ($allowed !== false && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) {
                return $resolved;
            }
        }

        throw new InvalidArgumentException("PhpFile refuses [{$path}]: only files below "
            .implode(' and ', self::ALLOWED_DIRECTORIES).' may be loaded (TE-004 AC3; bootstrap/app.php would boot an app without the database guard).');
    }
}
