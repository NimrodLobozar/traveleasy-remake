import { describe, expect, it, vi } from 'vite-plus/test';
import { createTranslator, interpolate, throwOnMissingKey } from './translate';

const messages = {
    'welcome.title': 'Hallo',
    'cart.items': ':count items voor :name',
    'literal.brace': 'Gebruik :niet hier',
};

describe('createTranslator', () => {
    it('returns the message for a key', () => {
        const t = createTranslator(messages, 'nl');

        expect(t('welcome.title')).toBe('Hallo');
    });

    it('fills named placeholders (AC5)', () => {
        const t = createTranslator(messages, 'nl');

        expect(t('cart.items', { count: 2, name: 'Sam' })).toBe(
            '2 items voor Sam',
        );
        expect(t('literal.brace', { other: 'x' })).toBe('Gebruik :niet hier');
    });

    it('is deterministic: same input, same output (server vs browser)', () => {
        const server = createTranslator(messages, 'nl');
        const browser = createTranslator({ ...messages }, 'nl');

        expect(server('cart.items', { count: 1, name: 'A' })).toBe(
            browser('cart.items', { count: 1, name: 'A' }),
        );
    });

    it('returns the key and never throws for a missing key outside tests (AC6)', () => {
        const t = createTranslator(messages, 'nl');

        expect(t('does.not.exist')).toBe('does.not.exist');
        expect(t('toString')).toBe('toString');
    });

    it('reports a missing key to the handler, which can fail the test (AC6)', () => {
        const onMissing = vi.fn();

        createTranslator(messages, 'en', onMissing)('nope');

        expect(onMissing).toHaveBeenCalledWith('nope', 'en');
        expect(() =>
            createTranslator(messages, 'en', throwOnMissingKey)('nope'),
        ).toThrow(/Missing translation key "nope"/);
    });
});

describe('interpolate (AC9)', () => {
    it('supports the :Name and :NAME casing variants and leaves clock times alone', () => {
        expect(interpolate(':name, :Name, :NAME', { name: 'sam' })).toBe(
            'sam, Sam, SAM',
        );
        expect(interpolate('Vertrek 10:30 uur', { name: 'x' })).toBe(
            'Vertrek 10:30 uur',
        );
    });

    it('keeps markup in values as plain text, for React to escape', () => {
        const text = interpolate('Hi :name', {
            name: '<img src=x onerror=alert(1)>',
        });

        expect(text).toBe('Hi <img src=x onerror=alert(1)>');
    });

    it('does not expand placeholders found inside a value', () => {
        expect(interpolate(':a :b', { a: ':b', b: 'X' })).toBe(':b X');
    });

    it('ignores inherited property names', () => {
        expect(interpolate(':constructor', {})).toBe(':constructor');
    });
});
