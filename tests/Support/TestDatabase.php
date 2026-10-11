<?php

namespace Tests\Support;

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

    /** The guard: the connection must be PostgreSQL and resolve to exactly traveleasy_test on the server. */
    public static function violation(ConnectionInterface $connection): ?string
    {
        $driver = method_exists($connection, 'getDriverName') ? $connection->getDriverName() : 'unknown';

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

        if ($actual !== self::DATABASE) {
            return sprintf('the active pgsql connection resolves to database [%s].', $actual);
        }

        return null;
    }

    public static function abortMessage(string $violation): string
    {
        return 'Refusing to run tests: '.$violation.PHP_EOL
            .'Tests only ever run on connection ['.self::CONNECTION.'] with database ['.self::DATABASE.'], '
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
