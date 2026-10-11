<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/*
| TE-002 AC17: every test run is pinned to pgsql / traveleasy_test, and a guard aborts the run otherwise.
| Lives in tests/Unit on purpose: it boots Laravel but does NOT use RefreshDatabase, so the nested runs
| below never race a migrate:fresh against an open test transaction.
|
| Each nested case proves the dev database `traveleasy` is untouched: the table set, every table's OID
| (a migrate:fresh / db:wipe recreates tables with new OIDs) and the row counts are identical before and
| after. Row counts of runtime tables the running dev server writes to (sessions, cache) are left out of the
| count comparison, because a browser tab can change them at any time; their OIDs are still compared.
*/

pest()->extend(TestCase::class);

const TE002_NESTED_FLAG = 'TE002_NESTED_ISOLATION_PROBE';
const TE002_DEV_DATABASE = 'traveleasy';
const TE002_VOLATILE_TABLES = ['sessions', 'cache', 'cache_locks'];

/** Read-only snapshot of the dev database: table => [oid, row count], or 'absent'. */
function devDatabaseSnapshot(): array|string
{
    $config = config('database.connections.pgsql');
    $config['database'] = TE002_DEV_DATABASE;
    $config['url'] = null;
    $config['name'] = 'te002_dev_snapshot';

    try {
        $db = DB::build($config);
        $db->statement('set default_transaction_read_only = on');

        $snapshot = [];
        $tables = $db->select("select c.oid, c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind = 'r' order by c.relname");

        foreach ($tables as $table) {
            $quoted = '"'.str_replace('"', '""', $table->relname).'"';
            $snapshot[$table->relname] = [
                'oid' => (int) $table->oid,
                'rows' => in_array($table->relname, TE002_VOLATILE_TABLES, true)
                    ? 'volatile'
                    : (int) $db->selectOne("select count(*) as n from public.{$quoted}")->n,
            ];
        }

        return $snapshot;
    } catch (Throwable $e) {
        // The dev DB does not exist (e.g. in CI): record that, it must still not exist afterwards.
        if (str_contains($e->getMessage(), 'database "'.TE002_DEV_DATABASE.'" does not exist')) {
            return 'absent';
        }

        throw $e;
    } finally {
        DB::purge('te002_dev_snapshot');
    }
}

/** @return list<string> */
function sqliteFiles(): array
{
    $files = [
        ...glob(base_path('*.sqlite*')) ?: [],
        ...glob(base_path('database/*.sqlite*')) ?: [],
        ...glob(sys_get_temp_dir().'/*.sqlite*') ?: [],
    ];
    sort($files);

    return $files;
}

/**
 * Runs the probe test below in a separate Pest process with the given environment.
 *
 * @param  array<string, string|false>  $env  false removes an inherited variable
 */
function runNestedProbe(array $env): Process
{
    $process = new Process(
        ['php', 'vendor/bin/pest', __FILE__, '--filter=isolation probe'],
        base_path(),
        $env + [TE002_NESTED_FLAG => '1'],
        null,
        120,
    );
    $process->run();

    return $process;
}

function skipInsideNestedRun(): void
{
    if (getenv(TE002_NESTED_FLAG) === '1') {
        test()->markTestSkipped('Nested probe run.');
    }
}

test('isolation probe: the active test connection is pgsql on traveleasy_test', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('select current_database() as db')->db)->toBe('traveleasy_test')
        ->and(config('database.connections.pgsql.url'))->toBeNull();
});

