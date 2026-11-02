<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * Picks the UI locale of a request (TE-009, docs/plan.md § 7 "i18n").
 *
 * Chain, first match wins: the `locale` cookie, then `Accept-Language`, then the configured default (nl).
 * Every input is checked against the allow-list (the Locale enum); an unknown or malformed value is ignored
 * and the chain continues. Values are never echoed or logged.
 *
 * M1 adds the user's saved preference as the first step of the chain in resolve().
 */
final class LocaleResolver
{
    /** Name of the first-party locale cookie, set by the language switch (TE-016). Not encrypted (bootstrap/app.php). */
    public const string COOKIE = 'locale';

    public function resolve(Request $request): Locale
    {
        return $this->fromCookie($request)
            ?? $this->fromAcceptLanguage($request)
            ?? Locale::default();
    }

    private function fromCookie(Request $request): ?Locale
    {
        // Read the raw bag: a crafted `locale[]=…` cookie is an array, which InputBag::get() would reject with a 400.
        $value = $request->cookies->all()[self::COOKIE] ?? null;

        return is_string($value) ? Locale::tryFrom($value) : null;
    }

    private function fromAcceptLanguage(Request $request): ?Locale
    {
        $header = $request->headers->get('Accept-Language');

        if ($header === null || $header === '') {
            return null;
        }

        // all() is sorted by quality, highest first; equal qualities keep the header's order.
        foreach (AcceptHeader::fromString($header)->all() as $item) {
            if ($item->getQuality() <= 0) {
                continue;
            }

            // `en-GB` and `en_GB` both count as `en`; `*` and unknown languages match nothing.
            $primary = strtolower(explode('-', str_replace('_', '-', $item->getValue()), 2)[0]);
            $locale = Locale::tryFrom($primary);

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }
}
