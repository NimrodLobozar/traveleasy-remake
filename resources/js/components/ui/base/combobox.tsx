import { ChevronDownIcon } from 'lucide-react';
import { useId, useState } from 'react';
import type { Key, ReactNode } from 'react';
import {
    Button as AriaButton,
    ComboBox as AriaComboBox,
    I18nProvider,
    Input,
    ListBox,
    ListBoxItem,
    Popover,
} from 'react-aria-components';
import { useTranslation } from '@/lib/i18n';
import { intlTag } from '@/lib/i18n/locales';
import { cn } from '@/lib/utils';
import { useBlurAnnounce } from './announcer';
import {
    CONTROL_CLASS,
    FieldErrorText,
    FieldHelp,
    FieldLabel,
} from './field-parts';

export type ComboboxOption = {
    id: string;
    label: string;
    /** Short code shown in monospace after the label, e.g. an IATA code. */
    code?: string;
};

export type ComboboxProps = {
    label: string;
    options: readonly ComboboxOption[];
    name?: string;
    description?: ReactNode;
    error?: string;
    placeholder?: string;
    isOptional?: boolean;
    isRequired?: boolean;
    isDisabled?: boolean;
    /** Selected option id (controlled). */
    value?: string | null;
    defaultValue?: string | null;
    onChange?: (id: string | null) => void;
    /** The typed text (controlled), for async loading: fetch on change, pass the new `options`. */
    inputValue?: string;
    onInputChange?: (text: string) => void;
    /**
     * `client` filters `options` by the typed text (contains, accent- and case-insensitive).
     * `none` shows `options` as given, for results filtered by the server.
     */
    filter?: 'client' | 'none';
    /** Async loading: shows a status, sets `aria-busy`, and keeps focus in the input. */
    isLoading?: boolean;
    /** Name of the toggle button, e.g. "Toon luchthavens". */
    toggleLabel?: string;
    /** Error when the text on blur matches no option, e.g. "Kies een luchthaven uit de lijst, bijvoorbeeld Malta (MLA).". */
    invalidMessage?: string;
    autoComplete?: string;
    className?: string;
};

function textOf(option: ComboboxOption): string {
    return option.code !== undefined
        ? `${option.label} ${option.code}`
        : option.label;
}

/** Contains-match ignoring case and accents (Malaga finds Málaga). */
function matches(text: string, query: string): boolean {
    const fold = (value: string) =>
        value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

    return fold(text).includes(fold(query));
}

/**
 * Combobox (TE-023, system.md 4.5) on RAC `ComboBox`: input `role="combobox"` with
 * `aria-activedescendant` (DOM focus stays in the input), `aria-expanded`, `aria-controls`,
 * `aria-autocomplete="list"`. Typing opens the list; arrows move, Enter picks, Escape closes and a
 * second Escape reverts, Tab never auto-picks. Result counts are announced by RAC's built-in
 * announcer in the active locale; there is deliberately no second live region for them. The
 * empty text is also rendered in the popover. Text that matches no option on blur is kept and
 * reported ("Kies een luchthaven uit de lijst, ..."). A 44 px toggle button (not in the tab order)
 * opens the list for touch screen-reader users.
 */
