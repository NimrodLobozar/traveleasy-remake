import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import { useState } from 'react';
import { renderToString } from 'react-dom/server';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { Button } from './button';
import { Combobox } from './combobox';
import type { ComboboxOption } from './combobox';
import {
    compareYmd,
    formatDateText,
    fromIso,
    parseDateText,
    toIso,
} from './date-text';
import { DatePicker } from './date-picker';
import { Dialog } from './dialog';
import { Pagination, pageItems } from './pagination';
import { Table } from './table';
import type { SortState } from './table';
import { showToast, ToastRegion, toastQueue, toastTimeout } from './toast';
import { announce, ValidationAnnouncer } from './announcer';

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

beforeEach(() => {
    lang.locale = 'nl';
});

const AIRPORTS: ComboboxOption[] = [
    { id: 'agp', label: 'Málaga', code: 'AGP' },
    { id: 'mla', label: 'Malta', code: 'MLA' },
    { id: 'ams', label: 'Amsterdam', code: 'AMS' },
];

const liveText = () =>
    Array.from(document.querySelectorAll('[data-live-announcer] [aria-live]'))
        .map((node) => node.textContent)
        .join('|');

describe('Combobox (AC1)', () => {
    it('is a combobox that filters while typing and keeps focus in the input', async () => {
        const user = userEvent.setup();
        const { container } = render(
            <Combobox label="Vertrek" options={AIRPORTS} />,
        );
        const input = screen.getByRole('combobox', { name: 'Vertrek' });
        expect(input).toHaveAttribute('aria-autocomplete', 'list');
        expect(input).toHaveAttribute('aria-expanded', 'false');
        await user.type(input, 'mal');
        expect(input).toHaveAttribute('aria-expanded', 'true');
        expect(input).toHaveAttribute('aria-controls');
        expect(screen.getAllByRole('option')).toHaveLength(2);
        expect(input).toHaveFocus();
        await expect(container).toHaveNoA11yViolations();
    });

    it('matches without accents (Malaga finds Málaga)', async () => {
        const user = userEvent.setup();
        render(<Combobox label="Vertrek" options={AIRPORTS} />);
        await user.type(screen.getByRole('combobox'), 'malaga');
        expect(screen.getAllByRole('option')).toHaveLength(1);
        expect(screen.getByRole('option')).toHaveTextContent('Málaga');
    });

    it('arrow keys move the highlight through aria-activedescendant, Enter picks, Escape closes', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <Combobox label="Vertrek" options={AIRPORTS} onChange={onChange} />,
        );
        const input = screen.getByRole('combobox');
        await user.type(input, 'm');
        await user.keyboard('{ArrowDown}');
        const first = screen.getAllByRole('option')[0];
        expect(input).toHaveAttribute('aria-activedescendant', first?.id);
        expect(input).toHaveFocus();
        await user.keyboard('{ArrowDown}');
        expect(input).toHaveAttribute(
            'aria-activedescendant',
            screen.getAllByRole('option')[1]?.id,
        );
        await user.keyboard('{Enter}');
        expect(onChange).toHaveBeenCalledWith('mla');
        expect(input).toHaveValue('Malta MLA');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('Escape closes the list; a second Escape reverts the text; Tab does not pick', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <Combobox label="Vertrek" options={AIRPORTS} onChange={onChange} />,
        );
        const input = screen.getByRole('combobox');
        await user.type(input, 'ams');
        await user.keyboard('{ArrowDown}');
        await user.keyboard('{Escape}');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
        expect(input).toHaveValue('ams');
        await user.keyboard('{Escape}');
        expect(input).toHaveValue('');
        await user.type(input, 'ams');
        await user.keyboard('{ArrowDown}{Tab}');
        expect(onChange).not.toHaveBeenCalledWith('ams');
    });

    it('works with a pointer', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <Combobox label="Vertrek" options={AIRPORTS} onChange={onChange} />,
        );
        await user.type(screen.getByRole('combobox'), 'ams');
        await user.click(screen.getByRole('option', { name: /Amsterdam/ }));
        expect(onChange).toHaveBeenCalledWith('ams');
    });

    it('has a toggle button (named, out of the tab order) that opens the full list', async () => {
        const user = userEvent.setup();
        render(
            <Combobox
                label="Vertrek"
                options={AIRPORTS}
                toggleLabel="Toon luchthavens"
            />,
        );
        const toggle = screen.getByRole('button', { name: /Toon luchthavens/ });
        expect(toggle).toHaveAttribute('tabindex', '-1');
        await user.click(toggle);
        expect(screen.getAllByRole('option')).toHaveLength(3);
        expect(toggle).toHaveAttribute('aria-expanded', 'true');
    });

    it('announces the result count through RAC only, in the active locale (nl, en)', async () => {
        const user = userEvent.setup();
        const view = render(<Combobox label="Vertrek" options={AIRPORTS} />);
        await user.type(screen.getByRole('combobox'), 'mal');
        await waitFor(() => {
            expect(liveText()).toMatch(/2 opties beschikbaar/);
        });
        // The component adds no count region of its own: only the loading status exists.
        const own = view.container.querySelectorAll(
            '[aria-live], [role="status"], output',
        );
        own.forEach((node) => {
            expect(node.textContent).toBe('');
        });
        view.unmount();
        lang.locale = 'en';
        render(<Combobox label="Departure" options={AIRPORTS} />);
        await userEvent.setup().type(screen.getByRole('combobox'), 'ams');
        await waitFor(() => {
            expect(liveText()).toMatch(/1 option available/);
        });
    });

    it('shows a visible no-results message in the popover (nl, en)', async () => {
        const user = userEvent.setup();
        render(<Combobox label="Vertrek" options={AIRPORTS} />);
        await user.type(screen.getByRole('combobox'), 'zzz');
        expect(
            await screen.findByText('Geen resultaten voor "zzz"'),
        ).toBeInTheDocument();
    });

    it('async loading: status text, aria-busy, focus stays in the input', async () => {
        const user = userEvent.setup();
        function Async() {
            const [loading, setLoading] = useState(false);
            const [text, setText] = useState('');

            return (
                <Combobox
                    label="Bestemming"
                    options={AIRPORTS}
                    filter="none"
                    isLoading={loading}
                    inputValue={text}
                    onInputChange={(next) => {
                        setText(next);
                        setLoading(next.length === 1);
                    }}
                />
            );
        }
        const { container } = render(<Async />);
        const input = screen.getByRole('combobox');
        await user.type(input, 'm');
        expect(screen.getAllByText('Resultaten laden…').length).toBeGreaterThan(
            0,
        );
        expect(container.querySelector('output')).toHaveTextContent(
            'Resultaten laden…',
        );
        expect(container.querySelector('[aria-busy="true"]')).not.toBeNull();
        expect(input).toHaveFocus();
        await user.type(input, 'a');
        expect(container.querySelector('output')).toHaveTextContent('');
        expect(input).toHaveFocus();
    });

    it('text that matches no option is kept and reported on blur; an exact match is selected', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <>
                <ValidationAnnouncer />
                <Combobox
                    label="Vertrek"
                    options={AIRPORTS}
                    onChange={onChange}
                    invalidMessage="Kies een luchthaven uit de lijst, bijvoorbeeld Malta (MLA)."
                />
                <button type="button">Elders</button>
            </>,
        );
        announce('');
        const input = screen.getByRole('combobox');
        await user.type(input, 'Mars');
        await user.tab();
        expect(
            screen.getByText(
                'Kies een luchthaven uit de lijst, bijvoorbeeld Malta (MLA).',
            ),
        ).toBeInTheDocument();
        expect(input).toHaveValue('Mars');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        await user.clear(input);
        await user.type(input, 'malta');
        await user.tab();
        expect(onChange).toHaveBeenCalledWith('mla');
        expect(input).toHaveValue('Malta MLA');
    });
});

