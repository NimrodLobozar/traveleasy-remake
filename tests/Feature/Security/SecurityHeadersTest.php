<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Foundation\Vite as ViteService;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/*
| TE-015 security headers and CSP baseline: the HTTP side.
| AC1 CSP on every HTML response; AC2 per-request nonce on every inline <script>/<style> and the Vite tags;
| AC4 other headers; AC5 HSTS from APP_URL; AC6 Vite dev origin only in local; AC7 no external reporting;
| AC8 the same headers on error and file responses. Quality: no X-Powered-By, nonce generated once per request.
| Source: docs/plan.md § 4 M0 App shell; legacy § 3 Security (no security headers).
*/

/**
 * The nonce from the CSP header of a response.
 *
 * @param  TestResponse<Response>  $response
 */
function te015Nonce(TestResponse $response): string
{
    $csp = (string) $response->headers->get('Content-Security-Policy');

    if (preg_match("#script-src 'self' 'nonce-([A-Za-z0-9+/=]+)'#", $csp, $match) !== 1) {
        throw new UnexpectedValueException("No script nonce in the CSP: {$csp}");
    }

    return $match[1];
}

/**
 * Assert the baseline security headers on a response.
 *
 * @param  TestResponse<Response>  $response
 */
function te015AssertSecurityHeaders(TestResponse $response): void
{
    $csp = (string) $response->headers->get('Content-Security-Policy');

    foreach ([
        "default-src 'self'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ] as $directive) {
        expect($csp)->toContain($directive);
    }

    expect($csp)->toMatch("#script-src 'self' 'nonce-[A-Za-z0-9+/]{22}=='#")
        ->toMatch("#style-src 'self' 'nonce-[A-Za-z0-9+/]{22}=='#")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval');

    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By');
}

/**
 * Every <script> and <style> element of an HTML page, as [tag, attributes].
 *
 * @return list<array{0: string, 1: array<string, string>}>
 */
function te015InlineTags(string $html): array
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $tags = [];

    foreach ($document->querySelectorAll('script, style') as $element) {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        $tags[] = [strtolower($element->tagName), $attributes];
    }

    return $tags;
}

/**
 * Use the real Vite helper with a temporary hot file, so @vite and @viteReactRefresh print their dev-server tags.
 */
function te015WithViteDevServer(): void
{
    $hotFile = storage_path('framework/testing/te015-hot-'.getmypid());
    is_dir(dirname($hotFile)) || mkdir(dirname($hotFile), 0o775, true);
    file_put_contents($hotFile, 'http://localhost:5173');

    // TestCase::setUp() swapped in a no-op Vite (withoutVite); use a real one that reads the temporary hot file.
    $vite = new ViteService;
    $vite->useHotFile($hotFile);
    Vite::swap($vite);

    register_shutdown_function(static fn () => @unlink($hotFile));
}

beforeEach(function () {
    Route::middleware('web')->group(function () {
        Route::get('/te015/abort/{status}', fn (int $status) => abort($status));
        Route::get('/te015/419', fn () => throw new TokenMismatchException);
        Route::get('/te015/429', fn () => throw new ThrottleRequestsException);
        Route::get('/te015/500', fn () => throw new RuntimeException('te015 boom'));
        Route::get('/te015/file', fn () => response()->file(public_path('favicon.ico')));
        Route::get('/te015/powered', fn () => response('ok')->header('X-Powered-By', 'PHP/8.5'));
    });
});

test('AC1 AC4 an HTML page carries the CSP and the other security headers', function () {
    te015AssertSecurityHeaders($this->get(route('home'))->assertOk());
});

test('AC1 the CSP names no external origin outside local', function () {
    $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

    expect($csp)->not->toMatch('#\b(https?|wss?):#')->not->toContain('*');
});

test('AC2 every request gets a different nonce', function () {
    $first = te015Nonce($this->get(route('home')));
    $second = te015Nonce($this->get(route('home')));

    expect($first)->not->toBe($second)
        ->and(strlen((string) base64_decode($first, true)))->toBe(16);
});

test('AC2 every inline script and style, the Vite tags and the csp-nonce meta carry the header nonce', function () {
    te015WithViteDevServer();

    $response = $this->get(route('home'))->assertOk();
    $nonce = te015Nonce($response);
    $html = (string) $response->getContent();
    $tags = te015InlineTags($html);

    expect($html)->toContain('http://localhost:5173/@vite/client')
        ->and($html)->toContain('<meta property="csp-nonce" nonce="'.$nonce.'">');

    $checked = 0;

    foreach ($tags as [$tag, $attributes]) {
        // The page object is JSON data (`type="application/json"`); the browser never runs it, so CSP doesn't apply.
        if ($tag === 'script' && ($attributes['type'] ?? '') === 'application/json') {
            continue;
        }

        expect($attributes)->toHaveKey('nonce', $nonce, "<{$tag}> without the matching nonce");
        $checked++;
    }

    // Theme script, theme style, React refresh preamble, @vite/client and the three entry tags at least.
    expect($checked)->toBeGreaterThanOrEqual(5);
});

