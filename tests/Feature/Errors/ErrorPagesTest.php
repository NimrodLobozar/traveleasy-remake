<?php

declare(strict_types=1);

use App\Support\Errors\ErrorPageRenderer;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Symfony\Component\HttpFoundation\Response;

/*
| TE-019 localised error pages, server side.
| AC1: unknown URLs are a 404 `error` page in the visitor's locale. AC2: 403/419/429/500/503 keep their status and
| get the localised copy (test routes in routes/testing.php). AC3: a random request ID in the page, the X-Request-Id
| header and the log line. AC4: no exception message, SQL, path or trace with APP_DEBUG=false. AC5: locale and theme
| also on maintenance 503. AC6: Inertia XHR errors get the same page. AC9: security headers on every error page.
| Quality: the request ID is random; 429 doesn't name the limiter; no database access while rendering.
| Source: docs/plan.md § 4 M0 App shell, § 7 Error handling; docs/design/system.md § 3 404.
*/

/** The design copy per status (story AC2; 404 per the approved design). */
const TE019_TITLES = [
    403 => ['nl' => 'Geen toegang', 'en' => 'Access denied'],
    404 => ['nl' => 'Deze pagina bestaat niet', 'en' => 'This page does not exist'],
    419 => ['nl' => 'Sessie verlopen, probeer opnieuw', 'en' => 'Session expired, please try again'],
    429 => ['nl' => 'Te veel verzoeken', 'en' => 'Too many requests'],
    500 => ['nl' => 'Er ging iets mis', 'en' => 'Something went wrong'],
    503 => ['nl' => 'We zijn zo terug', 'en' => "We'll be right back"],
];

/**
 * Assert the localised error page: status, component, variant, locale and its copy.
 *
 * @param  TestResponse<Response>  $response
 */
function te019AssertErrorPage(TestResponse $response, int $httpStatus, int $variant, string $locale): void
{
    $response->assertStatus($httpStatus)
        ->assertHeader('Content-Language', $locale)
        ->assertSee('<html lang="'.$locale.'"', false)
        ->assertInertia(fn (Assert $page) => $page->component('error', false)
            ->where('status', $variant)
            ->where('requestId', $response->headers->get('X-Request-Id'))
            ->where('locale', $locale)
            ->where('translations', fn (Collection $lines): bool => $lines->get("error.{$variant}.title") === TE019_TITLES[$variant][$locale])
            ->etc());
}

/**
 * @param  TestResponse<Response>  $response
 */
function te019AssertSecurityHeaders(TestResponse $response): void
{
    expect((string) $response->headers->get('Content-Security-Policy'))->toMatch("#script-src 'self' 'nonce-#")
        ->not->toContain('unsafe-inline');
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect((string) $response->headers->get('X-Request-Id'))->toMatch('/\A[0-9a-f]{32}\z/');
}

/**
 * A maintenance-mode store that lives in memory, so parallel test workers never share a `down` file.
 *
 * @param  array<mixed>  $data
 */
function te019Maintenance(array $data): void
{
    app()->instance(MaintenanceMode::class, new class($data) implements MaintenanceMode
    {
        /** @param  array<mixed>  $data */
        public function __construct(private array $data) {}

        /** @param  array<mixed>  $payload */
        public function activate(array $payload): void
        {
            $this->data = $payload;
        }

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        /** @return array<mixed> */
        public function data(): array
        {
            return $this->data;
        }
    });
}

beforeEach(function () {
    config(['app.debug' => false]);
});

test('AC1 an unknown URL is a 404 error page in the default locale', function () {
    $response = $this->withHeader('Accept-Language', '')->get('/te019-no-such-page');

    te019AssertErrorPage($response, 404, 404, 'nl');
    te019AssertSecurityHeaders($response);
});

test('AC1 an unknown URL uses the visitor\'s locale from the cookie or Accept-Language', function () {
    te019AssertErrorPage($this->withUnencryptedCookie('locale', 'en')->withHeader('Accept-Language', 'nl')->get('/te019-nope'), 404, 404, 'en');

    $this->unencryptedCookies = [];
    te019AssertErrorPage($this->withHeader('Accept-Language', 'en-GB,en;q=0.8')->get('/te019-nope'), 404, 404, 'en');
});

