// @vitest-environment node
import type { ReactElement } from 'react';
import { renderToString } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';
import { useTranslation } from './use-translation';

let props: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props }) }));

const render = (node: ReactElement) =>
    renderToString(node).replaceAll('<!-- -->', '');

function Greeting({ name }: { name: string }) {
    const { t, formatMoney } = useTranslation();

    return (
        <p>
            {t('greeting', { name })} {formatMoney(1999, 'EUR')}
        </p>
    );
}

describe('useTranslation', () => {
    it('renders the active locale from the shared props, on the server too (AC5)', () => {
        props = { locale: 'en', translations: { greeting: 'Hello :name' } };

        expect(render(<Greeting name="Sam" />)).toContain('Hello Sam €19.99');

        props = { locale: 'nl', translations: { greeting: 'Hallo :name' } };

        expect(render(<Greeting name="Sam" />)).toMatch(
            /Hallo Sam €[\s ]19,99/,
        );
    });

    it('escapes interpolated values in the markup (AC9)', () => {
        props = { locale: 'nl', translations: { greeting: 'Hallo :name' } };

        const html = renderToString(
            <Greeting name="<script>alert(1)</script>" />,
        );

        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
    });

    it('falls back to nl for an unknown locale value', () => {
        props = { locale: 'xx', translations: { greeting: 'Hallo :name' } };

        expect(render(<Greeting name="Sam" />)).toMatch(/€[\s ]19,99/);
    });
});
