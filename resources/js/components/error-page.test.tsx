import { render, screen } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import ErrorPage, { ERROR_STATUSES } from './error-page';

const lang = {
    nl: JSON.parse(readFileSync('lang/nl.json', 'utf8')) as Record<
        string,
        string
    >,
    en: JSON.parse(readFileSync('lang/en.json', 'utf8')) as Record<
        string,
        string
    >,
};
const state = vi.hoisted(() => ({ locale: 'nl' as 'nl' | 'en' }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: state.locale,
            translations: state.locale === 'nl' ? lang.nl : lang.en,
        },
    }),
    Head: () => null,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: unknown;
        children: ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : '/'} {...rest}>
            {children}
        </a>
    ),
}));

const titles = {
    nl: {
        403: 'Geen toegang',
        404: 'Deze pagina bestaat niet',
        419: 'Sessie verlopen, probeer opnieuw',
        429: 'Te veel verzoeken',
        500: 'Er ging iets mis',
        503: 'We zijn zo terug',
    },
    en: {
        403: 'Access denied',
        404: 'This page does not exist',
        419: 'Session expired, please try again',
        429: 'Too many requests',
        500: 'Something went wrong',
        503: "We'll be right back",
    },
} as const;

describe('ErrorPage (TE-019)', () => {
    for (const locale of ['nl', 'en'] as const) {
        it.each(ERROR_STATUSES)(
            `renders status %s in ${locale} with one h1 that has focus (AC2, AC7)`,
            (status) => {
                state.locale = locale;
                render(<ErrorPage status={status} requestId="3f8a9c" />);

                const heading = screen.getByRole('heading', { level: 1 });
                expect(heading).toHaveTextContent(titles[locale][status]);
                expect(heading).toHaveFocus();
                expect(screen.getAllByRole('heading')).toHaveLength(1);
            },
        );
    }

    it('shows the request id as selectable text (AC3)', () => {
        state.locale = 'nl';
        render(<ErrorPage status={500} requestId="3f8a9c" />);

        expect(screen.getByText('Referentie: 3f8a9c')).toBeVisible();
    });

    it('omits the reference when there is no request id', () => {
        state.locale = 'en';
        render(<ErrorPage status={500} />);

        expect(screen.queryByText(/Reference/)).toBeNull();
    });

    it('shows the Retry-After wait for 429 only (AC2)', () => {
        state.locale = 'en';
        const { rerender } = render(<ErrorPage status={429} retryAfter={30} />);
        expect(screen.getByText(/Try again in 30 seconds/)).toBeVisible();

        rerender(<ErrorPage status={500} retryAfter={30} />);
        expect(screen.queryByText(/Try again in/)).toBeNull();
    });

    it('offers a reload action for 419 and a search link for 404', () => {
        state.locale = 'en';
        const { rerender } = render(<ErrorPage status={419} />);
        expect(
            screen.getByRole('button', { name: 'Reload the page' }),
        ).toBeVisible();

        rerender(<ErrorPage status={404} />);
        expect(
            screen.getByRole('link', { name: 'Search trips' }),
        ).toHaveAttribute('href', '/search');
        expect(
            screen.getByRole('link', { name: 'Go to the home page' }),
        ).toBeVisible();
    });

    it('treats an unknown status as the 500 page', () => {
        state.locale = 'en';
        render(<ErrorPage status={418} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Something went wrong',
        );
    });

    it('has no axe violations', async () => {
        state.locale = 'nl';
        const { container } = render(
            <ErrorPage status={429} retryAfter={5} requestId="abc" />,
        );

        await expect(container).toHaveNoA11yViolations();
    });
});
