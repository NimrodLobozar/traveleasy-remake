<?php

declare(strict_types=1);

use App\Support\Runtime\ProductionBootCheck;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/*
| TE-012 AC8 (docs/plan.md § 7): `php artisan traveleasy:boot-check`, run by the production entrypoint's app, worker
| and scheduler roles, refuses to start with APP_DEBUG=true or an empty/invalid APP_KEY in production, and with a
| wildcard TRUSTED_PROXIES anywhere. That it never runs for `ssr` or the pass-through is proven by
| tests/Unit/ProductionEntrypointContractTest.php and docker/prod/verify.sh.
*/

/** @param  array<string, mixed>  $config */
function bootAs(string $environment, array $config = []): void
{
    app()->instance('env', $environment);
    config(array_merge([
        'app.env' => $environment,
        'app.debug' => false,
        'app.key' => 'base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=',
        'app.cipher' => 'AES-256-CBC',
        'security.trusted_proxies' => '',
    ], $config));
}

function bootCheck(TestCase $case): PendingCommand
{
    $command = $case->artisan('traveleasy:boot-check');

    if (! $command instanceof PendingCommand) {
        throw new RuntimeException('Expected a pending command; console output mocking is off.');
    }

    return $command;
}

test('a safe production configuration passes', function () {
    bootAs('production', ['security.trusted_proxies' => '172.18.0.0/16']);

    bootCheck($this)->assertExitCode(0)->expectsOutputToContain('Boot check passed');
});

test('production with APP_DEBUG=true refuses to start with a clear message', function () {
    bootAs('production', ['app.debug' => true]);

    bootCheck($this)
        ->assertExitCode(1)
        ->expectsOutputToContain('Refusing to start: APP_DEBUG is true with APP_ENV=production');
});

test('production without a valid APP_KEY refuses to start with a clear message', function (?string $key) {
    bootAs('production', ['app.key' => $key]);

    bootCheck($this)
        ->assertExitCode(1)
        ->expectsOutputToContain('Refusing to start: APP_KEY is empty or not a valid key');
})->with([
    'empty' => [''],
    'null' => [null],
    'too short' => ['base64:'.base64_encode('short')],
    'not base64' => ['base64:%%%not-base64%%%'],
    'raw, wrong length' => ['plain-text-key'],
]);

test('every problem is reported, never the value of a secret', function () {
    $key = 'base64:'.base64_encode('a-secret-but-invalid-key');
    bootAs('production', ['app.debug' => true, 'app.key' => $key, 'security.trusted_proxies' => '*']);

    $violations = ProductionBootCheck::fromConfig()->violations();

    expect($violations)->toHaveCount(3)
        ->and(implode("\n", $violations))->not->toContain($key)->not->toContain('a-secret-but-invalid-key');
    bootCheck($this)->assertExitCode(1)->doesntExpectOutputToContain('a-secret-but-invalid-key');
});

test('a wildcard TRUSTED_PROXIES refuses to start in every environment', function (string $environment, string $proxies) {
    bootAs($environment, ['security.trusted_proxies' => $proxies]);

    bootCheck($this)
        ->assertExitCode(1)
        ->expectsOutputToContain('Refusing to start: TRUSTED_PROXIES contains');
})->with(['production', 'local', 'testing'])->with(['*', '**', 'REMOTE_ADDR', '0.0.0.0/0', '10.0.0.1, *']);

test('outside production, debug mode and a missing key do not block local work', function (string $environment) {
    bootAs($environment, ['app.debug' => true, 'app.key' => '']);

    bootCheck($this)->assertExitCode(0);
})->with(['local', 'testing']);

test('the AES-128 cipher accepts a 16-byte key', function () {
    bootAs('production', ['app.cipher' => 'AES-128-CBC', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 16))]);

    expect(ProductionBootCheck::fromConfig()->violations())->toBe([]);
});
