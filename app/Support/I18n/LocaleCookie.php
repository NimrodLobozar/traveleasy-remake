<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The `locale` preference cookie set by the language switch (TE-017 AC4).
 *
 * First-party and host-only, one year, `SameSite=Lax`, `HttpOnly` (React reads the locale from the Inertia props,
 * never from the cookie), `Secure` when the request came in over HTTPS. It holds only `nl` or `en`, no personal
 * data, and is not encrypted (bootstrap/app.php), so LocaleResolver reads it as-is.
 */
final class LocaleCookie
{
    public const int LIFETIME_MINUTES = 60 * 24 * 365;

    public static function make(Locale $locale, Request $request): Cookie
    {
        return Cookie::create(
            name: LocaleResolver::COOKIE,
            value: $locale->value,
            expire: now()->addMinutes(self::LIFETIME_MINUTES),
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
