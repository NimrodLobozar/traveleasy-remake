import { CalendarIcon } from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Button as AriaButton,
    I18nProvider,
    Input,
    TextField as AriaTextField,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { DEFAULT_TIME_ZONE, intlTag } from '@/lib/i18n/locales';
import { cn } from '@/lib/utils';
import { useBlurAnnounce } from './announcer';
import {
    compareYmd,
    formatDateText,
    fromIso,
    parseDateText,
    toIso,
    toUtcNoon,
} from './date-text';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';

// The calendar (RAC Calendar + @internationalized/date) is a separate chunk, loaded on first open.
const CalendarPopover = lazy(() => import('./calendar-popover'));

export type DatePickerProps = {
    label: string;
    name?: string;
    /** ISO `yyyy-mm-dd` (controlled); `null` for empty. */
    value?: string | null;
    defaultValue?: string | null;
    onChange?: (iso: string | null) => void;
    /** Earliest and latest allowed day, ISO `yyyy-mm-dd`. */
    minValue?: string;
    maxValue?: string;
    /** Days that cannot be picked (dimmed, struck through, exposed as disabled). */
    isDateUnavailable?: (iso: string) => boolean;
    description?: ReactNode;
    error?: string;
    isOptional?: boolean;
    isRequired?: boolean;
    isDisabled?: boolean;
    /** `bday` for a date of birth; defaults to `off`. */
    autoComplete?: string;
    /** Zone that decides which day is "today". Defaults to Europe/Amsterdam. */
    timeZone?: string;
    className?: string;
};

function dateLabel(
    iso: string,
    formatDate: (
        value: Date,
        timeZone?: string,
        options?: Intl.DateTimeFormatOptions,
    ) => string,
): string {
    const ymd = fromIso(iso);

    return ymd === null
        ? iso
        : formatDate(toUtcNoon(ymd), 'UTC', { dateStyle: 'long' });
}

/**
 * Date picker (TE-023, system.md 4.6): one text input that takes a typed date in the locale
 * format (nl `dd-mm-jjjj`, en `dd/mm/yyyy`; ISO and `.` or `/` separators are accepted too), the
 * format shown under the label, a 44 px calendar button and a calendar popover that is operable by
 * keyboard. Parsing happens on blur and Enter; min/max and impossible dates (31 April, 29 February
 * in a non-leap year) give localised errors and keep what was typed. The value is an ISO string
 * and a hidden input with `name` carries it in plain form posts. Dates carry no time zone; the
 * `timeZone` prop only decides what "today" is. Date-only values are never passed through `Date`
 * local time, so a DST day cannot shift them. A date range uses two pickers (no drag, WCAG 2.5.7).
 */
