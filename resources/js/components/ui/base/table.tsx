import { ArrowDownIcon, ArrowUpIcon, ChevronsUpDownIcon } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { useIsOverflowing } from './overflow';

export type SortDirection = 'ascending' | 'descending';
export type SortState = { column: string; direction: SortDirection };

export type TableColumn<Row> = {
    id: string;
    header: string;
    /** Cell content. Render text as React children, never as HTML strings. */
    cell: (row: Row) => ReactNode;
    sortable?: boolean;
    /** Right-aligned, tabular figures. */
    numeric?: boolean;
    /** Renders the cells of this column as `<th scope="row">`. */
    isRowHeader?: boolean;
};

export type TableProps<Row> = {
    /** Names the table; always in the DOM, visible unless `captionHidden`. */
    caption: string;
    captionHidden?: boolean;
    columns: readonly TableColumn<Row>[];
    rows: readonly Row[];
    getRowKey: (row: Row) => string;
    sort?: SortState | null;
    onSortChange?: (sort: SortState) => void;
    isLoading?: boolean;
    /** Replaces "Loading…" in the live region, e.g. "Vluchten laden…". */
    loadingLabel?: string;
    /** Replaces "No results found". */
    emptyLabel?: string;
    /** The cause, shown under the empty message, e.g. "Geen vluchten op 12 juni." */
    emptyDescription?: ReactNode;
    /** With `onResetFilters`, the empty state offers "Clear filters". */
    filtersActive?: boolean;
    onResetFilters?: () => void;
    /** 40 px rows for staff tables (44 px on touch). */
    dense?: boolean;
    /**
     * `stack` (customer tables): below md every row becomes a card whose cells keep their header
     * text. `scroll` (wide staff tables): scrolls inside a focusable region.
     */
    layout?: 'stack' | 'scroll';
    className?: string;
};

const DIRECTION_ICON = {
    ascending: ArrowUpIcon,
    descending: ArrowDownIcon,
} as const;

/**
 * Table (TE-023, system.md 4.8): a native `<table>` with a caption, `th scope`, sortable columns as
 * real buttons (`aria-sort`, change announced), a scroll region that becomes keyboard-focusable
 * only when it overflows, stacked cards below md, a loading state (`aria-busy`, static skeleton)
 * and an empty state. One persistent polite `role="status"` region carries the loading, empty and
 * sort announcements. Use RAC `Table` instead only for row selection or bulk actions.
 */
