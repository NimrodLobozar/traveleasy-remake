<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Support\I18n\LocaleResolver;
use App\Support\I18n\Translations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Translation\Translator;
use Inertia\Testing\AssertableInertia as Assert;

/*
| TE-009 i18n infrastructure: the backend part.
| AC1: nl default, en fallback, configured via env; lang/nl.json + lang/en.json exist.
| AC2: the `locale` cookie selects the locale; shared props carry locale + translations; <html lang> server-side.
| AC3: Accept-Language when there is no cookie; unknown languages fall back to nl.
| AC4: cookie values outside the allow-list are ignored, never echoed, never an error.
| AC9 (server side): interpolated values are escaped; translation strings are plain text.
| Quality: only the active locale's strings are sent. Source: docs/plan.md § 7 "i18n", legacy § 3 Design (mixed NL/EN).
*/

/**
 * Merge the fixture JSON files (tests/Fixtures/lang) into the real ones for this test.
 */
function te009UseFixtureLines(): void
{
    app(Translator::class)->getLoader()->addJsonPath(base_path('tests/Fixtures/lang'));
}

/**
 * Read lang/<locale>.json; fails unless it is a flat JSON object of string keys and string values.
 *
 * @return array<string, string>
 */
function te009LangFile(string $locale): array
{
    $decoded = asArray(json_decode((string) file_get_contents(lang_path("{$locale}.json")), true, 512, JSON_THROW_ON_ERROR));
    $lines = [];

    foreach ($decoded as $key => $value) {
        if (! is_string($key) || ! is_string($value)) {
            throw new UnexpectedValueException("lang/{$locale}.json: {$key} is not a flat string entry.");
        }

        $lines[$key] = $value;
    }

    return $lines;
}

test('AC1 nl is the default and en the fallback locale, both from env', function () {
    expect(config('app.locale'))->toBe('nl')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(Locale::default())->toBe(Locale::Nl)
        ->and(Locale::fallback())->toBe(Locale::En);

    $configFile = (string) file_get_contents(config_path('app.php'));
    expect($configFile)->toContain("env('APP_LOCALE', 'nl')")
        ->and($configFile)->toContain("env('APP_FALLBACK_LOCALE', 'en')");

    $envExample = (string) file_get_contents(base_path('.env.example'));
    expect($envExample)->toMatch('/^APP_LOCALE=nl$/m')
        ->and($envExample)->toMatch('/^APP_FALLBACK_LOCALE=en$/m');
});

test('AC1 an APP_LOCALE outside the allow-list falls back to nl and en', function () {
    config(['app.locale' => 'xx', 'app.fallback_locale' => '../etc']);

    expect(Locale::default())->toBe(Locale::Nl)
        ->and(Locale::fallback())->toBe(Locale::En);
});

test('AC1 lang/nl.json and lang/en.json are flat key => string maps for every allowed locale', function () {
    expect(array_map(fn (Locale $l) => $l->value, Locale::cases()))->toBe(['nl', 'en']);

    foreach (Locale::cases() as $locale) {
        $lines = te009LangFile($locale->value);

        expect($lines)->not->toBeEmpty();

        foreach (array_keys($lines) as $key) {
            expect($key)->toMatch('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', "Key {$key} isn't area.thing.action");
        }
    }
});

test('AC9 translation strings are plain text, never HTML', function () {
    foreach (Locale::cases() as $locale) {
        foreach (te009LangFile($locale->value) as $key => $value) {
            expect($value)->not->toMatch('/[<>]/', "lang/{$locale->value}.json {$key} contains markup");
        }
    }
});

test('AC2 the locale cookie selects the locale, the shared props and <html lang>', function () {
    $response = $this->withUnencryptedCookie('locale', 'en')
        ->withHeader('Accept-Language', 'nl')
        ->get(route('home'));

    $response->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('fallbackLocale', 'en')
            ->where('translations', te009LangFile('en')));

    expect($response->getContent())->toContain('<html lang="en"');
});

test('AC2 the nl cookie wins over an English Accept-Language', function () {
    $this->withUnencryptedCookie('locale', 'nl')
        ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));
});

test('AC2 without cookie or header the locale is nl and the nl strings are sent', function () {
    // Symfony's test requests send `Accept-Language: en-us,en;q=0.5` unless told otherwise.
    $response = $this->withHeader('Accept-Language', '')->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'nl')
        ->where('fallbackLocale', 'en')
        ->where('translations', app(Translations::class)->for(Locale::Nl)));

    expect($response->getContent())->toContain('<html lang="nl"');
    expect(app(Translations::class)->for(Locale::Nl))->toMatchArray(te009LangFile('nl'));
});

