<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite as ViteService;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\PhpFile;

/*
| TE-007 Inertia SSR entry point: the backend part.
| AC2 (server-side): the configured SSR server renders the page and its HTML is served.
| AC4: SSR down or failing -> HTTP 200, client-side rendering, no error text in the response.
| AC7: the SSR server binds to loopback by default and its port is never published on the host.
| Quality: the SSR config points at the configured URL; SSR gets only the browser's page object;
| failures are logged without personal data. Source: docs/plan.md § 4 M0 "Inertia SSR".
*/

const TE007_SSR_ENV = [
    'INERTIA_SSR_ENABLED',
    'INERTIA_SSR_URL',
    'INERTIA_SSR_TIMEOUT',
    'INERTIA_SSR_HOST',
    'INERTIA_SSR_PORT',
    'INERTIA_SSR_THROW_ON_ERROR',
];

/**
 * Load config/inertia.php with only the given INERTIA_SSR_* variables set (all others unset),
 * restoring the environment afterwards.
 *
 * @param  array<string, string>  $env
 * @return array<array-key, mixed>
 */
function te007SsrConfig(array $env = []): array
{
    $saved = [];

    foreach (TE007_SSR_ENV as $key) {
        $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        $value = $env[$key] ?? null;

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    try {
        return Arr::array(asArray(PhpFile::returnValue(config_path('inertia.php'))), 'ssr');
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

/**
 * The JSON page object the browser receives in `<script data-page="app">`.
 *
 * @param  TestResponse<Response>  $response
 */
function te007BrowserPage(TestResponse $response): mixed
{
    $html = asString($response->getContent());

    expect(preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m))->toBe(1);

    return json_decode(asString($m[1] ?? null), true, flags: JSON_THROW_ON_ERROR);
}

/** SSR on, pointing at a fake server; any existing file stands in for the built SSR bundle. */
function te007UseFakeSsrServer(): void
{
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.url' => 'http://ssr.test:13714',
        'inertia.ssr.bundle' => __FILE__,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();

    // Act as a production build: no asset tags and Vite "not running hot". Laravel's withoutVite()
    // double still reads public/hot, which exists while the dev server runs, so pin it here.
    $this->withoutVite();
    Vite::clearResolvedInstance();
    $this->swap(ViteService::class, new class extends ViteService
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            return new HtmlString('');
        }

        public function isRunningHot(): bool
        {
            return false;
        }
    });
});

// --- Config ---------------------------------------------------------------------------------

test('the SSR config defaults to the loopback SSR server on port 13714, enabled, never throwing', function () {
    $ssr = te007SsrConfig();

    expect($ssr['enabled'])->toBeTrue()
        ->and($ssr['url'])->toBe('http://127.0.0.1:13714')
        ->and($ssr['timeout'])->toBe(2.0)
        ->and($ssr['throw_on_error'])->toBeFalse()
        ->and($ssr['ensure_bundle_exists'])->toBeTrue()
        ->and($ssr['server'])->toBe(['host' => '127.0.0.1', 'port' => 13714]);
});

test('the SSR config takes the URL, timeout, bind address and port from the environment', function () {
    $ssr = te007SsrConfig([
        'INERTIA_SSR_URL' => 'http://ssr:13800',
        'INERTIA_SSR_TIMEOUT' => '0.5',
        'INERTIA_SSR_HOST' => '0.0.0.0',
        'INERTIA_SSR_PORT' => '13800',
    ]);

    expect($ssr['url'])->toBe('http://ssr:13800')
        ->and($ssr['timeout'])->toBe(0.5)
        ->and($ssr['server'])->toBe(['host' => '0.0.0.0', 'port' => 13800]);
});

test('without INERTIA_SSR_URL the SSR URL follows INERTIA_SSR_PORT', function () {
    expect(te007SsrConfig(['INERTIA_SSR_PORT' => '13801'])['url'])->toBe('http://127.0.0.1:13801');
});

test('SSR can be switched off with INERTIA_SSR_ENABLED=false', function () {
    expect(te007SsrConfig(['INERTIA_SSR_ENABLED' => 'false'])['enabled'])->toBeFalse();
});

test('AC4 the environment cannot make SSR failures throw', function () {
    expect(te007SsrConfig(['INERTIA_SSR_THROW_ON_ERROR' => 'true'])['throw_on_error'])->toBeFalse();
});

// --- AC2 (server side) and the page object -------------------------------------------------

test('AC2 GET / is rendered by the SSR server at the configured URL and its HTML is served', function () {
    te007UseFakeSsrServer();
    Http::fake([
        'http://ssr.test:13714/render' => Http::response([
            'head' => ['<meta name="te007-ssr-head" content="1">'],
            'body' => '<div id="app"><h1>Server-rendered welcome</h1></div>',
        ]),
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<h1>Server-rendered welcome</h1>', false)
        ->assertSee('<meta name="te007-ssr-head" content="1">', false);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://ssr.test:13714/render');
});

test('the SSR server receives exactly the page object the browser gets, no extra props', function () {
    // What the browser gets without SSR.
    config(['inertia.ssr.enabled' => false]);
    $browserPage = te007BrowserPage($this->get('/'));
    Http::assertNothingSent();
    // Inertia caches the SSR result per request in a scoped binding; tests reuse one app instance.
    $this->app->forgetScopedInstances();

    te007UseFakeSsrServer();
    Http::fake(['http://ssr.test:13714/render' => Http::response(['head' => [], 'body' => '<div id="app"></div>'])]);

    $this->get('/')->assertOk();

    Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true) === $browserPage);
});

