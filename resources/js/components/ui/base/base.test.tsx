import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import { renderToString } from 'react-dom/server';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { Alert } from './alert';
import { announce, ValidationAnnouncer } from './announcer';
import { Banner, BannerLiveRegion } from './banner';
import { Button } from './button';
import { FormErrorSummary } from './form-error-summary';
import { Link } from './link';
import { NumberField } from './number-field';
import { Select } from './select';
import { TextField } from './text-field';

const read = (file: string) =>
    JSON.parse(readFileSync(file, 'utf8')) as Record<string, string>;
const lang = vi.hoisted(() => ({ locale: 'nl' as 'nl' | 'en' }));
const messages = { nl: read('lang/nl.json'), en: read('lang/en.json') };

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: { locale: lang.locale, translations: messages[lang.locale] },
    }),
    Link: ({ href, children, ...rest }: Record<string, unknown>) => (
        <a href={String(href)} data-inertia {...rest}>
            {children as never}
        </a>
    ),
}));

describe('Button (AC1)', () => {
    it('defaults to type=button and renders every variant and size', () => {
        render(
            <>
                {(['primary', 'secondary', 'danger', 'ghost'] as const).map(
                    (variant) => (
                        <Button key={variant} variant={variant} size="sm">
                            {variant}
                        </Button>
                    ),
                )}
            </>,
        );
        expect(screen.getByRole('button', { name: 'danger' })).toHaveAttribute(
            'type',
            'button',
        );
        expect(screen.getAllByRole('button')).toHaveLength(4);
    });

    it('pending: announces once, shows the pending text and ignores presses', async () => {
        const onPress = vi.fn();
        const { container } = render(
            <Button isPending onPress={onPress}>
                Boeken
            </Button>,
        );
        const status = screen.getByRole('status');
        expect(status).toHaveTextContent('Bezig…');
        const button = screen.getByRole('button');
        expect(button).toHaveAttribute('aria-disabled', 'true');
        await userEvent.click(button);
        expect(onPress).not.toHaveBeenCalled();
        await expect(container).toHaveNoA11yViolations();
    });

    it('works with the keyboard', async () => {
        const onPress = vi.fn();
        render(<Button onPress={onPress}>Opslaan</Button>);
        await userEvent.tab();
        await userEvent.keyboard('{Enter}');
        await userEvent.keyboard(' ');
        expect(onPress).toHaveBeenCalledTimes(2);
    });
});

