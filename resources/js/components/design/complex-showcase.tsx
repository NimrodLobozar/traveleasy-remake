import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/base/button';
import { Combobox } from '@/components/ui/base/combobox';
import type { ComboboxOption } from '@/components/ui/base/combobox';
import { DatePicker } from '@/components/ui/base/date-picker';
import { Dialog } from '@/components/ui/base/dialog';
import { Pagination } from '@/components/ui/base/pagination';
import { Table } from '@/components/ui/base/table';
import type { SortState } from '@/components/ui/base/table';
import { showToast, ToastRegion } from '@/components/ui/base/toast';
import { useTranslation } from '@/lib/i18n';

/**
 * Showcase of base components II (TE-023): every component and state, so axe and Playwright can
 * scan them in /design. Dev copy: deliberately not translated (built-in strings still are).
 */

const AIRPORTS: ComboboxOption[] = [
    { id: 'agp', label: 'Málaga', code: 'AGP' },
    { id: 'mla', label: 'Malta', code: 'MLA' },
    { id: 'ams', label: 'Amsterdam', code: 'AMS' },
    { id: 'ath', label: 'Athene', code: 'ATH' },
    { id: 'bcn', label: 'Barcelona', code: 'BCN' },
];

type Flight = {
    id: string;
    number: string;
    route: string;
    departs: string;
    seats: number;
    status: string;
};

const FLIGHTS: Flight[] = [
    {
        id: '1',
        number: 'HV5521',
        route: 'AMS - AGP',
        departs: '2027-06-12 08:15',
        seats: 42,
        status: 'On time',
    },
    {
        id: '2',
        number: 'HV5602',
        route: 'AMS - MLA',
        departs: '2027-06-13 11:40',
        seats: 7,
        status: 'Few seats',
    },
    {
        id: '3',
        number: 'HV5710',
        route: 'AMS - ATH',
        departs: '2027-06-11 06:05',
        seats: 120,
        status: 'On time',
    },
    {
        id: '4',
        number: 'HV5833',
        route: 'AMS - BCN',
        departs: '2027-06-14 14:30',
        seats: 0,
        status: 'Full',
    },
];

const CUSTOMER_COLUMNS = [
    {
        id: 'number',
        header: 'Flight',
        cell: (r: Flight) => r.number,
        sortable: true,
        isRowHeader: true,
    },
    { id: 'route', header: 'Route', cell: (r: Flight) => r.route },
    {
        id: 'departs',
        header: 'Departure',
        cell: (r: Flight) => r.departs,
        sortable: true,
    },
    {
        id: 'seats',
        header: 'Seats',
        cell: (r: Flight) => r.seats,
        numeric: true,
        sortable: true,
    },
    { id: 'status', header: 'Status', cell: (r: Flight) => r.status },
];

function sortRows(rows: Flight[], sort: SortState | null): Flight[] {
    if (sort === null) {
        return rows;
    }
    const key = sort.column as keyof Flight;
    const factor = sort.direction === 'ascending' ? 1 : -1;

    return [...rows].sort(
        (a, b) =>
            factor *
            String(a[key]).localeCompare(String(b[key]), 'nl', {
                numeric: true,
            }),
    );
}

