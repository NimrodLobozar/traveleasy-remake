<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

/*
| TE-024: the production compose file (compose.yaml) as text. Plain unit test: reads files only, no Laravel app,
| no database, no Docker. The rendered model is checked by scripts/ci/compose-guard.mjs (CI job `compose`), and the
| real stack is exercised by hand/CI with throwaway secrets (docs/deploy/casaos-portainer.md).
*/

/** @return array<array-key, mixed> */
function productionCompose(): array
{
    return asArray(Yaml::parseFile(dirname(__DIR__, 2).'/compose.yaml'));
}

/** @return array<string, array<array-key, mixed>> */
function productionServices(): array
{
    $services = [];
    foreach (Arr::array(productionCompose(), 'services') as $name => $service) {
        $services[(string) $name] = asArray($service);
    }

    return $services;
}

/** @return list<string> names used as ${NAME...} in the file, outside comment lines */
function productionComposeVariables(): array
{
    $code = implode("\n", array_filter(
        fileLines(dirname(__DIR__, 2).'/compose.yaml'),
        static fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    ));
    preg_match_all('/(?<!\$)\$\{([A-Z0-9_]+)/', $code, $matches);

    return array_values(array_unique($matches[1]));
}

const PRODUCTION_SECRETS = ['APP_KEY', 'DB_SUPERUSER_PASSWORD', 'DB_PASSWORD', 'SUPER_ADMIN_PASSWORD_HASH', 'SUPER_ADMIN_TOTP_SECRET'];

test('compose.yaml has exactly db-init, db, ssr and app in M0', function () {
    expect(array_keys(productionServices()))->toEqualCanonicalizing(['db-init', 'db', 'ssr', 'app']);
});

test('no service publishes a port, mounts a host path or builds an image', function () {
    foreach (productionServices() as $name => $service) {
        expect($service)->not->toHaveKey('ports', "{$name} publishes a port")
            ->and($service)->not->toHaveKey('build', "{$name} has a build section")
            ->and($service)->not->toHaveKey('expose', "{$name} exposes ports");
        foreach (asArray($service['volumes'] ?? []) as $volume) {
            $volume = asString($volume);
            expect($volume)->toMatch('/^(pgdata|storage):/', "{$name} mounts {$volume}");
        }
    }
});

test('app and ssr share one pinned image reference; db and db-init share one digest-pinned postgres image', function () {
    $services = productionServices();
    $appImage = asString($services['app']['image'] ?? null);

    expect($appImage)->toStartWith('ghcr.io/nimrodlobozar/traveleasy:${APP_IMAGE_TAG:?')
        ->and($services['ssr']['image'] ?? null)->toBe($appImage)
        ->and(substr($appImage, 0, (int) strpos($appImage, '$')))->not->toContain('latest');

    $content = fileContents(dirname(__DIR__, 2).'/compose.yaml');
    preg_match('/^x-postgres-image: &postgres-image (\S+)$/m', $content, $match);

    expect($match[1] ?? '')->toMatch('/^postgres:\d+\.\d+(-alpine)?@sha256:[0-9a-f]{64}$/')
        ->and($services['db']['image'] ?? null)->toBe($match[1] ?? '')
        ->and($services['db-init']['image'] ?? null)->toBe($match[1] ?? '');
});

test('every service is hardened: cap_drop ALL, no-new-privileges, read-only root, limits and a capped log', function () {
    foreach (productionServices() as $name => $service) {
        expect($service['cap_drop'] ?? null)->toBe(['ALL'], "{$name} cap_drop")
            ->and($service['security_opt'] ?? null)->toBe(['no-new-privileges:true'], "{$name} security_opt")
            ->and($service['read_only'] ?? null)->toBeTrue("{$name} read_only")
            ->and($service)->toHaveKeys(['mem_limit', 'memswap_limit', 'cpus', 'pids_limit', 'logging'], "{$name} limits")
            ->and($service['mem_limit'] ?? null)->toBe($service['memswap_limit'] ?? 'x', "{$name} must not swap");
    }
});

