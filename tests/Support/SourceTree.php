<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Loads the project's PHP and Blade sources for the file-scan rules (TE-004). Blade views are compiled to PHP
 * first, so `{{ env('X') }}`, `@php ... @endphp` and `@dd(...)` are scanned as the PHP they become. Bound
 * attributes of component tags (`<x-foo :bar="...">`) are not compiled (that needs a booted app) and not scanned.
 */
final class SourceTree
{
    /** Never scanned: generated framework caches. */
    private const ALWAYS_EXCLUDED = ['bootstrap/cache/'];

    /** @var array<string, SourceFile> */
    private static array $cache = [];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * All .php files (including .blade.php) below the given directories or files, relative to the project root.
     *
     * @param  list<string>  $paths  e.g. ['app', 'config', 'bootstrap/app.php']
     * @param  list<string>  $exclude  relative paths or path prefixes to skip
     * @return list<SourceFile>
     */
    public static function files(array $paths, array $exclude = []): array
    {
        $root = self::root();
        $exclude = [...self::ALWAYS_EXCLUDED, ...$exclude];
        $files = [];

        foreach ($paths as $path) {
            $absolute = $root.'/'.$path;

            if (is_file($absolute)) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                }
            }
        }

        $files = array_values(array_unique(array_filter(
            $files,
            static function (string $file) use ($exclude): bool {
                foreach ($exclude as $prefix) {
                    if ($file === $prefix || str_starts_with($file, $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        )));
        sort($files);

        return array_map(self::load(...), $files);
    }

    public static function load(string $relative): SourceFile
    {
        $absolute = self::root().'/'.$relative;
        $code = file_get_contents($absolute);

        if ($code === false) {
            throw new RuntimeException("Could not read [{$relative}].");
        }

        $key = $relative.':'.hash('xxh128', $code);

        return self::$cache[$key] ??= new SourceFile($relative, self::isBlade($relative) ? self::compileBlade($relative, $code) : $code);
    }

    private static function isBlade(string $relative): bool
    {
        return str_ends_with($relative, '.blade.php');
    }

    private static function compileBlade(string $relative, string $code): string
    {
        try {
            // Component tags (<x-...>) need a booted view factory to compile; they stay as plain markup here.
            $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
            $compiler->withoutComponentTags();

            return $compiler->compileString($code);
        } catch (Throwable $e) {
            throw new RuntimeException("Could not compile the Blade view [{$relative}] for scanning: {$e->getMessage()}", 0, $e);
        }
    }
}
