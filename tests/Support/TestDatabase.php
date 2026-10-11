<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Throwable;

/**
 * Pins every test run to the PostgreSQL database `traveleasy_test` (TE-002 AC17).
 *
 * `force="true"` in phpunit.xml only writes putenv/$_ENV, while Laravel's env reader checks $_SERVER first
 * (where container env vars live in PHP CLI), DB_URL overrides the database name, and a cached config
 * (`php artisan config:cache`) bypasses env completely. So the target is enforced here, in three layers:
 *
 *  1. forceEnvironment(): before the app boots, overwrite the DB variables in $_SERVER, $_ENV and putenv,
 *     drop DB_URL, and point APP_CONFIG_CACHE at a path that can never exist, so a cached config is ignored.
 *  2. forceConfiguration(): after boot, set database.default and the pgsql connection's database/url again.
 *  3. violation(): the guard. Checks the database the connection actually resolves to on the server.
 *
 * Parallel runs (`php artisan test --parallel`, TE-003 AC14): Laravel switches every worker that runs a test
 * with a database trait to its own database `traveleasy_test_test_{token}`, after the app is created and
 * before the trait (RefreshDatabase) migrates. checkParallelWorker() runs right after that switch and only
 * allows `traveleasy_test` itself or exactly this worker's `traveleasy_test_test_{token}`, again verified on
 * the server. The dev database `traveleasy` never matches either rule.
 *
 * Host, port, user and password still come from env: the test DB lives on the same server as the dev DB.
 */
final class TestDatabase
{
    public const CONNECTION = 'pgsql';

    public const DATABASE = 'traveleasy_test';

    /**
     * Linux cannot have a file below /dev/null, so Laravel never finds a cached config at this path.
     * Tests only run inside the (Linux) app container.
     */
    public const IGNORED_CONFIG_CACHE = '/dev/null/traveleasy-tests-ignore-config-cache.php';

    private static bool $serverChecked = false;

    private static ?string $parallelDatabaseChecked = null;

    public static function forceEnvironment(): void
    {
        self::setEnv('DB_CONNECTION', self::CONNECTION);
        self::setEnv('DB_DATABASE', self::DATABASE);
        self::setEnv('APP_CONFIG_CACHE', self::IGNORED_CONFIG_CACHE);
        self::unsetEnv('DB_URL');
    }

    public static function forceConfiguration(Application $app): void
    {
        $config = $app->make('config');

        $config->set('database.default', self::CONNECTION);
        $config->set('database.connections.'.self::CONNECTION.'.driver', 'pgsql');
        $config->set('database.connections.'.self::CONNECTION.'.database', self::DATABASE);
        $config->set('database.connections.'.self::CONNECTION.'.url', null);

        // Drop any connection that was opened while booting with the old settings.
        $app->make('db')->purge(self::CONNECTION);
    }

    /**
     * Returns null when the app may run tests, otherwise the reason it must not.
     * The server-side check (current_database()) runs once per process; the config is forced identically
     * for every test, so later apps resolve to the same database.
     */
    public static function checkApplication(Application $app): ?string
    {
        if ($app->getCachedConfigPath() !== self::IGNORED_CONFIG_CACHE) {
            return sprintf('the config cache path is [%s], so a cached config may have been loaded. '
                .'Run `php artisan config:clear`.', $app->getCachedConfigPath());
        }

        $config = $app->make('config');
        $default = $config->get('database.default');
        $database = $config->get('database.connections.'.self::CONNECTION.'.database');

        if ($default !== self::CONNECTION || $database !== self::DATABASE) {
            return sprintf('the configured connection is [%s] with database [%s].', self::str($default), self::str($database));
        }

        if (self::$serverChecked) {
            return null;
        }

        $violation = self::violation($app->make('db')->connection());
        self::$serverChecked = $violation === null;

        return $violation;
    }

    /**
     * The per-worker database of a parallel run, as Laravel names it ({database}_test_{token}), or null when the
     * token is not a positive integer.
     */
    public static function parallelDatabase(mixed $token): ?string
    {
        $token = is_int($token) ? (string) $token : $token;

        if (! is_string($token) || preg_match('/^[1-9][0-9]*$/', $token) !== 1) {
            return null;
        }

        return self::DATABASE.'_test_'.$token;
    }

    /**
     * The configuration rule for a parallel worker: the default connection is pgsql, and its database is either
     * traveleasy_test (a test without database traits, not switched) or exactly this worker's database.
     */
    public static function parallelConfigViolation(mixed $default, mixed $database, mixed $token): ?string
    {
        $expected = self::parallelDatabase($token);

        if ($expected === null) {
            return sprintf('the parallel worker token [%s] is not a positive integer.', self::str($token));
        }

        if ($default !== self::CONNECTION || ($database !== self::DATABASE && $database !== $expected)) {
            return sprintf('the parallel worker [%s] is configured for connection [%s] with database [%s]; '
                .'only [%s] or [%s] are allowed.', self::str($token), self::str($default), self::str($database),
                self::DATABASE, $expected);
        }

        return null;
    }

    /**
     * Runs after Laravel's parallel-testing switch (a ParallelTesting setUpTestCase callback registered after
     * the framework's own) and before any test trait, so before RefreshDatabase migrates anything.
     * The server-side check runs once per worker database.
     */
    public static function checkParallelWorker(Application $app, mixed $token): ?string
    {
        $config = $app->make('config');
        $database = $config->get('database.connections.'.self::CONNECTION.'.database');

        $violation = self::parallelConfigViolation($config->get('database.default'), $database, $token);

        if ($violation !== null || $database === self::DATABASE || ! is_string($database)) {
            // traveleasy_test itself was already verified on the server by checkApplication().
            return $violation;
        }

        if (self::$parallelDatabaseChecked === $database) {
            return null;
        }

        $violation = self::violation($app->make('db')->connection(), $database);
        self::$parallelDatabaseChecked = $violation === null ? $database : null;

        return $violation;
    }

    /**
     * The guard: the connection must be PostgreSQL and resolve to exactly the expected database on the server
     * (traveleasy_test, or a worker's traveleasy_test_test_{token} in a parallel run).
     */
    public static function violation(ConnectionInterface $connection, string $expected = self::DATABASE): ?string
    {
        if ($expected !== self::DATABASE && preg_match('/^'.self::DATABASE.'_test_[1-9][0-9]*$/', $expected) !== 1) {
            return sprintf('[%s] is not a test database name.', $expected);
        }

        $driver = $connection instanceof Connection ? $connection->getDriverName() : 'unknown';

        if ($driver !== 'pgsql') {
            return sprintf('the active connection uses driver [%s], not [pgsql].', $driver);
        }

        try {
            $row = $connection->selectOne('select current_database() as db');
            $actual = is_object($row) && property_exists($row, 'db') ? self::str($row->db) : 'unknown';
        } catch (Throwable $e) {
            // Class name only: the message of a connection error can contain host and user names.
            return sprintf('could not verify the active database (%s).', $e::class);
        }

        if ($actual !== $expected) {
            return sprintf('the active pgsql connection resolves to database [%s].', $actual);
        }

        return null;
    }

    public static function abortMessage(string $violation): string
    {
        return 'Refusing to run tests: '.$violation.PHP_EOL
            .'Tests only ever run on connection ['.self::CONNECTION.'] with database ['.self::DATABASE.'], '
            .'(or, in a parallel run, the worker\'s ['.self::DATABASE.'_test_{token}]), '
            .'so RefreshDatabase can never wipe another database (TE-002 AC17).'.PHP_EOL;
    }

    private static function setEnv(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }

    private static function unsetEnv(string $name): void
    {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
