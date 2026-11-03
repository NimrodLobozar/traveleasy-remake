import { CalendarDate, isSameDay, today } from '@internationalized/date';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { useMemo } from 'react';
import type { RefObject } from 'react';
import {
    Button as AriaButton,
    Calendar,
    CalendarCell,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHeader,
    CalendarHeaderCell,
    Dialog,
    Heading,
    Popover,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { fromIso, toIso } from './date-text';

export type CalendarPopoverProps = {
    triggerRef: RefObject<HTMLElement | null>;
    isOpen: boolean;
    onOpenChange: (isOpen: boolean) => void;
    /** Selected day, ISO `yyyy-mm-dd`. */
    value: string | null;
    min?: string;
    max?: string;
    onSelect: (iso: string) => void;
    /** Zone that decides which day is "today". Explicit, never the runtime's. */
    timeZone: string;
    isDateUnavailable?: (iso: string) => boolean;
};

function toCalendarDate(iso: string | null | undefined): CalendarDate | null {
    const ymd = fromIso(iso);

    return ymd === null ? null : new CalendarDate(ymd.year, ymd.month, ymd.day);
}

const NAV =
    'flex size-11 items-center justify-center rounded-md border-2 border-border bg-surface text-text hover:border-text hover:bg-surface-2 data-[disabled]:cursor-not-allowed data-[disabled]:border-dashed data-[disabled]:border-line data-[disabled]:bg-surface-2 data-[disabled]:text-text-muted';

/**
 * The calendar popover of the DatePicker (TE-023, system.md 4.6), a separate chunk loaded when the
 * calendar is first opened. RAC `Calendar` gives the keyboard model (arrows, PageUp/PageDown for
 * months, Shift+PageUp/PageDown for years, Home/End for the week, Enter, Escape) and announces the
 * visible month. Weeks start on Monday. Unavailable and out-of-range days are dimmed and struck
 * through and exposed as disabled; today is outlined; the selected day is filled, bold and
 * underlined (so it also shows in forced colours).
 */
export default function CalendarPopover({
    triggerRef,
    isOpen,
    onOpenChange,
    value,
    min,
    max,
    onSelect,
    timeZone,
    isDateUnavailable,
}: CalendarPopoverProps) {
    const { t } = useTranslation();
    const now = useMemo(() => today(timeZone), [timeZone]);
    const minValue = toCalendarDate(min);
    const maxValue = toCalendarDate(max);

    return (
        <Popover
            triggerRef={triggerRef}
            isOpen={isOpen}
            onOpenChange={onOpenChange}
            placement="bottom start"
            className="max-h-[calc(100dvh-2rem)] w-[min(21rem,calc(100vw-1rem))] overflow-auto rounded-md border-2 border-border bg-surface p-3 text-text shadow-(--shadow)"
        >
            <Dialog aria-label={t('date.calendar')}>
                <Calendar
                    // oxlint-disable-next-line jsx-a11y/no-autofocus -- the calendar was opened on purpose; focus goes to the selected day
                    autoFocus
                    firstDayOfWeek="mon"
                    aria-label={t('date.calendar')}
                    value={toCalendarDate(value)}
                    minValue={minValue}
                    maxValue={maxValue}
                    isDateUnavailable={
                        isDateUnavailable !== undefined
                            ? (date) =>
                                  isDateUnavailable(
                                      toIso({
                                          year: date.year,
                                          month: date.month,
                                          day: date.day,
                                      }),
                                  )
                            : undefined
                    }
                    onChange={(date) => {
                        onSelect(
                            toIso({
                                year: date.year,
                                month: date.month,
                                day: date.day,
                            }),
                        );
                    }}
                >
                    <header className="mb-2 flex items-center justify-between gap-2">
                        <AriaButton slot="previous" className={NAV}>
                            <ChevronLeftIcon
                                aria-hidden="true"
                                className="size-5"
                            />
                        </AriaButton>
                        <Heading className="flex-1 text-center font-bold capitalize" />
                        <AriaButton slot="next" className={NAV}>
                            <ChevronRightIcon
                                aria-hidden="true"
                                className="size-5"
                            />
                        </AriaButton>
                    </header>
                    <CalendarGrid className="w-full table-fixed border-separate border-spacing-0.5">
                        <CalendarGridHeader>
                            {(day) => (
                                <CalendarHeaderCell className="pb-1 text-sm font-bold text-text-muted">
                                    {day}
                                </CalendarHeaderCell>
                            )}
                        </CalendarGridHeader>
                        <CalendarGridBody>
                            {(date) => (
                                <CalendarCell
                                    date={date}
                                    className={({
                                        isSelected,
                                        isDisabled,
                                        isUnavailable,
                                        isOutsideMonth,
                                    }) =>
                                        cn(
                                            'flex min-h-10 w-full cursor-pointer items-center justify-center rounded-md border-2 border-transparent text-center tabular-nums pointer-coarse:min-h-11',
                                            'hover:bg-surface-2',
                                            isSameDay(date, now) &&
                                                'border-text',
                                            isOutsideMonth && 'invisible',
                                            (isDisabled || isUnavailable) &&
                                                'cursor-not-allowed text-text-muted line-through hover:bg-transparent',
                                            isSelected &&
                                                'border-b-4 border-b-current bg-brand font-bold text-on-brand underline hover:bg-brand-hover forced-colors:bg-[Highlight] forced-colors:text-[HighlightText]',
                                        )
                                    }
                                />
                            )}
                        </CalendarGridBody>
                    </CalendarGrid>
                </Calendar>
            </Dialog>
        </Popover>
    );
}