describe('Link (AC2)', () => {
    it('external: new tab, rel, icon and hidden hint (nl and en)', () => {
        render(<Link href="https://example.org">Voorwaarden</Link>);
        const link = screen.getByRole('link', {
            name: /Voorwaarden \(opent in nieuw tabblad\)/,
        });
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('internal: Inertia link without target', () => {
        render(<Link href="/trips">Reizen</Link>);
        const link = screen.getByRole('link', { name: 'Reizen' });
        expect(link).toHaveAttribute('data-inertia');
        expect(link).not.toHaveAttribute('target');
    });
});

describe('TextField (AC3)', () => {
    it('associates label, help and error and sets aria-invalid', async () => {
        const { container } = render(
            <TextField
                label="E-mailadres"
                autoComplete="email"
                type="email"
                description="We sturen niets."
                error="Vul een geldig e-mailadres in."
            />,
        );
        const input = screen.getByLabelText('E-mailadres');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('autocomplete', 'email');
        expect(input).toHaveAccessibleDescription(
            'We sturen niets. Vul een geldig e-mailadres in.',
        );
        await expect(container).toHaveNoA11yViolations();
    });

    it('is valid and error-free by default; optional suffix', () => {
        render(<TextField label="Naam" autoComplete="name" isOptional />);
        expect(screen.getByLabelText(/Naam/)).not.toHaveAttribute(
            'aria-invalid',
            'true',
        );
        expect(screen.getByText('(optioneel)')).toBeInTheDocument();
    });

    it('password toggle has a fixed name and aria-pressed', async () => {
        render(
            <TextField
                label="Wachtwoord"
                autoComplete="new-password"
                type="password"
            />,
        );
        const toggle = screen.getByRole('button', { name: 'Toon wachtwoord' });
        expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await userEvent.click(toggle);
        expect(
            screen.getByRole('button', { name: 'Toon wachtwoord' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByLabelText('Wachtwoord')).toHaveAttribute(
            'type',
            'text',
        );
    });

    it('textarea renders', () => {
        render(
            <TextField label="Opmerking" autoComplete="off" type="textarea" />,
        );
        expect(screen.getByRole('textbox', { name: 'Opmerking' }).tagName).toBe(
            'TEXTAREA',
        );
    });
});

describe('FormErrorSummary (AC4)', () => {
    it('moves focus to the summary and its links focus the field', async () => {
        function Form() {
            const [signal, setSignal] = useState(0);

            return (
                <>
                    <button
                        type="button"
                        onClick={() => {
                            setSignal(1);
                        }}
                    >
                        Verstuur
                    </button>
                    <FormErrorSummary
                        focusSignal={signal}
                        errors={[
                            {
                                fieldId: 'email',
                                label: 'E-mailadres',
                                message: 'Ongeldig',
                            },
                        ]}
                    />
                    <TextField
                        id="email"
                        label="E-mailadres"
                        autoComplete="email"
                    />
                </>
            );
        }
        render(<Form />);
        await userEvent.click(screen.getByRole('button', { name: 'Verstuur' }));
        const group = screen.getByRole('group', {
            name: 'Er zijn 1 problemen met dit formulier',
        });
        expect(group).toHaveFocus();
        await userEvent.click(
            screen.getByRole('link', { name: 'E-mailadres: Ongeldig' }),
        );
        expect(screen.getByLabelText('E-mailadres')).toHaveFocus();
    });
});

describe('Select (AC5)', () => {
    const options = [
        { id: 'eco', label: 'Economy' },
        { id: 'biz', label: 'Business' },
        { id: 'first', label: 'First' },
    ];

    it('opens by keyboard, type-ahead and Enter select, focus returns to the trigger', async () => {
        const onChange = vi.fn();
        render(
            <Select
                label="Reisklasse"
                placeholder="Kies"
                options={options}
                onChange={onChange}
            />,
        );
        const trigger = screen.getByRole('button', { name: /Reisklasse/ });
        expect(trigger).toHaveAttribute('aria-haspopup', 'listbox');
        trigger.focus();
        await userEvent.keyboard('{Enter}');
        const listbox = await screen.findByRole('listbox');
        expect(listbox).toBeInTheDocument();
        await userEvent.keyboard('Bu');
        await userEvent.keyboard('{Enter}');
        expect(onChange).toHaveBeenCalledWith('biz');
        await waitFor(() =>
            expect(screen.queryByRole('listbox')).not.toBeInTheDocument(),
        );
        expect(
            screen.getByRole('button', { name: /Reisklasse/ }),
        ).toHaveFocus();
    });

    it('shows the error with description wiring', () => {
        render(
            <Select
                label="Reisklasse"
                placeholder="Kies"
                options={options}
                error="Kies een reisklasse om door te gaan."
            />,
        );
        expect(
            screen.getByText('Kies een reisklasse om door te gaan.'),
        ).toBeInTheDocument();
    });
});

describe('NumberField (AC5)', () => {
    it('steps with the arrow and Page keys, Home/End and stepper buttons', async () => {
        const onChange = vi.fn();
        render(
            <NumberField
                label="Volwassenen"
                minValue={1}
                maxValue={9}
                defaultValue={2}
                onChange={onChange}
                decrementLabel="Minder volwassenen"
                incrementLabel="Meer volwassenen"
            />,
        );
        const input = screen.getByRole('textbox');
        expect(
            screen.getByText('Kies een getal van 1 tot 9.'),
        ).toBeInTheDocument();
        input.focus();
        await userEvent.keyboard('{ArrowUp}');
        expect(onChange).toHaveBeenLastCalledWith(3);
        await userEvent.keyboard('{End}');
        expect(onChange).toHaveBeenLastCalledWith(9);
        await userEvent.keyboard('{Home}');
        expect(onChange).toHaveBeenLastCalledWith(1);
        expect(
            screen.getByRole('button', { name: /^Minder volwassenen/ }),
        ).toBeDisabled();
        await userEvent.click(
            screen.getByRole('button', { name: /^Meer volwassenen/ }),
        );
        expect(onChange).toHaveBeenLastCalledWith(2);
    });

    it('parses and displays the active locale (nl comma)', async () => {
        render(<NumberField label="Bedrag" defaultValue={1234.5} />);
        expect(screen.getByRole('textbox')).toHaveValue('1.234,5');
        const input = screen.getByRole('textbox');
        await userEvent.clear(input);
        await userEvent.type(input, '23,5');
        await userEvent.tab();
        expect(input).toHaveValue('23,5');
    });

    it('clamps to min/max', async () => {
        render(
            <NumberField
                label="N"
                minValue={1}
                maxValue={9}
                defaultValue={2}
            />,
        );
        const input = screen.getByRole('textbox');
        await userEvent.clear(input);
        await userEvent.type(input, '12');
        await userEvent.tab();
        expect(input).toHaveValue('9');
    });
});

describe('Alert (AC6)', () => {
    it('uses status for info/success/warning and alert for danger', () => {
        render(
            <>
                <Alert severity="info" title="Info" />
                <Alert severity="warning" title="Let op" />
                <Alert severity="danger" title="Fout" />
            </>,
        );
        expect(screen.getAllByRole('status')).toHaveLength(2);
        expect(screen.getByRole('alert')).toHaveTextContent('Fout');
    });

    it('only info is dismissible, and focus moves on', async () => {
        render(
            <>
                <Alert severity="info" title="Info" dismissible />
                <button type="button">Volgende</button>
                <Alert severity="warning" title="W" dismissible />
            </>,
        );
        expect(screen.getAllByRole('button', { name: 'Sluiten' })).toHaveLength(
            1,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Sluiten' }));
        expect(screen.queryByText('Info')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Volgende' })).toHaveFocus();
    });
});

describe('Banner (AC6)', () => {
    it('is a labelled section without a live role; dismiss removes it and moves focus', async () => {
        sessionStorage.clear();
        const { container } = render(
            <>
                <Banner label="Onderhoud" dismissible storageKey="b1">
                    Onderhoud zaterdag.
                </Banner>
                <a href="#x">Verder</a>
            </>,
        );
        const region = screen.getByRole('region', { name: 'Onderhoud' });
        expect(region).not.toHaveAttribute('role', 'alert');
        await expect(container).toHaveNoA11yViolations();
        await userEvent.click(screen.getByRole('button', { name: 'Sluiten' }));
        expect(screen.queryByRole('region')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Verder' })).toHaveFocus();
        expect(sessionStorage.getItem('b1')).toBe('1');
    });

    it('non-dismissible banners have no dismiss button; the live region pre-exists', () => {
        const { rerender } = render(<BannerLiveRegion />);
        const live = screen.getByRole('alert');
        expect(live).toBeEmptyDOMElement();
        rerender(
            <BannerLiveRegion>
                <Banner label="Sessie" severity="warning" dismissible>
                    Je sessie verloopt.
                </Banner>
            </BannerLiveRegion>,
        );
        expect(screen.getByRole('alert')).toBe(live);
        expect(live).toHaveTextContent('Je sessie verloopt.');
        expect(
            screen.queryByRole('button', { name: 'Sluiten' }),
        ).not.toBeInTheDocument();
    });
});

describe('English strings and SSR (AC12)', () => {
    it('renders the english pending text and external hint', () => {
        lang.locale = 'en';
        render(
            <>
                <Button isPending>Book</Button>
                <Link href="https://example.org">Terms</Link>
            </>,
        );
        expect(screen.getByRole('status')).toHaveTextContent('Working…');
        expect(screen.getByRole('link')).toHaveAccessibleName(
            'Terms (opens in a new tab)',
        );
        lang.locale = 'nl';
    });

    it('renders every component on the server without throwing', () => {
        const html = renderToString(
            <>
                <Button>Ok</Button>
                <Link href="/a">A</Link>
                <Link href="https://example.org">B</Link>
                <TextField label="L" autoComplete="off" />
                <Select
                    label="S"
                    placeholder="p"
                    options={[{ id: 'a', label: 'A' }]}
                />
                <NumberField label="N" defaultValue={1} />
                <Alert title="A" />
                <Banner label="B">x</Banner>
            </>,
        );
        expect(html).toContain('Ok');
    });
});

describe('NumberField out-of-range message (AC5)', () => {
    it('shows a localised error and announces it when the typed value is out of range', async () => {
        render(
            <>
                <ValidationAnnouncer />
                <NumberField
                    label="Volwassenen"
                    minValue={1}
                    maxValue={9}
                    defaultValue={2}
                />
            </>,
        );
        const input = screen.getByRole('textbox');
        await userEvent.clear(input);
        await userEvent.type(input, '12');
        await userEvent.tab();
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('status')).toHaveTextContent(
            'Volwassenen: Kies een getal van 1 tot 9.',
        );
    });
});

describe('Blur announcements (live region)', () => {
    it('writes a blur error into the persistent polite region', async () => {
        function Demo() {
            const [error, setError] = useState<string | undefined>();

            return (
                <>
                    <ValidationAnnouncer />
                    <TextField
                        label="Stad"
                        autoComplete="off"
                        error={error}
                        onBlur={() => {
                            setError('Vul een stad in.');
                        }}
                    />
                </>
            );
        }
        announce('');
        render(<Demo />);
        expect(screen.getByRole('status')).toBeEmptyDOMElement();
        await userEvent.click(screen.getByRole('textbox'));
        await userEvent.tab();
        await waitFor(() => {
            expect(screen.getByRole('status')).toHaveTextContent(
                'Stad: Vul een stad in.',
            );
        });
    });
});

describe('Select on touch (spec 4.4)', () => {
    it('renders a native select for 5 options or fewer on a coarse pointer', () => {
        const original = window.matchMedia;
        window.matchMedia = ((query: string) => ({
            matches: query.includes('coarse'),
            media: query,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
        })) as unknown as typeof window.matchMedia;
        render(
            <Select
                label="Reisklasse"
                placeholder="Kies"
                options={[
                    { id: 'eco', label: 'Economy' },
                    { id: 'biz', label: 'Business' },
                ]}
            />,
        );
        window.matchMedia = original;
        expect(
            screen.getByRole('combobox', { name: 'Reisklasse' }).tagName,
        ).toBe('SELECT');
    });
});
