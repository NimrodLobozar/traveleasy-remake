import { describe, expect, it } from 'vite-plus/test';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatNumber,
} from './format';

// Intl puts a no-break space between the symbol and the amount in nl; compare with a normal space.
const plain = (value: string) => value.replace(/[  ]/g, ' ');

describe('formatMoney', () => {
    it('formats integer cents per locale (AC7)', () => {
        expect(plain(formatMoney(123450, 'EUR', 'nl'))).toBe('€ 1.234,50');
        expect(plain(formatMoney(123450, 'EUR', 'en'))).toBe('€1,234.50');
    });

    it('handles zero, negative and currencies without minor units', () => {
        expect(plain(formatMoney(0, 'EUR', 'en'))).toBe('€0.00');
        expect(plain(formatMoney(-5, 'EUR', 'en'))).toBe('-€0.05');
        expect(plain(formatMoney(1500, 'JPY', 'en'))).toBe('JP¥1,500');
    });

    it('rejects fractional cents', () => {
        expect(() => formatMoney(10.5, 'EUR', 'nl')).toThrow(RangeError);
    });
});

describe('formatNumber', () => {
    it('uses the locale separators', () => {
        expect(plain(formatNumber(1234567.891, 'nl'))).toBe('1.234.567,891');
        expect(plain(formatNumber(1234567.891, 'en'))).toBe('1,234,567.891');
    });
});

describe('formatDate and formatDateTime', () => {
    const departure = '2026-03-29T00:30:00Z';

    it('formats a date per locale in the given zone', () => {
        expect(formatDate(departure, 'nl', 'Europe/Amsterdam')).toBe(
            '29 mrt 2026',
        );
        expect(formatDate(departure, 'en', 'Europe/Amsterdam')).toBe(
            '29 Mar 2026',
        );
    });

    it('uses the explicit zone, not the runtime zone (day boundary)', () => {
        const late = '2026-06-30T23:30:00Z';

        expect(formatDate(late, 'en', 'Europe/Amsterdam')).toBe('1 Jul 2026');
        expect(formatDate(late, 'en', 'America/New_York')).toBe('30 Jun 2026');
    });

    it('labels the time with its zone and follows DST (spring forward)', () => {
        // 2026-03-29 01:00 UTC is the instant Amsterdam moves from CET to CEST.
        const before = formatDateTime(
            '2026-03-29T00:59:00Z',
            'en',
            'Europe/Amsterdam',
        );
        const after = formatDateTime(
            '2026-03-29T01:00:00Z',
            'en',
            'Europe/Amsterdam',
        );

        expect(before).toContain('01:59');
        expect(before).toMatch(/CET/);
        expect(after).toContain('03:00');
        expect(after).toMatch(/CEST/);
    });

    it('shows the same instant in the airport zone', () => {
        const text = formatDateTime('2026-11-02T12:00:00Z', 'nl', 'Asia/Tokyo');

        expect(text).toContain('21:00');
    });

    it('accepts a Date and a timestamp, and rejects garbage', () => {
        const date = new Date(departure);

        expect(formatDate(date, 'en')).toBe(formatDate(date.getTime(), 'en'));
        expect(() => formatDate('not a date', 'en')).toThrow(RangeError);
    });
});
