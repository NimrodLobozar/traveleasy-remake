<?php

declare(strict_types=1);

use Tests\Support\SourceTree;

/*
| TE-012 AC8 (ADR-0002 D3): the production boot check runs for the app, worker and scheduler roles before the DB
| wait, and never for the ssr role or the pass-through for one-off commands (the secrets helper of TE-025 must run
| without APP_KEY). docker/prod/verify.sh proves the same against the built image.
*/

/** @return array<string, string> role => body of its `case` arm */
function entrypointRoles(): array
{
    $script = file_get_contents(SourceTree::root().'/docker/prod/entrypoint.sh');
    expect($script)->toBeString();

    preg_match_all('/^ {4}(\S+)\)\n(.*?)\n {8};;$/ms', (string) $script, $matches, PREG_SET_ORDER);

    $roles = [];
    foreach ($matches as $match) {
        $roles[$match[1]] = $match[2];
    }

    return $roles;
}

function entrypointFunction(string $name): string
{
    $script = (string) file_get_contents(SourceTree::root().'/docker/prod/entrypoint.sh');
    preg_match('/^'.preg_quote($name, '/').'\(\) \{\n(.*?)\n\}$/ms', $script, $match);

    return $match[1] ?? '';
}

test('the entrypoint has exactly the app, ssr, worker, scheduler and pass-through roles', function () {
    expect(array_keys(entrypointRoles()))->toBe(['app', 'ssr', 'worker', 'scheduler', '*']);
});

test('prepare_runtime runs the boot check', function () {
    expect(entrypointFunction('prepare_runtime'))->toContain('php artisan traveleasy:boot-check');
});

test('the app, worker and scheduler roles run the boot check before the DB wait', function (string $role) {
    $body = entrypointRoles()[$role] ?? '';

    expect($body)->toStartWith('        prepare_runtime')
        ->and(strpos($body, 'prepare_runtime'))->toBeLessThan((int) strpos($body, 'wait-for-db.php'));
})->with(['app', 'worker', 'scheduler']);

test('the ssr role and the pass-through never boot PHP or run the boot check', function (string $role) {
    $body = entrypointRoles()[$role] ?? null;

    expect($body)->toBeString()
        ->and($body)->not->toContain('prepare_runtime')
        ->and($body)->not->toContain('boot-check')
        ->and($body)->not->toContain('artisan')
        ->and($body)->not->toContain('wait-for-db');
})->with(['ssr', '*']);

test('the app role migrates only after the boot check and the DB wait', function () {
    $body = entrypointRoles()['app'] ?? '';

    expect(strpos($body, 'wait-for-db.php'))->toBeLessThan((int) strpos($body, 'migrate --force'));
});
