<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Ssr\SsrRenderFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->disableOutgoingMail();
    }

    /**
     * ADR-0005: the app sends no e-mail. Laravel merges the framework's default
     * mailers (smtp, ses, ...) into config/mail.php, so pin the mail config here
     * to the `array` mailer only, regardless of env or cached config.
     */
    protected function disableOutgoingMail(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers' => ['array' => ['transport' => 'array']],
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureInertiaSsr();
    }

    /**
     * TE-007: server-side rendering of the first page visit (settings in config/inertia.php).
     *
     * - While the Vite dev server runs (public/hot exists), skip SSR: dev stays client-side with
     *   HMR, and SSR is checked against a build (TE-008). Without this, every dev request would
     *   try the hot SSR endpoint, which the app container can't reach, and log a failure.
     * - A failed render falls back to client-side rendering (config `throw_on_error` is false). The
     *   failure is logged without personal data: never the page props, the URL (it can carry
     *   identifiers or a query string), the error message or the stack, only what locates the bug.
     */
    protected function configureInertiaSsr(): void
    {
        Inertia::disableSsr(fn (): bool => ! config()->boolean('inertia.ssr.enabled') || Vite::isRunningHot());

        Event::listen(function (SsrRenderFailed $event): void {
            Log::warning('Inertia SSR render failed; the page was served for client-side rendering.', [
                'type' => $event->type->value,
                'component' => $event->component(),
                'browser_api' => $event->browserApi,
                'source_location' => $event->sourceLocation,
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Lazy loading, silently discarded attributes and missing attributes throw outside production.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