export function Table<Row>({
    caption,
    captionHidden = false,
    columns,
    rows,
    getRowKey,
    sort = null,
    onSortChange,
    isLoading = false,
    loadingLabel,
    emptyLabel,
    emptyDescription,
    filtersActive = false,
    onResetFilters,
    dense = false,
    layout = 'stack',
    className,
}: TableProps<Row>) {
    const { t } = useTranslation();
    const captionId = useId();
    const scrollRef = useRef<HTMLDivElement>(null);
    const overflowing = useIsOverflowing(scrollRef);
    const [sortMessage, setSortMessage] = useState('');
    const stacked = layout === 'stack';
    // Below md the stacked layout sets display:block on the table parts, which makes some browsers
    // drop the table semantics. The explicit roles bring them back; they are only added then.
    const semantic = (role: string) => (stacked ? { role } : {});
    const isEmpty = !isLoading && rows.length === 0;
    const emptyText = emptyLabel ?? t('table.empty');
    const status = isLoading
        ? (loadingLabel ?? t('table.loading'))
        : isEmpty
          ? emptyText
          : sortMessage;

    const toggleSort = (column: TableColumn<Row>) => {
        const direction: SortDirection =
            sort?.column === column.id && sort.direction === 'ascending'
                ? 'descending'
                : 'ascending';
        setSortMessage(
            t('table.sorted', {
                column: column.header,
                direction:
                    direction === 'ascending'
                        ? t('table.ascending')
                        : t('table.descending'),
            }),
        );
        onSortChange?.({ column: column.id, direction });
    };

    const rowHeight = stacked
        ? dense
            ? 'md:h-10 pointer-coarse:md:h-11'
            : 'md:h-11'
        : dense
          ? 'h-10 pointer-coarse:h-11'
          : 'h-11';
    // Wide staff tables keep each cell on one line and scroll inside their region.
    const cellPadding = cn('px-3 py-2', !stacked && 'whitespace-nowrap');

    return (
        <div className={className} aria-busy={isLoading || undefined}>
            <output className="sr-only">{status}</output>
            <div
                ref={scrollRef}
                {...(overflowing
                    ? { role: 'region', tabIndex: 0, 'aria-label': caption }
                    : {})}
                className="overflow-x-auto rounded-md"
            >
                <table
                    {...semantic('table')}
                    aria-labelledby={captionId}
                    className={cn(
                        'w-full border-collapse text-left',
                        stacked && 'max-md:block',
                    )}
                >
                    <caption
                        id={captionId}
                        className={cn(
                            'pb-2 text-left font-bold',
                            captionHidden && 'sr-only',
                            stacked && 'max-md:block',
                        )}
                    >
                        {caption}
                    </caption>
                    <thead
                        {...semantic('rowgroup')}
                        className={cn(
                            'bg-surface-2 text-sm',
                            stacked && 'max-md:sr-only',
                        )}
                    >
                        <tr
                            {...semantic('row')}
                            className="border-b-2 border-text"
                        >
                            {columns.map((column) => {
                                const sorted =
                                    sort?.column === column.id
                                        ? sort.direction
                                        : null;
                                const Icon =
                                    sorted === null
                                        ? ChevronsUpDownIcon
                                        : DIRECTION_ICON[sorted];

                                return (
                                    <th
                                        key={column.id}
                                        {...semantic('columnheader')}
                                        scope="col"
                                        aria-sort={
                                            column.sortable
                                                ? (sorted ?? 'none')
                                                : undefined
                                        }
                                        className={cn(
                                            'font-bold',
                                            column.sortable
                                                ? 'p-0'
                                                : cellPadding,
                                            column.numeric && 'text-right',
                                        )}
                                    >
                                        {column.sortable ? (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    toggleSort(column);
                                                }}
                                                className={cn(
                                                    'flex min-h-10 w-full items-center gap-1.5 rounded-sm px-3 py-2 text-left font-bold hover:bg-line pointer-coarse:min-h-11',
                                                    column.numeric &&
                                                        'justify-end text-right',
                                                )}
                                            >
                                                {column.header}
                                                <Icon
                                                    aria-hidden="true"
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        sorted === null &&
                                                            'text-text-muted',
                                                    )}
                                                />
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody
                        {...semantic('rowgroup')}
                        className={cn(stacked && 'max-md:block')}
                    >
                        {isLoading &&
                            [0, 1, 2].map((index) => (
                                <tr
                                    key={index}
                                    {...semantic('row')}
                                    aria-hidden="true"
                                    className={cn(
                                        'border-b border-line',
                                        stacked && 'max-md:block',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.id}
                                            {...semantic('cell')}
                                            className={cn(
                                                cellPadding,
                                                rowHeight,
                                            )}
                                        >
                                            <span className="block h-4 w-3/4 rounded-sm bg-surface-2" />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        {!isLoading &&
                            rows.map((row) => (
                                <tr
                                    key={getRowKey(row)}
                                    {...semantic('row')}
                                    className={cn(
                                        'border-b border-line',
                                        stacked &&
                                            'max-md:mb-3 max-md:block max-md:rounded-md max-md:border max-md:border-border max-md:bg-surface max-md:p-2',
                                    )}
                                >
                                    {columns.map((column) => {
                                        const Cell = column.isRowHeader
                                            ? 'th'
                                            : 'td';

                                        return (
                                            <Cell
                                                key={column.id}
                                                {...semantic(
                                                    column.isRowHeader
                                                        ? 'rowheader'
                                                        : 'cell',
                                                )}
                                                scope={
                                                    column.isRowHeader
                                                        ? 'row'
                                                        : undefined
                                                }
                                                className={cn(
                                                    cellPadding,
                                                    rowHeight,
                                                    'align-middle',
                                                    column.isRowHeader &&
                                                        'font-bold',
                                                    column.numeric &&
                                                        'text-right tabular-nums',
                                                    stacked &&
                                                        'max-md:flex max-md:min-h-11 max-md:items-baseline max-md:justify-between max-md:gap-3 max-md:text-right',
                                                )}
                                            >
                                                {stacked && (
                                                    <span
                                                        aria-hidden="true"
                                                        className="text-left font-bold text-text-muted md:hidden"
                                                    >
                                                        {column.header}
                                                    </span>
                                                )}
                                                <span>{column.cell(row)}</span>
                                            </Cell>
                                        );
                                    })}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>
            {isEmpty && (
                <div className="mt-3 flex flex-col items-start gap-3 rounded-md border-2 border-dashed border-line bg-surface p-4">
                    <p className="font-bold">{emptyText}</p>
                    {emptyDescription !== undefined && (
                        <p className="text-text-muted">{emptyDescription}</p>
                    )}
                    {filtersActive && onResetFilters !== undefined && (
                        <Button variant="secondary" onPress={onResetFilters}>
                            {t('table.reset')}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