test('AC2 the response varies by Accept-Language', function () {
    $vary = implode(', ', $this->get(route('home'))->headers->all('vary'));

    expect($vary)->toContain('Accept-Language');
});

test('quality only the active locale is sent, merged over the fallback for missing keys', function () {
    te009UseFixtureLines();

    $nl = app(Translations::class)->for(Locale::Nl);
    $en = app(Translations::class)->for(Locale::En);

    expect($nl['te009.fixture.both'])->toBe('Beide (nl)')
        ->and($nl['te009.fixture.only_en'])->toBe('Only in English')
        ->and($nl)->not->toContain('Both (en)')
        ->and($en['te009.fixture.both'])->toBe('Both (en)')
        ->and($en)->not->toContain('Beide (nl)')
        ->and(array_keys($nl))->toBe(array_keys($en));
});

test('AC3 Accept-Language picks the locale when there is no cookie', function (string $header, string $expected) {
    $this->withHeader('Accept-Language', $header)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', $expected));
})->with([
    'en-GB with q' => ['en-GB,en;q=0.9', 'en'],
    'plain en' => ['en', 'en'],
    'en_US underscore' => ['en_US', 'en'],
    'upper case' => ['EN-us', 'en'],
    'fr' => ['fr', 'nl'],
    'fr then en' => ['fr-FR,fr;q=0.9,en;q=0.8', 'en'],
    'nl beats en by quality' => ['en;q=0.5,nl;q=0.8', 'nl'],
    'en with q=0 is refused' => ['en;q=0', 'nl'],
    'wildcard' => ['*', 'nl'],
    'empty' => ['', 'nl'],
    'garbage' => [';;;,,q=abc', 'nl'],
]);

test('AC3 a request without Accept-Language or cookie gets nl', function () {
    $request = Request::create('/');
    $request->headers->remove('Accept-Language');

    expect(app(LocaleResolver::class)->resolve($request))->toBe(Locale::Nl);
});

test('AC4 a cookie outside the allow-list is ignored and the chain continues', function (string $value) {
    $response = $this->withUnencryptedCookie('locale', $value)
        ->withHeader('Accept-Language', 'en')
        ->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    expect($response->getContent())->not->toContain($value);
})->with([
    'unknown code' => ['xx'],
    'path traversal' => ['../../etc/te009-probe'],
    'wrong case' => ['EN'],
    'padded' => [' nl '],
    'region' => ['nl-NL'],
    'markup' => ['<script>te009</script>'],
    'null byte' => ["nl\0te009"],
]);

test('AC4 a bad cookie without Accept-Language falls back to nl', function () {
    $this->withUnencryptedCookie('locale', '../../etc/passwd')
        ->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));
});

test('AC4 an array-shaped locale cookie is ignored, not a 400 or 500', function () {
    $response = $this->call('GET', route('home'), cookies: ['locale' => ['en']], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));
});

test('AC4 the resolver ignores a rejected value and leaves the request untouched', function () {
    $request = Request::create('/', cookies: ['locale' => 'te009-evil'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

    expect(app(LocaleResolver::class)->resolve($request))->toBe(Locale::Nl)
        ->and($request->cookies->get('locale'))->toBe('te009-evil');
});

test('AC9 a user-supplied value interpolated into a translation is escaped in Blade', function () {
    te009UseFixtureLines();
    app()->setLocale('en');

    $html = Blade::render('<p>{{ __("te009.fixture.greeting", ["name" => $name]) }}</p>', [
        'name' => '<img src=x onerror=alert(1)>',
    ]);

    expect($html)->toBe('<p>Hello &lt;img src=x onerror=alert(1)&gt;</p>');
});

test('AC9 translations in the page object cannot break out of the HTML', function () {
    te009UseFixtureLines();

    $html = (string) $this->withUnencryptedCookie('locale', 'en')->get(route('home'))->getContent();

    $value = '</script><script>window.te009=1</script>';

    expect($html)->not->toContain($value)
        ->and($html)->not->toContain('<script>window.te009=1')
        ->and($html)->toContain('window.te009=1'); // present, but only with `<` escaped as \u003c
});
