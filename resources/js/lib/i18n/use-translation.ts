import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatNumber,
} from './format';
import type { DateInput } from './format';
import { DEFAULT_LOCALE, DEFAULT_TIME_ZONE, isLocale } from './locales';
import type { Locale } from './locales';
import { createTranslator, throwOnMissingKey } from './translate';
import type { Messages, Translator } from './translate';

export type Translation = {
    locale: Locale;
    t: Translator;
    formatDate: (
        value: DateInput,
        timeZone?: string,
        options?: Intl.DateTimeFormatOptions,
    ) => string;
    formatDateTime: (
        value: DateInput,
        timeZone?: string,
        options?: Intl.DateTimeFormatOptions,
    ) => string;
    formatMoney: (cents: number, currency: string) => string;
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
};

/** Tests fail on a missing key (TE-009 AC6); the app shows the key instead. */
const onMissing =
    import.meta.env.MODE === 'test' ? throwOnMissingKey : undefined;

/** Everything a component needs, bound to a locale; pure, so it is SSR-safe. */
export function createTranslation(
    locale: Locale,
    messages: Messages,
): Translation {
    return {
        locale,
        t: createTranslator(messages, locale, onMissing),
        formatDate: (value, timeZone = DEFAULT_TIME_ZONE, options) =>
            formatDate(value, locale, timeZone, options),
        formatDateTime: (value, timeZone = DEFAULT_TIME_ZONE, options) =>
            formatDateTime(value, locale, timeZone, options),
        formatMoney: (cents, currency) => formatMoney(cents, currency, locale),
        formatNumber: (value, options) => formatNumber(value, locale, options),
    };
}

/**
 * `const { t } = useTranslation()`. The locale and the messages come from the Inertia shared
 * props (`locale`, `translations`), which the server sends in the same page object that the
 * browser hydrates from: the same input on both sides, so the same output.
 */
export function useTranslation(): Translation {
    const { locale, translations } = usePage().props;

    // Memoised: callers put `t` in hook dependency lists, so it must stay the same object until
    // the locale or the messages change.
    return useMemo(
        () =>
            createTranslation(
                isLocale(locale) ? locale : DEFAULT_LOCALE,
                translations,
            ),
        [locale, translations],
    );
}
