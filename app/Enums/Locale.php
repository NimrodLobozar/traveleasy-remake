<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The UI locales: the allow-list for every locale input (cookie, Accept-Language, later the user preference).
 *
 * TE-009. Exported to TypeScript as `Locale` (`npm run generate`). Adding a locale means adding a case here and a
 * `lang/<code>.json` file with every key.
 */
enum Locale: string
{
    case Nl = 'nl';
    case En = 'en';

    /**
     * The configured default locale (`APP_LOCALE`); `nl` when the configured value isn't on the allow-list.
     */
    public static function default(): self
    {
        return self::tryFrom(config()->string('app.locale')) ?? self::Nl;
    }

    /**
     * The configured fallback locale (`APP_FALLBACK_LOCALE`); `en` when the configured value isn't on the allow-list.
     */
    public static function fallback(): self
    {
        return self::tryFrom(config()->string('app.fallback_locale')) ?? self::En;
    }

    /**
     * The locale the application runs in for this request (set by the SetLocale middleware).
     */
    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::default();
    }
}