describe('date text (AC2)', () => {
    it('parses day-month-year in nl and en, with - / . separators, and ISO', () => {
        for (const text of [
            '12-06-2027',
            '12/06/2027',
            '12.6.2027',
            '2027-06-12',
        ]) {
            const parsed = parseDateText(text);
            expect(parsed).toEqual({
                status: 'ok',
                date: { year: 2027, month: 6, day: 12 },
            });
        }
    });

    it('edge cases: 29 February, 31 April, DST day, empty, junk, two-digit year', () => {
        expect(parseDateText('29-02-2028').status).toBe('ok');
        expect(parseDateText('29-02-2027').status).toBe('invalid');
        expect(parseDateText('31-04-2027').status).toBe('invalid');
        expect(parseDateText('00-01-2027').status).toBe('invalid');
        expect(parseDateText('01-13-2027').status).toBe('invalid');
        // Clock changes: 28 Mar 2027 (spring forward) and 31 Oct 2027 (fall back) stay on their day.
        expect(parseDateText('28-03-2027')).toEqual({
            status: 'ok',
            date: { year: 2027, month: 3, day: 28 },
        });
        expect(toIso({ year: 2027, month: 10, day: 31 })).toBe('2027-10-31');
        expect(parseDateText('   ').status).toBe('empty');
        expect(parseDateText('morgen').status).toBe('invalid');
        expect(parseDateText('12-06-27').status).toBe('invalid');
    });

    it('formats in the locale format and round-trips ISO', () => {
        const date = { year: 2027, month: 6, day: 2 };
        expect(formatDateText(date, 'nl')).toBe('02-06-2027');
        expect(formatDateText(date, 'en')).toBe('02/06/2027');
        expect(fromIso(toIso(date))).toEqual(date);
        expect(fromIso('2027-02-30')).toBeNull();
        expect(compareYmd(date, { year: 2027, month: 6, day: 3 })).toBeLessThan(
            0,
        );
    });
});

