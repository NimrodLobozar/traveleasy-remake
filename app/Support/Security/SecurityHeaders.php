<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Builds the security headers of every response (TE-015, docs/plan.md § 4 M0 App shell).
 *
 * The CSP allows only this site ('self') plus a per-request nonce for the few inline <script>/<style> tags: no
 * 'unsafe-inline', no 'unsafe-eval', no external origin. The one exception is the Vite dev server on localhost,
 * added only in the local environment. No report endpoint unless a same-origin path is configured.
 */
final readonly class SecurityHeaders
{
    /** A same-origin path: one leading slash (never `//host`), then URL-safe path characters only. */
    private const string REPORT_PATH_PATTERN = '#\A/(?!/)[A-Za-z0-9._/-]*\z#';

    public function __construct(
        private bool $https,
        private bool $allowViteDevServer,
        private int $viteDevPort = 5173,
        private int $hstsMaxAge = 15552000,
        private bool $hstsIncludeSubdomains = false,
        private bool $hstsPreload = false,
        private ?string $reportPath = null,
    ) {}

    public static function fromConfig(): self
    {
        $reportPath = config('security.csp.report_path');

        return new self(
            https: str_starts_with(strtolower(config()->string('app.url')), 'https://'),
            allowViteDevServer: app()->environment('local'),
            viteDevPort: config()->integer('security.csp.vite_dev_port'),
            hstsMaxAge: config()->integer('security.hsts.max_age'),
            hstsIncludeSubdomains: config()->boolean('security.hsts.include_subdomains'),
            hstsPreload: config()->boolean('security.hsts.preload'),
            reportPath: is_string($reportPath) ? $reportPath : null,
        );
    }

    /**
     * A fresh 128-bit nonce, base64-encoded (a CSP `nonce-source`).
     */
    public static function nonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $nonce): array
    {
        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($this->https) {
            $headers['Strict-Transport-Security'] = $this->strictTransportSecurity();
        }

        return $headers;
    }

    public function contentSecurityPolicy(string $nonce): string
    {
        [$viteHttp, $viteWs] = $this->viteDevOrigins();
        $nonceSource = "'nonce-{$nonce}'";

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", $nonceSource, ...$viteHttp],
            'style-src' => ["'self'", $nonceSource, ...$viteHttp],
            'img-src' => ["'self'", 'data:', ...$viteHttp],
            'font-src' => ["'self'", ...$viteHttp],
            'connect-src' => ["'self'", ...$viteHttp, ...$viteWs],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        $reportPath = $this->reportPath();

        if ($reportPath !== null) {
            $directives['report-uri'] = [$reportPath];
        }

        $policy = [];

        foreach ($directives as $name => $sources) {
            $policy[] = $name.' '.implode(' ', $sources);
        }

        return implode('; ', $policy);
    }

    private function strictTransportSecurity(): string
    {
        $value = 'max-age='.max(0, $this->hstsMaxAge);

        if ($this->hstsIncludeSubdomains) {
            $value .= '; includeSubDomains';
        }

        if ($this->hstsPreload) {
            $value .= '; preload';
        }

        return $value;
    }

    /**
     * @return array{0: list<string>, 1: list<string>} the http and ws origins of the Vite dev server, or none
     */
    private function viteDevOrigins(): array
    {
        if (! $this->allowViteDevServer || $this->viteDevPort < 1 || $this->viteDevPort > 65535) {
            return [[], []];
        }

        return [["http://localhost:{$this->viteDevPort}"], ["ws://localhost:{$this->viteDevPort}"]];
    }

    private function reportPath(): ?string
    {
        if ($this->reportPath === null || preg_match(self::REPORT_PATH_PATTERN, $this->reportPath) !== 1) {
            return null;
        }

        return $this->reportPath;
    }
}
