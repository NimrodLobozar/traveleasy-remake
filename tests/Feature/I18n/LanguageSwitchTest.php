<?php

declare(strict_types=1);

use App\Support\Http\SameOriginReferrer;
use App\Support\I18n\LocaleCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/*
| TE-017 language switch, server side.
| AC3: `POST /locale` accepts only nl/en (422 for JSON, an inline error for form/Inertia posts), CSRF, 30/min per IP.
| AC4: the `locale` cookie: first-party, Secure on HTTPS, SameSite=Lax, HttpOnly, one year; it wins in a new session.
| AC5: a plain form post redirects back to the same page. AC6: a request with `locale=en` is rendered in English.
| Quality: the redirect target is the same-origin referrer path only (no open redirect).
| Source: docs/plan.md § 4 M0 "Language switch", § 7 i18n; docs/design/system.md § 3 Language switch.
*/

/**
 * The `locale` cookie a response sets, if any.
 *
 * @param  TestResponse<Response>  $response
 */
function te017Cookie(TestResponse $response): ?Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'locale') {
            return $cookie;
        }
    }

    return null;
}

/**
 * Fill in the test origin: `{base}` = APP_URL, `{https}` = APP_URL over https, `{userinfo}` = APP_URL with credentials.
 */
function te017Url(string $template): string
{
    $base = rtrim(url('/'), '/');

    return strtr($template, [
        '{base}' => $base,
        '{https}' => (string) preg_replace('#^http:#', 'https:', $base),
        '{userinfo}' => str_replace('://', '://user:pass@', $base),
    ]);
}

test('AC3 AC5 a valid locale sets the cookie and redirects back to the same page', function (string $locale) {
    $response = $this->withHeader('Referer', te017Url('{base}/settings/profile?tab=a'))
        ->post('/locale', ['locale' => $locale]);

    $response->assertRedirect(te017Url('{base}/settings/profile?tab=a'))->assertSessionHasNoErrors();

    expect(te017Cookie($response)?->getValue())->toBe($locale);
})->with(['nl', 'en']);

test('AC4 the cookie is first-party, HttpOnly, SameSite=Lax, one year, not Secure over HTTP', function () {
    $this->freezeTime();

    $cookie = te017Cookie($this->post('/locale', ['locale' => 'en']));

    expect($cookie)->not->toBeNull()
        ->and($cookie?->isHttpOnly())->toBeTrue()
        ->and($cookie?->getSameSite())->toBe(Cookie::SAMESITE_LAX)
        ->and($cookie?->getPath())->toBe('/')
        ->and($cookie?->getDomain())->toBeNull()
        ->and($cookie?->isSecure())->toBeFalse()
        ->and($cookie?->isRaw())->toBeFalse()
        ->and($cookie?->getExpiresTime())->toBe(now()->addYear()->getTimestamp())
        ->and(LocaleCookie::LIFETIME_MINUTES)->toBe(525600);
});

test('AC4 the cookie is Secure when the request came in over HTTPS', function () {
    $cookie = te017Cookie($this->post(te017Url('{https}/locale'), ['locale' => 'en']));

    expect($cookie?->isSecure())->toBeTrue();
});

test('AC4 the cookie is sent unencrypted, so the resolver can read it', function () {
    $header = (string) collect($this->post('/locale', ['locale' => 'en'])->headers->all('set-cookie'))
        ->first(fn (?string $line): bool => str_starts_with((string) $line, 'locale='));

    expect($header)->toStartWith('locale=en;')
        ->toContain('httponly')
        ->toContain('samesite=lax');
});

test('AC4 AC6 a new session with the cookie renders in English; without it Accept-Language applies', function () {
    $this->withUnencryptedCookie('locale', 'en')
        ->withHeader('Accept-Language', 'nl')
        ->get('/')
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')
            ->where('translations', fn (Collection $lines): bool => $lines->get('shell.lang.label') === 'Language'));

    $this->flushHeaders();
    $this->unencryptedCookies = [];
    $this->withHeader('Accept-Language', 'nl')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));
});

