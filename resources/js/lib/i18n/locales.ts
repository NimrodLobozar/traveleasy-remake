/** Locales the app supports (TE-009). The server allow-lists the same two values. */
export const LOCALES = ['nl', 'en'] as const;

export type Locale = (typeof LOCALES)[number];

export const DEFAULT_LOCALE: Locale = 'nl';

/** Zone used when a caller passes none. Callers should still pass the airport's own zone. */
export const DEFAULT_TIME_ZONE = 'Europe/Amsterdam';

export function isLocale(value: unknown): value is Locale {
    return LOCALES.some((locale) => locale === value);
}

/** BCP 47 tag handed to `Intl`: `nl` formats like the Netherlands, `en` like Europe (24 h, day first). */
const INTL_TAGS: Record<Locale, string> = {
    nl: 'nl-NL',
    en: 'en-GB',
};

export function intlTag(locale: Locale): string {
    return INTL_TAGS[locale];
}
