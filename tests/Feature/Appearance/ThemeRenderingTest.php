<?php

declare(strict_types=1);

use App\Enums\Appearance;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

/*
| TE-018 theme without flash, server side.
| AC1: without a cookie the page renders as `system`, with a tiny nonce'd inline script that applies the system theme.
| AC2: `appearance=dark|light` is rendered on <html> (class, data-theme) with the matching color-scheme meta, no script.
| AC3: any other cookie value is `system`, never echoed, never an error. AC5: the inline script carries the CSP nonce.
| Quality: the allow-list is checked server-side; the script stays under 1 KB; the choice is a shared prop for SSR.
| Source: docs/plan.md § 4 M0 App shell; docs/design/system.md § 3 Theme switch.
*/

/**
 * @param  TestResponse<Response>  $response
 * @return array{html: Element, meta: ?string, scripts: list<array{nonce: string, code: string}>, nonce: string}
 */
function te018Page(TestResponse $response): array
{
    $response->assertOk();
    $document = HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    $html = $document->documentElement;

    if (! $html instanceof Element) {
        throw new UnexpectedValueException('No <html> element.');
    }

    $scripts = [];

    foreach ($document->querySelectorAll('head script:not([src]):not([type])') as $script) {
        $scripts[] = ['nonce' => (string) $script->getAttribute('nonce'), 'code' => (string) $script->textContent];
    }

    preg_match("#'nonce-([A-Za-z0-9+/=]+)'#", (string) $response->headers->get('Content-Security-Policy'), $match);

    return [
        'html' => $html,
        'meta' => $document->querySelector('meta[name="color-scheme"]')?->getAttribute('content'),
        'scripts' => $scripts,
        'nonce' => $match[1] ?? '',
    ];
}

/**
 * @param  list<array{nonce: string, code: string}>  $scripts
 * @return list<array{nonce: string, code: string}>
 */
function te018ThemeScripts(array $scripts): array
{
    return array_values(array_filter($scripts, fn (array $script): bool => str_contains($script['code'], 'prefers-color-scheme')));
}

test('AC1 without a cookie the page renders as system with a nonce\'d theme script', function () {
    $response = $this->get('/');
    $page = te018Page($response);
    $themeScripts = te018ThemeScripts($page['scripts']);

    expect($page['html']->hasAttribute('data-theme'))->toBeFalse()
        ->and($page['html']->classList->contains('dark'))->toBeFalse()
        ->and($page['meta'])->toBe('light dark')
        ->and($themeScripts)->toHaveCount(1)
        ->and($themeScripts[0]['nonce'] ?? null)->toBe($page['nonce'])
        ->and(strlen($themeScripts[0]['code'] ?? ''))->toBeLessThan(1024);

    $response->assertInertia(fn (Assert $inertia) => $inertia->where('appearance', 'system'));
});

test('AC2 an explicit theme is rendered on <html> with its color-scheme and needs no script', function (string $value, bool $dark) {
    $response = $this->withUnencryptedCookie('appearance', $value)->get('/');
    $page = te018Page($response);

    expect($page['html']->getAttribute('data-theme'))->toBe($value)
        ->and($page['html']->classList->contains('dark'))->toBe($dark)
        ->and($page['meta'])->toBe($value)
        ->and(te018ThemeScripts($page['scripts']))->toBe([]);

    $response->assertInertia(fn (Assert $inertia) => $inertia->where('appearance', $value));
})->with([
    'dark' => ['dark', true],
    'light' => ['light', false],
]);

test('AC2 an explicit system cookie behaves like no cookie', function () {
    $page = te018Page($this->withUnencryptedCookie('appearance', 'system')->get('/'));

    expect($page['html']->hasAttribute('data-theme'))->toBeFalse()
        ->and($page['meta'])->toBe('light dark')
        ->and(te018ThemeScripts($page['scripts']))->toHaveCount(1);
});

test('AC3 any other cookie value is system and never echoed', function (string $value) {
    $response = $this->withUnencryptedCookie('appearance', $value)->get('/');
    $page = te018Page($response);

    expect($page['html']->hasAttribute('data-theme'))->toBeFalse()
        ->and($page['meta'])->toBe('light dark')
        ->and((string) $response->getContent())->not->toContain($value);

    $response->assertInertia(fn (Assert $inertia) => $inertia->where('appearance', 'system'));
})->with([
    'unknown' => ['foo-theme'],
    'upper case' => ['DARK'],
    'script fragment' => ["dark'</script><script>alert(1)</script>"],
    'attribute break' => ['dark" onload="alert(1)'],
    '5 KB' => [str_repeat('darkness', 640)],
]);

test('AC3 an array cookie does not fail the request', function () {
    $response = $this->call('GET', '/', cookies: ['appearance' => ['dark']]);

    expect(te018Page($response)['meta'])->toBe('light dark');
});

test('AC5 the CSP has no unsafe-inline, so the theme script runs only through its nonce', function () {
    expect((string) $this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('unsafe-inline');
});

test('quality the allow-list is the Appearance enum', function () {
    expect(array_map(fn (Appearance $case): string => $case->value, Appearance::cases()))->toBe(['system', 'light', 'dark'])
        ->and(Appearance::fromRequest(Request::create('/', cookies: ['appearance' => 'dark'])))->toBe(Appearance::Dark)
        ->and(Appearance::fromRequest(Request::create('/', cookies: ['appearance' => ' dark'])))->toBe(Appearance::System)
        ->and(Appearance::fromRequest(Request::create('/')))->toBe(Appearance::System)
        ->and(Appearance::Dark->colorScheme())->toBe('dark')
        ->and(Appearance::Light->colorScheme())->toBe('light')
        ->and(Appearance::System->colorScheme())->toBe('light dark');
});
