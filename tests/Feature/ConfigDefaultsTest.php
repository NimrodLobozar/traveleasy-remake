<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;

/**
 * Load a config file with the given environment variables overridden (null = unset),
 * restoring the environment afterwards. phpunit.xml sets some of them for the test run.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function loadConfigFileWithEnv(string $file, array $env): array
{
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($saved as $key => [$getenv, $envValue, $serverValue]) {
            $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
            unset($_ENV[$key], $_SERVER[$key]);
            if ($envValue !== null) {
                $_ENV[$key] = $envValue;
            }
            if ($serverValue !== null) {
                $_SERVER[$key] = $serverValue;
            }
        }
    }
}

test('the mailer is fixed to array and is the only mailer at runtime', function () {
    expect(config('mail.default'))->toBe('array')
        ->and(array_keys(config('mail.mailers')))->toBe(['array'])
        ->and(Mail::mailer()->getSymfonyTransport()::class)->toBe(ArrayTransport::class);
});

test('MAIL_MAILER in the environment cannot switch the mailer on', function () {
    $config = loadConfigFileWithEnv('mail.php', ['MAIL_MAILER' => 'smtp']);

    expect($config['default'])->toBe('array')
        ->and(array_keys($config['mailers']))->toBe(['array']);
});

test('a non-array mailer is not configured even when requested by name', function () {
    expect(fn () => Mail::mailer('smtp'))->toThrow(InvalidArgumentException::class);
});

test('the application time zone is UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

test('the database config defaults to the PostgreSQL service', function () {
    $config = loadConfigFileWithEnv('database.php', [
        'DB_CONNECTION' => null,
        'DB_HOST' => null,
        'DB_PORT' => null,
        'DB_DATABASE' => null,
        'DB_USERNAME' => null,
        'DB_PASSWORD' => null,
    ]);

    expect($config['default'])->toBe('pgsql')
        ->and($config['connections']['pgsql'])->toMatchArray([
            'host' => 'db',
            'port' => '5432',
            'database' => 'traveleasy',
            'username' => 'traveleasy',
            'password' => '',
        ])
        ->and($config['connections']['sqlite']['database'])->toBe(':memory:');
});

test('eloquent is strict outside production', function () {
    expect(app()->isProduction())->toBeFalse()
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});