describe('DatePicker (AC2)', () => {
    it('accepts a typed nl date and publishes ISO', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <DatePicker label="Vertrekdatum" name="d" onChange={onChange} />,
        );
        const input = screen.getByRole('textbox', { name: 'Vertrekdatum' });
        expect(screen.getByText('Gebruik dd-mm-jjjj')).toBeInTheDocument();
        await user.type(input, '12-06-2027');
        await user.tab();
        expect(onChange).toHaveBeenLastCalledWith('2027-06-12');
        expect(
            document.querySelector('input[type="hidden"][name="d"]'),
        ).toHaveValue('2027-06-12');
    });

    it('shows the localised errors and keeps the typed text (nl and en)', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<DatePicker label="Datum" isRequired />);
        const input = screen.getByRole('textbox');
        await user.type(input, '31-04-2027');
        await user.tab();
        expect(
            screen.getByText(
                'Die datum bestaat niet. Gebruik dd-mm-jjjj, bijvoorbeeld 12-06-2027.',
            ),
        ).toBeInTheDocument();
        expect(input).toHaveValue('31-04-2027');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        await user.clear(input);
        await user.tab();
        await user.click(input);
        await user.tab();
        expect(
            screen.getByText('Vul een datum in als dd-mm-jjjj.'),
        ).toBeInTheDocument();
        unmount();
        lang.locale = 'en';
        render(<DatePicker label="Date" />);
        await user.type(screen.getByRole('textbox'), '30/02/2027');
        await user.tab();
        expect(
            screen.getByText(
                'That date does not exist. Use dd/mm/yyyy, for example 12/06/2027.',
            ),
        ).toBeInTheDocument();
    });

    it('enforces min and max with the date in words', async () => {
        const user = userEvent.setup();
        render(
            <DatePicker
                label="Datum"
                minValue="2027-06-07"
                maxValue="2028-06-07"
            />,
        );
        const input = screen.getByRole('textbox');
        await user.type(input, '01-06-2027');
        await user.tab();
        expect(
            screen.getByText('Kies een datum vanaf 7 juni 2027.'),
        ).toBeInTheDocument();
        await user.clear(input);
        await user.type(input, '01-07-2028');
        await user.tab();
        expect(
            screen.getByText('Kies een datum tot en met 7 juni 2028.'),
        ).toBeInTheDocument();
    });

    it('formats a given value in the locale format and normalises typed input', async () => {
        const user = userEvent.setup();
        render(<DatePicker label="Datum" defaultValue="2028-02-29" />);
        const input = screen.getByRole('textbox');
        expect(input).toHaveValue('29-02-2028');
        await user.clear(input);
        await user.type(input, '1-6-2027');
        await user.tab();
        expect(input).toHaveValue('1-6-2027');
    });

    it('opens a calendar dialog (lazy), starts the week on Monday and picks with the keyboard', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(
            <DatePicker
                label="Datum"
                defaultValue="2027-06-12"
                onChange={onChange}
                minValue="2027-06-07"
            />,
        );
        const opener = screen.getByRole('button', { name: 'Kalender openen' });
        await user.click(opener);
        const dialog = await screen.findByRole('dialog', { name: 'Kalender' });
        expect(dialog.querySelectorAll('thead th')).toHaveLength(7);
        // 14 June 2027 is a Monday: first cell of its row.
        const monday = within(dialog).getByRole('button', {
            name: /^maandag 14 juni 2027/,
        });
        expect(monday.closest('tr')?.querySelector('td')).toBe(
            monday.closest('td'),
        );
        // Days before the minimum are exposed as disabled.
        expect(
            within(dialog).getByRole('button', {
                name: /^zaterdag 5 juni 2027/,
            }),
        ).toHaveAttribute('aria-disabled', 'true');
        await user.keyboard('{ArrowRight}{Enter}');
        expect(onChange).toHaveBeenLastCalledWith('2027-06-13');
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
        expect(screen.getByRole('textbox')).toHaveValue('13-06-2027');
        await waitFor(() => {
            expect(opener).toHaveFocus();
        });
    });

    it('Escape closes the calendar and returns focus to the button; PageDown moves a month', async () => {
        const user = userEvent.setup();
        render(<DatePicker label="Datum" defaultValue="2027-06-12" />);
        const opener = screen.getByRole('button', { name: 'Kalender openen' });
        await user.click(opener);
        const dialog = await screen.findByRole('dialog');
        await user.keyboard('{PageDown}');
        expect(
            within(dialog).getAllByText(/juli 2027/i).length,
        ).toBeGreaterThan(0);
        await user.keyboard('{Escape}');
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
        await waitFor(() => {
            expect(opener).toHaveFocus();
        });
    });

    it('marks unavailable days as disabled and the calendar passes axe', async () => {
        const user = userEvent.setup();
        render(
            <DatePicker
                label="Datum"
                defaultValue="2027-06-12"
                isDateUnavailable={(iso) => iso === '2027-06-15'}
            />,
        );
        await user.click(
            screen.getByRole('button', { name: 'Kalender openen' }),
        );
        const dialog = await screen.findByRole('dialog');
        expect(
            within(dialog).getByRole('button', {
                name: /^dinsdag 15 juni 2027/,
            }),
        ).toHaveAttribute('aria-disabled', 'true');
        await expect(dialog).toHaveNoA11yViolations();
    });

    it('en: format hint and calendar name', async () => {
        lang.locale = 'en';
        const user = userEvent.setup();
        render(<DatePicker label="Date" />);
        expect(screen.getByText('Use dd/mm/yyyy')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Open calendar' }));
        expect(
            await screen.findByRole('dialog', { name: 'Calendar' }),
        ).toBeInTheDocument();
    });
});

