import { fireEvent, render, screen } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import ShellLayout from './shell-layout';

const nl = JSON.parse(readFileSync('lang/nl.json', 'utf8')) as Record<
    string,
    string
>;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: { locale: 'nl', translations: nl },
        url: '/',
    }),
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: unknown;
        children: ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : '/x'} {...rest}>
            {children}
        </a>
    ),
    router: { on: () => () => undefined },
}));

function renderShell() {
    return render(
        <ShellLayout>
            <h1>Titel</h1>
        </ShellLayout>,
    );
}

describe('ShellLayout (TE-016)', () => {
    it('has one banner, main, contentinfo and uniquely named navs (AC1)', () => {
        renderShell();

        expect(screen.getAllByRole('banner')).toHaveLength(1);
        expect(screen.getAllByRole('contentinfo')).toHaveLength(1);
        const main = screen.getByRole('main');
        expect(main).toHaveAttribute('id', 'main');
        expect(main).toHaveAttribute('tabindex', '-1');
        const names = screen
            .getAllByRole('navigation', { hidden: true })
            .map((nav) => nav.getAttribute('aria-label'));
        expect(names).toEqual(['Hoofdmenu', 'Service', 'Juridisch']);
    });

    it('puts the skip link first in the DOM, pointing at main (AC2)', () => {
        const { container } = renderShell();
        const first = container.querySelector('a');

        expect(first).toHaveTextContent('Naar de hoofdinhoud');
        expect(first).toHaveAttribute('href', '#main');
    });

    it('toggles the menu with aria-expanded and closes on Escape, returning focus (AC6)', () => {
        renderShell();
        const button = screen.getByRole('button', { name: 'Menu' });

        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(button).toHaveAttribute('aria-controls', 'mobile-menu');
        fireEvent.click(button);
        expect(button).toHaveAttribute('aria-expanded', 'true');
        fireEvent.keyDown(screen.getByRole('link', { name: 'Help' }), {
            key: 'Escape',
        });
        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(button).toHaveFocus();
    });

    it('has no axe violations (AC5)', async () => {
        const { container } = renderShell();

        await expect(container).toHaveNoA11yViolations();
    });
});
