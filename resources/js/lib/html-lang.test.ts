import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { syncHtmlLang } from './html-lang';

const handlers = vi.hoisted(() => new Map<string, (event: unknown) => void>());

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (name: string, handler: (event: unknown) => void) => {
            handlers.set(name, handler);

            return () => undefined;
        },
    },
}));

const visit = (name: 'success' | 'navigate', locale: unknown) => {
    handlers.get(name)?.({ detail: { page: { props: { locale } } } });
};

describe('syncHtmlLang (TE-017 AC2)', () => {
    beforeEach(() => {
        document.documentElement.lang = 'nl';
        syncHtmlLang();
    });

    it.each(['success', 'navigate'] as const)(
        'sets <html lang> to the new locale after a %s event',
        (name) => {
            visit(name, 'en');

            expect(document.documentElement.lang).toBe('en');
        },
    );

    it('ignores a value that is not a supported locale', () => {
        visit('success', 'fr');

        expect(document.documentElement.lang).toBe('nl');
    });
});