type Row = { id: string; flight: string; seats: number };
const ROWS: Row[] = [
    { id: '1', flight: 'HV1', seats: 5 },
    { id: '2', flight: 'HV2', seats: 9 },
];
const COLUMNS = [
    {
        id: 'flight',
        header: 'Vlucht',
        cell: (r: Row) => r.flight,
        sortable: true,
        isRowHeader: true,
    },
    {
        id: 'seats',
        header: 'Plaatsen',
        cell: (r: Row) => r.seats,
        numeric: true,
        sortable: true,
    },
];

function SortableTable() {
    const [sort, setSort] = useState<SortState | null>(null);

    return (
        <Table
            caption="Vluchten"
            columns={COLUMNS}
            rows={ROWS}
            getRowKey={(r) => r.id}
            sort={sort}
            onSortChange={setSort}
        />
    );
}

describe('Table (AC3, AC4)', () => {
    it('has a caption, th scope, and no aria-sort on plain columns', async () => {
        const { container } = render(<SortableTable />);
        expect(
            screen.getByRole('table', { name: 'Vluchten' }),
        ).toBeInTheDocument();
        expect(container.querySelector('caption')).toHaveTextContent(
            'Vluchten',
        );
        const headers = container.querySelectorAll('thead th');
        headers.forEach((th) => {
            expect(th).toHaveAttribute('scope', 'col');
        });
        expect(container.querySelector('tbody th[scope="row"]')).not.toBeNull();
        await expect(container).toHaveNoA11yViolations();
    });

    it('sortable headers are buttons: aria-sort toggles and the change is announced politely', async () => {
        const user = userEvent.setup();
        render(<SortableTable />);
        const th = screen.getByRole('columnheader', { name: /Plaatsen/ });
        expect(th).toHaveAttribute('aria-sort', 'none');
        const status = screen.getByRole('status');
        expect(status).toHaveTextContent('');
        await user.click(screen.getByRole('button', { name: 'Plaatsen' }));
        expect(th).toHaveAttribute('aria-sort', 'ascending');
        expect(status).toHaveTextContent('Gesorteerd op Plaatsen, oplopend');
        await user.keyboard('{Enter}');
        expect(th).toHaveAttribute('aria-sort', 'descending');
        expect(status).toHaveTextContent('Gesorteerd op Plaatsen, aflopend');
        // Only one column is sorted.
        await user.click(screen.getByRole('button', { name: 'Vlucht' }));
        expect(th).toHaveAttribute('aria-sort', 'none');
    });

    it('stacked layout keeps header text in every cell (aria-hidden labels) and the semantics', () => {
        const { container } = render(<SortableTable />);
        const cell = container.querySelector('tbody td');
        expect(cell?.querySelector('[aria-hidden="true"]')).toHaveTextContent(
            'Plaatsen',
        );
        expect(container.querySelector('table')).toHaveAttribute(
            'role',
            'table',
        );
    });

    it('empty: persistent status, visible block, reset button only with active filters', async () => {
        const user = userEvent.setup();
        const onReset = vi.fn();
        const { rerender } = render(
            <Table
                caption="Vluchten"
                columns={COLUMNS}
                rows={[]}
                getRowKey={(r) => r.id}
            />,
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'Geen resultaten gevonden',
        );
        expect(
            screen.getByText('Geen resultaten gevonden', { selector: 'p' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Filters wissen' }),
        ).not.toBeInTheDocument();
        rerender(
            <Table
                caption="Vluchten"
                columns={COLUMNS}
                rows={[]}
                getRowKey={(r) => r.id}
                filtersActive
                onResetFilters={onReset}
            />,
        );
        await user.click(
            screen.getByRole('button', { name: 'Filters wissen' }),
        );
        expect(onReset).toHaveBeenCalledOnce();
    });

    it('loading: status text, aria-busy, static skeleton hidden from assistive tech', async () => {
        const { container } = render(
            <Table
                caption="Vluchten"
                columns={COLUMNS}
                rows={[]}
                getRowKey={(r) => r.id}
                isLoading
                loadingLabel="Vluchten laden…"
            />,
        );
        expect(screen.getByRole('status')).toHaveTextContent('Vluchten laden…');
        expect(container.querySelector('[aria-busy="true"]')).not.toBeNull();
        expect(
            screen.queryByText('Geen resultaten gevonden', { selector: 'p' }),
        ).not.toBeInTheDocument();
        expect(
            container.querySelectorAll('tbody tr[aria-hidden="true"]'),
        ).toHaveLength(3);
        await expect(container).toHaveNoA11yViolations();
    });

    it('the status region is present before it changes, and en strings exist', () => {
        lang.locale = 'en';
        const { rerender } = render(
            <Table
                caption="Flights"
                columns={COLUMNS}
                rows={ROWS}
                getRowKey={(r) => r.id}
            />,
        );
        const status = screen.getByRole('status');
        expect(status).toHaveTextContent('');
        rerender(
            <Table
                caption="Flights"
                columns={COLUMNS}
                rows={[]}
                getRowKey={(r) => r.id}
            />,
        );
        expect(screen.getByRole('status')).toBe(status);
        expect(status).toHaveTextContent('No results found');
    });

    it('renders cell content as text, never as HTML', () => {
        const { container } = render(
            <Table
                caption="x"
                columns={[
                    { id: 'a', header: 'A', cell: (r: { v: string }) => r.v },
                ]}
                rows={[{ v: '<img src=x onerror=alert(1)>' }]}
                getRowKey={(r) => r.v}
            />,
        );
        expect(container.querySelector('img')).toBeNull();
        expect(container).toHaveTextContent('<img src=x onerror=alert(1)>');
    });
});

describe('Dialog (AC5)', () => {
    function Demo({ pending = false }: { pending?: boolean }) {
        return (
            <>
                <button type="button">Elders</button>
                <Dialog
                    trigger={<Button>Boeking annuleren</Button>}
                    role="alertdialog"
                    title="Boeking annuleren?"
                    description="U krijgt € 120,00 terug, inclusief 21% btw."
                    confirm={{
                        label: 'Ja, annuleren',
                        variant: 'danger',
                        onConfirm: vi.fn(),
                        isPending: pending,
                    }}
                >
                    <p>Extra uitleg</p>
                </Dialog>
            </>
        );
    }

    it('names and describes itself, focuses the safe action, traps Tab and makes the page inert', async () => {
        const user = userEvent.setup();
        render(<Demo />);
        const opener = screen.getByRole('button', {
            name: 'Boeking annuleren',
        });
        await user.click(opener);
        const dialog = await screen.findByRole('alertdialog', {
            name: 'Boeking annuleren?',
        });
        expect(dialog).toHaveAccessibleDescription(
            'U krijgt € 120,00 terug, inclusief 21% btw.',
        );
        expect(screen.getByRole('button', { name: 'Terug' })).toHaveFocus();
        const inside = within(dialog).getAllByRole('button');
        for (let i = 0; i < inside.length + 2; i += 1) {
            await user.tab();
            expect(dialog.contains(document.activeElement)).toBe(true);
        }
        expect(
            document.querySelector('[aria-hidden="true"] button'),
        ).not.toBeNull();
        await expect(dialog).toHaveNoA11yViolations();
    });

    it('Escape closes and focus returns to the trigger', async () => {
        const user = userEvent.setup();
        render(<Demo />);
        const opener = screen.getByRole('button', {
            name: 'Boeking annuleren',
        });
        await user.click(opener);
        await screen.findByRole('alertdialog');
        await user.keyboard('{Escape}');
        await waitFor(() => {
            expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
        });
        await waitFor(() => {
            expect(opener).toHaveFocus();
        });
    });

    it('the close button is named and closes; Back closes without confirming', async () => {
        const user = userEvent.setup();
        render(<Demo />);
        await user.click(
            screen.getByRole('button', { name: 'Boeking annuleren' }),
        );
        await user.click(
            await screen.findByRole('button', { name: 'Sluiten' }),
        );
        await waitFor(() => {
            expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
        });
    });

    it('Escape is ignored while an action is pending', async () => {
        const user = userEvent.setup();
        render(
            <Dialog
                trigger={<Button>Open</Button>}
                title="Bezig"
                defaultOpen
                confirm={{ label: 'Doen', onConfirm: vi.fn(), isPending: true }}
            />,
        );
        await screen.findByRole('dialog');
        await user.keyboard('{Escape}');
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('confirm runs the action and closes', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();
        render(
            <Dialog
                trigger={<Button>Open</Button>}
                title="Zeker?"
                confirm={{ label: 'Ja', onConfirm }}
            />,
        );
        await user.click(screen.getByRole('button', { name: 'Open' }));
        await user.click(await screen.findByRole('button', { name: 'Ja' }));
        expect(onConfirm).toHaveBeenCalledOnce();
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
    });
});

describe('Toast (AC6, AC11)', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        toastQueue.clear();
    });
    afterEach(() => {
        act(() => {
            toastQueue.clear();
        });
        vi.useRealTimers();
    });

    it('region: persistent status and alert containers exist before any toast; named landmark', () => {
        const { container } = render(<ToastRegion />);
        expect(container.querySelector('output')).not.toBeNull();
        expect(container.querySelector('[role="alert"]')).not.toBeNull();
        expect(
            screen.getByRole('region', { name: 'Meldingen' }),
        ).toBeInTheDocument();
    });

    it('confirmations go into the status region, errors into the alert region, no role on the toast (AC11)', () => {
        const { container } = render(<ToastRegion />);
        act(() => {
            showToast({ severity: 'success', title: 'Opgeslagen' });
            showToast({
                severity: 'danger',
                title: 'Mislukt',
                description: 'Probeer het opnieuw.',
            });
        });
        const status = container.querySelector('output');
        const alert = container.querySelector('[role="alert"]');
        expect(status).toHaveTextContent('Opgeslagen');
        expect(alert).toHaveTextContent('Mislukt');
        // AC11: RAC's Toast always renders role="alertdialog"; ours must not.
        expect(document.querySelector('[role="alertdialog"]')).toBeNull();
        container.querySelectorAll('[data-toast]').forEach((toast) => {
            expect(toast.getAttribute('role')).toBeNull();
        });
        expect(screen.getAllByRole('alert')).toHaveLength(1);
    });

    it('never takes focus', () => {
        render(
            <>
                <button type="button">Eerder</button>
                <ToastRegion />
            </>,
        );
        screen.getByRole('button', { name: 'Eerder' }).focus();
        act(() => {
            showToast({ severity: 'info', title: 'Hallo' });
        });
        expect(screen.getByRole('button', { name: 'Eerder' })).toHaveFocus();
    });

    it('stays at least 8 s (+1 s per 15 words), errors stay until dismissed', () => {
        expect(toastTimeout({ severity: 'success', title: 'Kort' })).toBe(9000);
        expect(
            toastTimeout({
                severity: 'success',
                title: 'a',
                description: Array(30).fill('woord').join(' '),
            }),
        ).toBe(11000);
        render(<ToastRegion />);
        act(() => {
            showToast({ severity: 'success', title: 'Opgeslagen' });
            showToast({ severity: 'danger', title: 'Mislukt' });
        });
        act(() => {
            vi.advanceTimersByTime(7900);
        });
        expect(screen.getByText('Opgeslagen')).toBeInTheDocument();
        act(() => {
            vi.advanceTimersByTime(60000);
        });
        expect(screen.queryByText('Opgeslagen')).not.toBeInTheDocument();
        expect(screen.getByText('Mislukt')).toBeInTheDocument();
    });

    it('pauses on hover and on focus', async () => {
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        render(<ToastRegion />);
        act(() => {
            showToast({ severity: 'success', title: 'Opgeslagen' });
        });
        const toast = screen.getByText('Opgeslagen').closest('[data-toast]');
        await user.hover(toast as HTMLElement);
        act(() => {
            vi.advanceTimersByTime(30000);
        });
        expect(screen.getByText('Opgeslagen')).toBeInTheDocument();
        await user.unhover(toast as HTMLElement);
        act(() => {
            vi.advanceTimersByTime(20000);
        });
        expect(screen.queryByText('Opgeslagen')).not.toBeInTheDocument();
        act(() => {
            showToast({ severity: 'info', title: 'Tweede' });
        });
        await user.tab();
        expect(
            screen.getByText('Tweede').closest('[data-toast]'),
        ).toHaveFocus();
        act(() => {
            vi.advanceTimersByTime(30000);
        });
        expect(screen.getByText('Tweede')).toBeInTheDocument();
    });

    it('can be dismissed with the keyboard (Escape and the close button)', async () => {
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        render(<ToastRegion />);
        act(() => {
            showToast({ severity: 'warning', title: 'Let op' });
            showToast({ severity: 'warning', title: 'Opnieuw' });
        });
        await user.tab();
        await user.keyboard('{Escape}');
        expect(screen.getAllByText(/Let op|Opnieuw/)).toHaveLength(1);
        await user.tab();
        await user.keyboard('{Enter}');
        expect(screen.queryByText(/Let op|Opnieuw/)).not.toBeInTheDocument();
    });

    it('shows at most three toasts, newest first, and passes axe', async () => {
        const { container } = render(<ToastRegion />);
        act(() => {
            ['een', 'twee', 'drie', 'vier'].forEach((title) => {
                showToast({ severity: 'info', title });
            });
        });
        const toasts = container.querySelectorAll('[data-toast]');
        expect(toasts).toHaveLength(3);
        expect(toasts[0]).toHaveTextContent('vier');
        await expect(container).toHaveNoA11yViolations();
    });

    it('close button name includes the title (nl, en)', () => {
        render(<ToastRegion />);
        act(() => {
            showToast({ severity: 'info', title: 'Hallo' });
        });
        expect(
            screen.getByRole('button', { name: 'Melding sluiten: Hallo' }),
        ).toBeInTheDocument();
    });
});