export function ComplexShowcase() {
    const { formatMoney } = useTranslation();
    const [sort, setSort] = useState<SortState | null>(null);
    const [filtered, setFiltered] = useState(true);
    const [airport, setAirport] = useState<string | null>(null);
    const [asyncText, setAsyncText] = useState('');
    const [asyncOptions, setAsyncOptions] = useState<ComboboxOption[]>([]);
    const [asyncLoading, setAsyncLoading] = useState(false);
    const [date, setDate] = useState<string | null>(null);
    const [cancelled, setCancelled] = useState(false);
    const rows = useMemo(() => sortRows(FLIGHTS, sort), [sort]);

    return (
        <>
            <section aria-labelledby="d-combo" className="flex flex-col gap-4">
                <h2 id="d-combo" className="text-xl font-bold">
                    Combobox
                </h2>
                <Combobox
                    label="Departure airport"
                    options={AIRPORTS}
                    value={airport}
                    onChange={setAirport}
                    toggleLabel="Show airports"
                    invalidMessage="Choose an airport from the list, for example Malta (MLA)."
                    placeholder="Type a city or code"
                    description="Type to filter, then pick with the arrow keys."
                />
                <Combobox
                    label="Destination (loads from the server)"
                    options={asyncOptions}
                    filter="none"
                    isLoading={asyncLoading}
                    inputValue={asyncText}
                    onInputChange={(text) => {
                        setAsyncText(text);
                        setAsyncLoading(true);
                        window.setTimeout(() => {
                            setAsyncOptions(
                                AIRPORTS.filter((a) =>
                                    a.label
                                        .toLowerCase()
                                        .includes(text.toLowerCase()),
                                ),
                            );
                            setAsyncLoading(false);
                        }, 300);
                    }}
                />
                <Combobox
                    label="Airport with a server error"
                    options={AIRPORTS}
                    error="Choose an airport from the list."
                    isOptional
                />
            </section>

            <section aria-labelledby="d-date" className="flex flex-col gap-4">
                <h2 id="d-date" className="text-xl font-bold">
                    Date picker
                </h2>
                <DatePicker
                    label="Departure date"
                    name="departure"
                    value={date}
                    onChange={setDate}
                    minValue="2027-06-07"
                    maxValue="2028-06-07"
                    isDateUnavailable={(iso) => iso === '2027-06-15'}
                    isRequired
                />
                <DatePicker
                    label="Date of birth"
                    autoComplete="bday"
                    defaultValue="2000-02-29"
                    isOptional
                />
                <DatePicker label="Disabled date" isDisabled />
            </section>

            <section aria-labelledby="d-table" className="flex flex-col gap-4">
                <h2 id="d-table" className="text-xl font-bold">
                    Table
                </h2>
                <Table
                    caption="Flights (customer table, stacks on small screens)"
                    columns={CUSTOMER_COLUMNS}
                    rows={rows}
                    getRowKey={(r) => r.id}
                    sort={sort}
                    onSortChange={setSort}
                />
                <Table
                    caption="Bookings (staff table, scrolls)"
                    layout="scroll"
                    dense
                    columns={[
                        ...CUSTOMER_COLUMNS,
                        {
                            id: 'ref',
                            header: 'Booking reference',
                            cell: (r: Flight) =>
                                `TE-2027-${r.id.padStart(6, '0')}`,
                        },
                        {
                            id: 'notes',
                            header: 'Remarks for the back office',
                            cell: () =>
                                'No remarks have been added to this booking yet.',
                        },
                    ]}
                    rows={FLIGHTS}
                    getRowKey={(r) => r.id}
                />
                <Table
                    caption="Flights on 1 January"
                    columns={CUSTOMER_COLUMNS}
                    rows={filtered ? [] : FLIGHTS}
                    getRowKey={(r) => r.id}
                    emptyDescription="There are no flights for these filters."
                    filtersActive={filtered}
                    onResetFilters={() => {
                        setFiltered(false);
                    }}
                />
                <Table
                    caption="Flights (loading)"
                    columns={CUSTOMER_COLUMNS}
                    rows={[]}
                    getRowKey={(r) => r.id}
                    isLoading
                    loadingLabel="Loading flights…"
                />
            </section>

            <section aria-labelledby="d-dialog" className="flex flex-col gap-4">
                <h2 id="d-dialog" className="text-xl font-bold">
                    Dialog
                </h2>
                <div className="flex flex-wrap gap-3">
                    <Dialog
                        trigger={
                            <Button variant="danger">Cancel booking</Button>
                        }
                        role="alertdialog"
                        title="Cancel this booking?"
                        description={`You get ${formatMoney(12000, 'EUR')} back, including 21% VAT. This cannot be undone.`}
                        confirm={{
                            label: 'Cancel booking',
                            variant: 'danger',
                            onConfirm: () => {
                                setCancelled(true);
                            },
                        }}
                    />
                    <Dialog
                        trigger={
                            <Button variant="secondary">Read the terms</Button>
                        }
                        title="Terms of travel"
                    >
                        {Array.from({ length: 24 }, (_, index) => (
                            <p key={index} className="mb-3">
                                Clause {index + 1}: the carrier transports the
                                traveller and the baggage under the conditions
                                of the booking.
                            </p>
                        ))}
                    </Dialog>
                </div>
                <p aria-live="off">{cancelled ? 'Booking cancelled.' : ''}</p>
            </section>

            <section aria-labelledby="d-toast" className="flex flex-col gap-4">
                <h2 id="d-toast" className="text-xl font-bold">
                    Toast
                </h2>
                <ToastRegion />
                <div className="flex flex-wrap gap-3">
                    <Button
                        variant="secondary"
                        onPress={() => {
                            showToast({
                                severity: 'success',
                                title: 'Saved',
                                description: 'Your changes are stored.',
                            });
                        }}
                    >
                        Show confirmation
                    </Button>
                    <Button
                        variant="secondary"
                        onPress={() => {
                            showToast({
                                severity: 'danger',
                                title: 'Not saved',
                                description: 'Try again in a moment.',
                            });
                        }}
                    >
                        Show error
                    </Button>
                </div>
            </section>

            <section aria-labelledby="d-pages" className="flex flex-col gap-4">
                <h2 id="d-pages" className="text-xl font-bold">
                    Pagination
                </h2>
                <Pagination
                    page={2}
                    pageCount={9}
                    hrefFor={(n) => `/design?page=${String(n)}`}
                />
                <Pagination
                    page={1}
                    pageCount={9}
                    hrefFor={(n) => `/design?page=${String(n)}`}
                />
                <Pagination
                    page={9}
                    pageCount={9}
                    hrefFor={(n) => `/design?page=${String(n)}`}
                />
            </section>
        </>
    );
}
