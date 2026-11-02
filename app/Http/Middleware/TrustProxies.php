<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Security\TrustedProxies;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

/**
 * Replaces the framework's TrustProxies in the global stack (TE-012 AC12, ADR-0002 D6).
 *
 * - Only the proxies in TRUSTED_PROXIES are trusted; empty = none. Never `*`, and no implicit fallback (the framework
 *   would trust every caller on Laravel Cloud, Forge or Vapor hosts).
 * - Only X-Forwarded-For, -Proto and -Port are honoured. X-Forwarded-Host, -Prefix and the `Forwarded` header are
 *   ignored even from a trusted proxy: NPM passes the original Host header, and the host is the site's identity.
 */
final class TrustProxies extends BaseTrustProxies
{
    private const int TRUSTED_HEADERS = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PORT;

    /** @var int */
    protected $headers = self::TRUSTED_HEADERS;

    /**
     * @param  Request  $request
     */
    protected function setTrustedProxyIpAddresses($request): void
    {
        $proxies = TrustedProxies::fromConfig();

        if ($proxies->isEmpty()) {
            return;
        }

        $request->setTrustedProxies($proxies->addresses, self::TRUSTED_HEADERS);
    }

    /**
     * Fixed: the static TrustProxies::withHeaders() cannot widen the header set.
     */
    protected function headers(): int
    {
        return self::TRUSTED_HEADERS;
    }

    /**
     * Fixed: the static TrustProxies::at() cannot add proxies; only TRUSTED_PROXIES does.
     *
     * @return array<int, string>
     */
    protected function proxies(): array
    {
        return TrustedProxies::fromConfig()->addresses;
    }
}
