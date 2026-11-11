<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/*
| TE-052: `POST /register` is throttled per client IP with the login limit (5 per minute, FortifyServiceProvider).
| AC1 over the limit: 429 with the localised error page, no user row. AC2 the window passes: registering works again.
| AC3 the counter is per IP. AC5 a spoofed X-Forwarded-For from an untrusted peer doesn't change the key; behind a
| trusted proxy (TRUSTED_PROXIES, TE-012) the forwarded client address is the key. AC6 no e-mail in the key, the
| response or the logs. AC8 the route carries the limiter and login, two-factor and passkeys keep theirs.
*/

const TE052_PROXY_IP = '172.18.0.2';
const TE052_OUTSIDE_IP = '203.0.113.10';
const TE052_LIMIT = 5;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    // Like production: only the NPM network is trusted. Request::setTrustedProxies() is static, so it is reset too.
    config(['security.trusted_proxies' => '172.18.0.0/16']);
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

afterEach(function () {
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

/** @return array<string, string> */
function te052Registration(string $email): array
{
    return [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

/**
 * One registration attempt from a peer address, optionally with an X-Forwarded-For header.
 *
 * @param  array<string, string>  $payload
 * @return TestResponse<Response>
 */
function te052Post(TestCase $test, string $peer, array $payload, ?string $forwardedFor = null): TestResponse
{
    $test->withServerVariables(['REMOTE_ADDR' => $peer]);

    if ($forwardedFor !== null) {
        $test->withHeader('X-Forwarded-For', $forwardedFor);
    }

    return $test->post(route('register.store'), $payload);
}

/**
 * Use up the limit with attempts that fail validation, so no account is created and nobody signs in.
 *
 * @param  (callable(int): string)|null  $forwardedFor
 */
function te052ExhaustLimit(TestCase $test, string $peer, ?callable $forwardedFor = null): void
{
    for ($i = 0; $i < TE052_LIMIT; $i++) {
        $payload = ['email' => "attempt{$i}@example.com", 'password' => 'short'];

        te052Post($test, $peer, $payload, $forwardedFor === null ? null : $forwardedFor($i))
            ->assertSessionHasErrors();
    }
}

test('AC1 over the limit the next registration gets the localised 429 page and creates no user', function (string $locale, string $title) {
    te052ExhaustLimit($this, TE052_OUTSIDE_IP);

    $response = $this->withUnencryptedCookie('locale', $locale)
        ->withServerVariables(['REMOTE_ADDR' => TE052_OUTSIDE_IP])
        ->post(route('register.store'), te052Registration('new.user@example.com'));

    $response->assertTooManyRequests()
        ->assertInertia(fn (Assert $page) => $page->component('error', false)
            ->where('status', 429)
            ->where('locale', $locale)
            ->where('translations', fn (Collection $lines): bool => $lines->get('error.429.title') === $title)
            ->etc());

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
})->with([
    'nl' => ['nl', 'Te veel verzoeken'],
    'en' => ['en', 'Too many requests'],
]);

test('AC1 the limit is the login limit: the fifth attempt passes, the sixth is throttled', function () {
    for ($i = 0; $i < TE052_LIMIT; $i++) {
        te052Post($this, TE052_OUTSIDE_IP, ['email' => 'x@example.com'])->assertStatus(302);
    }

    te052Post($this, TE052_OUTSIDE_IP, ['email' => 'x@example.com'])->assertTooManyRequests();
});

test('AC2 after the window passes the same IP can register again', function () {
    te052ExhaustLimit($this, TE052_OUTSIDE_IP);
    te052Post($this, TE052_OUTSIDE_IP, te052Registration('new.user@example.com'))->assertTooManyRequests();

    $this->travel(61)->seconds();

    te052Post($this, TE052_OUTSIDE_IP, te052Registration('new.user@example.com'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::query()->sole()->email)->toBe('new.user@example.com');
});

test('AC3 a throttled IP does not block another IP', function () {
    te052ExhaustLimit($this, TE052_OUTSIDE_IP);
    te052Post($this, TE052_OUTSIDE_IP, te052Registration('first@example.com'))->assertTooManyRequests();

    te052Post($this, '198.51.100.20', te052Registration('second@example.com'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->sole()->email)->toBe('second@example.com');
});

test('AC5 a spoofed X-Forwarded-For from an untrusted peer does not change the key', function () {
    te052ExhaustLimit($this, TE052_OUTSIDE_IP, fn (int $i): string => "198.51.100.{$i}");

    te052Post($this, TE052_OUTSIDE_IP, te052Registration('new.user@example.com'), '198.51.100.99')
        ->assertTooManyRequests();

    expect(User::query()->count())->toBe(0)
        ->and(RateLimiter::attempts(md5('register'.TE052_OUTSIDE_IP)))->toBe(TE052_LIMIT);
});

test('AC5 behind the trusted proxy the client address is the key and a spoofed entry in front of it is ignored', function () {
    $client = '198.51.100.20';

    te052ExhaustLimit($this, TE052_PROXY_IP, fn (int $i): string => "192.0.2.{$i}, {$client}");

    te052Post($this, TE052_PROXY_IP, te052Registration('first@example.com'), "192.0.2.99, {$client}")
        ->assertTooManyRequests();

    // Another real client behind the same proxy has its own counter.
    te052Post($this, TE052_PROXY_IP, te052Registration('second@example.com'), '198.51.100.21')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->sole()->email)->toBe('second@example.com')
        ->and(RateLimiter::attempts(md5('register'.$client)))->toBe(TE052_LIMIT)
        ->and(RateLimiter::attempts(md5('register'.TE052_PROXY_IP)))->toBe(0);
});

test('AC6 the limiter key is the IP only, without the e-mail address', function () {
    $request = Request::create('/register', 'POST', te052Registration('Secret.Person@Example.com'), server: ['REMOTE_ADDR' => TE052_OUTSIDE_IP]);
    $limiter = RateLimiter::limiter('register');
    assert($limiter !== null);

    $limit = $limiter($request);
    assert($limit instanceof Limit);

    expect($limit->key)->toBe(TE052_OUTSIDE_IP)
        ->and($limit->maxAttempts)->toBe(TE052_LIMIT)
        ->and($limit->decaySeconds)->toBe(60);
});

test('AC6 a throttled registration neither returns nor logs the e-mail address', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    te052ExhaustLimit($this, TE052_OUTSIDE_IP);

    $response = te052Post($this, TE052_OUTSIDE_IP, te052Registration('secret.person@example.com'))
        ->assertTooManyRequests();

    expect((string) $response->getContent())->not->toContain('secret.person')
        ->and(implode("\n", $logged))->not->toContain('secret.person')
        ->and(session()->getOldInput())->toBe([]);
});

test('AC8 POST /register carries the register throttle and the other auth limiters are unchanged', function () {
    /** @return list<string> */
    $middleware = function (string $name): array {
        $route = Route::getRoutes()->getByName($name);
        assert($route !== null);

        return array_values(array_filter($route->gatherMiddleware(), is_string(...)));
    };

    expect($middleware('register.store'))->toContain('guest:web', 'throttle:register')
        ->and($middleware('login.store'))->toContain('throttle:login')
        ->and($middleware('two-factor.login.store'))->toContain('throttle:two-factor')
        ->and($middleware('passkey.login'))->toContain('throttle:passkeys')
        ->and(array_count_values($middleware('register.store'))['throttle:register'])->toBe(1);
});

test('AC8 the login, two-factor and passkey limits are unchanged', function () {
    $request = Request::create('/login', 'POST', ['email' => 'jan@example.com'], server: ['REMOTE_ADDR' => TE052_OUTSIDE_IP]);
    $request->setLaravelSession(app('session.store'));

    $limits = collect(['login' => 5, 'two-factor' => 5, 'passkeys' => 10])->map(function (int $expected, string $name) use ($request): bool {
        $limiter = RateLimiter::limiter($name);
        assert($limiter !== null);
        $limit = $limiter($request);

        return $limit instanceof Limit && $limit->maxAttempts === $expected && $limit->decaySeconds === 60;
    });

    expect($limits->all())->toBe(['login' => true, 'two-factor' => true, 'passkeys' => true]);
});
