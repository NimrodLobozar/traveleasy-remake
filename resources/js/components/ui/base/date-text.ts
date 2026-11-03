import type { Locale } from '@/lib/i18n/locales';

/**
 * Pure date-text helpers for the DatePicker (TE-023, system.md 4.6). A date is a plain
 * year/month/day and an ISO `yyyy-mm-dd` string, never a `Date` and never a time zone, so a DST
 * change or the server's zone cannot move it by a day. No browser globals: safe during SSR.
 */
export type Ymd = { year: number; month: number; day: number };

export type ParsedDate =
    | { status: 'empty' }
    | { status: 'invalid' }
    | { status: 'ok'; date: Ymd };

export function daysInMonth(year: number, month: number): number {
    // Day 0 of the next month is the last day of this one; UTC so no zone or DST is involved.
    return new Date(Date.UTC(year, month, 0)).getUTCDate();
}

export function isValidYmd({ year, month, day }: Ymd): boolean {
    return (
        Number.isInteger(year) &&
        year >= 1000 &&
        year <= 9999 &&
        month >= 1 &&
        month <= 12 &&
        day >= 1 &&
        day <= daysInMonth(year, month)
    );
}

const pad = (value: number, length = 2) => String(value).padStart(length, '0');

export function toIso({ year, month, day }: Ymd): string {
    return `${pad(year, 4)}-${pad(month)}-${pad(day)}`;
}

export function fromIso(iso: string | null | undefined): Ymd | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso ?? '');

    if (match === null) {
        return null;
    }
    const date = {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };

    return isValidYmd(date) ? date : null;
}

/** nl `12-06-2027`, en `12/06/2027`: the format the field shows and asks for. */
export function formatDateText(date: Ymd, locale: Locale): string {
    const separator = locale === 'nl' ? '-' : '/';

    return [pad(date.day), pad(date.month), pad(date.year, 4)].join(separator);
}

/**
 * Parses what a person typed. Accepts day-month-year with `-`, `/` or `.` (one or two digits for
 * day and month, four for the year) and ISO `yyyy-mm-dd`, in both locales. 29 February only exists
 * in leap years; two-digit years are not guessed.
 */
export function parseDateText(raw: string): ParsedDate {
    const text = raw.trim();

    if (text === '') {
        return { status: 'empty' };
    }
    const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(text);
    const local = /^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/.exec(text);
    const date: Ymd | null = iso
        ? { year: Number(iso[1]), month: Number(iso[2]), day: Number(iso[3]) }
        : local
          ? {
                year: Number(local[3]),
                month: Number(local[2]),
                day: Number(local[1]),
            }
          : null;

    return date !== null && isValidYmd(date)
        ? { status: 'ok', date }
        : { status: 'invalid' };
}

/** Negative, zero or positive like a comparator, on whole days. */
export function compareYmd(a: Ymd, b: Ymd): number {
    return a.year - b.year || a.month - b.month || a.day - b.day;
}

/** Noon UTC of the day, for `Intl` formatting in `UTC` (no day shift in any zone). */
export function toUtcNoon({ year, month, day }: Ymd): Date {
    return new Date(Date.UTC(year, month - 1, day, 12));
}