test('AC5 HSTS is sent when APP_URL is https', function () {
    config(['app.url' => 'https://traveleasy.example.com']);

    $this->get(route('home'))->assertHeader('Strict-Transport-Security', 'max-age=15552000');
});

test('AC5 the HSTS switches are read from config', function () {
    config([
        'app.url' => 'https://traveleasy.example.com',
        'security.hsts.max_age' => 31536000,
        'security.hsts.include_subdomains' => true,
        'security.hsts.preload' => true,
    ]);

    $this->get(route('home'))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
});

test('AC5 no HSTS when APP_URL is http', function (string $url) {
    config(['app.url' => $url]);

    $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');
})->with(['http://localhost:8000', 'http://192.168.1.20:8000']);

test('AC5 the HSTS defaults: 180 days, no preload, no includeSubDomains, documented env switches', function () {
    expect(config('security.hsts'))->toBe([
        'max_age' => 15552000,
        'include_subdomains' => false,
        'preload' => false,
    ]);

    $envExample = (string) file_get_contents(base_path('.env.example'));

    foreach (['HSTS_MAX_AGE', 'HSTS_INCLUDE_SUBDOMAINS', 'HSTS_PRELOAD', 'CSP_REPORT_PATH'] as $variable) {
        expect($envExample)->toMatch("/^#?\\s*{$variable}=/m");
    }
});

test('AC6 with APP_ENV=local the CSP allows only the Vite dev server in addition', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain("connect-src 'self' http://localhost:5173 ws://localhost:5173")
        ->and($csp)->toMatch("#script-src 'self' 'nonce-[^']+' http://localhost:5173;#");

    preg_match_all('#\b(?:https?|wss?)://[^\s;]+#', $csp, $origins);
    expect(array_values(array_unique($origins[0])))->toBe(['http://localhost:5173', 'ws://localhost:5173']);
});

test('AC6 outside local the Vite dev server is never allowed', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('localhost')->not->toContain('ws:');
})->with(['production', 'testing', 'staging']);

test('AC7 no reporting endpoint by default, and never an external one', function () {
    $response = $this->get(route('home'));

    expect((string) $response->headers->get('Content-Security-Policy'))
        ->not->toContain('report-uri')
        ->not->toContain('report-to');
    $response->assertHeaderMissing('Reporting-Endpoints')->assertHeaderMissing('Report-To');

    config(['security.csp.report_path' => 'https://reports.example.com/csp']);
    expect((string) $this->get(route('home'))->headers->get('Content-Security-Policy'))->not->toContain('report-uri');

    config(['security.csp.report_path' => '/csp-report']);
    expect((string) $this->get(route('home'))->headers->get('Content-Security-Policy'))->toEndWith('; report-uri /csp-report');
});

test('AC8 error responses carry the same headers', function (string $uri, int $status) {
    config(['app.debug' => false]);

    $response = $this->get($uri)->assertStatus($status);

    te015AssertSecurityHeaders($response);
})->with([
    '403' => ['/te015/abort/403', 403],
    '404 unknown route' => ['/te015-no-such-page', 404],
    '404 abort' => ['/te015/abort/404', 404],
    '419' => ['/te015/419', 419],
    '429' => ['/te015/429', 429],
    '500' => ['/te015/500', 500],
    '503' => ['/te015/abort/503', 503],
]);

test('AC8 error pages give their inline styles the header nonce', function (string $uri) {
    config(['app.debug' => false]);

    $response = $this->get($uri);
    $nonce = te015Nonce($response);
    $tags = te015InlineTags((string) $response->getContent());

    expect($tags)->not->toBeEmpty();

    foreach ($tags as [$tag, $attributes]) {
        // Since TE-019 error pages are Inertia pages: the page object is JSON data the browser never runs.
        if ($tag === 'script' && ($attributes['type'] ?? '') === 'application/json') {
            continue;
        }

        expect($attributes)->toHaveKey('nonce', $nonce, "<{$tag}> on {$uri} without the matching nonce");
    }
})->with(['/te015-no-such-page', '/te015/500', '/te015/abort/503']);

test('AC8 JSON error responses, files and the health check carry the headers', function () {
    config(['app.debug' => false]);

    te015AssertSecurityHeaders($this->getJson('/te015/500')->assertStatus(500));
    te015AssertSecurityHeaders($this->get('/te015/file')->assertOk());
    te015AssertSecurityHeaders($this->get('/up')->assertOk());
});

test('quality X-Powered-By is removed even when set by the app', function () {
    $this->get('/te015/powered')->assertOk()->assertHeaderMissing('X-Powered-By');
});

test('quality the error page leaks no exception text', function () {
    config(['app.debug' => false]);

    expect((string) $this->get('/te015/500')->getContent())->not->toContain('te015 boom');
});