export function DatePicker({
    label,
    name,
    value,
    defaultValue,
    onChange,
    minValue,
    maxValue,
    isDateUnavailable,
    description,
    error,
    isOptional,
    isRequired,
    isDisabled,
    autoComplete = 'off',
    timeZone = DEFAULT_TIME_ZONE,
    className,
}: DatePickerProps) {
    const { t, locale, formatDate } = useTranslation();
    const initial = fromIso(value ?? defaultValue ?? null);
    const [ownIso, setOwnIso] = useState<string | null>(
        initial === null ? null : toIso(initial),
    );
    const iso = value !== undefined ? value : ownIso;
    const [text, setText] = useState(
        initial === null ? '' : formatDateText(initial, locale),
    );
    const [localError, setLocalError] = useState<string | undefined>();
    const [open, setOpen] = useState(false);
    const [seenValue, setSeenValue] = useState(value);
    const [published, setPublished] = useState<string | null | undefined>(
        undefined,
    );
    const groupRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const shownError = error ?? localError;
    const blurAnnounce = useBlurAnnounce(label, shownError);

    // A value from the parent that we did not publish ourselves replaces the text (form reset,
    // loaded record), normalised to the locale format. Echoes of our own value keep what was typed.
    if (value !== seenValue) {
        setSeenValue(value);

        if (value !== published) {
            const next = fromIso(value);
            setText(next === null ? '' : formatDateText(next, locale));
        }
    }

    const publish = (next: string | null) => {
        setOwnIso(next);
        setPublished(next);
        onChange?.(next);
    };

    const commit = (raw: string) => {
        const parsed = parseDateText(raw);

        if (parsed.status === 'empty') {
            setLocalError(
                isRequired === true ? t('date.error.required') : undefined,
            );
            publish(null);

            return;
        }

        if (parsed.status === 'invalid') {
            setLocalError(t('date.error.invalid'));
            publish(null);

            return;
        }
        const min = fromIso(minValue);
        const max = fromIso(maxValue);

        if (min !== null && compareYmd(parsed.date, min) < 0) {
            setLocalError(
                t('date.error.min', {
                    date: dateLabel(toIso(min), formatDate),
                }),
            );
            publish(null);

            return;
        }

        if (max !== null && compareYmd(parsed.date, max) > 0) {
            setLocalError(
                t('date.error.max', {
                    date: dateLabel(toIso(max), formatDate),
                }),
            );
            publish(null);

            return;
        }
        setLocalError(undefined);
        publish(toIso(parsed.date));
    };

    const formatHint = t('date.format');
    const help = (
        <>
            {formatHint}
            {description !== undefined && (
                <>
                    {'. '}
                    {description}
                </>
            )}
        </>
    );

    return (
        <I18nProvider locale={intlTag(locale)}>
            <AriaTextField
                value={text}
                onChange={(next) => {
                    setText(next);
                    setLocalError(undefined);
                }}
                onFocus={blurAnnounce.onFocus}
                onBlur={() => {
                    commit(text);
                    blurAnnounce.onBlur();
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        commit(text);
                    }
                }}
                isInvalid={shownError !== undefined && shownError !== ''}
                isRequired={isRequired}
                isDisabled={isDisabled}
                validationBehavior="aria"
                className={cn('block w-full', className)}
            >
                <FieldLabel isOptional={isOptional}>{label}</FieldLabel>
                <div ref={groupRef} className="relative">
                    <Input
                        autoComplete={autoComplete}
                        inputMode="numeric"
                        className={cn(CONTROL_CLASS, 'pr-14')}
                    />
                    <AriaButton
                        ref={buttonRef}
                        isDisabled={isDisabled}
                        aria-label={t('date.open')}
                        aria-haspopup="dialog"
                        aria-expanded={open}
                        onPress={() => {
                            setOpen(true);
                        }}
                        className="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-md text-text data-[disabled]:text-text-muted"
                    >
                        <CalendarIcon aria-hidden="true" className="size-5" />
                    </AriaButton>
                </div>
                <FieldHelp>{help}</FieldHelp>
                <FieldErrorText>{shownError}</FieldErrorText>
            </AriaTextField>
            {name !== undefined && (
                <input type="hidden" name={name} value={iso ?? ''} />
            )}
            {open && (
                <Suspense fallback={null}>
                    <CalendarPopover
                        triggerRef={groupRef}
                        isOpen={open}
                        onOpenChange={(next) => {
                            setOpen(next);

                            if (!next) {
                                buttonRef.current?.focus();
                            }
                        }}
                        value={iso}
                        min={minValue}
                        max={maxValue}
                        timeZone={timeZone}
                        isDateUnavailable={isDateUnavailable}
                        onSelect={(selected) => {
                            const ymd = fromIso(selected);

                            if (ymd !== null) {
                                setText(formatDateText(ymd, locale));
                            }
                            setLocalError(undefined);
                            publish(selected);
                            setOpen(false);
                            buttonRef.current?.focus();
                        }}
                    />
                </Suspense>
            )}
        </I18nProvider>
    );
}
