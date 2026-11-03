<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Support\Http\SameOriginReferrer;
use App\Support\I18n\LocaleCookie;
use Illuminate\Http\RedirectResponse;

/**
 * The language switch (TE-017): `POST /locale` stores the choice in the `locale` cookie and goes back to the page.
 *
 * CSRF-protected (web group) and throttled per IP (`throttle:locale`, AppServiceProvider). Works as a plain form
 * post without JavaScript and as an Inertia visit; the next request resolves the new locale (LocaleResolver).
 */
final class LocaleController extends Controller
{
    public const string PATH = '/locale';

    public function __invoke(UpdateLocaleRequest $request, SameOriginReferrer $referrer): RedirectResponse
    {
        return redirect()
            ->to($referrer->path($request, excludedPaths: [self::PATH]))
            ->withCookie(LocaleCookie::make($request->locale(), $request));
    }
}