export function Combobox({
    label,
    options,
    description,
    error,
    placeholder,
    isOptional,
    isLoading = false,
    filter = 'client',
    toggleLabel,
    invalidMessage,
    className,
    value,
    defaultValue,
    onChange,
    inputValue,
    onInputChange,
    autoComplete = 'off',
    ...props
}: ComboboxProps) {
    const { t, locale } = useTranslation();
    const toggleId = useId();
    const [ownSelected, setOwnSelected] = useState<string | null>(
        defaultValue ?? null,
    );
    const selected = value !== undefined ? value : ownSelected;
    const [ownText, setOwnText] = useState(() => {
        const initial = options.find((o) => o.id === (value ?? defaultValue));

        return initial !== undefined ? textOf(initial) : '';
    });
    const text = inputValue ?? ownText;
    const [localError, setLocalError] = useState<string | undefined>();
    const shownError = error ?? localError;
    const blurAnnounce = useBlurAnnounce(label, shownError);
    const hasError = shownError !== undefined && shownError !== '';

    const setText = (next: string) => {
        setOwnText(next);
        setLocalError(undefined);
        onInputChange?.(next);
    };
    // RAC leaves the text to us because both the text and the selection are controlled.
    const select = (id: string | null) => {
        setOwnSelected(id);
        onChange?.(id);
        const picked = options.find((option) => option.id === id);

        if (picked !== undefined) {
            setText(textOf(picked));
        }
    };
    const handleInput = (next: string) => {
        setText(next);
        const current = options.find((option) => option.id === selected);

        if (
            selected !== null &&
            (current === undefined || textOf(current) !== next)
        ) {
            select(null);
        }
    };

    const visible =
        filter === 'client' &&
        text !== '' &&
        !options.some((o) => o.id === selected && textOf(o) === text)
            ? options.filter((option) => matches(textOf(option), text))
            : options;

    return (
        <I18nProvider locale={intlTag(locale)}>
            <AriaComboBox
                {...props}
                items={visible}
                value={selected}
                onChange={(key: Key | null) => {
                    select(key === null ? null : String(key));
                }}
                inputValue={text}
                onInputChange={handleInput}
                allowsCustomValue
                allowsEmptyCollection
                menuTrigger="input"
                isInvalid={hasError}
                validationBehavior="aria"
                onFocus={blurAnnounce.onFocus}
                onBlur={() => {
                    const exact =
                        selected === null && text.trim() !== ''
                            ? options.find(
                                  (option) =>
                                      textOf(option).toLowerCase() ===
                                          text.trim().toLowerCase() ||
                                      option.label.toLowerCase() ===
                                          text.trim().toLowerCase(),
                              )
                            : undefined;

                    if (exact !== undefined) {
                        select(exact.id);
                    } else if (selected === null && text.trim() !== '') {
                        setLocalError(invalidMessage ?? t('combobox.invalid'));
                    }
                    blurAnnounce.onBlur();
                }}
                className={cn('block w-full', className)}
            >
                <FieldLabel isOptional={isOptional}>{label}</FieldLabel>
                <div className="relative">
                    <Input
                        onKeyDownCapture={(event) => {
                            // Spec 4.5: Tab never auto-picks the highlighted option (RAC would commit it).
                            if (
                                event.key === 'Tab' &&
                                event.currentTarget.getAttribute(
                                    'aria-expanded',
                                ) === 'true'
                            ) {
                                event.stopPropagation();
                            }
                        }}
                        onKeyDown={(event) => {
                            // Spec 4.5: a second Escape (list already closed) clears the text.
                            if (
                                event.key === 'Escape' &&
                                text !== '' &&
                                event.currentTarget.getAttribute(
                                    'aria-expanded',
                                ) !== 'true'
                            ) {
                                handleInput('');
                            }
                        }}
                        aria-busy={isLoading || undefined}
                        placeholder={placeholder}
                        autoComplete={autoComplete}
                        className={cn(CONTROL_CLASS, 'pr-12')}
                    />
                    <AriaButton
                        aria-labelledby={toggleId}
                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-md text-text"
                    >
                        <ChevronDownIcon
                            aria-hidden="true"
                            className="size-5"
                        />
                    </AriaButton>
                    <span id={toggleId} hidden>
                        {toggleLabel ?? t('combobox.toggle')}
                    </span>
                </div>
                {description !== undefined && (
                    <FieldHelp>{description}</FieldHelp>
                )}
                <FieldErrorText>{shownError}</FieldErrorText>
                <output className="sr-only">
                    {isLoading ? t('combobox.loading') : ''}
                </output>
                <Popover className="max-h-[calc(100dvh-2rem)] w-(--trigger-width) overflow-auto rounded-md border-2 border-border bg-surface shadow-(--shadow)">
                    <ListBox
                        className="p-1"
                        renderEmptyState={() => (
                            <div className="px-3 py-3 text-text-muted">
                                {isLoading
                                    ? t('combobox.loading')
                                    : text.trim() === ''
                                      ? t('combobox.none')
                                      : t('combobox.none_for', {
                                            query: text.trim(),
                                        })}
                            </div>
                        )}
                    >
                        {(option: ComboboxOption) => (
                            <ListBoxItem
                                id={option.id}
                                textValue={textOf(option)}
                                className="group flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-sm px-3 text-text data-[focused]:bg-brand data-[focused]:text-on-brand data-[selected]:font-bold forced-colors:data-[focused]:bg-[Highlight] forced-colors:data-[focused]:text-[HighlightText]"
                            >
                                <span>{option.label}</span>
                                {option.code !== undefined && (
                                    <span className="font-mono text-sm text-text-muted group-data-[focused]:text-on-brand forced-colors:group-data-[focused]:text-[HighlightText]">
                                        {option.code}
                                    </span>
                                )}
                            </ListBoxItem>
                        )}
                    </ListBox>
                </Popover>
            </AriaComboBox>
        </I18nProvider>
    );
}
