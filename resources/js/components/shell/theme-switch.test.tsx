import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { initializeTheme } from '@/hooks/use-appearance';
import ThemeSwitch from './theme-switch';

const nl = JSON.parse(readFileSync('lang/nl.json', 'utf8')) as Record<
    string,
    string
>;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'nl', translations: nl } }),
}));

type Listener = () => void;
let systemDark = false;
const listeners = new Set<Listener>();

beforeEach(() => {
    document.cookie = 'appearance=; path=/; max-age=0';
    document.documentElement.className = '';
    document.documentElement.removeAttribute('data-theme');
    systemDark = false;
    listeners.clear();
    window.matchMedia = ((query: string) => ({
        matches: systemDark,
        media: query,
        addEventListener: (_: string, listener: Listener) =>
            listeners.add(listener),
        removeEventListener: (_: string, listener: Listener) =>
            listeners.delete(listener),
    })) as unknown as typeof window.matchMedia;
    initializeTheme();
});

/** The inline copy for the mobile menu (the second of two; one is always display:none). */
function mobileGroup(): HTMLElement {
    const groups = screen.getAllByRole('group', {
        name: 'Thema',
        hidden: true,
    });
    expect(groups).toHaveLength(2);

    return groups[1] as HTMLElement;
}

describe('ThemeSwitch (TE-018)', () => {
    it('offers Systeem, Licht and Donker as native radios, Systeem selected first (AC4)', () => {
        render(<ThemeSwitch />);
        const radios = within(mobileGroup()).getAllByRole('radio');

        expect(radios.map((radio) => radio.getAttribute('value'))).toEqual([
            'system',
            'light',
            'dark',
        ]);
        expect(radios[0]).toBeChecked();
    });

    it('applies the choice at once and stores it in the appearance cookie (AC4)', async () => {
        const user = userEvent.setup();
        render(<ThemeSwitch />);
        const group = mobileGroup();

        await user.click(within(group).getByRole('radio', { name: 'Donker' }));

        expect(document.documentElement).toHaveClass('dark');
        expect(document.documentElement.dataset.theme).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');

        await user.click(within(group).getByRole('radio', { name: 'Licht' }));

        expect(document.documentElement).not.toHaveClass('dark');
        expect(document.documentElement.dataset.theme).toBe('light');
        expect(document.cookie).toContain('appearance=light');
    });

    it('moves and selects with the arrow keys', async () => {
        const user = userEvent.setup();
        render(<ThemeSwitch />);
        const group = mobileGroup();

        within(group).getByRole('radio', { name: 'Systeem' }).focus();
        await user.keyboard('{ArrowDown}');

        expect(
            within(group).getByRole('radio', { name: 'Licht' }),
        ).toBeChecked();
    });

    it('opens the popover, focuses the selected option, closes on Escape and returns focus (AC4)', async () => {
        const user = userEvent.setup();
        render(<ThemeSwitch />);
        const button = screen.getByRole('button', { name: 'Thema' });

        expect(button).toHaveAttribute('aria-expanded', 'false');
        const popover = document.getElementById(
            button.getAttribute('aria-controls') ?? '',
        );
        expect(popover).toHaveAttribute('hidden');

        await user.click(button);
        expect(button).toHaveAttribute('aria-expanded', 'true');
        expect(popover).not.toHaveAttribute('hidden');
        expect(
            within(popover as HTMLElement).getByRole('radio', {
                name: 'Systeem',
            }),
        ).toHaveFocus();

        await user.keyboard('{Escape}');
        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(button).toHaveFocus();
    });

    it('closes the popover on a click outside', async () => {
        const user = userEvent.setup();
        render(
            <>
                <ThemeSwitch />
                <p>elsewhere</p>
            </>,
        );
        const button = screen.getByRole('button', { name: 'Thema' });

        await user.click(button);
        await user.click(screen.getByText('elsewhere'));

        expect(button).toHaveAttribute('aria-expanded', 'false');
    });

    it('follows the system theme while on Systeem, without a reload (AC6)', () => {
        render(<ThemeSwitch />);
        expect(document.documentElement).not.toHaveClass('dark');

        systemDark = true;
        act(() => {
            listeners.forEach((listener) => {
                listener();
            });
        });

        expect(document.documentElement).toHaveClass('dark');
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('treats an unknown cookie value as system (AC3, client side)', () => {
        document.cookie = 'appearance=foo; path=/';
        initializeTheme();
        render(<ThemeSwitch />);

        expect(
            within(mobileGroup()).getByRole('radio', { name: 'Systeem' }),
        ).toBeChecked();
    });

    it('has no axe violations', async () => {
        const { container } = render(<ThemeSwitch />);

        await expect(container).toHaveNoA11yViolations();
    });
});
