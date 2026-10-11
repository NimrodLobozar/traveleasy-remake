<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
| TE-005 AC4: the Playwright Docker image that runs the e2e suite (service `e2e` in compose.dev.yaml) must be
| the same version as @playwright/test. A mismatch makes Playwright look for browser builds the image doesn't
| have. To upgrade: change the exact version in package.json, run `npm install` in the vite container, set the
| same version in the image tag, rebuild (`docker compose up -d --build -V`).
|
| Plain unit test: reads files only, no Laravel app and no database.
*/

function playwrightSyncRepoFile(string $relative): string
{
    return dirname(__DIR__, 2).'/'.$relative;
}

function playwrightSyncImageVersion(): string
{
    $compose = asArray(Yaml::parseFile(playwrightSyncRepoFile('compose.dev.yaml')));
    $image = asString(asArray(asArray(asArray($compose['services'] ?? null)['e2e'] ?? null))['image'] ?? null);

    expect($image)->toMatch(
        '#^mcr\.microsoft\.com/playwright:v\d+\.\d+\.\d+-[a-z]+$#',
        "The e2e image [{$image}] must be the official Playwright image with an exact version tag.",
    );

    preg_match('#:v(\d+\.\d+\.\d+)-#', $image, $match);

    return $match[1] ?? '';
}

/** @return array<array-key, mixed> */
function playwrightSyncJson(string $relative): array
{
    return asArray(json_decode(fileContents(playwrightSyncRepoFile($relative)), true, flags: JSON_THROW_ON_ERROR));
}

test('the e2e Playwright image tag equals @playwright/test in package-lock.json', function () {
    $packages = asArray(playwrightSyncJson('package-lock.json')['packages'] ?? null);

    $locked = asString(asArray($packages['node_modules/@playwright/test'] ?? null)['version'] ?? null);
    $core = asString(asArray($packages['node_modules/playwright'] ?? null)['version'] ?? null);

    expect(playwrightSyncImageVersion())->toBe($locked, 'compose.dev.yaml e2e image vs package-lock.json')
        ->and($core)->toBe($locked, 'playwright (core) vs @playwright/test in package-lock.json');
});

test('@playwright/test is pinned to that exact version in package.json', function () {
    $devDependencies = asArray(playwrightSyncJson('package.json')['devDependencies'] ?? null);

    expect(asString($devDependencies['@playwright/test'] ?? null))
        ->toBe(playwrightSyncImageVersion(), 'package.json must pin @playwright/test exactly (no ^ or ~)');
});
