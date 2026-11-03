<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\SharedUserData;
use App\Enums\Appearance;
use App\Enums\Locale;
use App\Models\User;
use App\Support\I18n\Translations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = Locale::current();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Never share the Eloquent model itself: only the whitelisted fields in SharedUserData.
                'user' => $user instanceof User ? SharedUserData::fromModel($user) : null,
            ],
            // TE-009 shared-prop contract (docs/design/i18n.md): the resolved locale and its flat key => string
            // map, already merged over the fallback locale. Only the active locale's strings are sent.
            'locale' => $locale->value,
            'fallbackLocale' => Locale::fallback()->value,
            'translations' => fn (): array => app(Translations::class)->for($locale),
            // TE-018: the allow-listed theme choice, so SSR renders the theme control in the right state.
            'appearance' => Appearance::fromRequest($request)->value,
            // TE-017: the session's CSRF token, for plain (no-JavaScript) form posts such as the language switch.
            // Inertia's own requests send the XSRF-TOKEN cookie instead. Null when there is no session.
            'csrfToken' => fn (): ?string => $request->hasSession() ? $request->session()->token() : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
