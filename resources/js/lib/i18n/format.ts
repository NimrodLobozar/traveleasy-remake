import { DEFAULT_TIME_ZONE, intlTag } from './locales';
import type { Locale } from './locales';

/**
 * Locale-aware formatting on top of `Intl` (TE-009 AC7). Everything is a pure function of its
 * arguments: the locale and the time zone are always explicit, never read from the runtime
 * (`Intl.DateTimeFormat().resolvedOptions()`), so the server render and the browser agree.
 */

export type DateInput = Date | string | number;

function toDate(value: DateInput): Date {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        throw new RangeError('formatDate: invalid date');
    }

    return date;
}

/** Calendar date in the given zone, e.g. `3 nov 2026` / `3 Nov 2026`. */
export function formatDate(
    value: DateInput,
    locale: Locale,
    timeZone: string = DEFAULT_TIME_ZONE,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return new Intl.DateTimeFormat(intlTag(locale), {
        ...options,
        timeZone,
    }).format(toDate(value));
}

/**
 * Date and time in the given zone, labelled with the zone's abbreviation (`CET`, `GMT+1`) because
 * a time without its zone is ambiguous for travellers.
 */
export function formatDateTime(
    value: DateInput,
    locale: Locale,
    timeZone: string = DEFAULT_TIME_ZONE,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    },
): string {
    return new Intl.DateTimeFormat(intlTag(locale), {
        ...options,
        timeZone,
        timeZoneName: options.timeZoneName ?? 'short',
    }).format(toDate(value));
}

/**
 * Money is stored as integer cents plus an ISO 4217 code. `1234_50` cents of EUR give
 * `€ 1.234,50` (nl) or `€1,234.50` (en). Currencies without minor units (JPY) are handled by
 * reading the fraction digits from `Intl` instead of assuming 100.
 */
export function formatMoney(
    cents: number,
    currency: string,
    locale: Locale,
): string {
    if (!Number.isInteger(cents)) {
        throw new RangeError('formatMoney: cents must be an integer');
    }

    const formatter = new Intl.NumberFormat(intlTag(locale), {
        style: 'currency',
        currency,
    });
    const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2;

    return formatter.format(cents / 10 ** digits);
}

export function formatNumber(
    value: number,
    locale: Locale,
    options?: Intl.NumberFormatOptions,
): string {
    return new Intl.NumberFormat(intlTag(locale), options).format(value);
}
