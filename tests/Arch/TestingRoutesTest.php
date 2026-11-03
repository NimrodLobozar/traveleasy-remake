<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\SourceTree;

/*
| TE-019 AC10 (moved from TE-015 AC3): the deterministic error routes (routes/testing.php, `/_test/error/{status}`)
| exist only with APP_ENV `local` or `testing`, never in production or any other environment.
| Source: CLAUDE.md "No hidden test or demo toggles in production code"; legacy § 3 (dev_connection/simulate_error).
*/

/**
 * The URIs of the booted route table in the given environment (a separate PHP process, so `--env` really applies).
 *
 * @return list<string>
 */
function te019RoutesIn(string $environment): array
{
    $process = new Process(['php', 'artisan', 'route:list', '--json', '--env='.$environment], SourceTree::root(), ['APP_ENV' => $environment]);
    $process->setTimeout(60)->run();

    expect($process->isSuccessful())->toBeTrue("route:list failed in {$environment}: ".$process->getErrorOutput());

    $routes = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    return array_values(array_map(function (mixed $route): string {
        $uri = asArray($route)['uri'] ?? null;

        return is_string($uri) ? $uri : '';
    }, asArray($routes)));
}

test('AC10 the testing error routes exist in testing and local', function (string $environment) {
    expect(te019RoutesIn($environment))->toContain('_test/error/{status}')->toContain('design');
})->with(['testing', 'local']);

test('AC10 the testing error routes never exist in production or another environment', function (string $environment) {
    $uris = te019RoutesIn($environment);

    expect($uris)->toContain('locale')
        ->and(array_filter($uris, fn (string $uri): bool => str_starts_with($uri, '_test') || $uri === 'design'))->toBe([]);
})->with(['production', 'staging']);

test('AC10 routes/testing.php is loaded in exactly one place, behind the local/testing guard', function () {
    $loaders = [];

    foreach (SourceTree::files(['app', 'bootstrap', 'config', 'routes'], exclude: ['routes/testing.php']) as $file) {
        if (str_contains((string) file_get_contents(SourceTree::root().'/'.$file->path), 'testing.php')) {
            $loaders[] = $file->path;
        }
    }

    $bootstrap = (string) file_get_contents(SourceTree::root().'/bootstrap/app.php');

    expect($loaders)->toBe(['bootstrap/app.php'])
        ->and($bootstrap)->toMatch("#if \(app\(\)->environment\(\['local', 'testing'\]\)\) \{\s*Route::middleware\('web'\)->group\(base_path\('routes/testing\.php'\)\);\s*\}#");
});