// --- AC4: client-side fallback -------------------------------------------------------------

test('AC4 when the SSR server is down, / still renders client-side with 200 and no error text', function () {
    te007UseFakeSsrServer();
    Http::fake([
        'http://ssr.test:13714/render' => Http::failedConnection(
            'cURL error 7: Failed to connect to ssr.test port 13714: Connection refused',
        ),
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertSee('<script data-page="app" type="application/json">', false)
        ->assertDontSee('cURL error', false)
        ->assertDontSee('Connection refused', false)
        ->assertDontSee('ssr.test', false);
    expect(te007BrowserPage($response))->toMatchArray(['component' => 'welcome', 'url' => '/']);
    Http::assertSentCount(1);
});

test('AC4 when the SSR render fails, / renders client-side with 200 and no stack trace or SSR error', function () {
    te007UseFakeSsrServer();
    Http::fake([
        'http://ssr.test:13714/render' => Http::response([
            'error' => 'window is not defined',
            'type' => 'browser-api',
            'browserApi' => 'window',
            'hint' => 'Wrap browser-specific code in a useEffect lifecycle hook.',
            'stack' => "ReferenceError: window is not defined\n    at render (/var/www/html/bootstrap/ssr/ssr.js:12:7)",
            'sourceLocation' => 'resources/js/pages/welcome.tsx:12:7',
        ], 500),
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertDontSee('window is not defined', false)
        ->assertDontSee('ReferenceError', false)
        ->assertDontSee('bootstrap/ssr', false)
        ->assertDontSee('useEffect', false)
        ->assertDontSee('welcome.tsx', false);
});

test('a failed SSR render is logged without page props, URL, error message or stack', function () {
    te007UseFakeSsrServer();
    Http::fake([
        'http://ssr.test:13714/render' => Http::response([
            'error' => 'Cannot read te007_fake_secret of undefined',
            'type' => 'render',
            'stack' => 'TypeError: Cannot read te007_fake_secret',
            'sourceLocation' => 'resources/js/pages/welcome.tsx:3:1',
        ], 500),
    ]);
    /** @var list<MessageLogged> $logged */
    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged): void {
        if (str_contains($event->message, 'Inertia SSR')) {
            $logged[] = $event;
        }
    });

    $this->get('/?te007_fake_query=secret')->assertOk();

    expect($logged)->toHaveCount(1);
    $entry = $logged[0] ?? throw new RuntimeException('No SSR log entry.');
    expect($entry->level)->toBe('warning')
        ->and($entry->context)->toBe([
            'type' => 'render',
            'component' => 'welcome',
            'browser_api' => null,
            'source_location' => 'resources/js/pages/welcome.tsx:3:1',
        ])
        ->and(json_encode([$entry->message, $entry->context]))->not->toContain('te007_fake');
});

// --- When SSR is not attempted ------------------------------------------------------------

test('SSR is not attempted when it is disabled in config', function () {
    te007UseFakeSsrServer();
    config(['inertia.ssr.enabled' => false]);
    Http::fake();

    $this->get('/')->assertOk()->assertSee('<div id="app"></div>', false);

    Http::assertNothingSent();
});

test('SSR is not attempted while the Vite dev server runs (dev stays client-side with HMR)', function () {
    te007UseFakeSsrServer();
    Http::fake();
    $hotFile = storage_path('framework/testing/te007-vite-hot-'.getmypid());
    if (! is_dir(dirname($hotFile))) {
        mkdir(dirname($hotFile), 0o775, true);
    }
    file_put_contents($hotFile, 'http://localhost:5173');

    try {
        $this->withVite();
        Vite::useHotFile($hotFile);

        $this->get('/')->assertOk()->assertSee('<div id="app"></div>', false);
    } finally {
        if (is_file($hotFile)) {
            unlink($hotFile);
        }
    }

    Http::assertNothingSent();
});

// --- AC7: never on a host port ---------------------------------------------------------------

test('AC7 no compose service publishes the SSR port on the host', function () {
    foreach (['compose.yaml', 'compose.dev.yaml'] as $file) {
        $services = Arr::array(asArray(Yaml::parseFile(base_path($file))), 'services');

        foreach ($services as $name => $service) {
            $ports = Arr::array(asArray($service), 'ports', []);

            if ($name === 'ssr') {
                expect($ports)->toBe([], "{$file}: the ssr service must not publish any port");
            }

            foreach ($ports as $port) {
                expect(json_encode($port))
                    ->not->toContain('13714', "{$file}: service {$name} publishes the SSR port")
                    ->not->toContain('INERTIA_SSR', "{$file}: service {$name} publishes the SSR port");
            }
        }
    }
});