test('a conflicting environment never redirects the run and never touches the dev database', function (array $conflict) {
    skipInsideNestedRun();

    $before = devDatabaseSnapshot();
    $sqliteBefore = sqliteFiles();

    $process = runNestedProbe($conflict);

    expect($process->isSuccessful())->toBeTrue(
        'Nested run with env '.json_encode($conflict)." did not stay on traveleasy_test:\n"
        .substr($process->getOutput().$process->getErrorOutput(), -1500),
    )
        ->and(devDatabaseSnapshot())->toBe($before)
        ->and(sqliteFiles())->toBe($sqliteBefore);
})->with([
    'DB_CONNECTION=sqlite' => [['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => sys_get_temp_dir().'/te002-conflict.sqlite']],
    'DB_DATABASE=other' => [['DB_DATABASE' => 'te002_conflict_nonexistent_db']],
    'DB_DATABASE=dev' => [['DB_DATABASE' => TE002_DEV_DATABASE]],
    'DB_URL=other' => [['DB_URL' => 'pgsql://te002_fake_user:te002-fake-password@db:5432/te002_conflict_nonexistent_db']],
    'DB_URL=dev' => [['DB_URL' => 'pgsql://te002_fake_user:te002-fake-password@db:5432/'.TE002_DEV_DATABASE]],
]);

test('a cached dev config (DB_DATABASE=traveleasy) is ignored and never touches the dev database', function () {
    skipInsideNestedRun();

    $cachePath = base_path('bootstrap/cache/config.php');
    $existing = is_file($cachePath) ? file_get_contents($cachePath) : null;

    $before = devDatabaseSnapshot();
    $sqliteBefore = sqliteFiles();

    // The dev environment, not the phpunit.xml one: drop what this test process put into env.
    $devEnv = array_fill_keys([
        'APP_CONFIG_CACHE', 'APP_ENV', 'DB_URL', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION',
        'CACHE_STORE', 'QUEUE_CONNECTION', 'SESSION_DRIVER', 'PULSE_ENABLED', 'TELESCOPE_ENABLED', 'NIGHTWATCH_ENABLED',
    ], false);

    try {
        $cache = new Process(['php', 'artisan', 'config:cache'], base_path(),
            ['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => TE002_DEV_DATABASE] + $devEnv, null, 60);
        $cache->run();

        expect($cache->isSuccessful())->toBeTrue($cache->getErrorOutput().$cache->getOutput());

        // The scenario is real: the cache points the default connection at the dev database.
        $cached = require $cachePath;
        expect($cached['database']['default'])->toBe('pgsql')
            ->and($cached['database']['connections']['pgsql']['database'])->toBe(TE002_DEV_DATABASE);

        // The nested run inherits no DB overrides, so only the cached config could redirect it.
        $process = runNestedProbe(['DB_CONNECTION' => false, 'DB_DATABASE' => false, 'DB_URL' => false, 'APP_CONFIG_CACHE' => false]);

        expect($process->isSuccessful())->toBeTrue(
            "Nested run with a cached dev config did not stay on traveleasy_test:\n"
            .substr($process->getOutput().$process->getErrorOutput(), -1500),
        );
    } finally {
        $existing === null ? @unlink($cachePath) : file_put_contents($cachePath, $existing);
    }

    expect(is_file($cachePath))->toBe($existing !== null)
        ->and(devDatabaseSnapshot())->toBe($before)
        ->and(sqliteFiles())->toBe($sqliteBefore);
});

test('the guard rejects a connection that is not PostgreSQL', function () {
    $sqlite = DB::build(['driver' => 'sqlite', 'database' => ':memory:', 'name' => 'te002_guard_sqlite']);

    expect(TestDatabase::violation($sqlite))->toContain('driver [sqlite]');

    DB::purge('te002_guard_sqlite');
});

test('the guard rejects a PostgreSQL connection that resolves to another database', function () {
    $config = ['database' => 'postgres', 'url' => null, 'name' => 'te002_guard_other'] + config('database.connections.pgsql');
    $other = DB::build($config);
    $other->statement('set default_transaction_read_only = on');

    expect(TestDatabase::violation($other))->toContain('database [postgres]');

    DB::purge('te002_guard_other');
});

test('the guard accepts the active test connection', function () {
    expect(TestDatabase::violation(DB::connection()))->toBeNull()
        ->and(TestDatabase::abortMessage('x'))->toContain('Refusing to run tests', 'traveleasy_test');
});