test('AC1 rendering an unknown URL needs no database and shares the shell props', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get('/te019-nope')->assertInertia(fn (Assert $page) => $page->component('error', false)
        ->where('auth.user', null)
        ->where('appearance', 'system')
        ->has('translations')
        ->has('fallbackLocale')
        ->has('errors')
        ->etc());

    expect($queries)->toBe(0);
});

test('AC2 each status keeps its code and gets the localised copy', function (int $status, string $locale) {
    $response = $this->withUnencryptedCookie('locale', $locale)->get("/_test/error/{$status}");

    te019AssertErrorPage($response, $status, $status, $locale);
    te019AssertSecurityHeaders($response);
})->with([403, 404, 419, 429, 500, 503])->with(['nl', 'en']);

test('AC2 429 passes the Retry-After seconds; other statuses do not', function () {
    $this->get('/_test/error/429')
        ->assertHeader('Retry-After', '42')
        ->assertInertia(fn (Assert $page) => $page->where('retryAfter', 42)->etc());

    $this->get('/_test/error/403')
        ->assertInertia(fn (Assert $page) => $page->where('retryAfter', null)->etc());
});

test('quality a real throttle gives the 429 page without naming the limiter', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->post('/locale', ['locale' => 'en']);
    }

    $response = $this->post('/locale', ['locale' => 'en']);

    $response->assertTooManyRequests()
        ->assertInertia(fn (Assert $page) => $page->component('error', false)
            ->where('status', 429)
            ->where('retryAfter', fn (mixed $seconds): bool => is_int($seconds) && $seconds > 0 && $seconds <= 60)
            ->etc());

    $response->assertHeaderMissing('X-RateLimit-Limit')->assertHeaderMissing('X-RateLimit-Remaining');
    expect((string) $response->getContent())->not->toContain('throttle');
});

test('AC3 the request ID is random, matches the header and replaces any incoming value', function () {
    $first = $this->get('/_test/error/500');
    $second = $this->withHeader('X-Request-Id', 'planted-by-client')->get('/_test/error/500');
    $page = $this->get('/');

    $ids = [$first->headers->get('X-Request-Id'), $second->headers->get('X-Request-Id'), $page->headers->get('X-Request-Id')];

    expect($ids)->each->toMatch('/\A[0-9a-f]{32}\z/')
        ->and(array_unique($ids))->toHaveCount(3)
        ->and((string) $second->getContent())->not->toContain('planted-by-client');
});

test('AC3 the log line of a 500 carries the same request ID', function () {
    $handler = new TestHandler;
    config(['logging.channels.te019' => ['driver' => 'monolog', 'handler' => TestHandler::class], 'logging.default' => 'te019']);
    $channel = Log::channel('te019');
    $monolog = $channel instanceof Logger ? $channel->getLogger() : null;

    if (! $monolog instanceof Monolog) {
        throw new UnexpectedValueException('The te019 channel is not a Monolog logger.');
    }

    $monolog->setHandlers([$handler]);

    $response = $this->get('/_test/error/500')->assertServerError();
    $id = $response->headers->get('X-Request-Id');

    $records = $handler->getRecords();
    expect($records)->not->toBeEmpty()
        ->and($records[0]->extra['request_id'] ?? null)->toBe($id)
        ->and(Context::get('request_id'))->toBe($id);
});

test('AC4 with APP_DEBUG=false a 500 shows no message, SQL, path or trace', function () {
    $response = $this->get('/_test/error/500')->assertServerError();
    $everything = (string) $response->getContent().json_encode($response->headers->all());

    foreach (['SQLSTATE', 'select *', 'leak@example.test', 'Leak.php', '/var/www', 'RuntimeException', 'Stack trace', 'routes/testing.php'] as $secret) {
        expect($everything)->not->toContain($secret);
    }
});

test('AC4 JSON requests keep a JSON error without details', function () {
    $response = $this->getJson('/_test/error/500')->assertServerError()->assertExactJson(['message' => 'Server Error']);

    expect($response->headers->get('X-Request-Id'))->toMatch('/\A[0-9a-f]{32}\z/');
    $this->getJson('/te019-nope')->assertNotFound()->assertJsonMissingPath('component');
});

