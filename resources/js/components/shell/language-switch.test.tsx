import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import LanguageSwitch from './language-switch';

const nl = JSON.parse(readFileSync('lang/nl.json', 'utf8')) as Record<
    string,
    string
>;
const en = JSON.parse(readFileSync('lang/en.json', 'utf8')) as Record<
    string,
    string
>;

const state = vi.hoisted(() => ({
    locale: 'nl' as 'nl' | 'en',
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: state.locale,
            translations: state.locale === 'nl' ? nl : en,
            csrfToken: 'token-123',
        },
    }),
    router: { post: state.post },
}));

beforeEach(() => {
    state.locale = 'nl';
    state.post.mockReset();
});

describe('LanguageSwitch (TE-017)', () => {
    it('offers both languages by their own name, marks the current one and sets lang (AC1, AC7)', () => {
        render(<LanguageSwitch />);
        const group = screen.getByRole('group', { name: 'Taal' });
        const dutch = screen.getByRole('button', { name: 'Nederlands' });
        const english = screen.getByRole('button', { name: 'English' });

        expect(group).toContainElement(dutch);
        expect(dutch).toHaveAttribute('aria-current', 'true');
        expect(english).not.toHaveAttribute('aria-current');
        expect(english).toHaveAttribute('lang', 'en');
        expect(dutch).toHaveAttribute('lang', 'nl');
    });

    it('labels the group in English when the page is English', () => {
        state.locale = 'en';
        render(<LanguageSwitch />);

        expect(screen.getByRole('group', { name: 'Language' })).toBeVisible();
        expect(screen.getByRole('button', { name: 'English' })).toHaveAttribute(
            'aria-current',
            'true',
        );
    });

    it('is a real form post with a CSRF token, so it works without JavaScript (AC5)', () => {
        const { container } = render(<LanguageSwitch />);
        const form = container.querySelector('form');
        const english = screen.getByRole('button', { name: 'English' });

        expect(form).toHaveAttribute('method', 'post');
        expect(form).toHaveAttribute('action', '/locale');
        expect(container.querySelector('input[name="_token"]')).toHaveValue(
            'token-123',
        );
        expect(english).toHaveAttribute('type', 'submit');
        expect(english).toHaveAttribute('name', 'locale');
        expect(english).toHaveAttribute('value', 'en');
    });

    it('posts the other language with Enter or Space, without a full reload (AC2)', async () => {
        const user = userEvent.setup();
        render(<LanguageSwitch />);
        const english = screen.getByRole('button', { name: 'English' });

        english.focus();
        await user.keyboard('{Enter}');
        await user.keyboard(' ');

        expect(state.post).toHaveBeenCalledTimes(2);
        expect(state.post).toHaveBeenCalledWith(
            '/locale',
            { locale: 'en' },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('does not post when the current language is chosen', async () => {
        const user = userEvent.setup();
        render(<LanguageSwitch />);

        await user.click(screen.getByRole('button', { name: 'Nederlands' }));

        expect(state.post).not.toHaveBeenCalled();
    });

    it('announces the change politely in the new language (AC2)', async () => {
        const user = userEvent.setup();
        state.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess: () => void },
            ) => {
                state.locale = 'en';
                options.onSuccess();
            },
        );
        const { rerender } = render(<LanguageSwitch />);

        await user.click(screen.getByRole('button', { name: 'English' }));
        rerender(<LanguageSwitch />);

        const live = screen.getByTestId('language-announcer');
        expect(live).toHaveAttribute('aria-live', 'polite');
        expect(live).toHaveTextContent('Language changed to English');
    });

    it('shows an inline error when the server refuses', async () => {
        const user = userEvent.setup();
        state.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onError: (errors: Record<string, string>) => void },
            ) => {
                options.onError({ locale: 'Kies Nederlands of English.' });
            },
        );
        render(<LanguageSwitch />);

        await user.click(screen.getByRole('button', { name: 'English' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Kies Nederlands of English.',
        );
    });

    it('has no axe violations', async () => {
        const { container } = render(<LanguageSwitch />);

        await expect(container).toHaveNoA11yViolations();
    });
});