test('the default network is internal and only app joins the external npm_shared network, aliased traveleasy-app', function () {
    $compose = productionCompose();
    $services = productionServices();

    expect(Arr::get($compose, 'networks.default.internal'))->toBeTrue()
        ->and(Arr::get($compose, 'networks.npm_network.name'))->toBe('npm_shared')
        ->and(Arr::get($compose, 'networks.npm_network.external'))->toBeTrue()
        ->and(array_keys(Arr::array($compose, 'networks')))->toBe(['default', 'npm_network'])
        ->and(Arr::get($services['app'], 'networks.npm_network.aliases'))->toBe(['traveleasy-app']);

    foreach ($services as $name => $service) {
        if ($name !== 'app') {
            expect(asArray($service['networks'] ?? []))->not->toHaveKey('npm_network', "{$name} joins npm_shared")
                ->and(asArray($service['networks'] ?? []))->not->toContain('npm_network', "{$name} joins npm_shared");
        }
    }

    // The file ends with the networks block at the same level as services (AC11c).
    expect(fileContents(dirname(__DIR__, 2).'/compose.yaml'))->toMatch(
        '/\nnetworks:\n(?:    #[^\n]*\n)*    default:\n        internal: true\n(?:    #[^\n]*\n)*    npm_network:\n        name: npm_shared\n        external: true\n$/',
    );
});

test('ssr has no env_file, no secrets, runs the ssr role and holds no way to the outside', function () {
    $ssr = productionServices()['ssr'];
    $environment = asArray($ssr['environment'] ?? []);

    expect($ssr)->not->toHaveKey('env_file')
        ->and($ssr['command'] ?? null)->toBe(['ssr'])
        ->and(array_intersect(array_keys($environment), PRODUCTION_SECRETS))->toBe([])
        ->and($environment['INERTIA_SSR_HOST'] ?? null)->toBe('0.0.0.0')
        ->and(Arr::get($ssr, 'depends_on'))->toBeNull();

    foreach (['db', 'db-init', 'app'] as $name) {
        expect(asArray(productionServices()[$name]['environment'] ?? []))->not->toHaveKey('INERTIA_SSR_HOST', "{$name}");
    }

    expect(asString(asArray(productionServices()['app']['environment'] ?? [])['INERTIA_SSR_URL'] ?? ''))
        ->toContain('http://ssr:13714');
});

test('secrets use ${VAR:?message} with no default, and the superuser password reaches only db and db-init', function () {
    $content = fileContents(dirname(__DIR__, 2).'/compose.yaml');

    foreach (['APP_KEY', 'DB_SUPERUSER_PASSWORD', 'DB_PASSWORD', 'TRUSTED_PROXIES', 'APP_IMAGE_TAG'] as $variable) {
        expect($content)->toContain('${'.$variable.':?');
        expect($content)->not->toMatch('/\$\{'.$variable.':?-/');
    }

    foreach (productionServices() as $name => $service) {
        $mentions = str_contains(json_encode($service['environment'] ?? [], JSON_THROW_ON_ERROR), 'DB_SUPERUSER_PASSWORD');
        expect($mentions)->toBe(in_array($name, ['db', 'db-init'], true), "{$name} and DB_SUPERUSER_PASSWORD");
    }
});

test('app waits for db, db-init and ssr, and (later) worker/scheduler wait for a healthy app', function () {
    $dependsOn = asArray(productionServices()['app']['depends_on'] ?? []);

    expect(Arr::get($dependsOn, 'db.condition'))->toBe('service_healthy')
        ->and(Arr::get($dependsOn, 'db-init.condition'))->toBe('service_completed_successfully')
        ->and(Arr::get($dependsOn, 'ssr.condition'))->toBe('service_started');

    foreach (['worker', 'scheduler'] as $name) {
        if (isset(productionServices()[$name])) {
            expect(Arr::get(productionServices()[$name], 'depends_on.app.condition'))->toBe('service_healthy', $name);
        }
    }
});

test('db-init never echoes a statement and keeps failing statements out of the server log', function () {
    $initService = productionServices()['db-init'];
    $script = implode("\n", array_map(asString(...), asArray($initService['command'] ?? [])));

    expect(Arr::get($initService, 'environment.PGOPTIONS'))->toBe('-c log_min_error_statement=panic')
        ->and($script)->not->toMatch('/psql[^\n]*\s-[a-zA-Z]*[ea]\b/')
        ->and($script)->not->toContain('ECHO')
        ->and($script)->toContain('\set VERBOSITY terse')
        ->and($script)->toContain('psql -X -q -v ON_ERROR_STOP=1')
        ->and($script)->toContain("format('ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L'")
        ->and($script)->not->toMatch('/\bset -[a-z]*x/');
});

test('the restart policy, health checks and image-pinning rules hold for the long-running services', function () {
    foreach (['db', 'ssr', 'app'] as $name) {
        expect(productionServices()[$name]['restart'] ?? null)->toBe('unless-stopped', $name)
            ->and(productionServices()[$name])->toHaveKey('healthcheck');
    }
    expect(productionServices()['db-init']['restart'] ?? null)->toBe('no');
});

