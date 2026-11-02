<?php

declare(strict_types=1);

use App\Support\Security\TrustedProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/*
| TE-012 AC12 (ADR-0002 D6): X-Forwarded-For, -Proto and -Port are honoured only from the proxies in TRUSTED_PROXIES
| (here 172.18.0.0/16, like the npm_shared network). X-Forwarded-Host, -Prefix and `Forwarded` are never honoured.
| From any other address, every forwarded header is ignored. `*` and other trust-everyone values are rejected.
*/

const PROXY_IP = '172.18.0.2';
const OUTSIDE_IP = '203.0.113.9';

beforeEach(function () {
    config(['security.trusted_proxies' => '172.18.0.0/16, 10.10.10.10', 'app.debug' => false]);

    Route::get('/_test/proxy-echo', fn (Request $request): array => [
        'ip' => $request->ip(),
        'scheme' => $request->getScheme(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'port' => $request->getPort(),
        'base' => $request->getBaseUrl(),
        'url' => url('/trips'),
        'route' => route('home'),
        'asset' => asset('build/assets/app.js'),
    ]);
});

/**
 * @param  array<string, string>  $headers
 * @return TestResponse<Response>
 */
function viaProxy(TestCase $case, string $remoteAddress, array $headers): TestResponse
{
    return $case->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
        ->withHeaders($headers)
        ->getJson('http://localhost/_test/proxy-echo');
}

test('without forwarded headers the request is taken as it arrives', function (string $from) {
    viaProxy($this, $from, [])->assertOk()->assertJson([
        'ip' => $from,
        'scheme' => 'http',
        'secure' => false,
        'host' => 'localhost',
        'port' => 80,
    ]);
})->with([PROXY_IP, OUTSIDE_IP]);

test('X-Forwarded-For sets the client IP only from a trusted proxy', function () {
    viaProxy($this, PROXY_IP, ['X-Forwarded-For' => '198.51.100.7'])->assertJson(['ip' => '198.51.100.7']);
    viaProxy($this, '10.10.10.10', ['X-Forwarded-For' => '198.51.100.7'])->assertJson(['ip' => '198.51.100.7']);
});

test('a spoofed X-Forwarded-For from an untrusted address is ignored', function () {
    viaProxy($this, OUTSIDE_IP, ['X-Forwarded-For' => '172.18.0.2'])->assertJson(['ip' => OUTSIDE_IP]);
    viaProxy($this, OUTSIDE_IP, ['X-Forwarded-For' => '198.51.100.7, 172.18.0.2'])->assertJson(['ip' => OUTSIDE_IP]);
});

test('a client cannot prepend a fake address to the chain the proxy forwards', function () {
    // NPM appends the real peer (198.51.100.7) to whatever the client sent; the rightmost untrusted hop wins.
    viaProxy($this, PROXY_IP, ['X-Forwarded-For' => '1.2.3.4, 198.51.100.7'])->assertJson(['ip' => '198.51.100.7']);
});

test('X-Forwarded-Proto: https from a trusted proxy makes the request, URLs and asset URLs https', function () {
    viaProxy($this, PROXY_IP, ['X-Forwarded-Proto' => 'https'])->assertJson([
        'scheme' => 'https',
        'secure' => true,
        'port' => 443,
        'url' => 'https://localhost/trips',
        'route' => 'https://localhost',
        'asset' => 'https://localhost/build/assets/app.js',
    ]);
});

test('X-Forwarded-Proto from an untrusted address is ignored', function () {
    viaProxy($this, OUTSIDE_IP, ['X-Forwarded-Proto' => 'https'])->assertJson([
        'scheme' => 'http',
        'secure' => false,
        'port' => 80,
        'url' => 'http://localhost/trips',
        'asset' => 'http://localhost/build/assets/app.js',
    ]);
});

test('X-Forwarded-Port sets the port only from a trusted proxy', function () {
    viaProxy($this, PROXY_IP, ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '8443'])
        ->assertJson(['port' => 8443, 'url' => 'https://localhost:8443/trips']);
    viaProxy($this, OUTSIDE_IP, ['X-Forwarded-Port' => '8443'])->assertJson(['port' => 80, 'url' => 'http://localhost/trips']);
});

test('X-Forwarded-Host, X-Forwarded-Prefix and Forwarded are ignored even from a trusted proxy', function (string $from) {
    viaProxy($this, $from, [
        'X-Forwarded-Host' => 'evil.example',
        'X-Forwarded-Prefix' => '/evil',
        'Forwarded' => 'for=198.51.100.66;proto=https;host=evil.example',
    ])->assertJson([
        'ip' => $from,
        'scheme' => 'http',
        'host' => 'localhost',
        'base' => '',
        'url' => 'http://localhost/trips',
    ]);
})->with([PROXY_IP, OUTSIDE_IP]);

test('with TRUSTED_PROXIES empty no proxy is trusted', function () {
    config(['security.trusted_proxies' => '']);

    viaProxy($this, PROXY_IP, ['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https'])
        ->assertJson(['ip' => PROXY_IP, 'scheme' => 'http']);
});

test('framework hosts that would trust everyone get no special treatment', function () {
    config(['security.trusted_proxies' => '']);

    $this->withServerVariables(['REMOTE_ADDR' => OUTSIDE_IP])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https'])
        ->getJson('http://x.on-forge.com/_test/proxy-echo')
        ->assertJson(['ip' => OUTSIDE_IP, 'scheme' => 'http']);
});

test('a wildcard TRUSTED_PROXIES makes the app refuse to serve instead of trusting everyone', function (string $value) {
    config(['security.trusted_proxies' => $value]);

    $response = viaProxy($this, OUTSIDE_IP, ['X-Forwarded-For' => '198.51.100.7']);

    $response->assertStatus(500);
    expect((string) $response->getContent())->not->toContain('198.51.100.7')->not->toContain('TRUSTED_PROXIES');
})->with(['*', '**', 'REMOTE_ADDR', '0.0.0.0/0', '::/0']);

test('the parser accepts IPv4 and IPv6 addresses and CIDR ranges', function () {
    expect(TrustedProxies::parse(' 172.18.0.0/16 ,10.0.0.1,, fd00::/8, ::1 ')->addresses)
        ->toBe(['172.18.0.0/16', '10.0.0.1', 'fd00::/8', '::1'])
        ->and(TrustedProxies::parse('')->isEmpty())->toBeTrue();
});

test('the parser rejects anything that is not a specific address or range', function (string $value) {
    expect(fn () => TrustedProxies::parse($value))->toThrow(InvalidArgumentException::class, 'TRUSTED_PROXIES contains');
})->with([
    '*', '**', 'REMOTE_ADDR', '0.0.0.0/0', '::/0', '10.0.0.0/33', 'fd00::/129', '10.0.0.0/', '10.0.0.0/x',
    '10.0.0.0/8/8', 'npm', 'nginx-proxy-manager', '10.0.0', '10.0.0.0/-1',
]);