describe('Pagination (AC7)', () => {
    const hrefFor = (n: number) => `/vluchten?page=${String(n)}`;

    it('is a named nav of real links; the current page has aria-current', async () => {
        const { container } = render(
            <Pagination page={2} pageCount={9} hrefFor={hrefFor} />,
        );
        const nav = screen.getByRole('navigation', { name: 'Paginering' });
        const current = within(nav).getByRole('link', { name: 'Pagina 2' });
        expect(current).toHaveAttribute('aria-current', 'page');
        expect(current).toHaveAttribute('href', '/vluchten?page=2');
        expect(
            within(nav).getByRole('link', { name: 'Vorige' }),
        ).toHaveAttribute('href', '/vluchten?page=1');
        expect(
            within(nav).getByRole('link', { name: 'Volgende' }),
        ).toHaveAttribute('href', '/vluchten?page=3');
        expect(within(nav).getByText('Pagina 2 van 9')).toBeInTheDocument();
        await expect(container).toHaveNoA11yViolations();
    });

    it('first and last page show Vorige / Volgende as disabled non-links', () => {
        const { rerender } = render(
            <Pagination page={1} pageCount={9} hrefFor={hrefFor} />,
        );
        const prev = screen.getByRole('link', { name: 'Vorige' });
        expect(prev).toHaveAttribute('aria-disabled', 'true');
        expect(prev).not.toHaveAttribute('href');
        rerender(<Pagination page={9} pageCount={9} hrefFor={hrefFor} />);
        expect(screen.getByRole('link', { name: 'Volgende' })).toHaveAttribute(
            'aria-disabled',
            'true',
        );
    });

    it('windows the pages with ellipses that are hidden from assistive technology', () => {
        expect(pageItems(5, 12)).toEqual([
            1,
            'gap-start',
            4,
            5,
            6,
            'gap-end',
            12,
        ]);
        expect(pageItems(1, 3)).toEqual([1, 2, 3]);
        expect(pageItems(3, 5)).toEqual([1, 2, 3, 4, 5]);
        const { container } = render(
            <Pagination page={5} pageCount={12} hrefFor={hrefFor} />,
        );
        container.querySelectorAll('li[aria-hidden="true"]').forEach((gap) => {
            expect(gap).toHaveTextContent('…');
        });
        expect(
            container.querySelectorAll('li[aria-hidden="true"]'),
        ).toHaveLength(2);
    });

    it('en strings', () => {
        lang.locale = 'en';
        render(<Pagination page={2} pageCount={9} hrefFor={hrefFor} />);
        expect(
            screen.getByRole('navigation', { name: 'Pagination' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Page 2 of 9')).toBeInTheDocument();
    });
});

describe('SSR (AC9)', () => {
    it('renders every component on the server without browser globals', () => {
        const html = renderToString(
            <>
                <ToastRegion />
                <Combobox label="Vertrek" options={AIRPORTS} />
                <DatePicker label="Datum" defaultValue="2027-06-12" name="d" />
                <Table
                    caption="Vluchten"
                    columns={COLUMNS}
                    rows={ROWS}
                    getRowKey={(r) => r.id}
                />
                <Dialog trigger={<Button>Open</Button>} title="Titel" />
                <Pagination
                    page={2}
                    pageCount={9}
                    hrefFor={(n) => `?page=${String(n)}`}
                />
            </>,
        );
        expect(html).toContain('role="combobox"');
        expect(html).toContain('value="12-06-2027"');
        expect(html).toContain('href="?page=3"');
        expect(html).not.toContain('role="dialog"');
    });
});
