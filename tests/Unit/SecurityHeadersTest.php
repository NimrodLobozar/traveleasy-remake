<?php

declare(strict_types=1);

use App\Support\Security\SecurityHeaders;

/*
| TE-015 security headers: unit test of the header builder (no app, no HTTP).
| AC1 CSP directives, AC4 other headers, AC5 HSTS, AC6 Vite dev origins, AC7 reporting.
| Source: docs/plan.md § 4 M0 App shell; legacy § 3 Security (no security headers).
*/

const TE015_NONCE = 'dGUwMTUtdW5pdC1ub25jZQ==';

/**
 * @return array<string, string> directive name => source list
 */
function te015Directives(string $policy): array
{
    $directives = [];

    foreach (explode(';', $policy) as $directive) {
        $parts = explode(' ', trim($directive), 2);
        $directives[$parts[0]] = $parts[1] ?? '';
    }

    return $directives;
}

test('AC1 the CSP has exactly the baseline directives', function () {
    $policy = (new SecurityHeaders(https: false, allowViteDevServer: false))->contentSecurityPolicy(TE015_NONCE);

    expect(te015Directives($policy))->toBe([
        'default-src' => "'self'",
        'script-src' => "'self' 'nonce-".TE015_NONCE."'",
        'style-src' => "'self' 'nonce-".TE015_NONCE."'",
        'img-src' => "'self' data:",
        'font-src' => "'self'",
        'connect-src' => "'self'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
    ]);

    expect($policy)->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval')
        ->not->toContain('http')
        ->not->toContain('*');
});

test('AC2 nonces are 128-bit, base64 and different every time', function () {
    $nonces = array_map(fn () => SecurityHeaders::nonce(), range(1, 50));

    expect(array_unique($nonces))->toHaveCount(50);

    foreach ($nonces as $nonce) {
        expect($nonce)->toMatch('#\A[A-Za-z0-9+/]{22}==\z#')
            ->and(strlen((string) base64_decode($nonce, true)))->toBe(16);
    }
});

test('AC4 the other security headers are set', function () {
    $headers = (new SecurityHeaders(https: false, allowViteDevServer: false))->headers(TE015_NONCE);

    expect($headers)->toMatchArray([
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ])->and($headers)->toHaveKey('Content-Security-Policy');
});

test('AC5 HSTS only over https, 180 days, no preload or subdomains by default', function () {
    expect((new SecurityHeaders(https: false, allowViteDevServer: false))->headers(TE015_NONCE))
        ->not->toHaveKey('Strict-Transport-Security');

    expect((new SecurityHeaders(https: true, allowViteDevServer: false))->headers(TE015_NONCE))
        ->toHaveKey('Strict-Transport-Security', 'max-age=15552000');
});

test('AC5 the HSTS switches add includeSubDomains, preload and a longer max-age', function () {
    $headers = (new SecurityHeaders(
        https: true,
        allowViteDevServer: false,
        hstsMaxAge: 63072000,
        hstsIncludeSubdomains: true,
        hstsPreload: true,
    ))->headers(TE015_NONCE);

    expect($headers['Strict-Transport-Security'])->toBe('max-age=63072000; includeSubDomains; preload');
});

test('AC6 the Vite dev server origin is added only when allowed, and nothing else', function () {
    $dev = te015Directives((new SecurityHeaders(https: false, allowViteDevServer: true, viteDevPort: 5173))
        ->contentSecurityPolicy(TE015_NONCE));

    expect($dev['script-src'])->toBe("'self' 'nonce-".TE015_NONCE."' http://localhost:5173")
        ->and($dev['style-src'])->toBe("'self' 'nonce-".TE015_NONCE."' http://localhost:5173")
        ->and($dev['connect-src'])->toBe("'self' http://localhost:5173 ws://localhost:5173")
        ->and($dev['img-src'])->toBe("'self' data: http://localhost:5173")
        ->and($dev['font-src'])->toBe("'self' http://localhost:5173")
        ->and($dev['default-src'])->toBe("'self'");

    $prod = (new SecurityHeaders(https: true, allowViteDevServer: false))->contentSecurityPolicy(TE015_NONCE);
    expect($prod)->not->toContain('localhost')->not->toContain('ws:');
});

test('AC6 an out-of-range Vite port adds nothing', function (int $port) {
    $policy = (new SecurityHeaders(https: false, allowViteDevServer: true, viteDevPort: $port))
        ->contentSecurityPolicy(TE015_NONCE);

    expect($policy)->not->toContain('localhost');
})->with([0, -1, 70000]);

test('AC7 no reporting endpoint by default', function () {
    $policy = (new SecurityHeaders(https: true, allowViteDevServer: false))->contentSecurityPolicy(TE015_NONCE);

    expect($policy)->not->toContain('report-uri')->not->toContain('report-to');
});

test('AC7 a same-origin report path is used', function () {
    $policy = (new SecurityHeaders(https: true, allowViteDevServer: false, reportPath: '/csp-report'))
        ->contentSecurityPolicy(TE015_NONCE);

    expect(te015Directives($policy))->toHaveKey('report-uri', '/csp-report');
});

test('AC7 a report target on another host, or anything not a plain path, is ignored', function (string $path) {
    $policy = (new SecurityHeaders(https: true, allowViteDevServer: false, reportPath: $path))
        ->contentSecurityPolicy(TE015_NONCE);

    expect($policy)->not->toContain('report-uri');
})->with([
    'absolute URL' => ['https://reports.example.com/csp'],
    'protocol-relative' => ['//reports.example.com/csp'],
    'no leading slash' => ['csp-report'],
    'directive injection' => ['/r; script-src *'],
    'space' => ['/csp report'],
    'scheme' => ['javascript:alert(1)'],
    'backslash host' => ['/\\reports.example.com'],
    'empty' => [''],
]);
