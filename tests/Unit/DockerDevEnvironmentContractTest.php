<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/*
| TE-002 Docker dev environment contract, added by QA.
| Lives in tests/Unit on purpose: it boots Laravel (Tests\TestCase) but does NOT use RefreshDatabase,
| so the nested test runs below never race a migrate:fresh against an open test transaction.
*/

pest()->extend(TestCase::class);

/** @return array<array-key, mixed> */
function composeFile(string $name): array
{
    return asArray(Yaml::parseFile(base_path($name)));
}

/** @return array<string, string> */
function envExample(): array
{
    $vars = [];
    foreach (fileLines(base_path('.env.example')) as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m) === 1) {
            $vars[$m[1]] = $m[2];
        }
    }

    return $vars;
}

// --- AC17 + DB isolation: tests must only ever touch traveleasy_test on PostgreSQL ---------------

test('the test run uses the traveleasy_test database on PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::scalar('select current_database()'))->toBe('traveleasy_test');
});

test('a conflicting container environment variable cannot redirect the test database', function (array $conflict) {
    $conflict = asStringMap($conflict);

    if (getenv('QA_NESTED_ISOLATION_PROBE') === '1') {
        $this->markTestSkipped('Nested probe run.');
    }

    // Conflicting values are fake and point at nothing real: if they leak through, the nested
    // probe fails (wrong driver / unknown database) instead of touching the dev database.
    $process = new Process(
        ['php', 'vendor/bin/pest', __FILE__, '--filter=the test run uses the traveleasy_test database'],
        base_path(),
        $conflict + ['QA_NESTED_ISOLATION_PROBE' => '1'],
        null,
        120,
    );
    $process->run();

    if (isset($conflict['DB_DATABASE']) && str_starts_with($conflict['DB_DATABASE'], sys_get_temp_dir())) {
        @unlink($conflict['DB_DATABASE']);
    }

    expect($process->isSuccessful())->toBeTrue(
        'Nested test run with container env '.json_encode($conflict)." did not stay on traveleasy_test:\n"
        .substr($process->getOutput().$process->getErrorOutput(), -1500),
    );
})->with([
    'DB_CONNECTION=sqlite' => [['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => sys_get_temp_dir().'/qa-te002-conflict.sqlite']],
    'DB_DATABASE=other' => [['DB_DATABASE' => 'qa_conflict_nonexistent_db']],
    'DB_URL=other' => [['DB_URL' => 'pgsql://qa_fake_user:qa-fake-password@db:5432/qa_conflict_nonexistent_db']],
]);

// --- AC1, AC5, AC12, AC23, AC25: .env.example -------------------------------------------------

test('.env.example loads both compose files with an explicit path separator and no default profile', function () {
    $env = envExample();

    expect($env)->toHaveKeys(['COMPOSE_FILE', 'COMPOSE_PATH_SEPARATOR', 'APP_PORT', 'VITE_PORT', 'ADMINER_PORT',
        'VITE_USE_POLLING', 'VITE_POLLING_INTERVAL'])
        ->and($env['COMPOSE_PATH_SEPARATOR'] === '' ? [] : explode($env['COMPOSE_PATH_SEPARATOR'], $env['COMPOSE_FILE']))
        ->toBe(['compose.yaml', 'compose.dev.yaml'])
        ->and($env)->not->toHaveKey('COMPOSE_PROFILES')
        ->and($env['APP_PORT'])->toBe('8000')
        ->and($env['VITE_PORT'])->toBe('5173')
        ->and($env['ADMINER_PORT'])->toBe('8081');
});

test('every variable interpolated in the compose files is documented in .env.example', function () {
    $used = [];
    foreach (['compose.yaml', 'compose.dev.yaml'] as $file) {
        preg_match_all('/(?<!\$)\$\{([A-Z0-9_]+)/', fileContents(base_path($file)), $m);
        $used = [...$used, ...$m[1]];
    }

    expect(array_values(array_diff(array_unique($used), array_keys(envExample()))))->toBe([]);
});

// --- AC3, AC9, AC10, AC15: compose.yaml stands alone -------------------------------------------

test('compose.yaml only has the db service, without ports, bind mounts or a default password', function () {
    $base = composeFile('compose.yaml');
    $services = Arr::array($base, 'services');
    $db = Arr::array($services, 'db');

    expect(array_keys($services))->toBe(['db'])
        ->and($db)->not->toHaveKey('ports')
        ->and($db['image'])->toMatch('/^postgres:\d+\.\d+/')
        ->and(Arr::get($db, 'environment.POSTGRES_PASSWORD'))->toStartWith('${DB_PASSWORD:?')
        ->and($db['volumes'])->each->toStartWith('pgdata:')
        ->and($base['volumes'])->toHaveKey('pgdata');
});

// --- AC10, AC12, AC14, AC23-AC25, AC27: compose.dev.yaml ---------------------------------------

test('compose.dev.yaml publishes ports on 127.0.0.1 only and never on db', function () {
    $services = Arr::array(composeFile('compose.dev.yaml'), 'services');

    expect($services['db'] ?? [])->not->toHaveKey('ports');

    foreach ($services as $name => $service) {
        foreach (asArray(asArray($service)['ports'] ?? []) as $port) {
            $port = asString($port);

            expect($port)->toStartWith('127.0.0.1:', "Service {$name} publishes {$port}");
        }
    }
});

test('compose.dev.yaml has exactly the dev services, Adminer and e2e only behind their profiles', function () {
    $services = Arr::array(composeFile('compose.dev.yaml'), 'services');

    // e2e (TE-005): Playwright browser tests, one-shot via `docker compose run --rm e2e`.
    expect(array_keys($services))->toEqualCanonicalizing(['db', 'app', 'vite', 'adminer', 'e2e'])
        ->and(Arr::get($services, 'adminer.profiles'))->toBe(['tools'])
        // Profile-gated, so plain `docker compose up -d` never starts it; one-shot, so no restart policy.
        ->and(Arr::get($services, 'e2e.profiles'))->toBe(['e2e'])
        ->and($services['e2e'])->not->toHaveKey('restart')
        ->and($services['app'])->not->toHaveKey('profiles')
        ->and($services['vite'])->not->toHaveKey('profiles');
});

test('the e2e service sees no .env secrets, no database and publishes nothing', function () {
    $e2e = Arr::array(composeFile('compose.dev.yaml'), 'services.e2e');
    $masks = array_filter(
        Arr::array($e2e, 'volumes'),
        fn (mixed $volume): bool => is_array($volume) && ($volume['target'] ?? null) === '/var/www/html/.env',
    );

    expect($e2e)->not->toHaveKeys(['env_file', 'ports', 'networks', 'build'])
        ->and($e2e['network_mode'] ?? null)->toBe('service:vite')
        ->and(array_values($masks))->toBe([['type' => 'bind', 'source' => '/dev/null', 'target' => '/var/www/html/.env', 'read_only' => true]])
        ->and(array_keys(Arr::array($e2e, 'environment')))->toEqualCanonicalizing(['E2E_BASE_URL', 'E2E_FORWARD', 'NPM_CONFIG_UPDATE_NOTIFIER', 'HOME'])
        ->and($e2e['user'] ?? null)->toBe('1000:1000');
});

test('the adminer service stores no credentials, mounts nothing and is pinned', function () {
    $adminer = Arr::array(composeFile('compose.dev.yaml'), 'services.adminer');
    $environment = Arr::array($adminer, 'environment');

    expect($adminer['image'])->toMatch('/^adminer:\d+\.\d+/')
        ->and($adminer)->not->toHaveKey('volumes')
        ->and(array_keys($environment))->each->toBeIn(['ADMINER_DEFAULT_SERVER', 'ADMINER_PLUGINS', 'ADMINER_DESIGN'])
        ->and($environment['ADMINER_DEFAULT_SERVER'] ?? null)->toBe('db')
        ->and(strtolower(Arr::string($environment, 'ADMINER_PLUGINS', '')))->not->toContain('login-password-less');
});

// --- AC16, AC20, AC21, security QC: image and repo hygiene -------------------------------------

test('nothing seeds the database automatically', function () {
    foreach (['Dockerfile', 'compose.yaml', 'compose.dev.yaml', 'docker/dev/entrypoint.sh'] as $file) {
        expect(fileContents(base_path($file)))->not->toMatch('/db:seed|--seed/');
    }
});

test('the Dockerfile installs from lockfiles, runs as non-root and pins its base images', function () {
    $dockerfile = fileContents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('npm ci')
        ->and($dockerfile)->not->toMatch('/npm (install|i)\b/')
        ->and($dockerfile)->toContain('composer install')
        ->and($dockerfile)->toMatch('/^USER app$/m')
        ->and($dockerfile)->not->toMatch('/:latest\b/')
        ->and($dockerfile)->toMatch('/^ARG PHP_VERSION=8\.5\.\d+$/m')
        ->and($dockerfile)->toMatch('/^ARG NODE_VERSION=24\.\d+\.\d+$/m');
});

test('.dockerignore keeps secrets and dependency folders out of images', function () {
    $lines = fileLines(base_path('.dockerignore'));

    expect($lines)->toContain('.env', 'vendor', 'node_modules', '.git', 'storage/logs');
});

test('.gitattributes forces LF for shell scripts', function () {
    expect(fileContents(base_path('.gitattributes')))->toMatch('/^\*\.sh\s+text\s+eol=lf$/m');
});

// --- AC4, AC5: Vite dev server config ---------------------------------------------------------

test('vite.config.ts polls from env, ignores heavy folders and sets no server.origin', function () {
    $config = fileContents(base_path('vite.config.ts'));

    expect($config)->toContain("usePolling: process.env.VITE_USE_POLLING === 'true'")
        ->and($config)->toContain('process.env.VITE_POLLING_INTERVAL')
        ->and($config)->not->toMatch('/\borigin\s*:/');

    foreach (['**/vendor/**', '**/node_modules/**', '**/storage/**', '**/public/build/**', '**/.git/**'] as $glob) {
        expect($config)->toContain("'{$glob}'");
    }
});
