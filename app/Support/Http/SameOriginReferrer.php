<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * The page a form post came from, as a same-origin path, for "redirect back" without an open redirect (TE-017).
 *
 * Laravel's `back()` and `url()->previous()` trust the `Referer` header as-is, so a crafted cross-site post would
 * bounce the visitor to any site. This only returns the path and query of a referrer with the request's own scheme,
 * host and port; anything else (another origin, credentials in the URL, `//host`, backslashes, control characters,
 * an overlong header) gives the fallback path. The result always starts with exactly one `/`.
 */
final class SameOriginReferrer
{
    private const int MAX_LENGTH = 2048;

    /** A path or query made of visible ASCII only: no spaces, control characters or backslashes. */
    private const string SAFE_PART = '#\A[\x21-\x5B\x5D-\x7E]*\z#';

    /**
     * @param  list<string>  $excludedPaths  paths that never count as "the page" (e.g. the endpoint itself)
     */
    public function path(Request $request, string $fallback = '/', array $excludedPaths = []): string
    {
        $referrer = $request->headers->get('referer');

        if ($referrer === null || $referrer === '' || strlen($referrer) > self::MAX_LENGTH) {
            return $fallback;
        }

        $parts = parse_url($referrer);

        if ($parts === false || ! $this->sameOrigin($parts, $request) || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? null;

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')
            || preg_match(self::SAFE_PART, $path) !== 1
            || ($query !== null && preg_match(self::SAFE_PART, $query) !== 1)
            || in_array(rtrim($path, '/'), $excludedPaths, true)) {
            return $fallback;
        }

        return $query === null || $query === '' ? $path : "{$path}?{$query}";
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function sameOrigin(array $parts, Request $request): bool
    {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return $scheme === $request->getScheme()
            && $host === strtolower($request->getHost())
            && $port === (int) $request->getPort();
    }
}