test('AC3 any other value is rejected with an inline error and no cookie (form post)', function (mixed $value) {
    $response = $this->withHeader('Referer', te017Url('{base}/settings/profile'))
        ->post('/locale', ['locale' => $value]);

    $response->assertRedirect(te017Url('{base}/settings/profile'))
        ->assertSessionHasErrors(['locale' => 'Choose Nederlands or English.']);

    expect(te017Cookie($response))->toBeNull();
})->with([
    'unknown' => ['de'],
    'upper case' => ['EN'],
    'empty' => [''],
    'array' => [['en']],
    'script' => ['<script>alert(1)</script>'],
    'long' => [str_repeat('e', 5000)],
]);

test('AC3 a missing value is rejected too, and the Dutch message is localised', function () {
    $this->withUnencryptedCookie('locale', 'nl')
        ->post('/locale', [])
        ->assertSessionHasErrors(['locale' => 'Kies Nederlands of English.']);
});

test('AC3 a JSON request gets a 422 with the error and no cookie', function () {
    $response = $this->postJson('/locale', ['locale' => 'fr']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['locale' => 'Choose Nederlands or English.']);

    expect(te017Cookie($response))->toBeNull()
        ->and((string) $response->getContent())->not->toContain('fr"');
});

test('quality the CSRF token is required', function () {
    // PreventRequestForgery skips its check while unit tests run; pretend to be a real environment for this request.
    app()->instance('env', 'staging');

    $this->post('/locale', ['locale' => 'en'])->assertStatus(419);

    $this->get('/');
    $token = session()->token();

    $this->post('/locale', ['locale' => 'en', '_token' => $token])->assertRedirect();
});

test('quality the shared csrfToken prop is the session token, for no-JavaScript forms', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('csrfToken', session()->token()));
});

test('quality the switch is throttled at 30 posts a minute per IP', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->post('/locale', ['locale' => 'en'])->assertRedirect();
    }

    $this->post('/locale', ['locale' => 'en'])->assertTooManyRequests();
    $this->post('/locale', ['locale' => 'en'], ['REMOTE_ADDR' => '10.1.2.3'])->assertRedirect();
});

test('quality only GET-free POST is routed', function () {
    $this->get('/locale')->assertStatus(405);
});

test('quality no open redirect: anything but a same-origin referrer goes home', function (string $referrer) {
    $this->withHeader('Referer', te017Url($referrer))
        ->post('/locale', ['locale' => 'en'])
        ->assertRedirect(url('/'));

    $this->withHeader('Referer', te017Url($referrer))
        ->post('/locale', ['locale' => 'xx'])
        ->assertRedirect(url('/'));
})->with([
    'other host' => ['https://evil.example/phish'],
    'other scheme' => ['{https}/settings/profile'],
    'other port' => ['http://localhost:8080/settings/profile'],
    'host suffix' => ['http://localhost.evil.example/x'],
    'credentials' => ['{userinfo}/x'],
    'protocol-relative path' => ['{base}//evil.example/x'],
    'backslash path' => ['{base}/\\evil.example'],
    'not a URL' => ['javascript:alert(1)'],
    'relative' => ['/settings/profile'],
    'the endpoint itself' => ['{base}/locale'],
    'control characters' => ["{base}/a\r\nLocation: https://evil.example"],
    'overlong' => ['{base}/'.str_repeat('a', 2100)],
]);

test('quality SameOriginReferrer keeps path and query of a same-origin referrer', function (string $referrer, string $expected) {
    $request = Request::create('https://shop.test/locale', 'POST', server: ['HTTP_REFERER' => $referrer]);

    expect(app(SameOriginReferrer::class)->path($request, excludedPaths: ['/locale']))->toBe($expected);
})->with([
    'path' => ['https://shop.test/trips', '/trips'],
    'path and query' => ['https://shop.test/trips?from=AMS&page=2', '/trips?from=AMS&page=2'],
    'explicit default port' => ['https://shop.test:443/trips', '/trips'],
    'host case' => ['https://SHOP.test/trips', '/trips'],
    'no path' => ['https://shop.test', '/'],
    'fragment dropped' => ['https://shop.test/trips#top', '/trips'],
    'excluded with slash' => ['https://shop.test/locale/', '/'],
    'downgrade' => ['http://shop.test/trips', '/'],
]);