test('resource limits default to the ADR-0002 D8 values', function () {
    $content = fileContents(dirname(__DIR__, 2).'/compose.yaml');

    foreach ([
        'APP_MEM_LIMIT:-768m', 'APP_CPUS:-2.0', 'SSR_MEM_LIMIT:-256m', 'SSR_CPUS:-1.0',
        'POSTGRES_MEM_LIMIT:-1g', 'POSTGRES_CPUS:-2.0', 'DBINIT_MEM_LIMIT:-128m', 'DBINIT_CPUS:-0.5',
    ] as $default) {
        expect($content)->toContain('${'.$default.'}');
    }
});

test('every variable of compose.yaml appears in deploy/portainer.env.example', function () {
    $documented = [];
    foreach (fileLines(dirname(__DIR__, 2).'/deploy/portainer.env.example') as $line) {
        if (preg_match('/^([A-Z0-9_]+)=/', $line, $match) === 1) {
            $documented[] = $match[1];
        }
    }

    expect(array_values(array_diff(productionComposeVariables(), $documented)))->toBe([]);
});

test('the Portainer env file holds no secret value and never uses latest', function () {
    $lines = fileLines(dirname(__DIR__, 2).'/deploy/portainer.env.example');
    $values = [];
    foreach ($lines as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
            $values[$match[1]] = $match[2];
        }
    }

    foreach (PRODUCTION_SECRETS as $secret) {
        if (array_key_exists($secret, $values)) {
            expect($values[$secret])->toBe('', "{$secret} must be empty in the example file");
        }
    }

    expect($values['APP_IMAGE_TAG'] ?? '')->toMatch('/^v\d+\.\d+\.\d+$/')
        ->and($values)->not->toHaveKey('INERTIA_DEVTOOLS_ENABLED');
});

/** @return array<string, string> KEY => value of the Portainer env file */
function portainerEnvValues(): array
{
    $values = [];
    foreach (fileLines(dirname(__DIR__, 2).'/deploy/portainer.env.example') as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
            $values[$match[1]] = $match[2];
        }
    }

    return $values;
}

test('every variable of the Portainer env file is used by compose.yaml (TE-025 AC2)', function () {
    expect(array_values(array_diff(array_keys(portainerEnvValues()), productionComposeVariables())))->toBe([]);
});

test('the Portainer env file is plain KEY=value with a comment per variable (TE-025 AC1, AC7)', function () {
    $lines = fileLines(dirname(__DIR__, 2).'/deploy/portainer.env.example');
    $previous = '';
    foreach ($lines as $line) {
        expect($line)->not->toMatch('/\s$/', "trailing space: {$line}");
        if ($line !== '' && ! str_starts_with($line, '#')) {
            expect($line)->toMatch('#^[A-Z][A-Z0-9_]*=[A-Za-z0-9_.:/@+=,-]*$#', "not plain KEY=value: {$line}");
            expect($previous)->toStartWith('#', "no comment above: {$line}");
        }
        $previous = $line;
    }

    $values = portainerEnvValues();
    $empty = array_keys(array_filter($values, static fn (string $v): bool => $v === ''));
    sort($empty);
    expect($empty)->toBe(['APP_KEY', 'CSP_REPORT_PATH', 'DB_PASSWORD', 'DB_SUPERUSER_PASSWORD', 'TRUSTED_PROXIES']);
});

test('the Portainer env file has the secure production settings (TE-025 AC10, AC11)', function () {
    $values = portainerEnvValues();
    $lines = implode("\n", fileLines(dirname(__DIR__, 2).'/deploy/portainer.env.example'));

    expect($values['SESSION_SECURE_COOKIE'] ?? null)->toBe('true')
        ->and($values)->toHaveKeys(['HSTS_MAX_AGE', 'HSTS_INCLUDE_SUBDOMAINS', 'HSTS_PRELOAD', 'CSP_REPORT_PATH'])
        ->and($values['HSTS_PRELOAD'])->toBe('false')
        ->and($lines)->not->toContain('INERTIA_DEVTOOLS_ENABLED');

    foreach (['DB_SUPERUSER_PASSWORD', 'DB_PASSWORD', 'APP_KEY'] as $secret) {
        expect($lines)->toMatch('/^# SECRET: generate with .*\n'.$secret.'=$/m');
    }
});