test('quality APP_DEBUG=true keeps the debug page for 5xx but still renders 4xx pages', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->group(function () {
        Route::get('/te019/debug/500', fn () => throw new RuntimeException('te019 debug detail'));
        Route::get('/te019/debug/403', fn () => abort(403));
    });

    // The local debug renderer shows the exception (dev only; production refuses APP_DEBUG=true, TE-012 boot check).
    expect((string) $this->get('/te019/debug/500')->assertServerError()->getContent())->toContain('te019 debug detail');
    $this->get('/te019/debug/403')->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('error', false)->etc());
});

test('AC10 the deterministic 500 route renders the production page even with APP_DEBUG=true (dev container)', function () {
    config(['app.debug' => true]);

    $response = $this->get('/_test/error/500')->assertServerError();

    expect((string) $response->getContent())->not->toContain('SQLSTATE');
    $response->assertInertia(fn (Assert $page) => $page->component('error', false)->where('status', 500)->etc());
});

test('AC5 the error page uses the chosen language and theme', function () {
    $response = $this->withUnencryptedCookie('locale', 'en')->withUnencryptedCookie('appearance', 'dark')->get('/te019-nope');

    te019AssertErrorPage($response, 404, 404, 'en');
    $response->assertSee('data-theme="dark"', false)
        ->assertSee('<meta name="color-scheme" content="dark">', false)
        ->assertInertia(fn (Assert $page) => $page->where('appearance', 'dark')->etc());
});

test('AC5 AC9 maintenance mode renders the same localised 503 page with the headers', function () {
    te019Maintenance(['retry' => 60, 'status' => 503, 'except' => []]);

    $response = $this->withUnencryptedCookie('locale', 'en')->withUnencryptedCookie('appearance', 'dark')->get('/');

    te019AssertErrorPage($response, 503, 503, 'en');
    te019AssertSecurityHeaders($response);
    $response->assertHeader('Retry-After', '60')
        ->assertSee('data-theme="dark"', false)
        ->assertInertia(fn (Assert $page) => $page->where('retryAfter', 60)->where('csrfToken', null)->etc());
});

test('AC6 an Inertia request that fails with 419 gets the error page as an Inertia response', function () {
    app()->instance('env', 'staging'); // CSRF is skipped while unit tests run

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/locale', ['locale' => 'en']);

    $response->assertStatus(419)->assertHeader('X-Inertia', 'true');
    expect($response->json('component'))->toBe('error')
        ->and($response->json('props.status'))->toBe(419)
        ->and($response->json('props.requestId'))->toBe($response->headers->get('X-Request-Id'))
        ->and((string) $response->headers->get('Vary'))->toContain('X-Inertia');
});

test('AC6 an Inertia request to an unknown URL gets the 404 page as an Inertia response', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->get('/te019-nope');

    $response->assertNotFound()->assertHeader('X-Inertia', 'true');
    expect($response->json('component'))->toBe('error')
        ->and($response->json('props.status'))->toBe(404);
});

test('quality other statuses keep their code and show the closest variant', function (int $status, int $variant) {
    expect(ErrorPageRenderer::variant($status))->toBe($variant);
})->with([
    [403, 403], [404, 404], [419, 419], [429, 429], [500, 500], [503, 503],
    [401, 403], [405, 404], [400, 500], [413, 500], [502, 500],
]);

test('quality a wrong method is a 405 that shows the 404 page', function () {
    $this->post('/')->assertStatus(405)->assertInertia(fn (Assert $page) => $page->where('status', 404)->etc());
});

test('quality if the page itself cannot render, the plain fallback keeps status, nonce and no details', function () {
    Inertia::setRootView('te019-missing-view');
    Event::fake([MessageLogged::class]);

    $response = $this->get('/te019-nope')->assertNotFound();
    $nonce = (string) preg_replace("#.*'nonce-([^']+)'.*#s", '$1', (string) $response->headers->get('Content-Security-Policy'));

    expect((string) $response->getContent())->not->toContain('data-page')
        ->toContain('nonce="'.$nonce.'"')
        ->not->toContain('te019-missing-view');

    Event::assertDispatched(MessageLogged::class, fn (MessageLogged $log): bool => $log->level === 'error'
        && $log->message === 'Error page rendering failed; served the plain fallback page.'
        && array_keys($log->context) === ['failure', 'source_location', 'status']);
});
